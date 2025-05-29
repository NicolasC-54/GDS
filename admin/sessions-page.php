<?php
// Sécurité : empêche accès direct
if (!defined('ABSPATH')) {
    exit;
}

function gds_render_sessions_page() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // 🔢 Pagination vars
    $items_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $items_per_page;
    
      // 🔎 Filtres actifs
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$filter_date = isset($_GET['session_date']) ? sanitize_text_field($_GET['session_date']) : '';
$filter_stand = isset($_GET['stand']) ? sanitize_text_field($_GET['stand']) : '';


    // 📦 Nombre total de sessions
$where = "WHERE 1=1";

// 📅 date de session (filtre sur jour)
if ($filter_date) {
    $where .= $wpdb->prepare(" AND DATE(start_time) = %s", $filter_date);
}

// 🏟️ stand
if ($filter_stand) {
    $where .= $wpdb->prepare(" AND stand = %s", $filter_stand);
}

// 🔤 recherche encadrant
if ($search) {
    $where .= $wpdb->prepare(" AND (u.display_name LIKE %s)", '%' . $wpdb->esc_like($search) . '%');
}

// 🔢 total filtré
$total_items = $wpdb->get_var("
    SELECT COUNT(*) FROM {$prefix}gds_sessions s
    LEFT JOIN {$prefix}users u ON s.encadrant_id = u.ID
    $where
");

    // 🧾 Récupère les sessions paginées
$sessions = $wpdb->get_results($wpdb->prepare("
    SELECT s.*, u.display_name AS encadrant
    FROM {$prefix}gds_sessions s
    LEFT JOIN {$prefix}users u ON s.encadrant_id = u.ID
    $where
    ORDER BY s.start_time DESC
    LIMIT %d OFFSET %d
", $items_per_page, $offset));



        echo '<div class="wrap"><h1>📋 Liste des séances</h1>';
        
      

// 🏟️ Liste des stands auto

$raw_stands = get_option('gds_stands_list', 'Stand 25m, Stand 50m');
if (is_array($raw_stands)) {
    $stand_options = $raw_stands;
} else {
    $stand_options = array_map('trim', explode(',', $raw_stands));
}

echo '<form method="GET" style="margin-bottom: 1em; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">';
echo '<input type="hidden" name="page" value="gds-sessions" />';

echo '<label>🧑‍🏫 Encadrant : <input type="text" name="search" value="' . esc_attr($search) . '" placeholder="Nom de l\'encadrant" /></label>';

echo '<label>📅 Date session : <input type="date" name="session_date" value="' . esc_attr($filter_date) . '" /></label>';

echo '<label>🏟️ Stand : <select name="stand"><option value="">Tous</option>';
foreach ($stand_options as $opt) {
    $selected = ($opt === $filter_stand) ? 'selected' : '';
    echo "<option value='" . esc_attr($opt) . "' $selected>$opt</option>";
}
echo '</select></label>';

echo '<input type="submit" class="button button-primary" value="Rechercher" />';
echo '<a href="' . esc_url(admin_url('admin.php?page=gds-sessions')) . '" class="button">Réinitialiser</a>';
echo '</form>';

        
        
        

    // ✅ Sélecteur "lignes par page"
    echo '<form method="GET" style="margin-bottom: 10px;">';
    echo '<input type="hidden" name="page" value="gds-sessions">';
    echo 'Afficher ';
    echo '<select name="per_page" onchange="this.form.submit()">';
    foreach ([10, 20, 50, 100] as $n) {
        $selected = ($items_per_page == $n) ? 'selected' : '';
        echo "<option value='$n' $selected>$n</option>";
    }
    echo '</select> lignes par page';
    echo '</form>';

    echo '<table class="widefat striped" id="gds-sessions-table">';
    echo '<thead><tr>
        <th>Session</th>
        <th>Date</th>
        <th>Stand</th>
        <th>Encadrant</th>
        <th>Début</th>
        <th>Fin</th>
        <th>Durée</th>
        <th>👥 Présents</th>
        <th>Détails</th>
    </tr></thead><tbody>';

    foreach ($sessions as $s) {
        $start = new DateTime($s->start_time);
        $end = $s->end_time ? new DateTime($s->end_time) : null;
        $duration = $end ? $start->diff($end)->format('%Hh %Im') : '—';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}gds_scans WHERE session_id = %d",
            $s->id
        ));

        echo '<tr>';
        echo '<td>#' . esc_html($s->id) . '</td>';
        echo '<td>' . $start->format('d/m/Y') . '</td>';
        echo '<td>' . esc_html($s->stand) . '</td>';
        echo '<td>' . esc_html($s->encadrant) . '</td>';
        echo '<td>' . $start->format('H:i') . '</td>';
        echo '<td>' . ($end ? $end->format('H:i') : '—') . '</td>';
        echo '<td>' . esc_html($duration) . '</td>';
        echo '<td>' . esc_html($count) . '</td>';
        echo '<td><button class="button toggle-details" data-session="' . esc_attr($s->id) . '">👁️ Voir</button></td>';
        echo '</tr>';

        echo '<tr class="session-details" id="details-' . esc_attr($s->id) . '" style="display:none;">
                <td colspan="9">
                    <div class="gds-session-loader">⏳ Chargement…</div>
                </td>
              </tr>';
    }

     echo '</tbody></table></div>'; // 👈 fermeture du tableau et conteneur
     
     // Pagination
     
         $total_pages = ceil($total_items / $items_per_page);
