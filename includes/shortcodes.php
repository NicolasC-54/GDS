<?php

// ✅ [gds_scanner_interface] – Affiche l’interface principale
add_shortcode('gds_scanner_interface', function () {
    ob_start();

    if (!is_user_logged_in()) {
        echo '<p>🔒 Veuillez vous connecter pour accéder à cette interface.</p>';
        return ob_get_clean();
    }

    if (!current_user_can('gds_manage_sessions')) {
        echo '<p>⛔ Accès réservé aux encadrants.</p>';
        return ob_get_clean();
    }

    include GDS_PLUGIN_DIR . 'public/gds-interface.php';
    return ob_get_clean();
});


// ✅ [gds_session_export] – Affiche un tableau des entrées/sorties
add_shortcode('gds_session_export', function () {
    ob_start();

    if (!current_user_can('manage_options')) {
        echo '<p>🔒 Accès réservé aux administrateurs.</p>';
        return ob_get_clean();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'gds_scans';

    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY entry_time DESC LIMIT 100");

    if (empty($rows)) {
        echo '<p>Aucune donnée trouvée.</p>';
    } else {
        echo '<table style="width:100%; border-collapse: collapse;" border="1">';
        echo '<thead><tr><th>Type</th><th>Identifiant</th><th>Entrée</th><th>Sortie</th><th>Calibres</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $type = $row->is_guest ? 'Invité' : 'Adhérent';
            $id = $row->is_guest ? esc_html($row->name) : esc_html($row->licence_number);

            echo "<tr>
                <td>{$type}</td>
                <td>{$id}</td>
                <td>{$row->entry_time}</td>
                <td>{$row->exit_time}</td>
                <td>{$row->calibres}</td>
            </tr>";
        }

        echo '</tbody></table>';
    }

    return ob_get_clean();
});


// ✅ [gds_stats] – Statistiques globales
add_shortcode('gds_stats', function () {
    ob_start();

    if (!current_user_can('manage_options')) {
        echo '<p>🔒 Accès réservé aux administrateurs.</p>';
        return ob_get_clean();
    }

    global $wpdb;
    $sessions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gds_sessions");
    $scans = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gds_scans");
    $guests = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gds_scans WHERE is_guest = 1");

    echo '<div style="padding: 1em; background: #f9f9f9; border: 1px solid #ccc;">';
    echo '<h3>📊 Statistiques</h3>';
    echo '<ul>';
    echo "<li>🗓️ Nombre de séances : <strong>$sessions</strong></li>";
    echo "<li>👥 Total des participants : <strong>$scans</strong></li>";
    echo "<li>🎫 Nombre d'invités : <strong>$guests</strong></li>";
    echo '</ul>';
    echo '</div>';

    return ob_get_clean();
});
