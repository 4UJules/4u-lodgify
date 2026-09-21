<?php
/**
 * Module Webhooks : point d'entree unique.
 *
 * @package FourU_Lodgify
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-webhooks.php';

add_action( 'rest_api_init', array( 'FourU_Lodgify_Webhooks', 'enregistrer_route' ) );

if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-page-webhooks.php';
	new FourU_Lodgify_Page_Webhooks();
}

/* Les tables peuvent manquer si le plugin a ete depose par copie de fichiers
   sans passer par une activation : on verifie a chaque chargement admin. */
add_action( 'admin_init', function () {
	if ( get_option( 'fouru_lodgify_version' ) !== FOURU_LODGIFY_VERSION ) {
		FourU_Lodgify_Webhooks::creer_tables();
		FourU_Lodgify_Webhooks::jeton();
		update_option( 'fouru_lodgify_version', FOURU_LODGIFY_VERSION );
	}
}, 1 );