$base_url = remove_query_arg(['paged']);
$base_url = add_query_arg('per_page', $items_per_page, $base_url);

if ($total_pages > 1) {
    echo '<div class="tablenav-pages" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">';

    // ⬅️ Précédent
    if ($paged > 1) {
        $prev_url = add_query_arg('paged', $paged - 1, $base_url);
        echo '<a class="button" href="' . esc_url($prev_url) . '">⬅️ Précédent</a>';
    }

    // 🔢 Pages intelligentes
    for ($i = 1; $i <= $total_pages; $i++) {
        if (
            $i == 1 || $i == $total_pages || abs($i - $paged) <= 2
        ) {
            $url = add_query_arg('paged', $i, $base_url);
            $class = ($i == $paged) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . esc_url($url) . '">' . $i . '</a>';
        } elseif (
            abs($i - $paged) == 3 ||
            ($i == 2 && $paged > 4) ||
            ($i == $total_pages - 1 && $paged < $total_pages - 3)
        ) {
            echo '<span style="padding:0 6px;">…</span>';
        }
    }

    // ➡️ Suivant
    if ($paged < $total_pages) {
        $next_url = add_query_arg('paged', $paged + 1, $base_url);
        echo '<a class="button" href="' . esc_url($next_url) . '">Suivant ➡️</a>';
    }

    echo '</div>';
}



    // 🔽 Injecte le JS juste ici
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toggle-details').forEach(btn => {
            btn.addEventListener('click', function () {
                const sessionId = this.dataset.session;
                const row = document.getElementById('details-' + sessionId);

                if (row.style.display === 'none') {
                    row.style.display = '';
                    row.querySelector('.gds-session-loader').innerHTML = '⏳ Chargement…';

                    fetch('<?php echo esc_url(rest_url('gds/v1/session-scans/')); ?>' + sessionId + '?_wpnonce=<?php echo wp_create_nonce('wp_rest'); ?>')
                        .then(res => res.json())
                        .then(data => {
let html = '<table class="widefat"><thead><tr><th>Type</th><th>Nom</th><th>Licence</th><th>Entrée</th><th>Sortie</th><th>Calibres</th></tr></thead><tbody>';
data.forEach(p => {
    html += '<tr><td>' + p.type + '</td><td>' + p.nom + '</td><td>' + p.licence + '</td><td>' + p.entry + '</td><td>' + (p.exit || '-') + '</td><td>' + p.calibres + '</td></tr>';
});

                            html += '</tbody></table>';
                            row.querySelector('.gds-session-loader').innerHTML = html;
                        });
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    </script>
    <?php
} // 👈 fin de la fonction
