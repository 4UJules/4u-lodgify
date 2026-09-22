<?php
/**
 * Système de logs pour les synchronisations Lodgify
 * Stocke uniquement les logs de la DERNIÈRE synchronisation de chaque type
 *
 * @package FourU_Moteur_Availability_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FourU_Moteur_Sync_Logger {

    /** Option keys pour stocker les logs */
    const OPT_LOG_AVAILABILITY = 'lodgify_sync_log_availability';
    const OPT_LOG_PRICES       = 'lodgify_sync_log_prices';

    /**
     * Enregistrer le log d'une synchronisation de disponibilités
     */
    public static function log_availability_sync($data) {
        $log = [
            'timestamp'    => current_time('mysql'),
            'type'         => 'availability',
            'status'       => isset($data['success']) && $data['success'] ? 'success' : 'error',
            'deleted'      => isset($data['deleted']) ? intval($data['deleted']) : 0,
            'kept'         => isset($data['kept']) ? intval($data['kept']) : 0,
            'inserted'     => isset($data['inserted']) ? intval($data['inserted']) : 0,
            'details'      => isset($data['details']) ? $data['details'] : [],
            'properties'   => [],
            'duration'     => isset($data['duration']) ? $data['duration'] : 0,
            'error'        => isset($data['error']) ? $data['error'] : '',
        ];

        // Détails par propriété si fournis
        if (isset($data['properties_log']) && is_array($data['properties_log'])) {
            $log['properties'] = $data['properties_log'];
        }

        update_option(self::OPT_LOG_AVAILABILITY, $log, false);
    }

    /**
     * Enregistrer le log d'une synchronisation de prix journaliers
     */
    public static function log_prices_sync($data) {
        $log = [
            'timestamp'        => current_time('mysql'),
            'type'             => 'prices',
            'status'           => isset($data['success']) && $data['success'] ? 'success' : 'error',
            'total_synced'     => isset($data['synced']) ? intval($data['synced']) : 0,
            'total_properties' => isset($data['properties']) ? intval($data['properties']) : 0,
            'errors'           => isset($data['errors']) ? $data['errors'] : [],
            'properties_log'   => isset($data['properties_log']) ? $data['properties_log'] : [],
            'duration'         => isset($data['duration']) ? $data['duration'] : 0,
        ];

        update_option(self::OPT_LOG_PRICES, $log, false);
    }

    /**
     * Récupérer le dernier log de synchronisation de disponibilités
     */
    public static function get_availability_log() {
        return get_option(self::OPT_LOG_AVAILABILITY, null);
    }

    /**
     * Récupérer le dernier log de synchronisation de prix
     */
    public static function get_prices_log() {
        return get_option(self::OPT_LOG_PRICES, null);
    }

    /**
     * Afficher le tableau des logs (HTML)
     */
    public static function render_logs_page() {
        $avail_log = self::get_availability_log();
        $prices_log = self::get_prices_log();
        ?>
        <div class="wrap">
            <h1>📋 Logs de synchronisation</h1>
            <p>Résultats de la <strong>dernière</strong> synchronisation de chaque type.</p>

            <!-- LOG DISPONIBILITÉS -->
            <div class="card" style="margin-top: 15px; max-width: 100%;">
                <h2>🏠 Dernière synchronisation des disponibilités</h2>
                <?php if ($avail_log): ?>
                    <table class="widefat striped" style="margin-top: 10px;">
                        <tbody>
                            <tr>
                                <td style="width: 200px;"><strong>Date</strong></td>
                                <td><?php echo esc_html($avail_log['timestamp']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut</strong></td>
                                <td>
                                    <?php if ($avail_log['status'] === 'success'): ?>
                                        <span style="color: #28a745; font-weight: bold;">✅ Succès</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: bold;">❌ Erreur</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($avail_log['duration'])): ?>
                            <tr>
                                <td><strong>Durée</strong></td>
                                <td><?php echo esc_html(round($avail_log['duration'], 1)); ?>s</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Réservations supprimées</strong></td>
                                <td><span style="color: #dc3545;"><?php echo esc_html($avail_log['deleted']); ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Réservations conservées</strong></td>
                                <td><span style="color: #28a745;"><?php echo esc_html($avail_log['kept']); ?></span></td>
                            </tr>
                            <?php if (!empty($avail_log['inserted'])): ?>
                            <tr>
                                <td><strong>Réservations insérées</strong></td>
                                <td><span style="color: #007cba;"><?php echo esc_html($avail_log['inserted']); ?></span></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($avail_log['error'])): ?>
                            <tr>
                                <td><strong>Erreur</strong></td>
                                <td style="color: #dc3545;"><?php echo esc_html($avail_log['error']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($avail_log['details'])): ?>
                        <h3 style="margin-top: 15px;">Détails par site</h3>
                        <ul style="margin-left: 20px;">
                            <?php foreach ($avail_log['details'] as $detail): ?>
                                <li><?php echo esc_html($detail); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($avail_log['properties'])): ?>
                        <h3 style="margin-top: 15px;">Détails par propriété</h3>
                        <table class="widefat striped" style="margin-top: 5px;">
                            <thead>
                                <tr>
                                    <th>Propriété (Lodgify ID)</th>
                                    <th>Nom</th>
                                    <th>Action</th>
                                    <th>Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avail_log['properties'] as $prop): ?>
                                <tr>
                                    <td><?php echo esc_html($prop['property_id'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($prop['name'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $action = $prop['action'] ?? '-';
                                        $color = '#333';
                                        if ($action === 'deleted') $color = '#dc3545';
                                        elseif ($action === 'kept') $color = '#28a745';
                                        elseif ($action === 'inserted') $color = '#007cba';
                                        ?>
                                        <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                            <?php echo esc_html($action); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($prop['detail'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <p style="color: #999; font-style: italic;">Aucune synchronisation de disponibilités effectuée.</p>
                <?php endif; ?>
            </div>

            <!-- LOG PRIX JOURNALIERS -->
            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>💰 Dernière synchronisation des prix journaliers</h2>
                <?php if ($prices_log): ?>
                    <table class="widefat striped" style="margin-top: 10px;">
                        <tbody>
                            <tr>
                                <td style="width: 200px;"><strong>Date</strong></td>
                                <td><?php echo esc_html($prices_log['timestamp']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut</strong></td>
                                <td>
                                    <?php if ($prices_log['status'] === 'success'): ?>
                                        <span style="color: #28a745; font-weight: bold;">✅ Succès</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: bold;">❌ Erreur</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($prices_log['duration'])): ?>
                            <tr>
                                <td><strong>Durée</strong></td>
                                <td><?php echo esc_html(round($prices_log['duration'], 1)); ?>s</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Propriétés traitées</strong></td>
                                <td><?php echo esc_html($prices_log['total_properties']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Prix synchronisés</strong></td>
                                <td><span style="color: #007cba; font-weight: bold;"><?php echo esc_html($prices_log['total_synced']); ?></span></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if (!empty($prices_log['errors'])): ?>
                        <h3 style="margin-top: 15px; color: #dc3545;">Erreurs</h3>
                        <ul style="margin-left: 20px; color: #dc3545;">
                            <?php foreach ($prices_log['errors'] as $err): ?>
                                <li><?php echo esc_html($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($prices_log['properties_log'])): ?>
                        <h3 style="margin-top: 15px;">Détails par propriété</h3>
                        <table class="widefat striped" style="margin-top: 5px;">
                            <thead>
                                <tr>
                                    <th>Propriété (Lodgify ID)</th>
                                    <th>Nom</th>
                                    <th>Prix synchronisés</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prices_log['properties_log'] as $prop): ?>
                                <tr>
                                    <td><?php echo esc_html($prop['property_id'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($prop['name'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($prop['count'] ?? 0); ?></td>
                                    <td>
                                        <?php if (($prop['status'] ?? '') === 'success'): ?>
                                            <span style="color: #28a745;">✅</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">❌ <?php echo esc_html($prop['error'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <p style="color: #999; font-style: italic;">Aucune synchronisation de prix effectuée.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
