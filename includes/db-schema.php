<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create the database schema
function dsd_create_db_schema() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'dsd_businesses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        place_id VARCHAR(255) NOT NULL UNIQUE,
        display_name VARCHAR(255) NOT NULL,
        formatted_address TEXT NOT NULL,
        international_phone_number VARCHAR(50),
        website_uri TEXT,
        latitude FLOAT(10, 6),
        longitude FLOAT(10, 6),
        google_maps_uri TEXT,
        rating FLOAT(3, 2),
        weekday_descriptions TEXT,
        primary_type VARCHAR(100),
        business_status VARCHAR(50),
        price_level TINYINT(1),
        editorial_summary TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}