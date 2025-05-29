<?php
// 🧠 Enregistrement des routes REST API du plugin GDS
add_action('rest_api_init', function () {
    register_rest_route('gds/v1', '/start-session/', [
        'methods' => 'POST',
        'callback' => 'gds_rest_start_session',
        'permission_callback' => function () {
            return current_user_can('gds_manage_sessions');
        },
    ]);

    register_rest_route('gds/v1', '/end-session/', [
        'methods' => 'POST',
        'callback' => 'gds_rest_end_session',
        'permission_callback' => function () {
            return current_user_can('gds_manage_sessions');
        },
    ]);

    register_rest_route('gds/v1', '/scan/', [
        'methods' => 'POST',
        'callback' => 'gds_rest_scan_licence',
        'permission_callback' => function () {
            return current_user_can('gds_manage_sessions');
        },
    ]);

    register_rest_route('gds/v1', '/invite/', [
        'methods' => 'POST',
        'callback' => 'gds_rest_add_invite',
        'permission_callback' => function () {
            return current_user_can('gds_manage_sessions');
        },
    ]);
    
    register_rest_route('gds/v1', '/force-reset-session/', [
    'methods' => 'POST',
    'callback' => 'gds_rest_force_reset_session',
    'permission_callback' => function () {
        return current_user_can('gds_manage_sessions');
    },
]);
    
    register_rest_route('gds/v1', '/exit-invite/', [
    'methods' => 'POST',
    'callback' => 'gds_rest_exit_invite',
    'permission_callback' => function () {
        return current_user_can('gds_manage_sessions');
    },
]);

register_rest_route('gds/v1', '/session-summary/', [
    'methods' => 'POST',
    'callback' => 'gds_rest_session_summary',
    'permission_callback' => function () {
        return current_user_can('gds_manage_sessions');
    },
]);

    
});

// Fonction Reset de session 

function gds_rest_force_reset_session(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();

    // Sélectionne toutes les sessions ouvertes
    $sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}gds_sessions WHERE encadrant_id = %d AND end_time IS NULL",
        $user_id
    ));

    if (!$sessions || count($sessions) === 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => "Aucune session active à forcer."
        ], 200);
    }

    $now = current_time('mysql');

    foreach ($sessions as $session) {
        $wpdb->update("{$wpdb->prefix}gds_sessions", [
            'end_time' => $now
        ], ['id' => $session->id]);

        // Clôturer les scans orphelins
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gds_scans SET exit_time = %s WHERE session_id = %d AND exit_time IS NULL",
            $now, $session->id
        ));
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => "✅ Session(s) clôturée(s) de force."
    ], 200);
}


// fin fonction Reset de session

// 🎬 START SESSION
function gds_rest_start_session(WP_REST_Request $request) {
    global $wpdb;

    $stand = sanitize_text_field($request->get_param('stand'));
    $user_id = get_current_user_id();

    if (!$stand) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Le stand est requis.'
        ], 400);
    }

    // Clôture toutes les anciennes sessions actives de l'encadrant
    $now = current_time('mysql');

    $old_sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}gds_sessions WHERE encadrant_id = %d AND end_time IS NULL",
        $user_id
    ));

    foreach ($old_sessions as $session) {
        $wpdb->update("{$wpdb->prefix}gds_sessions", [
            'end_time' => $now
        ], ['id' => $session->id]);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gds_scans SET exit_time = %s WHERE session_id = %d AND exit_time IS NULL",
            $now, $session->id
        ));
    }

    // Démarre une nouvelle session
    $wpdb->insert("{$wpdb->prefix}gds_sessions", [
        'encadrant_id' => $user_id,
        'stand' => $stand,
        'start_time' => $now
    ]);

    return new WP_REST_Response([
        'success' => true,
        'session_id' => $wpdb->insert_id,
        'message' => count($old_sessions)
            ? '✅ Ancienne(s) session(s) clôturée(s) automatiquement. Nouvelle session démarrée.'
            : '✅ Nouvelle session démarrée.'
    ], 200);
}



// 🛑 END SESSION
function gds_rest_end_session(WP_REST_Request $request) {
    $session_id = intval($request->get_param('session_id'));

    if (!$session_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'ID de session manquant.'
        ], 400);
    }

    global $wpdb;

    $wpdb->update("{$wpdb->prefix}gds_sessions", [
        'end_time' => current_time('mysql')
    ], ['id' => $session_id]);

    // Clôturer les scans encore actifs
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gds_scans SET exit_time = NOW() WHERE session_id = %d AND exit_time IS NULL",
        $session_id
    ));

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Session terminée.'
    ], 200);
}


