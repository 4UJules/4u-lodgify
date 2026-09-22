<?php
/**
 * Airbnb-style Booking Widget
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_Airbnb_Booking_Widget {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_shortcode('airbnb_booking_form', array($this, 'render_booking_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        if (is_singular()) {
            wp_enqueue_style(
                'airbnb-booking-widget',
                FOURU_MOTEUR_URL . 'assets/css/airbnb-booking.css',
                array(),
                FOURU_MOTEUR_VERSION
            );
            
            wp_enqueue_script(
                'airbnb-booking-widget',
                FOURU_MOTEUR_URL . 'assets/js/airbnb-booking.js',
                array('jquery'),
                FOURU_MOTEUR_VERSION,
                true
            );
            
            wp_localize_script('airbnb-booking-widget', 'airbnbBooking', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lodgify_price_nonce'),
                'i18n' => array(
                    'nights' => __('nuits', 'lodgify-sync'),
                    'night' => __('nuit', 'lodgify-sync'),
                    'addDate' => __('Ajouter une date', 'lodgify-sync'),
                    'checkAvailability' => __('Vérifier la disponibilité', 'lodgify-sync'),
                    'reserve' => __('Réserver', 'lodgify-sync'),
                    'noCharge' => __('Aucun montant ne vous sera débité pour le moment', 'lodgify-sync'),
                    'enterDates' => __('Indiquez vos dates pour afficher les prix', 'lodgify-sync'),
                    'clearDates' => __('Effacer les dates', 'lodgify-sync'),
                    'close' => __('Fermer', 'lodgify-sync'),
                    'arrival' => __('ARRIVÉE', 'lodgify-sync'),
                    'departure' => __('DÉPART', 'lodgify-sync'),
                    'guests' => __('VOYAGEURS', 'lodgify-sync'),
                    'guest' => __('voyageur', 'lodgify-sync'),
                    'for' => __('pour', 'lodgify-sync'),
                ),
                'months' => array(
                    __('Janvier', 'lodgify-sync'),
                    __('Février', 'lodgify-sync'),
                    __('Mars', 'lodgify-sync'),
                    __('Avril', 'lodgify-sync'),
                    __('Mai', 'lodgify-sync'),
                    __('Juin', 'lodgify-sync'),
                    __('Juillet', 'lodgify-sync'),
                    __('Août', 'lodgify-sync'),
                    __('Septembre', 'lodgify-sync'),
                    __('Octobre', 'lodgify-sync'),
                    __('Novembre', 'lodgify-sync'),
                    __('Décembre', 'lodgify-sync'),
                ),
                'days' => array(
                    __('L', 'lodgify-sync'),
                    __('M', 'lodgify-sync'),
                    __('M', 'lodgify-sync'),
                    __('J', 'lodgify-sync'),
                    __('V', 'lodgify-sync'),
                    __('S', 'lodgify-sync'),
                    __('D', 'lodgify-sync'),
                ),
            ));
        }
    }
    
    public function render_booking_form($atts) {
        $atts = shortcode_atts(array(
            'property_id' => '',
            'checkout_url' => '',
            'currency' => '$',
            'max_guests' => 10,
        ), $atts);
        
        // Récupérer l'ID de la propriété depuis le post actuel si non spécifié
        if (empty($atts['property_id'])) {
            global $post;
            $atts['property_id'] = get_post_meta($post->ID, 'lodgify_property_id', true);
            
            // Essayer aussi avec rental_id
            if (empty($atts['property_id'])) {
                $atts['property_id'] = get_post_meta($post->ID, 'rental_id', true);
            }
        }
        
        // Récupérer l'URL de checkout Lodgify
        if (empty($atts['checkout_url'])) {
            global $post;
            $atts['checkout_url'] = get_post_meta($post->ID, 'lodgify_checkout_url', true);
        }
        
        ob_start();
        ?>
        <div class="airbnb-booking-widget" 
             data-property-id="<?php echo esc_attr($atts['property_id']); ?>"
             data-checkout-url="<?php echo esc_attr($atts['checkout_url']); ?>"
             data-currency="<?php echo esc_attr($atts['currency']); ?>"
             data-max-guests="<?php echo esc_attr($atts['max_guests']); ?>">
            
            <!-- État initial: sans dates -->
            <div class="abw-no-dates">
                <h3 class="abw-title"><?php _e('Indiquez vos dates pour afficher les prix', 'lodgify-sync'); ?></h3>
            </div>
            
            <!-- État avec dates -->
            <div class="abw-with-dates" style="display: none;">
                <div class="abw-price-display">
                    <span class="abw-price-original"></span>
                    <span class="abw-price-current"></span>
                    <span class="abw-price-nights"></span>
                </div>
            </div>
            
            <!-- Formulaire de dates -->
            <div class="abw-form">
                <div class="abw-dates-row">
                    <div class="abw-date-field abw-arrival" data-type="arrival">
                        <label><?php _e('ARRIVÉE', 'lodgify-sync'); ?></label>
                        <span class="abw-date-value"><?php _e('Ajouter une date', 'lodgify-sync'); ?></span>
                        <input type="hidden" name="arrival" value="">
                    </div>
                    <div class="abw-date-field abw-departure" data-type="departure">
                        <label><?php _e('DÉPART', 'lodgify-sync'); ?></label>
                        <span class="abw-date-value"><?php _e('Ajouter une date', 'lodgify-sync'); ?></span>
                        <input type="hidden" name="departure" value="">
                    </div>
                </div>
                
                <div class="abw-guests-row">
                    <div class="abw-guests-field">
                        <label><?php _e('VOYAGEURS', 'lodgify-sync'); ?></label>
                        <span class="abw-guests-value">1 <?php _e('voyageur', 'lodgify-sync'); ?></span>
                        <input type="hidden" name="guests" value="1">
                        <svg class="abw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    
                    <!-- Dropdown voyageurs -->
                    <div class="abw-guests-dropdown" style="display: none;">
                        <div class="abw-guests-counter">
                            <span><?php _e('Adultes', 'lodgify-sync'); ?></span>
                            <div class="abw-counter">
                                <button type="button" class="abw-counter-btn abw-minus" data-action="minus">−</button>
                                <span class="abw-counter-value">1</span>
                                <button type="button" class="abw-counter-btn abw-plus" data-action="plus">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bouton principal -->
            <button type="button" class="abw-submit-btn abw-check-availability">
                <?php _e('Vérifier la disponibilité', 'lodgify-sync'); ?>
            </button>
            
            <!-- Message de réassurance -->
            <p class="abw-reassurance" style="display: none;">
                <?php _e('Aucun montant ne vous sera débité pour le moment', 'lodgify-sync'); ?>
            </p>
        </div>
        
        <!-- Popup Calendrier -->
        <div class="abw-calendar-popup" style="display: none;">
            <div class="abw-calendar-overlay"></div>
            <div class="abw-calendar-modal">
                <div class="abw-calendar-header">
                    <div class="abw-calendar-summary">
                        <div class="abw-nights-count"></div>
                        <div class="abw-dates-range"></div>
                    </div>
                    <div class="abw-calendar-inputs">
                        <div class="abw-input-field abw-input-arrival">
                            <label><?php _e('ARRIVÉE', 'lodgify-sync'); ?></label>
                            <span class="abw-input-value"></span>
                            <button type="button" class="abw-clear-date" data-type="arrival">×</button>
                        </div>
                        <div class="abw-input-field abw-input-departure">
                            <label><?php _e('DÉPART', 'lodgify-sync'); ?></label>
                            <span class="abw-input-value"></span>
                            <button type="button" class="abw-clear-date" data-type="departure">×</button>
                        </div>
                    </div>
                </div>
                
                <div class="abw-calendar-body">
                    <div class="abw-calendar-nav">
                        <button type="button" class="abw-nav-prev">‹</button>
                    </div>
                    <div class="abw-calendar-month abw-month-1"></div>
                    <div class="abw-calendar-month abw-month-2"></div>
                    <div class="abw-calendar-nav">
                        <button type="button" class="abw-nav-next">›</button>
                    </div>
                </div>
                
                <div class="abw-calendar-footer">
                    <button type="button" class="abw-keyboard-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <line x1="6" y1="8" x2="6" y2="8"></line>
                            <line x1="10" y1="8" x2="10" y2="8"></line>
                            <line x1="14" y1="8" x2="14" y2="8"></line>
                            <line x1="18" y1="8" x2="18" y2="8"></line>
                            <line x1="6" y1="12" x2="6" y2="12"></line>
                            <line x1="10" y1="12" x2="10" y2="12"></line>
                            <line x1="14" y1="12" x2="14" y2="12"></line>
                            <line x1="18" y1="12" x2="18" y2="12"></line>
                            <line x1="8" y1="16" x2="16" y2="16"></line>
                        </svg>
                    </button>
                    <div class="abw-footer-actions">
                        <button type="button" class="abw-clear-all"><?php _e('Effacer les dates', 'lodgify-sync'); ?></button>
                        <button type="button" class="abw-close-calendar"><?php _e('Fermer', 'lodgify-sync'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialiser
FourU_Moteur_Airbnb_Booking_Widget::get_instance();
