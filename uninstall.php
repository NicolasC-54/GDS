<?php
// Nettoyage des rôles (optionnel, DB non supprimée)
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

remove_role('gds_encadrant');
