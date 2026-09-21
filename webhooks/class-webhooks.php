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

	public static function url_reception() {
		return rest_url( '4u-lodgify/v1/webhook' ) . '?jeton=' . rawurlencode( self::jeton() );
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
				return $fourni && hash_equals( self::jeton(), $fourni );
			},
		) );
	}

	/**
	 * Un evenement arrive : on ne fait JAMAIS confiance a son contenu, on
	 * relit l'API pour le compte concerne. L'ecrivain reste unique.
	 */
	public static function recevoir( WP_REST_Request $requete ) {
		$debut = microtime( true );
		$corps = (array) $requete->get_json_params();
		$evt   = (string) ( $corps['event'] ?? $corps['event_type'] ?? $requete->get_param( 'event' ) ?? '' );
		$pid   = self::extraire_property_id( $corps );

		$comptes = self::comptes_concernes( $pid );
		$fait    = array();
		foreach ( $comptes as $c ) {
			$fait[] = $c['website_id'] . ':' . ( self::resynchroniser( $c['website_id'], $c['api_key'] ) ? 'ok' : 'echec' );
		}
		if ( $pid ) { self::purger_fiches( $pid ); }

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
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value = %s", $property_id ) );
		foreach ( $ids as $id ) {
			if ( function_exists( 'rocket_clean_post' ) ) { rocket_clean_post( (int) $id ); }
			if ( function_exists( 'lodgify_calendar_vider_dates' ) ) { lodgify_calendar_vider_dates( $property_id ); }
			delete_transient( 'lodgify_calendar_prices_' . $property_id );
		}
		return count( $ids );
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

	private static function appel( $chemin, $corps, $cle ) {
		$r = wp_remote_post( self::BASE . $chemin, array(
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

	public static function abonner( $cle, $evenement ) {
		return self::appel( '/subscribe', array(
			'event'      => $evenement,
			'target_url' => self::url_reception(),
		), $cle );
	}

	public static function desabonner( $cle, $id ) {
		return self::appel( '/unsubscribe', array( 'id' => $id ), $cle );
	}
}
