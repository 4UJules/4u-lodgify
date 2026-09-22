<?php
/**
 * Synchronisation des prix journaliers Lodgify
 * Stocke tous les prix pour toutes les dates dans la BDD
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_Daily_Prices_Sync {
    
    private $table_daily;
    private $table_avail;
    
    // API Keys - chargées dynamiquement depuis les settings
    private $api_keys = [];
    
    public function __construct() {
        global $wpdb;
        $this->table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        $this->table_avail = $wpdb->prefix . 'lodgify_availabilities';
        
        // Charger les clés API depuis les settings
        $this->load_api_keys();
        
        // Hook AJAX
        add_action('wp_ajax_lodgify_sync_daily_prices', [$this, 'ajax_sync_prices']);
        add_action('wp_ajax_lodgify_sync_single_property', [$this, 'ajax_sync_single_property']);
        add_action('wp_ajax_lodgify_get_properties_list', [$this, 'ajax_get_properties_list']);
        add_action('wp_ajax_lodgify_test_api', [$this, 'ajax_test_api']);
    }
    
    /**
     * Charger les clés API depuis les settings
     */
    private function load_api_keys() {
        $api_settings = new FourU_Moteur_API_Settings();
        $this->api_keys = $api_settings->get_api_keys_array();
        
        // Fallback si aucune API configurée
        if (empty($this->api_keys)) {
            $this->api_keys = [
                '479060' => 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk',
                '453125' => 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H'
            ];
        }
    }
    
    /**
     * Récupérer toutes les API configurées
     */
    public function get_all_apis() {
        $api_settings = new FourU_Moteur_API_Settings();
        return $api_settings->get_all_apis();
    }
    
    /**
     * Synchroniser tous les prix pour les 12 prochains mois
     */
    public function sync_all_prices($months = 12) {
        global $wpdb;
        
        // Récupérer toutes les propriétés depuis les posts WordPress (meta field rental-id)
        $posts_with_rental = $wpdb->get_results(
            "SELECT DISTINCT pm.meta_value as property_id 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rental-id' 
             AND pm.meta_value != ''
             AND p.post_status = 'publish'"
        );
        
        if (empty($posts_with_rental)) {
            return ['success' => false, 'message' => 'Aucune propriété avec rental-id trouvée'];
        }
        
        // Convertir en format attendu
        $properties = [];
        foreach ($posts_with_rental as $post) {
            $property_id = $post->property_id;
            
            // Récupérer website_id et room_type_id depuis lodgify_availabilities si disponible
            $avail_data = $wpdb->get_row($wpdb->prepare(
                "SELECT website_id, room_type_id FROM $this->table_avail WHERE property_id = %s LIMIT 1",
                $property_id
            ));
            
            $properties[] = (object)[
                'property_id' => $property_id,
                'room_type_id' => $avail_data ? $avail_data->room_type_id : null,
                'website_id' => $avail_data ? $avail_data->website_id : null
            ];
        }
        
        if (empty($properties)) {
            return ['success' => false, 'message' => 'Aucune propriété trouvée'];
        }
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$months} months"));
        
        $synced = 0;
        $errors = [];
        $properties_log = [];
        
        foreach ($properties as $prop) {
            // Récupérer le nom de la propriété pour les logs
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value = %s LIMIT 1",
                $prop->property_id
            ));
            $prop_name = $post_id ? get_the_title($post_id) : 'ID ' . $prop->property_id;
            
            $result = $this->sync_property_prices(
                $prop->property_id,
                $prop->room_type_id,
                $prop->website_id,
                $start_date,
                $end_date
            );
            
            if ($result['success']) {
                $synced += $result['count'];
                $properties_log[] = [
                    'property_id' => $prop->property_id,
                    'name'        => $prop_name,
                    'count'       => $result['count'],
                    'status'      => 'success',
                ];
            } else {
                $errors[] = "Property {$prop->property_id}: {$result['message']}";
                $properties_log[] = [
                    'property_id' => $prop->property_id,
                    'name'        => $prop_name,
                    'count'       => 0,
                    'status'      => 'error',
                    'error'       => $result['message'],
                ];
            }
            
            // Pause pour éviter rate limit API
            usleep(500000); // 0.5 secondes
        }
        
        return [
            'success'        => true,
            'synced'         => $synced,
            'properties'     => count($properties),
            'errors'         => $errors,
            'properties_log' => $properties_log,
        ];
    }
    
    /**
     * Synchroniser les prix d'une propriété
     */
    public function sync_property_prices($property_id, $room_type_id, $website_id, $start_date, $end_date) {
        global $wpdb;
        
        // Si website_id ou room_type_id manquant, essayer de les récupérer depuis l'API
        if (!$website_id || !$room_type_id) {
            $property_info = $this->get_property_info_from_api($property_id);
            if ($property_info) {
                $website_id = $property_info['website_id'];
                $room_type_id = $property_info['room_type_id'];
            } else {
                return ['success' => false, 'message' => 'Impossible de récupérer les infos de la propriété'];
            }
        }
        
        $api_key = $this->api_keys[$website_id] ?? $this->api_keys['453125'];
        
        // Appel API rates/calendar
        $api_url = add_query_arg([
            'RoomTypeId' => $room_type_id,
            'HouseId' => $property_id,
            'StartDate' => $start_date,
            'EndDate' => $end_date
        ], 'https://api.lodgify.com/v2/rates/calendar');
        
        $response = wp_remote_get($api_url, [
            'headers' => [
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ],
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['calendar_items']) || empty($data['calendar_items'])) {
            return ['success' => false, 'message' => 'Pas de données calendar_items'];
        }
        
        // Extraire cleaning_fee, tax_percentage, et extra_guest_fee depuis rate_settings
        $cleaning_fee = 0;
        $tax_percentage = 0;
        $included_guests = 2;
        $extra_guest_fee = 0;
        $currency = 'USD';
        
        if (isset($data['rate_settings'])) {
            $settings = $data['rate_settings'];
            $currency = $settings['currency_code'] ?? 'USD';
            
            // Log toutes les clés de rate_settings pour debug
            error_log("Lodgify rate_settings keys for property $property_id: " . implode(', ', array_keys($settings)));
            
            // Log le contenu complet de rate_settings (limité)
            error_log("Lodgify rate_settings for property $property_id: " . json_encode($settings));
            
            // Nombre de guests inclus dans le prix de base (additional_guests_starts_from)
            if (isset($settings['additional_guests_starts_from'])) {
                $included_guests = intval($settings['additional_guests_starts_from']);
            }
            
            // Frais par guest supplémentaire (price_per_additional_guest)
            if (isset($settings['price_per_additional_guest'])) {
                $extra_guest_fee = floatval($settings['price_per_additional_guest']);
            }
            
            // Frais de ménage
            if (isset($settings['fees'])) {
                foreach ($settings['fees'] as $fee) {
                    if (($fee['fee_type'] ?? '') === 'CleaningFee') {
                        $cleaning_fee = floatval($fee['price']['amount'] ?? 0);
                        break;
                    }
                }
            }
            
            // Taxes
            if (isset($settings['taxes'])) {
                foreach ($settings['taxes'] as $tax) {
                    if (isset($tax['price']['percentage'])) {
                        $tax_percentage = floatval($tax['price']['percentage']);
                    }
                    break;
                }
            }
        }
        
        // Log pour debug
        error_log("Lodgify Sync Property $property_id: included_guests=$included_guests, extra_guest_fee=$extra_guest_fee");
        
        // Insérer/mettre à jour chaque jour
        $count = 0;
        $now = current_time('mysql');
        
        // Log les premiers items pour debug
        $debug_count = 0;
        
        foreach ($data['calendar_items'] as $item) {
            if (!isset($item['date']) || !isset($item['prices'][0])) {
                continue;
            }
            
            $date = date('Y-m-d', strtotime($item['date']));
            $price_data = $item['prices'][0];
            $price = floatval($price_data['price_per_day'] ?? 0);
            $min_stay = intval($price_data['min_stay'] ?? 1);
            
            // Debug: log les 5 premiers prix pour vérifier
            if ($debug_count < 5) {
                error_log("Lodgify API Price Debug - Property $property_id, Date: $date, Price: $price, Min Stay: $min_stay");
                error_log("Lodgify API price_data keys: " . json_encode(array_keys($price_data)));
                error_log("Lodgify API price_data full: " . json_encode($price_data));
                $debug_count++;
            }
            
            // Extraire les frais de guests supplémentaires depuis prices[] (pas rate_settings)
            $item_extra_guest_fee = floatval($price_data['price_per_additional_guest'] ?? 0);
            $item_included_guests = intval($price_data['additional_guests_starts_from'] ?? 0);
            
            // Si additional_guests_starts_from est 0, ça veut dire pas de frais supplémentaires
            // Sinon, c'est le nombre de guests inclus dans le prix de base
            if ($item_included_guests > 0) {
                $included_guests = $item_included_guests;
            }
            if ($item_extra_guest_fee > 0) {
                $extra_guest_fee = $item_extra_guest_fee;
            }
            
            if ($price <= 0) {
                continue;
            }
            
            // REPLACE INTO pour insérer ou mettre à jour
            $result = $wpdb->query($wpdb->prepare(
                "REPLACE INTO $this->table_daily 
                (property_id, room_type_id, website_id, date, price_per_day, min_stay, cleaning_fee, tax_percentage, included_guests, extra_guest_fee, currency, last_updated)
                VALUES (%s, %s, %s, %s, %f, %d, %f, %f, %d, %f, %s, %s)",
                $property_id,
                $room_type_id,
                $website_id,
                $date,
                $price,
                $min_stay,
                $cleaning_fee,
                $tax_percentage,
                $item_included_guests > 0 ? $item_included_guests : $included_guests,
                $item_extra_guest_fee > 0 ? $item_extra_guest_fee : $extra_guest_fee,
                $currency,
                $now
            ));
            
            // Debug: vérifier si la requête a réussi
            if ($result === false) {
                error_log("Lodgify DB Error for $property_id on $date: " . $wpdb->last_error);
            }
            
            $count++;
        }
        
        return ['success' => true, 'count' => $count];
    }
    
    /**
     * Récupérer les infos d'une propriété depuis l'API Lodgify
     * Essaie les deux comptes (The Hills et Amazing Stay)
     */
    private function get_property_info_from_api($property_id) {
        // Essayer d'abord avec The Hills (453125)
        foreach (['453125', '479060'] as $website_id) {
            $api_key = $this->api_keys[$website_id];
            $api_url = 'https://api.lodgify.com/v2/properties/' . $property_id . '?wid=' . $website_id . '&includeInOut=false';
            
            $response = wp_remote_get($api_url, [
                'headers' => [
                    'X-ApiKey' => $api_key,
                    'accept' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                continue;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['rooms'][0]['id'])) {
                return [
                    'website_id' => $website_id,
                    'room_type_id' => $data['rooms'][0]['id'],
                    'currency' => $data['currency_code'] ?? 'USD'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Récupérer le prix pour une plage de dates depuis la BDD
     */
    public function get_prices_for_dates($property_id, $check_in, $check_out) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT date, price_per_day, min_stay, cleaning_fee, tax_percentage, currency, website_id
             FROM $this->table_daily
             WHERE property_id = %s AND date >= %s AND date < %s
             ORDER BY date ASC",
            $property_id,
            $check_in,
            $check_out
        ));
        
        if (empty($results)) {
            return null;
        }
        
        // Calculer le total
        $nights_total = 0;
        $nights = 0;
        $max_min_stay = 1;
        $cleaning_fee = 0;
        $tax_percentage = 0;
        $currency = 'USD';
        $website_id = '453125';
        
        foreach ($results as $day) {
            $nights_total += floatval($day->price_per_day);
            $nights++;
            if ($day->min_stay > $max_min_stay) {
                $max_min_stay = $day->min_stay;
            }
            $cleaning_fee = floatval($day->cleaning_fee);
            $tax_percentage = floatval($day->tax_percentage);
            $currency = $day->currency;
            $website_id = $day->website_id;
        }
        
        // Calculer le total avec taxes
        $tax_amount = $nights_total * ($tax_percentage / 100);
        $total_price = $nights_total + $cleaning_fee + $tax_amount;
        $avg_price = $nights > 0 ? $nights_total / $nights : 0;
        
        return [
            'price_per_day' => round($avg_price, 2),
            'min_stay' => $max_min_stay,
            'nights' => $nights,
            'nights_total' => round($nights_total, 2),
            'cleaning_fee' => round($cleaning_fee, 2),
            'tax_percentage' => $tax_percentage,
            'tax_amount' => round($tax_amount, 2),
            'total_price' => round($total_price, 2),
            'currency' => $currency,
            'website_id' => $website_id
        ];
    }
    
    /**
     * Récupérer le prix de base (minimum) pour une propriété
     */
    public function get_base_price($property_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT MIN(price_per_day) as min_price, 
                    AVG(min_stay) as avg_min_stay,
                    currency, website_id
             FROM $this->table_daily
             WHERE property_id = %s AND date >= CURDATE()
             GROUP BY property_id
             LIMIT 1",
            $property_id
        ));
        
        if ($result && $result->min_price > 0) {
            return [
                'price_per_day' => floatval($result->min_price),
                'min_stay' => intval($result->avg_min_stay),
                'currency' => $result->currency,
                'website_id' => $result->website_id,
                'is_base_price' => true
            ];
        }
        
        return null;
    }
    
    /**
     * AJAX handler pour synchroniser les prix (legacy - peut timeout)
     */
    public function ajax_sync_prices() {
        check_ajax_referer('lodgify_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $months = isset($_POST['months']) ? intval($_POST['months']) : 12;
        $result = $this->sync_all_prices($months);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    '%d prix synchronisés pour %d propriétés',
                    $result['synced'],
                    $result['properties']
                ),
                'errors' => $result['errors']
            ]);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler pour récupérer la liste des propriétés
     */
    public function ajax_get_properties_list() {
        check_ajax_referer('lodgify_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        global $wpdb;
        
        $posts_with_rental = $wpdb->get_results(
            "SELECT DISTINCT pm.meta_value as property_id 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rental-id' 
             AND pm.meta_value != ''
             AND p.post_status = 'publish'"
        );
        
        $properties = [];
        foreach ($posts_with_rental as $post) {
            $property_id = $post->property_id;
            
            $avail_data = $wpdb->get_row($wpdb->prepare(
                "SELECT website_id, room_type_id FROM $this->table_avail WHERE property_id = %s LIMIT 1",
                $property_id
            ));
            
            $properties[] = [
                'property_id' => $property_id,
                'room_type_id' => $avail_data ? $avail_data->room_type_id : null,
                'website_id' => $avail_data ? $avail_data->website_id : null
            ];
        }
        
        wp_send_json_success(['properties' => $properties, 'total' => count($properties)]);
    }
    
    /**
     * AJAX handler pour synchroniser une seule propriété
     */
    public function ajax_sync_single_property() {
        check_ajax_referer('lodgify_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        $room_type_id = isset($_POST['room_type_id']) ? sanitize_text_field($_POST['room_type_id']) : '';
        $website_id = isset($_POST['website_id']) ? sanitize_text_field($_POST['website_id']) : '';
        $months = isset($_POST['months']) ? intval($_POST['months']) : 12;
        
        if (empty($property_id)) {
            wp_send_json_error(['message' => 'Property ID manquant']);
        }
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$months} months"));
        
        $result = $this->sync_property_prices($property_id, $room_type_id, $website_id, $start_date, $end_date);
        
        if ($result['success']) {
            wp_send_json_success([
                'property_id' => $property_id,
                'count' => $result['count'],
                'message' => sprintf('Propriété %s: %d prix synchronisés', $property_id, $result['count'])
            ]);
        } else {
            wp_send_json_error([
                'property_id' => $property_id,
                'message' => $result['message']
            ]);
        }
    }
    
    /**
     * AJAX handler pour tester l'API Lodgify et comparer avec la DB
     */
    public function ajax_test_api() {
        check_ajax_referer('lodgify_test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        global $wpdb;
        
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        $test_date = isset($_POST['test_date']) ? sanitize_text_field($_POST['test_date']) : date('Y-m-d');
        
        if (empty($property_id)) {
            wp_send_json_error(['message' => 'Property ID manquant']);
        }
        
        // Récupérer les données de notre DB
        $db_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_daily WHERE property_id = %s AND date = %s",
            $property_id, $test_date
        ));
        
        // Récupérer website_id et room_type_id
        $avail_data = $wpdb->get_row($wpdb->prepare(
            "SELECT website_id, room_type_id FROM $this->table_avail WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        $website_id = $avail_data ? $avail_data->website_id : '453125';
        $room_type_id = $avail_data ? $avail_data->room_type_id : '';
        
        if (empty($room_type_id)) {
            // Essayer de récupérer depuis l'API
            $property_info = $this->get_property_info_from_api($property_id);
            if ($property_info) {
                $website_id = $property_info['website_id'];
                $room_type_id = $property_info['room_type_id'];
            }
        }
        
        $api_key = $this->api_keys[$website_id] ?? $this->api_keys['453125'];
        
        // Appel API
        $end_date = date('Y-m-d', strtotime($test_date . ' +1 day'));
        $api_url = add_query_arg([
            'RoomTypeId' => $room_type_id,
            'HouseId' => $property_id,
            'StartDate' => $test_date,
            'EndDate' => $end_date
        ], 'https://api.lodgify.com/v2/rates/calendar');
        
        $response = wp_remote_get($api_url, [
            'headers' => [
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erreur API: ' . $response->get_error_message()]);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Extraire les données de l'API
        $api_price = 0;
        $api_cleaning = 0;
        $api_tax = 0;
        $api_included_guests = 0;
        $api_extra_guest_fee = 0;
        
        if (isset($data['rate_settings'])) {
            $settings = $data['rate_settings'];
            
            // Cleaning fee
            if (isset($settings['fees'])) {
                foreach ($settings['fees'] as $fee) {
                    if (($fee['fee_type'] ?? '') === 'CleaningFee') {
                        $api_cleaning = floatval($fee['price']['amount'] ?? 0);
                        break;
                    }
                }
            }
            
            // Taxes
            if (isset($settings['taxes'])) {
                foreach ($settings['taxes'] as $tax) {
                    if (isset($tax['price']['percentage'])) {
                        $api_tax = floatval($tax['price']['percentage']);
                    }
                    break;
                }
            }
            
            // Extra guests
            $api_included_guests = intval($settings['additional_guests_starts_from'] ?? 0);
            $api_extra_guest_fee = floatval($settings['price_per_additional_guest'] ?? 0);
        }
        
        // Prix du jour
        if (isset($data['calendar_items'][0]['prices'][0])) {
            $price_data = $data['calendar_items'][0]['prices'][0];
            $api_price = floatval($price_data['price_per_day'] ?? 0);
            
            // Override avec les données du jour si présentes
            if (isset($price_data['additional_guests_starts_from']) && $price_data['additional_guests_starts_from'] > 0) {
                $api_included_guests = intval($price_data['additional_guests_starts_from']);
            }
            if (isset($price_data['price_per_additional_guest']) && $price_data['price_per_additional_guest'] > 0) {
                $api_extra_guest_fee = floatval($price_data['price_per_additional_guest']);
            }
        }
        
        wp_send_json_success([
            'api_price' => $api_price,
            'api_cleaning' => $api_cleaning,
            'api_tax' => $api_tax,
            'api_included_guests' => $api_included_guests,
            'api_extra_guest_fee' => $api_extra_guest_fee,
            'db_price' => $db_data ? floatval($db_data->price_per_day) : 0,
            'db_cleaning' => $db_data ? floatval($db_data->cleaning_fee) : 0,
            'db_tax' => $db_data ? floatval($db_data->tax_percentage) : 0,
            'db_included_guests' => $db_data ? intval($db_data->included_guests) : 0,
            'db_extra_guest_fee' => $db_data ? floatval($db_data->extra_guest_fee) : 0,
            'raw_api' => $data
        ]);
    }
}

// Initialiser
new FourU_Moteur_Daily_Prices_Sync();
