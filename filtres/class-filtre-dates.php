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

		$biens = array_merge( $biens, self::biens_sous_sejour_minimum( $a, $z ) );
		$biens = array_values( array_unique( $biens ) );
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
	 * AJAX_DATES_20260922 - recuperer les dates quand JetSmartFilters les perd.
	 *
	 * Quand on actionne un filtre depuis la page de resultats, JSF reconstruit
	 * la requete a partir de ses SEULS widgets de filtre. Charge capturee sur
	 * thehills le 2026-09-22 :
	 *
	 *   action=jet_smart_filters
	 *   provider=jet-engine/filter-vacation-en
	 *   query[_tax_query_building]=1393
	 *
	 * Les dates n'y sont pas : elles etaient arrivees par l'URL en
	 * `meta=checkin_checkout!date:…`, et aucun widget de filtre ne les porte.
	 * Resultat mesure : le bien A105, dont la nuit du 10 novembre est reservee,
	 * reapparaissait comme disponible - un risque de surreservation.
	 *
	 * Le navigateur envoie le referer avec la requete AJAX, et ce referer est
	 * l'URL de la page de resultats, dates comprises. On les y relit.
	 *
	 * @return array|null Bornes [debut, fin] au format Y-m-d, ou null.
	 */
	public static function bornes_depuis_referer() {
		if ( ! wp_doing_ajax() ) { return null; }

		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		if ( '' === $ref ) { return null; }

		// Ne suivre que nos propres pages.
		$hote = wp_parse_url( $ref, PHP_URL_HOST );
		if ( ! $hote || ! in_array( strtolower( $hote ), self::hotes_acceptes(), true ) ) { return null; }

		$qs = wp_parse_url( $ref, PHP_URL_QUERY );
		if ( ! $qs ) { return null; }
		parse_str( $qs, $p );

		$meta = isset( $p['meta'] ) ? (string) $p['meta'] : '';
		if ( '' === $meta || false === strpos( $meta, 'checkin_checkout!date:' ) ) { return null; }

		$part = explode( 'checkin_checkout!date:', $meta );
		$part = explode( ';', $part[1] )[0];
		$deux = explode( '-', $part );
		if ( count( $deux ) !== 2 ) { return null; }

		$iso = static function ( $v ) {
			$m = explode( '.', $v );
			if ( count( $m ) !== 3 ) { return ''; }
			return sprintf( '%04d-%02d-%02d', (int) $m[0], (int) $m[1], (int) $m[2] );
		};
		$a = $iso( $deux[0] );
		$z = $iso( $deux[1] );
		if ( '' === $a || '' === $z || $z <= $a ) { return null; }

		return array( $a, $z );
	}

	/** Hotes du site, pour ne pas suivre un referer etranger. */
	private static function hotes_acceptes() {
		$hotes = array();
		foreach ( array( home_url(), site_url() ) as $u ) {
			$h = wp_parse_url( $u, PHP_URL_HOST );
			if ( $h ) {
				$h = strtolower( $h );
				$hotes[] = $h;
				$hotes[] = ( 0 === strpos( $h, 'www.' ) ) ? substr( $h, 4 ) : 'www.' . $h;
			}
		}
		return array_values( array_unique( $hotes ) );
	}

	/**
	 * MINSTAY_20260922 - biens dont le sejour minimum depasse la duree cherchee.
	 *
	 * Regle etablie sur devis Lodgify reels le 2026-09-22 : c'est le MAXIMUM des
	 * `min_stay` sur les nuits du sejour qui s'applique, pas celui de la nuit
	 * d'arrivee. Verifie sur D403 (nuit du 18/12 a 3, fenetre libre) : 3 et 4
	 * nuits refuses, 5 nuits acceptes a 1738,09 $. La table locale concorde donc
	 * exactement avec ce que le devis accepte ou refuse - on peut filtrer sans
	 * appeler l'API.
	 *
	 * Proposer un bien qu'on ne peut pas reserver est un faux resultat, au meme
	 * titre qu'un bien deja occupe.
	 *
	 * @return array Identifiants Lodgify (property_id).
	 */
	public static function biens_sous_sejour_minimum( $a, $z ) {
		global $wpdb;

		$nuits = (int) round( ( strtotime( $z ) - strtotime( $a ) ) / DAY_IN_SECONDS );
		if ( $nuits < 1 ) { return array(); }

		$t = $wpdb->prefix . 'lodgify_daily_prices';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { return array(); }

		return (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT property_id FROM {$t}
			 WHERE date >= %s AND date < %s
			 GROUP BY property_id
			 HAVING MAX(min_stay) > %d",
			$a, $z, $nuits
		) );
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

		// Cas 3 : requete AJAX de JetSmartFilters.
		if ( null === $bornes ) {
			$bornes = self::bornes_depuis_referer();
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
