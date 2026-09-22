<?php
/**
 * Dynamic Tag pour afficher le total du séjour avec taxes et frais depuis Lodgify API
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour le total du séjour (données réelles API Lodgify)
 */
class FourU_Moteur_Total_Stay_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-total-stay';
    }
    
    public function get_title() {
        return __('Total Séjour Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['text'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Texte avant', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Total: ',
            ]
        );
        
        $this->add_control(
            'show_breakdown',
            [
                'label' => __('Afficher le détail', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
                'description' => __('Affiche le détail: nuits, taxes, frais ménage', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'label_nights',
            [
                'label' => __('Label nuits', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'nights',
                'condition' => ['show_breakdown' => 'yes'],
            ]
        );
        
        $this->add_control(
            'fallback',
            [
                'label' => __('Valeur par défaut (sans dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Select dates', 'lodgify-availability-sync'),
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
        
        // Sans dates, afficher le fallback
        if (!$search_dates) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $price_info = $this->get_price_info($property_id, $search_dates);
        
        if (!$price_info || !isset($price_info['total_price'])) {
            echo esc_html($settings['fallback']);
            return;
        }

        /* CARTES_DEVIS_20260922 : un sejour que Lodgify ne chiffrera pas - nuit
           bloquee, ou duree sous le sejour minimum - ne doit afficher AUCUN
           total. Le montant reconstruit localement serait exact au centime (0
           ecart sur 17 comparaisons) mais porterait sur un sejour qu'on ne peut
           pas vendre. */
        if (!$this->sejour_possible($property_id, $search_dates, $price_info)) {
            echo '<span class="lodgify-total-indispo">'
               . esc_html__('Not available for these dates', 'lodgify-availability-sync')
               . '</span>';
            return;
        }
        
        // Données depuis l'API Lodgify
        $nights = $price_info['nights'];
        $nights_total = $price_info['nights_total'];
        $cleaning_fee = $price_info['cleaning_fee'];
        $cleaning_name = $price_info['cleaning_name'] ?: 'Cleaning fee';
        $tax_percentage = $price_info['tax_percentage'];
        $tax_name = $price_info['tax_name'] ?: 'Tax';
        $tax_amount = $price_info['tax_amount'];
        $insurance_fee = isset($price_info['insurance_fee']) ? $price_info['insurance_fee'] : 0;
        $insurance_name = isset($price_info['insurance_name']) ? $price_info['insurance_name'] : 'Insurance';
        $total_price = $price_info['total_price'];
        $currency = $price_info['currency'];
        
        // Affichage
        $output = '';
        
        // Données extra guests
        $extra_guests = isset($price_info['extra_guests']) ? $price_info['extra_guests'] : 0;
        $extra_guests_total = isset($price_info['extra_guests_total']) ? $price_info['extra_guests_total'] : 0;
        $extra_guest_fee = isset($price_info['extra_guest_fee']) ? $price_info['extra_guest_fee'] : 0;
        
        if ($settings['show_breakdown'] === 'yes') {
            $label_nights = $settings['label_nights'] ?: 'nights';
            
            $output .= '<div class="lodgify-total-breakdown">';
            
            // Prix des nuits
            $output .= '<div class="lodgify-breakdown-item">';
            $output .= '<span class="label">' . $nights . ' ' . esc_html($label_nights) . '</span>';
            $output .= '<span class="value">' . $this->format_price($nights_total, $currency) . '</span>';
            $output .= '</div>';
            
            // Frais de guests supplémentaires (si > 0)
            if ($extra_guests > 0 && $extra_guests_total > 0) {
                $output .= '<div class="lodgify-breakdown-item">';
                $output .= '<span class="label">Extra guests (' . $extra_guests . ' × ' . $this->format_price($extra_guest_fee, $currency) . '/night)</span>';
                $output .= '<span class="value">' . $this->format_price($extra_guests_total, $currency) . '</span>';
                $output .= '</div>';
            }
            
            // Frais de ménage (si > 0)
            if ($cleaning_fee > 0) {
                $output .= '<div class="lodgify-breakdown-item">';
                $output .= '<span class="label">' . esc_html($cleaning_name) . '</span>';
                $output .= '<span class="value">' . $this->format_price($cleaning_fee, $currency) . '</span>';
                $output .= '</div>';
            }
            
            // Taxes (si > 0)
            if ($tax_amount > 0) {
                $tax_label = esc_html($tax_name);
                if ($tax_percentage > 0) {
                    $tax_label .= ' (' . $tax_percentage . '%)';
                }
                $output .= '<div class="lodgify-breakdown-item">';
                $output .= '<span class="label">' . $tax_label . '</span>';
                $output .= '<span class="value">' . $this->format_price($tax_amount, $currency) . '</span>';
                $output .= '</div>';
            }
            
            // Insurance fee
            if ($insurance_fee > 0) {
                $output .= '<div class="lodgify-breakdown-item">';
                $output .= '<span class="label">' . esc_html($insurance_name) . '</span>';
                $output .= '<span class="value">' . $this->format_price($insurance_fee, $currency) . '</span>';
                $output .= '</div>';
            }
            
            // Total
            $output .= '<div class="lodgify-breakdown-total">';
            $output .= '<span class="label">' . esc_html($settings['prefix_text']) . '</span>';
            $output .= '<span class="value">' . $this->format_price($total_price, $currency) . '</span>';
            $output .= '</div>';
            
            $output .= '</div>';
        } else {
            if (!empty($settings['prefix_text'])) {
                $output .= esc_html($settings['prefix_text']);
            }
            $output .= '<span class="lodgify-total-montant">'
                    . $this->format_price($total_price, $currency)
                    . '</span>';
        }

        /* Le devis exact arrive apres l'affichage : card-quotes.js lit ces
           attributs, interroge lodgify_get_price (cache 10 min cote serveur) et
           remplace le montant, ou efface tout si Lodgify repond unavailable. */
        $output = '<span class="lodgify-total-stay" data-rental="' . esc_attr($property_id)
                . '" data-in="' . esc_attr($search_dates['check_in'])
                . '" data-out="' . esc_attr($search_dates['check_out'])
                . '" data-fallback="' . esc_attr($settings['fallback']) . '">'
                . $output . '</span>';

        echo $output;
    }
}
