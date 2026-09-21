<?php
/**
 * Prix par nuit du calendrier — source unique : /v2/rates/calendar.
 *
 * Un seul appel pour toute la periode affichee, jamais un appel par date, et
 * jamais le devis /v2/quote : le devis chiffre un sejour, pas une nuit isolee.
 * Verifie le 2026-09-21 sur les deux comptes : la somme des tarifs par date
 * redonne exactement le poste « Room rate » du devis (499029 fev 2027 : 300/nuit
 * jusqu'au 20 puis 325 ; 528613 dec 2026 : 400 ; 528055 oct 2026 : 520).
 *
 * Module AUTONOME : ne lit que des donnees (table des disponibilites et option
 * de configuration), aucune classe du plugin hote.
 *
 * @package Lodgify_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_lodgify_calendar_prices', 'lodgify_calendar_prices' );
add_action( 'wp_ajax_nopriv_lodgify_calendar_prices', 'lodgify_calendar_prices' );

function lodgify_calendar_prices() {

	$rental = isset( $_GET['rental_id'] ) ? preg_replace( '/[^0-9]/', '', $_GET['rental_id'] ) : '';
	$debut  = isset( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] ) : '';
	$fin    = isset( $_GET['end'] ) ? sanitize_text_field( $_GET['end'] ) : '';

	$iso = '/^\d{4}-\d{2}-\d{2}$/';
	if ( '' === $rental || ! preg_match( $iso, $debut ) || ! preg_match( $iso, $fin ) || $fin <= $debut ) {
		wp_send_json_error( array( 'message' => 'parametres invalides' ) );
	}

	// Borne de securite : on ne laisse pas demander dix ans de tarifs.
	if ( ( strtotime( $fin ) - strtotime( $debut ) ) > 400 * DAY_IN_SECONDS ) {
		wp_send_json_error( array( 'message' => 'periode trop longue' ) );
	}

	$cle = 'lcal_px_' . md5( $rental . '|' . $debut . '|' . $fin );
	$cache = get_transient( $cle );
	if ( false !== $cache ) {
		$cache['cache'] = true;
		wp_send_json_success( $cache );
	}

	global $wpdb;
	$ligne = $wpdb->get_row( $wpdb->prepare(
		"SELECT room_type_id, website_id FROM {$wpdb->prefix}lodgify_availabilities
		 WHERE property_id = %s AND room_type_id <> '' LIMIT 1",
		$rental
	) );
	if ( ! $ligne ) {
		wp_send_json_error( array( 'message' => 'bien inconnu' ) );
	}

	// Cle API et devise du compte, depuis la configuration stockee.
	$cle_api = '';
	$devise  = '';
	foreach ( (array) get_option( 'lodgify_api_configurations', array() ) as $api ) {
		if ( isset( $api['website_id'] ) && (string) $api['website_id'] === (string) $ligne->website_id ) {
			$cle_api = isset( $api['api_key'] ) ? $api['api_key'] : '';
			$devise  = isset( $api['currency'] ) ? $api['currency'] : '';
			break;
		}
	}
	if ( '' === $cle_api ) {
		wp_send_json_error( array( 'message' => 'compte introuvable' ) );
	}

	$url = add_query_arg( array(
		'RoomTypeId' => $ligne->room_type_id,
		'HouseId'    => $rental,
		'StartDate'  => $debut,
		'EndDate'    => $fin,
	), 'https://api.lodgify.com/v2/rates/calendar' );

	$rep = wp_remote_get( $url, array(
		'headers' => array( 'X-ApiKey' => $cle_api, 'accept' => 'application/json' ),
		'timeout' => 8,
	) );

	if ( is_wp_error( $rep ) || 200 !== (int) wp_remote_retrieve_response_code( $rep ) ) {
		// Pas de prix, mais le calendrier reste affiche : on ne met pas en cache
		// un echec, pour reessayer a la visite suivante.
		wp_send_json_error( array( 'message' => 'tarifs indisponibles' ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $rep ), true );
	$prix = array();
	$min  = array();
	if ( ! empty( $data['calendar_items'] ) ) {
		foreach ( $data['calendar_items'] as $it ) {
			if ( empty( $it['date'] ) || empty( $it['prices'][0] ) ) { continue; }
			$p = $it['prices'][0];
			if ( ! isset( $p['price_per_day'] ) || null === $p['price_per_day'] ) { continue; }
			$prix[ $it['date'] ] = round( (float) $p['price_per_day'], 2 );
			if ( isset( $p['min_stay'] ) ) { $min[ $it['date'] ] = (int) $p['min_stay']; }
		}
	}

	$symboles = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'ANG' => 'ƒ' );
	$code = strtoupper( (string) $devise );

	$sortie = array(
		'currency' => isset( $symboles[ $code ] ) ? $symboles[ $code ] : $code,
		'prices'   => $prix,
		'min_stay' => $min,
		'cache'    => false,
	);

	set_transient( $cle, $sortie, HOUR_IN_SECONDS );
	wp_send_json_success( $sortie );
}
