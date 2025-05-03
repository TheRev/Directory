<?php
if (!session_id()) {
    session_start();
}

// Register Admin Submenu for Import
function dsd_register_import_page() {
    add_submenu_page(
        'edit.php?post_type=dive_shop',
        'Import Dive Shops',
        'Import Dive Shops',
        'manage_options',
        'dsd-import',
        'dsd_import_page_html'
    );
}
add_action('admin_menu', 'dsd_register_import_page');

// Handle Import Page HTML
function dsd_import_page_html() {
    if (!current_user_can('manage_options')) return;

    // Ensure Google API Key is set
    $google_api_key = get_option('dsd_google_api_key');
    if (!$google_api_key) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> Google API Key not set in Settings.</p></div>';
        return;
    }

    $text_search = '';
    $radius = 5000;
    $batch_limit = 10;
    $next_page_token = '';

    // New Search
    if (isset($_POST['new_search'])) {
        $text_search = sanitize_text_field($_POST['text_search']);
        $radius = intval($_POST['radius']) ?: 5000;
        $batch_limit = intval($_POST['batch_limit']) ?: 10;

        $_SESSION['text_search'] = $text_search;
        $_SESSION['radius'] = $radius;
        $_SESSION['next_page_token'] = ''; // Reset pagination
    }

    // Continue Search
    if (isset($_POST['continue_search'])) {
        $text_search = sanitize_text_field($_SESSION['text_search']);
        $radius = intval($_SESSION['radius']);
        $batch_limit = intval($_POST['batch_limit']) ?: 10;
        $next_page_token = sanitize_text_field($_SESSION['next_page_token']);
    }

    // Import Selected Dive Shops
    if (isset($_POST['dsd_import_selected']) && !empty($_POST['shop_place_ids'])) {
        dsd_process_selected_import($_POST['shop_place_ids'], $_POST['beginner_friendly'], $_POST['nitrox_available']);
    }

    ?>
    <div class="wrap">
        <h1>Import Dive Shops</h1>
        <form method="post" style="margin-bottom:20px;">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="text_search">Search Query</label></th>
                    <td><input type="text" id="text_search" name="text_search" required class="regular-text" value=""></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="radius">Search Radius</label></th>
                    <td>
                        <select name="radius">
                            <option value="5000">5 km</option>
                            <option value="10000">10 km</option>
                            <option value="20000">20 km</option>
                            <option value="50000">50 km</option>
                        </select>
                        <small>Distance from search location.</small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="batch_limit">Results Limit</label></th>
                    <td>
                        <select name="batch_limit">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                        <small>Number of results to display at once.</small>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" name="new_search" class="button-primary">Start New Search</button>
                <?php if (!empty($_SESSION['next_page_token'])): ?>
                    <button type="submit" name="continue_search" class="button-secondary">Continue Search</button>
                <?php endif; ?>
            </p>
        </form>

        <?php
        // Fetch Results from Google Places API
        if (!empty($text_search)) {
            $url_args = [
                'query' => $text_search,
                'radius' => $radius,
                'key' => $google_api_key,
            ];

            if (!empty($next_page_token)) {
                $url_args['pagetoken'] = $next_page_token;
            }

            $places_url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query($url_args);
            $response = wp_remote_get($places_url);

            if (is_wp_error($response)) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> Google Places API request failed.</p></div>';
                return;
            }

            $body = wp_remote_retrieve_body($response);
            $results = json_decode($body);

            if (!empty($results->results)) {
                echo '<form method="post">';
                echo '<p><label><input type="checkbox" name="beginner_friendly" value="yes" checked> Beginner-Friendly</label> ';
                echo '<label style="margin-left:20px;"><input type="checkbox" name="nitrox_available" value="yes" checked> Nitrox Available</label></p>';

                echo '<table class="widefat striped">';
                echo '<thead><tr><th><input type="checkbox" id="select-all-import"></th><th>Name</th><th>Address</th><th>Rating</th><th>Status</th></tr></thead>';
                echo '<tbody>';

                $count = 0;
                foreach ($results->results as $shop) {
                    if ($count >= $batch_limit) break;

                    $place_id = sanitize_text_field($shop->place_id);

                    // Skip duplicates
                    if (dsd_dive_shop_exists($place_id)) continue;

                    echo '<tr>';
                    echo '<td><input type="checkbox" name="shop_place_ids[]" value="' . esc_attr($place_id) . '"></td>';
                    echo '<td>' . esc_html($shop->name ?? '') . '</td>';
                    echo '<td>' . esc_html($shop->formatted_address ?? '') . '</td>';
                    echo '<td>' . esc_html($shop->rating ?? 'N/A') . '</td>';
                    echo '<td>' . esc_html($shop->business_status ?? '-') . '</td>';
                    echo '</tr>';

                    $count++;
                }

                if ($count === 0) {
                    echo '<tr><td colspan="5" style="text-align:center;">No new dive shops found!</td></tr>';
                }

                echo '</tbody></table>';
                echo '<p><button type="submit" name="dsd_import_selected" class="button-primary">Import Selected Dive Shops</button></p>';
                echo '</form>';

                if (!empty($results->next_page_token)) {
                    $_SESSION['next_page_token'] = $results->next_page_token;
                } else {
                    unset($_SESSION['next_page_token']);
                    echo '<p><strong>All results imported or displayed!</strong></p>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>No dive shops found for this query.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}

// Helper Functions
function dsd_dive_shop_exists($place_id) {
    $query = new WP_Query([
        'post_type' => 'dive_shop',
        'meta_query' => [
            [
                'key' => 'dive_shop_place_id',
                'value' => $place_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    return !empty($query->posts);
}

function dsd_process_selected_import($place_ids, $beginner_friendly, $nitrox_available) {
    $google_api_key = get_option('dsd_google_api_key');
    $imported = 0;

    foreach ($place_ids as $place_id) {
        $place_id = sanitize_text_field($place_id);

        if (dsd_dive_shop_exists($place_id)) continue;

        $details_url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
            'place_id' => $place_id,
            'key' => $google_api_key,
        ]);

        $response = wp_remote_get($details_url);
        if (is_wp_error($response)) continue;

        $details = json_decode(wp_remote_retrieve_body($response));
        if (!empty($details->result)) {
            $shop = $details->result;

            $post_id = wp_insert_post([
                'post_title' => sanitize_text_field($shop->name ?? ''),
                'post_type' => 'dive_shop',
                'post_status' => 'publish',
            ]);

            if ($post_id) {
                update_post_meta($post_id, 'dive_shop_place_id', $place_id);
                update_post_meta($post_id, 'dive_shop_address', sanitize_text_field($shop->formatted_address ?? ''));
                update_post_meta($post_id, 'dive_shop_rating', sanitize_text_field($shop->rating ?? ''));
                update_post_meta($post_id, 'dive_shop_status', 'operational');
                update_post_meta($post_id, 'dive_shop_beginner_friendly', ($beginner_friendly === 'yes') ? 'yes' : 'no');
                update_post_meta($post_id, 'dive_shop_nitrox', ($nitrox_available === 'yes') ? 'yes' : 'no');

                $imported++;
            }
        }
    }

    echo '<div class="notice notice-success"><p><strong>Import Complete:</strong> Imported ' . intval($imported) . ' dive shops.</p></div>';
}
?>