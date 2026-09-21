<?php
/**
 * Comptes Lodgify : stockage, chiffrement de la cle, biens affiches.
 *
 * @package FourU_Lodgify
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Comptes {

	const TABLE_COMPTES = 'fouru_lodgify_comptes';
	const TABLE_BIENS   = 'fouru_lodgify_biens';

	/* ------------------------------------------------------------------ */
	/* Tables                                                              */
	/* ------------------------------------------------------------------ */

	public static function creer_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();

		$c = $wpdb->prefix . self::TABLE_COMPTES;
		dbDelta( "CREATE TABLE {$c} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			nom VARCHAR(190) NOT NULL DEFAULT '',
			website_id VARCHAR(32) NOT NULL,
			cle_chiffree TEXT NULL,
			checkout_slug VARCHAR(190) NOT NULL DEFAULT '',
			devise VARCHAR(8) NOT NULL DEFAULT '',
			actif TINYINT(1) NOT NULL DEFAULT 1,
			derniere_verif DATETIME NULL,
			dernier_message TEXT NULL,
			biens_total INT NULL,
			biens_maj DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY website_id (website_id)
		) {$collate};" );

		$b = $wpdb->prefix . self::TABLE_BIENS;
		dbDelta( "CREATE TABLE {$b} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			website_id VARCHAR(32) NOT NULL,
			property_id VARCHAR(32) NOT NULL,
			room_type_id VARCHAR(32) NOT NULL DEFAULT '',
			nom VARCHAR(255) NOT NULL DEFAULT '',
			devise VARCHAR(8) NOT NULL DEFAULT '',
			photo VARCHAR(500) NOT NULL DEFAULT '',
			chambres SMALLINT NOT NULL DEFAULT 0,
			capacite SMALLINT NOT NULL DEFAULT 0,
			affiche TINYINT(1) NOT NULL DEFAULT 1,
			maj DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY bien (website_id, property_id)
		) {$collate};" );
	}

	/* ------------------------------------------------------------------ */
	/* Chiffrement                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * La cle de chiffrement derive des sels WordPress : elle n'est donc jamais
	 * stockee en base, et une copie de la base sans le wp-config ne permet pas
	 * de dechiffrer les cles API.
	 */
	private static function cle_secrete() {
		$base = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$base .= defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '';
		if ( '' === $base ) { $base = DB_NAME . DB_USER; }
		return hash( 'sha256', 'fouru-lodgify|' . $base, true );
	}

	public static function chiffrer( $texte ) {
		if ( '' === (string) $texte ) { return ''; }
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'clair:' . base64_encode( $texte );
		}
		$iv  = openssl_random_pseudo_bytes( 16 );
		$enc = openssl_encrypt( $texte, 'aes-256-cbc', self::cle_secrete(), OPENSSL_RAW_DATA, $iv );
		return 'v1:' . base64_encode( $iv . $enc );
	}

	public static function dechiffrer( $stocke ) {
		if ( '' === (string) $stocke ) { return ''; }
		if ( 0 === strpos( $stocke, 'clair:' ) ) {
			return base64_decode( substr( $stocke, 6 ) );
		}
		if ( 0 !== strpos( $stocke, 'v1:' ) ) { return ''; }
		$brut = base64_decode( substr( $stocke, 3 ) );
		if ( strlen( $brut ) < 17 ) { return ''; }
		$iv  = substr( $brut, 0, 16 );
		$enc = substr( $brut, 16 );
		$out = openssl_decrypt( $enc, 'aes-256-cbc', self::cle_secrete(), OPENSSL_RAW_DATA, $iv );
		return false === $out ? '' : $out;
	}

	/** Empreinte affichable : on ne remontre jamais la cle. */
	public static function masquer( $cle ) {
		$cle = (string) $cle;
		if ( '' === $cle ) { return ''; }
		return substr( $cle, 0, 4 ) . str_repeat( '•', 12 ) . substr( $cle, -4 ) . '  (' . strlen( $cle ) . ' car.)';
	}

	/* ------------------------------------------------------------------ */
	/* Lecture / ecriture                                                  */
	/* ------------------------------------------------------------------ */

	public static function tous() {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_COMPTES;
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY nom, website_id" );
	}

	public static function par_website( $website_id ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_COMPTES;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE website_id = %s", $website_id ) );
	}

	public static function cle_api( $website_id ) {
		$c = self::par_website( $website_id );
		return $c ? self::dechiffrer( $c->cle_chiffree ) : '';
	}

	/**
	 * @param array $d nom, website_id, checkout_slug, cle_api (vide = inchangee), actif
	 */
	public static function enregistrer( $d ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_COMPTES;
		$website = preg_replace( '/[^0-9A-Za-z_-]/', '', (string) ( $d['website_id'] ?? '' ) );
		if ( '' === $website ) { return new WP_Error( 'website', 'Identifiant de site manquant.' ); }

		$champs = array(
			'nom'           => sanitize_text_field( $d['nom'] ?? '' ),
			'website_id'    => $website,
			'checkout_slug' => sanitize_title( $d['checkout_slug'] ?? '' ),
			'actif'         => empty( $d['actif'] ) ? 0 : 1,
		);
		if ( ! empty( $d['cle_api'] ) ) {
			$champs['cle_chiffree'] = self::chiffrer( trim( $d['cle_api'] ) );
		}

		$existe = self::par_website( $website );
		if ( $existe ) {
			$wpdb->update( $t, $champs, array( 'id' => $existe->id ) );
			return (int) $existe->id;
		}
		$wpdb->insert( $t, $champs );
		return (int) $wpdb->insert_id;
	}

	public static function supprimer( $website_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE_COMPTES, array( 'website_id' => $website_id ) );
		$wpdb->delete( $wpdb->prefix . self::TABLE_BIENS, array( 'website_id' => $website_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Biens                                                               */
	/* ------------------------------------------------------------------ */

	public static function biens( $website_id ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_BIENS;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE website_id = %s ORDER BY nom", $website_id ) );
	}

	/** Total de biens annonce par Lodgify pour ce compte (FOURU_PAGINATION_BIENS_2026-09-21). */
	public static function enregistrer_total( $website_id, $total ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . self::TABLE_COMPTES,
			array( 'biens_total' => (int) $total, 'biens_maj' => current_time( 'mysql' ) ),
			array( 'website_id' => $website_id )
		);
	}

	/** Remplace la liste des biens d'un compte en conservant les cases cochees. */
	public static function enregistrer_biens( $website_id, $liste ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_BIENS;
		$deja = array();
		foreach ( self::biens( $website_id ) as $b ) { $deja[ $b->property_id ] = (int) $b->affiche; }

		foreach ( $liste as $b ) {
			$pid = (string) ( $b['property_id'] ?? '' );
			if ( '' === $pid ) { continue; }
			$wpdb->replace( $t, array(
				'website_id'   => $website_id,
				'property_id'  => $pid,
				'room_type_id' => (string) ( $b['room_type_id'] ?? '' ),
				'nom'          => (string) ( $b['nom'] ?? '' ),
				'devise'       => (string) ( $b['devise'] ?? '' ),
				'photo'        => (string) ( $b['photo'] ?? '' ),
				'chambres'     => (int) ( $b['chambres'] ?? 0 ),
				'capacite'     => (int) ( $b['capacite'] ?? 0 ),
				// un bien deja connu garde son reglage ; un bien nouveau est affiche
				'affiche'      => array_key_exists( $pid, $deja ) ? $deja[ $pid ] : 1,
				'maj'          => current_time( 'mysql' ),
			) );
		}
	}

	public static function definir_affichage( $website_id, $affiches ) {
		global $wpdb;
		$t = $wpdb->prefix . self::TABLE_BIENS;
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET affiche = 0 WHERE website_id = %s", $website_id ) );
		foreach ( (array) $affiches as $pid ) {
			$wpdb->update( $t, array( 'affiche' => 1 ), array( 'website_id' => $website_id, 'property_id' => $pid ) );
		}
	}

	/**
	 * Biens des comptes ACTIFS sur ce site uniquement.
	 *
	 * Chaque site a sa propre base, donc sa propre liste de comptes actifs :
	 * aqua-resort ne doit voir que le compte Aqua, dolcebeachresidence que
	 * Dolce. Sans ce filtre, l'association proposait des biens d'un autre
	 * compte - constate sur Aqua, qui se voyait proposer des biens The Hills.
	 */
	public static function biens_actifs() {
		global $wpdb;
		$c = $wpdb->prefix . self::TABLE_COMPTES;
		$b = $wpdb->prefix . self::TABLE_BIENS;
		return $wpdb->get_results(
			"SELECT b.*, c.nom AS compte_nom FROM {$b} b
			 INNER JOIN {$c} c ON c.website_id = b.website_id AND c.actif = 1
			 ORDER BY c.nom, b.nom"
		);
	}

	/* ------------------------------------------------------------------ */
	/* Reprise de l'ancien plugin                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Reprend les comptes la ou ils se trouvent aujourd'hui :
	 *   1. l'option lodgify_api_configurations (source la plus complete) ;
	 *   2. a defaut, le tableau $api_keys code en dur dans l'ancien plugin.
	 * Ne touche jamais a un compte deja present : l'import ne s'execute qu'une
	 * fois, et ne remplace rien de ce que l'utilisateur a saisi.
	 */
	/** Ce site a-t-il des fiches rattachees a un bien de ce compte ? */
	public static function site_utilise_compte( $website_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'lodgify_availabilities';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) !== $t ) { return 1; }
		$n = (int) $wpdb->get_var( $wpdb->prepare(
			/* COLLATE explicite : les deux tables n'ont pas toujours la meme
			   interclassement (utf8mb4_unicode_520_ci cote WordPress,
			   utf8mb4_unicode_ci cote table Lodgify), ce qui fait echouer la
			   jointure sur certains sites. */
			"SELECT COUNT(*) FROM {$wpdb->postmeta} m
			 INNER JOIN {$t} a
			   ON a.property_id COLLATE utf8mb4_general_ci = m.meta_value COLLATE utf8mb4_general_ci
			 WHERE m.meta_key = 'rental-id' AND a.website_id = %s",
			$website_id
		) );
		return $n > 0 ? 1 : 0;
	}

	public static function importer_ancien_plugin() {
		/* FOURU_IMPORT_UNE_FOIS_2026-09-21 : l'import ne doit se produire qu'une
		   seule fois dans la vie du site. Le test « la table est vide » ne suffit
		   pas : un compte supprime volontairement reviendrait a la prochaine
		   montee de version, la table etant alors vide a nouveau. */
		if ( get_option( 'lodgify_comptes_import_fait' ) ) { return 0; }
		update_option( 'lodgify_comptes_import_fait', 1, false );
		if ( self::tous() ) { return 0; }
		$repris = 0;

		foreach ( (array) get_option( 'lodgify_api_configurations', array() ) as $a ) {
			if ( empty( $a['website_id'] ) || empty( $a['api_key'] ) ) { continue; }
			/* Actif seulement si ce site s'en sert vraiment, c'est-a-dire s'il a
			   au moins une fiche rattachee a un bien de ce compte. Un site sans
			   aucune fiche liee recoit le compte en inactif : a l'utilisateur de
			   l'activer s'il le souhaite. */
			self::enregistrer( array(
				'nom'           => $a['name'] ?? ( 'Compte ' . $a['website_id'] ),
				'website_id'    => $a['website_id'],
				'checkout_slug' => $a['checkout_slug'] ?? '',
				'cle_api'       => $a['api_key'],
				'actif'         => self::site_utilise_compte( $a['website_id'] ),
			) );
			if ( ! empty( $a['currency'] ) ) {
				global $wpdb;
				$wpdb->update( $wpdb->prefix . self::TABLE_COMPTES,
					array( 'devise' => $a['currency'] ), array( 'website_id' => $a['website_id'] ) );
			}
			$repris++;
		}
		if ( $repris ) { return $repris; }

		// Repli : le tableau code en dur de l'ancien plugin.
		$fichier = WP_PLUGIN_DIR . '/lodgify-availability-sync/lodgify-availability-sync.php';
		if ( ! file_exists( $fichier ) ) { return 0; }
		$src = file_get_contents( $fichier );
		if ( ! preg_match( '/private \$api_keys = \[(.*?)\];/s', $src, $m ) ) { return 0; }
		if ( ! preg_match_all( "/'key'\s*=>\s*'([^']+)'.*?'website_id'\s*=>\s*'(\d+)'/s", $m[1], $p, PREG_SET_ORDER ) ) { return 0; }
		foreach ( $p as $x ) {
			self::enregistrer( array(
				'nom'        => 'Compte ' . $x[2],
				'website_id' => $x[2],
				'cle_api'    => $x[1],
				'actif'      => 1,
			) );
			$repris++;
		}
		return $repris;
	}
}
