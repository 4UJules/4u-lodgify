<?php
/**
 * Widget Elementor : Calendrier Lodgify (lecture seule).
 *
 * Module AUTONOME. Il ne depend que de :
 *   - l'action AJAX 'lodgify_get_unavailable_dates' (meme source que le popup
 *     de reservation), qui renvoie la liste des nuits occupees ;
 *   - la meta 'rental-id' de la fiche pour identifier le bien.
 * Aucun appel a une classe du plugin hote : le dossier entier peut etre
 * deplace tel quel dans 4u-lodgify.
 *
 * @package Lodgify_Calendar
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Lodgify_Calendar_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'lodgify_calendar'; }

	public function get_title() { return __( 'Calendrier Lodgify', 'lodgify-calendar' ); }

	public function get_icon() { return 'eicon-calendar'; }

	public function get_categories() { return array( 'lodgify-availability-sync', 'general' ); }

	public function get_keywords() { return array( 'lodgify', 'calendrier', 'calendar', 'disponibilite' ); }

	public function get_script_depends() { return array( 'lodgify-calendar' ); }

	public function get_style_depends()  { return array( 'lodgify-calendar' ); }

	/* =====================================================================
	 * CONTROLES
	 * ================================================================== */

	protected function register_controls() {

		/* ---------------------- Contenu ---------------------- */
		$this->start_controls_section( 'sec_contenu', array(
			'label' => __( 'Calendrier', 'lodgify-calendar' ),
		) );

		$this->add_control( 'rental_id', array(
			'label'       => __( 'Bien (rental-id)', 'lodgify-calendar' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'vide = la fiche courante', 'lodgify-calendar' ),
			'description' => __( 'Laisser vide pour utiliser la meta rental-id de la fiche affichee.', 'lodgify-calendar' ),
		) );

		$this->add_control( 'months', array(
			'label'   => __( 'Nombre de mois', 'lodgify-calendar' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '2',
			'options' => array( '1' => '1', '2' => '2', '3' => '3' ),
		) );

		$this->add_control( 'months_mobile', array(
			'label'        => __( 'Forcer 1 mois sur mobile', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'selection', array(
			'label'        => __( 'Sélection des dates', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			// Active par defaut : c'est l'usage attendu sur une fiche de location.
			'default'      => 'yes',
			'return_value' => 'yes',
			'description'  => __( 'Permet de choisir arrivée et départ directement dans ce calendrier. Désactivé : calendrier en lecture seule.', 'lodgify-calendar' ),
		) );

		$this->add_control( 'week_start', array(
			'label'   => __( 'Premier jour de la semaine', 'lodgify-calendar' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '1',
			'options' => array(
				'1' => __( 'Lundi', 'lodgify-calendar' ),
				'0' => __( 'Dimanche', 'lodgify-calendar' ),
			),
		) );

		$this->end_controls_section();

		/* ---------------------- Legende ---------------------- */
		$this->start_controls_section( 'sec_legende', array(
			'label' => __( 'Légende', 'lodgify-calendar' ),
		) );

		$this->add_control( 'legend_show', array(
			'label'        => __( 'Afficher la légende', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'legend_free', array(
			'label'     => __( 'Texte « disponible »', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => '',
			'placeholder' => __( 'automatique selon la langue', 'lodgify-calendar' ),
			'condition' => array( 'legend_show' => 'yes' ),
		) );

		$this->add_control( 'legend_booked', array(
			'label'     => __( 'Texte « réservé »', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => '',
			'placeholder' => __( 'automatique selon la langue', 'lodgify-calendar' ),
			'condition' => array( 'legend_show' => 'yes' ),
		) );

		$this->add_control( 'legend_note', array(
			'label'       => __( 'Mention sous la légende', 'lodgify-calendar' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'automatique selon la langue', 'lodgify-calendar' ),
			'description' => __( 'Précise que le prix affiché est le tarif nuitée seul. Vider ce champ ne la masque pas : utiliser l\'interrupteur.', 'lodgify-calendar' ),
			'condition'   => array( 'legend_show' => 'yes' ),
		) );

		$this->add_control( 'legend_note_show', array(
			'label'        => __( 'Afficher la mention', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'legend_show' => 'yes' ),
		) );

		$this->end_controls_section();

		/* ---------------------- Prix (prevu, desactive) ---------------------- */
		$this->start_controls_section( 'sec_prix', array(
			'label' => __( 'Prix par nuit', 'lodgify-calendar' ),
		) );

		$this->add_control( 'price_show', array(
			'label'        => __( 'Afficher le prix sous chaque date', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'description'  => __( 'Emplacement prévu, pas encore alimenté. Sans effet pour l\'instant.', 'lodgify-calendar' ),
		) );

		$this->add_control( 'price_decimals', array(
			'label'        => __( 'Afficher les décimales', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'condition'    => array( 'price_show' => 'yes' ),
		) );

		$this->add_control( 'price_symbol_after', array(
			'label'        => __( 'Symbole après le montant', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'description'  => __( 'Désactivé : $120 — activé : 120 €', 'lodgify-calendar' ),
			'condition'    => array( 'price_show' => 'yes' ),
		) );

		$this->add_control( 'price_color', array(
			'label'     => __( 'Couleur du prix', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-price' => 'color: {{VALUE}};' ),
			'condition' => array( 'price_show' => 'yes' ),
		) );

		$this->add_responsive_control( 'price_size', array(
			'label'      => __( 'Taille du prix', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 7, 'max' => 20 ) ),
			'selectors'  => array( '{{WRAPPER}} .lcal-price' => 'font-size: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'price_show' => 'yes' ),
		) );

		$this->end_controls_section();

		/* ==================== STYLE : jours ==================== */
		$this->start_controls_section( 'sty_jours', array(
			'label' => __( 'Jours', 'lodgify-calendar' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'day_typo',
			'label'    => __( 'Typographie des chiffres', 'lodgify-calendar' ),
			'selector' => '{{WRAPPER}} .lcal-day',
		) );

		$this->add_responsive_control( 'day_size', array(
			'label'      => __( 'Taille des cases', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 22, 'max' => 90 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 46 ),
			'selectors'  => array( '{{WRAPPER}} .lcal' => '--lcal-day-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'head_free', array(
			'label'     => __( 'Jours disponibles', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'free_color', array(
			'label'     => __( 'Couleur du texte', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-day.is-free' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'free_bg', array(
			'label'     => __( 'Couleur de fond', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .lcal-day.is-free'      => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .lcal-swatch-free'      => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'head_booked', array(
			'label'     => __( 'Jours réservés', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'booked_color', array(
			'label'     => __( 'Couleur du texte', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-day.is-booked' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'booked_bg', array(
			'label'     => __( 'Couleur de fond', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .lcal-day.is-booked' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .lcal-swatch-booked' => 'background-color: {{VALUE}};',
			),
		) );
		$this->add_control( 'booked_strike', array(
			'label'        => __( 'Barrer les jours réservés', 'lodgify-calendar' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'selectors'    => array( '{{WRAPPER}} .lcal-day.is-booked' => 'text-decoration: line-through;' ),
		) );

		$this->add_control( 'head_today', array(
			'label'     => __( "Aujourd'hui", 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'today_color', array(
			'label'     => __( 'Couleur du texte', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-day.is-today' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'today_border',
			'label'    => __( 'Contour', 'lodgify-calendar' ),
			'selector' => '{{WRAPPER}} .lcal-day.is-today',
		) );

		$this->end_controls_section();

		/* ==================== STYLE : en-tetes ==================== */
		$this->start_controls_section( 'sty_entetes', array(
			'label' => __( 'Mois et jours de la semaine', 'lodgify-calendar' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'month_color', array(
			'label'     => __( 'Couleur du nom du mois', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-month-name' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'month_typo',
			'label'    => __( 'Typographie du mois', 'lodgify-calendar' ),
			'selector' => '{{WRAPPER}} .lcal-month-name',
		) );
		$this->add_control( 'note_color', array(
			'label'     => __( 'Couleur de la mention', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .lcal-note' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'note_typo',
			'label'    => __( 'Typographie de la mention', 'lodgify-calendar' ),
			'selector' => '{{WRAPPER}} .lcal-note',
		) );

		$this->add_control( 'dow_color', array(
			'label'     => __( 'Couleur des jours de la semaine', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .lcal-dow span' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'dow_typo',
			'label'    => __( 'Typographie des jours', 'lodgify-calendar' ),
			'selector' => '{{WRAPPER}} .lcal-dow span',
		) );

		$this->end_controls_section();

		/* ==================== STYLE : fleches ==================== */
		$this->start_controls_section( 'sty_fleches', array(
			'label' => __( 'Flèches', 'lodgify-calendar' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'nav_color', array(
			'label'     => __( 'Couleur', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-nav' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'nav_hover', array(
			'label'     => __( 'Couleur au survol', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal-nav:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'nav_size', array(
			'label'      => __( 'Taille', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 14, 'max' => 64 ) ),
			'selectors'  => array( '{{WRAPPER}} .lcal-nav' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ==================== STYLE : cadre ==================== */

		/* SEL_AIRBNB_2026-09-21 */
		$this->start_controls_section( 'sel_sel', array(
			'label' => __( 'Sélection des dates', 'lodgify-calendar' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'sel_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Arrivée et départ s\'affichent en ronds pleins, les nuits intermédiaires en bande continue qui passe derrière les ronds.', 'lodgify-calendar' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'sel_bg', array(
			'label'     => __( 'Ronds début/fin — fond', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .lcal' => '--lcal-sel-bg: {{VALUE}};' ),
		) );
		$this->add_control( 'sel_fg', array(
			'label'     => __( 'Ronds début/fin — texte', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array( '{{WRAPPER}} .lcal' => '--lcal-sel-fg: {{VALUE}};' ),
		) );
		$this->add_control( 'sel_band_bg', array(
			'label'     => __( 'Bande — couleur', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ebebeb',
			'selectors' => array( '{{WRAPPER}} .lcal' => '--lcal-band-bg: {{VALUE}};' ),
		) );
		$this->add_control( 'sel_band_fg', array(
			'label'     => __( 'Bande — texte', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .lcal' => '--lcal-band-fg: {{VALUE}};' ),
		) );
		$this->add_control( 'sel_ring', array(
			'label'     => __( 'Survol — couleur du contour', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .lcal' => '--lcal-ring: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'sel_h', array(
			'label'      => __( 'Hauteur des ronds et de la bande', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 24, 'max' => 80 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 44 ),
			'selectors'  => array( '{{WRAPPER}} .lcal' => '--lcal-sel-h: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'sel_r', array(
			'label'      => __( 'Arrondi de la bande', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 22 ),
			'selectors'  => array( '{{WRAPPER}} .lcal' => '--lcal-sel-r: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'sty_cadre', array(
			'label' => __( 'Cadre du calendrier', 'lodgify-calendar' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'cal_bg', array(
			'label'     => __( 'Fond', 'lodgify-calendar' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .lcal' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'cal_border',
			'selector' => '{{WRAPPER}} .lcal',
		) );
		$this->add_responsive_control( 'cal_radius', array(
			'label'      => __( 'Arrondi', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .lcal' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'cal_padding', array(
			'label'      => __( 'Marge intérieure', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .lcal' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'cal_gap', array(
			'label'      => __( 'Espacement entre les mois', 'lodgify-calendar' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .lcal-months' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/* =====================================================================
	 * RENDU
	 * ================================================================== */

	private function resolve_rental_id() {
		$s = $this->get_settings_for_display();
		if ( ! empty( $s['rental_id'] ) ) {
			return sanitize_text_field( $s['rental_id'] );
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			global $post;
			$post_id = $post ? $post->ID : 0;
		}
		return $post_id ? (string) get_post_meta( $post_id, 'rental-id', true ) : '';
	}

	/** Langue courante : Polylang si present, sinon la locale WordPress. */
	private function current_lang() {
		if ( function_exists( 'pll_current_language' ) ) {
			$l = pll_current_language( 'slug' );
			if ( $l ) { return substr( $l, 0, 2 ); }
		}
		return substr( (string) get_locale(), 0, 2 );
	}

	protected function render() {
		$s         = $this->get_settings_for_display();
		$rental_id = $this->resolve_rental_id();

		if ( '' === $rental_id ) {
			if ( class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Aucun bien Lodgify associé à cette fiche (rental-id absent).', 'lodgify-calendar' ) . '</p>';
			}
			return;
		}

		$lang   = $this->current_lang();
		$fr     = ( 'fr' === $lang );
		$months = max( 1, min( 3, (int) $s['months'] ) );

		$libre   = $s['legend_free']   !== '' ? $s['legend_free']   : ( $fr ? 'Disponible' : 'Available' );
		$occupe  = $s['legend_booked'] !== '' ? $s['legend_booked'] : ( $fr ? 'Réservé'    : 'Booked' );

		/* Le prix affiche sous chaque date est le tarif nuitee seul : ni menage,
		   ni taxes. Sans cette mention, l'ecart avec le total de l'encadre de
		   reservation passe pour une incoherence. */
		$mention = $s['legend_note'] !== ''
			? $s['legend_note']
			: ( $fr
				? 'Prix par nuit, hors frais de ménage et taxes'
				: 'Nightly rate, excluding cleaning fee and taxes' );

		/* Les nuits occupees partent AVEC la page : plus aucun appel a
		   admin-ajax.php, qui coute 1,4 s d'amorcage WordPress. La page, elle,
		   est servie depuis le cache. Le popup de reservation lit la meme
		   donnee via window.LodgifyDates. */
		$nuits = function_exists( 'lodgify_calendar_dates' ) ? lodgify_calendar_dates( $rental_id ) : array();

		/* Un widget insere avant l'introduction du reglage n'a pas la cle
		   'selection' : on considere alors qu'elle est active, comme le defaut. */
		$selection = ! array_key_exists( 'selection', $s ) || 'yes' === $s['selection'];

		$config = array(
			'rentalId'    => $rental_id,
			'nights'      => $nuits,
			'months'      => $months,
			'monthsMobile'=> ( 'yes' === $s['months_mobile'] ) ? 1 : $months,
			'weekStart'   => (int) $s['week_start'],
			'lang'        => $fr ? 'fr' : 'en',
			'selection'        => $selection,
			'minStayMsg'       => $fr
				? 'Séjour minimum de {n} nuits pour ces dates'
				: 'Minimum stay of {n} nights for these dates',
			/* ETATS_DISTINCTS_20260922 : une fois l'arrivee posee, le refus ne
			   porte plus sur « ces dates » mais sur cette arrivee-la. */
			'minStayArriveeMsg' => $fr
				? 'Séjour minimum de {n} nuits pour cette arrivée'
				: 'Minimum stay of {n} nights for this arrival',
			/* Infobulle des dates libres ecartees par le minimum. */
			'minStayTitre'      => $fr
				? 'Séjour minimum de {n} nuits'
				: 'Minimum stay of {n} nights',
			'showPrice'        => ( 'yes' === $s['price_show'] ),
			'priceDecimals'    => ( 'yes' === $s['price_decimals'] ) ? 'yes' : '',
			'priceSymbolAfter' => ( 'yes' === $s['price_symbol_after'] ) ? 'yes' : '',
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'monthNames'  => $fr
				? array( 'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre' )
				: array( 'January','February','March','April','May','June','July','August','September','October','November','December' ),
			'dayNames'    => $fr
				? array( 'D','L','M','M','J','V','S' )
				: array( 'S','M','T','W','T','F','S' ),
			'prevLabel'   => $fr ? 'Mois précédent' : 'Previous month',
			'nextLabel'   => $fr ? 'Mois suivant'   : 'Next month',
			'loading'     => $fr ? 'Chargement du calendrier…' : 'Loading calendar…',
		);
		?>
		<script>
			window.LodgifyDates = window.LodgifyDates || {};
			window.LodgifyDates[<?php echo wp_json_encode( (string) $rental_id ); ?>] = <?php echo wp_json_encode( $nuits ); ?>;
		</script>
		<div class="lcal<?php echo $selection ? ' lcal-selectable' : ''; ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="lcal-body" aria-live="polite">
				<button type="button" class="lcal-nav lcal-prev" aria-label="<?php echo esc_attr( $config['prevLabel'] ); ?>">&lsaquo;</button>
				<div class="lcal-months"></div>
				<button type="button" class="lcal-nav lcal-next" aria-label="<?php echo esc_attr( $config['nextLabel'] ); ?>">&rsaquo;</button>
			</div>
			<div class="lcal-loading"><?php echo esc_html( $config['loading'] ); ?></div>
			<div class="lcal-message" role="status"></div>
			<?php if ( 'yes' === $s['legend_show'] ) : ?>
				<div class="lcal-legend">
					<span class="lcal-legend-item"><i class="lcal-swatch lcal-swatch-free"></i><?php echo esc_html( $libre ); ?></span>
					<span class="lcal-legend-item"><i class="lcal-swatch lcal-swatch-booked"></i><?php echo esc_html( $occupe ); ?></span>
					<?php if ( 'yes' === $s['legend_note_show'] ) : ?>
						<span class="lcal-note"><?php echo esc_html( $mention ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
