<?php
/**
 * Classe de base pour les Dynamic Tags Lodgify
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe abstraite pour les tags dynamiques Lodgify
 */
abstract class FourU_Moteur_Dynamic_Tag_Base extends \Elementor\Core\DynamicTags\Tag {
    
    /**
     * Obtenir le groupe du tag
     */
    public function get_group() {
        return 'lodgify';
    }
    
    /**
     * Récupérer l'ID de location (rental-id) de la propriété courante
     */
    protected function get_current_property_rental_id() {
        /* MINSTAY_20260922 : get_the_ID() seul ne designe pas la fiche dans tous
           les contextes de rendu Elementor - releve sur D403 le 2026-09-22, ou
           le meme titre rendu trois fois donnait « from 1 night » deux fois et
           « from 2 nights » une fois, pour un seul et meme bien.
           L'ordre compte : DANS une boucle (cartes de listing, biens lies), seul
           l'element courant fait foi - prendre l'objet interroge y ferait
           heriter toutes les cartes du bien de la page. HORS boucle, sur une
           page singuliere, c'est l'objet interroge qui est fiable. */
        $post_id = 0;

        if (function_exists('in_the_loop') && in_the_loop()) {
            $post_id = (int) get_the_ID();
        }
        if (!$post_id && is_singular()) {
            $post_id = (int) get_queried_object_id();
        }
        if (!$post_id) {
            $post_id = (int) get_the_ID();
        }
        if (!$post_id) {
            return false;
        }

        $rental_id = get_post_meta($post_id, 'rental-id', true);
        return !empty($rental_id) ? $rental_id : false;
    }
    
