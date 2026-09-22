<?php
/**
 * Intégration avec Elementor - Dynamic Tags, Widgets et Ajax
 *
 * @package FourU_Moteur_Availability_Sync
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'intégration avec Elementor
 */
class FourU_Moteur_Elementor_Integration {
    
    /**
     * Constructeur
     */
    public function __construct() {
        // Vérifier si Elementor est activé
        if (!did_action('elementor/loaded')) {
            return;
        }
        
        // Ajouter la catégorie de widgets
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_categories'));
        
        // Enregistrer les widgets (nouvelle méthode pour Elementor 3.5+)
        add_action('elementor/widgets/register', array($this, 'register_widgets_new'));
        // Fallback pour anciennes versions
        add_action('elementor/widgets/widgets_registered', array($this, 'register_widgets'));
        
        // Enregistrer les Dynamic Tags
        add_action('elementor/dynamic_tags/register_tags', array($this, 'register_dynamic_tags'));
        add_action('elementor/dynamic_tags/register', array($this, 'register_dynamic_tags_new'));
        
        // Enregistrer les scripts et styles
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_styles'));
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        
        // Actions AJAX pour le calcul dynamique des prix
        add_action('wp_ajax_lodgify_get_price', array($this, 'ajax_get_price'));
        add_action('wp_ajax_nopriv_lodgify_get_price', array($this, 'ajax_get_price'));
    }
    
