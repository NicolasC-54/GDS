<?php
add_action('admin_post_gds_export_csv', 'gds_export_csv');

function gds_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé');
    }

    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gds_scans", ARRAY_A);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gds_export_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nom', 'Licence', 'Entrée', 'Sortie', 'Calibres', 'Session', 'Invité ?']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['name'],
            $row['licence_number'],
            $row['entry_time'],
            $row['exit_time'],
            $row['calibres'],
            $row['session_id'],
            $row['is_guest'] ? 'Oui' : 'Non'
        ]);
    }

    fclose($output);
    exit;
}
