<?php
/**
 * Module autonome « Comptes Lodgify ».
 *
 * Point d'entree unique : un seul require_once depuis le plugin hote. Le
 * dossier entier se deplace tel quel vers 4u-lodgify.
 *
 * Il devient la source des cles API : le plugin hote ne doit plus jamais lire
 * son tableau $api_keys code en dur.
 *
 * @package Lodgify_Comptes
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'LODGIFY_COMPTES_VERSION' ) ) {
	define( 'LODGIFY_COMPTES_VERSION', '1.1.0' );
	define( 'LODGIFY_COMPTES_DIR', plugin_dir_path( __FILE__ ) );
	define( 'LODGIFY_COMPTES_URL', plugin_dir_url( __FILE__ ) );
}

require_once LODGIFY_COMPTES_DIR . 'class-comptes.php';
require_once LODGIFY_COMPTES_DIR . 'class-api.php';
require_once LODGIFY_COMPTES_DIR . 'class-association.php';

if ( is_admin() ) {
	require_once LODGIFY_COMPTES_DIR . 'admin/class-page-comptes.php';
	new FourU_Lodgify_Page_Comptes();
	FourU_Lodgify_Recherche::init_metabox();
}

/* Tables et reprise des comptes existants : verifiees a chaque chargement
   admin, car le module peut etre deploye par copie de fichiers sans passer
   par une activation de plugin. */
add_action( 'admin_init', function () {
	if ( get_option( 'lodgify_comptes_version' ) !== LODGIFY_COMPTES_VERSION ) {
		FourU_Lodgify_Comptes::creer_tables();
		FourU_Lodgify_Comptes::importer_ancien_plugin();
		update_option( 'lodgify_comptes_version', LODGIFY_COMPTES_VERSION );
	}
}, 1 );

/**
 * Cle API d'un compte, pour le plugin hote.
 * A utiliser partout a la place du tableau code en dur.
 */
function lodgify_cle_api( $website_id ) {
	if ( ! class_exists( 'FourU_Lodgify_Comptes' ) ) { return ''; }
	return FourU_Lodgify_Comptes::cle_api( $website_id );
}

/** Tous les comptes actifs, au format attendu par l'ancien code. */
function lodgify_comptes_actifs() {
	$out = array();
	if ( ! class_exists( 'FourU_Lodgify_Comptes' ) ) { return $out; }
	foreach ( FourU_Lodgify_Comptes::tous() as $c ) {
		if ( ! $c->actif ) { continue; }
		$cle = FourU_Lodgify_Comptes::dechiffrer( $c->cle_chiffree );
		if ( '' === $cle ) { continue; }
		$out[] = array(
			'id'            => 'api_' . $c->website_id,
			'name'          => $c->nom,
			'website_id'    => $c->website_id,
			'api_key'       => $cle,
			'checkout_slug' => $c->checkout_slug,
			'currency'      => $c->devise,
			'active'        => true,
		);
	}
	return $out;
}

/* Le plugin hote lit ses comptes via l'option lodgify_api_configurations.
   On l'alimente depuis le module : une seule source, plus de cles en dur. */
add_filter( 'option_lodgify_api_configurations', function ( $valeur ) {
	$comptes = lodgify_comptes_actifs();
	return $comptes ? $comptes : $valeur;
}, 10, 1 );
