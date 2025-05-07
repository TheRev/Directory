<?php
function gpd_install_database_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $businesses_table = $wpdb->prefix . 'gpd_businesses';
    $cache_table = $wpdb->prefix . 'gpd_cache';
    $search_cache_table = $wpdb->prefix . 'gpd_search_cache'; // ✅ New Search Cache Table

    // ✅ Businesses Table (existing)
    $sql1 = "CREATE TABLE $businesses_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        place_id VARCHAR(255) NOT NULL UNIQUE,
        post_id BIGINT(20) UNSIGNED,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        destination VARCHAR(100),
        latitude DECIMAL(10, 6),
        longitude DECIMAL(10, 6),
        types TEXT,
        rating DECIMAL(2, 1),
        user_ratings_total INT,
        website TEXT,
        phone_number VARCHAR(50),
        google_maps_url TEXT,
        business_status VARCHAR(100),
        is_scraped TINYINT(1) DEFAULT 0,
        scraped_fields TEXT,
        street VARCHAR(255),
        city VARCHAR(100),
        state VARCHAR(100),
        postal_code VARCHAR(20),
        country VARCHAR(100),
        is_sponsored TINYINT(1) DEFAULT 0,
        last_synced DATETIME,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // ✅ Cache Table (existing)
    $sql2 = "CREATE TABLE $cache_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        query_hash VARCHAR(64) NOT NULL,
        page_token VARCHAR(255) DEFAULT '',
        cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        response_json LONGTEXT,
        PRIMARY KEY (id),
        KEY query_page (query_hash, page_token)
    ) $charset_collate;";

    // ✅ Search Cache Table (new)
    $sql3 = "CREATE TABLE $search_cache_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        destination VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        imported_count INT DEFAULT 0,
        pages_imported INT DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY destination (destination)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3); // ✅ Add this
}
