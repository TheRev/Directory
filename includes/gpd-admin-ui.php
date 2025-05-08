<?php

require_once plugin_dir_path(__FILE__) . 'gpd-search-sessions.php';

// ✅ Render Import Page
function gpd_render_import_page() {
    ?>
    <div class="wrap">
        <h1>Import Places from Google</h1>
        <form method="post" id="gpd-search-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gpd-query">Search Term</label></th>
                    <td><input name="gpd_query" type="text" id="gpd-query" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gpd-radius">Radius (km)</label></th>
                    <td>
                        <select name="gpd_radius" id="gpd-radius">
                            <option value="6">6</option>
                            <option value="15">15</option>
                            <option value="30">30</option>
                            <option value="50">50</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gpd-limit">Results per Page</label></th>
                    <td>
                        <select name="gpd_limit" id="gpd-limit">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Search</button>
            </p>
        </form>
        <div id="gpd-results"></div>
        <div id="gpd-pagination" style="margin-top:10px;"></div>
        <div style="margin-top: 20px;">
            <button class="button button-primary" id="gpd-import-selected">Import Selected</button>
        </div>
        <div id="gpd-imported-count" style="margin-top: 10px;"><strong>Imported this session:</strong> <span id="imported-count">0</span></div>
    </div>
    <?php
}

// ✅ Render Settings Page
function gpd_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Google Places API Settings</h1>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="message" class="updated notice is-dismissible">
                <p><strong>Settings saved.</strong></p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('gpd_settings_group');
            do_settings_sections('gpd_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Places API Key</th>
                    <td>
                        <input type="text" name="gpd_api_key" value="<?php echo esc_attr(get_option('gpd_api_key')); ?>" size="60" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ✅ Admin Menus
function gpd_admin_menus() {
    // Top-level menu
    add_menu_page(
        'Import Places',
        'Google Places',
        'manage_options',
        'gpd-import',
        'gpd_render_import_page',
        'dashicons-location-alt',
        25
    );

    // Submenu: Settings
    add_submenu_page(
        'gpd-import',
        'Google Places Settings',
        'Settings',
        'manage_options',
        'gpd-settings',
        'gpd_render_settings_page'
    );

    // Submenu: Search History
    add_submenu_page(
        'gpd-import',
        'Search History',
        'Search History',
        'manage_options',
        'gpd-search-history'
        // 'gpd_render_search_history_page'
    );

    // Register API Key setting
    register_setting('gpd_settings_group', 'gpd_api_key');
}
add_action('admin_menu', 'gpd_admin_menus');

// ✅ Admin Scripts (enqueue only on plugin admin pages)
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'gpd-import') !== false) {
        wp_enqueue_script(
            'gpd-admin-js',
            plugin_dir_url(__FILE__) . '../assets/js/gpd-admin.js',
            ['jquery'],
            null,
            true
        );
    }
});

// ✅ AJAX Handler - Search Places
add_action('wp_ajax_gpd_search_places', 'gpd_search_places_handler');

