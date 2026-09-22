<?php
/**
 * Gestion des configurations API Lodgify
 * Permet d'ajouter/modifier/supprimer plusieurs API Lodgify depuis l'admin
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_API_Settings {
    
    private $option_name = 'lodgify_api_configurations';
    
    public function __construct() {
        // Hooks admin
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_lodgify_save_api', [$this, 'ajax_save_api']);
        add_action('wp_ajax_lodgify_delete_api', [$this, 'ajax_delete_api']);
        add_action('wp_ajax_lodgify_test_api_connection', [$this, 'ajax_test_api_connection']);
    }
    
    /**
     * Enregistrer les settings
     */
    public function register_settings() {
        register_setting('lodgify_api_settings', $this->option_name);
    }
    
    /**
     * Récupérer toutes les configurations API
     */
    public function get_all_apis() {
        $apis = get_option($this->option_name, []);
        
        // Migration: si vide, ajouter les API existantes
        if (empty($apis)) {
            $apis = [
                [
                    'id' => 'api_453125',
                    'name' => 'The Hills',
                    'website_id' => '453125',
                    'api_key' => 'BlgPFQ4/5QA36Frs9Mxk60xyUJRgKSjvLn9hwVFxUXf8ItMEem1InBE2N7aMwn0H',
                    'checkout_slug' => 'thehills',
                    'currency' => 'USD',
                    'active' => true
                ],
                [
                    'id' => 'api_479060',
                    'name' => 'Amazing Stay',
                    'website_id' => '479060',
                    'api_key' => 'qQd5C+bZUSncVCIsmVTzI717D7CsxvyRLv0uJGojLierbYWQukUa5eRCF+muqMgk',
                    'checkout_slug' => 'amazing-stay',
                    'currency' => 'EUR',
                    'active' => true
                ]
            ];
            update_option($this->option_name, $apis);
        }
        
        return $apis;
    }
    
    /**
     * Récupérer une API par son ID
     */
    public function get_api_by_id($api_id) {
        $apis = $this->get_all_apis();
        foreach ($apis as $api) {
            if ($api['id'] === $api_id) {
                return $api;
            }
        }
        return null;
    }
    
    /**
     * Récupérer une API par son website_id
     */
    public function get_api_by_website_id($website_id) {
        $apis = $this->get_all_apis();
        foreach ($apis as $api) {
            if ($api['website_id'] === $website_id) {
                return $api;
            }
        }
        return null;
    }
    
    /**
     * Récupérer la clé API par website_id
     */
    public function get_api_key($website_id) {
        $api = $this->get_api_by_website_id($website_id);
        return $api ? $api['api_key'] : null;
    }
    
    /**
     * Récupérer le slug checkout par website_id
     */
    public function get_checkout_slug($website_id) {
        $api = $this->get_api_by_website_id($website_id);
        return $api ? $api['checkout_slug'] : 'thehills';
    }
    
    /**
     * Récupérer toutes les clés API sous forme de tableau [website_id => api_key]
     */
    public function get_api_keys_array() {
        $apis = $this->get_all_apis();
        $keys = [];
        foreach ($apis as $api) {
            if (!empty($api['active'])) {
                $keys[$api['website_id']] = $api['api_key'];
            }
        }
        return $keys;
    }
    
    /**
     * Sauvegarder une API (AJAX)
     */
    public function ajax_save_api() {
        check_ajax_referer('lodgify_api_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $api_id = isset($_POST['api_id']) ? sanitize_text_field($_POST['api_id']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $website_id = isset($_POST['website_id']) ? sanitize_text_field($_POST['website_id']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $checkout_slug = isset($_POST['checkout_slug']) ? sanitize_text_field($_POST['checkout_slug']) : '';
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'USD';
        $active = isset($_POST['active']) && $_POST['active'] === 'true';
        
        if (empty($name) || empty($website_id) || empty($api_key) || empty($checkout_slug)) {
            wp_send_json_error(['message' => 'Tous les champs sont requis']);
        }
        
        $apis = $this->get_all_apis();
        
        // Nouveau ou mise à jour ?
        if (empty($api_id)) {
            // Nouvelle API
            $api_id = 'api_' . $website_id;
            
            // Vérifier si website_id existe déjà
            foreach ($apis as $existing) {
                if ($existing['website_id'] === $website_id) {
                    wp_send_json_error(['message' => 'Ce Website ID existe déjà']);
                }
            }
            
            $apis[] = [
                'id' => $api_id,
                'name' => $name,
                'website_id' => $website_id,
                'api_key' => $api_key,
                'checkout_slug' => $checkout_slug,
                'currency' => $currency,
                'active' => $active
            ];
        } else {
            // Mise à jour
            foreach ($apis as &$api) {
                if ($api['id'] === $api_id) {
                    $api['name'] = $name;
                    $api['website_id'] = $website_id;
                    $api['api_key'] = $api_key;
                    $api['checkout_slug'] = $checkout_slug;
                    $api['currency'] = $currency;
                    $api['active'] = $active;
                    break;
                }
            }
        }
        
        update_option($this->option_name, $apis);
        
        wp_send_json_success([
            'message' => 'API sauvegardée avec succès',
            'api_id' => $api_id
        ]);
    }
    
    /**
     * Supprimer une API (AJAX)
     */
    public function ajax_delete_api() {
        check_ajax_referer('lodgify_api_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $api_id = isset($_POST['api_id']) ? sanitize_text_field($_POST['api_id']) : '';
        
        if (empty($api_id)) {
            wp_send_json_error(['message' => 'ID API manquant']);
        }
        
        $apis = $this->get_all_apis();
        $apis = array_filter($apis, function($api) use ($api_id) {
            return $api['id'] !== $api_id;
        });
        $apis = array_values($apis); // Réindexer
        
        update_option($this->option_name, $apis);
        
        wp_send_json_success(['message' => 'API supprimée avec succès']);
    }
    
    /**
     * Tester la connexion API (AJAX)
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('lodgify_api_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission refusée']);
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Clé API manquante']);
        }
        
        // Test de connexion à l'API Lodgify
        $response = wp_remote_get('https://api.lodgify.com/v1/properties', [
            'headers' => [
                'X-ApiKey' => $api_key,
                'accept' => 'application/json'
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erreur de connexion: ' . $response->get_error_message()]);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $count = is_array($body) ? count($body) : 0;
            wp_send_json_success([
                'message' => 'Connexion réussie! ' . $count . ' propriétés trouvées.'
            ]);
        } elseif ($code === 401) {
            wp_send_json_error(['message' => 'Clé API invalide (401 Unauthorized)']);
        } else {
            wp_send_json_error(['message' => 'Erreur API: Code ' . $code]);
        }
    }
    
    /**
     * Récupérer l'API pour une propriété (post) - Auto-détection via website_id
     */
    public function get_api_for_property($post_id) {
        global $wpdb;
        $rental_id = get_post_meta($post_id, 'rental-id', true);

        if (!empty($rental_id)) {
            /* AMM_COMPTE 2026-09-21 : la resolution passait par lodgify_daily_prices,
               qui ne couvre que 8 biens sur 124. Les 116 autres tombaient sur le repli
               "premiere API active" et recevaient le slug thehills et la devise USD,
               quel que soit leur vrai compte - donc un envoi possible vers le mauvais
               compte Lodgify. On interroge d'abord lodgify_availabilities, alimentee
               toutes les 15 minutes et complete, puis daily_prices en second recours. */
            $table_avail = $wpdb->prefix . 'lodgify_availabilities';
            $website_id = $wpdb->get_var($wpdb->prepare(
                "SELECT website_id FROM $table_avail WHERE property_id = %s LIMIT 1",
                $rental_id
            ));

            if (empty($website_id)) {
                $table_daily = $wpdb->prefix . 'lodgify_daily_prices';
                $website_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT website_id FROM $table_daily WHERE property_id = %s LIMIT 1",
                    $rental_id
                ));
            }

            if (!empty($website_id)) {
                return $this->get_api_by_website_id($website_id);
            }
        }

        /* AMM_COMPTE : plus de repli sur la premiere API active. Compte introuvable
           = on ne devine pas. L'appelant doit desactiver la reservation plutot que
           d'envoyer le visiteur vers un compte qui n'est pas le sien. */
        return null;
    }
    
    /**
     * Générer l'URL de checkout pour une propriété
     */
    public function get_checkout_url($post_id, $property_id = null) {
        if (empty($property_id)) {
            $property_id = get_post_meta($post_id, 'rental-id', true);
        }
        
        $api = $this->get_api_for_property($post_id);
        
        if (!$api) {
            return '';
        }
        
        return 'https://checkout.lodgify.com/' . $api['checkout_slug'] . '/' . $property_id;
    }
    
    /**
     * Afficher la page de configuration des API
     */
    /**
     * AMM_COMPTE : devise du compte Lodgify du bien. Symetrique de
     * get_checkout_url() : meme resolution, meme source, meme regle d'echec.
     *
     * @return string Symbole, ou '' si le compte est introuvable.
     */
    public function get_currency_for_property( $post_id ) {
        $api = $this->get_api_for_property( $post_id );
        if ( ! $api || empty( $api['currency'] ) ) {
            return '';
        }
        return $this->currency_symbol( $api['currency'] );
    }

    /**
     * Code ISO -> symbole. Un code inconnu est rendu tel quel ("CHF 120"),
     * ce qui reste lisible plutot que de mentir avec un mauvais symbole.
     */
    public function currency_symbol( $code ) {
        $table = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'ANG' => 'ƒ' );
        $code  = strtoupper( (string) $code );
        return isset( $table[ $code ] ) ? $table[ $code ] : $code;
    }

    public function render_settings_page() {
        $apis = $this->get_all_apis();
        ?>
        <div class="wrap">
            <h1>Configuration des API Lodgify</h1>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>API Lodgify configurées</h2>
                <p>Gérez vos différentes API Lodgify ici. Chaque API correspond à un compte/site Lodgify différent.</p>
                
                <table class="widefat" id="lodgify-apis-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Website ID</th>
                            <th>Slug Checkout</th>
                            <th>Devise</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($apis)): ?>
                            <tr class="no-apis">
                                <td colspan="6" style="text-align: center;">Aucune API configurée</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($apis as $api): ?>
                                <tr data-api-id="<?php echo esc_attr($api['id']); ?>">
                                    <td><strong><?php echo esc_html($api['name']); ?></strong></td>
                                    <td><code><?php echo esc_html($api['website_id']); ?></code></td>
                                    <td><code><?php echo esc_html($api['checkout_slug']); ?></code></td>
                                    <td><?php echo esc_html($api['currency']); ?></td>
                                    <td>
                                        <?php if (!empty($api['active'])): ?>
                                            <span style="color: green;">✅ Active</span>
                                        <?php else: ?>
                                            <span style="color: gray;">⏸️ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small edit-api-btn" 
                                                data-api='<?php echo esc_attr(json_encode($api)); ?>'>
                                            Modifier
                                        </button>
                                        <button type="button" class="button button-small button-link-delete delete-api-btn"
                                                data-api-id="<?php echo esc_attr($api['id']); ?>"
                                                data-api-name="<?php echo esc_attr($api['name']); ?>">
                                            Supprimer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p style="margin-top: 15px;">
                    <button type="button" class="button button-primary" id="add-new-api-btn">
                        ➕ Ajouter une nouvelle API
                    </button>
                </p>
            </div>
        </div>
        
        <!-- Modal pour ajouter/modifier une API -->
        <div id="api-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;">
            <div style="background: white; max-width: 500px; margin: 50px auto; padding: 20px; border-radius: 8px; position: relative;">
                <h2 id="modal-title">Ajouter une API Lodgify</h2>
                <button type="button" id="close-modal" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                
                <form id="api-form">
                    <input type="hidden" name="api_id" id="api_id">
                    
                    <p>
                        <label for="api_name"><strong>Nom de l'API:</strong></label><br>
                        <input type="text" name="name" id="api_name" class="regular-text" style="width: 100%;" placeholder="Ex: The Hills, Amazing Stay..." required>
                    </p>
                    
                    <p>
                        <label for="api_website_id"><strong>Website ID:</strong></label><br>
                        <input type="text" name="website_id" id="api_website_id" class="regular-text" style="width: 100%;" placeholder="Ex: 453125" required>
                        <span class="description">L'ID du site Lodgify (visible dans l'URL de votre dashboard Lodgify)</span>
                    </p>
                    
                    <p>
                        <label for="api_key_input"><strong>Clé API:</strong></label><br>
                        <input type="text" name="api_key" id="api_key_input" class="regular-text" style="width: 100%;" placeholder="Votre clé API Lodgify" required>
                        <button type="button" id="test-api-btn" class="button button-small" style="margin-top: 5px;">🔍 Tester la connexion</button>
                        <span id="test-api-result" style="margin-left: 10px;"></span>
                    </p>
                    
                    <p>
                        <label for="api_checkout_slug"><strong>Slug URL Checkout:</strong></label><br>
                        <input type="text" name="checkout_slug" id="api_checkout_slug" class="regular-text" style="width: 100%;" placeholder="Ex: thehills, amazing-stay" required>
                        <span class="description">Le slug utilisé dans l'URL de checkout: checkout.lodgify.com/<strong>slug</strong>/...</span>
                    </p>
                    
                    <p>
                        <label for="api_currency"><strong>Devise:</strong></label><br>
                        <select name="currency" id="api_currency" style="width: 100%;">
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="CAD">CAD ($)</option>
                            <option value="AUD">AUD ($)</option>
                        </select>
                    </p>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="active" id="api_active" value="true" checked>
                            <strong>API Active</strong>
                        </label>
                    </p>
                    
                    <p style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">💾 Sauvegarder</button>
                        <button type="button" class="button" id="cancel-modal">Annuler</button>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('lodgify_api_settings_nonce'); ?>';
            
            // Ouvrir modal pour nouvelle API
            $('#add-new-api-btn').on('click', function() {
                $('#modal-title').text('Ajouter une nouvelle API Lodgify');
                $('#api-form')[0].reset();
                $('#api_id').val('');
                $('#api_active').prop('checked', true);
                $('#test-api-result').text('');
                $('#api-modal').show();
            });
            
            // Ouvrir modal pour modifier
            $(document).on('click', '.edit-api-btn', function() {
                var api = $(this).data('api');
                $('#modal-title').text('Modifier l\'API: ' + api.name);
                $('#api_id').val(api.id);
                $('#api_name').val(api.name);
                $('#api_website_id').val(api.website_id);
                $('#api_key_input').val(api.api_key);
                $('#api_checkout_slug').val(api.checkout_slug);
                $('#api_currency').val(api.currency);
                $('#api_active').prop('checked', api.active);
                $('#test-api-result').text('');
                $('#api-modal').show();
            });
            
            // Fermer modal
            $('#close-modal, #cancel-modal').on('click', function() {
                $('#api-modal').hide();
            });
            
            // Tester la connexion API
            $('#test-api-btn').on('click', function() {
                var btn = $(this);
                var apiKey = $('#api_key_input').val();
                
                if (!apiKey) {
                    $('#test-api-result').html('<span style="color: red;">Entrez une clé API</span>');
                    return;
                }
                
                btn.prop('disabled', true);
                $('#test-api-result').html('<span style="color: gray;">Test en cours...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lodgify_test_api_connection',
                        nonce: nonce,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-api-result').html('<span style="color: green;">✅ ' + response.data.message + '</span>');
                        } else {
                            $('#test-api-result').html('<span style="color: red;">❌ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#test-api-result').html('<span style="color: red;">❌ Erreur de connexion</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });
            
            // Sauvegarder API
            $('#api-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'lodgify_save_api',
                    nonce: nonce,
                    api_id: $('#api_id').val(),
                    name: $('#api_name').val(),
                    website_id: $('#api_website_id').val(),
                    api_key: $('#api_key_input').val(),
                    checkout_slug: $('#api_checkout_slug').val(),
                    currency: $('#api_currency').val(),
                    active: $('#api_active').is(':checked') ? 'true' : 'false'
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion');
                    }
                });
            });
            
            // Supprimer API
            $(document).on('click', '.delete-api-btn', function() {
                var apiId = $(this).data('api-id');
                var apiName = $(this).data('api-name');
                
                if (!confirm('Êtes-vous sûr de vouloir supprimer l\'API "' + apiName + '" ?')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lodgify_delete_api',
                        nonce: nonce,
                        api_id: apiId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialiser
new FourU_Moteur_API_Settings();
