<?php
/**
 * Client API Lodgify : test de connexion, biens, frais, taxes, devis.
 *
 * Les endpoints et leurs particularites ont ete releves et mesures en
 * septembre 2026 :
 *   - /v2/properties        liste les biens du compte (devise incluse) ;
 *   - /v2/rates/calendar    tarifs PAR DATE + rate_settings (frais, taxes,
 *                           promotions) ; ne renvoie ni frais ni taxes au
 *                           premier niveau, tout est dans rate_settings ;
 *   - /v2/quote/{id}        total exact d'un sejour ; total_including_vat peut
 *                           etre null (compte 453125) - il faut alors sommer
 *                           les postes ;
 *   - /v2/availability      ignore page/pageSize.
 * Quotas documentes : 750 req/min sur v2, 10 req/min sur les flux .ics.
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_API {

	const BASE = 'https://api.lodgify.com';

	private $cle;

	public function __construct( $cle_api ) {
		$this->cle = (string) $cle_api;
	}

	private function get( $chemin, $args = array(), $timeout = 20 ) {
		$url = add_query_arg( $args, self::BASE . $chemin );
		$r   = wp_remote_get( $url, array(
			'headers' => array( 'X-ApiKey' => $this->cle, 'accept' => 'application/json' ),
			'timeout' => $timeout,
		) );
		if ( is_wp_error( $r ) ) { return $r; }
		$code = (int) wp_remote_retrieve_response_code( $r );
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'http', sprintf( 'HTTP %d', $code ), array( 'body' => $body ) );
		}
		return $body;
	}

	/* FOURU_PAGINATION_BIENS_2026-09-21 */
	const TAILLE_PAGE = 50;
	const MAX_PAGES   = 60;

	/** Total annonce par Lodgify lors du dernier appel a biens(). */
	private $total_annonce = null;

	public function total_annonce() { return $this->total_annonce; }

	/**
	 * Liste des biens du compte, TOUTES les pages.
	 *
	 * /v2/properties pagine par 50 et IGNORE une taille superieure : size=200
	 * renvoie quand meme 50 items, avec la meme signature d'ids que la page 1
	 * (mesure du 21/09/2026, compte 453125 : 119 biens annonces, pages 50+50+19).
	 * Deux regles en decoulent :
	 *   - on s'arrete sur le TOTAL annonce par includeCount=true (champ "count"),
	 *     jamais sur une page incomplete : c'est exactement le piege qui avait
	 *     fait boucler /v2/availability a l'infini ;
	 *   - on dedoublonne par id et on sort des qu'une page n'apporte rien de
	 *     neuf, au cas ou l'endpoint ignorerait aussi "page".
	 */
	public function biens() {
		$out = array();
		$vus = array();
		$this->total_annonce = null;

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$d = $this->get( '/v2/properties', array(
				'includeCount' => 'true',
				'includeInOut' => 'false',
				'page'         => $page,
				'size'         => self::TAILLE_PAGE,
			) );
			if ( is_wp_error( $d ) ) { return $d; }

			$items = isset( $d['items'] ) ? $d['items'] : ( is_array( $d ) ? $d : array() );
			if ( null === $this->total_annonce && isset( $d['count'] ) ) {
				$this->total_annonce = (int) $d['count'];
			}
			if ( ! $items ) { break; }

			$nouveaux = 0;
			foreach ( (array) $items as $b ) {
				if ( empty( $b['id'] ) ) { continue; }
				$pid = (string) $b['id'];
				if ( isset( $vus[ $pid ] ) ) { continue; }
				$vus[ $pid ] = 1;
				$nouveaux++;

				$rt = '';
				if ( ! empty( $b['rooms'][0]['id'] ) )          { $rt = $b['rooms'][0]['id']; }
				elseif ( ! empty( $b['room_types'][0]['id'] ) ) { $rt = $b['room_types'][0]['id']; }
				$photo = '';
				if ( ! empty( $b['image_url'] ) )            { $photo = $b['image_url']; }
				elseif ( ! empty( $b['images'][0]['url'] ) ) { $photo = $b['images'][0]['url']; }

				$out[] = array(
					'property_id'  => $pid,
					'room_type_id' => (string) $rt,
					'nom'          => (string) ( $b['name'] ?? '' ),
					'devise'       => (string) ( $b['currency_code'] ?? '' ),
					'photo'        => (string) $photo,
					'chambres'     => (int) ( $b['bedrooms'] ?? ( $b['rooms'][0]['bedrooms'] ?? 0 ) ),
					'capacite'     => (int) ( $b['max_people'] ?? ( $b['rooms'][0]['max_people'] ?? 0 ) ),
				);
			}

			if ( 0 === $nouveaux ) { break; }
			if ( null !== $this->total_annonce && count( $out ) >= $this->total_annonce ) { break; }
		}

		if ( null === $this->total_annonce ) { $this->total_annonce = count( $out ); }
		return $out;
	}

	/** Frais, taxes, promotions et devise, lus dans rate_settings. */
	public function reglages_tarifaires( $property_id, $room_type_id ) {
		$debut = gmdate( 'Y-m-d' );
		$fin   = gmdate( 'Y-m-d', strtotime( '+5 days' ) );
		$d = $this->get( '/v2/rates/calendar', array(
			'RoomTypeId' => $room_type_id, 'HouseId' => $property_id,
			'StartDate'  => $debut, 'EndDate' => $fin,
		) );
		if ( is_wp_error( $d ) ) { return $d; }
		$rs = isset( $d['rate_settings'] ) ? $d['rate_settings'] : array();
		$frais = array();
		foreach ( (array) ( $rs['fees'] ?? array() ) as $f ) {
			$frais[] = array(
				'nom'     => (string) ( $f['name'] ?? '' ),
				'montant' => isset( $f['price']['amount'] ) ? (float) $f['price']['amount'] : null,
				'pct'     => isset( $f['price']['percentage'] ) ? (float) $f['price']['percentage'] : null,
			);
		}
		$taxes = array();
		foreach ( (array) ( $rs['taxes'] ?? array() ) as $t ) {
			$taxes[] = array(
				'nom'     => (string) ( $t['name'] ?? '' ),
				'montant' => isset( $t['price']['amount'] ) ? (float) $t['price']['amount'] : null,
				'pct'     => isset( $t['price']['percentage'] ) ? (float) $t['price']['percentage'] : null,
			);
		}
		return array(
			'devise'          => (string) ( $rs['currency_code'] ?? '' ),
			'frais'           => $frais,
			'taxes'           => $taxes,
			'promotions'      => count( (array) ( $rs['promotions'] ?? array() ) ),
			'vat_exclusif'    => ! empty( $rs['is_vat_exclusive'] ),
		);
	}

	/**
	 * Total d'un sejour. Somme les postes quand total_including_vat est null,
	 * en respectant is_negative (les promotions comptent en negatif).
	 */
	public function devis( $property_id, $room_type_id, $arrivee, $depart, $voyageurs = 2 ) {
		$d = $this->get( '/v2/quote/' . rawurlencode( $property_id ), array(
			'arrival' => $arrivee, 'departure' => $depart,
			'roomTypes[0].Id' => $room_type_id, 'roomTypes[0].People' => max( 1, (int) $voyageurs ),
		) );
		if ( is_wp_error( $d ) ) { return $d; }
		if ( empty( $d[0] ) ) { return new WP_Error( 'vide', 'Devis vide.' ); }

		$q = $d[0];
		$postes = array(); $somme = 0.0; $des_postes = false;
		foreach ( (array) ( $q['room_types'] ?? array() ) as $rt ) {
			foreach ( (array) ( $rt['price_types'] ?? array() ) as $pt ) {
				if ( empty( $pt['subtotal'] ) ) { continue; }
				$m = (float) $pt['subtotal'];
				if ( ! empty( $pt['is_negative'] ) ) { $m = -$m; }
				$somme += $m; $des_postes = true;
				$postes[] = array( 'libelle' => (string) ( $pt['description'] ?? '' ), 'montant' => round( $m, 2 ) );
			}
		}
		$total = isset( $q['total_including_vat'] ) ? (float) $q['total_including_vat'] : ( $des_postes ? round( $somme, 2 ) : null );

		return array(
			'total'  => null === $total ? null : round( $total, 2 ),
			'devise' => (string) ( $q['currency_code'] ?? '' ),
			'postes' => $postes,
		);
	}

	/**
	 * Test de connexion : nombre de biens, devise, et un devis reel sur le
	 * premier bien disponible, pour verifier que la cle donne bien acces aux
	 * tarifs et pas seulement a la liste.
	 */
	public function tester() {
		$biens = $this->biens();
		if ( is_wp_error( $biens ) ) {
			return array( 'ok' => false, 'message' => 'Connexion refusée : ' . $biens->get_error_message() );
		}
		$res = array(
			'ok'      => true,
			'biens'   => count( $biens ),
			'total'   => $this->total_annonce,
			'devise'  => $biens ? $biens[0]['devise'] : '',
			'devis'   => null,
			'message' => sprintf( '%1$d bien(s) lus, %2$d annoncés par Lodgify.', count( $biens ), (int) $this->total_annonce ),
		);
		// Devis test : on cherche une plage qui passe, sur les premiers biens.
		foreach ( array_slice( $biens, 0, 4 ) as $b ) {
			if ( '' === $b['room_type_id'] ) { continue; }
			foreach ( array( 30, 60, 120, 200 ) as $jours ) {
				$a = gmdate( 'Y-m-d', strtotime( "+{$jours} days" ) );
				$z = gmdate( 'Y-m-d', strtotime( '+' . ( $jours + 7 ) . ' days' ) );
				$q = $this->devis( $b['property_id'], $b['room_type_id'], $a, $z );
				if ( ! is_wp_error( $q ) && null !== $q['total'] ) {
					$res['devis'] = array(
						'bien' => $b['nom'] ?: $b['property_id'],
						'du'   => $a, 'au' => $z,
						'total' => $q['total'], 'devise' => $q['devise'] ?: $res['devise'],
					);
					$res['message'] .= sprintf( ' Devis test : %s %s du %s au %s.',
						$q['total'], $q['devise'] ?: $res['devise'], $a, $z );
					break 2;
				}
			}
		}
		if ( ! $res['devis'] ) {
			$res['message'] .= ' Aucun devis test n\'a abouti (dates indisponibles ou séjour minimum) — la lecture des biens fonctionne.';
		}
		return $res;
	}
}
