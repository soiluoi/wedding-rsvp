<?php

class Wedding_RSVP_Activator {
    public static function activate() {
        self::create_tables();
        self::upgrade_response_columns();
        if (function_exists('get_option') && get_option(Wedding_RSVP_Settings::OPTION, null) === null) {
            Wedding_RSVP_Settings::update(Wedding_RSVP_Settings::defaults());
        }
        Wedding_RSVP_Frontend::register_rewrites();
        flush_rewrite_rules();
        update_option('wedding_db_version', WEDDING_RSVP_DB_VERSION);
        update_option('wedding_rewrite_version', Wedding_RSVP_Frontend::REWRITE_VERSION, false);
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $current = get_option('wedding_db_version');
        if ($current === WEDDING_RSVP_DB_VERSION) {
            return;
        }
        self::create_tables();
        self::upgrade_response_columns();
        if (get_option(Wedding_RSVP_Settings::OPTION, null) === null) {
            Wedding_RSVP_Settings::update(Wedding_RSVP_Settings::defaults());
        }
        update_option('wedding_db_version', WEDDING_RSVP_DB_VERSION);
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $guests = $wpdb->prefix . 'wedding_guests';
        $responses = $wpdb->prefix . 'wedding_responses';
        $pickups = $wpdb->prefix . 'wedding_pickups';

        $sql_guests = "CREATE TABLE {$guests} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(16) NOT NULL DEFAULT '',
            salutation varchar(50) NOT NULL DEFAULT '',
            name varchar(191) NOT NULL DEFAULT '',
            name_normalized varchar(191) NOT NULL DEFAULT '',
            note text NOT NULL,
            invited_count tinyint(3) unsigned NOT NULL DEFAULT 1,
            host varchar(100) NOT NULL DEFAULT '',
            group_name varchar(100) NOT NULL DEFAULT '',
            sort_order int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            pickup_id bigint(20) unsigned NULL DEFAULT NULL,
            link_sent_at datetime NULL DEFAULT NULL,
            pickup_notified_at datetime NULL DEFAULT NULL,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY name_normalized (name_normalized),
            KEY host (host),
            KEY group_name (group_name)
        ) {$charset};";

        $sql_responses = "CREATE TABLE {$responses} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            guest_id bigint(20) unsigned NOT NULL,
            attending varchar(10) NOT NULL DEFAULT '',
            ride_bus tinyint(1) NULL DEFAULT NULL,
            phone varchar(20) NULL DEFAULT NULL,
            diet_note text NULL,
            companions text NULL,
            has_companion tinyint(1) NOT NULL DEFAULT 0,
            companion_name varchar(191) NULL DEFAULT NULL,
            companion_type varchar(10) NULL DEFAULT NULL,
            companion_age tinyint(3) unsigned NULL DEFAULT NULL,
            message text NOT NULL,
            source varchar(10) NOT NULL DEFAULT 'guest',
            update_count int(11) NOT NULL DEFAULT 0,
            submitted_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY guest_id (guest_id)
        ) {$charset};";

        $sql_pickups = "CREATE TABLE {$pickups} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL DEFAULT '',
            address varchar(255) NOT NULL DEFAULT '',
            pickup_time varchar(50) NOT NULL DEFAULT '',
            note text NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) {$charset};";

        dbDelta($sql_guests);
        dbDelta($sql_responses);
        dbDelta($sql_pickups);
    }

    public static function upgrade_response_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'wedding_responses';
        $found = $wpdb->get_results('SHOW COLUMNS FROM ' . $table, ARRAY_A);
        if (!is_array($found) || !$found) {
            return;
        }
        $types = array();
        foreach ($found as $column) {
            $types[$column['Field']] = $column['Type'];
        }
        if (!isset($types['companions'])) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD companions text NULL');
        }
        if (isset($types['diet_note']) && stripos((string) $types['diet_note'], 'varchar') !== false) {
            $wpdb->query('ALTER TABLE ' . $table . ' MODIFY diet_note text NULL');
        }
    }
}
