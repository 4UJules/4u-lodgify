<?php
/**
 * Dynamic Tag pour afficher le nombre de nuits
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour le nombre de nuits
 */
class FourU_Moteur_Nights_Count_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-nights-count';
    }
    
    public function get_title() {
        return __('Nombre de Nuits', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['text', 'number'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'format',
            [
                'label' => __('Format', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'number_text',
                'options' => [
                    'number_only' => __('Nombre seul (ex: 5)', 'lodgify-availability-sync'),
                    'number_text' => __('Avec texte (ex: 5 nuits)', 'lodgify-availability-sync'),
                    'full_text' => __('Texte complet (ex: pour 5 nuits)', 'lodgify-availability-sync'),
                ],
            ]
        );
        
        $this->add_control(
            'singular_text',
            [
                'label' => __('Texte singulier', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'nuit',
                'condition' => ['format!' => 'number_only'],
            ]
        );
        
        $this->add_control(
            'plural_text',
            [
                'label' => __('Texte pluriel', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'nuits',
                'condition' => ['format!' => 'number_only'],
            ]
        );
        
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Préfixe', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'pour',
                'condition' => ['format' => 'full_text'],
            ]
        );
        
        $this->add_control(
            'fallback',
            [
                'label' => __('Valeur par défaut (sans dates)', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        
        $search_dates = $this->get_search_dates_from_url();
        
        // Sans dates, afficher le fallback
        if (!$search_dates) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        // Calculer le nombre de nuits
        $check_in = strtotime($search_dates['check_in']);
        $check_out = strtotime($search_dates['check_out']);
        
        if (!$check_in || !$check_out || $check_out <= $check_in) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $nights = round(($check_out - $check_in) / 86400);
        
        // Formater selon le format choisi
        $format = $settings['format'];
        $singular = $settings['singular_text'] ?: 'nuit';
        $plural = $settings['plural_text'] ?: 'nuits';
        $prefix = $settings['prefix_text'] ?: 'pour';
        
        $night_text = ($nights === 1) ? $singular : $plural;
        
        switch ($format) {
            case 'number_only':
                echo esc_html($nights);
                break;
                
            case 'number_text':
                echo esc_html($nights . ' ' . $night_text);
                break;
                
            case 'full_text':
                echo esc_html($prefix . ' ' . $nights . ' ' . $night_text);
                break;
                
            default:
                echo esc_html($nights);
        }
    }
}
