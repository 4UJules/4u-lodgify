<?php
/**
 * Dynamic Tag pour l'URL de réservation Lodgify
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour l'URL de réservation
 */
class FourU_Moteur_Booking_URL_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-booking-url';
    }
    
    public function get_title() {
        return __('URL Réservation Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['url'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'default_adults',
            [
                'label' => __('Nombre d\'adultes par défaut', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 2,
                'min' => 1,
                'max' => 20,
            ]
        );
        
        $this->add_control(
            'fallback_url',
            [
                'label' => __('URL par défaut (sans dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '#',
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        $property_id = $this->get_current_property_rental_id();
        
        if (!$property_id) {
            echo esc_url($settings['fallback_url']);
            return;
        }
        
        $website_id = $this->get_property_website_id($property_id);
        $search_dates = $this->get_search_dates_from_url();
        
        // Déterminer le domaine et la devise
        if ($website_id === '479060') {
            $domain = 'amazing-stay';
            $currency = 'EUR';
        } else {
            $domain = 'thehills';
            $currency = 'USD';
        }
        
        // Construire l'URL
        $url = 'https://checkout.lodgify.com/' . $domain . '/' . $property_id . '/addons';
        $url .= '?currency=' . $currency . '&ref=bnbox';
        
        // Ajouter les dates si disponibles
        /* CARTE_BOOK_20260922 : sans dates choisies, ce tag inventait
           aujourd'hui + 3 jours et renvoyait quand meme une URL de checkout.
           Le bouton « Book now » des cartes partait donc chez Lodgify avec des
           dates que le visiteur n'a jamais demandees - souvent indisponibles ou
           sous le sejour minimum. Sans dates, on renvoie la fiche du bien, que
           l'appelant fournit en fallback_url. */
        if ( ! $search_dates ) {
            echo esc_url( $settings['fallback_url'] );
            return;
        }

        $url .= '&arrival=' . $search_dates['check_in'];
        $url .= '&departure=' . $search_dates['check_out'];
        
        // Ajouter le nombre d'adultes
        $url .= '&adults=' . intval($settings['default_adults']);
        
        echo esc_url($url);
    }
}
