<?php
// 📍 Fonction de rendu de la page "Réglages"
function gds_settings_page() {
    ?>
    <div class="wrap">
        <h1>⚙️ Réglages du plugin GDS</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gds_settings_group');
            do_settings_sections('gds-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// 🔧 Enregistrement des options et sections
function gds_register_settings() {
    register_setting('gds_settings_group', 'gds_api_url');
    register_setting('gds_settings_group', 'gds_api_key');
    register_setting('gds_settings_group', 'gds_calibres_list');
    register_setting('gds_settings_group', 'gds_stands_list');

    add_settings_section('gds_main', 'Paramètres Généraux', null, 'gds-settings');

    add_settings_field('gds_api_url', 'URL de validation API', 'gds_api_url_field', 'gds-settings', 'gds_main');
    add_settings_field('gds_api_key', 'Clé API', 'gds_api_key_field', 'gds-settings', 'gds_main');
    add_settings_field('gds_calibres_list', 'Calibres disponibles', 'gds_calibres_field', 'gds-settings', 'gds_main');
    add_settings_field('gds_stands_list', 'Stands disponibles', 'gds_stands_field', 'gds-settings', 'gds_main');
}
add_action('admin_init', 'gds_register_settings');

// 🔑 Champs simples
function gds_api_url_field() {
    echo '<input type="text" name="gds_api_url" value="' . esc_attr(get_option('gds_api_url')) . '" class="regular-text" />';
}

function gds_api_key_field() {
    echo '<input type="text" name="gds_api_key" value="' . esc_attr(get_option('gds_api_key')) . '" class="regular-text" />';
}

// 🎯 Calibres dynamiques
function gds_calibres_field() {
    $raw = get_option('gds_calibres_list', []);
    $calibres = is_array($raw) ? $raw : explode(',', $raw);
    if (empty($calibres)) $calibres = [''];

    echo '<div id="gds-calibres-wrapper">';
    foreach ($calibres as $calibre) {
        echo '<div class="gds-calibre-row">
                <input type="text" name="gds_calibres_list[]" value="' . esc_attr($calibre) . '" placeholder="ex: 9mm">
                <button type="button" class="button gds-remove-calibre">❌</button>
              </div>';
    }
    echo '</div>';
    echo '<p><button type="button" class="button" id="gds-add-calibre">➕ Ajouter un calibre</button></p>';
}

// 🏟️ Stands dynamiques
function gds_stands_field() {
    $raw = get_option('gds_stands_list', []);
    $stands = is_array($raw) ? $raw : explode(',', $raw);
    if (empty($stands)) $stands = [''];

    echo '<div id="gds-stands-wrapper">';
    foreach ($stands as $stand) {
        echo '<div class="gds-stand-row">
                <input type="text" name="gds_stands_list[]" value="' . esc_attr($stand) . '" placeholder="ex: Stand 25m">
                <button type="button" class="button gds-remove-stand">❌</button>
              </div>';
    }
    echo '</div>';
    echo '<p><button type="button" class="button" id="gds-add-stand">➕ Ajouter un stand</button></p>';
}

// 🧾 Historique (page admin)

function gds_render_history_page() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // 🔍 Filtres
    $q            = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $session_from = isset($_GET['session_from']) ? sanitize_text_field($_GET['session_from']) : '';
    $stand        = isset($_GET['stand']) ? sanitize_text_field($_GET['stand']) : '';

    // 🔄 Pagination
    $items_per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $items_per_page;

    // 🔍 Sessions filtrées
    $session_ids = [];
    if ($session_from || $stand) {
        $session_sql = "SELECT id FROM {$prefix}gds_sessions WHERE 1=1";
        $session_params = [];

        if ($session_from) {
            $session_sql .= " AND DATE(start_time) = %s";
            $session_params[] = $session_from;
        }

        if ($stand) {
            $session_sql .= " AND stand LIKE %s";
            $session_params[] = '%' . $wpdb->esc_like($stand) . '%';
        }

        $session_ids = $wpdb->get_col($wpdb->prepare($session_sql, ...$session_params));
    }

    // 🔧 Base de la requête
    $base_sql = "FROM {$prefix}gds_scans AS scans
                 LEFT JOIN {$prefix}gds_sessions AS sessions ON scans.session_id = sessions.id
                 WHERE 1=1";
    $params = [];

    if ($q) {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $base_sql .= " AND (licence_number LIKE %s OR name LIKE %s)";
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($session_ids)) {
        $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
        $base_sql .= " AND scans.session_id IN ($placeholders)";
        $params = array_merge($params, $session_ids);
    }

    // 🧮 Total count
    $count_sql = "SELECT COUNT(*) " . $base_sql;
    $total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));

    // 🔎 Résultats paginés
    $query_sql = "SELECT scans.*, sessions.stand, sessions.start_time " . $base_sql . " 
                  ORDER BY scans.entry_time DESC LIMIT %d OFFSET %d";
    $params[] = $items_per_page;
    $params[] = $offset;

    $scans = $wpdb->get_results($wpdb->prepare($query_sql, ...$params));

    // 📋 Interface
    echo '<div class="wrap"><h1>📜 Historique des enregistrements</h1>';
    echo '<form method="GET" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="gds-history" />';

    echo '<div style="display: flex; gap: 20px; flex-wrap: wrap;">';

    echo '<div><label>Licence / Nom</label><br>';
    echo '<input type="text" name="q" value="' . esc_attr($q) . '" /></div>';

    echo '<div><label>Date de session</label><br>';
    echo '<input type="date" name="session_from" value="' . esc_attr($session_from) . '" /></div>';

    echo '<div><label>Stand</label><br>';
    echo '<select name="stand"><option value="">-- Tous --</option>';
    $stands = get_option('gds_stands_list', []);
    if (is_string($stands)) $stands = explode(',', $stands);
    foreach ($stands as $s) {
        $s = trim($s);
        $sel = ($stand === $s) ? 'selected' : '';
        echo "<option value='" . esc_attr($s) . "' $sel>" . esc_html($s) . "</option>";
    }
    echo '</select></div></div>';

    echo '<div style="margin-top: 10px; display: flex; gap: 10px;">';
    submit_button('🔎 Rechercher', 'primary', '', false);
    echo '<a href="' . admin_url('admin.php?page=gds-history') . '" class="button button-secondary">↩️ Réinitialiser</a>';
    echo '</div>';
    echo '</form>';

    if (empty($scans)) {
        echo '<div class="notice notice-warning"><p>❌ Aucun résultat ne correspond à vos critères.</p></div>';
        return;
    }

    echo '<p><strong>📦 Résultats trouvés :</strong> ' . esc_html($total_items) . '</p>';

    echo '<table class="widefat striped" id="gds-history-table">';
    echo '<thead><tr>
        <th>Session</th>
        <th>Stand</th>
        <th>Nom</th>
        <th>Licence</th>
        <th>Entrée</th>
        <th>Sortie</th>
        <th>Durée</th>
        <th>Calibres</th>
        <th>Invité ?</th>
    </tr></thead><tbody>';

    foreach ($scans as $scan) {
        $session_date = $scan->start_time ? date('d/m/Y', strtotime($scan->start_time)) : '-';
        $entry_time = $scan->entry_time ? date('H:i', strtotime($scan->entry_time)) : '-';
        $exit_time = $scan->exit_time ? date('H:i', strtotime($scan->exit_time)) : '-';

        $duration = '-';
        if ($scan->entry_time && $scan->exit_time) {
            try {
                $start = new DateTime($scan->entry_time);
                $end   = new DateTime($scan->exit_time);
                $interval = $start->diff($end);
                $duration = $interval->format('%Hh %Im');
            } catch (Exception $e) {
                $duration = '⛔️';
            }
        }

        echo '<tr>';
        echo '<td>#' . esc_html($scan->session_id) . ' - ' . esc_html($session_date) . '</td>';
        echo '<td>' . esc_html($scan->stand ?? '-') . '</td>';
        echo '<td>' . esc_html($scan->name) . '</td>';
        echo '<td>' . esc_html($scan->licence_number) . '</td>';
        echo '<td>' . esc_html($entry_time) . '</td>';
        echo '<td>' . esc_html($exit_time) . '</td>';
        echo '<td>' . esc_html($duration) . '</td>';
        echo '<td>' . esc_html($scan->calibres) . '</td>';
        echo '<td>' . ($scan->is_guest ? '✅' : '—') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // 📄 Export CSV
    echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=gds_export_csv')) . '" class="button button-primary">📁 Exporter en CSV</a></p>';

    // 🔢 Pagination
$total_pages = ceil($total_items / $items_per_page);
$base_url = remove_query_arg('paged');

if ($total_pages > 1) {
    echo '<div class="tablenav-pages" style="margin-top: 15px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">';

    // ⬅️ Précédent
    if ($paged > 1) {
        $prev_url = add_query_arg('paged', $paged - 1, $base_url);
        echo '<a class="button" href="' . esc_url($prev_url) . '">⬅️ Précédent</a>';
    }

    // 🔢 Numérotation intelligente
    for ($i = 1; $i <= $total_pages; $i++) {
        if (
            $i == 1 || $i == $total_pages || // always show first & last
            abs($i - $paged) <= 2            // current page ±2
        ) {
            $url = add_query_arg('paged', $i, $base_url);
            $class = ($i == $paged) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . esc_url($url) . '">' . $i . '</a>';
        } elseif (
            abs($i - $paged) == 3 ||
            ($i == 2 && $paged > 4) ||
            ($i == $total_pages - 1 && $paged < $total_pages - 3)
        ) {
            echo '<span style="padding:0 5px;">…</span>';
        }
    }

    // ➡️ Suivant
    if ($paged < $total_pages) {
        $next_url = add_query_arg('paged', $paged + 1, $base_url);
        echo '<a class="button" href="' . esc_url($next_url) . '">Suivant ➡️</a>';
    }

    echo '</div>';
}


    echo '</div>'; // end .wrap
}

