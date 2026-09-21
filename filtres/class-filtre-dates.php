<?php
/**
 * Filtre de dates de la page d'accueil, sans JetBooking.
 *
 * JetBooking ne s'accroche a JetSmartFilters qu'a UN seul endroit :
 * `jet-smart-filters/query/final-query`, ou il repere l'entree de meta_query
 * dont la cle vaut « checkin_checkout », la retire, et renseigne
 * `post__not_in` avec les logements indisponibles sur la periode.
 *
 * Ce module refait exactement cela, mais en lisant `lodgify_availabilities`
 * - la table alimentee par l'ecrivain unique - au lieu de
 * `jet_apartment_bookings`. C'est la derniere dependance a JetBooking pour
 * le filtre d'accueil : une fois ce module actif, JetBooking peut partir.
 *
 * Il reste INACTIF tant que l'option `fouru_lodgify_filtre_dates` ne vaut pas
 * « oui » : on compare d'abord les deux implementations bien par bien.
 *
 * @package FourU_Lodgify
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Filtre_Dates {

	const OPTION = 'fouru_lodgify_filtre_dates';

	public static function init() {
		/* Priorite 20 : apres JetBooking (priorite 10 par defaut) tant que les
		   deux cohabitent. Si JetBooking a deja renseigne post__not_in, on
		   compare sans ecraser - voir plus bas. */
		add_filter( 'jet-smart-filters/query/final-query', array( __CLASS__, 'appliquer' ), 20 );
	}

	public static function actif() {
		return 'oui' === get_option( self::OPTION, 'non' );
	}

	/**
	 * Normalise une borne de date. JetSmartFilters peut transmettre un
	 * horodatage ou une chaine : on accepte les deux plutot que de parier.
	 */
	public static function normaliser( $valeur ) {
		$v = trim( (string) $valeur );
		if ( '' === $v ) { return ''; }
		if ( ctype_digit( $v ) && strlen( $v ) >= 9 ) {
			return gmdate( 'Y-m-d', (int) $v );
		}
		$t = strtotime( $v );
		return $t ? gmdate( 'Y-m-d', $t ) : '';
	}

	/**
	 * Logements indisponibles entre deux dates.
	 *
	 * Un sejour du 10 au 14 occupe les NUITS du 10 au 13 : la nuit du depart
	 * n'est pas occupee. La comparaison est donc stricte sur la borne haute,
	 * sinon un logement libere le 14 serait exclu a tort.
	 *
	 * @return array Liste d'ID de fiches.
	 */
	public static function biens_indisponibles( $from, $to ) {
		global $wpdb;

		$a = self::normaliser( $from );
		$z = self::normaliser( $to );
		if ( '' === $a || '' === $z || $z <= $a ) { return array(); }

		$t = $wpdb->prefix . 'lodgify_availabilities';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return array(); }

		// Biens dont au moins une periode bloquee chevauche les nuits demandees.
		$biens = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT property_id FROM {$t}
			 WHERE available = 0 AND start_date < %s AND end_date >= %s",
			$z, $a
		) );
		if ( ! $biens ) { return array(); }

		$dans = implode( ',', array_fill( 0, count( $biens ), '%s' ) );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rental-id'
			 WHERE p.post_status = 'publish' AND m.meta_value IN ({$dans})",
			$biens
		) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Reprend le contrat de JetBooking, et rattrape le cas ou il est passe avant.
	 *
	 * JetBooking s'accroche au meme filtre en priorite 10 : il RETIRE l'entree
	 * `checkin_checkout` de meta_query et pose son propre `post__not_in`. En
	 * priorite 20, chercher cette entree ne donne donc plus rien et ce module
	 * reste inerte - mesure sur thehills le 2026-09-21 : 22 fiches exclues par
	 * JetBooking la ou la table en designe 102.
	 *
	 * On lit donc aussi `jet_booking_period`, que JetBooking renseigne au moment
	 * ou il consomme l'entree. Les deux listes sont additionnees tant que les
	 * deux plugins cohabitent : on n'affiche jamais comme libre ce que l'une des
	 * deux sources dit occupe. Quand JetBooking partira, il ne restera que la
	 * notre.
	 */
	public static function appliquer( $query ) {
		if ( ! self::actif() ) { return $query; }

		$bornes = null;

		// Cas 1 : l'entree est encore la (JetBooking absent, ou passe apres nous).
		if ( ! empty( $query['meta_query'] ) ) {
			foreach ( (array) $query['meta_query'] as $i => $mq ) {
				if ( ! isset( $mq['key'] ) ) { continue; }
				// JetBooking accepte la coquille « chekin_checkout » : on fait pareil.
				if ( 'checkin_checkout' !== $mq['key'] && 'chekin_checkout' !== $mq['key'] ) { continue; }

				$v = (array) $mq['value'];
				if ( count( $v ) < 2 ) { continue; }

				$bornes = array_values( $v );
				$query['jet_booking_period'] = $bornes;
				unset( $query['meta_query'][ $i ] );
				break;
			}
		}

		// Cas 2 : JetBooking est passe avant nous et a deja consomme l'entree.
		if ( null === $bornes && ! empty( $query['jet_booking_period'] ) ) {
			$v = (array) $query['jet_booking_period'];
			if ( count( $v ) >= 2 ) { $bornes = array_values( $v ); }
		}

		if ( null === $bornes ) { return $query; }

		list( $from, $to ) = $bornes;

		$exclus = self::biens_indisponibles( $from, $to );
		if ( $exclus ) {
			$deja = isset( $query['post__not_in'] ) ? (array) $query['post__not_in'] : array();
			$query['post__not_in'] = array_values( array_unique( array_merge( $deja, $exclus ) ) );
		}

		return $query;
	}
}
