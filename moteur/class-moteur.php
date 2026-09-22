<?php
/**
 * Moteur Lodgify — disponibilites, prix, widget de reservation, dynamic tags.
 *
 * MOTEUR_20260923 : code repris TEL QUEL de lodgify-availability-sync, sans
 * reecriture. Seules trois choses ont ete adaptees :
 *   - les classes `Lodgify_*` sont prefixees `FourU_Moteur_*`, pour que les
 *     deux extensions puissent cohabiter pendant la periode de comparaison ;
 *   - les constantes LODGIFY_SYNC_* deviennent FOURU_MOTEUR_* ;
 *   - le chargement pointe vers les modules deja presents dans 4u-lodgify
 *     (comptes, calendrier) au lieu de les redeclarer.
 *
 * Ce qui n'a PAS bouge, et ne doit pas bouger : noms des widgets Elementor,
 * actions AJAX, dynamic tags, tables, metas, transients, classes CSS.
 *
 * @package FourU_Lodgify
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

// Inclure les fichiers de classe
require_once plugin_dir_path(__FILE__) . 'includes/class-availability-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prices-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-api-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-daily-prices-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-min-stay-filter.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-sync-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-message-settings.php';

/* Le fichier de debug temporaire de l'ancien plugin n'est pas repris. */

// Inclure l'intégration Elementor
require_once plugin_dir_path(__FILE__) . 'elementor/elementor-integration.php';

// Inclure le widget Airbnb Booking
require_once plugin_dir_path(__FILE__) . 'includes/class-airbnb-booking-widget.php';

// Définir les constantes
if (!defined('FOURU_MOTEUR_VERSION')) {
    define('FOURU_MOTEUR_VERSION', '1.0.0');
}
if (!defined('FOURU_MOTEUR_URL')) {
    define('FOURU_MOTEUR_URL', plugin_dir_url(__FILE__));
}

class FourU_Moteur_Availability_Sync {
    
    // Clés API et identifiants des sites Lodgify
    private $api_keys = [
        [
            'key' => 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H',
            'website_id' => '453125'
        ],
        [
            'key' => 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk',
            'website_id' => '479060'
        ]
    ];
    
    // Préfixe des tables de base de données
    private $table_availabilities;
    private $table_prices;
    
    // Instances des gestionnaires
    private $availability_manager;
    private $prices_manager;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_availabilities = $wpdb->prefix . 'lodgify_availabilities';
        $this->table_prices = $wpdb->prefix . 'lodgify_prices';
        
        // Initialiser les gestionnaires
        $this->availability_manager = new FourU_Moteur_Availability_Manager();
        $this->prices_manager = new FourU_Moteur_Prices_Manager();
        
        // Activation et désactivation du plugin
        register_activation_hook(FOURU_LODGIFY_FILE, array($this, 'activate_plugin'));
        register_deactivation_hook(FOURU_LODGIFY_FILE, array($this, 'deactivate_plugin'));
        
        // Vérifier et créer les tables si nécessaire (pour les installations existantes)
        add_action('admin_init', array($this, 'maybe_create_tables'));
        
        // Auto-replanifier les crons s'ils ne sont pas planifiés
        add_action('admin_init', array($this, 'maybe_schedule_crons'));
        
        // Ajouter les hooks pour la synchronisation
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('lodgify_availability_sync_cron', array($this, 'sync_availabilities'));
        
        // Hook pour la synchronisation hebdomadaire des prix
        add_action('lodgify_weekly_prices_sync_cron', array($this, 'cron_sync_daily_prices'));
        
        // Ajouter l'intervalle hebdomadaire personnalisé
        add_filter('cron_schedules', array($this, 'add_weekly_cron_schedule'));
        
        // Ajouter les actions AJAX
        add_action('wp_ajax_sync_availabilities', array($this, 'ajax_sync_availabilities'));
        add_action('wp_ajax_sync_prices', array($this, 'ajax_sync_prices'));
        add_action('wp_ajax_cleanup_jetbooking_reservations', array($this, 'ajax_cleanup_jetbooking'));
        add_action('wp_ajax_smart_sync_jetbooking', array($this, 'ajax_smart_sync_jetbooking'));
        
        // AJAX pour le widget Airbnb Booking
        add_action('wp_ajax_lodgify_get_unavailable_dates', array($this, 'ajax_get_unavailable_dates'));
        add_action('wp_ajax_nopriv_lodgify_get_unavailable_dates', array($this, 'ajax_get_unavailable_dates'));
        
        // AJAX pour planifier le cron
        add_action('wp_ajax_lodgify_schedule_cron', array($this, 'ajax_schedule_cron'));
        
        // AJAX pour sauvegarder le log du batch de prix
        add_action('wp_ajax_lodgify_save_prices_log', array($this, 'ajax_save_prices_log'));
        
        // AJAX pour sauvegarder les paramètres des messages
        add_action('wp_ajax_lodgify_save_message_settings', array('FourU_Moteur_Message_Settings', 'ajax_save_settings'));
        
        // AJAX pour le nettoyage et la maintenance
        add_action('wp_ajax_lodgify_cleanup_old_data', array($this, 'ajax_cleanup_old_data'));
        add_action('wp_ajax_lodgify_truncate_resync', array($this, 'ajax_truncate_resync'));
        
        // AJAX pour la sync batch des disponibilités (1 site à la fois)
        add_action('wp_ajax_lodgify_sync_availability_site', array($this, 'ajax_sync_availability_site'));
        
