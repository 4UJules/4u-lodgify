<?php
/**
 * Widget Elementor pour afficher le prix par jour
 *
 * @package FourU_Moteur_Availability_Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe du widget de prix Lodgify
 */
class FourU_Moteur_Price_Widget extends \Elementor\Widget_Base {
    
    /**
     * Obtenir le nom du widget
     *
     * @return string Nom du widget
     */
    public function get_name() {
        return 'lodgify_price_widget';
    }
    
    /**
     * Obtenir le titre du widget
     *
     * @return string Titre du widget
     */
    public function get_title() {
        return __('Prix Lodgify', 'lodgify-availability-sync');
    }
    
    /**
     * Obtenir l'icône du widget
     *
     * @return string Nom de l'icône
     */
    public function get_icon() {
        return 'eicon-price-table';
    }
    
    /**
     * Obtenir les catégories du widget
     *
     * @return array Catégories du widget
     */
    public function get_categories() {
        return ['lodgify-availability-sync'];
    }
    
    /**
     * Obtenir les mots-clés du widget
     *
     * @return array Mots-clés du widget
     */
    public function get_keywords() {
        return ['lodgify', 'prix', 'disponibilité', 'tarif', 'price'];
    }
    
    /**
     * Enregistrer les contrôles du widget
     */
    protected function _register_controls() {
        // Section des paramètres de propriété
        $this->start_controls_section(
            'section_property',
            [
                'label' => __('Paramètres de propriété', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        // Utiliser les dates de recherche
        $this->add_control(
            'use_search_dates',
            [
                'label' => __('Utiliser les dates de recherche', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Si activé, le widget utilisera les dates de recherche de l\'URL pour afficher le prix correspondant.', 'lodgify-availability-sync'),
            ]
        );
        
        // Afficher le bouton de réservation
        $this->add_control(
            'show_book_button',
            [
                'label' => __('Afficher le bouton de réservation', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        // Nom du site Lodgify pour la réservation
        $this->add_control(
            'lodgify_website',
            [
                'label' => __('Nom du site Lodgify', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'thehills',
                'placeholder' => 'thehills',
                'condition' => [
                    'show_book_button' => 'yes',
                ],
            ]
        );
        
        // Texte du bouton de réservation
        $this->add_control(
            'button_text',
            [
                'label' => __('Texte du bouton', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Book Now', 'lodgify-availability-sync'),
                'placeholder' => __('Book Now', 'lodgify-availability-sync'),
                'condition' => [
                    'show_book_button' => 'yes',
                ],
            ]
        );
        
        // Texte pour "Prix par jour"
        $this->add_control(
            'price_label',
            [
                'label' => __('Texte pour le prix (sans dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Prix par jour:', 'lodgify-availability-sync'),
                'placeholder' => __('Prix par jour:', 'lodgify-availability-sync'),
            ]
        );
        
        // Texte pour "Prix par nuit" (avec dates)
        $this->add_control(
            'price_with_dates_label',
            [
                'label' => __('Texte pour le prix (avec dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('From', 'lodgify-availability-sync'),
                'placeholder' => __('From', 'lodgify-availability-sync'),
            ]
        );
        
        // Texte pour "/night" après le prix
        $this->add_control(
            'price_suffix',
            [
                'label' => __('Texte après le prix', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('/night', 'lodgify-availability-sync'),
                'placeholder' => __('/night ou /nuit', 'lodgify-availability-sync'),
            ]
        );
        
        // Option pour toujours afficher le suffixe
        $this->add_control(
            'show_suffix_always',
            [
                'label' => __('Toujours afficher le suffixe', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
        
        // Texte pour "Séjour minimum"
        $this->add_control(
            'min_stay_label',
            [
                'label' => __('Texte pour séjour minimum', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Séjour minimum:', 'lodgify-availability-sync'),
                'placeholder' => __('Séjour minimum:', 'lodgify-availability-sync'),
            ]
        );
        
        // Texte pour "nuits" après le séjour minimum
        $this->add_control(
            'nights_singular',
            [
                'label' => __('Texte pour 1 nuit', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('nuit', 'lodgify-availability-sync'),
                'placeholder' => __('nuit', 'lodgify-availability-sync'),
            ]
        );
        
        $this->add_control(
            'nights_plural',
            [
                'label' => __('Texte pour plusieurs nuits', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('nuits', 'lodgify-availability-sync'),
                'placeholder' => __('nuits', 'lodgify-availability-sync'),
            ]
        );
        
        // Mode d'utilisation
        $this->add_control(
            'use_mode',
            [
                'label' => __('Mode d\'utilisation', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Automatique (utiliser la propriété courante)', 'lodgify-availability-sync'),
                    'manual' => __('Manuel (spécifier les IDs)', 'lodgify-availability-sync'),
                ],
            ]
        );
        
        // Sélection du site Lodgify
        $this->add_control(
            'website_id',
            [
                'label' => __('Site Lodgify', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '453125',
                'options' => [
                    '453125' => __('The Hills (USD)', 'lodgify-availability-sync'),
                    '479060' => __('Amazing Stay (EUR)', 'lodgify-availability-sync'),
                ],
                'condition' => [
                    'use_mode' => 'manual',
                ],
            ]
        );
        
        // ID de la propriété
        $this->add_control(
            'property_id',
            [
                'label' => __('ID de la propriété', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Ex: 499024', 'lodgify-availability-sync'),
                'condition' => [
                    'use_mode' => 'manual',
                ],
            ]
        );
        
        // ID du type de chambre
        $this->add_control(
            'room_type_id',
            [
                'label' => __('ID du type de chambre', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Ex: 565344', 'lodgify-availability-sync'),
                'condition' => [
                    'use_mode' => 'manual',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section des paramètres d'affichage
        $this->start_controls_section(
            'section_display',
            [
                'label' => __('Paramètres d\'affichage', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        // Afficher le prix par jour
        $this->add_control(
            'show_price',
            [
                'label' => __('Afficher le prix par jour', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        // Afficher la durée minimale de séjour
        $this->add_control(
            'show_min_stay',
            [
                'label' => __('Afficher la durée minimale de séjour', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'lodgify-availability-sync'),
                'label_off' => __('Non', 'lodgify-availability-sync'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        // Texte avant le prix
        $this->add_control(
            'price_prefix',
            [
                'label' => __('Texte avant le prix', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Prix par jour :', 'lodgify-availability-sync'),
                'condition' => [
                    'show_price' => 'yes',
                ],
            ]
        );
        
        // Texte avant la durée minimale
        $this->add_control(
            'min_stay_prefix',
            [
                'label' => __('Texte avant la durée minimale', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Durée minimale de séjour :', 'lodgify-availability-sync'),
                'condition' => [
                    'show_min_stay' => 'yes',
                ],
            ]
        );
        
        // Suffixe de la durée minimale
        $this->add_control(
            'min_stay_suffix',
            [
                'label' => __('Suffixe de la durée minimale', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('nuits', 'lodgify-availability-sync'),
                'condition' => [
                    'show_min_stay' => 'yes',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section de style du prix
        $this->start_controls_section(
            'section_price_style',
            [
                'label' => __('Style du prix', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        // Typographie du prix
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'label' => __('Typographie du prix', 'lodgify-availability-sync'),
                'selector' => '{{WRAPPER}} .lodgify-price',
            ]
        );
        
        // Couleur du prix
        $this->add_control(
            'price_color',
            [
                'label' => __('Couleur du prix', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4054b2',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-price' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        // Espacement du prix
        $this->add_responsive_control(
            'price_spacing',
            [
                'label' => __('Espacement', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .lodgify-price-container' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Section de style de la durée minimale
        $this->start_controls_section(
            'section_min_stay_style',
            [
                'label' => __('Style de la durée minimale', 'lodgify-availability-sync'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_min_stay' => 'yes',
                ],
            ]
        );
        
        // Typographie de la durée minimale
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'min_stay_typography',
                'label' => __('Typographie de la durée minimale', 'lodgify-availability-sync'),
                'selector' => '{{WRAPPER}} .lodgify-min-stay',
            ]
        );
        
        // Couleur de la durée minimale
        $this->add_control(
            'min_stay_color',
            [
                'label' => __('Couleur de la durée minimale', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#54595f',
                'selectors' => [
                    '{{WRAPPER}} .lodgify-min-stay' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Rendre le widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $use_search_dates = isset($settings['use_search_dates']) && $settings['use_search_dates'] === 'yes';
        $use_mode = isset($settings['use_mode']) ? $settings['use_mode'] : 'auto';
        $show_book_button = isset($settings['show_book_button']) && $settings['show_book_button'] === 'yes';
        $lodgify_website = isset($settings['lodgify_website']) ? $settings['lodgify_website'] : 'thehills';
        $button_text = isset($settings['button_text']) ? $settings['button_text'] : __('Book Now', 'lodgify-availability-sync');
        
        // Variables pour stocker les IDs
        $website_id = '';
        $property_id = '';
        $room_type_id = '';
        
        // Déterminer les IDs en fonction du mode
        if ($use_mode === 'auto') {
            // Mode automatique - utiliser la propriété courante
            $property_id = $this->get_current_property_rental_id();
            
            if (!$property_id) {
                echo '<div class="lodgify-price-widget">';
                echo __('Aucun ID de propriété trouvé pour cette page.', 'lodgify-availability-sync');
                echo '</div>';
                return;
            }
            
            // Déterminer le website ID en fonction de la propriété
            // Vérifier si la propriété appartient au site 479060 (EUR)
            $website_id = $this->get_property_website_id($property_id);
            error_log('Lodgify Price Widget: Website ID déterminé pour la propriété ' . $property_id . ': ' . $website_id);
            
            // Récupérer le type de chambre pour cette propriété
            $room_type_id = $this->get_room_type_id_for_property($property_id);
        } else {
            // Mode manuel - utiliser les IDs spécifiés
            $website_id = $settings['lodgify_website']; // Utiliser lodgify_website au lieu de website_id
            $property_id = $settings['property_id'];
            
            if (empty($property_id)) {
                echo '<div class="lodgify-price-widget">';
                echo __('Veuillez spécifier un ID de propriété.', 'lodgify-availability-sync');
                echo '</div>';
                return;
            }
            
            // Récupérer le type de chambre pour cette propriété
            $room_type_id = $this->get_room_type_id_for_property($property_id);
        }
        
        // Récupérer les dates de recherche si nécessaire
        $search_dates = null;
        if ($use_search_dates) {
            $search_dates = $this->get_search_dates_from_url();
        }
        
        // Récupérer les informations de prix
        $price_info = $this->get_price_info($website_id, $property_id, $room_type_id, $search_dates);
        
        if (!$price_info) {
            echo '<div class="lodgify-price-widget">';
            echo __('Aucune information de prix disponible pour cette propriété.', 'lodgify-availability-sync');
            echo '</div>';
            return;
        }
        
        // Préparer l'URL de réservation Lodgify
        $booking_url = '';
        if ($show_book_button) {
            // Simplification de la logique pour éviter les conflits
            // Déterminer si nous utilisons le compte Amazing Stay (EUR) ou The Hills (USD)
            $use_amazing_stay = false;
            
            // 1. En mode manuel, utiliser le website ID sélectionné dans les paramètres
            if ($use_mode === 'manual' && isset($settings['lodgify_website'])) {
                $use_amazing_stay = ($settings['lodgify_website'] === '479060');
            } 
            // 2. En mode auto, utiliser le website ID déterminé par la méthode get_property_website_id
            else if ($use_mode === 'auto') {
                $use_amazing_stay = ($website_id === '479060');
            }
            
            // Déterminer le nom de domaine et la devise en fonction du compte
            if ($use_amazing_stay) {
                // Compte Amazing Stay
                $lodgify_domain = 'amazing-stay';
                $currency = 'EUR';
            } else {
                // Compte The Hills (par défaut)
                $lodgify_domain = 'thehills';
                $currency = 'USD';
            }
            
            // Formatage de l'URL avec le bon domaine et la bonne devise
            $booking_url = 'https://checkout.lodgify.com/' . esc_attr($lodgify_domain) . '/' . esc_attr($property_id) . '/addons?currency=' . $currency . '&ref=bnbox';
            
            // Ajouter les dates si disponibles
            if ($search_dates && isset($price_info->check_in) && isset($price_info->check_out)) {
                // Format des dates: YYYY-MM-DD
                $arrival = date('Y-m-d', strtotime($price_info->check_in));
                $departure = date('Y-m-d', strtotime($price_info->check_out));
                $booking_url .= '&arrival=' . $arrival . '&departure=' . $departure;
            } else {
                // Si pas de dates spécifiées, utiliser les dates par défaut (aujourd'hui + 3 jours)
                $today = new DateTime();
                $departure = (new DateTime())->modify('+3 days');
                $booking_url .= '&arrival=' . $today->format('Y-m-d') . '&departure=' . $departure->format('Y-m-d');
            }
            
            // Ajouter le nombre d'adultes par défaut
            $booking_url .= '&adults=1';
        }
        
        // Afficher le prix
        echo '<div class="lodgify-price-widget">';
        
        // Récupérer le texte personnalisé pour le prix
        $price_label = $settings['price_label'] ? $settings['price_label'] : __('Prix par jour:', 'lodgify-availability-sync');
        
        // Déterminer le symbole de devise en fonction du website ID
        $currency_symbol = '';
        
        // Débogage: Vérifier la valeur réelle du website_id
        error_log('Website ID: ' . $website_id . ' (Type: ' . gettype($website_id) . ')');
        
        // Conversion explicite en string pour la comparaison
        $website_id_str = (string) $website_id;
        
        if ($website_id_str === '453125') {
            $currency_symbol = '$';
            error_log('Symbole de devise choisi: $ (Dollar)');
        } elseif ($website_id_str === '479060') {
            $currency_symbol = '€'; // symbole Euro
            error_log('Symbole de devise choisi: € (Euro)');
        } else {
            // Fallback au code de devise standard si le website ID n'est pas reconnu
            $currency_symbol = $price_info->currency;
            error_log('Symbole de devise par défaut: ' . $price_info->currency . ' (website_id non reconnu)');
        }
        
        // Simplification de la logique pour éviter les conflits
        // Déterminer si nous utilisons le compte Amazing Stay (EUR) ou The Hills (USD)
        $use_amazing_stay = false;
        
        // 1. En mode manuel, utiliser le website ID sélectionné dans les paramètres
        if ($use_mode === 'manual' && isset($settings['lodgify_website'])) {
            $use_amazing_stay = ($settings['lodgify_website'] === '479060');
        } 
        // 2. En mode auto, utiliser le website ID déterminé par la méthode get_property_website_id
        else if ($use_mode === 'auto') {
            $use_amazing_stay = ($website_id === '479060');
        }
        
        // Formater le prix avec le symbole de devise approprié
        if ($use_amazing_stay) {
            // Format pour EUR: 155€
            $formatted_price = esc_html(number_format($price_info->price_per_day, 2, ',', ' ')) . '€';
        } else {
            // Format pour USD: $155 (par défaut)
            $formatted_price = '$' . esc_html(number_format($price_info->price_per_day, 2, '.', ','));
        }
        
        // Afficher le prix par jour avec format différent selon si des dates sont sélectionnées ou non
        echo '<div class="lodgify-price-container">';
        if ($search_dates) {
            // Si des dates sont sélectionnées, afficher "From XXX/night"
            $price_with_dates_label = !empty($settings['price_with_dates_label']) ? $settings['price_with_dates_label'] : __('From', 'lodgify-availability-sync');
            $price_suffix = !empty($settings['price_suffix']) ? $settings['price_suffix'] : __('/night', 'lodgify-availability-sync');
            
            echo '<span class="lodgify-price-prefix">' . esc_html($price_with_dates_label) . '</span> ';
            echo '<span class="lodgify-price">' . $formatted_price . '</span>';
            echo '<span class="lodgify-price-suffix">' . esc_html($price_suffix) . '</span>';
        } else {
            // Si aucune date n'est sélectionnée, utiliser le format standard
            echo '<span class="lodgify-price-prefix">' . esc_html($price_label) . '</span> ';
            echo '<span class="lodgify-price">' . $formatted_price . '</span>';
            
            // Afficher le suffixe du prix même sans dates si l'option est activée
            if (!empty($settings['show_suffix_always']) && $settings['show_suffix_always'] === 'yes') {
                $price_suffix = !empty($settings['price_suffix']) ? $settings['price_suffix'] : __('/night', 'lodgify-availability-sync');
                echo '<span class="lodgify-price-suffix">' . esc_html($price_suffix) . '</span>';
            }
        }
        echo '</div>';
        
        // Toujours afficher le séjour minimum (avec ou sans dates)
        if (isset($price_info->min_stay) && $price_info->min_stay > 1) {
            // Récupérer les textes personnalisés
            $min_stay_label = !empty($settings['min_stay_label']) ? $settings['min_stay_label'] : __('Séjour minimum:', 'lodgify-availability-sync');
            $night_singular = !empty($settings['nights_singular']) ? $settings['nights_singular'] : __('nuit', 'lodgify-availability-sync');
            $night_plural = !empty($settings['nights_plural']) ? $settings['nights_plural'] : __('nuits', 'lodgify-availability-sync');
            
            // Formater le texte avec le singulier ou pluriel approprié
            $night_text = $price_info->min_stay > 1 ? $night_plural : $night_singular;
            
            echo '<div class="lodgify-min-stay-container">';
            echo '<span class="lodgify-min-stay-prefix">' . esc_html($min_stay_label) . '</span> ';
            echo '<span class="lodgify-min-stay">' . esc_html($price_info->min_stay) . ' ' . esc_html($night_text) . '</span>';
            echo '</div>';
            
            // En plus, afficher un avertissement si les dates sélectionnées ne respectent pas le séjour minimum
            if ($search_dates && isset($price_info->nights) && $price_info->nights < $price_info->min_stay) {
                echo '<div class="lodgify-min-stay-warning">';
                echo '<span class="lodgify-min-stay-warning-text">' . sprintf(__('Attention: Séjour minimum de %s %s requis!', 'lodgify-availability-sync'), $price_info->min_stay, esc_html($night_plural)) . '</span>';
                echo '</div>';
            }
        }
        
        // Afficher le bouton de réservation uniquement si des dates sont sélectionnées
        if ($search_dates && $show_book_button && !empty($booking_url)) {
            echo '<div class="lodgify-book-button-container">';
            echo '<a href="' . esc_url($booking_url) . '" target="_blank" class="lodgify-book-button">' . esc_html($button_text) . '</a>';
            echo '</div>';
        }
        
        echo '</div>'; // .lodgify-price-widget
    }
    
    /**
     * Récupérer les informations de prix directement depuis l'API Lodgify
     *
     * @param string $website_id ID du site Lodgify
     * @param string $property_id ID de la propriété
     * @param string $room_type_id ID du type de chambre
     * @param array $search_dates Dates de recherche (check-in et check-out)
     * @return object|false Informations de prix ou false si non trouvé
     */
    private function get_price_info($website_id, $property_id, $room_type_id, $search_dates = null) {
        global $wpdb;
        
        // Déterminer les dates à utiliser
        $check_in = date('Y-m-d');
        $check_out = date('Y-m-d', strtotime('+1 day')); // Au moins une nuit par défaut
        
        if ($search_dates && isset($search_dates['check_in']) && isset($search_dates['check_out'])) {
            $check_in = $search_dates['check_in'];
            $check_out = $search_dates['check_out'];
        }
        
        // Créer une clé de cache unique pour cette requête
        $cache_key = 'lodgify_price_' . $website_id . '_' . $property_id . '_' . $room_type_id . '_' . $check_in . '_' . $check_out;
        
        // Vérifier si les données sont en cache
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            error_log('Lodgify Price Widget: Utilisation des données en cache pour ' . $cache_key);
            return $cached_data;
        }
        
        // Convertir les dates au format de l'API Lodgify (YYYY.M.D)
        $api_check_in = str_replace('-', '.', $check_in);
        $api_check_out = str_replace('-', '.', $check_out);
        
        // Journaliser les dates pour débogage
        error_log('Lodgify Price Widget: Recherche de prix pour les dates ' . $check_in . ' à ' . $check_out);
        error_log('Lodgify Price Widget: Propriété ID: ' . $property_id . ', Room Type ID: ' . $room_type_id);
        
        // Récupérer la clé API Lodgify en fonction du website ID
        $api_key = $this->get_api_key_for_website($website_id);
        
        error_log('Lodgify Price Widget: Utilisation de la clé API pour le website ID ' . $website_id);
        
        // Construire l'URL de l'API Lodgify pour récupérer les prix
        $api_url = add_query_arg(
            array(
                'RoomTypeId' => $room_type_id,
                'HouseId' => $property_id,
                'StartDate' => $api_check_in,
                'EndDate' => $api_check_out
            ),
            'https://api.lodgify.com/v2/rates/calendar'
        );
        
        error_log('Lodgify Price Widget: Appel API - ' . $api_url);
        
        // Appeler l'API Lodgify
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        // Vérifier si l'appel API a réussi
        if (is_wp_error($response)) {
            error_log('Lodgify Price Widget: Erreur API - ' . $response->get_error_message());
            
            // En cas d'erreur, utiliser les prix de la base de données locale comme fallback
            return $this->get_price_info_from_db($website_id, $property_id, $room_type_id, $search_dates);
        }
        
        // Décoder la réponse JSON
        $api_data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Vérifier si la réponse contient des données valides
        if (!isset($api_data['calendar_items']) || empty($api_data['calendar_items'])) {
            error_log('Lodgify Price Widget: Aucune donnée de prix dans la réponse API');
            
            // En cas d'absence de données, utiliser les prix de la base de données locale comme fallback
            return $this->get_price_info_from_db($website_id, $property_id, $room_type_id, $search_dates);
        }
        
        // Créer un objet pour stocker les informations de prix
        $price_info = new stdClass();
        $price_info->check_in = $check_in;
        $price_info->check_out = $check_out;
        
        // Calculer le nombre de nuits
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $interval = $check_in_date->diff($check_out_date);
        $nights = $interval->days;
        
        // S'assurer que nous avons au moins une nuit
        if ($nights < 1) {
            $nights = 1;
        }
        
        $price_info->nights = $nights;
        
        // Récupérer le prix par jour du premier jour (ou utiliser une moyenne si nécessaire)
        $total_price = 0;
        $min_stay = 0;
        $currency = 'USD';
        
        // Parcourir tous les jours pour calculer le prix total
        foreach ($api_data['calendar_items'] as $day) {
            if (isset($day['prices']) && !empty($day['prices'])) {
                $day_price = $day['prices'][0]['price_per_day'];
                $total_price += $day_price;
                
                // Récupérer le séjour minimum du premier jour
                if ($min_stay === 0 && isset($day['prices'][0]['min_stay'])) {
                    $min_stay = $day['prices'][0]['min_stay'];
                }
            }
        }
        
        // Calculer le prix moyen par jour
        $count_days = count($api_data['calendar_items']);
        if ($count_days > 0) {
            $price_info->price_per_day = $total_price / $count_days;
        } else {
            // Valeur par défaut si aucun jour n'est disponible
            $price_info->price_per_day = 0;
        }
        
        // Définir le séjour minimum
        $price_info->min_stay = $min_stay;
        
        // Définir la devise
        if (isset($api_data['rate_settings']) && isset($api_data['rate_settings']['currency_code'])) {
            $price_info->currency = $api_data['rate_settings']['currency_code'];
        } else {
            $price_info->currency = $currency;
        }
        
        // Calculer le prix total pour le séjour
        $price_info->total_price = $price_info->price_per_day * $nights;
        
        error_log('Lodgify Price Widget: Prix API - ' . $price_info->price_per_day . ' par jour x ' . $nights . ' nuits = ' . $price_info->total_price);
        
        // Enregistrer les données dans le cache pour 12 heures (43200 secondes)
        // Utiliser la même clé de cache que celle utilisée pour la vérification
        $cache_key = 'lodgify_price_' . $website_id . '_' . $property_id . '_' . $room_type_id . '_' . $check_in . '_' . $check_out;
        set_transient($cache_key, $price_info, 43200);
        error_log('Lodgify Price Widget: Données enregistrées dans le cache avec la clé ' . $cache_key);
        
        return $price_info;
    }
    
    /**
     * Récupérer les informations de prix depuis la base de données locale (fallback)
     *
     * @param string $website_id ID du site Lodgify
     * @param string $property_id ID de la propriété
     * @param string $room_type_id ID du type de chambre
     * @param array $search_dates Dates de recherche (check-in et check-out)
     * @return object|false Informations de prix ou false si non trouvé
     */
    private function get_price_info_from_db($website_id, $property_id, $room_type_id, $search_dates = null) {
        global $wpdb;
        
        // Récupérer la table des disponibilités et des prix
        $table_availabilities = $wpdb->prefix . 'lodgify_availabilities';
        $table_prices = $wpdb->prefix . 'lodgify_prices';
        
        // Déterminer les dates à utiliser
        $check_in = date('Y-m-d');
        $check_out = date('Y-m-d', strtotime('+1 day')); // Au moins une nuit par défaut
        
        if ($search_dates && isset($search_dates['check_in']) && isset($search_dates['check_out'])) {
            $check_in = $search_dates['check_in'];
            $check_out = $search_dates['check_out'];
        }
        
        // Créer une clé de cache unique pour cette requête de base de données
        $cache_key = 'lodgify_db_price_' . $website_id . '_' . $property_id . '_' . $room_type_id . '_' . $check_in . '_' . $check_out;
        
        // Vérifier si les données sont en cache
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            error_log('Lodgify Price Widget: Utilisation des données DB en cache pour ' . $cache_key);
            return $cached_data;
        }
        
        error_log('Lodgify Price Widget: Utilisation du fallback DB pour les prix');
        
        // Récupérer le prix le plus récent pour cette propriété
        $price_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.* FROM $table_prices p
                JOIN $table_availabilities a ON p.availability_id = a.id
                WHERE a.property_id = %s
                ORDER BY a.last_updated DESC
                LIMIT 1",
                $property_id
            )
        );
        
        // Si aucun prix n'est trouvé, retourner false
        if (!$price_info) {
            error_log('Lodgify Price Widget: Aucun prix trouvé pour la propriété ' . $property_id);
            return false;
        }
        
        // Ajouter les dates de séjour au prix pour l'affichage
        $price_info->check_in = $check_in;
        $price_info->check_out = $check_out;
        
        // Calculer le nombre de nuits
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $interval = $check_in_date->diff($check_out_date);
        $nights = $interval->days;
        
        // S'assurer que nous avons au moins une nuit
        if ($nights < 1) {
            $nights = 1;
        }
        
        $price_info->nights = $nights;
        
        // Calculer le prix total pour le séjour
        if (isset($price_info->price_per_day)) {
            $price_info->total_price = $price_info->price_per_day * $nights;
            error_log('Lodgify Price Widget: Prix DB - ' . $price_info->price_per_day . ' par jour x ' . $nights . ' nuits = ' . $price_info->total_price);
        } else {
            error_log('Lodgify Price Widget: Impossible de calculer le prix total, price_per_day non défini');
        }
        
        // Enregistrer les données dans le cache pour 24 heures (86400 secondes)
        // Utiliser la même clé de cache que celle utilisée pour la vérification
        $cache_key = 'lodgify_db_price_' . $website_id . '_' . $property_id . '_' . $room_type_id . '_' . $check_in . '_' . $check_out;
        set_transient($cache_key, $price_info, 86400);
        error_log('Lodgify Price Widget: Données DB enregistrées dans le cache avec la clé ' . $cache_key);
        
        return $price_info;
    }
    
    /**
     * Récupérer l'ID de location (rental-id) de la propriété courante
     *
     * @return string|false ID de la propriété ou false si non trouvé
     */
    private function get_current_property_rental_id() {
        // Récupérer l'ID de la page/post courant
        $post_id = get_the_ID();
        
        if (!$post_id) {
            return false;
        }
        
        // Récupérer le champ meta 'rental-id'
        $rental_id = get_post_meta($post_id, 'rental-id', true);
        
        return !empty($rental_id) ? $rental_id : false;
    }
    
    /**
     * Récupérer l'ID du type de chambre pour une propriété
     *
     * @param string $property_id ID de la propriété
     * @return string ID du type de chambre ou valeur par défaut
     */
    private function get_room_type_id_for_property($property_id) {
        global $wpdb;
        
        // Récupérer la table des disponibilités
        $table_availabilities = $wpdb->prefix . 'lodgify_availabilities';
        
        // Essayer de trouver un type de chambre existant pour cette propriété
        $room_type_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT room_type_id FROM $table_availabilities 
                WHERE property_id = %s 
                LIMIT 1",
                $property_id
            )
        );
        
        // Si nous trouvons un type de chambre, l'utiliser, sinon utiliser une valeur par défaut
        return !empty($room_type_id) ? $room_type_id : '565344';
    }
    
    /**
     * Récupérer la clé API Lodgify en fonction du website ID
     *
     * @param string $website_id ID du site Lodgify
     * @return string Clé API pour le site spécifié
     */
    private function get_api_key_for_website($website_id) {
        if ($website_id === '479060') {
            // Clé pour le compte amazing-stay (EUR)
            return 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk';
        } else {
            // Clé pour le compte thehills (USD) - par défaut
            return 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H';
        }
    }

    /**
     * Déterminer le website ID en fonction de la propriété
     *
     * @param string $property_id ID de la propriété
     * @return string Website ID ('453125' pour USD ou '479060' pour EUR)
     */
    private function get_property_website_id($property_id) {
        // Récupérer le website_id depuis la BDD
        global $wpdb;
        $table = $wpdb->prefix . 'lodgify_availabilities';
        $website_id = $wpdb->get_var($wpdb->prepare(
            "SELECT website_id FROM $table WHERE property_id = %s LIMIT 1",
            $property_id
        ));
        
        if (!empty($website_id)) {
            return $website_id;
        }
        
        // Par défaut, retourner le website ID pour USD (The Hills)
        return '453125';
    }
    
    /**
     * Récupérer les dates de recherche depuis l'URL
     *
     * @return array|null Dates de recherche (check_in et check_out) ou null si non trouvées
     */
    private function get_search_dates_from_url() {
        // Format attendu : checkin_checkout!date:2025.7.16-2025.7.26
        if (isset($_GET['meta']) && !empty($_GET['meta'])) {
            $meta = $_GET['meta'];
            error_log('Lodgify Price Widget: Paramètre meta trouvé : ' . $meta);
            
            // Vérifier si le paramètre meta contient des dates
            if (strpos($meta, 'checkin_checkout!date:') !== false) {
                // Extraire la partie date
                $date_part = explode('checkin_checkout!date:', $meta)[1];
                error_log('Lodgify Price Widget: Partie date extraite : ' . $date_part);
                
                // Séparer les dates
                $dates = explode('-', $date_part);
                
                if (count($dates) === 2) {
                    // Convertir le format de date avec des points en format standard YYYY-MM-DD
                    $date_parts_in = explode('.', $dates[0]);
                    $date_parts_out = explode('.', $dates[1]);
                    
                    if (count($date_parts_in) === 3 && count($date_parts_out) === 3) {
                        // Format correct: année.mois.jour
                        $check_in = $date_parts_in[0] . '-' . 
                                   str_pad($date_parts_in[1], 2, '0', STR_PAD_LEFT) . '-' . 
                                   str_pad($date_parts_in[2], 2, '0', STR_PAD_LEFT);
                        
                        $check_out = $date_parts_out[0] . '-' . 
                                    str_pad($date_parts_out[1], 2, '0', STR_PAD_LEFT) . '-' . 
                                    str_pad($date_parts_out[2], 2, '0', STR_PAD_LEFT);
                        
                        error_log('Lodgify Price Widget: Dates formatées - check_in: ' . $check_in . ', check_out: ' . $check_out);
                        
                        // Valider les dates
                        $check_in_time = strtotime($check_in);
                        $check_out_time = strtotime($check_out);
                        
                        if ($check_in_time && $check_out_time) {
                            // Vérifier que les dates sont dans le bon ordre
                            if ($check_in_time < $check_out_time) {
                                return [
                                    'check_in' => $check_in,
                                    'check_out' => $check_out
                                ];
                            } else {
                                error_log('Lodgify Price Widget: Dates invalides - check-in après check-out');
                            }
                        } else {
                            error_log('Lodgify Price Widget: Dates invalides - impossible de convertir en timestamp');
                        }
                    } else {
                        error_log('Lodgify Price Widget: Format de date invalide - parties manquantes');
                    }
                } else {
                    error_log('Lodgify Price Widget: Format de date invalide - séparateur manquant');
                }
            }
        }
        
        return null;
    }
}