// fin page admin Historique




// 📌 JavaScript + CSS admin
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_gds-settings') {
        ?>
        <style>
            .gds-calibre-row, .gds-stand-row {
                display: flex;
                gap: 10px;
                margin-bottom: 8px;
            }
            .gds-calibre-row input,
            .gds-stand-row input {
                width: 250px;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const calibreWrapper = document.getElementById('gds-calibres-wrapper');
                const addCalibreBtn = document.getElementById('gds-add-calibre');
                if (addCalibreBtn && calibreWrapper) {
                    addCalibreBtn.addEventListener('click', () => {
                        const div = document.createElement('div');
                        div.classList.add('gds-calibre-row');
                        div.innerHTML = `<input type="text" name="gds_calibres_list[]" placeholder="ex: 9mm">
                                         <button type="button" class="button gds-remove-calibre">❌</button>`;
                        calibreWrapper.appendChild(div);
                    });
                    calibreWrapper.addEventListener('click', e => {
                        if (e.target.classList.contains('gds-remove-calibre')) {
                            e.target.closest('.gds-calibre-row').remove();
                        }
                    });
                }

                const standWrapper = document.getElementById('gds-stands-wrapper');
                const addStandBtn = document.getElementById('gds-add-stand');
                if (addStandBtn && standWrapper) {
                    addStandBtn.addEventListener('click', () => {
                        const div = document.createElement('div');
                        div.classList.add('gds-stand-row');
                        div.innerHTML = `<input type="text" name="gds_stands_list[]" placeholder="ex: Stand 25m">
                                         <button type="button" class="button gds-remove-stand">❌</button>`;
                        standWrapper.appendChild(div);
                    });
                    standWrapper.addEventListener('click', e => {
                        if (e.target.classList.contains('gds-remove-stand')) {
                            e.target.closest('.gds-stand-row').remove();
                        }
                    });
                }
            });
        </script>
        <?php
    }
});

add_action('admin_enqueue_scripts', 'gds_enqueue_admin_scripts');
function gds_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_gds-plugin') return;

    wp_enqueue_script(
        'gds-admin-js',
        plugin_dir_url(__FILE__) . 'js/gds-admin.js',
        [],
        '1.0',
        true
    );
}

