<?php
/**
 * Association bien Lodgify <-> fiche du site.
 *
 * Propose la fiche la plus proche par similarite de nom, et ecrit le rental-id
 * sur la fiche ET sur toutes ses traductions Polylang en une fois.
 *
 * @package Lodgify_Comptes
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Association {

	/** Type de contenu des fiches. Filtrable : tous les sites n'utilisent pas le meme. */
	public static function type_fiche() {
		return apply_filters( 'lodgify_comptes/type_fiche', 'propriete' );
	}

	/**
	 * Normalise un titre pour la comparaison : minuscules, sans accents, sans
	 * ponctuation, et surtout sans les prefixes commerciaux qui parasitent la
	 * similarite (« For Sale | », « A115 - », « Villa »…).
	 */
	public static function normaliser( $txt ) {
		$t = (string) $txt;
		$t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
		if ( function_exists( 'remove_accents' ) ) { $t = remove_accents( $t ); }
		$t = strtolower( $t );
		$t = preg_replace( '/^(for sale|a vendre|à vendre)\s*[\|:\-]\s*/u', '', $t );
		$t = preg_replace( '/[^a-z0-9]+/', ' ', $t );
		return trim( preg_replace( '/\s+/', ' ', $t ) );
	}

	/**
	 * Score de proximite entre deux titres, 0 a 100.
	 * On combine deux signaux : la similarite globale, et le partage d'un code
	 * de bien (A115, D405, B256…) qui, lorsqu'il existe, est bien plus fiable
	 * qu'une ressemblance de mots.
	 */
	public static function score( $a, $b ) {
		$na = self::normaliser( $a );
		$nb = self::normaliser( $b );
		if ( '' === $na || '' === $nb ) { return 0; }

		similar_text( $na, $nb, $pct );
		$score = (float) $pct;

		$ca = array(); $cb = array();
		preg_match_all( '/\b([a-d]\s?\d{3})\b/', $na, $ca );
		preg_match_all( '/\b([a-d]\s?\d{3})\b/', $nb, $cb );
		$ca = array_map( function ( $x ) { return preg_replace( '/\s/', '', $x ); }, $ca[1] );
		$cb = array_map( function ( $x ) { return preg_replace( '/\s/', '', $x ); }, $cb[1] );
		if ( $ca && $cb ) {
			$score = array_intersect( $ca, $cb ) ? min( 100, $score + 45 ) : max( 0, $score - 25 );
		}
		return round( $score, 1 );
	}

	/** Fiches candidates : publiees, du bon type, sans rental-id ou avec celui-ci. */
	public static function fiches( $rental_id = '' ) {
		global $wpdb;
		$type = self::type_fiche();
		$sql = "SELECT p.ID, p.post_title, COALESCE(m.meta_value,'') AS rid
		        FROM {$wpdb->posts} p
		        LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rental-id'
		        WHERE p.post_type = %s AND p.post_status = 'publish'";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $type ) );

		// Une seule entree par groupe de traduction : on garde la premiere.
		$vus = array(); $out = array();
		foreach ( (array) $rows as $r ) {
			$groupe = self::groupe( $r->ID );
			$cle = $groupe ? 'g' . $groupe : 'p' . $r->ID;
			if ( isset( $vus[ $cle ] ) ) { continue; }
			$vus[ $cle ] = true;
			$out[] = $r;
		}
		return $out;
	}

	/** Identifiant de groupe de traduction Polylang, ou 0. */
	public static function groupe( $post_id ) {
		if ( ! function_exists( 'pll_get_post_translations' ) ) { return 0; }
		$tr = pll_get_post_translations( $post_id );
		return $tr ? crc32( implode( '|', array_map( 'strval', $tr ) ) ) : 0;
	}

	/** Meilleures suggestions pour un bien Lodgify. */
	public static function suggestions( $nom_lodgify, $limite = 3 ) {
		$out = array();
		foreach ( self::fiches() as $f ) {
			$out[] = array(
				'post_id' => (int) $f->ID,
				'titre'   => $f->post_title,
				'rid'     => $f->rid,
				'score'   => self::score( $nom_lodgify, $f->post_title ),
			);
		}
		usort( $out, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
		return array_slice( $out, 0, $limite );
	}

	/**
	 * Ecrit le rental-id sur la fiche et sur toutes ses traductions.
	 * @return array Liste des post_id effectivement ecrits.
	 */
	public static function associer( $post_id, $rental_id ) {
		$post_id   = (int) $post_id;
		$rental_id = preg_replace( '/[^0-9]/', '', (string) $rental_id );
		if ( ! $post_id || '' === $rental_id ) { return array(); }

		$cibles = array( $post_id );
		if ( function_exists( 'pll_get_post_translations' ) ) {
			foreach ( pll_get_post_translations( $post_id ) as $tid ) { $cibles[] = (int) $tid; }
		}
		$cibles = array_unique( array_filter( $cibles ) );

		$faits = array();
		foreach ( $cibles as $id ) {
			update_post_meta( $id, 'rental-id', $rental_id );
			$faits[] = $id;
		}
		return $faits;
	}

	/** Retire l'association (et ses traductions). */
	public static function dissocier( $post_id ) {
		$cibles = array( (int) $post_id );
		if ( function_exists( 'pll_get_post_translations' ) ) {
			foreach ( pll_get_post_translations( $post_id ) as $tid ) { $cibles[] = (int) $tid; }
		}
		foreach ( array_unique( array_filter( $cibles ) ) as $id ) {
			delete_post_meta( $id, 'rental-id' );
		}
	}
}