    /**
     * Déterminer le website ID en fonction de la propriété
     * Récupère dynamiquement depuis la BDD lodgify_availabilities
     */
    protected function get_property_website_id($property_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        
        // Récupérer le website_id depuis la table de synchronisation
        $website_id = $wpdb->get_var($wpdb->prepare(
            "SELECT website_id FROM $table WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        // Si trouvé, retourner
        if (!empty($website_id)) {
            return $website_id;
        }
        
        // Sinon, valeur par défaut The Hills
        return '453125';
    }
    
    /**
     * Récupérer la clé API en fonction du website ID
     */
    protected function get_api_key_for_website($website_id) {
        if ($website_id === '479060') {
            return 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk';
        }
        return 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H';
    }
    
    /**
     * Récupérer le room type ID pour une propriété
     */
    protected function get_room_type_id_for_property($property_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        
        $room_type_id = $wpdb->get_var($wpdb->prepare(
            "SELECT room_type_id FROM $table WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        return !empty($room_type_id) ? $room_type_id : '565344';
    }
    
    /**
     * Récupérer le prix de base depuis la table lodgify_daily_prices
     * Prix minimum pour les 30 prochains jours
     */
    protected function get_base_price($property_id) {
        global $wpdb;
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        // Récupérer le prix minimum depuis la BDD locale
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT MIN(price_per_day) as min_price, 
                    AVG(min_stay) as avg_min_stay,
                    currency, website_id
             FROM $table_daily
             WHERE property_id = %s AND date >= CURDATE() AND date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY property_id
             LIMIT 1",
            $property_id
        ));
        
        if ($result && $result->min_price > 0) {
            return [
                'price_per_day' => floatval($result->min_price),
                'min_stay' => intval($result->avg_min_stay),
                'currency' => $result->currency ?: 'USD',
                'website_id' => $result->website_id ?: '453125',
                'is_base_price' => true,
            ];
        }
        
        return null;
    }
    
    /**
     * Récupérer les dates depuis JetSmartFilters (URL)
     */
    protected function get_search_dates_from_url() {
        if (isset($_GET['meta']) && !empty($_GET['meta'])) {
            $meta = $_GET['meta'];
            
            if (strpos($meta, 'checkin_checkout!date:') !== false) {
                $date_part = explode('checkin_checkout!date:', $meta)[1];
                // Séparer les dates du reste des paramètres
                $date_part = explode(';', $date_part)[0];
                $dates = explode('-', $date_part);
                
                if (count($dates) === 2) {
                    $date_parts_in = explode('.', $dates[0]);
                    $date_parts_out = explode('.', $dates[1]);
                    
                    if (count($date_parts_in) === 3 && count($date_parts_out) === 3) {
                        $check_in = $date_parts_in[0] . '-' . 
                                   str_pad($date_parts_in[1], 2, '0', STR_PAD_LEFT) . '-' . 
                                   str_pad($date_parts_in[2], 2, '0', STR_PAD_LEFT);
                        
                        $check_out = $date_parts_out[0] . '-' . 
                                    str_pad($date_parts_out[1], 2, '0', STR_PAD_LEFT) . '-' . 
                                    str_pad($date_parts_out[2], 2, '0', STR_PAD_LEFT);
                        
                        $check_in_time = strtotime($check_in);
                        $check_out_time = strtotime($check_out);
                        
                        if ($check_in_time && $check_out_time && $check_in_time < $check_out_time) {
                            return [
                                'check_in' => $check_in,
                                'check_out' => $check_out
                            ];
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Récupérer le nombre de guests depuis l'URL
     */
    protected function get_guests_from_url() {
        $guests = 2; // Défaut
        
        if (isset($_GET['meta']) && !empty($_GET['meta'])) {
            $meta = $_GET['meta'];
            
            // Format: guest!compare-greater:X ou guest!is:X
            if (preg_match('/guest!(?:compare-greater|is):(\d+)/', $meta, $matches)) {
                $guests = intval($matches[1]);
            }
        }
        
        return max(1, $guests);
    }
    
    /**
     * Formater le prix avec le symbole de devise
     * Accepte currency (EUR/USD) ou website_id (479060/453125)
     */
    protected function format_price($amount, $currency_or_website_id = 'USD') {
        // Si c'est un website_id, convertir en currency
        if ($currency_or_website_id === '479060') {
            $currency = 'EUR';
        } elseif ($currency_or_website_id === '453125') {
            $currency = 'USD';
        } else {
            $currency = $currency_or_website_id;
        }
        
        if ($currency === 'EUR') {
            return number_format($amount, 2, ',', ' ') . '€';
        }
        return '$' . number_format($amount, 2, '.', ',');
    }
    
    /**
     * Alias pour la clé API
     */
    protected function get_api_key($website_id) {
        return $this->get_api_key_for_website($website_id);
    }
    
    /**
     * Récupérer le room_type_id via l'API Lodgify si pas en BDD
     */
    protected function get_room_type_id($property_id, $website_id) {
        // Essayer depuis la base de données d'abord
        global $wpdb;
        $table_name = $wpdb->prefix . 'lodgify_availabilities';
        $room_type_id = $wpdb->get_var($wpdb->prepare(
            "SELECT room_type_id FROM $table_name WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        if ($room_type_id) {
            return $room_type_id;
        }
        
        // Sinon appeler l'API /v2/properties/{id}
        $api_key = $this->get_api_key($website_id);
        $api_url = 'https://api.lodgify.com/v2/properties/' . $property_id . '?wid=' . $website_id . '&includeInOut=false';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'X-ApiKey' => $api_key,
                'accept' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['rooms'][0]['id'])) {
            return $data['rooms'][0]['id'];
        }
        
        return null;
    }
    
    /**
     * Récupérer les informations de prix depuis la BDD locale (lodgify_daily_prices)
     * PAS D'APPEL API - données pré-synchronisées
     */
    /**
     * CARTES_DEVIS_20260922
     * Un sejour est impossible si l'une de ses nuits est bloquee, ou si sa duree
     * est inferieure au sejour minimum de la periode. Mesure du 2026-09-22 :
     * 23 cartes sur 40 affichaient un total pour un sejour que Lodgify refuse de
     * chiffrer. On ne peut pas interroger le devis au rendu - 1,9 s par appel,
     * 38 s pour 20 cartes - mais ces deux controles locaux coutent une requete.
     * Le devis differe affine ensuite, cote navigateur.
     *
     * @return bool
     */
    protected function sejour_possible($property_id, $search_dates, $price_info = null) {
        if (!$property_id || empty($search_dates['check_in']) || empty($search_dates['check_out'])) {
            return false;
        }

        $debut = strtotime($search_dates['check_in']);
        $fin   = strtotime($search_dates['check_out']);
        if (!$debut || !$fin || $fin <= $debut) {
            return false;
        }

        // 1. Sejour minimum de la periode (maximum des nuits, regle Lodgify verifiee).
        $nuits = (int) round(($fin - $debut) / DAY_IN_SECONDS);
        if ($price_info && isset($price_info['min_stay']) && $nuits < (int) $price_info['min_stay']) {
            return false;
        }

        // 2. Aucune nuit bloquee entre l'arrivee et la veille du depart.
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return true;
        }

        $chevauche = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE property_id = %s AND available = 0
               AND start_date < %s AND end_date >= %s",
            $property_id,
            $search_dates['check_out'],
            $search_dates['check_in']
        ));

        return 0 === $chevauche;
    }

    protected function get_price_info($property_id, $search_dates = null) {
        if (!$property_id || !$search_dates) {
            return null;
        }
        
        global $wpdb;
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        $check_in = $search_dates['check_in'];
        $check_out = $search_dates['check_out'];
        $guests = $this->get_guests_from_url();
        
        // Récupérer tous les jours entre check_in et check_out (avec included_guests et extra_guest_fee)
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT date, price_per_day, min_stay, cleaning_fee, tax_percentage, included_guests, extra_guest_fee, currency, website_id
             FROM $table_daily
             WHERE property_id = %s AND date >= %s AND date < %s
             ORDER BY date ASC",
            $property_id,
            $check_in,
            $check_out
        ));
        
        if (empty($results)) {
            return null;
        }
        
        // Calculer les totaux
        $nights_total = 0;
        $nights = 0;
        $max_min_stay = 1;
        $cleaning_fee = 0;
        $tax_percentage = 0;
        $included_guests = 2;
        $extra_guest_fee = 0;
        $currency = 'USD';
        $website_id = '453125';
        
        foreach ($results as $day) {
            $nights_total += floatval($day->price_per_day);
            $nights++;
            if ($day->min_stay > $max_min_stay) {
                $max_min_stay = intval($day->min_stay);
            }
            // Prendre les valeurs du premier jour (identiques pour tous)
            if ($nights === 1) {
                $cleaning_fee = floatval($day->cleaning_fee);
                $tax_percentage = floatval($day->tax_percentage);
                $included_guests = intval($day->included_guests) ?: 2;
                $extra_guest_fee = floatval($day->extra_guest_fee);
                $currency = $day->currency;
                $website_id = $day->website_id;
            }
        }
        
        // Calculer les frais de guests supplémentaires
        // included_guests = "additional_guests_starts_from" de Lodgify
        // Cela signifie que le supplément commence À PARTIR de ce nombre de guests
        // Exemple: si included_guests=3, alors guest 3 et + paient le supplément (2 guests inclus)
        // Donc avec 2 guests: 0 extra, avec 3 guests: 1 extra, avec 4 guests: 2 extra
        $extra_guests = ($guests >= $included_guests && $included_guests > 0) ? ($guests - $included_guests + 1) : 0;
        $extra_guests_total = $extra_guests * $extra_guest_fee * $nights;
        
        // Insurance fee fixe
        $insurance_fee = 28.99;
        
        // Calculer le total avec taxes et frais de guests supplémentaires
        // Formule Lodgify: taxes uniquement sur (nuits + extra guests), cleaning fee ajouté après
        $taxable_amount = $nights_total + $extra_guests_total;
        $tax_amount = $taxable_amount * ($tax_percentage / 100);
        $total_price = $taxable_amount + $tax_amount + $cleaning_fee + $insurance_fee;
        $avg_price = $nights > 0 ? $nights_total / $nights : 0;
        
        return [
            'price_per_day' => round($avg_price, 2),
            'min_stay' => $max_min_stay,
            'nights' => $nights,
            'nights_total' => round($nights_total, 2),
            'cleaning_fee' => round($cleaning_fee, 2),
            'cleaning_name' => 'Cleaning fee',
            'insurance_fee' => round($insurance_fee, 2),
            'insurance_name' => 'Insurance',
            'tax_percentage' => $tax_percentage,
            'tax_name' => 'Tax',
            'tax_amount' => round($tax_amount, 2),
            'included_guests' => $included_guests,
            'extra_guest_fee' => round($extra_guest_fee, 2),
            'extra_guests' => $extra_guests,
            'extra_guests_total' => round($extra_guests_total, 2),
            'total_price' => round($total_price, 2),
            'currency' => $currency,
            'website_id' => $website_id,
            'guests' => $guests
        ];
    }
    
    /**
     * ANCIENNE FONCTION - Récupérer room_type_id via API (fallback)
     */
    protected function get_room_type_id_from_api($property_id, $website_id) {
        $api_key = $this->get_api_key($website_id);
        
        $api_url = 'https://api.lodgify.com/v2/properties/' . $property_id . '?wid=' . $website_id . '&includeInOut=false';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'X-ApiKey' => $api_key,
                'accept' => 'application/json',
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['rooms'][0]['id'])) {
            return $data['rooms'][0]['id'];
        }
        
        return null;
    }
}
