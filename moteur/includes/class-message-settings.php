<?php
/**
 * Page d'administration pour personnaliser les messages de suggestion Min Stay
 * Support multilingue FR / EN via Polylang
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_Message_Settings {

    const OPTION_KEY = 'lodgify_message_settings';

    /**
     * Langues supportées
     */
    public static function get_languages() {
        return ['en', 'fr'];
    }

    /**
     * Labels des langues
     */
    public static function get_language_labels() {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ];
    }

    /**
     * Valeurs par défaut — par langue pour les textes, partagées pour styles/couleurs
     */
    public static function get_defaults() {
        return [
            // === Textes EN ===
            'en_suggestion_icon'           => '💡',
            'en_suggestion_text'           => 'No properties available for {nights} night{s} from {checkin}.',
            'en_suggestion_sub_text'       => 'Here are suggestions for <strong>{suggested_nights} night{s2}</strong> (until {checkout}):',
            'en_no_results_icon'           => '⚠️',
            'en_no_results_text'           => 'No properties available for {nights} night{s} from {checkin}. Please try different dates or a longer stay.',

            // === Textes FR ===
            'fr_suggestion_icon'           => '💡',
            'fr_suggestion_text'           => 'Aucune propriété disponible pour {nights} nuit{s} à partir du {checkin}.',
            'fr_suggestion_sub_text'       => 'Voici des suggestions pour <strong>{suggested_nights} nuit{s2}</strong> (jusqu\'au {checkout}) :',
            'fr_no_results_icon'           => '⚠️',
            'fr_no_results_text'           => 'Aucune propriété disponible pour {nights} nuit{s} à partir du {checkin}. Veuillez essayer d\'autres dates ou un séjour plus long.',

            // === Couleurs suggestion (partagées) ===
            'suggestion_bg_color'          => '#e8f4fd',
            'suggestion_border_color'      => '#007cba',
            'suggestion_text_color'        => '#333333',
            'suggestion_sub_text_color'    => '#555555',

            // === Couleurs no results (partagées) ===
            'no_results_bg_color'          => '#fff3cd',
            'no_results_border_color'      => '#ffc107',
            'no_results_text_color'        => '#333333',

            // === Styles communs ===
            'font_size'                    => '15',
            'border_radius'                => '6',
            'padding'                      => '15',
            'icon_size'                    => '18',
            'font_weight'                  => '600',
        ];
    }

    /**
     * Récupérer les options sauvegardées (avec fallback aux défauts)
     */
    public static function get_settings() {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args($saved, self::get_defaults());
    }

    /**
     * Détecter la langue courante sur le frontend
     */
    public static function get_current_language() {
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language('slug');
            if (in_array($lang, self::get_languages())) {
                return $lang;
            }
        }
        return 'en';
    }

    /**
     * Récupérer les settings pour la langue courante (frontend)
     * Retourne les clés sans préfixe de langue pour faciliter l'utilisation
     */
    public static function get_localized_settings() {
        $all = self::get_settings();
        $lang = self::get_current_language();

        return [
            'suggestion_icon'           => $all[$lang . '_suggestion_icon'],
            'suggestion_text'           => $all[$lang . '_suggestion_text'],
            'suggestion_sub_text'       => $all[$lang . '_suggestion_sub_text'],
            'no_results_icon'           => $all[$lang . '_no_results_icon'],
            'no_results_text'           => $all[$lang . '_no_results_text'],
            'suggestion_bg_color'       => $all['suggestion_bg_color'],
            'suggestion_border_color'   => $all['suggestion_border_color'],
            'suggestion_text_color'     => $all['suggestion_text_color'],
            'suggestion_sub_text_color' => $all['suggestion_sub_text_color'],
            'no_results_bg_color'       => $all['no_results_bg_color'],
            'no_results_border_color'   => $all['no_results_border_color'],
            'no_results_text_color'     => $all['no_results_text_color'],
            'font_size'                 => $all['font_size'],
            'border_radius'             => $all['border_radius'],
            'padding'                   => $all['padding'],
            'icon_size'                 => $all['icon_size'],
            'font_weight'               => $all['font_weight'],
        ];
    }

    /**
     * Sauvegarder les options via AJAX
     */
    public static function ajax_save_settings() {
        check_ajax_referer('lodgify_message_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $defaults = self::get_defaults();
        $text_fields = [];
        foreach (self::get_languages() as $lang) {
            $text_fields[] = $lang . '_suggestion_text';
            $text_fields[] = $lang . '_suggestion_sub_text';
            $text_fields[] = $lang . '_no_results_text';
        }

        $settings = [];
        foreach ($defaults as $key => $default) {
            if (isset($_POST[$key])) {
                $value = wp_unslash($_POST[$key]);
                if (in_array($key, $text_fields)) {
                    $settings[$key] = wp_kses($value, ['strong' => [], 'em' => [], 'br' => []]);
                } else {
                    $settings[$key] = sanitize_text_field($value);
                }
            } else {
                $settings[$key] = $default;
            }
        }

        update_option(self::OPTION_KEY, $settings, false);
        wp_send_json_success('Settings saved');
    }

    /**
     * Rendu de la page admin
     */
    public static function render_page() {
        $s = self::get_settings();
        $nonce = wp_create_nonce('lodgify_message_settings_nonce');
        $langs = self::get_languages();
        $lang_labels = self::get_language_labels();
        ?>
        <div class="wrap">
            <h1>🎨 Messages & Styles — Min Stay Filter</h1>
            <p>Personnalisez les textes, couleurs et styles des messages affichés. Les textes sont configurables par langue (EN / FR).</p>

            <form id="lodgify-msg-settings-form">
                <input type="hidden" name="action" value="lodgify_save_message_settings">
                <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">

                <!-- ========== ONGLETS LANGUES ========== -->
                <h2 class="nav-tab-wrapper" id="lang-tabs">
                    <?php foreach ($langs as $i => $lang): ?>
                        <a href="#" class="nav-tab <?php echo $i === 0 ? 'nav-tab-active' : ''; ?>" data-lang="<?php echo $lang; ?>">
                            <?php echo $lang === 'en' ? '🇬🇧' : '🇫🇷'; ?> <?php echo esc_html($lang_labels[$lang]); ?>
                        </a>
                    <?php endforeach; ?>
                </h2>

                <?php foreach ($langs as $i => $lang): ?>
                <div class="lang-panel" data-lang="<?php echo $lang; ?>" style="<?php echo $i !== 0 ? 'display:none;' : ''; ?>">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; max-width:1200px; margin-top:20px;">

                        <!-- ========== SUGGESTION ========== -->
                        <div class="card" style="padding:20px;">
                            <h2 style="margin-top:0;">💡 Message de suggestion (N+1 nuits) — <?php echo strtoupper($lang); ?></h2>
                            <p style="color:#666; font-size:13px;">Affiché quand des résultats sont trouvés avec une nuit supplémentaire.</p>

                            <table class="form-table">
                                <tr>
                                    <th><label>Icône</label></th>
                                    <td><input type="text" name="<?php echo $lang; ?>_suggestion_icon" value="<?php echo esc_attr($s[$lang . '_suggestion_icon']); ?>" style="width:80px;font-size:24px;text-align:center;"></td>
                                </tr>
                                <tr>
                                    <th><label>Texte principal</label></th>
                                    <td>
                                        <textarea name="<?php echo $lang; ?>_suggestion_text" rows="2" class="large-text"><?php echo esc_textarea($s[$lang . '_suggestion_text']); ?></textarea>
                                        <p class="description">Variables : <code>{nights}</code> <code>{s}</code> <code>{checkin}</code></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Sous-texte</label></th>
                                    <td>
                                        <textarea name="<?php echo $lang; ?>_suggestion_sub_text" rows="2" class="large-text"><?php echo esc_textarea($s[$lang . '_suggestion_sub_text']); ?></textarea>
                                        <p class="description">Variables : <code>{suggested_nights}</code> <code>{s2}</code> <code>{checkout}</code>. HTML : <code>&lt;strong&gt;</code> <code>&lt;em&gt;</code></p>
                                    </td>
                                </tr>
                            </table>

                            <h3>Aperçu</h3>
                            <div class="preview-suggestion" data-lang="<?php echo $lang; ?>" style="border-radius:6px; padding:15px 20px; margin-top:10px; border-left:4px solid #007cba; font-size:15px; line-height:1.5;"></div>
                        </div>

                        <!-- ========== NO RESULTS ========== -->
                        <div class="card" style="padding:20px;">
                            <h2 style="margin-top:0;">⚠️ Message aucun résultat — <?php echo strtoupper($lang); ?></h2>
                            <p style="color:#666; font-size:13px;">Affiché quand aucune propriété n'est trouvée, même avec N+1 nuits.</p>

                            <table class="form-table">
                                <tr>
                                    <th><label>Icône</label></th>
                                    <td><input type="text" name="<?php echo $lang; ?>_no_results_icon" value="<?php echo esc_attr($s[$lang . '_no_results_icon']); ?>" style="width:80px;font-size:24px;text-align:center;"></td>
                                </tr>
                                <tr>
                                    <th><label>Texte</label></th>
                                    <td>
                                        <textarea name="<?php echo $lang; ?>_no_results_text" rows="3" class="large-text"><?php echo esc_textarea($s[$lang . '_no_results_text']); ?></textarea>
                                        <p class="description">Variables : <code>{nights}</code> <code>{s}</code> <code>{checkin}</code></p>
                                    </td>
                                </tr>
                            </table>

                            <h3>Aperçu</h3>
                            <div class="preview-no-results" data-lang="<?php echo $lang; ?>" style="border-radius:6px; padding:15px 20px; margin-top:10px; border-left:4px solid #ffc107; font-size:15px; line-height:1.5;"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- ========== COULEURS (partagées) ========== -->
                <div class="card" style="padding:20px; max-width:1200px; margin-top:20px;">
                    <h2 style="margin-top:0;">🎨 Couleurs (toutes langues)</h2>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
                        <div>
                            <h3 style="margin-top:0;">💡 Suggestion</h3>
                            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Fond</label>
                                    <input type="color" name="suggestion_bg_color" value="<?php echo esc_attr($s['suggestion_bg_color']); ?>">
                                </div>
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Bordure</label>
                                    <input type="color" name="suggestion_border_color" value="<?php echo esc_attr($s['suggestion_border_color']); ?>">
                                </div>
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Texte</label>
                                    <input type="color" name="suggestion_text_color" value="<?php echo esc_attr($s['suggestion_text_color']); ?>">
                                </div>
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Sous-texte</label>
                                    <input type="color" name="suggestion_sub_text_color" value="<?php echo esc_attr($s['suggestion_sub_text_color']); ?>">
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 style="margin-top:0;">⚠️ Aucun résultat</h3>
                            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Fond</label>
                                    <input type="color" name="no_results_bg_color" value="<?php echo esc_attr($s['no_results_bg_color']); ?>">
                                </div>
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Bordure</label>
                                    <input type="color" name="no_results_border_color" value="<?php echo esc_attr($s['no_results_border_color']); ?>">
                                </div>
                                <div>
                                    <label style="font-size:12px; display:block; margin-bottom:4px;">Texte</label>
                                    <input type="color" name="no_results_text_color" value="<?php echo esc_attr($s['no_results_text_color']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== STYLES COMMUNS ========== -->
                <div class="card" style="padding:20px; max-width:1200px; margin-top:20px;">
                    <h2 style="margin-top:0;">📐 Styles communs</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="font_size">Taille du texte (px)</label></th>
                            <td><input type="number" id="font_size" name="font_size" value="<?php echo esc_attr($s['font_size']); ?>" min="10" max="30" style="width:80px;"> px</td>
                        </tr>
                        <tr>
                            <th><label for="icon_size">Taille de l'icône (px)</label></th>
                            <td><input type="number" id="icon_size" name="icon_size" value="<?php echo esc_attr($s['icon_size']); ?>" min="12" max="40" style="width:80px;"> px</td>
                        </tr>
                        <tr>
                            <th><label for="border_radius">Arrondi des coins (px)</label></th>
                            <td><input type="number" id="border_radius" name="border_radius" value="<?php echo esc_attr($s['border_radius']); ?>" min="0" max="30" style="width:80px;"> px</td>
                        </tr>
                        <tr>
                            <th><label for="padding">Espacement interne (px)</label></th>
                            <td><input type="number" id="padding" name="padding" value="<?php echo esc_attr($s['padding']); ?>" min="5" max="40" style="width:80px;"> px</td>
                        </tr>
                        <tr>
                            <th><label for="font_weight">Poids du texte principal</label></th>
                            <td>
                                <select id="font_weight" name="font_weight">
                                    <option value="400" <?php selected($s['font_weight'], '400'); ?>>Normal (400)</option>
                                    <option value="500" <?php selected($s['font_weight'], '500'); ?>>Medium (500)</option>
                                    <option value="600" <?php selected($s['font_weight'], '600'); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected($s['font_weight'], '700'); ?>>Bold (700)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <p style="margin-top:20px;">
                    <button type="submit" class="button button-primary button-hero" id="save-msg-settings">💾 Sauvegarder les paramètres</button>
                    <button type="button" class="button button-secondary" id="reset-msg-settings" style="margin-left:10px;">🔄 Réinitialiser par défaut</button>
                    <span id="save-msg-status" style="margin-left:15px; display:none; font-weight:600;"></span>
                </p>
            </form>
        </div>

        <script>
        jQuery(function($) {
            var defaults = <?php echo json_encode(self::get_defaults()); ?>;
            var langs = <?php echo json_encode($langs); ?>;

            // Onglets langues
            $('#lang-tabs a').on('click', function(e) {
                e.preventDefault();
                var lang = $(this).data('lang');
                $('#lang-tabs a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.lang-panel').hide();
                $('.lang-panel[data-lang="' + lang + '"]').show();
            });

            function updatePreviews() {
                var f = {};
                $('#lodgify-msg-settings-form').serializeArray().forEach(function(item) {
                    f[item.name] = item.value;
                });

                var demoVarsEN = {
                    '{nights}': '1', '{s}': '', '{checkin}': 'May 8, 2026',
                    '{suggested_nights}': '2', '{s2}': 's', '{checkout}': 'May 10, 2026'
                };
                var demoVarsFR = {
                    '{nights}': '1', '{s}': '', '{checkin}': '8 mai 2026',
                    '{suggested_nights}': '2', '{s2}': 's', '{checkout}': '10 mai 2026'
                };

                function replaceVars(text, vars) {
                    for (var k in vars) { text = text.split(k).join(vars[k]); }
                    return text;
                }

                langs.forEach(function(lang) {
                    var vars = lang === 'fr' ? demoVarsFR : demoVarsEN;

                    // Suggestion preview
                    var $ps = $('.preview-suggestion[data-lang="' + lang + '"]');
                    $ps.css({
                        'background': f.suggestion_bg_color,
                        'border': '1px solid ' + f.suggestion_border_color,
                        'border-left': '4px solid ' + f.suggestion_border_color,
                        'border-radius': f.border_radius + 'px',
                        'padding': f.padding + 'px ' + (parseInt(f.padding)+5) + 'px',
                        'font-size': f.font_size + 'px'
                    });
                    $ps.html(
                        '<span style="font-size:' + f.icon_size + 'px; margin-right:8px;">' + (f[lang + '_suggestion_icon'] || '') + '</span>' +
                        '<span style="color:' + f.suggestion_text_color + '; font-weight:' + f.font_weight + ';">' + replaceVars(f[lang + '_suggestion_text'] || '', vars) + '</span>' +
                        '<div style="color:' + f.suggestion_sub_text_color + '; margin-top:5px;">' + replaceVars(f[lang + '_suggestion_sub_text'] || '', vars) + '</div>'
                    );

                    // No results preview
                    var $pn = $('.preview-no-results[data-lang="' + lang + '"]');
                    $pn.css({
                        'background': f.no_results_bg_color,
                        'border': '1px solid ' + f.no_results_border_color,
                        'border-left': '4px solid ' + f.no_results_border_color,
                        'border-radius': f.border_radius + 'px',
                        'padding': f.padding + 'px ' + (parseInt(f.padding)+5) + 'px',
                        'font-size': f.font_size + 'px'
                    });
                    $pn.html(
                        '<span style="font-size:' + f.icon_size + 'px; margin-right:8px;">' + (f[lang + '_no_results_icon'] || '') + '</span>' +
                        '<span style="color:' + f.no_results_text_color + '; font-weight:' + f.font_weight + ';">' + replaceVars(f[lang + '_no_results_text'] || '', vars) + '</span>'
                    );
                });
            }

            $('#lodgify-msg-settings-form').on('input change', 'input, textarea, select', function() {
                updatePreviews();
            });
            updatePreviews();

            // Sauvegarder
            $('#lodgify-msg-settings-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#save-msg-settings');
                var $status = $('#save-msg-status');
                $btn.prop('disabled', true).text('Saving...');

                $.post(ajaxurl, $(this).serialize(), function(response) {
                    $btn.prop('disabled', false).text('💾 Sauvegarder les paramètres');
                    if (response.success) {
                        $status.css('color', '#00a32a').text('✅ Sauvegardé !').fadeIn().delay(3000).fadeOut();
                    } else {
                        $status.css('color', '#d63638').text('❌ Erreur : ' + response.data).fadeIn().delay(3000).fadeOut();
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Sauvegarder les paramètres');
                    $status.css('color', '#d63638').text('❌ Erreur réseau').fadeIn().delay(3000).fadeOut();
                });
            });

            // Réinitialiser
            $('#reset-msg-settings').on('click', function() {
                if (!confirm('Réinitialiser tous les paramètres aux valeurs par défaut ?')) return;
                for (var key in defaults) {
                    var $field = $('[name="' + key + '"]');
                    if ($field.length) {
                        $field.val(defaults[key]);
                    }
                }
                updatePreviews();
            });
        });
        </script>
        <?php
    }
}
