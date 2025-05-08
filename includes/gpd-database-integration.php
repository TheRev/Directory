<?php

// ✅ Add Search Cache Table
function gpd_update_search_cache_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $search_cache_table = $wpdb->prefix . 'gpd_search_cache';

    // Update the table schema
    $sql = "CREATE TABLE $search_cache_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        query VARCHAR(255) NOT NULL,
        radius INT NOT NULL,
        limit_results INT NOT NULL,
        next_page_token VARCHAR(255) DEFAULT NULL,
        cached_results LONGTEXT NOT NULL,
        last_accessed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY search_key (query, radius, limit_results)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('init', 'gpd_update_search_cache_table');

// ✅ Helper Function: Save Search to Cache
function gpd_save_search_to_cache($query, $radius, $limit_results, $next_page_token, $cached_results) {
    global $wpdb;
    $search_cache_table = $wpdb->prefix . 'gpd_search_cache';

    $wpdb->replace($search_cache_table, [
        'query'            => $query,
        'radius'           => $radius,
        'limit_results'    => $limit_results,
        'next_page_token'  => $next_page_token,
        'cached_results'   => maybe_serialize($cached_results),
        'last_accessed'    => current_time('mysql')
    ]);
}

// ✅ Helper Function: Get Search from Cache
function gpd_get_search_from_cache($query, $radius, $limit_results) {
    global $wpdb;
    $search_cache_table = $wpdb->prefix . 'gpd_search_cache';

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $search_cache_table WHERE query = %s AND radius = %d AND limit_results = %d",
        $query,
        $radius,
        $limit_results
    ));

    if ($result) {
        $result->cached_results = maybe_unserialize($result->cached_results);
    }

    return $result;
}

// ✅ Helper Function: Update Last Accessed Timestamp
function gpd_update_last_accessed($query, $radius, $limit_results) {
    global $wpdb;
    $search_cache_table = $wpdb->prefix . 'gpd_search_cache';

    $wpdb->update($search_cache_table, [
        'last_accessed' => current_time('mysql')
    ], [
        'query'         => $query,
        'radius'        => $radius,
        'limit_results' => $limit_results
    ]);
}
