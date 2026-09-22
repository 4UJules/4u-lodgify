<?php
/**
 * Widget Elementor pour afficher le total du séjour avec Ajax
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget total prix Lodgify avec mise à jour Ajax
 */
class FourU_Moteur_Total_Price_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'lodgify_total_price';
    }
    
    public function get_title() {
        return __('Total Séjour Lodgify (Ajax)', 'lodgify-availability-sync');
    }
    
    public function get_icon() {
        return 'eicon-price-list';
    }
    
    public function get_categories() {
        return ['lodgify-availability-sync'];
    }
    
    public function get_keywords() {
        return ['lodgify', 'total', 'prix', 'séjour', 'ajax', 'taxes'];
    }
    
    public function get_script_depends() {
        return ['lodgify-ajax-price'];
    }
    
    protected function register_controls() {
        // Section Contenu
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Paramètres', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'api_notice',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background:#e8f4fc;padding:10px;border-radius:4px;margin-bottom:10px;">
                    <strong>📊 Données API Lodgify</strong><br>
                    Les taxes et frais de ménage sont récupérés automatiquement depuis l\'API Lodgify.
                </div>',
            ]
        );
        
        $this->add_control(
            'show_breakdown',
            [
                'label' => __('Afficher le détail', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Affiche: nuits, frais ménage, taxes', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'label_nights',
            [
                'label' => __('Label nuits', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'nights',
            ]
        );
        
        $this->add_control(
            'label_taxes',
            [
                'label' => __('Label taxes', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Taxes',
            ]
        );
        
        $this->add_control(
            'label_cleaning',
            [
                'label' => __('Label ménage', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Cleaning fee',
            ]
        );
        
        $this->add_control(
            'label_total',
            [
                'label' => __('Label total', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Total',
            ]
        );
        
        $this->add_control(
            'no_dates_text',
            [
                'label' => __('Texte sans dates', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Select dates to see the price', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'loading_text',
            [
                'label' => __('Texte de chargement', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Calculating...', 'lodgify-availability-sync'),
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'container_bg',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f8f9fa',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-total-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .lodgify-total-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 30]],
                'default' => ['unit' => 'px', 'size' => 8],
                'selectors' => [
                    '{{WRAPPER}} .lodgify-total-container' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'items_typography',
                'label' => __('Typographie items', 'lodgify-availability-sync'),
                'selector' => '{{WRAPPER}} .lodgify-breakdown-item',
            ]
        );
        
        $this->add_control(
            'items_color',
            [
                'label' => __('Couleur des items', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-breakdown-item' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'total_typography',
                'label' => __('Typographie total', 'lodgify-availability-sync'),
                'selector' => '{{WRAPPER}} .lodgify-total-line',
            ]
        );
        
        $this->add_control(
            'total_color',
            [
                'label' => __('Couleur du total', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4054b2',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-total-line' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Récupérer l'ID du post courant (compatible avec les boucles JetEngine)
        $post_id = get_the_ID();
        
        // Si on est dans une boucle JetEngine, utiliser le post courant de la boucle
        if (class_exists('Jet_Engine') && isset($GLOBALS['post'])) {
            $post_id = $GLOBALS['post']->ID;
        }
        
        // Fallback: utiliser get_queried_object_id() pour les templates
        if (!$post_id) {
            $post_id = get_queried_object_id();
        }
        
        $property_id = get_post_meta($post_id, 'rental-id', true);
        
        // Debug: afficher les infos si WP_DEBUG est activé
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<!-- Total Price Widget Debug: post_id=' . $post_id . ', property_id=' . $property_id . ' -->';
        }
        
        // Attributs data pour JavaScript (données API)
        $data_attrs = [
            'property-id' => $property_id,
            'show-breakdown' => $settings['show_breakdown'] === 'yes' ? '1' : '0',
            'label-nights' => $settings['label_nights'],
            'label-taxes' => $settings['label_taxes'],
            'label-cleaning' => $settings['label_cleaning'],
            'label-total' => $settings['label_total'],
            'loading-text' => $settings['loading_text'],
            'no-dates-text' => $settings['no_dates_text'],
        ];
        
        $data_string = '';
        foreach ($data_attrs as $key => $value) {
            $data_string .= ' data-' . $key . '="' . esc_attr($value) . '"';
        }
        
        echo '<div class="lodgify-total-container lodgify-ajax-total"' . $data_string . '>';
        
        // Affichage initial
        $search_dates = $this->get_search_dates_from_url();
        
        // Debug: afficher les dates parsées
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<!-- Dates parsed: ' . ($search_dates ? json_encode($search_dates) : 'null') . ' -->';
            echo '<!-- URL meta: ' . (isset($_GET['meta']) ? esc_html($_GET['meta']) : 'not set') . ' -->';
        }
        
        if (!$property_id) {
            echo '<div class="lodgify-no-dates">' . esc_html($settings['no_dates_text']) . '</div>';
        } elseif (!$search_dates) {
            echo '<div class="lodgify-no-dates">' . esc_html($settings['no_dates_text']) . '</div>';
        } else {
            // Afficher le prix initial avec données API
            $this->render_price_breakdown($property_id, $search_dates, $settings);
        }
        
        echo '</div>';
    }
    
    private function render_price_breakdown($property_id, $search_dates, $settings) {
        $price_info = $this->get_price_info($property_id, $search_dates);
        
        if (!$price_info) {
            echo '<div class="lodgify-error">Price not available</div>';
            return;
        }
        
        // Données depuis l'API Lodgify
        $nights = $price_info['nights'];
        $nights_total = $price_info['nights_total'];
        $cleaning_fee = $price_info['cleaning_fee'];
        $cleaning_name = $price_info['cleaning_name'] ?: $settings['label_cleaning'];
        $tax_percentage = $price_info['tax_percentage'];
        $tax_name = $price_info['tax_name'] ?: $settings['label_taxes'];
        $tax_amount = $price_info['tax_amount'];
        $insurance_fee = $price_info['insurance_fee'];
        $insurance_name = $price_info['insurance_name'] ?: 'Insurance';
        $total_price = $price_info['total_price'];
        $currency = $price_info['currency'];
        
        // Fonction pour formater
        $format = function($amount) use ($currency) {
            if ($currency === 'EUR') {
                return number_format($amount, 2, ',', ' ') . '€';
            }
            return '$' . number_format($amount, 2, '.', ',');
        };
        
        // Données extra guests
        $extra_guests = isset($price_info['extra_guests']) ? $price_info['extra_guests'] : 0;
        $extra_guests_total = isset($price_info['extra_guests_total']) ? $price_info['extra_guests_total'] : 0;
        $extra_guest_fee = isset($price_info['extra_guest_fee']) ? $price_info['extra_guest_fee'] : 0;
        
        if ($settings['show_breakdown'] === 'yes') {
            echo '<div class="lodgify-breakdown">';
            
            // Ligne des nuits
            echo '<div class="lodgify-breakdown-item">';
            echo '<span class="lodgify-label">' . $nights . ' ' . esc_html($settings['label_nights']) . '</span>';
            echo '<span class="lodgify-value">' . $format($nights_total) . '</span>';
            echo '</div>';
            
            // Frais de guests supplémentaires
            if ($extra_guests > 0 && $extra_guests_total > 0) {
                echo '<div class="lodgify-breakdown-item">';
                echo '<span class="lodgify-label">Extra guests (' . $extra_guests . ' × ' . $format($extra_guest_fee) . '/night)</span>';
                echo '<span class="lodgify-value">' . $format($extra_guests_total) . '</span>';
                echo '</div>';
            }
            
            // Frais de ménage (depuis API)
            if ($cleaning_fee > 0) {
                echo '<div class="lodgify-breakdown-item">';
                echo '<span class="lodgify-label">' . esc_html($cleaning_name) . '</span>';
                echo '<span class="lodgify-value">' . $format($cleaning_fee) . '</span>';
                echo '</div>';
            }
            
            // Taxes (depuis API)
            if ($tax_amount > 0) {
                $tax_label = esc_html($tax_name);
                if ($tax_percentage > 0) {
                    $tax_label .= ' (' . $tax_percentage . '%)';
                }
                echo '<div class="lodgify-breakdown-item">';
                echo '<span class="lodgify-label">' . $tax_label . '</span>';
                echo '<span class="lodgify-value">' . $format($tax_amount) . '</span>';
                echo '</div>';
            }
            
            // Insurance fee
            if ($insurance_fee > 0) {
                echo '<div class="lodgify-breakdown-item">';
                echo '<span class="lodgify-label">' . esc_html($insurance_name) . '</span>';
                echo '<span class="lodgify-value">' . $format($insurance_fee) . '</span>';
                echo '</div>';
            }
            
            echo '</div>';
            
            // Ligne total
            echo '<div class="lodgify-total-line">';
            echo '<span class="lodgify-label">' . esc_html($settings['label_total']) . '</span>';
            echo '<span class="lodgify-value">' . $format($total_price) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="lodgify-total-simple">';
            echo '<span class="lodgify-label">' . esc_html($settings['label_total']) . ':</span> ';
            echo '<span class="lodgify-value">' . $format($total_price) . '</span>';
            echo '</div>';
        }
    }
    
    private function get_search_dates_from_url() {
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
                        
                        if (strtotime($check_in) && strtotime($check_out) && strtotime($check_in) < strtotime($check_out)) {
                            return ['check_in' => $check_in, 'check_out' => $check_out];
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
    private function get_guests_from_url() {
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
     * Récupérer les prix depuis la BDD locale (lodgify_daily_prices)
     * PAS D'APPEL API - données pré-synchronisées
     */
    private function get_price_info($property_id, $search_dates) {
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
            'nights' => $nights,
            'nights_total' => round($nights_total, 2),
            'price_per_day' => round($avg_price, 2),
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
            'min_stay' => $max_min_stay,
            'currency' => $currency,
            'website_id' => $website_id,
            'guests' => $guests
        ];
    }
}
