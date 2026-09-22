<?php
/**
 * Classe pour gérer les prix des propriétés Lodgify
 *
 * @package FourU_Moteur_Availability_Sync
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe FourU_Moteur_Prices_Manager
 */
class FourU_Moteur_Prices_Manager {
    
    // Table des prix
    private $table_prices;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_prices = $wpdb->prefix . 'lodgify_prices';
    }
    
    /**
     * Récupérer les prix depuis l'API Lodgify
     *
     * @param string $api_key Clé API Lodgify
     * @param string $property_id ID de la propriété
     * @param string $room_type_id ID du type de chambre
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @return array|WP_Error Réponse de l'API ou erreur
     */
    public function fetch_lodgify_prices($api_key, $property_id, $room_type_id, $start_date, $end_date) {
        $url = add_query_arg(
            array(
                'RoomTypeId' => $room_type_id,
                'HouseId' => $property_id,
                'StartDate' => $start_date,
                'EndDate' => $end_date
            ),
            'https://api.lodgify.com/v2/rates/calendar'
        );
        
        $args = array(
            'headers' => array(
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ),
            'timeout' => 60
        );
        
        return wp_remote_get($url, $args);
    }
    
    /**
     * Enregistrer les prix dans la base de données
     *
     * @param int $availability_id ID de la disponibilité
     * @param float $price_per_day Prix par jour
     * @param int $min_stay Séjour minimum
     * @param string $currency Devise
     * @return int|false ID du prix inséré ou false en cas d'erreur
     */
    public function save_price($availability_id, $price_per_day, $min_stay, $currency = 'USD') {
        global $wpdb;
        
        // Vérifier si un prix existe déjà pour cette disponibilité
        $existing_price = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $this->table_prices WHERE availability_id = %d",
                $availability_id
            )
        );
        
        if ($existing_price) {
            // Mettre à jour le prix existant
            $result = $wpdb->update(
                $this->table_prices,
                array(
                    'price_per_day' => $price_per_day,
                    'min_stay' => $min_stay,
                    'currency' => $currency
                ),
                array('availability_id' => $availability_id),
                array('%f', '%d', '%s'),
                array('%d')
            );
            
            return $result !== false ? $existing_price : false;
        } else {
            // Insérer un nouveau prix
            $result = $wpdb->insert(
                $this->table_prices,
                array(
                    'availability_id' => $availability_id,
                    'price_per_day' => $price_per_day,
                    'min_stay' => $min_stay,
                    'currency' => $currency
                ),
                array('%d', '%f', '%d', '%s')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Récupérer le prix pour une disponibilité
     *
     * @param int $availability_id ID de la disponibilité
     * @return object|null Objet contenant le prix par jour, séjour minimum et la devise, ou null si non trouvé
     */
    public function get_price($availability_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT price_per_day, min_stay, currency FROM $this->table_prices WHERE availability_id = %d",
                $availability_id
            )
        );
    }
    
    /**
     * Récupérer tous les prix
     *
     * @param array $args Arguments de filtrage
     * @return array Liste des prix
     */
    public function get_prices($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'availability_id' => 0,
            'min_price' => 0,
            'max_price' => 0,
            'min_stay' => 0,
            'currency' => '',
            'orderby' => 'id',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_format = array();
        
        if ($args['availability_id']) {
            $where[] = 'availability_id = %d';
            $where_format[] = $args['availability_id'];
        }
        
        if ($args['min_price']) {
            $where[] = 'price_per_day >= %f';
            $where_format[] = $args['min_price'];
        }
        
        if ($args['max_price']) {
            $where[] = 'price_per_day <= %f';
            $where_format[] = $args['max_price'];
        }
        
        if ($args['min_stay']) {
            $where[] = 'min_stay >= %d';
            $where_format[] = $args['min_stay'];
        }
        
        if ($args['currency']) {
            $where[] = 'currency = %s';
            $where_format[] = $args['currency'];
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'id ASC';
        }
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare('LIMIT %d', $args['limit']);
            
            if ($args['offset'] > 0) {
                $limit_clause .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        $query = "SELECT * FROM $this->table_prices $where_clause ORDER BY $orderby $limit_clause";
        
        if (!empty($where_format)) {
            $query = $wpdb->prepare($query, $where_format);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Supprimer un prix
     *
     * @param int $price_id ID du prix à supprimer
     * @return bool Succès ou échec
     */
    public function delete_price($price_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_prices,
            array('id' => $price_id),
            array('%d')
        );
    }
    
    /**
     * Supprimer tous les prix pour une disponibilité
     *
     * @param int $availability_id ID de la disponibilité
     * @return bool Succès ou échec
     */
    public function delete_prices_by_availability($availability_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_prices,
            array('availability_id' => $availability_id),
            array('%d')
        );
    }
    
    /**
     * Synchroniser les prix pour une disponibilité
     *
     * @param string $api_key Clé API Lodgify
     * @param string $website_id ID du site Lodgify
     * @param string $property_id ID de la propriété
     * @param string $room_type_id ID du type de chambre
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @param int $availability_id ID de la disponibilité
     * @return bool Succès ou échec
     */
    public function sync_prices_for_availability($api_key, $website_id, $property_id, $room_type_id, $start_date, $end_date, $availability_id) {
        // Récupérer les données de prix de l'API Lodgify
        $response = $this->fetch_lodgify_prices($api_key, $property_id, $room_type_id, $start_date, $end_date);
        
        if (is_wp_error($response)) {
            error_log('Lodgify Price Sync: Error fetching price data for property ' . $property_id . ': ' . $response->get_error_message());
            return false;
        }
        
        $price_data = json_decode($response['body'], true);
        
        if (empty($price_data) || !isset($price_data['calendar_items']) || !is_array($price_data['calendar_items'])) {
            error_log('Lodgify Price Sync: Invalid price response for property ' . $property_id);
            return false;
        }
        
        // Supprimer les anciens prix pour cette disponibilité
        $this->delete_prices_by_availability($availability_id);
        
        $success = true;
        
        // Parcourir les éléments du calendrier
        foreach ($price_data['calendar_items'] as $calendar_item) {
            $date = $calendar_item['date'];
            
            // Vérifier si la date est dans la période de disponibilité
            if ($date >= $start_date && $date <= $end_date) {
                // Récupérer les informations de prix
                if (isset($calendar_item['prices']) && is_array($calendar_item['prices']) && !empty($calendar_item['prices'])) {
                    $price_info = $calendar_item['prices'][0]; // Prendre le premier prix disponible
                    
                    // Extraire min_stay et price_per_day
                    $min_stay = isset($price_info['min_stay']) ? $price_info['min_stay'] : 1;
                    $price_per_day = isset($price_info['price_per_day']) ? $price_info['price_per_day'] : 0;
                    
                    // Récupérer la devise depuis les paramètres de tarification
                    $currency = 'USD'; // Valeur par défaut
                    if (isset($price_data['rate_settings']) && isset($price_data['rate_settings']['currency_code'])) {
                        $currency = $price_data['rate_settings']['currency_code'];
                    }
                    
                    // Enregistrer le prix dans la base de données
                    $result = $this->save_price($availability_id, $price_per_day, $min_stay, $currency);
                    
                    if (!$result) {
                        error_log('Lodgify Price Sync: Error saving price for availability ' . $availability_id . ' on date ' . $date);
                        $success = false;
                    }
                    
                    // Nous n'avons besoin que d'un seul prix par disponibilité, donc nous pouvons sortir de la boucle
                    break;
                }
            }
        }
        
        return $success;
    }
}
