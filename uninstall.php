<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$raw = get_option('wedding_settings');
$data = array();
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
} elseif (is_array($raw)) {
    $data = $raw;
}

if (empty($data['delete_data_on_uninstall'])) {
    return;
}

global $wpdb;
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wedding_responses');
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wedding_guests');
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wedding_pickups');
delete_option('wedding_settings');
delete_option('wedding_db_version');
