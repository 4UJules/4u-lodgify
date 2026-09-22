<?php
/**
 * Widget Elementor pour le bouton de réservation dynamique
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget bouton de réservation Lodgify
 */
class FourU_Moteur_Booking_Button_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'lodgify_booking_button';
    }
    
    public function get_title() {
        return __('Bouton Réservation Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_icon() {
        return 'eicon-button';
    }
    
    public function get_categories() {
        return ['lodgify-availability-sync'];
    }
    
    public function get_keywords() {
        return ['lodgify', 'booking', 'reservation', 'button', 'checkout'];
    }
    
    protected function register_controls() {
        // Section Contenu
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Contenu', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'button_text',
            [
                'label' => __('Texte du bouton', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Book Now', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'button_text_no_dates',
            [
                'label' => __('Texte sans dates', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Check Availability', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'show_price_in_button',
            [
                'label' => __('Afficher le prix dans le bouton', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => '',
            ]
        );
        
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
            'open_in_new_tab',
            [
                'label' => __('Ouvrir dans un nouvel onglet', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_responsive_control(
            'align',
            [
                'label' => __('Alignement', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Gauche', 'lodgify-availability-sync'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Centre', 'lodgify-availability-sync'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Droite', 'lodgify-availability-sync'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section Style du bouton
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style du bouton', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .lodgify-booking-btn',
            ]
        );
        
        $this->start_controls_tabs('button_tabs');
        
        // Normal
        $this->start_controls_tab(
            'button_normal',
            ['label' => __('Normal', 'lodgify-availability-sync')]
        );
        
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Couleur du texte', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_bg_color',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4054b2',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        // Hover
        $this->start_controls_tab(
            'button_hover',
            ['label' => __('Survol', 'lodgify-availability-sync')]
        );
        
        $this->add_control(
            'button_text_color_hover',
            [
                'label' => __('Couleur du texte', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_bg_color_hover',
            [
                'label' => __('Couleur de fond', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2d3a8c',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        $this->end_controls_tabs();
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'separator' => 'before',
                'default' => [
                    'top' => 15,
                    'right' => 30,
                    'bottom' => 15,
                    'left' => 30,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 5,
                    'right' => 5,
                    'bottom' => 5,
                    'left' => 5,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .lodgify-booking-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .lodgify-booking-btn',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Récupérer l'ID de la propriété
        $post_id = get_the_ID();
        $property_id = get_post_meta($post_id, 'rental-id', true);
        
        if (!$property_id) {
            echo '<div class="lodgify-booking-btn-wrapper">';
            echo '<span class="lodgify-booking-btn lodgify-btn-disabled">' . esc_html($settings['button_text_no_dates']) . '</span>';
            echo '</div>';
            return;
        }
        
        // Récupérer le website_id depuis la BDD
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        $website_id = $wpdb->get_var($wpdb->prepare(
            "SELECT website_id FROM $table WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        if (!$website_id) {
            $website_id = '453125'; // Défaut The Hills
        }
        
        // Récupérer les dates depuis l'URL
        $search_dates = $this->get_search_dates_from_url();
        $has_dates = !empty($search_dates);
        
        // Construire l'URL
        $domain = ($website_id === '479060') ? 'amazing-stay' : 'thehills';
        $currency = ($website_id === '479060') ? 'EUR' : 'USD';
        
        $url = 'https://checkout.lodgify.com/' . $domain . '/' . $property_id . '/addons';
        $url .= '?currency=' . $currency . '&ref=bnbox';
        
        if ($has_dates) {
            $url .= '&arrival=' . $search_dates['check_in'];
            $url .= '&departure=' . $search_dates['check_out'];
        } else {
            $url .= '&arrival=' . date('Y-m-d');
            $url .= '&departure=' . date('Y-m-d', strtotime('+3 days'));
        }
        
        $url .= '&adults=' . intval($settings['default_adults']);
        
        // Texte du bouton
        $button_text = $has_dates ? $settings['button_text'] : $settings['button_text_no_dates'];
        
        // Ajouter le prix si demandé
        if ($has_dates && $settings['show_price_in_button'] === 'yes') {
            $price_info = $this->get_price_info($property_id, $search_dates);
            if ($price_info && isset($price_info['total_price'])) {
                $formatted_price = ($website_id === '479060') 
                    ? number_format($price_info['total_price'], 2, ',', ' ') . '€'
                    : '$' . number_format($price_info['total_price'], 2, '.', ',');
                $button_text .= ' - ' . $formatted_price;
            }
        }
        
        $target = ($settings['open_in_new_tab'] === 'yes') ? '_blank' : '_self';
        
        echo '<div class="lodgify-booking-btn-wrapper">';
        echo '<a href="' . esc_url($url) . '" target="' . $target . '" class="lodgify-booking-btn" data-property-id="' . esc_attr($property_id) . '">';
        echo esc_html($button_text);
        echo '</a>';
        echo '</div>';
    }
    
    private function get_search_dates_from_url() {
        if (isset($_GET['meta']) && !empty($_GET['meta'])) {
            $meta = $_GET['meta'];
            
            if (strpos($meta, 'checkin_checkout!date:') !== false) {
                $date_part = explode('checkin_checkout!date:', $meta)[1];
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
    
    private function get_price_info($property_id, $search_dates) {
        // Cache court de 5 minutes pour éviter les timeouts
        $cache_key = 'lodgify_btn_' . $property_id . '_' . $search_dates['check_in'] . '_' . $search_dates['check_out'];
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        
        // Récupérer website_id et room_type_id depuis la BDD
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT website_id, room_type_id FROM $table WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        $website_id = $row ? $row->website_id : '453125';
        $room_type_id = $row ? $row->room_type_id : null;
        
        $api_key = ($website_id === '479060') 
            ? 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk'
            : 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H';
        
        if (!$room_type_id) return null;
        
        $api_url = add_query_arg([
            'RoomTypeId' => $room_type_id,
            'HouseId' => $property_id,
            'StartDate' => str_replace('-', '.', $search_dates['check_in']),
            'EndDate' => str_replace('-', '.', $search_dates['check_out'])
        ], 'https://api.lodgify.com/v2/rates/calendar');
        
        $response = wp_remote_get($api_url, [
            'headers' => ['X-ApiKey' => $api_key, 'accept' => 'application/json'],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) return null;
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['calendar_items'])) return null;
        
        $total = 0;
        foreach ($data['calendar_items'] as $day) {
            if (isset($day['prices'][0]['price_per_day'])) {
                $total += $day['prices'][0]['price_per_day'];
            }
        }
        
        $result = ['total_price' => $total, 'website_id' => $website_id];
        
        // Cache 5 minutes
        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        
        return $result;
    }
}
