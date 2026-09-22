<?php
/**
 * Dynamic Tag pour afficher le séjour minimum
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag dynamique pour le séjour minimum
 */
class FourU_Moteur_Min_Stay_Tag extends FourU_Moteur_Dynamic_Tag_Base {
    
    public function get_name() {
        return 'lodgify-min-stay';
    }
    
    public function get_title() {
        return __('Séjour Minimum Lodgify', 'lodgify-availability-sync');
    }
    
    public function get_categories() {
        return ['text', 'number'];
    }
    
    protected function register_controls() {
        $this->add_control(
            'prefix_text',
            [
                'label' => __('Texte avant', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        
        $this->add_control(
            'suffix_singular',
            [
                'label' => __('Suffixe singulier', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' night',
            ]
        );
        
        $this->add_control(
            'suffix_plural',
            [
                'label' => __('Suffixe pluriel', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ' nights',
            ]
        );
        
        $this->add_control(
            'fallback',
            [
                'label' => __('Valeur par défaut', 'lodgify-availability-sync'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1 night',
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        $property_id = $this->get_current_property_rental_id();
        
        if (!$property_id) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $search_dates = $this->get_search_dates_from_url();
        $price_info = null;
        
        if ($search_dates) {
            // Avec dates de recherche → min_stay depuis l'API
            $price_info = $this->get_price_info($property_id, $search_dates);
        } else {
            // Sans dates → min_stay depuis la BDD locale
            $price_info = $this->get_base_price($property_id);
        }
        
        if (!$price_info || !isset($price_info['min_stay'])) {
            echo esc_html($settings['fallback']);
            return;
        }
        
        $min_stay = max(1, intval($price_info['min_stay']));
        $suffix = $min_stay > 1 ? $settings['suffix_plural'] : $settings['suffix_singular'];
        
        $output = '';
        
        if (!empty($settings['prefix_text'])) {
            $output .= esc_html($settings['prefix_text']) . ' ';
        }
        
        $output .= $min_stay . $suffix;
        
        echo $output;
    }
}
