<?php
function gpd_install_database_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // ✅ Businesses Table (existing)
    $businesses_table = $wpdb->prefix . 'gpd_businesses';
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

    // ✅ Consolidated Cache Table
    $cache_table = $wpdb->prefix . 'gpd_cache';
    $sql2 = "CREATE TABLE $cache_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        query_hash VARCHAR(64) NOT NULL, -- Unique hash for the search query
        destination VARCHAR(100) DEFAULT NULL, -- Search destination or query identifier
        page_token VARCHAR(255) DEFAULT '', -- Token for paginated results
        cached_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- When the data was cached
        place_id VARCHAR(255) NOT NULL, -- Unique ID of the place
        name VARCHAR(255) NOT NULL, -- Place name
        address TEXT, -- Full address
        latitude DECIMAL(10, 6), -- Latitude
        longitude DECIMAL(10, 6), -- Longitude
        types TEXT, -- Place types (e.g., restaurant, store)
        rating DECIMAL(2, 1), -- Average rating
        user_ratings_total INT, -- Number of user ratings
        phone_number VARCHAR(50), -- Phone number
        website TEXT, -- Website URL
        google_maps_url TEXT, -- Google Maps URL
        imported_count INT DEFAULT 0, -- Number of businesses imported for this search
        pages_imported INT DEFAULT 0, -- Number of pages imported
        PRIMARY KEY (id),
        UNIQUE KEY query_place (query_hash, place_id), -- Prevent duplicate entries for the same query and place
        KEY idx_page_token (page_token)
    ) $charset_collate;";

    // ✅ Run the SQL to create/update the tables
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1); // Create or update the businesses table
    dbDelta($sql2); // Create or update the consolidated cache table
}