/**
 * Recherche de biens Lodgify et liste deroulante sur la fiche.
 *
 * L'association par similarite de nom ne fonctionne que lorsque les deux cotes
 * partagent un code (A115, D405). Sur des sites dont les titres sont des
 * accroches commerciales, elle ne peut rien deviner : on propose donc une
 * recherche libre et une liste deroulante, ou l'utilisateur choisit.
 */
class FourU_Lodgify_Recherche {

	/** Biens des comptes actifs, filtres sur un terme libre (nom ou identifiant). */
	public static function chercher( $terme = '', $limite = 40 ) {
		$terme = trim( (string) $terme );
		$out   = array();
		foreach ( FourU_Lodgify_Comptes::biens_actifs() as $b ) {
			if ( '' !== $terme ) {
				$foin = FourU_Lodgify_Association::normaliser( $b->nom . ' ' . $b->property_id . ' ' . $b->compte_nom );
				if ( false === strpos( $foin, FourU_Lodgify_Association::normaliser( $terme ) ) ) { continue; }
			}
			$out[] = array(
				'property_id' => $b->property_id,
				'nom'         => $b->nom,
				'compte'      => $b->compte_nom,
				'website_id'  => $b->website_id,
				'devise'      => $b->devise,
				'photo'       => $b->photo,
				'chambres'    => (int) $b->chambres,
				'capacite'    => (int) $b->capacite,
			);
			if ( count( $out ) >= $limite ) { break; }
		}
		return $out;
	}

	/** Encart sur l'ecran d'edition d'une fiche. */
	public static function init_metabox() {
		add_action( 'add_meta_boxes', function () {
			add_meta_box(
				'fouru-lodgify-bien',
				__( 'Bien Lodgify', '4u-lodgify' ),
				array( __CLASS__, 'rendu_metabox' ),
				FourU_Lodgify_Association::type_fiche(),
				'side',
				'high'
			);
		} );
		add_action( 'save_post', array( __CLASS__, 'sauver_metabox' ), 10, 2 );
	}

	public static function rendu_metabox( $post ) {
		$actuel = (string) get_post_meta( $post->ID, 'rental-id', true );
		$biens  = self::chercher( '', 500 );
		wp_nonce_field( 'fouru_metabox', 'fouru_metabox_nonce' );
		if ( ! $biens ) {
			echo '<p>' . esc_html__( "Aucun compte Lodgify actif sur ce site. Activez-en un dans « Lodgify › Comptes ».", '4u-lodgify' ) . '</p>';
			return;
		}
		echo '<select name="fouru_rental_id" style="width:100%">';
		echo '<option value="">' . esc_html__( '— aucun —', '4u-lodgify' ) . '</option>';
		$compte = '';
		foreach ( $biens as $b ) {
			if ( $compte !== $b['compte'] ) {
				if ( '' !== $compte ) { echo '</optgroup>'; }
				$compte = $b['compte'];
				echo '<optgroup label="' . esc_attr( $compte ?: $b['website_id'] ) . '">';
			}
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $b['property_id'] ),
				selected( $actuel, $b['property_id'], false ),
				esc_html( sprintf( '%s — %s%s',
					$b['nom'] ?: $b['property_id'],
					$b['property_id'],
					$b['chambres'] ? sprintf( ' · %d ch. · %d pers.', $b['chambres'], $b['capacite'] ) : ''
				) )
			);
		}
		if ( '' !== $compte ) { echo '</optgroup>'; }
		echo '</select>';
		echo '<p class="description">' . esc_html__( "Le rental-id est écrit sur cette fiche et sur ses traductions.", '4u-lodgify' ) . '</p>';
	}

	public static function sauver_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['fouru_metabox_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fouru_metabox_nonce'] ) ), 'fouru_metabox' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$rid = sanitize_text_field( wp_unslash( $_POST['fouru_rental_id'] ?? '' ) );
		if ( '' === $rid ) { FourU_Lodgify_Association::dissocier( $post_id ); }
		else { FourU_Lodgify_Association::associer( $post_id, $rid ); }
	}
}