        // Calendrier JetBooking : libérer le checkout day (le client part à 11h, un autre peut arriver)
        add_filter('jet-booking/assets/config', array($this, 'fix_checkout_day_in_calendar'));
    }
    
    /**
     * Ajouter l'intervalle hebdomadaire au cron WordPress
     */
    public function add_weekly_cron_schedule($schedules) {
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 jours en secondes
            'display' => __('Once Weekly', 'lodgify-availability-sync')
        );
        return $schedules;
    }
    
    /**
     * Activation du plugin
     */
    public function activate_plugin() {
        // Créer les tables de base de données
        $this->create_tables();
        
        // Planifier la tâche cron pour la synchronisation quotidienne des disponibilités
        if (!wp_next_scheduled('lodgify_availability_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'lodgify_availability_sync_cron');
        }
        
        // Planifier la tâche cron pour la synchronisation quotidienne des prix
        if (!wp_next_scheduled('lodgify_weekly_prices_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'lodgify_weekly_prices_sync_cron');
        }
    }
    
    /**
     * Auto-replanifier les crons s'ils ne sont pas planifiés
     */
    public function maybe_schedule_crons() {
        if (!wp_next_scheduled('lodgify_availability_sync_cron')) {
            wp_schedule_event(time() + 60, 'daily', 'lodgify_availability_sync_cron');
        }
        if (!wp_next_scheduled('lodgify_weekly_prices_sync_cron')) {
            wp_schedule_event(time() + 120, 'daily', 'lodgify_weekly_prices_sync_cron');
        }
    }
    
    /**
     * Vérifier et créer les tables si elles n'existent pas
     */
    public function maybe_create_tables() {
        global $wpdb;
        
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        // Vérifier si la table lodgify_daily_prices existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_daily'");
        
        if (!$table_exists) {
            $this->create_tables();
        }
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate_plugin() {
        global $wpdb;
        
        // Supprimer les tâches cron
        $timestamp = wp_next_scheduled('lodgify_availability_sync_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'lodgify_availability_sync_cron');
        }
        
        $timestamp_prices = wp_next_scheduled('lodgify_weekly_prices_sync_cron');
        if ($timestamp_prices) {
            wp_unschedule_event($timestamp_prices, 'lodgify_weekly_prices_sync_cron');
        }
        
        // Supprimer les tables de la base de données
        $wpdb->query("DROP TABLE IF EXISTS $this->table_prices");
        $wpdb->query("DROP TABLE IF EXISTS $this->table_availabilities");
    }
    
    /**
     * Synchronisation hebdomadaire des prix via cron
     */
    public function cron_sync_daily_prices() {
        error_log('Lodgify: Starting weekly prices sync via cron at ' . current_time('mysql'));
        
        // Enregistrer l'heure d'exécution
        update_option('lodgify_last_prices_cron_run', current_time('mysql'));
        
        $start_time = microtime(true);
        
        $daily_sync = new FourU_Moteur_Daily_Prices_Sync();
        $result = $daily_sync->sync_all_prices(12); // 12 mois
        
        $duration = microtime(true) - $start_time;
        
        // Enregistrer les logs
        FourU_Moteur_Sync_Logger::log_prices_sync([
            'success'        => isset($result['success']) ? $result['success'] : false,
            'synced'         => isset($result['synced']) ? $result['synced'] : 0,
            'properties'     => isset($result['properties']) ? $result['properties'] : 0,
            'errors'         => isset($result['errors']) ? $result['errors'] : [],
            'properties_log' => isset($result['properties_log']) ? $result['properties_log'] : [],
            'duration'       => $duration,
        ]);
        
        error_log('Lodgify: Weekly prices sync completed - ' . json_encode($result));
        
        return $result;
    }
    
    /**
     * AJAX pour planifier/replanifier les tâches cron
     */
    public function ajax_schedule_cron() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lodgify_schedule_cron_nonce')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité.'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
            return;
        }
        
        // Supprimer les anciennes tâches si elles existent
        $timestamp_avail = wp_next_scheduled('lodgify_availability_sync_cron');
        if ($timestamp_avail) {
            wp_unschedule_event($timestamp_avail, 'lodgify_availability_sync_cron');
        }
        
        $timestamp_prices = wp_next_scheduled('lodgify_weekly_prices_sync_cron');
        if ($timestamp_prices) {
            wp_unschedule_event($timestamp_prices, 'lodgify_weekly_prices_sync_cron');
        }
        
        // Replanifier les tâches (prochaine exécution dans 24h)
        wp_schedule_event(time() + 86400, 'daily', 'lodgify_availability_sync_cron');
        wp_schedule_event(time() + 86400, 'daily', 'lodgify_weekly_prices_sync_cron');
        
        // Vérifier que les tâches sont bien planifiées
        $next_avail = wp_next_scheduled('lodgify_availability_sync_cron');
        $next_prices = wp_next_scheduled('lodgify_weekly_prices_sync_cron');
        
        if ($next_avail && $next_prices) {
            wp_send_json_success(array(
                'message' => 'Tâches cron replanifiées avec succès !',
                'next_availability' => date_i18n('d/m/Y H:i:s', $next_avail),
                'next_prices' => date_i18n('d/m/Y H:i:s', $next_prices)
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la planification des tâches.'));
        }
    }
    
    /**
     * Création des tables de base de données
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des disponibilités
        $sql_availabilities = "CREATE TABLE $this->table_availabilities (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            website_id varchar(20) NOT NULL,
            user_id varchar(20) NOT NULL,
            property_id varchar(20) NOT NULL,
            room_type_id varchar(20) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            available tinyint(1) NOT NULL,
            booking_id varchar(20) DEFAULT NULL,
            booking_status varchar(50) DEFAULT NULL,
            last_updated datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY website_property_room (website_id,property_id,room_type_id),
            KEY date_range (start_date,end_date)
        ) $charset_collate;";
        
        // Table des prix avec min_stay et price_per_day
        $sql_prices = "CREATE TABLE $this->table_prices (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            availability_id mediumint(9) NOT NULL,
            price_per_day decimal(10,2) NOT NULL,
            min_stay int(5) NOT NULL DEFAULT 1,
            currency varchar(10) NOT NULL,
            PRIMARY KEY  (id),
            KEY availability_id (availability_id)
        ) $charset_collate;";
        
        // Table des prix journaliers pour toutes les dates
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        $sql_daily = "CREATE TABLE $table_daily (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            property_id varchar(20) NOT NULL,
            room_type_id varchar(20) NOT NULL,
            website_id varchar(20) NOT NULL,
            date date NOT NULL,
            price_per_day decimal(10,2) NOT NULL,
            min_stay int(5) NOT NULL DEFAULT 1,
            cleaning_fee decimal(10,2) DEFAULT 0,
            tax_percentage decimal(5,2) DEFAULT 0,
            included_guests int(3) DEFAULT 2,
            extra_guest_fee decimal(10,2) DEFAULT 0,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            last_updated datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY property_date (property_id, date),
            KEY date_idx (date),
            KEY website_idx (website_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_availabilities);
        dbDelta($sql_prices);
        dbDelta($sql_daily);
        
        // Ajouter les nouvelles colonnes si elles n'existent pas
        $this->maybe_add_extra_guest_columns();
    }
    
    /**
     * Ajouter les colonnes included_guests et extra_guest_fee si elles n'existent pas
     */
    private function maybe_add_extra_guest_columns() {
        global $wpdb;
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        // Vérifier si la colonne included_guests existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_daily LIKE 'included_guests'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_daily ADD COLUMN included_guests int(3) DEFAULT 2 AFTER tax_percentage");
        }
        
        // Vérifier si la colonne extra_guest_fee existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_daily LIKE 'extra_guest_fee'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_daily ADD COLUMN extra_guest_fee decimal(10,2) DEFAULT 0 AFTER included_guests");
        }
    }
    
    /**
     * Ajouter le menu d'administration
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Lodgify Availability Sync', 'lodgify-availability-sync'),
            __('Lodgify Sync', 'lodgify-availability-sync'),
            'manage_options',
            'lodgify-availability-sync',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Sous-menu pour les logs de synchronisation
        add_submenu_page(
            'lodgify-availability-sync',
            __('Logs de synchronisation', 'lodgify-availability-sync'),
            __('📋 Logs', 'lodgify-availability-sync'),
            'manage_options',
            'lodgify-sync-logs',
            array('FourU_Moteur_Sync_Logger', 'render_logs_page')
        );
        
        // Sous-menu pour la configuration des API
        add_submenu_page(
            'lodgify-availability-sync',
            __('Configuration API', 'lodgify-availability-sync'),
            __('⚙️ Configuration API', 'lodgify-availability-sync'),
            'manage_options',
            'lodgify-api-settings',
            array($this, 'render_api_settings_page')
        );
        
        // Sous-menu pour les messages et styles
        add_submenu_page(
            'lodgify-availability-sync',
            __('Messages & Styles', 'lodgify-availability-sync'),
            __('🎨 Messages & Styles', 'lodgify-availability-sync'),
            'manage_options',
            'lodgify-message-settings',
            array('FourU_Moteur_Message_Settings', 'render_page')
        );
    }
    
    /**
     * Afficher la page de configuration des API
     */
    public function render_api_settings_page() {
        $api_settings = new FourU_Moteur_API_Settings();
        $api_settings->render_settings_page();
    }
    
    /**
     * Synchroniser uniquement les prix
     */
    public function sync_prices() {
        global $wpdb;
        $success = true;
        
        // Récupérer toutes les disponibilités
        $availabilities = $wpdb->get_results("SELECT * FROM $this->table_availabilities WHERE available = 1");
        
        if (empty($availabilities)) {
            return false;
        }
        
        // Parcourir chaque disponibilité
        foreach ($availabilities as $availability) {
            // Trouver la clé API correspondante
            $api_key = '';
            foreach ($this->api_keys as $api_config) {
                if ($api_config['website_id'] == $availability->website_id) {
                    $api_key = $api_config['key'];
                    break;
                }
            }
            
            if (empty($api_key)) {
                continue;
            }
            
            // Synchroniser les prix pour cette disponibilité
            $this->prices_manager->sync_prices_for_availability(
                $api_key,
                $availability->website_id,
                $availability->property_id,
                $availability->room_type_id,
                $availability->start_date,
                $availability->end_date,
                $availability->id
            );
        }
        
        return $success;
    }
    
    /**
     * Afficher la page d'administration
     */
    public function render_admin_page() {
        $logs_url = admin_url('admin.php?page=lodgify-sync-logs');
        ?>
        <div class="wrap">
            <h1>Lodgify Availability Sync</h1>
            
            <!-- SYNCHRONISATION MANUELLE -->
            <div class="card" style="max-width: 100%;">
                <h2>🔄 Synchronisation manuelle</h2>
                <p>Lancez une synchronisation manuelle des données Lodgify.</p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
                    <div style="flex: 1; min-width: 280px; border: 1px solid #ddd; border-radius: 6px; padding: 15px; background: #f9fff9;">
                        <h3 style="margin-top: 0;">🏠 Disponibilités (2 ans)</h3>
                        <p style="font-size: 13px; color: #666;">Synchronise les disponibilités depuis l'API Lodgify et met à jour JetBooking. Traite chaque site séparément pour éviter les timeouts.</p>
                        <button id="sync-availability-btn" class="button button-primary" style="background-color: #28a745; border-color: #28a745;">
                            Synchroniser les disponibilités
                        </button>
                        <div id="sync-availability-status" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div style="flex: 1; min-width: 280px; border: 1px solid #ddd; border-radius: 6px; padding: 15px; background: #f5f9ff;">
                        <h3 style="margin-top: 0;">💰 Prix journaliers (12 mois)</h3>
                        <p style="font-size: 13px; color: #666;">Synchronise les prix, min_stay, cleaning fees et taxes pour toutes les propriétés sur 12 mois.</p>
                        <button id="sync-prices-btn" class="button button-primary" style="background-color: #007cba; border-color: #007cba;">
                            Synchroniser les prix
                        </button>
                        <div id="sync-prices-status" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <p style="margin-top: 15px;">
                    <a href="<?php echo esc_url($logs_url); ?>" class="button button-secondary">📋 Voir les logs de la dernière synchronisation</a>
                </p>
            </div>
            
            <!-- MAINTENANCE -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>🧹 Maintenance de la base de données</h2>
                <p style="font-size: 13px; color: #666;">Supprime toutes les périodes et prix dont la date est passée. Exécuté automatiquement à chaque sync cron, mais peut être lancé manuellement.</p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px;">
                    <button id="cleanup-old-data-btn" class="button button-secondary" style="background-color: #ffc107; border-color: #ffc107; color: #333;">
                        🧹 Purger les données anciennes
                    </button>
                    <button id="truncate-resync-btn" class="button button-secondary" style="background-color: #dc3545; border-color: #dc3545; color: #fff;">
                        🗑️ Vider tout et resynchroniser
                    </button>
                </div>
                <div id="cleanup-status" style="margin-top: 10px;"></div>
            </div>
            
            <!-- STATISTIQUES -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>📊 Statistiques</h2>
                <?php $this->display_stats(); ?>
            </div>
            
            <!-- TÂCHES CRON -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>⏰ Tâches Cron automatiques</h2>
                <?php $this->display_cron_status(); ?>
                <div style="margin-top: 15px;">
                    <button id="reschedule-cron-btn" class="button button-secondary">
                        🔄 Replanifier les tâches cron
                    </button>
                    <div id="reschedule-cron-status" style="margin-top: 10px;"></div>
                </div>
            </div>

            <!-- RÉSUMÉ DERNIÈRE SYNC -->
            <?php $this->display_last_sync_summary(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {

            // === SYNC DISPONIBILITÉS (BATCH PAR SITE) ===
            $('#sync-availability-btn').on('click', function() {
                var btn = $(this), status = $('#sync-availability-status');
                var nonce = '<?php echo wp_create_nonce('lodgify_sync_nonce'); ?>';
                if (btn.prop('disabled')) return;
                btn.prop('disabled', true);
                
                var sites = [
                    { website_id: '453125', label: 'Site 1 (453125)' },
                    { website_id: '479060', label: 'Site 2 (479060)' }
                ];
                var currentSite = 0, totalDeleted = 0, totalInserted = 0, siteErrors = [];
                var batchStart = Date.now();
                
                status.html(
                    '<p>⏳ Synchronisation des disponibilités...</p>' +
                    '<div style="background:#e0e0e0;border-radius:4px;height:24px;margin:10px 0;overflow:hidden;">' +
                        '<div id="avail-progress-bar" style="background:linear-gradient(90deg,#28a745,#5cb85c);height:100%;width:0%;transition:width 0.3s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:12px;"></div>' +
                    '</div>' +
                    '<div id="avail-progress-text" style="text-align:center;font-size:13px;"></div>'
                );
                
                function syncNextSite() {
                    if (currentSite >= sites.length) {
                        var duration = ((Date.now() - batchStart) / 1000).toFixed(1);
                        $('#avail-progress-bar').css('width', '100%').text('100%');
                        var msg = '✅ Synchronisation terminée en ' + duration + 's.';
                        msg += ' Insérées: ' + totalInserted + ', Supprimées JetBooking: ' + totalDeleted;
                        if (siteErrors.length > 0) msg += ' (' + siteErrors.length + ' erreurs)';
                        $('#avail-progress-text').html('<p style="color:green;margin-top:10px;">' + msg + '</p>');
                        $('#avail-progress-text').append('<p><a href="<?php echo esc_url($logs_url); ?>">📋 Voir les logs détaillés</a></p>');
                        btn.prop('disabled', false);
                        return;
                    }
                    
                    var site = sites[currentSite];
                    var pct = Math.round(((currentSite) / sites.length) * 100);
                    $('#avail-progress-bar').css('width', pct + '%').text(pct + '%');
                    $('#avail-progress-text').html('Traitement ' + site.label + ' (' + (currentSite+1) + '/' + sites.length + ')...');
                    
                    $.ajax({
                        url: ajaxurl, type: 'POST', timeout: 300000,
                        data: {
                            action: 'lodgify_sync_availability_site',
                            nonce: nonce,
                            website_id: site.website_id
                        },
                        success: function(r) {
                            if (r.success) {
                                totalInserted += r.data.inserted || 0;
                                totalDeleted += r.data.deleted || 0;
                            } else {
                                siteErrors.push(site.label + ': ' + r.data.message);
                            }
                            currentSite++;
                            syncNextSite();
                        },
                        error: function(x, s, e) {
                            siteErrors.push(site.label + ': ' + e);
                            currentSite++;
                            syncNextSite();
                        }
                    });
                }
                syncNextSite();
            });

            // === SYNC PRIX JOURNALIERS (BATCH) ===
            $('#sync-prices-btn').on('click', function() {
                var btn = $(this), status = $('#sync-prices-status');
                var nonce = '<?php echo wp_create_nonce('lodgify_sync_nonce'); ?>';
                if (btn.prop('disabled')) return;
                btn.prop('disabled', true);
                status.html('<p>⏳ Récupération de la liste des propriétés...</p>');
                
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'lodgify_get_properties_list', nonce: nonce },
                    success: function(response) {
                        if (!response.success) {
                            status.html('<p style="color: red;">❌ ' + response.data.message + '</p>');
                            btn.prop('disabled', false);
                            return;
                        }
                        
                        var properties = response.data.properties, total = properties.length;
                        var current = 0, synced = 0, errors = [], propsLog = [];
                        var batchStart = Date.now();
                        
                        status.html(
                            '<p>⏳ Synchronisation de ' + total + ' propriétés...</p>' +
                            '<div style="background:#e0e0e0;border-radius:4px;height:24px;margin:10px 0;overflow:hidden;">' +
                                '<div id="price-progress-bar" style="background:linear-gradient(90deg,#007cba,#00a0d2);height:100%;width:0%;transition:width 0.3s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:12px;"></div>' +
                            '</div>' +
                            '<div id="price-progress-text" style="text-align:center;font-size:13px;"></div>'
                        );
                        
                        function savePricesLog() {
                            var duration = (Date.now() - batchStart) / 1000;
                            $.ajax({
                                url: ajaxurl, type: 'POST',
                                data: {
                                    action: 'lodgify_save_prices_log', nonce: nonce,
                                    synced: synced, total: total, duration: duration,
                                    errors: errors, properties_log: propsLog
                                }
                            });
                        }
                        
                        function syncNext() {
                            if (current >= total) {
                                $('#price-progress-bar').css('width', '100%').text('100%');
                                var msg = '✅ ' + synced + ' prix synchronisés pour ' + total + ' propriétés';
                                if (errors.length > 0) msg += ' (' + errors.length + ' erreurs)';
                                $('#price-progress-text').html('<p style="color:green;margin-top:10px;">' + msg + '</p>');
                                $('#price-progress-text').append('<p><a href="<?php echo esc_url($logs_url); ?>">📋 Voir les logs détaillés</a></p>');
                                btn.prop('disabled', false);
                                savePricesLog();
                                return;
                            }
                            var prop = properties[current];
                            var pct = Math.round(((current+1)/total)*100);
                            $('#price-progress-bar').css('width', pct+'%').text(pct+'%');
                            $('#price-progress-text').html('Propriété '+(current+1)+'/'+total+' (ID: '+prop.property_id+')...');
                            
                            $.ajax({
                                url: ajaxurl, type: 'POST', timeout: 120000,
                                data: {
                                    action: 'lodgify_sync_single_property', nonce: nonce,
                                    property_id: prop.property_id,
                                    room_type_id: prop.room_type_id || '',
                                    website_id: prop.website_id || '',
                                    months: 12
                                },
                                success: function(r) {
                                    if (r.success) {
                                        synced += r.data.count;
                                        propsLog.push({property_id: prop.property_id, name: r.data.property_id || prop.property_id, count: r.data.count, status: 'success', error: ''});
                                    } else {
                                        errors.push(prop.property_id + ': ' + r.data.message);
                                        propsLog.push({property_id: prop.property_id, name: prop.property_id, count: 0, status: 'error', error: r.data.message});
                                    }
                                    current++; syncNext();
                                },
                                error: function(x,s,e) {
                                    errors.push(prop.property_id+': '+e);
                                    propsLog.push({property_id: prop.property_id, name: prop.property_id, count: 0, status: 'error', error: e});
                                    current++; syncNext();
                                }
                            });
                        }
                        syncNext();
                    },
                    error: function(x,s,e) {
                        status.html('<p style="color:red;">❌ Erreur: '+e+'</p>');
                        btn.prop('disabled', false);
                    }
                });
            });

            // === PURGER DONNÉES ANCIENNES ===
            $('#cleanup-old-data-btn').on('click', function() {
                var btn = $(this), status = $('#cleanup-status');
                btn.prop('disabled', true);
                status.html('<p>⏳ Nettoyage des anciennes données en cours...</p>');
                
                $.ajax({
                    url: ajaxurl, type: 'POST', timeout: 120000,
                    data: { action: 'lodgify_cleanup_old_data', nonce: '<?php echo wp_create_nonce('lodgify_cleanup_nonce'); ?>' },
                    success: function(r) {
                        if (r.success) {
                            status.html('<p style="color:green;">✅ ' + r.data.message + '</p>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<p style="color:red;">❌ ' + r.data.message + '</p>');
                        }
                        btn.prop('disabled', false);
                    },
                    error: function(x,s,e) {
                        status.html('<p style="color:red;">❌ Erreur: '+e+'</p>');
                        btn.prop('disabled', false);
                    }
                });
            });

            // === VIDER TOUT ET RESYNCHRONISER ===
            $('#truncate-resync-btn').on('click', function() {
                if (!confirm('⚠️ Attention !\n\nCette action va :\n1. Vider complètement la table des disponibilités\n2. Vider la table des prix journaliers\n3. Relancer une synchronisation complète\n\nCela peut prendre plusieurs minutes.\n\nContinuer ?')) {
                    return;
                }
                var btn = $(this), status = $('#cleanup-status');
                btn.prop('disabled', true);
                status.html('<p>⏳ Vidage des tables et resynchronisation en cours... Cela peut prendre plusieurs minutes.</p>');
                
                $.ajax({
                    url: ajaxurl, type: 'POST', timeout: 600000,
                    data: { action: 'lodgify_truncate_resync', nonce: '<?php echo wp_create_nonce('lodgify_truncate_nonce'); ?>' },
                    success: function(r) {
                        if (r.success) {
                            status.html('<p style="color:green;">✅ ' + r.data.message + '</p>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<p style="color:red;">❌ ' + r.data.message + '</p>');
                        }
                        btn.prop('disabled', false);
                    },
                    error: function(x,s,e) {
                        status.html('<p style="color:red;">❌ Erreur: '+e+'</p>');
                        btn.prop('disabled', false);
                    }
                });
            });

            // === REPLANIFIER CRON ===
            $('#reschedule-cron-btn').on('click', function() {
                var btn = $(this), status = $('#reschedule-cron-status');
                btn.prop('disabled', true);
                status.html('<p>⏳ Replanification en cours...</p>');
                
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'lodgify_schedule_cron', nonce: '<?php echo wp_create_nonce('lodgify_schedule_cron_nonce'); ?>' },
                    success: function(r) {
                        if (r.success) {
                            status.html('<p style="color:green;">✅ ' + r.data.message + '</p>');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            status.html('<p style="color:red;">❌ ' + r.data.message + '</p>');
                        }
                        btn.prop('disabled', false);
                    },
                    error: function(x,s,e) {
                        status.html('<p style="color:red;">❌ Erreur: '+e+'</p>');
                        btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Afficher le statut des tâches cron
     */
    private function display_cron_status() {
        $next_availability = wp_next_scheduled('lodgify_availability_sync_cron');
        $next_prices = wp_next_scheduled('lodgify_weekly_prices_sync_cron');
        $last_availability_run = get_option('lodgify_last_availability_cron_run', 'Jamais');
        $last_prices_run = get_option('lodgify_last_prices_cron_run', 'Jamais');
        ?>
        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Tâche', 'lodgify-availability-sync'); ?></th>
                    <th><?php echo esc_html__('Fréquence', 'lodgify-availability-sync'); ?></th>
                    <th><?php echo esc_html__('Prochaine exécution', 'lodgify-availability-sync'); ?></th>
                    <th><?php echo esc_html__('Dernière exécution', 'lodgify-availability-sync'); ?></th>
                    <th><?php echo esc_html__('Statut', 'lodgify-availability-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Sync Disponibilités</strong></td>
                    <td>Quotidienne</td>
                    <td><?php echo $next_availability ? esc_html(date_i18n('d/m/Y H:i:s', $next_availability)) : '-'; ?></td>
                    <td><?php echo esc_html($last_availability_run); ?></td>
                    <td><?php echo $next_availability ? '<span style="color: green;">✅ Planifiée</span>' : '<span style="color: red;">❌ Non planifiée</span>'; ?></td>
                </tr>
                <tr>
                    <td><strong>Sync Prix (12 mois)</strong></td>
                    <td>Quotidienne (24h)</td>
                    <td><?php echo $next_prices ? esc_html(date_i18n('d/m/Y H:i:s', $next_prices)) : '-'; ?></td>
                    <td><?php echo esc_html($last_prices_run); ?></td>
                    <td><?php echo $next_prices ? '<span style="color: green;">✅ Planifiée</span>' : '<span style="color: red;">❌ Non planifiée</span>'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Afficher les statistiques
     */
    private function display_stats() {
        // Récupérer les statistiques depuis le gestionnaire de disponibilités
        $stats = $this->availability_manager->get_stats();
        
        // Compter le nombre de prix enregistrés
        global $wpdb;
        $prices_count = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_prices");
        
        ?>
        <table class="widefat" style="margin-top: 10px;">
            <tr>
                <td><strong><?php echo esc_html__('Nombre total d\'enregistrements', 'lodgify-availability-sync'); ?></strong></td>
                <td><?php echo esc_html($stats['total_records']); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo esc_html__('Périodes disponibles', 'lodgify-availability-sync'); ?></strong></td>
                <td><?php echo esc_html($stats['available_periods']); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo esc_html__('Nombre de propriétés', 'lodgify-availability-sync'); ?></strong></td>
                <td><?php echo esc_html($stats['properties_count']); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo esc_html__('Prix enregistrés', 'lodgify-availability-sync'); ?></strong></td>
                <td><?php echo esc_html($prices_count); ?></td>
            </tr>
            <tr>
                <td><strong><?php echo esc_html__('Dernière mise à jour', 'lodgify-availability-sync'); ?></strong></td>
                <td><?php echo $stats['last_updated'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats['last_updated']))) : esc_html__('Jamais', 'lodgify-availability-sync'); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Afficher un résumé rapide de la dernière sync sur la page principale
     */
    private function display_last_sync_summary() {
        $avail_log = FourU_Moteur_Sync_Logger::get_availability_log();
        $prices_log = FourU_Moteur_Sync_Logger::get_prices_log();
        
        if (!$avail_log && !$prices_log) {
            return;
        }
        ?>
        <div class="card" style="margin-top: 20px; max-width: 100%;">
            <h2>📋 Résumé de la dernière synchronisation</h2>
            <table class="widefat striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Résumé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($avail_log): ?>
                    <tr>
                        <td><strong>🏠 Disponibilités</strong></td>
                        <td><?php echo esc_html($avail_log['timestamp']); ?></td>
                        <td>
                            <?php echo $avail_log['status'] === 'success' 
                                ? '<span style="color:#28a745;font-weight:bold;">✅ Succès</span>' 
                                : '<span style="color:#dc3545;font-weight:bold;">❌ Erreur</span>'; ?>
                        </td>
                        <td>
                            <?php 
                            $parts = [];
                            if ($avail_log['deleted'] > 0) $parts[] = $avail_log['deleted'] . ' supprimées';
                            if ($avail_log['kept'] > 0) $parts[] = $avail_log['kept'] . ' conservées';
                            if ($avail_log['inserted'] > 0) $parts[] = $avail_log['inserted'] . ' insérées';
                            echo esc_html(implode(' · ', $parts) ?: 'Aucun changement');
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($prices_log): ?>
                    <tr>
                        <td><strong>💰 Prix journaliers</strong></td>
                        <td><?php echo esc_html($prices_log['timestamp']); ?></td>
                        <td>
                            <?php echo $prices_log['status'] === 'success' 
                                ? '<span style="color:#28a745;font-weight:bold;">✅ Succès</span>' 
                                : '<span style="color:#dc3545;font-weight:bold;">❌ Erreur</span>'; ?>
                        </td>
                        <td>
                            <?php 
                            echo esc_html($prices_log['total_synced'] . ' prix pour ' . $prices_log['total_properties'] . ' propriétés');
                            if (!empty($prices_log['errors'])) echo ' · ' . count($prices_log['errors']) . ' erreur(s)';
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=lodgify-sync-logs')); ?>">📋 Voir les logs détaillés →</a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Synchronisation AJAX des disponibilités
     */
    public function ajax_sync_availabilities() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sync_lodgify_availabilities_nonce')) {
            wp_send_json_error(array('message' => __('Erreur de sécurité.', 'lodgify-availability-sync')));
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions nécessaires.', 'lodgify-availability-sync')));
            return;
        }
        
        // Exécuter la synchronisation
        $result = $this->sync_availabilities();
        
        if ($result) {
            // Récupérer les détails de réconciliation
            $reconciliation_data = get_option('lodgify_sync_last_reconciliation', array());
            $message = __('Synchronisation réussie.', 'lodgify-availability-sync');
            
            if (!empty($reconciliation_data)) {
                $message .= '<br><br><strong>Réconciliation JetBooking :</strong><br>';
                $message .= 'Total supprimé : ' . $reconciliation_data['total_deleted'] . '<br>';
                $message .= 'Total inséré : ' . $reconciliation_data['total_inserted'] . '<br>';
                
                if (!empty($reconciliation_data['details'])) {
                    $message .= '<br><strong>Détails par site :</strong><br>';
                    foreach ($reconciliation_data['details'] as $detail) {
                        $message .= '• ' . $detail . '<br>';
                    }
                }
                
                if (!empty($reconciliation_data['timestamp'])) {
                    $message .= '<br><em>Dernière réconciliation : ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($reconciliation_data['timestamp'])) . '</em>';
                }
            }
            
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => __('Erreur lors de la synchronisation.', 'lodgify-availability-sync')));
        }
    }
    
    /**
     * Synchronisation AJAX des prix
     */
    public function ajax_sync_prices() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sync_lodgify_prices_nonce')) {
            wp_send_json_error(array('message' => __('Erreur de sécurité.', 'lodgify-availability-sync')));
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions nécessaires.', 'lodgify-availability-sync')));
            return;
        }
        
        // Exécuter la synchronisation des prix
        $result = $this->sync_prices();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Synchronisation des prix réussie.', 'lodgify-availability-sync')));
        } else {
            wp_send_json_error(array('message' => __('Erreur lors de la synchronisation des prix.', 'lodgify-availability-sync')));
        }
    }
    
    /**
     * Synchroniser les disponibilités depuis l'API Lodgify
     */
    public function sync_availabilities() {
        // Augmenter les limites pour cette opération lourde
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        // Enregistrer un shutdown handler pour capturer les erreurs fatales
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
                error_log('Lodgify FATAL ERROR during sync: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
                // Enregistrer l'échec dans les logs
                FourU_Moteur_Sync_Logger::log_availability_sync([
                    'success' => false,
                    'error'   => 'Fatal: ' . $error['message'],
                    'details' => ['Fatal error in ' . $error['file'] . ':' . $error['line']],
                ]);
            }
        });
        
        error_log('Lodgify: Starting availability sync via cron at ' . current_time('mysql'));
        
        // Enregistrer l'heure d'exécution
        update_option('lodgify_last_availability_cron_run', current_time('mysql'));
        
        // Nettoyage : supprimer les anciennes périodes (end_date passée) et les anciens prix
        $this->cleanup_old_data();
        
        $start_time = microtime(true);
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+2 years'));
        
        $success = true;
        $total_deleted = 0;
        $total_inserted = 0;
        $reconciliation_details = array();
        
        // Parcourir chaque clé API
        foreach ($this->api_keys as $api_config) {
            $api_key = $api_config['key'];
            $website_id = $api_config['website_id'];
            
            // Synchroniser les disponibilités pour ce site
            $result = $this->availability_manager->sync_website_availabilities($api_key, $website_id, $start_date, $end_date);
            
            if (is_array($result) && isset($result['success']) && $result['success']) {
                if (isset($result['reconciliation'])) {
                    $recon = $result['reconciliation'];
                    $total_deleted += $recon['deleted'];
                    $total_inserted += $recon['inserted'];
                    $reconciliation_details[] = "Site $website_id: " . $recon['deleted'] . " supprimées, " . $recon['inserted'] . " insérées (Table: " . $recon['table_name'] . ", Status: " . ($recon['has_status_column'] ? 'OUI' : 'NON') . ")";
                }
            } elseif (!$result || (is_array($result) && !$result['success'])) {
                $success = false;
            }
        }
        
        $duration = microtime(true) - $start_time;
        
        // Stocker les détails pour l'affichage
        update_option('lodgify_sync_last_reconciliation', array(
            'total_deleted' => $total_deleted,
            'total_inserted' => $total_inserted,
            'details' => $reconciliation_details,
            'timestamp' => current_time('mysql')
        ));
        
        // Enregistrer les logs (cron sync)
        FourU_Moteur_Sync_Logger::log_availability_sync([
            'success'  => $success,
            'deleted'  => $total_deleted,
            'inserted' => $total_inserted,
            'details'  => $reconciliation_details,
            'duration' => $duration,
        ]);
        
        return $success;
    }
    

    /**
     * Nettoyer les anciennes données des tables (périodes passées + prix passés)
     * Appelé automatiquement avant chaque synchronisation
     */
    public function cleanup_old_data() {
        global $wpdb;
        $today = date('Y-m-d');
        
        // 1. Supprimer les périodes de disponibilité dont la end_date est passée
        $deleted_avail = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_availabilities} WHERE end_date < %s",
            $today
        ));
        error_log('Lodgify Cleanup: Deleted ' . $deleted_avail . ' old availability periods (end_date < ' . $today . ')');
        
        // 2. Supprimer les prix journaliers pour les dates passées
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_daily}'") === $table_daily) {
            $deleted_prices = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_daily} WHERE date < %s",
                $today
            ));
            error_log('Lodgify Cleanup: Deleted ' . $deleted_prices . ' old daily prices (date < ' . $today . ')');
        }
        
        // 3. Optimiser les tables après la suppression massive
        $wpdb->query("OPTIMIZE TABLE {$this->table_availabilities}");
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_daily}'") === $table_daily) {
            $wpdb->query("OPTIMIZE TABLE {$table_daily}");
        }
        
        error_log('Lodgify Cleanup: Tables optimized');
    }

    /**
     * AJAX : Purger les données anciennes
     */
    public function ajax_cleanup_old_data() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lodgify_cleanup_nonce')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité.'));
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
            return;
        }
        
        global $wpdb;
        $today = date('Y-m-d');
        
        // Compter avant suppression
        $count_avail_before = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_availabilities}");
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        $count_prices_before = $wpdb->get_var("SELECT COUNT(*) FROM {$table_daily}");
        
        $this->cleanup_old_data();
        
        // Compter après suppression
        $count_avail_after = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_availabilities}");
        $count_prices_after = $wpdb->get_var("SELECT COUNT(*) FROM {$table_daily}");
        
        $deleted_avail = $count_avail_before - $count_avail_after;
        $deleted_prices = $count_prices_before - $count_prices_after;
        
        wp_send_json_success(array(
            'message' => "Nettoyage terminé. Disponibilités : {$deleted_avail} supprimées ({$count_avail_after} restantes). Prix : {$deleted_prices} supprimés ({$count_prices_after} restants)."
        ));
    }

    /**
     * AJAX : Vider complètement les tables et relancer la sync
     */
    public function ajax_truncate_resync() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lodgify_truncate_nonce')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité.'));
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
            return;
        }
        
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        global $wpdb;
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        // 1. Vider les tables
        $wpdb->query("TRUNCATE TABLE {$this->table_availabilities}");
        $wpdb->query("TRUNCATE TABLE {$this->table_prices}");
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_daily}'") === $table_daily) {
            $wpdb->query("TRUNCATE TABLE {$table_daily}");
        }
        error_log('Lodgify Truncate: All tables truncated');
        
        // 2. Relancer la synchronisation des disponibilités
        $result = $this->sync_availabilities();
        
        if ($result) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_availabilities}");
            wp_send_json_success(array(
                'message' => "Tables vidées et resynchronisées avec succès. {$count} enregistrements de disponibilité insérés. Pensez à relancer la sync des prix séparément."
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Tables vidées mais la resynchronisation a échoué. Vérifiez les logs PHP pour plus de détails.'
            ));
        }
    }

    /**
     * AJAX : Sync les disponibilités pour UN SEUL website_id (mode batch)
     * Appelé par le JS site par site pour éviter le timeout
     */
    public function ajax_sync_availability_site() {
        check_ajax_referer('lodgify_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'));
            return;
        }
        
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        
        $website_id = isset($_POST['website_id']) ? sanitize_text_field($_POST['website_id']) : '';
        if (empty($website_id)) {
            wp_send_json_error(array('message' => 'website_id manquant.'));
            return;
        }
        
        // Déterminer la clé API pour ce website_id
        $api_keys = array(
            '453125' => 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H',
            '479060' => 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk',
        );
        
        if (!isset($api_keys[$website_id])) {
            wp_send_json_error(array('message' => "Clé API inconnue pour website_id: {$website_id}"));
            return;
        }
        
        $api_key = $api_keys[$website_id];
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+2 years'));
        
        // Appeler sync_website_availabilities (DELETE + INSERT + Reconcile JetBooking)
        $result = $this->availability_manager->sync_website_availabilities($api_key, $website_id, $start_date, $end_date);
        
        if (is_array($result) && !empty($result['success'])) {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_availabilities} WHERE website_id = %s",
                $website_id
            ));
            
            $reconciliation = isset($result['reconciliation']) && is_array($result['reconciliation']) ? $result['reconciliation'] : array();
            $deleted = isset($reconciliation['deleted']) ? $reconciliation['deleted'] : 0;
            $inserted_jb = isset($reconciliation['inserted']) ? $reconciliation['inserted'] : 0;
            
            wp_send_json_success(array(
                'message' => "Site {$website_id}: {$count} périodes synchronisées.",
                'inserted' => (int)$count,
                'deleted' => $deleted,
                'inserted_jetbooking' => $inserted_jb
            ));
        } else {
            wp_send_json_error(array(
                'message' => "Échec de la synchronisation pour le site {$website_id}. L'API a peut-être renvoyé une erreur."
            ));
        }
    }

    /**
     * Nettoyage AJAX des réservations JetBooking
     */
    public function ajax_cleanup_jetbooking() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cleanup_jetbooking_nonce')) {
            wp_send_json_error(array('message' => __('Erreur de sécurité.', 'lodgify-availability-sync')));
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions nécessaires.', 'lodgify-availability-sync')));
            return;
        }
        
        // Exécuter le nettoyage
        $result = $this->availability_manager->cleanup_jetbooking_reservations();
        
        if ($result && isset($result['total_deleted'])) {
            $message = __('Nettoyage terminé.', 'lodgify-availability-sync');
            $message .= '<br><br><strong>Résultats :</strong><br>';
            $message .= 'Total supprimé : ' . $result['total_deleted'] . '<br>';
            
            if (!empty($result['details'])) {
                $message .= '<br><strong>Détails :</strong><br>';
                foreach ($result['details'] as $detail) {
                    $message .= '• ' . $detail . '<br>';
                }
            }
            
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => __('Erreur lors du nettoyage.', 'lodgify-availability-sync')));
        }
    }

    /**
     * Synchronisation intelligente AJAX
     */
    public function ajax_smart_sync_jetbooking() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smart_sync_jetbooking_nonce')) {
            wp_send_json_error(array('message' => __('Erreur de sécurité.', 'lodgify-availability-sync')));
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas les permissions nécessaires.', 'lodgify-availability-sync')));
            return;
        }
        
        // Augmenter les limites pour cette opération lourde (114 propriétés)
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        $start_time = microtime(true);
        
        // Exécuter la synchronisation intelligente
        $result = $this->availability_manager->smart_sync_jetbooking();
        
        $duration = microtime(true) - $start_time;
        
        // Enregistrer les logs
        FourU_Moteur_Sync_Logger::log_availability_sync([
            'success'  => isset($result['success']) ? $result['success'] : false,
            'deleted'  => isset($result['deleted']) ? $result['deleted'] : 0,
            'kept'     => isset($result['kept']) ? $result['kept'] : 0,
            'details'  => isset($result['details']) ? $result['details'] : [],
            'duration' => $duration,
            'error'    => isset($result['error']) ? $result['error'] : '',
        ]);
        
        if ($result && isset($result['success']) && $result['success']) {
            $message = __('Synchronisation intelligente terminée !', 'lodgify-availability-sync');
            $message .= '<br><br><strong>Résultats :</strong><br>';
            $message .= 'Réservations supprimées : ' . $result['deleted'] . '<br>';
            $message .= 'Réservations conservées : ' . $result['kept'] . '<br>';
            $message .= 'Durée : ' . round($duration, 1) . 's<br>';
            
            if (!empty($result['details'])) {
                $message .= '<br><strong>Détails par site :</strong><br>';
                foreach ($result['details'] as $detail) {
                    $message .= '• ' . $detail . '<br>';
                }
            }
            
            wp_send_json_success(array('message' => $message));
        } else {
            $error_msg = isset($result['error']) ? $result['error'] : __('Erreur lors de la synchronisation intelligente.', 'lodgify-availability-sync');
            wp_send_json_error(array('message' => $error_msg));
        }
    }

    /**
     * Sauvegarder le log du batch de prix (appelé par JS à la fin du batch)
     */
    public function ajax_save_prices_log() {
        check_ajax_referer('lodgify_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $log_data = [
            'success'        => true,
            'synced'         => isset($_POST['synced']) ? intval($_POST['synced']) : 0,
            'properties'     => isset($_POST['total']) ? intval($_POST['total']) : 0,
            'errors'         => isset($_POST['errors']) ? array_map('sanitize_text_field', (array) $_POST['errors']) : [],
            'properties_log' => [],
            'duration'       => isset($_POST['duration']) ? floatval($_POST['duration']) : 0,
        ];
        
        // Reconstruire properties_log depuis les données JS
        if (isset($_POST['properties_log']) && is_array($_POST['properties_log'])) {
            foreach ($_POST['properties_log'] as $p) {
                $log_data['properties_log'][] = [
                    'property_id' => sanitize_text_field($p['property_id'] ?? ''),
                    'name'        => sanitize_text_field($p['name'] ?? ''),
                    'count'       => intval($p['count'] ?? 0),
                    'status'      => sanitize_text_field($p['status'] ?? 'success'),
                    'error'       => sanitize_text_field($p['error'] ?? ''),
                ];
            }
        }
        
        FourU_Moteur_Sync_Logger::log_prices_sync($log_data);
        
        // Mettre à jour aussi l'heure d'exécution
        update_option('lodgify_last_prices_cron_run', current_time('mysql'));
        
        wp_send_json_success(['message' => 'Logs sauvegardés']);
    }
    
    /**
     * Récupérer les dates indisponibles pour le widget Airbnb Booking
     */
    public function ajax_get_unavailable_dates() {
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        
        if (empty($property_id)) {
            wp_send_json_success(array());
            return;
        }
        
        global $wpdb;
        $unavailable = array();
        
        // 1. Dates indisponibles depuis lodgify_availabilities (périodes non-disponibles)
        $table_availabilities = $wpdb->prefix . 'lodgify_availabilities';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_availabilities}'") === $table_availabilities) {
            $periods = $wpdb->get_results($wpdb->prepare(
                "SELECT start_date, end_date FROM {$table_availabilities} WHERE property_id = %s AND available = 0",
                $property_id
            ));
            foreach ($periods as $period) {
                $from = new DateTime($period->start_date);
                $to   = new DateTime($period->end_date);
                while ($from <= $to) {
                    $dateStr = $from->format('Y-m-d');
                    if (!in_array($dateStr, $unavailable)) {
                        $unavailable[] = $dateStr;
                    }
                    $from->modify('+1 day');
                }
            }
        }
        
        // 2. Dates des réservations JetBooking
        $jb_table = $wpdb->prefix . 'jet_apartment_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$jb_table}'") === $jb_table) {
            
            // Trouver TOUS les post_ids qui partagent ce rental-id (traductions Polylang)
            $all_post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rental-id' AND meta_value = %s",
                $property_id
            ));
            
            if (!empty($all_post_ids)) {
                $placeholders = implode(',', array_map('intval', $all_post_ids));
                $bookings = $wpdb->get_results(
                    "SELECT check_in_date, check_out_date FROM {$jb_table} 
                     WHERE apartment_id IN ($placeholders) 
                     AND status = 'pending'"
                );
                
                // Collecter les nuits occupées (ci+1 → co) et les checkin days
                $all_checkin = array();
                $all_occupied = array();
                
                foreach ($bookings as $booking) {
                    $ci = intval($booking->check_in_date);
                    $co = intval($booking->check_out_date);
                    
                    $all_checkin[] = date('Y-m-d', $ci);
                    
                    // Nuits occupées = du lendemain du checkin au checkout en base inclus
                    for ($d = $ci + 86400; $d <= $co; $d += 86400) {
                        $dateStr = date('Y-m-d', $d);
                        if (!in_array($dateStr, $all_occupied)) {
                            $all_occupied[] = $dateStr;
                        }
                    }
                }
                
                // Ajouter les nuits occupées
                foreach ($all_occupied as $dateStr) {
                    if (!in_array($dateStr, $unavailable)) {
                        $unavailable[] = $dateStr;
                    }
                }
                
                // Ajouter les checkin days SAUF s'ils ne sont pas une nuit occupée d'une autre résa
                // (dans ce cas ils restent libres = un checkout d'un autre client peut coïncider)
                foreach ($all_checkin as $ci_day) {
                    if (in_array($ci_day, $all_occupied) && !in_array($ci_day, $unavailable)) {
                        $unavailable[] = $ci_day;
                    }
                }
            }
        }
        
        sort($unavailable);
        wp_send_json_success($unavailable);
    }

    /**
     * Libérer le checkout day dans le calendrier JetBooking.
     * 
     * Problème : JetBooking marque le checkout day comme indisponible alors que
     * le client part à 11h et un nouveau peut arriver l'après-midi.
     * 
     * Logique : Le checkout day (dernier jour de la résa dans booked_dates) doit être
     * libéré SAUF si c'est aussi un check-in ou une nuit occupée d'une AUTRE réservation.
     * On traite TOUS les statuts (pending + external).
     */
    public function fix_checkout_day_in_calendar($config) {
        if (empty($config['booked_dates']) || empty($config['post_id'])) {
            return $config;
        }
        
        global $wpdb;
        $post_id = intval($config['post_id']);
        $jb_table = $wpdb->prefix . 'jet_apartment_bookings';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$jb_table}'") !== $jb_table) {
            return $config;
        }
        
        // Récupérer TOUTES les réservations actives (pending + external)
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT check_in_date, check_out_date, status FROM {$jb_table} 
             WHERE apartment_id = %d 
             AND status IN ('pending', 'external')
             AND check_out_date >= %d",
            $post_id,
            strtotime(date('Y-m-d'))
        ));
        
        if (empty($bookings)) {
            return $config;
        }
        
        // Collecter les checkout days, checkin days, et nuits occupées de chaque résa
        $checkout_days = array();
        $checkin_days = array();
        $occupied_nights = array(); // nuits entre check_in et check_out (excluant les deux)
        
        foreach ($bookings as $b) {
            $ci_ts = intval($b->check_in_date);
            $co_ts = intval($b->check_out_date);
            
            $ci_day = date('Y-m-d', $ci_ts);
            $co_day = date('Y-m-d', $co_ts);
            
            $checkin_days[] = $ci_day;
            $checkout_days[] = $co_day;
            
            // Nuits occupées = tous les jours entre ci et co (inclus ci et co)
            $d = $ci_ts;
            while ($d <= $co_ts) {
                $occupied_nights[] = date('Y-m-d', $d);
                $d += 86400;
            }
        }
        
        $checkout_days = array_unique($checkout_days);
        $occupied_nights = array_unique($occupied_nights);
        
        // Un checkout day doit être libéré SAUF si :
        // - C'est aussi un check-in d'une AUTRE réservation (arrivée le même jour → occupé)
        // - C'est une nuit occupée d'une réservation qui le couvre au milieu
        // La règle : on libère le checkout day s'il n'est PAS aussi un checkin day
        // et s'il n'est pas occupé par une autre réservation qui le couvre
        $final_free = array();
        foreach ($checkout_days as $co_day) {
            // Vérifier si ce jour est un checkin d'une AUTRE résa
            // Si oui, ne pas libérer (le nouveau client arrive)
            $is_also_checkin = false;
            foreach ($bookings as $b) {
                $b_ci = date('Y-m-d', intval($b->check_in_date));
                $b_co = date('Y-m-d', intval($b->check_out_date));
                // C'est le checkin d'une résa DIFFÉRENTE (pas la même qui checkout)
                if ($b_ci === $co_day && $b_co !== $co_day) {
                    // Vérifier que ce n'est pas la même résa qui a ce checkout
                    $is_also_checkin = true;
                    break;
                }
            }
            
            // Vérifier si ce jour est une nuit occupée au milieu d'une AUTRE réservation
            $is_mid_booking = false;
            foreach ($bookings as $b) {
                $b_ci = date('Y-m-d', intval($b->check_in_date));
                $b_co = date('Y-m-d', intval($b->check_out_date));
                // Ce jour est au milieu d'une autre résa (pas le checkout de cette résa)
                if ($co_day > $b_ci && $co_day < $b_co) {
                    $is_mid_booking = true;
                    break;
                }
            }
            
            if (!$is_also_checkin && !$is_mid_booking) {
                $final_free[] = $co_day;
            }
        }
        
        if (!empty($final_free)) {
            $config['booked_dates'] = array_values(array_diff($config['booked_dates'], $final_free));
            if (!empty($config['booked_next'])) {
                $config['booked_next'] = array_values(array_diff($config['booked_next'], $final_free));
            }
        }
        
        return $config;
    }

}

// Initialiser le plugin
/* Module autonome « Calendrier Lodgify ». Un seul point d'entree : ce require.
   Le dossier lodgify-calendar/ peut etre deplace tel quel vers 4u-lodgify. */
/* Calendrier : deja charge par 4u-lodgify.php, on ne le redeclare pas. */

/* Module autonome « Comptes Lodgify » : source unique des cles API.
   Le tableau $api_keys code en dur ne doit plus servir. */
/* Comptes : deja charge par 4u-lodgify.php, on ne le redeclare pas. */

$lodgify_availability_sync = new FourU_Moteur_Availability_Sync();

// Initialiser le filtre min_stay pour les résultats de recherche
new FourU_Moteur_Min_Stay_Search_Filter();
