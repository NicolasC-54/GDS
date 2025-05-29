<?php
/*
Plugin Name: Gestion de Séances (GDS)
Description: Plugin pour gérer les séances de présence et de tir avec scan de licences, validation, calibres et export.
Version: 1.0
Author: Nicolas C. 🥷
*/

if (!defined('ABSPATH')) {
    exit;
}

define('GDS_VERSION', '1.0');
define('GDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 🔁 Inclusions
 */
require_once GDS_PLUGIN_DIR . 'includes/db-functions.php';
require_once GDS_PLUGIN_DIR . 'includes/session-handler.php';
require_once GDS_PLUGIN_DIR . 'includes/shortcodes.php';
require_once GDS_PLUGIN_DIR . 'admin/settings-page.php';
require_once GDS_PLUGIN_DIR . 'admin/sessions-page.php';


// REST endpoints (ajoute plus tard : scan-handler.php, export-handler.php...)

/**
 * 🚀 Activation / désactivation
 */
register_activation_hook(__FILE__, 'gds_install_plugin');
register_deactivation_hook(__FILE__, 'gds_uninstall_plugin');

function gds_install_plugin() {
    gds_create_tables();
    gds_create_roles();
}

function gds_uninstall_plugin() {
    remove_role('gds_encadrant');
}

function gds_create_roles() {
    add_role('gds_encadrant', 'Encadrant GDS', [
        'read' => true,
        'edit_posts' => false,
        'gds_manage_sessions' => true
    ]);
}

/**
 * ⚙️ Admin settings
 */
add_action('admin_init', 'gds_register_settings');


/**
 * 🧩 Menu admin GDS + sous-menus
 */
add_action('admin_menu', 'gds_admin_menu');

function gds_admin_menu() {
    // Menu principal
    add_menu_page(
        'Gestion de Séances',
        'GDS',
        'manage_options',
        'gds-plugin',
        'gds_settings_page',
        'dashicons-clipboard',
        60
    );

    // Sous-menu : Réglages
    add_submenu_page(
        'gds-plugin',
        'Réglages GDS',
        'Réglages',
        'manage_options',
        'gds-plugin',
        'gds_settings_page'
    );
    

    add_submenu_page(
        'gds-plugin', // 🔁 Slug de ta page principale
        'Sessions', // Titre <title>
        'Sessions',             // Label du menu
        'manage_options',
        'gds-sessions',            // Slug de la sous-page
        'gds_render_sessions_page' // 🧠 Fonction d'affichage
    );
    
    add_submenu_page(
        'gds-plugin', // 🔁 Slug de ta page principale
        'Historique des enregistrements', // Titre <title>
        'Enregistrements',             // Label du menu
        'manage_options',
        'gds-history',            // Slug de la sous-page
        'gds_render_history_page' // 🧠 Fonction d'affichage
    );

    // Sous-menu : Exports
    add_submenu_page(
        'gds-plugin',
        'Exports GDS',
        'Exports',
        'manage_options',
        'gds-exports',
        'gds_exports_page'
    );
}



/**
 * 📤 Page Export GDS (placeholder)
 */
function gds_exports_page() {
    ?>
    <div class="wrap">
        <h1>📤 Export des séances</h1>
        <p>Fonctionnalité à venir : export des présences, filtrage, téléchargement CSV...</p>
    </div>
    <?php
}

/**
 * 📦 Assets publics (CSS/JS)
 */
add_action('wp_enqueue_scripts', 'gds_enqueue_assets');
function gds_enqueue_assets() {
    if (!is_user_logged_in()) return;
    
     // 🖼️ CSS + JS du plugin
    wp_enqueue_style('gds-style', GDS_PLUGIN_URL . 'assets/css/gds-style.css', [], GDS_VERSION);
    wp_enqueue_script('gds-script', GDS_PLUGIN_URL . 'public/js/gds-scanner.js', ['jquery'], GDS_VERSION, true);

    // 📷 Ajout du QR code en local
    wp_enqueue_script('html5-qrcode', plugin_dir_url(__FILE__) . 'public/js/html5-qrcode.min.js', [], null, true);
    
    // 🧠 Inject nonce REST dans le script principal
    wp_add_inline_script('gds-script', 'const gds_rest_nonce = "' . wp_create_nonce('wp_rest') . '";', 'before');

}
