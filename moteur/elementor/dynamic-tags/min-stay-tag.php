<?php
/**
 * Dynamic Tag pour afficher le séjour minimum
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour le séjour minimum
 */
class FourU_Moteur_Min_Stay_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-min-stay';
    }
    
    public function get_title() {
        return __('Séjour Minimum Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['text', 'number'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Texte avant', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        
        $this->add_control(
            'suffix_singular',
            [
                'label' => __('Suffixe singulier', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' night',
            ]
        );
        
        $this->add_control(
            'suffix_plural',
            [
                'label' => __('Suffixe pluriel', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' nights',
            ]
        );
        
        $this->add_control(
            'fallback',
            [
                'label' => __('Valeur par défaut', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1 night',
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
            // Avec dates : le minimum EXACT de la periode.
            $price_info = $this->get_price_info($property_id, $search_dates);
        } else {
            /* MINSTAY_20260922 - sans dates, annoncer le minimum de la nuit
               courante induit en erreur : Six View affichait « 5 nights » alors
               que fin decembre exige 6 ou 7. On annonce donc le PLUS PETIT
               minimum de l'annee, explicitement presente comme un « a partir
               de ». Le minimum exact s'affiche des que des dates sont choisies. */
            global $wpdb;
            $table = $wpdb->prefix . 'lodgify_daily_prices';
            $plancher = $wpdb->get_var($wpdb->prepare(
                "SELECT MIN(min_stay) FROM {$table}
                 WHERE property_id = %s AND date >= %s AND date < %s AND min_stay > 0",
                $property_id,
                gmdate('Y-m-d'),
                gmdate('Y-m-d', strtotime('+1 year'))
            ));

            if (null !== $plancher) {
                $plancher = max(1, (int) $plancher);
                $suffix = $plancher > 1 ? $settings['suffix_plural'] : $settings['suffix_singular'];
                $prefixe = !empty($settings['prefix_text']) ? esc_html($settings['prefix_text']) . ' ' : '';
                echo $prefixe
                   . esc_html__('from', 'lodgify-availability-sync') . ' '
                   . '<span class="lodgify-min-stay-valeur">' . $plancher . '</span>'
                   . $suffix;
                return;
            }

            $price_info = $this->get_base_price($property_id);
        }
        
        if (!$price_info || !isset($price_info['min_stay'])) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $min_stay = max(1, intval($price_info['min_stay']));
        $suffix = $min_stay > 1 ? $settings['suffix_plural'] : $settings['suffix_singular'];
        
        $output = '';
        
        if (!empty($settings['prefix_text'])) {
            $output .= esc_html($settings['prefix_text']) . ' ';
        }
        
        /* CARTES_DEVIS_20260922 : la valeur est isolee dans un porteur pour que
           card-quotes.js puisse la remplacer par le sejour minimum du devis,
           sans requete supplementaire ni reecriture du libelle. */
        $output .= '<span class="lodgify-min-stay-valeur">' . $min_stay . '</span>' . $suffix;

        echo $output;
    }
}
