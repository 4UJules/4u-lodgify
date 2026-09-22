<?php
/**
 * Widget Elementor pour le formulaire de réservation style Airbnb
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Airbnb Booking Form
 */
class FourU_Moteur_Airbnb_Booking_Elementor_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'lodgify_airbnb_booking';
    }
    
    public function get_title() {
        return __('Airbnb Booking Form', 'lodgify-availability-sync');
    }
    
    public function get_icon() {
        return 'eicon-calendar';
    }
    
    public function get_categories() {
        return ['lodgify-availability-sync'];
    }
    
    public function get_keywords() {
        return ['lodgify', 'airbnb', 'booking', 'reservation', 'calendar', 'prix', 'dates'];
    }
    
    public function get_style_depends() {
        return ['airbnb-booking-widget'];
    }
    
    public function get_script_depends() {
        return ['airbnb-booking-widget'];
    }
    
    protected function register_controls() {
        // Section Contenu
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Paramètres', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'property_id',
            [
                'label' => __('Property ID', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'dynamic' => ['active' => true],
                'description' => __('Laissez vide pour utiliser le meta lodgify_property_id du post', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'checkout_url',
            [
                'label' => __('URL Checkout Lodgify', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'dynamic' => ['active' => true],
                'description' => __('Ex: https://checkout.lodgify.com/thehills/722544', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'currency',
            [
                'label' => __('Devise', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '$',
                'options' => [
                    '$' => 'USD ($)',
                    '€' => 'EUR (€)',
                    '£' => 'GBP (£)',
                ],
            ]
        );
        
        $this->add_control(
            'max_guests',
            [
                'label' => __('Max voyageurs', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ]
        );
        
        $this->end_controls_section();
        
        // Section Mobile Sticky
        $this->start_controls_section(
            'section_mobile_sticky',
            [
                'label' => __('Mobile Sticky', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'enable_mobile_sticky',
            [
                'label' => __('Activer sticky mobile', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Affiche le widget fixé en bas de l\'écran sur mobile', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'mobile_sticky_breakpoint',
            [
                'label' => __('Breakpoint (px)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 768,
                'min' => 320,
                'max' => 1024,
                'condition' => [
                    'enable_mobile_sticky' => 'yes',
                ],
                'description' => __('Largeur d\'écran max pour activer le sticky', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'mobile_sticky_bg',
            [
                'label' => __('Couleur de fond sticky', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'condition' => [
                    'enable_mobile_sticky' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget.abw-mobile-sticky' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'mobile_sticky_shadow',
            [
                'label' => __('Ombre sticky', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::BOX_SHADOW,
                'condition' => [
                    'enable_mobile_sticky' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget.abw-mobile-sticky' => 'box-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}};',
                ],
                'default' => [
                    'horizontal' => 0,
                    'vertical' => -4,
                    'blur' => 16,
                    'spread' => 0,
                    'color' => 'rgba(0,0,0,0.15)',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'mobile_sticky_padding',
            [
                'label' => __('Padding sticky', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => 12,
                    'right' => 16,
                    'bottom' => 12,
                    'left' => 16,
                    'unit' => 'px',
                ],
                'condition' => [
                    'enable_mobile_sticky' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget.abw-mobile-sticky' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Labels
        $this->start_controls_section(
            'section_labels',
            [
                'label' => __('Labels', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'label_title',
            [
                'label' => __('Title (no dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Select Dates to See Booking Amount',
            ]
        );
        
        $this->add_control(
            'label_arrival',
            [
                'label' => __('Check-in Label', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'CHECK-IN',
            ]
        );
        
        $this->add_control(
            'label_departure',
            [
                'label' => __('Check-out Label', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'CHECK-OUT',
            ]
        );
        
        $this->add_control(
            'label_guests',
            [
                'label' => __('Guests Label', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'GUESTS',
            ]
        );
        
        $this->add_control(
            'label_add_date',
            [
                'label' => __('Add Date', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Add Date',
            ]
        );
        
        $this->add_control(
            'label_check_btn',
            [
                'label' => __('Check Button', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Check Availability',
            ]
        );
        
        $this->add_control(
            'label_reserve_btn',
            [
                'label' => __('Book Button', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Book Now',
            ]
        );
        
        $this->add_control(
            'label_reassurance',
            [
                'label' => __('Reassurance Message', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'You won\'t be charged yet',
            ]
        );
        
        $this->add_control(
            'label_guest_singular',
            [
                'label' => __('Guest (singular)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'guest',
            ]
        );
        
        $this->add_control(
            'label_guest_plural',
            [
                'label' => __('Guests (plural)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'guests',
            ]
        );
        
        $this->end_controls_section();
        
        // Section Min Stay Error
        $this->start_controls_section(
            'section_min_stay',
            [
                'label' => __('Message Min Stay', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'min_stay_message_template',
            [
                'label' => __('Texte du message', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Minimum stay of {min_stay} nights required',
                'description' => __('Utilisez {min_stay} pour afficher le nombre de nuits minimum', 'lodgify-availability-sync'),
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Min Stay Error
        $this->start_controls_section(
            'section_style_min_stay',
            [
                'label' => __('Message Min Stay', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'min_stay_text_color',
            [
                'label' => __('Couleur du texte', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3545',
                'selectors' => [
                    '{{WRAPPER}} .abw-min-stay-error' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'min_stay_bg_color',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#fff5f5',
                'selectors' => [
                    '{{WRAPPER}} .abw-min-stay-error' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'min_stay_border_color',
            [
                'label' => __('Couleur de bordure', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3545',
                'selectors' => [
                    '{{WRAPPER}} .abw-min-stay-error' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'min_stay_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 20],
                ],
                'default' => ['size' => 4],
                'selectors' => [
                    '{{WRAPPER}} .abw-min-stay-error' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'min_stay_padding',
            [
                'label' => __('Padding', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => 8,
                    'right' => 12,
                    'bottom' => 8,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .abw-min-stay-error' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'min_stay_typography',
                'selector' => '{{WRAPPER}} .abw-min-stay-error',
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Container
        $this->start_controls_section(
            'section_style_container',
            [
                'label' => __('Container', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'container_bg',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 30],
                ],
                'default' => ['size' => 12],
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .airbnb-booking-widget',
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 24,
                    'right' => 24,
                    'bottom' => 24,
                    'left' => 24,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .airbnb-booking-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Bouton
        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __('Bouton', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'button_full_width',
            [
                'label' => __('Pleine largeur', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'yes',
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'width: 100%; display: block;',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'button_width',
            [
                'label' => __('Largeur', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 100, 'max' => 500],
                    '%' => ['min' => 10, 'max' => 100],
                ],
                'condition' => [
                    'button_full_width!' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_bg',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e61e4d',
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'background: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Couleur du texte', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 30],
                ],
                'default' => ['size' => 8],
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .abw-submit-btn',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .abw-submit-btn',
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_heading',
            [
                'label' => __('Hover', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'button_hover_bg',
            [
                'label' => __('Couleur de fond (hover)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn:hover' => 'background: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Couleur du texte (hover)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_border_color',
            [
                'label' => __('Couleur bordure (hover)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .abw-submit-btn:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Formulaire (dates/voyageurs)
        $this->start_controls_section(
            'section_style_form',
            [
                'label' => __('Formulaire', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'form_border_color',
            [
                'label' => __('Couleur bordure', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#b0b0b0',
                'selectors' => [
                    '{{WRAPPER}} .abw-form' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .abw-dates-row' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .abw-date-field:first-child' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'form_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 20],
                ],
                'default' => ['size' => 8],
                'selectors' => [
                    '{{WRAPPER}} .abw-form' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'form_label_color',
            [
                'label' => __('Couleur labels', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => [
                    '{{WRAPPER}} .abw-date-field label' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .abw-guests-field label' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'form_label_typography',
                'label' => __('Typographie labels', 'lodgify-availability-sync'),
                'selector' => '{{WRAPPER}} .abw-date-field label, {{WRAPPER}} .abw-guests-field label',
            ]
        );
        
        $this->add_control(
            'form_value_color',
            [
                'label' => __('Couleur valeurs', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#717171',
                'selectors' => [
                    '{{WRAPPER}} .abw-date-value' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .abw-guests-value' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'form_hover_bg',
            [
                'label' => __('Fond au survol', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f7f7f7',
                'selectors' => [
                    '{{WRAPPER}} .abw-date-field:hover' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .abw-guests-row:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Dropdown
        $this->start_controls_section(
            'section_style_dropdown',
            [
                'label' => __('Dropdown Voyageurs', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'dropdown_bg',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .abw-guests-dropdown' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'dropdown_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 20],
                ],
                'default' => ['size' => 12],
                'selectors' => [
                    '{{WRAPPER}} .abw-guests-dropdown' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'dropdown_shadow',
                'selector' => '{{WRAPPER}} .abw-guests-dropdown',
            ]
        );
        
        $this->add_control(
            'dropdown_item_color',
            [
                'label' => __('Couleur texte options', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => [
                    '{{WRAPPER}} .abw-guest-option' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'dropdown_item_hover_bg',
            [
                'label' => __('Fond option au survol', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f7f7f7',
                'selectors' => [
                    '{{WRAPPER}} .abw-guest-option:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'dropdown_item_selected_bg',
            [
                'label' => __('Fond option sélectionnée', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f0f0',
                'selectors' => [
                    '{{WRAPPER}} .abw-guest-option.selected' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style Prix
        $this->start_controls_section(
            'section_style_price',
            [
                'label' => __('Prix', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'price_color',
            [
                'label' => __('Couleur du prix', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => [
                    '{{WRAPPER}} .abw-price-current' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .abw-price-current',
            ]
        );
        
        $this->add_control(
            'price_original_color',
            [
                'label' => __('Couleur prix barré', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#717171',
                'selectors' => [
                    '{{WRAPPER}} .abw-price-original' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();

		/* SEL_AIRBNB_2026-09-21 */
		$this->start_controls_section( 'abwsel_sel', array(
			'label' => __( 'Sélection des dates (calendrier)', 'lodgify-availability-sync' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'abwsel_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Arrivée et départ s\'affichent en ronds pleins, les nuits intermédiaires en bande continue qui passe derrière les ronds.', 'lodgify-availability-sync' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'abwsel_bg', array(
			'label'     => __( 'Ronds début/fin — fond', 'lodgify-availability-sync' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-sel-bg: {{VALUE}};' ),
		) );
		$this->add_control( 'abwsel_fg', array(
			'label'     => __( 'Ronds début/fin — texte', 'lodgify-availability-sync' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-sel-fg: {{VALUE}};' ),
		) );
		$this->add_control( 'abwsel_band_bg', array(
			'label'     => __( 'Bande — couleur', 'lodgify-availability-sync' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ebebeb',
			'selectors' => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-band-bg: {{VALUE}};' ),
		) );
		$this->add_control( 'abwsel_band_fg', array(
			'label'     => __( 'Bande — texte', 'lodgify-availability-sync' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-band-fg: {{VALUE}};' ),
		) );
		$this->add_control( 'abwsel_ring', array(
			'label'     => __( 'Survol — couleur du contour', 'lodgify-availability-sync' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#222222',
			'selectors' => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-ring: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'abwsel_h', array(
			'label'      => __( 'Hauteur des ronds et de la bande', 'lodgify-availability-sync' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 24, 'max' => 80 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 40 ),
			'selectors'  => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-sel-h: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'abwsel_r', array(
			'label'      => __( 'Arrondi de la bande', 'lodgify-availability-sync' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 20 ),
			'selectors'  => array( '{{WRAPPER}} .abw-calendar-popup' => '--abw-sel-r: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Utiliser get_queried_object_id() pour les templates Elementor
        $post_id = get_queried_object_id();
        if (!$post_id) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        
        // Récupérer l'URL de checkout - utiliser les settings API dynamiques
        $checkout_url = $settings['checkout_url'];
        if (empty($checkout_url) && $post_id) {
            $checkout_url = get_post_meta($post_id, 'lodgify_checkout_url', true);
        }
        
        // Si pas d'URL de checkout, la générer dynamiquement depuis l'API configurée
        if (empty($checkout_url) && $post_id) {
            $api_settings = new FourU_Moteur_API_Settings();
            $checkout_url = $api_settings->get_checkout_url($post_id);
        }

        /* AMM_COMPTE 2026-09-21 : la devise suit le compte du bien, exactement comme
           le lien de reservation. Elle n'est plus saisie dans le widget - un seul
           widget suffit desormais pour tous les comptes. Le reglage Elementor ne
           sert plus que de repli explicite. */
        $amm_api = new FourU_Moteur_API_Settings();
        $currency = $post_id ? $amm_api->get_currency_for_property($post_id) : '';
        if ('' === $currency) {
            $currency = !empty($settings['currency']) ? $settings['currency'] : '';
        }
        // Compte introuvable : pas de lien de reservation, donc pas de bouton actif.
        $compte_inconnu = empty($checkout_url);
        
        // Récupérer l'ID de la propriété
        $property_id = '';
        
        // 1. PRIORITÉ: depuis le meta field 'rental-id' (source Lodgify)
        if ($post_id) {
            $property_id = get_post_meta($post_id, 'rental-id', true);
        }
        
        // 2. Fallback: extraire depuis checkout_url
        if (empty($property_id) && !empty($checkout_url) && preg_match('/\/(\d+)(?:\/|$)/', $checkout_url, $matches)) {
            $property_id = $matches[1];
        }
        
        // 3. Fallback: depuis les settings Elementor (dynamic tag)
        if (empty($property_id) && !empty($settings['property_id'])) {
            $property_id = $settings['property_id'];
        }
        
        // 4. Fallback: depuis d'autres meta fields du post
        if (empty($property_id) && $post_id) {
            $meta_names = array('lodgify_property_id', 'rental_id', 'property_id', '_lodgify_property_id', 'lodgify_id');
            foreach ($meta_names as $meta_name) {
                $property_id = get_post_meta($post_id, $meta_name, true);
                if (!empty($property_id)) {
                    break;
                }
            }
        }
        
        // Récupérer le nombre max de voyageurs.
        // Règle: si le champ "guest" est vide => max = 6, sinon max = valeur de "guest".
        // IMPORTANT: on ignore les settings Elementor pour éviter une valeur par défaut (ex: 10).
        $max_guests = 6;
        if ($post_id) {
            $guest_meta = get_post_meta($post_id, 'guest', true);
            if (!empty($guest_meta) && intval($guest_meta) > 0) {
                $max_guests = intval($guest_meta);
            }
        }
        
        $widget_id = $this->get_id();
        ?>
        <?php
        $label_singular = !empty($settings['label_guest_singular']) ? $settings['label_guest_singular'] : 'voyageur';
        $label_plural = !empty($settings['label_guest_plural']) ? $settings['label_guest_plural'] : 'voyageurs';
        $label_reserve = !empty($settings['label_reserve_btn']) ? $settings['label_reserve_btn'] : 'Réserver';
        $label_check = !empty($settings['label_check_btn']) ? $settings['label_check_btn'] : 'Vérifier la disponibilité';
        
        // Mobile sticky settings
        $enable_mobile_sticky = !empty($settings['enable_mobile_sticky']) && $settings['enable_mobile_sticky'] === 'yes';
        $mobile_sticky_breakpoint = !empty($settings['mobile_sticky_breakpoint']) ? intval($settings['mobile_sticky_breakpoint']) : 768;
        
        // Min stay message template
        $min_stay_message_template = !empty($settings['min_stay_message_template']) ? $settings['min_stay_message_template'] : 'Minimum stay of {min_stay} nights required';
        /* AMM_RENTALID_GUARD 2026-09-21 : pas de rental-id = ce bien n'est pas une
           location Lodgify (fiche a la vente, page hors perimetre). On n'affiche aucun
           widget de reservation plutot qu'un formulaire qui ne mene nulle part.
           Cote serveur plutot qu'en condition JetEngine : c'est la meme regle pour
           tous les sites, elle ne depend d'aucun reglage de modele. */
        if ( empty( $property_id ) ) {
            if ( class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p>' . esc_html__( 'Aucun bien Lodgify associe a cette fiche (rental-id absent).', 'lodgify-availability-sync' ) . '</p>';
            }
            return;
        }

        ?>
        <div class="airbnb-booking-widget<?php echo $enable_mobile_sticky ? ' abw-sticky-enabled' : ''; ?>" 
             id="abw-<?php echo esc_attr($widget_id); ?>"
             data-property-id="<?php echo esc_attr($property_id); ?>"
             data-checkout-url="<?php echo esc_attr($checkout_url); ?>"
             data-currency="<?php echo esc_attr($currency); ?>"
            data-account-error="<?php echo $compte_inconnu ? '1' : '0'; ?>"
             data-max-guests="<?php echo esc_attr($max_guests); ?>"
             data-label-singular="<?php echo esc_attr($label_singular); ?>"
             data-label-plural="<?php echo esc_attr($label_plural); ?>"
             data-label-reserve="<?php echo esc_attr($label_reserve); ?>"
             data-label-check="<?php echo esc_attr($label_check); ?>"
             data-min-stay-message="<?php echo esc_attr($min_stay_message_template); ?>"
             data-mobile-sticky="<?php echo $enable_mobile_sticky ? 'true' : 'false'; ?>"
             data-sticky-breakpoint="<?php echo esc_attr($mobile_sticky_breakpoint); ?>">
            
            <!-- État initial: sans dates -->
            <div class="abw-no-dates">
                <h3 class="abw-title"><?php echo esc_html($settings['label_title']); ?></h3>
            </div>
            
            <!-- État avec dates -->
            <div class="abw-with-dates" style="display: none;">
                <div class="abw-price-display">
                    <span class="abw-price-original"></span>
                    <span class="abw-price-current"></span>
                    <span class="abw-price-nights"></span>
                </div>
            </div>
            
            <!-- Formulaire de dates -->
            <div class="abw-form">
                <div class="abw-dates-row">
                    <div class="abw-date-field abw-arrival" data-type="arrival">
                        <label><?php echo esc_html($settings['label_arrival']); ?></label>
                        <span class="abw-date-value"><?php echo esc_html($settings['label_add_date']); ?></span>
                        <input type="hidden" name="arrival" value="">
                    </div>
                    <div class="abw-date-field abw-departure" data-type="departure">
                        <label><?php echo esc_html($settings['label_departure']); ?></label>
                        <span class="abw-date-value"><?php echo esc_html($settings['label_add_date']); ?></span>
                        <input type="hidden" name="departure" value="">
                    </div>
                </div>
                
                <div class="abw-guests-row">
                    <div class="abw-guests-field">
                        <div>
                            <label><?php echo esc_html($settings['label_guests']); ?></label>
                            <span class="abw-guests-value">1 <?php echo esc_html($label_singular); ?></span>
                        </div>
                        <input type="hidden" name="guests" value="1">
                        <svg class="abw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    
                    <!-- Dropdown voyageurs - Liste déroulante -->
                    <div class="abw-guests-dropdown" style="display: none;">
                        <ul class="abw-guests-list">
                            <?php for ($i = 1; $i <= $max_guests; $i++) : ?>
                                <li class="abw-guest-option<?php echo $i === 1 ? ' selected' : ''; ?>" data-value="<?php echo $i; ?>">
                                    <?php echo $i; ?> <?php echo $i === 1 ? esc_html($label_singular) : esc_html($label_plural); ?>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Bouton principal -->
            <button type="button" class="abw-submit-btn abw-check-availability">
                <?php echo esc_html($settings['label_check_btn']); ?>
            </button>
            
            <!-- Message de réassurance -->
            <p class="abw-reassurance" style="display: none;">
                <?php echo esc_html($settings['label_reassurance']); ?>
            </p>
        </div>
        
        <!-- Popup Calendrier -->
        <div class="abw-calendar-popup" id="abw-popup-<?php echo esc_attr($widget_id); ?>" style="display: none;">
            <div class="abw-calendar-overlay"></div>
            <div class="abw-calendar-modal">
                <div class="abw-calendar-header">
                    <div class="abw-calendar-summary">
                        <div class="abw-nights-count"></div>
                        <div class="abw-dates-range"><?php echo esc_html($settings['label_add_date']); ?></div>
                    </div>
                    <div class="abw-calendar-inputs">
                        <div class="abw-input-field abw-input-arrival active">
                            <label><?php echo esc_html($settings['label_arrival']); ?></label>
                            <span class="abw-input-value"><?php echo esc_html($settings['label_add_date']); ?></span>
                            <button type="button" class="abw-clear-date" data-type="arrival">×</button>
                        </div>
                        <div class="abw-input-field abw-input-departure">
                            <label><?php echo esc_html($settings['label_departure']); ?></label>
                            <span class="abw-input-value"><?php echo esc_html($settings['label_add_date']); ?></span>
                            <button type="button" class="abw-clear-date" data-type="departure">×</button>
                        </div>
                    </div>
                </div>
                
                <div class="abw-calendar-body">
                    <div class="abw-calendar-nav">
                        <button type="button" class="abw-nav-prev">‹</button>
                    </div>
                    <div class="abw-calendar-month abw-month-1"></div>
                    <div class="abw-calendar-month abw-month-2"></div>
                    <div class="abw-calendar-nav">
                        <button type="button" class="abw-nav-next">›</button>
                    </div>
                </div>
                
                <div class="abw-calendar-footer">
                    <button type="button" class="abw-keyboard-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <line x1="6" y1="8" x2="6" y2="8"></line>
                            <line x1="18" y1="16" x2="6" y2="16"></line>
                        </svg>
                    </button>
                    <div class="abw-footer-actions">
                        <button type="button" class="abw-clear-all"><?php _e('Effacer les dates', 'lodgify-availability-sync'); ?></button>
                        <button type="button" class="abw-close-calendar"><?php _e('Fermer', 'lodgify-availability-sync'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
