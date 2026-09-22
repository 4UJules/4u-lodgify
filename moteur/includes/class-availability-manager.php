<?php
/**
 * Classe pour gérer les disponibilités des propriétés Lodgify
 *
 * @package FourU_Moteur_Availability_Sync
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe FourU_Moteur_Availability_Manager
 */
class FourU_Moteur_Availability_Manager {
    
    // Table des disponibilités
    private $table_availabilities;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_availabilities = $wpdb->prefix . 'lodgify_availabilities';
    }
    
    /**
     * Détecter la table JetBooking active
     */
    private function detect_jetbooking_table() {
        global $wpdb;
        $opt = get_option('lodgify_sync_jetbooking_table');
        if ($opt) { return $opt; }
        return $wpdb->prefix . 'jet_apartment_bookings';
    }

    /**
     * Détecter les colonnes JetBooking usuelles
     */
    private function detect_jetbooking_columns($table) {
        global $wpdb;
        $cols = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        
        error_log("Lodgify Sync: DEBUG - Colonnes trouvées dans $table:");
        if (!$cols) { 
            error_log("Lodgify Sync: ERREUR - Aucune colonne trouvée ou erreur SQL: " . $wpdb->last_error);
            return null; 
        }
        
        foreach ($cols as $col) {
            error_log("Lodgify Sync: Colonne: " . $col['Field'] . " (Type: " . $col['Type'] . ", Key: " . (isset($col['Key']) ? $col['Key'] : 'N/A') . ")");
        }
        
        $names = wp_list_pluck($cols, 'Field');
        $map = array(
            'apartment' => null,
            'check_in'  => null,
            'check_out' => null,
            'status'    => null,
            'user_id'   => null,
            'order_id'  => null,
            'primary'   => null,
        );
        
        foreach ( $cols as $c ) {
            if ( isset($c['Key']) && $c['Key'] === 'PRI' ) { $map['primary'] = $c['Field']; }
        }
        foreach ( array('apartment_id','apartment') as $c ) if ( in_array($c, $names, true) ) { $map['apartment'] = $c; break; }
        foreach ( array('check_in','checkin','start_date','check_in_date') as $c ) if ( in_array($c, $names, true) ) { $map['check_in'] = $c; break; }
        foreach ( array('check_out','checkout','end_date','check_out_date') as $c ) if ( in_array($c, $names, true) ) { $map['check_out'] = $c; break; }
        foreach ( array('status','booking_status') as $c ) if ( in_array($c, $names, true) ) { $map['status'] = $c; break; }
        foreach ( array('user_id','customer_id') as $c ) if ( in_array($c, $names, true) ) { $map['user_id'] = $c; break; }
        foreach ( array('order_id','wc_order_id') as $c ) if ( in_array($c, $names, true) ) { $map['order_id'] = $c; break; }
        
        error_log("Lodgify Sync: Mapping des colonnes: " . json_encode($map));
        
        if ( ! $map['apartment'] || ! $map['check_in'] || ! $map['check_out'] ) { 
            error_log("Lodgify Sync: ERREUR - Colonnes essentielles manquantes. Apartment: " . ($map['apartment'] ?: 'MANQUANT') . ", Check-in: " . ($map['check_in'] ?: 'MANQUANT') . ", Check-out: " . ($map['check_out'] ?: 'MANQUANT'));
            return null; 
        }
        if ( ! $map['primary'] ) { $map['primary'] = 'id'; }
        
        error_log("Lodgify Sync: Détection des colonnes réussie ✓");
        return $map;
    }

    /**
     * Réconcilier JetBooking avec les périodes indisponibles Lodgify (available = 0)
     * 
     * LOGIQUE : On compare les périodes indisponibles de Lodgify avec les réservations
     * "external" de JetBooking. On utilise le mapping property_id -> apartment_id
     * via la meta 'rental-id' des posts WordPress.
     * Les dates sont normalisées en Y-m-d pour la comparaison (JetBooking stocke des timestamps).
     */
    private function reconcile_jetbooking_with_lodgify($website_id) {
        global $wpdb;
        
        error_log("Lodgify Reconcile: === DÉBUT RÉCONCILIATION pour website_id: $website_id ===");
        
        $table = $this->detect_jetbooking_table();
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s", DB_NAME, $table ) );
        if ( ! $exists ) { 
            error_log("Lodgify Reconcile: Table JetBooking '$table' n'existe pas");
            return array('deleted' => 0, 'inserted' => 0, 'has_status_column' => false, 'table_name' => $table, 'total_lodgify_unavailable' => 0, 'total_jetbooking_external' => 0); 
        }

        $columns = $this->detect_jetbooking_columns($table);
        if ( ! $columns ) { 
            error_log("Lodgify Reconcile: Impossible de détecter les colonnes");
            return array('deleted' => 0, 'inserted' => 0, 'has_status_column' => false, 'table_name' => $table, 'total_lodgify_unavailable' => 0, 'total_jetbooking_external' => 0); 
        }

        $has_status = !empty($columns['status']);

        // 1) Construire le mapping Lodgify property_id -> JetBooking apartment_id
        // via la meta 'rental-id' des posts WordPress
        $property_to_apartment = array();
        $rows_mapping = $wpdb->get_results(
            "SELECT post_id, meta_value as rental_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value != ''"
        );
        foreach ($rows_mapping as $row) {
            $property_to_apartment[$row->rental_id] = $row->post_id;
        }
        error_log("Lodgify Reconcile: Mapping property->apartment: " . count($property_to_apartment) . " entrées");

        // 2) Construire l'état désiré: périodes indisponibles Lodgify converties en apartment_id
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT property_id, start_date, end_date, booking_id, booking_status FROM {$this->table_availabilities} WHERE website_id = %s AND available = 0",
            $website_id
        ) );
        error_log("Lodgify Reconcile: Périodes indisponibles Lodgify: " . count($rows));
        
        // Clé normalisée: apartment_id|start_date(Y-m-d)|end_date(Y-m-d)
        $desired = array();
        if ($rows) {
            foreach ($rows as $r) {
                // Convertir property_id Lodgify en apartment_id JetBooking
                if (!isset($property_to_apartment[$r->property_id])) {
                    continue; // Pas de post WordPress correspondant
                }
                $apartment_id = $property_to_apartment[$r->property_id];
                $start = date('Y-m-d', strtotime($r->start_date));
                $end = date('Y-m-d', strtotime($r->end_date));
                $key = $apartment_id . '|' . $start . '|' . $end;
                $desired[$key] = $r;
            }
        }
        error_log("Lodgify Reconcile: Périodes désirées (avec apartment_id): " . count($desired));

        /* AMM_RECONCILE_SITESCOPE 2026-09-21 : l'etat desire ne couvre QUE ce
           website_id, alors que l'etat courant couvre toute la table. Sans ce
           garde-fou, la passe du compte A supprime les lignes du compte B, puis
           la passe de B supprime celles de A - la table oscille et ne contient
           jamais les deux. On borne donc la comparaison aux logements de ce
           compte, tous biens confondus (pas seulement ceux qui ont une periode
           bloquee, sinon un bien entierement libre ne serait jamais nettoye). */
        $apartments_this_site = array();
        $site_props = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT property_id FROM {$this->table_availabilities} WHERE website_id = %s",
            $website_id
        ) );
        foreach ( (array) $site_props as $spid ) {
            if ( isset( $property_to_apartment[ $spid ] ) ) {
                $apartments_this_site[ (string) $property_to_apartment[ $spid ] ] = true;
            }
        }
        error_log("Lodgify Reconcile: Logements de ce compte: " . count($apartments_this_site));

        // 3) Lire l'état actuel JetBooking (réservations 'external')
        /* AMM_RECONCILE_SCOPE 2026-09-21 : la reconciliation ne lisait que ses
           propres lignes (status = 'external'), laissant les lignes importees
           depuis l'iCal (status 'pending') hors de tout controle - d'ou les
           reservations annulees qui persistaient. Elle prend desormais
           possession de TOUTE la table : l'API est la source unique. */
        $where_status = '';
        $params = array();
        $sql = "SELECT * FROM {$table} {$where_status}";
        $existing = $params ? $wpdb->get_results( $wpdb->prepare($sql, $params) ) : $wpdb->get_results($sql);
        error_log("Lodgify Reconcile: Réservations JetBooking external: " . count($existing));

        // Clé normalisée: apartment_id|check_in(Y-m-d)|check_out(Y-m-d)
        $existing_map = array();
        if ($existing) {
            foreach ($existing as $row) {
                $apt  = isset($row->{$columns['apartment']}) ? $row->{$columns['apartment']} : null;
                $cin  = isset($row->{$columns['check_in']}) ? $row->{$columns['check_in']} : null;
                $cout = isset($row->{$columns['check_out']}) ? $row->{$columns['check_out']} : null;
                if ($apt === null || $cin === null || $cout === null) { continue; }

                /* AMM_RECONCILE_SITESCOPE : ignorer les logements d'un autre compte.
                   Le filtre est INCONDITIONNEL : si ce compte n'a aucun logement
                   sur ce site, il ne doit toucher a AUCUNE ligne. Un garde-fou
                   "si la liste est vide, ne filtre pas" ferait exactement
                   l'inverse et viderait la table (constate sur thehills). */
                if ( ! isset( $apartments_this_site[ (string) $apt ] ) ) {
                    continue;
                }
                
                // Normaliser les dates (timestamps -> Y-m-d)
                $cin_date = is_numeric($cin) ? date('Y-m-d', $cin) : $cin;
                $cout_date = is_numeric($cout) ? date('Y-m-d', $cout) : $cout;
                
                $key = $apt . '|' . $cin_date . '|' . $cout_date;
                $existing_map[$key] = $row;
            }
        }
        error_log("Lodgify Reconcile: Réservations JetBooking mappées: " . count($existing_map));

        $deleted_count = 0;
        $inserted_count = 0;

        // 4) Supprimer les réservations JetBooking external qui ne sont plus dans Lodgify
        // AMM_RECONCILE_SCOPE : suppression inconditionnelle, $existing_map
        // contient maintenant l'integralite de la table.
        {
            foreach ($existing_map as $key => $row) {
                if (!isset($desired[$key])) {
                    $primary_id = isset($row->{$columns['primary']}) ? (int)$row->{$columns['primary']} : 0;
                    error_log("Lodgify Reconcile: Suppression réservation obsolète: $key (ID: $primary_id)");
                    $deleted = $wpdb->delete($table, array( $columns['primary'] => $primary_id ) );
                    if ($deleted) { $deleted_count++; }
                }
            }
        }

        // 5) Insérer les réservations Lodgify manquantes dans JetBooking
        if (!empty($desired)) {
            foreach ($desired as $key => $lodgify_row) {
                if (isset($existing_map[$key])) { continue; }
                
                list($apt, $cin, $cout) = explode('|', $key);
                $cin_timestamp = strtotime($cin);
                $cout_timestamp = strtotime($cout);
                
                $data = array(
                    $columns['apartment'] => (int)$apt,
                    $columns['check_in']  => $cin_timestamp,
                    $columns['check_out'] => $cout_timestamp,
                );
                if ($has_status) { $data[$columns['status']] = 'external'; }
                if (!empty($columns['user_id'])) { $data[$columns['user_id']] = 0; }
                if (!empty($columns['order_id'])) { $data[$columns['order_id']] = 0; }
                
                $inserted = $wpdb->insert($table, $data);
                if ($inserted) {
                    $inserted_count++;
                    error_log("Lodgify Reconcile: Réservation insérée: $key (ID: " . $wpdb->insert_id . ")");
                } else {
                    error_log("Lodgify Reconcile: Échec insertion: $key - " . $wpdb->last_error);
                }
            }
        }

        error_log("Lodgify Reconcile: === FIN === Supprimées: $deleted_count, Insérées: $inserted_count");
        
        return array(
            'deleted' => $deleted_count,
            'inserted' => $inserted_count,
            'has_status_column' => $has_status,
            'table_name' => $table,
            'total_lodgify_unavailable' => count($desired),
            'total_jetbooking_external' => count($existing_map)
        );
    }
    
    /**
     * Mapping dynamique entre property_id Lodgify et apartment_id JetBooking
     * Utilise la meta 'rental-id' des posts WordPress pour construire le mapping
     */
    private function get_property_mapping() {
        global $wpdb;
        
        $mapping = array();
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value as rental_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value != '' AND meta_value IS NOT NULL"
        );
        
        foreach ($rows as $row) {
            // rental_id (= Lodgify property_id) => post_id (= JetBooking apartment_id)
            $mapping[$row->rental_id] = $row->post_id;
        }
        
        error_log("Lodgify Sync: Dynamic property mapping: " . count($mapping) . " properties found");
        
        return $mapping;
    }



    /**
     * Synchronisation intelligente : lit l'API Lodgify et synchronise avec JetBooking
     * Garde seulement les vraies réservations, supprime les blocages automatiques
     */
    public function smart_sync_jetbooking() {
        global $wpdb;
        
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        $total_deleted = 0;
        $total_kept = 0;
        $sync_details = array();
        
        error_log("Lodgify Sync: === DÉBUT SYNCHRONISATION INTELLIGENTE ===");
        
        // Sites configurés
        $websites = array('453125', '479060');
        
        foreach ($websites as $website_id) {
            error_log("Lodgify Sync: Traitement site $website_id");
            
            // 1) Récupérer les disponibilités DIRECTEMENT depuis l'API Lodgify (données fraîches)
            $api_key = null;
            if ($website_id == '453125') {
                $api_key = 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H';
            } elseif ($website_id == '479060') {
                $api_key = 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk';
            }
            
            if (!$api_key) {
                error_log("Lodgify Sync: Clé API non trouvée pour site $website_id");
                $sync_details[] = "Site $website_id: Clé API manquante";
                continue;
            }
            
            // Récupérer les données fraîches de l'API
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 year'));
            
            error_log("Lodgify Sync: Appel API pour site $website_id, dates: $start_date à $end_date");
            $fresh_availabilities = $this->fetch_all_availabilities($api_key, $start_date, $end_date);
            
            error_log("Lodgify Sync: API a retourné " . count($fresh_availabilities) . " propriétés pour site $website_id");
            
            if (empty($fresh_availabilities)) {
                error_log("Lodgify Sync: Aucune donnée API fraîche pour site $website_id");
                $sync_details[] = "Site $website_id: Aucune donnée API";
                continue;
            }
            
            // 2) Parser les périodes en dates individuelles et créer un index de disponibilité
            $lodgify_dates = array();
            $property_mapping = $this->get_property_mapping();
            
            foreach ($fresh_availabilities as $property) {
                $property_id = $property['property_id'];
                
                // Vérifier si on a un mapping pour cette property_id
                if (!isset($property_mapping[$property_id])) {
                    error_log("Lodgify Sync: Pas de mapping trouvé pour property_id $property_id");
                    continue;
                }
                
                $apartment_id = $property_mapping[$property_id];
                error_log("Lodgify Sync: Mapping trouvé: property_id $property_id -> apartment_id $apartment_id");
                
                if (!isset($property['periods']) || !is_array($property['periods'])) {
                    continue;
                }
                
                foreach ($property['periods'] as $period) {
                    $start = $period['start'];
                    $end = $period['end'];
                    $available = (int)$period['available'];
                    
                    // Convertir la période en dates individuelles
                    $current_date = new DateTime($start);
                    $end_date = new DateTime($end);
                    
                    while ($current_date <= $end_date) {
                        $date_str = $current_date->format('Y-m-d');
                        $key = $apartment_id . '|' . $date_str;
                        $lodgify_dates[$key] = $available;
                        $current_date->add(new DateInterval('P1D'));
                    }
                }
            }
            
            error_log("Lodgify Sync: Total dates indexées: " . count($lodgify_dates));
            
            // 3) Traiter JetBooking pour ce site
            $result = $this->sync_jetbooking_for_site($website_id, $lodgify_dates);
            $total_deleted += $result['deleted'];
            $total_kept += $result['kept'];
            $sync_details[] = "Site $website_id: {$result['deleted']} supprimées, {$result['kept']} conservées";
        }
        
        error_log("Lodgify Sync: === FIN SYNCHRONISATION INTELLIGENTE ===");
        error_log("Lodgify Sync: Total: $total_deleted supprimées, $total_kept conservées");
        
        return array(
            'success' => true,
            'deleted' => $total_deleted,
            'kept' => $total_kept,
            'details' => $sync_details
        );
    }
    
    /**
     * Synchronise JetBooking pour un site spécifique avec les dates Lodgify
     */
    private function sync_jetbooking_for_site($website_id, $lodgify_dates) {
        global $wpdb;
        
        // Détecter la table JetBooking
        $table = $this->detect_jetbooking_table();
        if (!$table) {
            error_log("Lodgify Sync: Table JetBooking non trouvée pour site $website_id");
            return array('deleted' => 0, 'kept' => 0);
        }
        
        // Détecter les colonnes
        $columns = $this->detect_jetbooking_columns($table);
        if (empty($columns['apartment']) || empty($columns['check_in']) || empty($columns['check_out'])) {
            error_log("Lodgify Sync: Colonnes essentielles manquantes pour site $website_id");
            return array('deleted' => 0, 'kept' => 0);
        }
        
        error_log("Lodgify Sync: Table JetBooking: $table");
        error_log("Lodgify Sync: Colonnes détectées: " . json_encode($columns));
        
        // Récupérer toutes les réservations JetBooking
        $all_bookings = $wpdb->get_results("SELECT * FROM {$table}");
        error_log("Lodgify Sync: Réservations JetBooking trouvées: " . count($all_bookings));
        
        // Debug: lister les apartment_id uniques dans JetBooking
        $jetbooking_apartments = array();
        foreach ($all_bookings as $booking) {
            $apt = isset($booking->{$columns['apartment']}) ? $booking->{$columns['apartment']} : null;
            if ($apt && !in_array($apt, $jetbooking_apartments)) {
                $jetbooking_apartments[] = $apt;
            }
        }
        error_log("Lodgify Sync: Apartment IDs JetBooking trouvés: " . implode(', ', $jetbooking_apartments));
        
        $deleted_count = 0;
        $kept_count = 0;
        
        foreach ($all_bookings as $booking) {
            $apt = isset($booking->{$columns['apartment']}) ? $booking->{$columns['apartment']} : null;
            $cin_timestamp = isset($booking->{$columns['check_in']}) ? $booking->{$columns['check_in']} : null;
            $cout_timestamp = isset($booking->{$columns['check_out']}) ? $booking->{$columns['check_out']} : null;
            $booking_id = isset($booking->{$columns['primary']}) ? $booking->{$columns['primary']} : null;
            
            if (!$apt || !$cin_timestamp || !$cout_timestamp || !$booking_id) {
                continue;
            }
            
            // Convertir timestamps en dates
            $cin_date = is_numeric($cin_timestamp) ? date('Y-m-d', $cin_timestamp) : $cin_timestamp;
            $cout_date = is_numeric($cout_timestamp) ? date('Y-m-d', $cout_timestamp) : $cout_timestamp;
            
            // Vérifier si cette réservation JetBooking est sur des dates LIBRES dans Lodgify
            $booking_key = $apt . '|' . $cin_date . '|' . $cout_date;
            $should_delete = false;
            
            // Vérifier chaque jour de la réservation JetBooking
            $current_date = $cin_date;
            $dates_to_check = array();
            
            // Générer toutes les dates de la réservation
            while ($current_date < $cout_date) {
                $dates_to_check[] = $current_date;
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            // Vérifier si on a des données Lodgify pour cette propriété
            $has_lodgify_data = false;
            foreach ($dates_to_check as $check_date) {
                $lodgify_key = $apt . '|' . $check_date;
                if (isset($lodgify_dates[$lodgify_key])) {
                    $has_lodgify_data = true;
                    break;
                }
            }
            
            if ($has_lodgify_data) {
                // Vérifier si TOUTES les dates de la réservation sont LIBRES dans Lodgify
                $all_dates_available = true;
                foreach ($dates_to_check as $check_date) {
                    $lodgify_key = $apt . '|' . $check_date;
                    if (isset($lodgify_dates[$lodgify_key])) {
                        if ($lodgify_dates[$lodgify_key] == 0) {
                            // Cette date est indisponible dans Lodgify = réservation légitime
                            $all_dates_available = false;
                            break;
                        }
                    }
                }
                
                if ($all_dates_available) {
                    $should_delete = true;
                }
            }
            
            if ($should_delete) {
                $deleted = $wpdb->delete($table, array($columns['primary'] => $booking_id));
                if ($deleted) {
                    $deleted_count++;
                    error_log("Lodgify Sync: Réservation supprimée (dates libres): $booking_key (ID: $booking_id)");
                }
            } else {
                $kept_count++;
            }
        }
        
        return array('deleted' => $deleted_count, 'kept' => $kept_count);
    }
    
    /**
     * Nettoyer uniquement les réservations JetBooking obsolètes (sans synchronisation Lodgify)
     */
    public function cleanup_jetbooking_reservations() {
        global $wpdb;
        
        error_log("Lodgify Sync: === DÉBUT NETTOYAGE JETBOOKING ===");
        
        $total_deleted = 0;
        $cleanup_details = array();
        
        // Parcourir chaque site configuré
        foreach (array('453125', '479060') as $website_id) {
            error_log("Lodgify Sync: Nettoyage pour website_id: $website_id");
            
            $table = $this->detect_jetbooking_table();
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s", DB_NAME, $table ) );
            if ( ! $exists ) {
                error_log("Lodgify Sync: Table JetBooking '$table' n'existe pas");
                continue;
            }

            $columns = $this->detect_jetbooking_columns($table);
            if ( ! $columns ) {
                error_log("Lodgify Sync: Impossible de détecter les colonnes de la table JetBooking");
                continue;
            }
            
            // Vérifier qu'on peut identifier les réservations importées
            $has_import_id = in_array('import_id', wp_list_pluck($wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A), 'Field'));
            if (!$has_import_id) {
                error_log("Lodgify Sync: SÉCURITÉ - Pas de colonne import_id, nettoyage ignoré pour $website_id");
                $cleanup_details[] = "Site $website_id: Ignoré (pas de colonne import_id)";
                continue;
            }

            // 1) Lire les périodes indisponibles actuelles dans Lodgify
            $lodgify_unavailable = $wpdb->get_results( $wpdb->prepare(
                "SELECT property_id, start_date, end_date FROM {$this->table_availabilities} WHERE website_id = %s AND available = 0",
                $website_id
            ) );
            
            $desired_keys = array();
            if ($lodgify_unavailable) {
                foreach ($lodgify_unavailable as $r) {
                    $key = $r->property_id . '|' . $r->start_date . '|' . $r->end_date;
                    $desired_keys[$key] = true;
                    error_log("Lodgify Sync: Période indisponible Lodgify: $key");
                }
            }
            
            error_log("Lodgify Sync: Périodes indisponibles Lodgify pour site $website_id: " . count($desired_keys));

            // 2) Lire les réservations JetBooking importées (avec import_id)
            $sql = "SELECT * FROM {$table} WHERE import_id IS NOT NULL AND import_id != ''";
            $jetbooking_external = $wpdb->get_results( $sql );
            
            error_log("Lodgify Sync: Réservations JetBooking importées (avec import_id): " . count($jetbooking_external));
            
            // Debug: lister toutes les réservations JetBooking pour ce site
            $all_jetbooking = $wpdb->get_results( "SELECT * FROM {$table} LIMIT 10" );
            error_log("Lodgify Sync: DEBUG - Échantillon de toutes les réservations JetBooking:");
            foreach ($all_jetbooking as $booking) {
                $apt = isset($booking->{$columns['apartment']}) ? $booking->{$columns['apartment']} : 'N/A';
                $cin_timestamp = isset($booking->{$columns['check_in']}) ? $booking->{$columns['check_in']} : 'N/A';
                $cout_timestamp = isset($booking->{$columns['check_out']}) ? $booking->{$columns['check_out']} : 'N/A';
                $status = isset($booking->{$columns['status']}) ? $booking->{$columns['status']} : 'N/A';
                $import_id = isset($booking->import_id) ? $booking->import_id : 'N/A';
                $id = isset($booking->{$columns['primary']}) ? $booking->{$columns['primary']} : 'N/A';
                
                // Convertir les timestamps en dates lisibles
                $cin_date = ($cin_timestamp !== 'N/A' && is_numeric($cin_timestamp)) ? date('Y-m-d', $cin_timestamp) : $cin_timestamp;
                $cout_date = ($cout_timestamp !== 'N/A' && is_numeric($cout_timestamp)) ? date('Y-m-d', $cout_timestamp) : $cout_timestamp;
                
                error_log("Lodgify Sync: Réservation ID $id: apt=$apt, $cin_date à $cout_date, status=$status, import_id=$import_id");
            }

            $deleted_for_site = 0;
            
            // 3) Supprimer les réservations JetBooking qui ne sont plus dans Lodgify
            foreach ($jetbooking_external as $row) {
                $apt  = isset($row->{$columns['apartment']}) ? $row->{$columns['apartment']} : null;
                $cin_timestamp  = isset($row->{$columns['check_in']}) ? $row->{$columns['check_in']} : null;
                $cout_timestamp = isset($row->{$columns['check_out']}) ? $row->{$columns['check_out']} : null;
                
                if ($apt === null || $cin_timestamp === null || $cout_timestamp === null) {
                    continue;
                }
                
                // Convertir les timestamps en dates au format Y-m-d pour comparaison avec Lodgify
                if (is_numeric($cin_timestamp) && is_numeric($cout_timestamp)) {
                    $cin = date('Y-m-d', $cin_timestamp);
                    $cout = date('Y-m-d', $cout_timestamp);
                } else {
                    // Si ce ne sont pas des timestamps, les utiliser directement
                    $cin = $cin_timestamp;
                    $cout = $cout_timestamp;
                }
                
                $key = $apt . '|' . $cin . '|' . $cout;
                error_log("Lodgify Sync: Vérification réservation JetBooking: $key (timestamps: $cin_timestamp -> $cin, $cout_timestamp -> $cout)");
                
                if (!isset($desired_keys[$key])) {
                    $primary_id = isset($row->{$columns['primary']}) ? (int)$row->{$columns['primary']} : 0;
                    error_log("Lodgify Sync: Suppression de la réservation JetBooking obsolète: $key (ID: $primary_id)");
                    $deleted = $wpdb->delete($table, array( $columns['primary'] => $primary_id ) );
                    if ($deleted) {
                        $deleted_for_site++;
                        $total_deleted++;
                        error_log("Lodgify Sync: ✓ Réservation supprimée avec succès");
                    } else {
                        error_log("Lodgify Sync: ✗ Échec de la suppression");
                    }
                }
            }
            
            $cleanup_details[] = "Site $website_id: $deleted_for_site supprimées";
        }
        
        error_log("Lodgify Sync: === FIN NETTOYAGE ===");
        error_log("Lodgify Sync: Total réservations supprimées: $total_deleted");
        
        return array(
            'total_deleted' => $total_deleted,
            'details' => $cleanup_details
        );
    }
    
    /**
     * Récupérer les disponibilités depuis l'API Lodgify
     *
     * @param string $api_key Clé API Lodgify
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @param int $page Numéro de page pour la pagination
     * @param int $page_size Nombre d'éléments par page
     * @return array|WP_Error Réponse de l'API ou erreur
     */
    public function fetch_lodgify_availabilities($api_key, $start_date, $end_date, $page = 1, $page_size = 100) {
        $url = add_query_arg(
            array(
                'start' => $start_date,
                'end' => $end_date,
                'includeDetails' => 'true',
                'page' => $page,
                'pageSize' => $page_size
            ),
            'https://api.lodgify.com/v2/availability'
        );
        
        $args = array(
            'headers' => array(
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ),
            'timeout' => 120 // Augmenter le timeout pour les grandes quantités de données
        );
        
        return wp_remote_get($url, $args);
    }
    
    /**
     * Récupérer toutes les disponibilités avec pagination
     *
     * @param string $api_key Clé API Lodgify
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @return array Toutes les disponibilités
     */
    public function fetch_all_availabilities($api_key, $start_date, $end_date) {
        $all_availabilities = array();
        $page = 1;
        $page_size = 100;
        $has_more = true;
        /* AMM_PAGINATION_FIX : meme defaut que la boucle d'insertion, symptome
           different - ici array_merge gonfle la memoire a chaque tour. */
        $seen_property_ids = array();
        $max_pages = 50;
        
        error_log('Lodgify Availability Sync: fetch_all_availabilities starting (dates: ' . $start_date . ' to ' . $end_date . ')');
        
        while ($has_more) {
            error_log('Lodgify Availability Sync: Fetching page ' . $page . '...');
            $response = $this->fetch_lodgify_availabilities($api_key, $start_date, $end_date, $page, $page_size);
            
            if (is_wp_error($response)) {
                error_log('Lodgify Availability Sync: WP_Error on page ' . $page . ': ' . $response->get_error_message());
                break;
            }
            
            // Vérifier le code HTTP
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('Lodgify Availability Sync: HTTP ' . $http_code . ' on page ' . $page . ' - Body: ' . substr(wp_remote_retrieve_body($response), 0, 500));
                // Si rate limit (429), attendre et réessayer
                if ($http_code === 429) {
                    error_log('Lodgify Availability Sync: Rate limited, waiting 10s...');
                    sleep(10);
                    continue;
                }
                break;
            }
            
            $availabilities = json_decode($response['body'], true);
            
            if (empty($availabilities) || !is_array($availabilities)) {
                error_log('Lodgify Availability Sync: Invalid/empty response on page ' . $page . ' - Raw body length: ' . strlen($response['body']));
                break;
            }
            
            error_log('Lodgify Availability Sync: Page ' . $page . ' returned ' . count($availabilities) . ' properties');
            
            // AMM_PAGINATION_FIX2 : compter AVANT de fusionner, et ne rien
            // fusionner si la page est un doublon de la precedente.
            $new_on_this_page = 0;
            foreach ($availabilities as $pd) {
                $pid = isset($pd['property_id']) ? (string) $pd['property_id'] : '';
                if ('' === $pid || isset($seen_property_ids[$pid])) { continue; }
                $seen_property_ids[$pid] = true;
                $new_on_this_page++;
            }
            if (0 === $new_on_this_page) { break; }
            
            // Ajouter les disponibilités de cette page au tableau complet
            $all_availabilities = array_merge($all_availabilities, $availabilities);
            
            // Vérifier s'il y a plus de pages
            if (count($availabilities) < $page_size || 0 === $new_on_this_page || $page >= $max_pages) {
                $has_more = false;
            } else {
                $page++;
                // Petite pause pour éviter le rate limiting
                usleep(200000); // 0.2s
            }
        }
        
        error_log('Lodgify Availability Sync: fetch_all_availabilities completed - Total properties: ' . count($all_availabilities));
        
        return $all_availabilities;
    }
    
    /**
     * Synchroniser les disponibilités pour un site Lodgify
     * Traite PAGE PAR PAGE pour éviter l'épuisement mémoire (512 MB insuffisant pour tout charger)
     *
     * @param string $api_key Clé API Lodgify
     * @param string $website_id ID du site Lodgify
     * @param string $start_date Date de début
     * @param string $end_date Date de fin
     * @return array|false Succès avec résultat ou false en cas d'échec
     */
    public function sync_website_availabilities($api_key, $website_id, $start_date, $end_date) {
        global $wpdb;
        
        error_log('Lodgify Availability Sync: Starting sync for website ' . $website_id . ' (dates: ' . $start_date . ' to ' . $end_date . ')');
        error_log('Lodgify Availability Sync: Memory: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB');
        
        // Supprimer les anciennes données pour ce site AVANT de commencer l'insertion
        $wpdb->delete(
            $this->table_availabilities,
            array('website_id' => $website_id),
            array('%s')
        );
        
        $today = date('Y-m-d');
        $skipped_past = 0;
        $inserted_count = 0;
        $page = 1;
        $page_size = 50; // Pages plus petites pour économiser la mémoire
        $has_more = true;
        $total_properties = 0;
        /* AMM_PAGINATION_FIX 2026-09-20 : l'API v2/availability ignore page/pageSize
           (verifie : page=1,2,3 et sans pagination renvoient les memes 121 biens,
           signature SHA-256 identique). La condition "count < pageSize" n'etait
           donc jamais vraie -> boucle infinie. On suit les biens deja vus. */
        $seen_property_ids = array();
        $max_pages = 50;
        
        // Traiter page par page — chaque page est insérée puis libérée de la mémoire
        while ($has_more) {
            $response = $this->fetch_lodgify_availabilities($api_key, $start_date, $end_date, $page, $page_size);
            
            if (is_wp_error($response)) {
                error_log('Lodgify Availability Sync: WP_Error page ' . $page . ': ' . $response->get_error_message());
                break;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('Lodgify Availability Sync: HTTP ' . $http_code . ' page ' . $page);
                if ($http_code === 429) {
                    sleep(10);
                    continue;
                }
                break;
            }
            
            $body = wp_remote_retrieve_body($response);
            $availabilities = json_decode($body, true);
            unset($response, $body); // Libérer la mémoire immédiatement
            
            if (empty($availabilities) || !is_array($availabilities)) {
                break;
            }
            
            $page_count = count($availabilities);
            $total_properties += $page_count;
            
            // AMM_PAGINATION_FIX : combien de biens cette page apporte-t-elle de neufs ?
            $new_on_this_page = 0;
            foreach ($availabilities as $pd) {
                $pid = isset($pd['property_id']) ? (string) $pd['property_id'] : '';
                if ('' === $pid || isset($seen_property_ids[$pid])) { continue; }
                $seen_property_ids[$pid] = true;
                $new_on_this_page++;
            }
            
            /* AMM_PAGINATION_FIX2 : si la page n'apporte aucun bien nouveau, elle
               est un doublon de la precedente : on sort AVANT d'inserer, sinon on
               ecrit deux fois le catalogue. */
            if (0 === $new_on_this_page) {
                error_log('Lodgify Availability Sync: page ' . $page . ' identique a la precedente - arret avant insertion.');
                unset($availabilities);
                break;
            }
            
            // Insérer les données de cette page directement en DB
            foreach ($availabilities as $property_data) {
                $user_id = isset($property_data['user_id']) ? $property_data['user_id'] : '';
                $property_id = isset($property_data['property_id']) ? $property_data['property_id'] : '';
                $room_type_id = isset($property_data['room_type_id']) ? $property_data['room_type_id'] : '';

                if (isset($property_data['periods']) && is_array($property_data['periods'])) {
                    foreach ($property_data['periods'] as $period) {
                        $period_end = isset($period['end']) ? $period['end'] : '';
                        if (!empty($period_end) && $period_end < $today) {
                            $skipped_past++;
                            continue;
                        }
                        
                        $available_flag = isset($period['available']) ? (int) $period['available'] : 0;
                        $booking_id = isset($period['bookingId']) ? $period['bookingId'] : (isset($period['booking_id']) ? $period['booking_id'] : null);
                        $booking_status = isset($period['status']) ? $period['status'] : (isset($period['booking_status']) ? $period['booking_status'] : null);

                        $wpdb->insert(
                            $this->table_availabilities,
                            array(
                                'website_id' => $website_id,
                                'user_id' => $user_id,
                                'property_id' => $property_id,
                                'room_type_id' => $room_type_id,
                                'start_date' => isset($period['start']) ? $period['start'] : '',
                                'end_date' => $period_end,
                                'available' => $available_flag,
                                'booking_id' => $booking_id,
                                'booking_status' => $booking_status,
                                'last_updated' => current_time('mysql')
                            ),
                            array(
                                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'
                            )
                        );
                        $inserted_count++;
                    }
                }
            }
            
            unset($availabilities); // Libérer la mémoire de cette page
            
            // Page suivante ?
            // AMM_PAGINATION_FIX : trois conditions, la premiere qui tombe gagne.
            //  1. page incomplete -> pagination honoree, derniere page
            //  2. aucun bien neuf -> pagination ignoree, on tourne en rond
            //  3. plafond de tours -> filet de securite
            if ($page_count < $page_size || 0 === $new_on_this_page || $page >= $max_pages) {
                if (0 === $new_on_this_page) {
                    error_log('Lodgify Availability Sync: page ' . $page . ' sans bien nouveau - pagination ignoree par l API, arret.');
                }
                if ($page >= $max_pages) {
                    error_log('Lodgify Availability Sync: plafond de ' . $max_pages . ' pages atteint, arret.');
                }
                $has_more = false;
            } else {
                $page++;
                usleep(200000); // 0.2s pause anti rate-limit
            }
        }
        
        error_log('Lodgify Availability Sync: Website ' . $website_id . ' - Properties: ' . $total_properties . ', Inserted: ' . $inserted_count . ', Skipped past: ' . $skipped_past . ', Memory: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB');
        
        if ($inserted_count === 0 && $total_properties === 0) {
            error_log('Lodgify Availability Sync: FAILED - No data received from API for website ' . $website_id);
            return false;
        }

        // Réconcilier JetBooking avec les périodes indisponibles (bookings)
        $reconciliation_result = $this->reconcile_jetbooking_with_lodgify($website_id);

        return array(
            'success' => true,
            'reconciliation' => $reconciliation_result
        );
    }
    
    /**
     * Récupérer les disponibilités
     *
     * @param array $args Arguments de filtrage
     * @return array Liste des disponibilités
     */
    public function get_availabilities($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'website_id' => '',
            'property_id' => '',
            'room_type_id' => '',
            'start_date' => '',
            'end_date' => '',
            'available' => 1,
            'orderby' => 'start_date',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_format = array();
        
        if ($args['website_id']) {
            $where[] = 'website_id = %s';
            $where_format[] = $args['website_id'];
        }
        
        if ($args['property_id']) {
            $where[] = 'property_id = %s';
            $where_format[] = $args['property_id'];
        }
        
        if ($args['room_type_id']) {
            $where[] = 'room_type_id = %s';
            $where_format[] = $args['room_type_id'];
        }
        
        if ($args['start_date']) {
            $where[] = 'start_date >= %s';
            $where_format[] = $args['start_date'];
        }
        
        if ($args['end_date']) {
            $where[] = 'end_date <= %s';
            $where_format[] = $args['end_date'];
        }
        
        $where[] = 'available = %d';
        $where_format[] = $args['available'];
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'start_date ASC';
        }
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare('LIMIT %d', $args['limit']);
            
            if ($args['offset'] > 0) {
                $limit_clause .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        $query = "SELECT * FROM $this->table_availabilities $where_clause ORDER BY $orderby $limit_clause";
        
        if (!empty($where_format)) {
            $query = $wpdb->prepare($query, $where_format);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Vérifier si une propriété est disponible pour une période donnée
     *
     * @param string $website_id ID du site Lodgify
     * @param string $property_id ID de la propriété
     * @param string $room_type_id ID du type de chambre
     * @param string $check_in Date d'arrivée
     * @param string $check_out Date de départ
     * @return bool Disponibilité
     */
    public function is_property_available($website_id, $property_id, $room_type_id, $check_in, $check_out) {
        global $wpdb;
        
        // Vérifier si la période est entièrement couverte par une période disponible
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table_availabilities 
            WHERE website_id = %s 
            AND property_id = %s 
            AND room_type_id = %s 
            AND available = 1 
            AND start_date <= %s 
            AND end_date >= %s",
            $website_id,
            $property_id,
            $room_type_id,
            $check_in,
            $check_out
        );
        
        $count = $wpdb->get_var($query);
        
        return $count > 0;
    }
    
    /**
     * Obtenir des statistiques sur les disponibilités
     *
     * @return array Statistiques
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array(
            'total_records' => 0,
            'available_periods' => 0,
            'last_updated' => null,
            'properties_count' => 0,
            'websites_count' => 0
        );
        
        // Nombre total d'enregistrements
        $stats['total_records'] = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_availabilities");
        
        // Nombre de périodes disponibles
        $stats['available_periods'] = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_availabilities WHERE available = 1");
        
        // Dernière mise à jour
        $stats['last_updated'] = $wpdb->get_var("SELECT MAX(last_updated) FROM $this->table_availabilities");
        
        // Nombre de propriétés uniques
        $stats['properties_count'] = $wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(website_id, '-', property_id, '-', room_type_id)) FROM $this->table_availabilities");
        
        // Nombre de sites
        $stats['websites_count'] = $wpdb->get_var("SELECT COUNT(DISTINCT website_id) FROM $this->table_availabilities");
        
        return $stats;
    }
}
