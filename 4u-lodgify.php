<?php
/**
 * Plugin Name:       4U Lodgify
 * Plugin URI:        https://github.com/4UJules/4u-lodgify
 * Description:       Intégration Lodgify unifiée : comptes, calendrier, webhooks temps réel. Remplace progressivement lodgify-availability-sync.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            4U Real Estate
 * Text Domain:       4u-lodgify
 * Domain Path:       /languages
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FOURU_LODGIFY_VERSION', '1.0.0' );
define( 'FOURU_LODGIFY_FILE', __FILE__ );
define( 'FOURU_LODGIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOURU_LODGIFY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Les modules sont autonomes et montes dans un ordre volontaire :
 * « comptes » est la source des cles API, donc il precede tout le reste.
 *
 * Tant que lodgify-availability-sync tourne encore, ces deux modules sont
 * charges par LUI et pas par nous : on ne les monte ici que s'ils ne sont pas
 * deja presents, sinon PHP planterait sur une redeclaration de classe.
 */
if ( ! class_exists( 'FourU_Lodgify_Comptes' ) && file_exists( FOURU_LODGIFY_DIR . 'lodgify-comptes/bootstrap.php' ) ) {
	require_once FOURU_LODGIFY_DIR . 'lodgify-comptes/bootstrap.php';
}
if ( ! function_exists( 'lodgify_calendar_dates' ) && file_exists( FOURU_LODGIFY_DIR . 'lodgify-calendar/bootstrap.php' ) ) {
	require_once FOURU_LODGIFY_DIR . 'lodgify-calendar/bootstrap.php';
}

require_once FOURU_LODGIFY_DIR . 'webhooks/bootstrap.php';

register_activation_hook( __FILE__, function () {
	if ( class_exists( 'FourU_Lodgify_Comptes' ) ) { FourU_Lodgify_Comptes::creer_tables(); }
	FourU_Lodgify_Webhooks::creer_tables();
	FourU_Lodgify_Webhooks::jeton();   // genere le secret d'URL si absent
} );