function gpd_search_places_handler() {
    global $wpdb;

    $query = sanitize_text_field($_POST['query']);
    $radius = sanitize_text_field($_POST['radius']);
    $limit = intval($_POST['limit']);
    $next_token = isset($_POST['next_page_token']) ? sanitize_text_field($_POST['next_page_token']) : null;

    $api_key = get_option('gpd_api_key');
    if (empty($api_key)) {
        wp_send_json([
            'html' => '<tr><td colspan="4"><strong>Error:</strong> API key is not set. Please go to <a href="' . admin_url('admin.php?page=gpd-settings') . '">Settings</a> and add it.</td></tr>',
            'next_page_token' => null
        ]);
        wp_die();
    }

    // --- SESSION LOGIC BLOCK START ---
    $session = gpd_get_search_session($query, $radius);
    if ($session) {
        $page_token = $session['last_page_token'];
        $session_id = $session['id'];
    } else {
        $session_id = gpd_create_search_session($query, $radius, $next_token);
        $page_token = $next_token;
    }
    // --- SESSION LOGIC BLOCK END ---

    $url = 'https://places.googleapis.com/v1/places:searchText';

    $post_data = [
        'textQuery'    => $query,
        'languageCode' => 'en',
        'regionCode'   => 'US',
        'pageSize'     => $limit,
    ];
    if ($page_token) {
        $post_data['pageToken'] = $page_token;
        // Do NOT unset pageSize! Always send it so Google respects your limit.
    }

    $args = [
        'body' => wp_json_encode($post_data),
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $api_key,
            'X-Goog-FieldMask' => 'places.name,places.displayName,places.addressComponents,places.location,places.types,places.rating,places.businessStatus,places.websiteUri,places.internationalPhoneNumber,places.googleMapsUri,nextPageToken'
        ],
        'method' => 'POST'
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        wp_send_json([
            'html' => '<tr><td colspan="4"><strong>Error:</strong> ' . $response->get_error_message() . '</td></tr>',
            'next_page_token' => null
        ]);
        wp_die();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

// Debug the Places API response and nextPageToken
error_log('API RESPONSE: ' . print_r($data, true));
if (isset($data['nextPageToken'])) {
    error_log('nextPageToken exists: ' . $data['nextPageToken']);
} else {
    error_log('nextPageToken is NOT set in response.');
}

    if (!isset($data['places']) || !is_array($data['places'])) {
        $error_message = 'No places found or error in API call.';
        if (isset($data['error'])) {
            $error_message .= ' Google API Error: ' . esc_html($data['error']['message']);
        }
        wp_send_json([
            'html' => '<tr><td colspan="4"><strong>Error:</strong> ' . $error_message . '</td></tr>',
            'next_page_token' => null
        ]);
        wp_die();
    }

    // Helper function for extracting a component by type
    if (!function_exists('gpd_extract_address_part')) {
        function gpd_extract_address_part($components, $type) {
            foreach ($components as $component) {
                if (
                    is_array($component) &&
                    !empty($component['types']) &&
                    in_array($type, $component['types'], true)
                ) {
                    // Prefer longText, then long_name if somehow present
                    if (isset($component['longText'])) {
                        return $component['longText'];
                    } elseif (isset($component['long_name'])) {
                        return $component['long_name'];
                    }
                }
            }
            return '';
        }
    }

    // --- BEGIN: Save results to cache table ---
    if (isset($data['places']) && is_array($data['places'])) {
        $query_hash = md5($query . $radius); // You can add more params if you want the hash to be unique
        $cached_at = current_time('mysql');
        foreach ($data['places'] as $place) {
            // Debug: inspect the $place structure
          //  error_log("---- DEBUG: Full place array ----");
         //   error_log(print_r($place, true));

            $place_id    = sanitize_text_field($place['name']);

            // Try both possible keys for address components
            $components  = $place['addressComponents'] ?? $place['address_components'] ?? [];
            if (empty($components)) {
                error_log("WARNING: address components missing for place_id: $place_id, place data: " . print_r($place, true));
            }

            // Extract all relevant address parts
            $subpremise  = gpd_extract_address_part($components, 'subpremise');
            $street_num  = gpd_extract_address_part($components, 'street_number');
            $route       = gpd_extract_address_part($components, 'route');
            $locality    = gpd_extract_address_part($components, 'locality');
            $admin_area2 = gpd_extract_address_part($components, 'administrative_area_level_2');
            $admin_area1 = gpd_extract_address_part($components, 'administrative_area_level_1');
            $country     = gpd_extract_address_part($components, 'country');
            $postal_code = gpd_extract_address_part($components, 'postal_code');

            // Debug: log extracted values
            // error_log("Extracted for place_id $place_id: subpremise=$subpremise, street_number=$street_num, route=$route, locality=$locality, admin_area2=$admin_area2, admin_area1=$admin_area1, country=$country, postal_code=$postal_code");

            $destination = strtolower($locality ?: 'unassigned');

            // Use REPLACE to upsert on place_id
            $result = $wpdb->replace(
                $wpdb->prefix . 'gpd_cache',
                [
                    'query_hash'                  => $query_hash,
                    'destination'                 => $destination,
                    'page_token'                  => $page_token ?? '',
                    'cached_at'                   => $cached_at,
                    'place_id'                    => $place_id,
                    'name'                        => sanitize_text_field($place['displayName']['text'] ?? ''),
                    'address'                     => sanitize_text_field($place['formattedAddress'] ?? ''),
                    'latitude'                    => isset($place['location']['latitude']) ? (float)$place['location']['latitude'] : null,
                    'longitude'                   => isset($place['location']['longitude']) ? (float)$place['location']['longitude'] : null,
                    'types'                       => maybe_serialize($place['types'] ?? []),
                    'rating'                      => isset($place['rating']) ? (float)$place['rating'] : null,
                    'user_ratings_total'          => isset($place['userRatingCount']) ? (int)$place['userRatingCount'] : null,
                    'website'                     => sanitize_text_field($place['websiteUri'] ?? ''),
                    'phone_number'                => sanitize_text_field($place['internationalPhoneNumber'] ?? ''),
                    'google_maps_url'             => sanitize_text_field($place['googleMapsUri'] ?? ''),
                    'business_status'             => sanitize_text_field($place['businessStatus'] ?? ''),
                    'imported_count'              => 0,
                    'pages_imported'              => 1,
                    // Individual address component columns:
                    'subpremise'                  => $subpremise,
                    'street_number'               => $street_num,
                    'route'                       => $route,
                    'locality'                    => $locality,
                    'administrative_area_level_2' => $admin_area2,
                    'administrative_area_level_1' => $admin_area1,
                    'country'                     => $country,
                    'postal_code'                 => $postal_code
                ]
            );

            if ($result === false) {
                error_log("DB ERROR: Failed to REPLACE for place_id: $place_id. Last error: " . $wpdb->last_error);
            } else {
                error_log("SUCCESS: Place $place_id cached/updated.");
            }
        }
    }
    // --- END: Save results to cache table ---

    $output = '';
    $shown = 0;

    // ✅ Normalize destination once from search query
    $components = $place['addressComponents'] ?? [];
    $destination_hint =
        gpd_extract_address_part($components, 'locality') ?:
        gpd_extract_address_part($components, 'administrative_area_level_2') ?:
        gpd_extract_address_part($components, 'sublocality') ?:
        'Unassigned';

    foreach ($data['places'] as $place) {
        $place_id = esc_sql($place['name']);
        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gpd_businesses WHERE place_id = %s",
            $place_id
        ));

        $display_name = esc_html($place['displayName']['text'] ?? 'Unnamed');
        $address = esc_html($place['formattedAddress'] ?? '');

        // Break out address components
        $components = $place['addressComponents'] ?? [];
        $street = gpd_extract_address_part($components, 'route');
        $city = gpd_extract_address_part($components, 'locality');
        $state = gpd_extract_address_part($components, 'administrative_area_level_1');
        $postal = gpd_extract_address_part($components, 'postal_code');
        $country = gpd_extract_address_part($components, 'country');

        if (!$address && ($street || $city || $state)) {
            $address_parts = array_filter([$street, $city, $state, $postal, $country]);
            $address = esc_html(implode(', ', $address_parts));
        }

        // Display formatting based on already-imported status
        $row_style = $already ? 'background-color:#ffe0e0;' : 'background-color:#f0fff0;';
        $disabled_attr = $already ? 'disabled' : '';
        $label = $already ? '<strong>[Imported]</strong>' : '';

        $output .= '<tr class="gpd-place-row" style="' . $row_style . '">';
        $output .= '<th scope="row" class="check-column"><input type="checkbox" class="gpd-select-place" ' . $disabled_attr . ' /></th>';
        $output .= '<td><strong>' . $display_name . '</strong><br>' . $address . '</td>';
        $output .= '<td><em>' . esc_html($city ?: 'Unassigned') . '</em></td>';
        $output .= '<td>' . $label . ' <button class="button gpd-delete-row">🗑️</button></td>';
        $output .= '<script type="application/json" class="gpd-place-data">' . wp_json_encode($place) . '</script>';
        $output .= '</tr>';

        $shown++;
    }

    // --- SESSION LOGIC BLOCK: update session progress after API response ---
$next_page_token = isset($data['nextPageToken']) ? $data['nextPageToken'] : '';
$is_complete = $next_page_token ? 0 : 1;
error_log('About to update session ' . $session_id . ' with token: "' . $next_page_token . '"');
gpd_update_search_session($session_id, $next_page_token, $is_complete);

    if ($shown === 0 && !$next_page_token) {
        $output .= '<tr><td colspan="4"><em>No shops found matching your search.</em></td></tr>';
    } elseif ($shown === 0 && $next_page_token) {
        $output .= '<tr><td colspan="4"><em>No new shops to show on this page. Click Search again for more.</em></td></tr>';
    } elseif ($shown > 0 && !$next_page_token && isset($data['nextPageToken'])) {
        $output .= '<tr><td colspan="4"><em>Showing initial results. Click Next Page for more.</em></td></tr>';
    } elseif ($shown > 0 && !$next_page_token && !isset($data['nextPageToken'])) {
        $output .= '<tr><td colspan="4"><em>Showing all available shops for this search.</em></td></tr>';
    }

    wp_send_json([
        'html' => $output,
        'next_page_token' => $next_page_token
    ]);
    wp_die();
}
