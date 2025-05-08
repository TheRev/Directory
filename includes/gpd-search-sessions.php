<?php
// Helper functions for managing gpd_search_sessions table

function gpd_get_search_session($query, $radius, $destination = null) {
    global $wpdb;
    if ($destination !== null && $destination !== '') {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gpd_search_sessions WHERE query = %s AND radius = %d AND destination = %s AND is_complete = 0 ORDER BY last_fetched DESC LIMIT 1",
                $query, $radius, $destination
            ),
            ARRAY_A
        );
    } else {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gpd_search_sessions WHERE query = %s AND radius = %d AND (destination = '' OR destination IS NULL) AND is_complete = 0 ORDER BY last_fetched DESC LIMIT 1",
                $query, $radius
            ),
            ARRAY_A
        );
    }
}

function gpd_create_search_session($query, $radius, $destination, $page_token = null) {
    global $wpdb;
    // Always store an empty string if null, to avoid SQL NULLs
    $page_token = $page_token !== null ? $page_token : '';
    $result = $wpdb->insert(
        "{$wpdb->prefix}gpd_search_sessions",
        [
            'query'            => $query,
            'radius'           => $radius,
            'destination'      => $destination, // Save the resolved destination
            'last_page_token'  => $page_token,
            'last_fetched'     => current_time('mysql'),
            'is_complete'      => 0
        ]
    );
    if ($result === false) {
        error_log("gpd_create_search_session: DB insert failed! Error: " . $wpdb->last_error);
    } else {
        error_log("gpd_create_search_session: DB insert result: $result, page_token: \"$page_token\", destination: \"$destination\"");
    }
    return $wpdb->insert_id;
}

function gpd_update_search_session($session_id, $next_page_token, $is_complete = 0) {
    global $wpdb;
    // Always store an empty string if null, to avoid SQL NULLs
    $next_page_token = $next_page_token !== null ? $next_page_token : '';
    error_log("gpd_update_search_session: session_id=$session_id, token=\"$next_page_token\", complete=$is_complete");
    $result = $wpdb->update(
        "{$wpdb->prefix}gpd_search_sessions",
        [
            'last_page_token' => $next_page_token,
            'last_fetched'    => current_time('mysql'),
            'is_complete'     => $is_complete
        ],
        ['id' => $session_id]
    );
    if ($result === false) {
        error_log("gpd_update_search_session: DB update failed! Error: " . $wpdb->last_error);
    } else {
        error_log("gpd_update_search_session: DB update result: $result");
    }
}

/**
 * Count the number of cached shops for a given destination.
 * @param string $destination
 * @return int
 */
function gpd_count_cached_shops_by_destination($destination) {
    global $wpdb;
    // Assumes your gpd_cache table has a 'destination' column.
    // If not, you may need to join via session or adjust as needed.
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gpd_cache WHERE destination = %s",
            $destination
        )
    );
}
