<?php
// Helper functions for managing gpd_search_sessions table gdp-search-seessions.php

function gpd_get_search_session($query, $radius) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gpd_search_sessions WHERE query = %s AND radius = %d AND is_complete = 0 ORDER BY last_fetched DESC LIMIT 1",
            $query, $radius
        ),
        ARRAY_A
    );
}

function gpd_create_search_session($query, $radius, $page_token = null) {
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}gpd_search_sessions",
        [
            'query'            => $query,
            'radius'           => $radius,
            'last_page_token'  => $page_token,
            'last_fetched'     => current_time('mysql'),
            'is_complete'      => 0
        ]
    );
    return $wpdb->insert_id;
}

function gpd_update_search_session($session_id, $next_page_token, $is_complete = 0) {
    global $wpdb;
    $wpdb->update(
        "{$wpdb->prefix}gpd_search_sessions",
        [
            'last_page_token' => $next_page_token,
            'last_fetched'    => current_time('mysql'),
            'is_complete'     => $is_complete
        ],
        ['id' => $session_id]
    );
}
