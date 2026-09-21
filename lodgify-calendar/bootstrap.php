<?php
/**
 * Amorce du module « Calendrier Lodgify ».
 *
 * Point d'entree UNIQUE du module : un seul require_once depuis le plugin hote
 * suffit. Aucune autre modification du plugin n'est necessaire, ce qui permet
 * de deplacer le dossier tel quel vers 4u-lodgify.
 *
 * @package Lodgify_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'LODGIFY_CALENDAR_VERSION' ) ) {
	define( 'LODGIFY_CALENDAR_VERSION', '1.0.0' );
	define( 'LODGIFY_CALENDAR_DIR', plugin_dir_path( __FILE__ ) );
	define( 'LODGIFY_CALENDAR_URL', plugin_dir_url( __FILE__ ) );
}

/* Nuits occupees : calcul, cache 10 min, invalidation a la synchro. */
require_once __DIR__ . '/dates.php';

/* Prix par nuit : endpoint AJAX dedie, un seul appel par periode. */
require_once __DIR__ . '/prices.php';

/** Enregistrement des assets. La version suit le mtime : pas de cache fige. */
add_action( 'wp_enqueue_scripts', function () {
	$js  = LODGIFY_CALENDAR_DIR . 'assets/lodgify-calendar.js';
	$css = LODGIFY_CALENDAR_DIR . 'assets/lodgify-calendar.css';
	wp_register_script( 'lodgify-calendar', LODGIFY_CALENDAR_URL . 'assets/lodgify-calendar.js',
		array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : LODGIFY_CALENDAR_VERSION, true );
	wp_register_style( 'lodgify-calendar', LODGIFY_CALENDAR_URL . 'assets/lodgify-calendar.css',
		array(), file_exists( $css ) ? filemtime( $css ) : LODGIFY_CALENDAR_VERSION );
}, 5 );

/** Enregistrement du widget Elementor. */
add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once LODGIFY_CALENDAR_DIR . 'class-lodgify-calendar-widget.php';
	$widgets_manager->register( new Lodgify_Calendar_Widget() );
} );

/** Compatibilite Elementor < 3.5. */
add_action( 'elementor/widgets/widgets_registered', function ( $widgets_manager ) {
	if ( ! method_exists( $widgets_manager, 'register_widget_type' ) ) { return; }
	require_once LODGIFY_CALENDAR_DIR . 'class-lodgify-calendar-widget.php';
	$widgets_manager->register_widget_type( new Lodgify_Calendar_Widget() );
} );