    /**
     * Ajouter une catégorie de widgets personnalisée
     */
    public function add_elementor_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'lodgify-availability-sync',
            array(
                'title' => __('Lodgify Availability', 'lodgify-availability-sync'),
                'icon' => 'fa fa-calendar',
            )
        );
    }
    
    /**
     * Enregistrer les widgets Elementor (nouvelle méthode 3.5+)
     */
    public function register_widgets_new($widgets_manager) {
        // Inclure les fichiers des widgets
        require_once plugin_dir_path(__FILE__) . 'widgets/price-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/booking-button-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/total-price-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/airbnb-booking-widget.php';
        
        // Enregistrer les widgets
        $widgets_manager->register(new FourU_Moteur_Price_Widget());
        $widgets_manager->register(new FourU_Moteur_Booking_Button_Widget());
        $widgets_manager->register(new FourU_Moteur_Total_Price_Widget());
        $widgets_manager->register(new FourU_Moteur_Airbnb_Booking_Elementor_Widget());
    }
    
    /**
     * Enregistrer les widgets Elementor (ancienne méthode)
     */
    public function register_widgets() {
        // Inclure les fichiers des widgets
        require_once plugin_dir_path(__FILE__) . 'widgets/price-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/booking-button-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/total-price-widget.php';
        require_once plugin_dir_path(__FILE__) . 'widgets/airbnb-booking-widget.php';
        
        // Enregistrer les widgets
        $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
        $widgets_manager->register_widget_type(new FourU_Moteur_Price_Widget());
        $widgets_manager->register_widget_type(new FourU_Moteur_Booking_Button_Widget());
        $widgets_manager->register_widget_type(new FourU_Moteur_Total_Price_Widget());
        $widgets_manager->register_widget_type(new FourU_Moteur_Airbnb_Booking_Elementor_Widget());
    }
    
    /**
     * Enregistrer les Dynamic Tags (nouvelle méthode)
     */
    public function register_dynamic_tags_new($dynamic_tags_manager) {
        // Enregistrer le groupe Lodgify
        $dynamic_tags_manager->register_group('lodgify', [
            'title' => __('Lodgify', 'lodgify-availability-sync')
        ]);
        
        // Inclure et enregistrer les tags
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/base-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/price-per-day-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/min-stay-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/total-stay-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/booking-url-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/nights-count-tag.php';
        
        $dynamic_tags_manager->register(new FourU_Moteur_Price_Per_Day_Tag());
        $dynamic_tags_manager->register(new FourU_Moteur_Min_Stay_Tag());
        $dynamic_tags_manager->register(new FourU_Moteur_Total_Stay_Tag());
        $dynamic_tags_manager->register(new FourU_Moteur_Booking_URL_Tag());
        $dynamic_tags_manager->register(new FourU_Moteur_Nights_Count_Tag());
    }
    
    /**
     * Enregistrer les Dynamic Tags (ancienne méthode)
     */
    public function register_dynamic_tags($dynamic_tags) {
        // Enregistrer le groupe Lodgify
        $dynamic_tags->register_group('lodgify', [
            'title' => __('Lodgify', 'lodgify-availability-sync')
        ]);
        
        // Inclure et enregistrer les tags
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/base-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/price-per-day-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/min-stay-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/total-stay-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/booking-url-tag.php';
        require_once plugin_dir_path(__FILE__) . 'dynamic-tags/nights-count-tag.php';
        
        $dynamic_tags->register_tag('FourU_Moteur_Price_Per_Day_Tag');
        $dynamic_tags->register_tag('FourU_Moteur_Min_Stay_Tag');
        $dynamic_tags->register_tag('FourU_Moteur_Total_Stay_Tag');
        $dynamic_tags->register_tag('FourU_Moteur_Booking_URL_Tag');
        $dynamic_tags->register_tag('FourU_Moteur_Nights_Count_Tag');
    }
    
    /**
     * Enregistrer les styles pour le frontend
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'lodgify-availability-sync-elementor',
            plugins_url('assets/css/elementor-widgets.css', dirname(__FILE__)),
            array(),
            '2.0.0'
        );
        
        // Style du widget Airbnb Booking - enqueuer directement
        wp_enqueue_style(
            'airbnb-booking-widget',
            plugins_url('assets/css/airbnb-booking.css', dirname(__FILE__)),
            array(),
            '1.3.8'
        );
    }
    
    /**
     * Enregistrer les scripts pour le frontend
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script(
            'lodgify-availability-sync-elementor',
            plugins_url('assets/js/elementor-widgets.js', dirname(__FILE__)),
            array('jquery'),
            '2.0.0',
            true
        );
        
        // Script pour l'Ajax des prix
        wp_enqueue_script(
            'lodgify-ajax-price',
            plugins_url('assets/js/ajax-price.js', dirname(__FILE__)),
            array('jquery'),
            '2.1.0',
            true
        );
        
        wp_localize_script('lodgify-ajax-price', 'lodgifyAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lodgify_price_nonce')
        ));
        
        // Script du widget Airbnb Booking - enqueuer directement
        wp_enqueue_script(
            'airbnb-booking-widget',
            plugins_url('assets/js/airbnb-booking.js', dirname(__FILE__)),
            array('jquery'),
            '1.5.1',
            true
        );
        
        wp_localize_script('airbnb-booking-widget', 'airbnbBooking', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lodgify_price_nonce'),
            'i18n' => array(
                'nights' => 'nights',
                'night' => 'night',
                'addDate' => 'Add Date',
                'checkAvailability' => 'Check Availability',
                'reserve' => 'Book Now',
                'noCharge' => 'You won\'t be charged yet',
                'enterDates' => 'Select Dates to See Booking Amount',
                'clearDates' => 'Clear dates',
                'close' => 'Close',
                'arrival' => 'CHECK-IN',
                'departure' => 'CHECK-OUT',
                'guestsLabel' => 'GUESTS',
                'guest' => 'guest',
                'guests' => 'guests',
                'for' => 'for',
            ),
            'months' => array(
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ),
            'days' => array('M', 'T', 'W', 'T', 'F', 'S', 'S'),
        ));
    }
    
    /**
     * Scripts pour l'éditeur Elementor
     */
    public function enqueue_editor_scripts() {
        wp_enqueue_script(
            'lodgify-elementor-editor',
            plugins_url('assets/js/elementor-editor.js', dirname(__FILE__)),
            array('jquery'),
            '2.0.0',
            true
        );
    }
    
    /**
     * AJAX handler pour récupérer les prix depuis la BDD locale (lodgify_daily_prices)
     * PAS D'APPEL API - données pré-synchronisées
     */
    public function ajax_get_price() {
        check_ajax_referer('lodgify_price_nonce', 'nonce');
        
        $property_id = isset($_POST['property_id']) ? sanitize_text_field($_POST['property_id']) : '';
        $check_in = isset($_POST['check_in']) ? sanitize_text_field($_POST['check_in']) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field($_POST['check_out']) : '';
        $guests = isset($_POST['guests']) ? intval($_POST['guests']) : 2;
        
        if (empty($property_id) || empty($check_in) || empty($check_out)) {
            wp_send_json_error(['message' => 'Missing parameters']);
            return;
        }
        
        global $wpdb;
        $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
        
        // Récupérer les prix depuis la BDD locale (avec included_guests et extra_guest_fee)
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
            wp_send_json_error(['message' => 'No price data available for these dates']);
            return;
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
        
        /* AMM_QUOTE 2026-09-21 : le devis Lodgify fait foi. L'ancien calcul ajoutait
           une "assurance" de 28,99 codee en dur que Lodgify ne facture pas : mesure sur
           9 sejours (3 biens x 3 plages), l'ecart etait de 28,99 exactement a chaque
           fois, tout le reste concordant au centime. */
        $insurance_fee = 0;
        $quote_total = null;
        $quote_breakdown = array();
        $quote_postes    = array();   // AMM_QUOTE_DETAIL : poste -> montant
        $quote_lignes    = array();   // AMM_QUOTE_DETAIL : lignes fines du devis
        
        $room_type_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT room_type_id FROM {$wpdb->prefix}lodgify_availabilities WHERE property_id = %s AND room_type_id <> '' LIMIT 1",
            $property_id
        ) );
        
        if ( $room_type_id ) {
            $api_settings_q = new FourU_Moteur_API_Settings();
            $cfg = $api_settings_q->get_api_by_website_id( $website_id );
            $api_key = is_array( $cfg ) && ! empty( $cfg['api_key'] ) ? $cfg['api_key'] : '';
        
            if ( $api_key ) {
        $quote_url = add_query_arg( array(
            'arrival'             => $check_in,
            'departure'           => $check_out,
            'roomTypes[0].Id'     => $room_type_id,
            'roomTypes[0].People' => max( 1, $guests ),
        ), 'https://api.lodgify.com/v2/quote/' . rawurlencode( $property_id ) );
        
        $qr = wp_remote_get( $quote_url, array(
            'headers' => array( 'X-ApiKey' => $api_key, 'accept' => 'application/json' ),
            'timeout' => 4,
        ) );
        
        if ( ! is_wp_error( $qr ) && 200 === (int) wp_remote_retrieve_response_code( $qr ) ) {
            $qd = json_decode( wp_remote_retrieve_body( $qr ), true );
            if ( ! empty( $qd[0]['currency_code'] ) ) { $currency = $qd[0]['currency_code']; }

            /* AMM_QUOTE_TIV 2026-09-21 : sur le compte 453125, Lodgify renvoie
               total_including_vat = null alors que les postes du devis sont bien
               presents. On additionne alors les sous-totaux, en respectant
               is_negative (les promotions comptent en negatif). */
            $somme = 0.0;
            $a_des_postes = false;
            if ( ! empty( $qd[0]['room_types'] ) ) {
                foreach ( $qd[0]['room_types'] as $rtq ) {
                    if ( empty( $rtq['price_types'] ) ) { continue; }
                    foreach ( $rtq['price_types'] as $pt ) {
                        if ( empty( $pt['subtotal'] ) ) { continue; }
                        $montant = (float) $pt['subtotal'];
                        if ( ! empty( $pt['is_negative'] ) ) { $montant = -$montant; }
                        $somme += $montant;
                        $a_des_postes = true;
                        $quote_breakdown[] = array(
                            'label'  => isset( $pt['description'] ) ? $pt['description'] : '',
                            'amount' => round( $montant, 2 ),
                        );
                        /* AMM_QUOTE_DETAIL : on retient aussi les lignes fines
                           (ménage, assurance, taxe…) pour que l'encadré affiche
                           le detail du devis et non celui du cache. */
                        $quote_postes[ isset( $pt['description'] ) ? $pt['description'] : '' ] = round( $montant, 2 );
                        if ( ! empty( $pt['prices'] ) ) {
                            foreach ( $pt['prices'] as $ligne ) {
                                if ( ! isset( $ligne['amount'] ) ) { continue; }
                                /* AMM_QUOTE_DETAIL2 : cloisonner par poste. Sans cela,
                                   la ligne du tarif nuitee (dont le libelle est le nom
                                   du bien) tombait dans les frais et gonflait le menage. */
                                $quote_lignes[ isset( $pt['description'] ) ? $pt['description'] : '' ][] = array(
                                    'label'  => isset( $ligne['description'] ) ? $ligne['description'] : '',
                                    'amount' => round( (float) $ligne['amount'], 2 ),
                                );
                            }
                        }
                    }
                }
            }
            if ( isset( $qd[0]['total_including_vat'] ) ) {
                $quote_total = (float) $qd[0]['total_including_vat'];
            } elseif ( $a_des_postes ) {
                $quote_total = round( $somme, 2 );
            }
        } else {
            error_log( 'Lodgify Quote: echec pour ' . $property_id . ' ' . $check_in . '->' . $check_out
                . ' (' . ( is_wp_error( $qr ) ? $qr->get_error_message() : wp_remote_retrieve_response_code( $qr ) ) . ')' );
        }
            }
        }
        
        // Calculer le total avec taxes et frais de guests supplémentaires
        // Formule Lodgify: taxes uniquement sur (nuits + extra guests), cleaning fee ajouté après
        $taxable_amount = $nights_total + $extra_guests_total;
        $tax_amount = $taxable_amount * ($tax_percentage / 100);
        $grand_total = $taxable_amount + $tax_amount + $cleaning_fee + $insurance_fee;
        
        /* AMM_QUOTE : le devis est la SEULE source. Pas de repli sur le calcul local :
           un total faux est pire qu'aucun total. Si l'API ne repond pas, aucun montant
           n'est affiche, le bouton Book reste actif. */
        $price_source = 'unavailable';
        $unavailable_message = '';
        if ( null !== $quote_total ) {
            $grand_total  = $quote_total;
            $price_source = 'quote';
            /* AMM_QUOTE_DETAIL 2026-09-21 : le total venait du devis mais tout le
               detail restait celui du cache, souvent perime - prix par nuit 325
               au lieu de 300, assurance 0 au lieu de 28,99, taxe 204,75 au lieu
               de 189. Quand le devis fait foi, le detail vient du devis aussi. */
            if ( isset( $quote_postes['Room rate'] ) ) {
                $nights_total  = $quote_postes['Room rate'];
                $price_per_day = $nights > 0 ? round( $nights_total / $nights, 2 ) : 0;
            }
            if ( isset( $quote_postes['Taxes'] ) ) {
                $tax_amount = $quote_postes['Taxes'];
                $tax_percentage = $nights_total > 0 ? round( $tax_amount / $nights_total * 100, 2 ) : $tax_percentage;
            }
            if ( isset( $quote_postes['Fees'] ) ) {
                // Un seul poste "Fees" cote Lodgify : on separe menage et assurance
                // sur le libelle des lignes, et tout le reste va dans les frais.
                $menage = 0; $assurance = 0; $autres = 0;
                $lignes_frais = isset( $quote_lignes['Fees'] ) ? $quote_lignes['Fees'] : array();
                foreach ( $lignes_frais as $l ) {
                    $lib = strtolower( $l['label'] );
                    if ( false !== strpos( $lib, 'clean' ) || false !== strpos( $lib, 'ménage' ) || false !== strpos( $lib, 'menage' ) ) {
                        $menage += $l['amount'];
                    } elseif ( false !== strpos( $lib, 'insur' ) || false !== strpos( $lib, 'assur' ) || false !== strpos( $lib, 'damage' ) ) {
                        $assurance += $l['amount'];
                    } elseif ( false === strpos( $lib, 'tax' ) && false === strpos( $lib, 'rate' ) ) {
                        $autres += $l['amount'];
                    }
                }
                if ( $menage || $assurance || $autres ) {
                    $cleaning_fee  = round( $menage + $autres, 2 );
                    $insurance_fee = round( $assurance, 2 );
                } else {
                    $cleaning_fee  = $quote_postes['Fees'];
                    $insurance_fee = 0;
                }
            }
        } else {
            $grand_total = null;
            $unavailable_message = __( 'Prix exact \u00e0 l\'\u00e9tape suivante', 'lodgify-availability-sync' );
        }
        $price_per_day = $nights > 0 ? $nights_total / $nights : 0;
        
        $currency_symbol = ($currency === 'EUR') ? '€' : '$';
        
        // Vérifier le min_stay
        $min_stay_error = false;
        $min_stay_message = '';
        if ($nights < $max_min_stay) {
            $min_stay_error = true;
            $min_stay_message = sprintf(
                __('Séjour minimum de %d nuits requis pour ces dates', 'lodgify-availability-sync'),
                $max_min_stay
            );
        }
        
        // URL de réservation - utiliser les settings API
        $api_settings = new FourU_Moteur_API_Settings();
        $api_config = $api_settings->get_api_by_website_id($website_id);
        $checkout_slug = $api_config ? $api_config['checkout_slug'] : 'thehills';
        
        $booking_url = 'https://checkout.lodgify.com/' . $checkout_slug . '/' . $property_id . '/addons';
        $booking_url .= '?currency=' . $currency . '&ref=bnbox';
        $booking_url .= '&arrival=' . $check_in . '&departure=' . $check_out . '&adults=' . $guests;
        
        wp_send_json_success([
            'price_per_day' => round($price_per_day, 2),
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
            'total' => ( null === $grand_total ? null : round($grand_total, 2) ),
            'min_stay' => $max_min_stay,
            'min_stay_error' => $min_stay_error,
            'min_stay_message' => $min_stay_message,
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'website_id' => $website_id,
            'booking_url' => $booking_url,
            'price_source' => $price_source,
            'unavailable_message' => $unavailable_message,
            'quote_breakdown' => $quote_breakdown
        ]);
    }
}

// Initialiser l'intégration Elementor
new FourU_Moteur_Elementor_Integration();
