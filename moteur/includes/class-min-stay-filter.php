<?php
/**
 * Filtre Min Stay pour les résultats de recherche JetEngine
 * 
 * Exclut les propriétés dont le séjour minimum (min_stay) stocké dans 
 * lodgify_daily_prices est supérieur au nombre de nuits recherchées.
 * 
 * Si toutes les propriétés sont exclues pour N nuits, essaie N+1 nuits
 * et affiche un message de suggestion.
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_Min_Stay_Search_Filter {

    private $table_daily;
    
    /** Contexte de suggestion stocké pour affichage frontend */
    private static $suggestion_context = null;
    
    /** IDs autorisés calculés une seule fois */
    private $computed_allowed_ids = null;
    private $computed = false;

    public function __construct() {
        global $wpdb;
        $this->table_daily = $wpdb->prefix . 'lodgify_daily_prices';

        // Calculer les IDs autorisés tôt (chargement normal)
        add_action('template_redirect', [$this, 'init_filter']);
        
        // Aussi initialiser pendant les requêtes AJAX (pagination/scroll/load more)
        add_action('wp_ajax_jet_smart_filters', [$this, 'init_filter'], 1);
        add_action('wp_ajax_nopriv_jet_smart_filters', [$this, 'init_filter'], 1);
        add_action('wp_ajax_jet_engine_ajax', [$this, 'init_filter'], 1);
        add_action('wp_ajax_nopriv_jet_engine_ajax', [$this, 'init_filter'], 1);
        
        // Injecter le message de suggestion dans le footer
        add_action('wp_footer', [$this, 'render_suggestion_message']);
    }
    
    /**
     * Initialiser le filtre : calculer les IDs et brancher les hooks
     */
    public function init_filter() {
        // Autoriser l'AJAX (admin-ajax.php est côté admin mais sert le frontend)
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        error_log('Lodgify Min Stay Filter: init_filter() appelé, action=' . current_action() . ', URL=' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A'));
        
        $this->compute_allowed_ids();
        
        if ($this->computed_allowed_ids !== null) {
            // Priorité 999 = APRÈS JetSmartFilters (priorité 20) pour avoir le dernier mot
            add_filter('jet-engine/listing/grid/posts-query-args', [$this, 'filter_by_min_stay'], 999, 2);
            add_filter('jet-smart-filters/query/final-query', [$this, 'filter_by_min_stay'], 999);
        }
    }
    
    /**
     * Calculer les IDs autorisés (une seule fois, mis en cache)
     */
    private function compute_allowed_ids() {
        if ($this->computed) {
            return;
        }
        $this->computed = true;
        
        // Ne s'exécuter que sur le frontend (ou AJAX frontend)
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        $dates = $this->parse_search_dates();
        if (!$dates) {
            error_log('Lodgify Min Stay Filter: Pas de dates de recherche trouvées, filtre non appliqué');
            return;
        }
        
        $check_in  = $dates['check_in'];
        $check_out = $dates['check_out'];
        $nights = $this->calculate_nights($check_in, $check_out);
        if ($nights <= 0) {
            return;
        }
        
        error_log('Lodgify Min Stay Filter: === DÉBUT FILTRAGE === check_in=' . $check_in . ', check_out=' . $check_out . ', nights=' . $nights);
        
        $allowed_ids = $this->get_allowed_post_ids($check_in, $check_out, $nights);
        error_log('Lodgify Min Stay Filter: Après min_stay: ' . count($allowed_ids) . ' propriétés (IDs: ' . implode(',', $allowed_ids) . ')');
        
        // Exclure les propriétés indisponibles sur Lodgify (available=0)
        $allowed_ids = $this->filter_unavailable_lodgify($allowed_ids, $check_in, $check_out);
        error_log('Lodgify Min Stay Filter: Après dispo Lodgify: ' . count($allowed_ids) . ' propriétés (IDs: ' . implode(',', $allowed_ids) . ')');
        
        // Exclure les propriétés réservées (JetBooking) pour les dates demandées
        $allowed_ids = $this->filter_booked_apartments($allowed_ids, $check_in, $check_out);
        error_log('Lodgify Min Stay Filter: Après JetBooking: ' . count($allowed_ids) . ' propriétés (IDs: ' . implode(',', $allowed_ids) . ')');
        
        if (empty($allowed_ids)) {
            $suggestion = $this->try_suggestion($check_in, $nights);
            
            if ($suggestion) {
                $this->computed_allowed_ids = $suggestion['allowed_ids'];
                
                self::$suggestion_context = [
                    'original_nights'    => $nights,
                    'suggested_nights'   => $suggestion['nights'],
                    'check_in'           => $check_in,
                    'suggested_checkout'  => $suggestion['check_out'],
                    'results_count'      => count($suggestion['allowed_ids']),
                ];
                
                error_log('Lodgify Min Stay Filter: 0 résultats pour ' . $nights . ' nuit(s), suggestion ' . $suggestion['nights'] . ' nuit(s) → ' . count($suggestion['allowed_ids']) . ' propriétés');
            } else {
                $this->computed_allowed_ids = [0];
                
                self::$suggestion_context = [
                    'original_nights'    => $nights,
                    'suggested_nights'   => 0,
                    'check_in'           => $check_in,
                    'no_results'         => true,
                ];
                
                error_log('Lodgify Min Stay Filter: 0 résultats pour ' . $nights . ' et ' . ($nights + 1) . ' nuit(s)');
            }
        } else {
            $this->computed_allowed_ids = $allowed_ids;
            error_log('Lodgify Min Stay Filter: ' . count($allowed_ids) . ' propriétés autorisées pour ' . $nights . ' nuit(s)');
        }
        
    }

    /**
     * Filtrer les propriétés par min_stay — appelé par JetEngine et JetSmartFilters
     */
    public function filter_by_min_stay($args, $settings = null) {
        if ($this->computed_allowed_ids === null) {
            return $args;
        }
        
        $args['post__in'] = $this->computed_allowed_ids;
        
        return $args;
    }
    
    /**
     * Essayer de trouver des résultats avec exactement N+1 nuits
     * Si N+1 ne donne rien → pas de suggestion
     */
    private function try_suggestion($check_in, $original_nights) {
        $try_nights = $original_nights + 1;
        $try_checkout = date('Y-m-d', strtotime($check_in . ' + ' . $try_nights . ' days'));
        
        $allowed_ids = $this->get_allowed_post_ids($check_in, $try_checkout, $try_nights);
        
        // Exclure les propriétés indisponibles sur Lodgify
        $allowed_ids = $this->filter_unavailable_lodgify($allowed_ids, $check_in, $try_checkout);
        
        // Exclure les propriétés réservées pour les dates de suggestion
        $allowed_ids = $this->filter_booked_apartments($allowed_ids, $check_in, $try_checkout);
        
        if (!empty($allowed_ids)) {
            return [
                'nights'       => $try_nights,
                'check_out'    => $try_checkout,
                'allowed_ids'  => $allowed_ids,
            ];
        }
        
        return null;
    }
    
    /**
     * Remplacer les variables dans un template de message
     */
    private function replace_message_vars($template, $vars) {
        return str_replace(array_keys($vars), array_values($vars), $template);
    }
    
    /**
     * Afficher le message de suggestion dans le frontend
     */
    public function render_suggestion_message() {
        echo '<!-- LODGIFY_SUGGESTION_DEBUG: context=' . (self::$suggestion_context === null ? 'NULL' : 'SET') . ' -->';
        error_log('Lodgify Min Stay Filter: render_suggestion_message() appelé, suggestion_context=' . (self::$suggestion_context === null ? 'NULL' : 'SET'));
        if (self::$suggestion_context === null) {
            return;
        }
        
        $ctx = self::$suggestion_context;
        $s = FourU_Moteur_Message_Settings::get_localized_settings();
        $check_in_formatted = date_i18n('M j, Y', strtotime($ctx['check_in']));
        
        $vars = [
            '{nights}'           => $ctx['original_nights'],
            '{s}'                => $ctx['original_nights'] > 1 ? 's' : '',
            '{checkin}'          => $check_in_formatted,
            '{suggested_nights}' => isset($ctx['suggested_nights']) ? $ctx['suggested_nights'] : '',
            '{s2}'               => (isset($ctx['suggested_nights']) && $ctx['suggested_nights'] > 1) ? 's' : '',
            '{checkout}'         => isset($ctx['suggested_checkout']) ? date_i18n('M j, Y', strtotime($ctx['suggested_checkout'])) : '',
        ];
        
        if (isset($ctx['no_results']) && $ctx['no_results']) {
            $message    = $this->replace_message_vars($s['no_results_text'], $vars);
            $bg_color   = $s['no_results_bg_color'];
            $border_color = $s['no_results_border_color'];
            $text_color = $s['no_results_text_color'];
            $icon       = $s['no_results_icon'];
            $show_suggestions = false;
            $suggestion_text = '';
            $sub_text_color = '';
        } else {
            $message    = $this->replace_message_vars($s['suggestion_text'], $vars);
            $suggestion_text = $this->replace_message_vars($s['suggestion_sub_text'], $vars);
            $bg_color   = $s['suggestion_bg_color'];
            $border_color = $s['suggestion_border_color'];
            $text_color = $s['suggestion_text_color'];
            $sub_text_color = $s['suggestion_sub_text_color'];
            $icon       = $s['suggestion_icon'];
            $show_suggestions = true;
        }
        
        $font_size     = intval($s['font_size']);
        $icon_size     = intval($s['icon_size']);
        $border_radius = intval($s['border_radius']);
        $padding       = intval($s['padding']);
        $font_weight   = intval($s['font_weight']);
        
        ?>
        <style>
            .lodgify-suggestion-banner {
                background: <?php echo esc_attr($bg_color); ?>;
                border: 1px solid <?php echo esc_attr($border_color); ?>;
                border-left: 4px solid <?php echo esc_attr($border_color); ?>;
                border-radius: <?php echo $border_radius; ?>px;
                padding: <?php echo $padding; ?>px <?php echo $padding + 5; ?>px;
                margin: 0 0 20px 0;
                font-size: <?php echo $font_size; ?>px;
                line-height: 1.5;
                display: none;
            }
            .lodgify-suggestion-banner .lodgify-suggestion-icon {
                font-size: <?php echo $icon_size; ?>px;
                margin-right: 8px;
            }
            .lodgify-suggestion-banner .lodgify-suggestion-main {
                color: <?php echo esc_attr($text_color); ?>;
                font-weight: <?php echo $font_weight; ?>;
            }
            .lodgify-suggestion-banner .lodgify-suggestion-sub {
                color: <?php echo esc_attr($sub_text_color ? $sub_text_color : '#555'); ?>;
                margin-top: 5px;
            }
            body.lodgify-suggestion-mode .hide_when_use_filter {
                display: flex !important;
            }
            body.lodgify-suggestion-mode .show_when_use_filter {
                display: none !important;
            }
        </style>
        
        <!-- Banner rendu directement en HTML PHP (pas de création JS) -->
        <div id="lodgify-suggestion-banner" class="lodgify-suggestion-banner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:99999;">
            <span class="lodgify-suggestion-icon"><?php echo $icon; ?></span>
            <span class="lodgify-suggestion-main"><?php echo wp_kses($message, ['strong' => [], 'em' => [], 'br' => []]); ?></span>
            <?php if ($show_suggestions): ?>
            <div class="lodgify-suggestion-sub"><?php echo wp_kses($suggestion_text, ['strong' => [], 'em' => [], 'br' => []]); ?></div>
            <?php endif; ?>
        </div>
        
        <script>
        (function() {
            function moveBanner() {
                var banner = document.getElementById('lodgify-suggestion-banner');
                if (!banner) return true;
                
                var selectors = [
                    '.jet-listing-grid',
                    '.elementor-widget-jet-listing-grid',
                    '[data-is-block="jet-engine/listing-grid"]',
                    '.jet-listing-grid__items'
                ];
                var grid = null;
                for (var i = 0; i < selectors.length; i++) {
                    grid = document.querySelector(selectors[i]);
                    if (grid) break;
                }
                
                if (grid) {
                    // Déplacer le banner juste avant le grid
                    grid.parentNode.insertBefore(banner, grid);
                    banner.style.position = '';
                    banner.style.top = '';
                    banner.style.left = '';
                    banner.style.right = '';
                    banner.style.zIndex = '';
                }
                
                // Afficher le banner (même si le grid n'est pas trouvé, on l'affiche en fixed)
                banner.style.display = 'block';
                document.body.classList.add('lodgify-suggestion-mode');
                
                // Gérer hide/show des filtres en mode suggestion
                if (window.jQuery) {
                    var _origSlideUp = jQuery.fn.slideUp;
                    var _origHide = jQuery.fn.hide;
                    var _origSlideDown = jQuery.fn.slideDown;
                    var _origShow = jQuery.fn.show;
                    
                    jQuery.fn.slideUp = function() {
                        if (document.body.classList.contains('lodgify-suggestion-mode') && this.hasClass('hide_when_use_filter')) {
                            return this;
                        }
                        return _origSlideUp.apply(this, arguments);
                    };
                    
                    jQuery.fn.hide = function() {
                        if (document.body.classList.contains('lodgify-suggestion-mode') && this.hasClass('hide_when_use_filter')) {
                            return this;
                        }
                        return _origHide.apply(this, arguments);
                    };
                    
                    jQuery.fn.slideDown = function() {
                        if (document.body.classList.contains('lodgify-suggestion-mode') && this.hasClass('show_when_use_filter')) {
                            return this;
                        }
                        return _origSlideDown.apply(this, arguments);
                    };
                    
                    jQuery.fn.show = function() {
                        if (document.body.classList.contains('lodgify-suggestion-mode') && this.hasClass('show_when_use_filter')) {
                            return this;
                        }
                        return _origShow.apply(this, arguments);
                    };
                    
                    jQuery('.hide_when_use_filter').each(function() {
                        jQuery(this).stop(true, true).css('display', '');
                    });
                    jQuery('.show_when_use_filter').each(function() {
                        jQuery(this).stop(true, true).css('display', 'none');
                    });
                    
                    jQuery(document).on('jet-smart-filters/inited', function() {
                        if (typeof JetSmartFilters !== 'undefined' && JetSmartFilters.events) {
                            JetSmartFilters.events.subscribe('activeItems/change', function() {
                                if (document.body.classList.contains('lodgify-suggestion-mode')) {
                                    setTimeout(function() {
                                        jQuery('.hide_when_use_filter').stop(true, true).css('display', '');
                                        jQuery('.show_when_use_filter').stop(true, true).css('display', 'none');
                                    }, 50);
                                }
                            });
                        }
                    });
                }
                
                <?php if (isset($ctx['no_results']) && $ctx['no_results']): ?>
                if (grid) {
                    var notFound = grid.querySelector('.jet-listing-grid__not-found');
                    if (notFound) notFound.style.display = 'none';
                }
                <?php endif; ?>
                return true;
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    moveBanner();
                    // Retry pour déplacer au bon endroit si le grid charge en async
                    setTimeout(moveBanner, 1000);
                    setTimeout(moveBanner, 3000);
                });
            } else {
                moveBanner();
                setTimeout(moveBanner, 1000);
                setTimeout(moveBanner, 3000);
            }
        })();
        </script>
        <?php
    }

    /**
     * Parser les dates depuis le paramètre meta de l'URL
     * Format: checkin_checkout!date:2026.7.16-2026.7.17
     */
    private function parse_search_dates() {
        $meta = null;
        $source = 'none';
        
        // 1. Paramètre URL direct (chargement normal)
        if (!empty($_GET['meta'])) {
            $meta = sanitize_text_field($_GET['meta']);
            $source = 'GET';
        }
        
        // 2. Données AJAX JetSmartFilters (query.meta)
        if (!$meta && !empty($_REQUEST['query']['meta'])) {
            $meta = sanitize_text_field($_REQUEST['query']['meta']);
            $source = 'REQUEST[query][meta]';
        }
        
        // 3. Données AJAX JetSmartFilters - autre format possible
        if (!$meta && !empty($_POST['query'])) {
            $query = $_POST['query'];
            if (is_string($query)) {
                $decoded = json_decode(stripslashes($query), true);
                if ($decoded && !empty($decoded['meta'])) {
                    $meta = sanitize_text_field($decoded['meta']);
                    $source = 'POST[query] json decoded';
                }
            }
        }
        
        // 4. Données AJAX JetSmartFilters - chercher dans les filtres actifs
        if (!$meta && !empty($_REQUEST['filters'])) {
            $filters = $_REQUEST['filters'];
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    if (is_array($filter) && !empty($filter['meta'])) {
                        $meta = sanitize_text_field($filter['meta']);
                        $source = 'REQUEST[filters]';
                        break;
                    }
                }
            }
        }
        
        // 5. Données AJAX JetEngine load more - page_settings peut contenir l'URL d'origine
        if (!$meta && !empty($_REQUEST['page_settings']['query_string'])) {
            $qs = $_REQUEST['page_settings']['query_string'];
            if (is_string($qs)) {
                parse_str($qs, $qs_params);
                if (!empty($qs_params['meta'])) {
                    $meta = sanitize_text_field($qs_params['meta']);
                    $source = 'REQUEST[page_settings][query_string]';
                }
            }
        }
        
        // 6. Données AJAX JetEngine - post__in de la query initiale (nos IDs filtrés)
        // Si la query originale contient déjà un post__in, c'est probablement notre filtre
        if (!$meta && !empty($_REQUEST['page_settings']['meta'])) {
            $meta = sanitize_text_field($_REQUEST['page_settings']['meta']);
            $source = 'REQUEST[page_settings][meta]';
        }
        
        // 7. Fallback : HTTP Referer (requêtes AJAX de pagination/scroll)
        if (!$meta && !empty($_SERVER['HTTP_REFERER'])) {
            $referer_parts = parse_url($_SERVER['HTTP_REFERER']);
            if (!empty($referer_parts['query'])) {
                parse_str($referer_parts['query'], $referer_params);
                if (!empty($referer_params['meta'])) {
                    $meta = sanitize_text_field($referer_params['meta']);
                    $source = 'HTTP_REFERER';
                }
            }
        }
        
        if (wp_doing_ajax()) {
            error_log('Lodgify Min Stay Filter: parse_search_dates() AJAX source=' . $source . ', meta=' . ($meta ?: 'NULL') . ', referer=' . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));
        }
        
        if (!$meta) {
            return null;
        }

        if (strpos($meta, 'checkin_checkout!date:') === false) {
            return null;
        }

        // Extraire la partie date
        $date_part = explode('checkin_checkout!date:', $meta);
        if (!isset($date_part[1])) {
            return null;
        }
        $date_part = $date_part[1];

        // Couper si d'autres paramètres suivent (séparés par ;)
        if (strpos($date_part, ';') !== false) {
            $date_part = explode(';', $date_part)[0];
        }

        // Séparer check-in et check-out
        $dates = explode('-', $date_part);
        if (count($dates) < 2) {
            return null;
        }

        // Le format peut contenir 6 parties (année.mois.jour-année.mois.jour)
        // car le tiret sépare aussi les deux dates
        // Format: 2026.7.16-2026.7.17 → on a potentiellement 2026.7.16 et 2026.7.17
        // Mais si le mois ou jour contient un tiret... 
        // En fait le format est: YYYY.M.DD-YYYY.M.DD avec un seul tiret séparateur
        
        $date_in_parts  = explode('.', $dates[0]);
        $date_out_parts = explode('.', $dates[1]);

        if (count($date_in_parts) !== 3 || count($date_out_parts) !== 3) {
            return null;
        }

        $check_in = sprintf(
            '%s-%s-%s',
            $date_in_parts[0],
            str_pad($date_in_parts[1], 2, '0', STR_PAD_LEFT),
            str_pad($date_in_parts[2], 2, '0', STR_PAD_LEFT)
        );

        $check_out = sprintf(
            '%s-%s-%s',
            $date_out_parts[0],
            str_pad($date_out_parts[1], 2, '0', STR_PAD_LEFT),
            str_pad($date_out_parts[2], 2, '0', STR_PAD_LEFT)
        );

        // Valider les dates
        $ci_time = strtotime($check_in);
        $co_time = strtotime($check_out);

        if (!$ci_time || !$co_time || $ci_time >= $co_time) {
            return null;
        }

        return [
            'check_in'  => $check_in,
            'check_out' => $check_out,
        ];
    }

    /**
     * Calculer le nombre de nuits entre deux dates
     */
    private function calculate_nights($check_in, $check_out) {
        $ci = new DateTime($check_in);
        $co = new DateTime($check_out);
        return (int) $ci->diff($co)->days;
    }

    /**
     * Récupérer les post IDs WordPress AUTORISÉS pour N nuits
     * 
     * Logique inversée : on ne retourne QUE les propriétés dont le min_stay <= N nuits.
     * Toute propriété absente de ce résultat sera exclue.
     */
    private function get_allowed_post_ids($check_in, $check_out, $nights) {
        global $wpdb;

        $date_wide_start = date('Y-m-d', strtotime($check_in . ' - 7 days'));
        $date_wide_end   = date('Y-m-d', strtotime($check_out . ' + 7 days'));

        // Récupérer le min_stay effectif pour chaque propriété.
        // COALESCE : priorité période exacte → fenêtre élargie → dernier connu.
        // On ne garde que celles dont effective_min_stay <= $nights
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.property_id,
                COALESCE(
                    exact_period.ms,
                    wide_period.ms,
                    latest.ms
                ) as effective_min_stay
             FROM (SELECT DISTINCT property_id FROM {$this->table_daily}) p
             LEFT JOIN (
                 SELECT property_id, MAX(min_stay) as ms
                 FROM {$this->table_daily}
                 WHERE date >= %s AND date < %s
                 GROUP BY property_id
             ) exact_period ON p.property_id = exact_period.property_id
             LEFT JOIN (
                 SELECT property_id, MAX(min_stay) as ms
                 FROM {$this->table_daily}
                 WHERE date >= %s AND date <= %s
                 GROUP BY property_id
             ) wide_period ON p.property_id = wide_period.property_id
             LEFT JOIN (
                 SELECT dp.property_id, dp.min_stay as ms
                 FROM {$this->table_daily} dp
                 INNER JOIN (
                     SELECT property_id, MAX(date) as max_date
                     FROM {$this->table_daily}
                     GROUP BY property_id
                 ) mx ON dp.property_id = mx.property_id AND dp.date = mx.max_date
             ) latest ON p.property_id = latest.property_id
             HAVING effective_min_stay IS NOT NULL AND effective_min_stay <= %d",
            $check_in, $check_out,
            $date_wide_start, $date_wide_end,
            $nights
        ));

        if (empty($results)) {
            return [];
        }

        // Construire la liste des property_id Lodgify autorisés
        $allowed_rental_ids = [];
        foreach ($results as $row) {
            $allowed_rental_ids[] = $row->property_id;
            error_log('Lodgify Min Stay Filter: Propriété ' . $row->property_id . ' autorisée (min_stay=' . $row->effective_min_stay . ', nuits=' . $nights . ')');
        }

        // Trouver les post IDs WordPress correspondants
        $placeholders = implode(',', array_fill(0, count($allowed_rental_ids), '%s'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rental-id'
             AND pm.meta_value IN ($placeholders)
             AND p.post_status = 'publish'",
            $allowed_rental_ids
        );

        $post_ids = $wpdb->get_col($query);

        return array_map('intval', $post_ids);
    }
    
    /**
     * Exclure les propriétés réservées via JetBooking.
     * 
     * Utilise un mapping via rental-id pour gérer les traductions Polylang :
     * Si apartment_id X est réservé dans JetBooking et partage le même rental-id
     * que le post Y du listing, alors Y est aussi exclu.
     */
    private function filter_booked_apartments($post_ids, $check_in, $check_out) {
        if (empty($post_ids)) {
            return $post_ids;
        }
        
        global $wpdb;
        
        // Convertir les dates en timestamps Unix
        $from = strtotime($check_in);
        $to   = strtotime($check_out);
        
        if (!$from || !$to) {
            return $post_ids;
        }
        
        // Récupérer les apartment_ids réservés directement depuis la table
        $jb_table = $wpdb->prefix . 'jet_apartment_bookings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jb_table}'");
        
        if (!$table_exists) {
            error_log('Lodgify Min Stay Filter: Table jet_apartment_bookings introuvable');
            return $post_ids;
        }
        
        // Log détaillé pour diagnostic des dates
        // Pour la RECHERCHE : la nuit du checkin EST occupée (le client dort là)
        // Overlap = check_in_date < $to AND check_out_date >= $from
        // (co en base = dernier jour occupé, ex: résa Lodgify 7→10 → ci=7, co=9)
        $debug_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_id, apartment_id, check_in_date, check_out_date, status 
             FROM {$jb_table}
             WHERE check_in_date < %d AND check_out_date >= %d
             AND status = 'pending'
             LIMIT 20",
            $to, $from
        ));
        if (!empty($debug_bookings)) {
            foreach ($debug_bookings as $db) {
                error_log(sprintf(
                    'Lodgify Min Stay Filter: [BOOKING #%d] apt=%d, ci=%s (%d), co=%s (%d), status=%s | Recherche: from=%s (%d), to=%s (%d)',
                    $db->booking_id, $db->apartment_id,
                    date('Y-m-d H:i', $db->check_in_date), $db->check_in_date,
                    date('Y-m-d H:i', $db->check_out_date), $db->check_out_date,
                    $db->status,
                    $check_in, $from, $check_out, $to
                ));
            }
        }
        
        // Pour la RECHERCHE : nuit du checkin = occupée
        // ci < $to AND co >= $from → overlap entre [ci..co] et [from..to)
        $booked_apartment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT apartment_id FROM {$jb_table}
             WHERE check_in_date < %d AND check_out_date >= %d
             AND status = 'pending'",
            $to, $from
        ));
        
        if (empty($booked_apartment_ids)) {
            error_log('Lodgify Min Stay Filter: Aucune réservation JetBooking pour ' . $check_in . ' → ' . $check_out);
            return $post_ids;
        }
        
        $booked_apartment_ids = array_map('intval', $booked_apartment_ids);
        error_log('Lodgify Min Stay Filter: ' . count($booked_apartment_ids) . ' apartment_ids réservés dans JetBooking');
        
        // Étape 1 : Exclusion directe (apartment_id = post_id du listing)
        $excluded_post_ids = array_intersect($post_ids, $booked_apartment_ids);
        
        // Étape 2 : Exclusion via mapping rental-id (pour les traductions Polylang)
        // Récupérer les rental-id des apartment_ids réservés
        $booked_placeholders = implode(',', $booked_apartment_ids);
        $booked_rental_ids = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = 'rental-id' AND meta_value != ''
             AND post_id IN ($booked_placeholders)"
        );
        
        if (!empty($booked_rental_ids)) {
            // Récupérer les rental-id des post_ids du listing
            $listing_placeholders = implode(',', array_map('intval', $post_ids));
            $listing_rental_map = $wpdb->get_results(
                "SELECT post_id, meta_value as rental_id FROM {$wpdb->postmeta}
                 WHERE meta_key = 'rental-id' AND meta_value != ''
                 AND post_id IN ($listing_placeholders)"
            );
            
            // Exclure les post_ids du listing dont le rental-id est dans les rental-ids réservés
            foreach ($listing_rental_map as $row) {
                if (in_array($row->rental_id, $booked_rental_ids)) {
                    $excluded_post_ids[(int) $row->post_id] = (int) $row->post_id;
                }
            }
        }
        
        $excluded_post_ids = array_unique(array_map('intval', array_values($excluded_post_ids)));
        
        if (!empty($excluded_post_ids)) {
            error_log('Lodgify Min Stay Filter: Posts exclus via JetBooking+rental-id: ' . implode(',', $excluded_post_ids));
        }
        
        $available_ids = array_values(array_diff($post_ids, $excluded_post_ids));
        
        error_log('Lodgify Min Stay Filter: ' . count($post_ids) . ' → ' . count($available_ids) . ' après exclusion JetBooking (mapping rental-id)');
        
        return $available_ids;
    }
    
    /**
     * Exclure les propriétés indisponibles sur Lodgify.
     * 
     * Stratégie double :
     * 1) Si lodgify_availabilities contient des données → utiliser available=0
     * 2) Sinon (table vide) → utiliser lodgify_daily_prices : 
     *    une propriété DOIT avoir un prix pour CHAQUE jour de la période demandée,
     *    sinon elle est considérée indisponible (Lodgify ne retourne pas de prix pour les dates bloquées).
     */
    private function filter_unavailable_lodgify($post_ids, $check_in, $check_out) {
        if (empty($post_ids)) {
            return $post_ids;
        }
        
        global $wpdb;
        $table_avail = $wpdb->prefix . 'lodgify_availabilities';
        
        // Vérifier si lodgify_availabilities contient des données
        $avail_count = 0;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_avail}'");
        if ($table_exists) {
            $avail_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_avail}");
        }
        
        if ($avail_count > 0) {
            // Méthode 1 : lodgify_availabilities a des données → l'utiliser
            return $this->filter_by_availabilities_table($post_ids, $check_in, $check_out);
        }
        
        // Méthode 2 : lodgify_availabilities est vide → utiliser lodgify_daily_prices
        error_log('Lodgify Min Stay Filter: lodgify_availabilities est VIDE, vérification via lodgify_daily_prices');
        return $this->filter_by_daily_prices($post_ids, $check_in, $check_out);
    }
    
    /**
     * Filtrer via lodgify_availabilities (periodes available=0)
     */
    private function filter_by_availabilities_table($post_ids, $check_in, $check_out) {
        global $wpdb;
        $table_avail = $wpdb->prefix . 'lodgify_availabilities';
        
        $unavailable_property_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT property_id FROM {$table_avail}
             WHERE available = 0
             AND start_date < %s
             AND end_date > %s",
            $check_out,
            $check_in
        ));
        
        if (empty($unavailable_property_ids)) {
            return $post_ids;
        }
        
        error_log('Lodgify Min Stay Filter: ' . count($unavailable_property_ids) . ' propriétés indisponibles via lodgify_availabilities');
        
        $placeholders = implode(',', array_fill(0, count($unavailable_property_ids), '%s'));
        $unavailable_post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = 'rental-id'
             AND pm.meta_value IN ($placeholders)",
            $unavailable_property_ids
        ));
        
        $unavailable_post_ids = array_map('intval', $unavailable_post_ids);
        $available_ids = array_values(array_diff($post_ids, $unavailable_post_ids));
        
        error_log('Lodgify Min Stay Filter: ' . count($post_ids) . ' → ' . count($available_ids) . ' après exclusion via availabilities');
        return $available_ids;
    }
    
    /**
     * Filtrer via lodgify_daily_prices : une propriété doit avoir un prix
     * pour CHAQUE jour de [check_in, check_out) sinon elle est indisponible.
     * Lodgify ne retourne pas de prix pour les dates bloquées/réservées.
     */
    private function filter_by_daily_prices($post_ids, $check_in, $check_out) {
        global $wpdb;
        
        $nights = $this->calculate_nights($check_in, $check_out);
        if ($nights <= 0) {
            return $post_ids;
        }
        
        // Récupérer les rental-id des post_ids passés en paramètre
        $post_id_list = implode(',', array_map('intval', $post_ids));
        $rental_map = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as rental_id
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = 'rental-id'
             AND pm.meta_value != ''
             AND pm.post_id IN ($post_id_list)"
        );
        
        if (empty($rental_map)) {
            return $post_ids;
        }
        
        // Construire le mapping rental_id → post_ids
        $rental_to_posts = [];
        foreach ($rental_map as $row) {
            $rental_to_posts[$row->rental_id][] = (int) $row->post_id;
        }
        
        $rental_ids = array_keys($rental_to_posts);
        $placeholders = implode(',', array_fill(0, count($rental_ids), '%s'));
        
        // Compter combien de jours chaque propriété a un prix dans [check_in, check_out)
        $params = array_merge($rental_ids, [$check_in, $check_out]);
        $price_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT property_id, COUNT(DISTINCT date) as days_with_price
             FROM {$this->table_daily}
             WHERE property_id IN ($placeholders)
             AND date >= %s AND date < %s
             GROUP BY property_id",
            $params
        ));
        
        // Indexer par property_id
        $days_map = [];
        foreach ($price_counts as $row) {
            $days_map[$row->property_id] = (int) $row->days_with_price;
        }
        
        // Filtrer : garder seulement les post_ids dont la propriété a un prix pour TOUS les jours
        $available_post_ids = [];
        $excluded_count = 0;
        
        foreach ($rental_to_posts as $rental_id => $pids) {
            $days_available = isset($days_map[$rental_id]) ? $days_map[$rental_id] : 0;
            
            if ($days_available >= $nights) {
                // Propriété a un prix pour chaque jour → disponible
                foreach ($pids as $pid) {
                    $available_post_ids[] = $pid;
                }
            } else {
                // Propriété n'a pas de prix pour tous les jours → indisponible
                $excluded_count++;
                error_log('Lodgify Min Stay Filter: Propriété ' . $rental_id . ' EXCLUE (prix pour ' . $days_available . '/' . $nights . ' jours)');
            }
        }
        
        // Aussi garder les post_ids qui n'ont pas de rental-id (ne pas les exclure)
        $posts_with_rental = [];
        foreach ($rental_to_posts as $pids) {
            $posts_with_rental = array_merge($posts_with_rental, $pids);
        }
        foreach ($post_ids as $pid) {
            if (!in_array($pid, $posts_with_rental) && !in_array($pid, $available_post_ids)) {
                $available_post_ids[] = $pid;
            }
        }
        
        error_log('Lodgify Min Stay Filter: ' . count($post_ids) . ' → ' . count($available_post_ids) . ' après exclusion via daily_prices (' . $excluded_count . ' exclues, ' . $nights . ' nuits requises)');
        
        return array_values($available_post_ids);
    }
    
    /**
     * Récupérer TOUS les post IDs de propriétés publiées avec un rental-id
     */
    private function get_all_property_post_ids() {
        global $wpdb;
        
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }
        
        $ids = array_map('intval', $wpdb->get_col(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rental-id'
             AND pm.meta_value != ''
             AND p.post_status = 'publish'"
        ));
        
        return $ids;
    }
}
