<?php
/**
 * Plugin Name:       4U Lodgify
 * Plugin URI:        https://github.com/4UJules/4u-lodgify
 * Description:       Intégration Lodgify unifiée : comptes, calendrier, webhooks temps réel. Remplace progressivement lodgify-availability-sync.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            4U Real Estate
 * Text Domain:       4u-lodgify
 * Domain Path:       /languages
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FOURU_LODGIFY_VERSION', '1.1.0' );
define( 'FOURU_LODGIFY_FILE', __FILE__ );
define( 'FOURU_LODGIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOURU_LODGIFY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Les modules sont autonomes et montes dans un ordre volontaire :
 * « comptes » est la source des cles API, donc il precede tout le reste.
 *
 * MAIS : tant que lodgify-availability-sync est actif, c'est LUI qui charge sa
 * propre copie de ces deux modules. Les charger ici aussi declarerait les memes
 * classes deux fois - erreur fatale, site blanc.
 *
 * Un test class_exists() ne suffit PAS : « 4u-lodgify » passe avant
 * « lodgify-availability-sync » dans l'ordre alphabetique de chargement, donc
 * au moment ou ce fichier s'execute les classes n'existent pas encore et le
 * garde laisserait passer. On interroge donc directement la liste des
 * extensions actives, disponible des ce stade.
 */
$fouru_ancien_plugin_actif = in_array(
	'lodgify-availability-sync/lodgify-availability-sync.php',
	(array) get_option( 'active_plugins', array() ),
	true
);

if ( ! $fouru_ancien_plugin_actif ) {
	if ( ! class_exists( 'FourU_Lodgify_Comptes' ) && file_exists( FOURU_LODGIFY_DIR . 'lodgify-comptes/bootstrap.php' ) ) {
		require_once FOURU_LODGIFY_DIR . 'lodgify-comptes/bootstrap.php';
	}
	if ( ! function_exists( 'lodgify_calendar_dates' ) && file_exists( FOURU_LODGIFY_DIR . 'lodgify-calendar/bootstrap.php' ) ) {
		require_once FOURU_LODGIFY_DIR . 'lodgify-calendar/bootstrap.php';
	}
}

/* Le module webhooks, lui, se charge toujours : il n'entre en conflit avec
   rien et c'est le seul apport reel de cette version. Il n'utilise les classes
   « comptes » que dans ses methodes, donc apres le chargement de l'ancien
   plugin - l'ordre alphabetique joue cette fois en notre faveur. */

require_once FOURU_LODGIFY_DIR . 'webhooks/bootstrap.php';

register_activation_hook( __FILE__, function () {
	if ( class_exists( 'FourU_Lodgify_Comptes' ) ) { FourU_Lodgify_Comptes::creer_tables(); }
	FourU_Lodgify_Webhooks::creer_tables();
	FourU_Lodgify_Webhooks::jeton();   // genere le secret d'URL si absent
} );

/* Filtre de dates de l'accueil : charge toujours, actif seulement sur option
   (voir FourU_Lodgify_Filtre_Dates::OPTION). Permet de comparer l'ancienne et
   la nouvelle implementation avant de retirer JetBooking. */
require_once FOURU_LODGIFY_DIR . 'filtres/class-filtre-dates.php';
FourU_Lodgify_Filtre_Dates::init();

/**
 * Modules rapatries de lodgify-availability-sync.
 *
 * Le garde ci-dessus ne concerne que « comptes » et « calendrier », dont
 * l'ancien plugin embarque une copie mot pour mot : memes classes, memes
 * fonctions, donc redeclaration fatale si les deux se chargent. Les modules
 * rapatries, eux, portent des noms neufs en FourU_Lodgify_* et n'entrent en
 * collision avec rien : ils peuvent donc se charger TOUJOURS.
 *
 * Contrat d'un module rapatrie, pour que la bascule reste reversible :
 *
 *   1. il se charge dans tous les cas, mais reste INERTE tant que son option
 *      vaut autre chose que 'oui' ;
 *   2. quand son option est levee, il commence par retirer l'enregistrement
 *      equivalent de l'ancien plugin (remove_action / remove_filter, ou
 *      remove_all_actions sur l'action AJAX concernee) AVANT de poser le sien,
 *      sinon les deux repondent et c'est le dernier inscrit qui gagne ;
 *   3. il expose OPTION et init(), comme FourU_Lodgify_Filtre_Dates.
 *
 * La bascule se fait donc par module ET par site, et se defait en remettant
 * l'option a 'non'. Desactiver lodgify-availability-sync devient du nettoyage
 * de fin de course, et non le moment risque.
 *
 * Voir CONSOLIDATION.md, §4 et §5.
 */
foreach ( array(
	/* 'A. synchro'      => 'synchro/class-synchro.php',        FourU_Lodgify_Synchro      */
	/* 'B. reservation'  => 'reservation/class-reservation.php', FourU_Lodgify_Reservation */
	/* 'C. prix'         => 'prix/class-prix.php',               FourU_Lodgify_Prix        */
) as $fouru_module ) {
	$fouru_chemin = FOURU_LODGIFY_DIR . $fouru_module;
	if ( file_exists( $fouru_chemin ) ) {
		require_once $fouru_chemin;
	}
}
unset( $fouru_module, $fouru_chemin );

/**
 * Etat de la bascule, pour l'ecran d'administration et pour le diagnostic.
 *
 * @return array<string,bool> nom du module => actif
 */
function fouru_lodgify_modules_actifs() {
	$etat = array();
	foreach ( array(
		'FourU_Lodgify_Filtre_Dates',
		'FourU_Lodgify_Synchro',
		'FourU_Lodgify_Reservation',
		'FourU_Lodgify_Prix',
	) as $classe ) {
		if ( class_exists( $classe ) && defined( $classe . '::OPTION' ) ) {
			$etat[ $classe ] = ( 'oui' === get_option( constant( $classe . '::OPTION' ), 'non' ) );
		}
	}
	return $etat;
}
