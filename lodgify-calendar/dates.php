<?php
/**
 * Nuits occupees d'un bien — calcul et cache.
 *
 * Reprend les deux memes sources que l'endpoint historique :
 *   1. les periodes bloquees de lodgify_availabilities (bornes incluses) ;
 *   2. les reservations de jet_apartment_bookings, pour tous les posts
 *      partageant le meme rental-id (traductions Polylang comprises).
 *
 * POURQUOI ICI : un appel a admin-ajax.php coute 1,4 s d'amorcage WordPress,
 * quoi qu'on mette en cache dedans — mesure du 2026-09-21 : une action vide
 * (heartbeat) coute deja 1,37 s. Mettre la donnee dans la page, qui est elle
 * servie depuis le cache, supprime l'appel au lieu de l'accelerer.
 *
 * @package Lodgify_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function lodgify_calendar_dates( $rental_id ) {

	$rental_id = (string) $rental_id;
	if ( '' === $rental_id ) { return array(); }

	$cle = 'lodgify_dates_' . md5( $rental_id );
	$vu  = get_transient( $cle );
	if ( false !== $vu && is_array( $vu ) ) { return $vu; }

	global $wpdb;
	$nuits = array();

	// 1. periodes bloquees, bornes incluses
	$t_av = $wpdb->prefix . 'lodgify_availabilities';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_av}'" ) === $t_av ) {
		$periodes = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_date, end_date FROM {$t_av} WHERE property_id = %s AND available = 0",
			$rental_id
		) );
		foreach ( (array) $periodes as $p ) {
			$a = strtotime( $p->start_date );
			$b = strtotime( $p->end_date );
			if ( ! $a || ! $b || $b < $a ) { continue; }
			for ( $t = $a; $t <= $b; $t += DAY_IN_SECONDS ) {
				$nuits[ gmdate( 'Y-m-d', $t ) ] = true;
			}
		}
	}

	// 2. reservations JetBooking, pour toutes les traductions du bien
	$t_jb = $wpdb->prefix . 'jet_apartment_bookings';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_jb}'" ) === $t_jb ) {
		$posts = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value = %s",
			$rental_id
		) );
		if ( $posts ) {
			$in  = implode( ',', array_map( 'intval', $posts ) );
			$res = $wpdb->get_results(
				"SELECT check_in_date, check_out_date FROM {$t_jb} WHERE apartment_id IN ({$in})"
			);
			foreach ( (array) $res as $r ) {
				$ci = (int) $r->check_in_date;
				$co = (int) $r->check_out_date;
				if ( ! $ci || ! $co ) { continue; }
				/* Les horodatages portent un decalage d'une seconde (arrivee a
				   00:00:01, depart a 00:00:00). On travaille donc sur les dates
				   et non sur les timestamps : la nuit d'arrivee et la derniere
				   nuit sont ainsi toutes deux prises. */
				$d = strtotime( gmdate( 'Y-m-d', $ci ) );
				$f = strtotime( gmdate( 'Y-m-d', $co ) );
				for ( $t = $d; $t < $f; $t += DAY_IN_SECONDS ) {
					$nuits[ gmdate( 'Y-m-d', $t ) ] = true;
				}
			}
		}
	}

	$liste = array_keys( $nuits );
	sort( $liste );

	set_transient( $cle, $liste, 10 * MINUTE_IN_SECONDS );
	return $liste;
}

/** Invalidation : les dates changent a chaque synchro. */
function lodgify_calendar_vider_dates() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_lodgify\\_dates\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_lodgify\\_dates\\_%'"
	);
}
add_action( 'lodgify_availability_sync_cron', 'lodgify_calendar_vider_dates', 1 );
