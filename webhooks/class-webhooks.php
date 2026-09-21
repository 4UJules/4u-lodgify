<?php
/**
 * Webhooks Lodgify : abonnement, reception, resynchronisation ciblee.
 *
 * Ce que l'API Lodgify permet, releve en septembre 2026 :
 *   - POST https://api.lodgify.com/webhooks/v1/subscribe   {event, target_url}
 *   - GET  https://api.lodgify.com/webhooks/v1/list
 *   - POST https://api.lodgify.com/webhooks/v1/unsubscribe {id}
 *   - 10 tentatives en cas d'echec de livraison ;
 *   - AUCUNE signature : rien ne prouve cryptographiquement l'origine d'un
 *     appel. Le seul controle possible est un jeton secret dans l'URL, ce que
 *     fait ce module. Les donnees recues ne sont donc jamais ecrites telles
 *     quelles : elles servent uniquement a declencher une relecture de l'API.
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Webhooks {

	const TABLE   = 'fouru_lodgify_journal';
	const BASE    = 'https://api.lodgify.com/webhooks/v1';
	const OPT_JETON = 'fouru_lodgify_jeton_webhook';

	/** Evenements utiles : tout ce qui change une dispo ou un prix. */
	public static function evenements() {
		return array(
			'availability_change',
			'rate_change',
			'booking_new_any_status',
			'booking_change',
			'booking_status_change_booked',
			'booking_status_change_tentative',
			'booking_status_change_open',
			'booking_status_change_declined',
		);
	}

	public static function creer_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = $wpdb->prefix . self::TABLE;
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recu_le DATETIME NOT NULL,
			evenement VARCHAR(64) NOT NULL DEFAULT '',
			website_id VARCHAR(32) NOT NULL DEFAULT '',
			property_id VARCHAR(32) NOT NULL DEFAULT '',
			resultat VARCHAR(190) NOT NULL DEFAULT '',
			duree_ms INT NOT NULL DEFAULT 0,
			charge MEDIUMTEXT NULL,
			PRIMARY KEY (id),
			KEY recu_le (recu_le),
			KEY property_id (property_id)
		) " . $wpdb->get_charset_collate() . ";" );
	}

	/** Jeton secret de l'URL de reception, genere une fois. */
	public static function jeton() {
		$j = get_option( self::OPT_JETON );
		if ( ! $j ) {
			$j = wp_generate_password( 40, false, false );
			update_option( self::OPT_JETON, $j, false );
		}
		return $j;
	}

	/**
	 * URL de reception, unique par SITE + COMPTE + EVENEMENT.
	 *
	 * Lodgify refuse en 409 « This callback url already exists » toute
	 * inscription sur une URL deja enregistree, et cette unicite est
	 * GLOBALE, pas par compte : mesure du 21/09/2026, le compte 479060
	 * s'etant abonne le premier sur quatre sites, le compte 453125 se
	 * heurtait a ses URL et ne pouvait s'abonner que sur thehills, seul site
	 * ou 479060 n'est pas actif. Le suffixe « cpt » leve la collision.
	 */
	public static function url_reception( $evenement = '', $website_id = '' ) {
		$u = rest_url( '4u-lodgify/v1/webhook' ) . '?jeton=' . rawurlencode( self::jeton() );
		if ( $website_id ) { $u .= '&cpt=' . rawurlencode( $website_id ); }
		if ( $evenement )  { $u .= '&evt=' . rawurlencode( $evenement ); }
		return $u;
	}

	/* ------------------------------------------------------------------ */
	/* Reception                                                           */
	/* ------------------------------------------------------------------ */

	public static function enregistrer_route() {
		register_rest_route( '4u-lodgify/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'recevoir' ),
			/* Lodgify ne signe pas : le jeton d'URL est le seul controle.
			   Il est compare en temps constant pour ne rien laisser fuiter. */
			'permission_callback' => function ( $requete ) {
				$fourni = (string) $requete->get_param( 'jeton' );
				if ( ! $fourni || ! hash_equals( self::jeton(), $fourni ) ) { return false; }

				/* L'en-tete de signature est « ms-signature », identifie sur une
				   livraison reelle. On le consigne pour pouvoir, une fois
				   l'algorithme confirme, passer du jeton a une vraie
				   verification. Tant que l'algorithme n'est pas etabli, refuser
				   sur ce seul critere couperait des evenements legitimes. */
				$sig = $requete->get_header( 'ms_signature' );
				if ( $sig ) { update_option( 'fouru_lodgify_derniere_signature', substr( $sig, 0, 190 ), false ); }
				return true;
			},
		) );
	}

	/**
	 * Un evenement arrive : on ne fait JAMAIS confiance a son contenu, on
	 * relit l'API pour le compte concerne. L'ecrivain reste unique.
	 */
	public static function recevoir( WP_REST_Request $requete ) {
		$debut = microtime( true );
		$brut = (array) $requete->get_json_params();

		/* Forme reelle de la charge, relevee sur une livraison du 21/09/2026 :
		   un TABLEAU d'evenements, et le champ s'appelle « action », pas
		   « event ». Le bien est au premier niveau pour availability_change,
		   mais sous « booking » pour les evenements de reservation. */
		$corps = ( isset( $brut[0] ) && is_array( $brut[0] ) ) ? $brut[0] : $brut;
		$evt   = (string) ( $corps['action'] ?? $corps['event'] ?? $corps['event_type'] ?? '' );
		$pid   = self::extraire_property_id( $corps );

		$comptes = self::comptes_concernes( $pid );
		$fait    = array();
		foreach ( $comptes as $c ) {
			$fait[] = $c['website_id'] . ':' . ( self::resynchroniser( $c['website_id'], $c['api_key'] ) ? 'ok' : 'echec' );
		}
		if ( $pid ) { self::purger_fiches( $pid ); }
		if ( 'rate_change' === $evt ) { self::purger_tarifs(); }

		$duree = (int) round( ( microtime( true ) - $debut ) * 1000 );
		self::journaliser( $evt, $pid, $comptes ? $comptes[0]['website_id'] : '', implode( ' ', $fait ), $duree, $corps );

		return new WP_REST_Response( array(
			'recu'     => true,
			'evenement'=> $evt,
			'bien'     => $pid,
			'traite'   => $fait,
			'duree_ms' => $duree,
		), 200 );
	}

	/** Le nom du champ varie selon l'evenement : on ratisse large. */
	public static function extraire_property_id( $c ) {
		if ( isset( $c[0] ) && is_array( $c[0] ) ) { $c = $c[0]; }
		foreach ( array( 'property_id', 'propertyId', 'house_id', 'houseId', 'rental_id' ) as $k ) {
			if ( ! empty( $c[ $k ] ) ) { return (string) $c[ $k ]; }
		}
		foreach ( array( 'booking', 'data', 'payload' ) as $sous ) {
			if ( ! empty( $c[ $sous ] ) && is_array( $c[ $sous ] ) ) {
				$r = self::extraire_property_id( $c[ $sous ] );
				if ( $r ) { return $r; }
			}
		}
		if ( ! empty( $c['bookings'][0] ) && is_array( $c['bookings'][0] ) ) {
			return self::extraire_property_id( $c['bookings'][0] );
		}
		return '';
	}

	/**
	 * Compte proprietaire du bien. Si le bien est inconnu - charge inattendue,
	 * bien tout juste cree - on retombe sur tous les comptes actifs plutot que
	 * de ne rien faire : mieux vaut une relecture de trop qu'une dispo fausse.
	 */
	public static function comptes_concernes( $property_id ) {
		$actifs = function_exists( 'lodgify_comptes_actifs' ) ? lodgify_comptes_actifs() : array();
		if ( ! $actifs ) { return array(); }
		if ( $property_id ) {
			global $wpdb;
			$t = $wpdb->prefix . 'fouru_lodgify_biens';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
				$w = $wpdb->get_var( $wpdb->prepare( "SELECT website_id FROM {$t} WHERE property_id = %s LIMIT 1", $property_id ) );
				if ( $w ) {
					foreach ( $actifs as $c ) { if ( (string) $c['website_id'] === (string) $w ) { return array( $c ); } }
				}
			}
		}
		return $actifs;
	}

	/**
	 * Declenche la synchronisation EXISTANTE pour ce compte.
	 * On n'ecrit pas nous-memes : l'ecrivain reste lodgify-availability-sync,
	 * conformement a la regle d'ecrivain unique.
	 */
	public static function resynchroniser( $website_id, $api_key ) {
		if ( ! class_exists( 'Lodgify_Availability_Manager' ) ) { return false; }
		try {
			$m = new Lodgify_Availability_Manager();
			if ( ! method_exists( $m, 'sync_website_availabilities' ) ) { return false; }
			$m->sync_website_availabilities(
				$api_key, $website_id,
				gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+2 years' ) )
			);
			return true;
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/** Purge le cache des fiches qui portent ce bien : sinon le visiteur
	    continuerait de voir l'ancienne disponibilite malgre la resynchro. */
	public static function purger_fiches( $property_id ) {
		global $wpdb;
		$property_id = (string) $property_id;

		/* LE point critique. Les nuits bloquees sont injectees dans la page via
		   window.LodgifyDates, servi depuis un transient de 10 minutes dont la
		   cle est « lodgify_dates_ » + md5(rental_id).
		   Ne pas le vider ici laissait les PAGES afficher l'ancienne
		   disponibilite pendant 10 minutes alors que la base et l'endpoint AJAX
		   etaient deja a jour - constate le 21/09/2026 sur A101 : 8 pages sur 10
		   encore fausses 1 minute apres le webhook.
		   Et il faut passer par delete_transient(), pas par un DELETE sur
		   wp_options : avec Redis Object Cache actif, les transients ne sont pas
		   en base et la purge SQL ne touche rien. */
		delete_transient( 'lodgify_dates_' . md5( $property_id ) );

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value = %s", $property_id ) );
		foreach ( $ids as $id ) {
			if ( function_exists( 'rocket_clean_post' ) ) { rocket_clean_post( (int) $id ); }
		}
		return count( $ids );
	}

	/**
	 * Tarifs : la cle de cache integre les bornes de dates, donc elle n'est pas
	 * enumerable bien par bien. Sur un rate_change on vide donc le groupe
	 * entier, ce qui reste peu couteux (rechargement a la demande).
	 */
	public static function purger_tarifs() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_lcal\\_px\\_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_lcal\\_px\\_%'"
		);
		if ( function_exists( 'wp_cache_flush_group' ) ) { wp_cache_flush_group( 'transient' ); }
	}

	/** Journal borne : on gagne le diagnostic sans laisser gonfler la base. */
	public static function journaliser( $evt, $pid, $website, $resultat, $duree, $charge ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return; }
		$wpdb->insert( $t, array(
			'recu_le'     => current_time( 'mysql' ),
			'evenement'   => substr( (string) $evt, 0, 64 ),
			'website_id'  => substr( (string) $website, 0, 32 ),
			'property_id' => substr( (string) $pid, 0, 32 ),
			'resultat'    => substr( (string) $resultat, 0, 190 ),
			'duree_ms'    => (int) $duree,
			'charge'      => wp_json_encode( $charge ),
		) );
		$wpdb->query( "DELETE FROM {$t} WHERE recu_le < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
	}

	/* ------------------------------------------------------------------ */
	/* Abonnement cote Lodgify                                             */
	/* ------------------------------------------------------------------ */

	private static function appel( $chemin, $corps, $cle, $methode = 'POST' ) {
		$r = wp_remote_request( self::BASE . $chemin, array(
			'method'  => $methode,
			'headers' => array( 'X-ApiKey' => $cle, 'accept' => 'application/json', 'content-type' => 'application/json' ),
			'body'    => wp_json_encode( $corps ),
			'timeout' => 25,
		) );
		if ( is_wp_error( $r ) ) { return $r; }
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $r ),
			'corps'=> json_decode( wp_remote_retrieve_body( $r ), true ),
		);
	}

	public static function lister( $cle ) {
		$r = wp_remote_get( self::BASE . '/list', array(
			'headers' => array( 'X-ApiKey' => $cle, 'accept' => 'application/json' ),
			'timeout' => 25,
		) );
		if ( is_wp_error( $r ) ) { return $r; }
		return json_decode( wp_remote_retrieve_body( $r ), true );
	}

	/**
	 * Abonne un evenement et conserve le secret renvoye par Lodgify.
	 *
	 * Contrairement a ce que laissait croire la documentation, /subscribe
	 * renvoie bien un « secret » par inscription. Il est range en base, jamais
	 * dans le depot, et servira a verifier la signature des appels entrants
	 * une fois l'en-tete utilise identifie sur une livraison reelle.
	 */
	public static function abonner( $cle, $evenement, $website_id = '' ) {
		$r = self::appel( '/subscribe', array(
			'event'      => $evenement,
			'target_url' => self::url_reception( $evenement, $website_id ),
		), $cle );
		if ( ! is_wp_error( $r ) && ! empty( $r['corps']['id'] ) ) {
			$abos = (array) get_option( 'fouru_lodgify_abonnements', array() );
			$abos[ $r['corps']['id'] ] = array(
				'evenement'  => $evenement,
				'website_id' => $website_id,
				'secret'     => (string) ( $r['corps']['secret'] ?? '' ),
				'pose_le'    => current_time( 'mysql' ),
			);
			update_option( 'fouru_lodgify_abonnements', $abos, false );
		}
		return $r;
	}

	/**
	 * Retrait d'un abonnement.
	 *
	 * La methode est DELETE, pas POST : /unsubscribe en POST repond 405
	 * (mesure du 21/09/2026). L'identifiant passe dans le CORPS, pas dans
	 * l'URL - DELETE /unsubscribe/{id} repond 404.
	 */
	public static function desabonner( $cle, $id ) {
		$r = self::appel( '/unsubscribe', array( 'id' => $id ), $cle, 'DELETE' );
		if ( ! is_wp_error( $r ) && $r['code'] < 300 ) {
			$abos = (array) get_option( 'fouru_lodgify_abonnements', array() );
			unset( $abos[ $id ] );
			update_option( 'fouru_lodgify_abonnements', $abos, false );
		}
		return $r;
	}
}
