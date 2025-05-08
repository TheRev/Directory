<?php

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
        'gpd-search-history',
        'gpd_render_search_history_page'
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

    $url = 'https://places.googleapis.com/v1/places:searchText';

$post_data = [
    'textQuery'    => $query,
    'languageCode' => 'en',
    'regionCode'   => 'US',
    'pageSize'     => $limit,
];
if ($next_token) {
    $post_data['pageToken'] = $next_token;
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
// --- BEGIN: Save results to cache table ---
if (isset($data['places']) && is_array($data['places'])) {
    $query_hash = md5($query . $radius); // You can add more params if you want the hash to be unique
    $cached_at = current_time('mysql');
    foreach ($data['places'] as $place) {
        // Set $place_id the same way as in your import handler.
        // Prefer a unique Google Place ID if available, otherwise fallback to name.
        $place_id = '';
        if (isset($place['place_id'])) {
            $place_id = sanitize_text_field($place['place_id']);
        } elseif (isset($place['id'])) {
            $place_id = sanitize_text_field($place['id']);
        } elseif (isset($place['name'])) {
            $place_id = sanitize_text_field($place['name']);
        }

        $wpdb->insert(
            $wpdb->prefix . 'gpd_cache',
            [
                'query_hash'         => $query_hash,
                'destination'        => $query, // or use a parsed city/destination if you have one
                'page_token'         => $next_token ?? '',
                'cached_at'          => $cached_at,
                'place_id'           => $place_id,
                'name'               => $place['displayName']['text'] ?? '',
                'address'            => $place['formattedAddress'] ?? '',
                'latitude'           => $place['location']['latitude'] ?? null,
                'longitude'          => $place['location']['longitude'] ?? null,
                'types'              => maybe_serialize($place['types'] ?? []),
                'rating'             => $place['rating'] ?? null,
                'user_ratings_total' => $place['userRatingCount'] ?? null,
                'phone_number'       => $place['internationalPhoneNumber'] ?? '',
                'website'            => $place['websiteUri'] ?? '',
                'google_maps_url'    => $place['googleMapsUri'] ?? '',
                'imported_count'     => 0,
                'pages_imported'     => 1,
            ]
        );
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


    if ($shown === 0 && !$next_token) {
        $output .= '<tr><td colspan="4"><em>No shops found matching your search.</em></td></tr>';
    } elseif ($shown === 0 && $next_token) {
        $output .= '<tr><td colspan="4"><em>No new shops to show on this page. Click Search again for more.</em></td></tr>';
    } elseif ($shown > 0 && !$next_token && isset($data['nextPageToken'])) {
        $output .= '<tr><td colspan="4"><em>Showing initial results. Click Next Page for more.</em></td></tr>';
    } elseif ($shown > 0 && !$next_token && !isset($data['nextPageToken'])) {
        $output .= '<tr><td colspan="4"><em>Showing all available shops for this search.</em></td></tr>';
    }

    wp_send_json([
        'html' => $output,
        'next_page_token' => $data['nextPageToken'] ?? null
    ]);
    wp_die();
}
