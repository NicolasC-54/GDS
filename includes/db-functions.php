<?php
function gds_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sessions = $wpdb->prefix . 'gds_sessions';
    $scans = $wpdb->prefix . 'gds_scans';
    $calibres = $wpdb->prefix . 'gds_calibres';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "
        CREATE TABLE $sessions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            encadrant_id BIGINT NOT NULL,
            stand VARCHAR(100) NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME DEFAULT NULL
        ) $charset_collate;

        CREATE TABLE $scans (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id BIGINT NOT NULL,
            licence_number VARCHAR(50),
            name VARCHAR(100),
            entry_time DATETIME,
            exit_time DATETIME,
            calibres TEXT,
            is_guest BOOLEAN DEFAULT FALSE
        ) $charset_collate;

        CREATE TABLE $calibres (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(100) NOT NULL
        ) $charset_collate;
    ";

    dbDelta($sql);
}