// 📲 SCAN LICENCE (entrée ou sortie)
// 📲 SCAN LICENCE (entrée ou sortie)
function gds_rest_scan_licence(WP_REST_Request $request) {
    global $wpdb;

    $session_id = intval($request->get_param('session_id'));
    $licence    = sanitize_text_field($request->get_param('licence'));
    $calibres   = $request->get_param('calibres') ?? [];
    $nom        = sanitize_text_field($request->get_param('nom')); // ✅ ajout nom complet

    if (!$session_id || !$licence) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Paramètres manquants.'
        ], 400);
    }

    $table = $wpdb->prefix . 'gds_scans';

    // 🔁 Recherche d'une entrée active non sortie
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table
         WHERE session_id = %d AND licence_number = %s AND is_guest = 0 AND exit_time IS NULL
         ORDER BY entry_time DESC LIMIT 1",
        $session_id, $licence
    ));

    if ($existing) {
        // 👉 Adhérent déjà présent → marquer sortie
        $wpdb->update($table, [
            'exit_time' => current_time('mysql')
        ], ['id' => $existing->id]);

        return new WP_REST_Response([
            'success' => true,
            'message' => "👋 Sortie enregistrée pour l'adhérent $licence"
        ], 200);
    }

    // ➕ Sinon → Nouvelle entrée avec nom
    $wpdb->insert($table, [
        'session_id'     => $session_id,
        'licence_number' => $licence,
        'entry_time'     => current_time('mysql'),
        'calibres'       => implode(',', array_map('sanitize_text_field', $calibres)),
        'is_guest'       => 0,
        'name'           => $nom // ✅ Enregistrement du nom complet
    ]);

    return new WP_REST_Response([
        'success' => true,
        'message' => "✅ Entrée enregistrée pour $nom"
    ], 200);
}



// 👤 AJOUTER INVITÉ
function gds_rest_add_invite(WP_REST_Request $request) {
    global $wpdb;

    $session_id = intval($request->get_param('session_id'));
    $name = sanitize_text_field($request->get_param('name'));
    $code = sanitize_text_field($request->get_param('code'));
    $calibres = $request->get_param('calibres') ?? [];

    if (!$session_id || !$name) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Nom et session requis.'
        ], 400);
    }

    $wpdb->insert($wpdb->prefix . 'gds_scans', [
        'session_id' => $session_id,
        'name' => $name,
        'licence_number' => $code ?: null,
        'entry_time' => current_time('mysql'),
        'calibres' => implode(',', array_map('sanitize_text_field', $calibres)),
        'is_guest' => 1
    ]);

    return new WP_REST_Response([
        'success' => true,
        'message' => "👤 Invité $name ajouté"
    ], 200);
}

// sortie Invité

function gds_rest_exit_invite(WP_REST_Request $request) {
    global $wpdb;

    $session_id = intval($request->get_param('session_id'));
    $name = sanitize_text_field($request->get_param('name'));

    if (!$session_id || !$name) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Session et nom requis.'
        ], 400);
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gds_scans 
         WHERE session_id = %d AND name = %s AND is_guest = 1 AND exit_time IS NULL 
         ORDER BY entry_time DESC LIMIT 1",
        $session_id, $name
    ));

    if (!$row) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Aucune entrée active trouvée pour cet invité.'
        ], 404);
    }

    $wpdb->update("{$wpdb->prefix}gds_scans", [
        'exit_time' => current_time('mysql')
    ], ['id' => $row->id]);

    return new WP_REST_Response([
        'success' => true,
        'message' => "👋 Sortie enregistrée pour $name"
    ], 200);
}


// fonction de synthese

function gds_rest_session_summary(WP_REST_Request $request) {
    global $wpdb;

    $session_id = intval($request->get_param('session_id'));
    if (!$session_id) {
        return new WP_REST_Response(['success' => false, 'message' => 'ID session manquant'], 400);
    }

    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gds_sessions WHERE id = %d",
        $session_id
    ));

    if (!$session) {
        return new WP_REST_Response(['success' => false, 'message' => 'Session introuvable'], 404);
    }

    $scans = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gds_scans WHERE session_id = %d",
        $session_id
    ));

    $summary = [
        'start_time' => $session->start_time,
        'now' => current_time('mysql'),
        'participants' => []
    ];

    foreach ($scans as $scan) {
        $entry = strtotime($scan->entry_time);
        $exit = $scan->exit_time ? strtotime($scan->exit_time) : null;

        $summary['participants'][] = [
            'type' => $scan->is_guest ? 'invité' : 'adhérent',
            'id' => $scan->is_guest ? $scan->name : $scan->licence_number,
            'entry_time' => $scan->entry_time,
            'exit_time' => $scan->exit_time ?: null,
            'calibres' => $scan->calibres
        ];
    }

    return new WP_REST_Response([
        'success' => true,
        'summary' => $summary
    ], 200);
}


