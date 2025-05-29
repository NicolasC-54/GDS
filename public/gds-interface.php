<?php

// Récupération du nom de l'encadrant

$current_user = wp_get_current_user();
$user_name = $current_user->display_name;
$user_id = $current_user->ID;


// ✅ Récupération des calibres
$raw = get_option('gds_calibres', '');
if (is_string($raw)) {
    $calibres = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));
} else {
    $calibres = (array) $raw;
}

// ✅ Récupération des stands
//$stands = explode(',', get_option('gds_stands_list', 'Stand 10m, Stand 25m'));
//$stands = array_map('trim', $stands);

// ✅ Récupération des stands (array friendly)
$raw = get_option('gds_stands_list', []);
$stands = is_array($raw)
    ? array_filter(array_map('trim', $raw))
    : array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));



?>

<!-- Nouvelle interface -->
<div class="gds-wrapper">
    <h2>👋 Bonjour <?= esc_html($user_name); ?></h2>

  <!-- 🟢 ÉTAPE 1 : Démarrage -->
    <div id="gds-session-start">
        <p><strong>Débuter une nouvelle séance :</strong></p>
        <select id="gds-stand">
            <?php foreach ($stands as $stand): ?>
                <option value="<?= esc_attr($stand); ?>"><?= esc_html($stand); ?></option>
            <?php endforeach; ?>
        </select>
        <button id="gds-start-session" class="button button-primary">🎬 Ouvrir la séance</button>
    </div>

    <!-- 🔵 ÉTAPE 2 : Interface active -->
    <div id="gds-scanner-section" style="display:none; margin-top: 20px;">
        <p id="gds-header-session" style="font-style: italic; color: #555;"></p>

        <div style="margin-bottom: 15px;">
            <input type="text" id="gds-scan-input" placeholder="Scanner ou entrer le n° de licence" />
            <button id="gds-scan-qr" class="button">📷 Scanner une licence</button>
            <button id="gds-show-invite" class="button">➕ Ajouter un invité</button>
        </div>

        <div id="gds-qr-reader" style="display:none; max-width:300px; margin-top:10px;"></div>

        <!-- 👥 Liste des présents -->
        <div id="gds-live-presence" style="margin-top:30px; display:none;">
            <h3>👥 Participants présents (<span id="gds-live-count">0</span>)</h3>
            <table id="gds-live-table" class="widefat striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Nom</th>
                        <th>Identifiant</th>
                        <th>Entrée</th>
                        <th>Sortie</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <button id="gds-end-session" class="button button-danger" style="margin-top: 20px; display:none;">🛑 Clôturer la séance</button>
    </div>
</div>


<!-- 🔐 Modale générique -->
<div id="gds-modal-overlay" style="display:none;">
    <div id="gds-modal-box">
        <div id="gds-modal-content"></div>
        <div style="margin-top: 20px; text-align:right;">
            <button id="gds-modal-cancel" class="button">❌ Annuler</button>
            <button id="gds-modal-confirm" class="button button-primary">✔️ Valider l’entrée</button>
        </div>
    </div>
</div>

<!-- 🔫 Styles rapides -->
<style>
#gds-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex; justify-content: center; align-items: center;
    z-index: 9999;
}
#gds-modal-box {
    background: #fff;
    padding: 20px;
    max-width: 500px;
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0,0,0,0.3);
}
#gds-modal-box input {
    width: 100%;
    padding: 6px;
    margin-bottom: 10px;
}
.gds-calibre-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.gds-calibre-btn {
    background: #eee;
    border: 1px solid #ccc;
    padding: 6px 10px;
    cursor: pointer;
}
.gds-calibre-btn.active {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}
</style>

<?php
// 👇 Ajout de variables JS globales pour JS
wp_enqueue_script('gds-scanner', plugin_dir_url(__FILE__) . 'assets/gds-scanner.js', ['jquery'], '1.0', true);
wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js', [], null, true);
wp_localize_script('gds-scanner', 'gds_user_name', $user_name);
wp_localize_script('gds-scanner', 'gds_rest_nonce', wp_create_nonce('wp_rest'));
?>