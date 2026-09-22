<?php
/**
 * Dynamic Tag pour afficher le prix par jour
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour le prix par jour
 */
class FourU_Moteur_Price_Per_Day_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-price-per-day';
    }
    
    public function get_title() {
        return __('Prix par Jour Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['text'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'show_currency',
            [
                'label' => __('Afficher la devise', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Texte avant', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        
        $this->add_control(
            'suffix_text',
            [
                'label' => __('Texte après', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '/night',
            ]
        );
        
        $this->add_control(
            'fallback',
            [
                'label' => __('Valeur par défaut', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Prix non disponible', 'lodgify-availability-sync'),
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        $property_id = $this->get_current_property_rental_id();
        
        if (!$property_id) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $search_dates = $this->get_search_dates_from_url();
        $price_info = null;
        
        if ($search_dates) {
            // Avec dates de recherche → prix calculé depuis l'API
            $price_info = $this->get_price_info($property_id, $search_dates);
        } else {
            // Sans dates → prix de base depuis la BDD locale
            $price_info = $this->get_base_price($property_id);
        }
        
        if (!$price_info || !isset($price_info['price_per_day'])) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $output = '';
        
        if (!empty($settings['prefix_text'])) {
            $output .= esc_html($settings['prefix_text']) . ' ';
        }
        
        if ($settings['show_currency'] === 'yes') {
            $output .= $this->format_price($price_info['price_per_day'], $price_info['website_id']);
        } else {
            $output .= number_format($price_info['price_per_day'], 2, '.', ',');
        }
        
        if (!empty($settings['suffix_text'])) {
            $output .= $settings['suffix_text'];
        }
        
        echo $output;
    }
}