// Ajout admin sessions

add_action('rest_api_init', function () {
    register_rest_route('gds/v1', '/session-scans/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'gds_get_session_scans',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);
});

function gds_get_session_scans($data) {
    global $wpdb;
    $session_id = intval($data['id']);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT name, licence_number, is_guest, entry_time, exit_time, calibres 
         FROM {$wpdb->prefix}gds_scans 
         WHERE session_id = %d",
        $session_id
    ));

    $result = [];

    foreach ($rows as $r) {
        $result[] = [
            'type'     => $r->is_guest ? 'Invité' : 'Adhérent',
            'nom'      => esc_html($r->name ?? ''), // 🆕
            'licence'  => esc_html($r->licence_number ?? ''), // 🆕
            'entry'    => $r->entry_time ? date('H:i', strtotime($r->entry_time)) : '',
            'exit'     => $r->exit_time ? date('H:i', strtotime($r->exit_time)) : '',
            'calibres' => esc_html($r->calibres)
        ];
    }

    return rest_ensure_response($result);
}


// API de scan

add_action('rest_api_init', function () {
    register_rest_route('gds/v1', '/scan-qr/', [
        'methods' => 'POST',
        'callback' => 'gds_rest_parse_qr',
        'permission_callback' => function () {
            return current_user_can('gds_manage_sessions');
        }
    ]);
});

function gds_rest_parse_qr(WP_REST_Request $request) {
    $url = esc_url_raw($request->get_param('url'));

    if (!str_contains($url, 'itac.pro')) {
        return new WP_REST_Response(['success' => false, 'message' => 'URL invalide'], 400);
    }

    $res = wp_remote_get($url);
    if (is_wp_error($res)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Erreur réseau'], 500);
    }

    $body = wp_remote_retrieve_body($res);

    preg_match('/Nom\s*:\s*<\/strong>\s*(.*?)</i', $body, $nom);
    preg_match('/Prénom\s*:\s*<\/strong>\s*(.*?)</i', $body, $prenom);
    preg_match('/N° licence\s*:\s*<\/strong>\s*(.*?)</i', $body, $licence);
    preg_match('/(En cours de validité|Licencié non valide)/i', $body, $validite);

    return [
        'success' => true,
        'nom' => trim($nom[1] ?? ''),
        'prenom' => trim($prenom[1] ?? ''),
        'licence' => trim($licence[1] ?? ''),
        'statut' => trim($validite[1] ?? 'Inconnu')
    ];
}

// API Route vers ITAC

add_action('rest_api_init', function () {
    register_rest_route('gds/v1', '/itac-parse/', [
        'methods' => 'POST',
        'callback' => 'gds_parse_itac_url',
        'permission_callback' => '__return_true',
    ]);
});

// Fonction de parsing ITAC 

function gds_parse_itac_url( WP_REST_Request $request ) {
    $url = $request->get_param('url');

    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'URL invalide ou absente.'
        ], 400);
    }

    // ✅ maintenant safe
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Erreur lors de la récupération de la page ITAC.'
        ], 400);
    }

    $html = wp_remote_retrieve_body($response);
    if (!$html || strpos($html, 'lb_res_nom') === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Contenu inattendu (élément non trouvé)',
        ], 400);
    }

    if (!class_exists('DOMDocument')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'DOMDocument n’est pas disponible sur ce serveur.',
        ], 500);
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $xpath = new DOMXPath($doc);

    $getVal = function($id) use ($xpath) {
        $node = $xpath->query("//*[@id='$id']");
        return ($node->length > 0) ? trim($node->item(0)->textContent) : '';
    };

    $data = [
        'licence' => $getVal('lb_res_licence'),
        'nom'     => $getVal('lb_res_nom'),
        'prenom'  => $getVal('lb_res_prenom'),
        'statut'  => $getVal('lb_resultat'),
    ];

    return new WP_REST_Response([
        'success' => true,
        'data' => $data,
    ]);
}
