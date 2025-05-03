<?php
if (!session_id()) {
    session_start();
}

// Helper Functions File
include_once 'includes/import-helpers.php';

// Register Admin Submenu for Import
function dsd_register_import_page() {
    add_submenu_page(
        'edit.php?post_type=dive_shop',
        'Import Dive Shops & Businesses',
        'Import Data',
        'manage_options',
        'dsd-import',
        'dsd_import_page_html'
    );
}
add_action('admin_menu', 'dsd_register_import_page');

// Main Import Page
function dsd_import_page_html() {
    if (!current_user_can('manage_options')) return;

    // Ensure Google API Key is set
    $google_api_key = get_option('dsd_google_api_key');
    if (!$google_api_key) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> Google API Key not set in Settings.</p></div>';
        return;
    }

    // Initialize Variables
    $text_search = '';
    $radius = 5000;
    $batch_limit = 10;
    $latitude = '';
    $longitude = '';
    $next_page_token = '';

    // Handle New Search
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_search'])) {
        $text_search = sanitize_text_field($_POST['text_search']);
        $radius = intval($_POST['radius']) ?: 5000;
        $batch_limit = intval($_POST['batch_limit']) ?: 10;
        $latitude = sanitize_text_field($_POST['latitude']);
        $longitude = sanitize_text_field($_POST['longitude']);

        $_SESSION['text_search'] = $text_search;
        $_SESSION['radius'] = $radius;
        $_SESSION['latitude'] = $latitude;
        $_SESSION['longitude'] = $longitude;
        $_SESSION['next_page_token'] = ''; // Reset pagination
    }

    // Handle Continue Search
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['continue_search'])) {
        $text_search = sanitize_text_field($_SESSION['text_search']);
        $radius = intval($_SESSION['radius']);
        $latitude = sanitize_text_field($_SESSION['latitude']);
        $longitude = sanitize_text_field($_SESSION['longitude']);
        $batch_limit = intval($_POST['batch_limit']) ?: 10;
        $next_page_token = sanitize_text_field($_SESSION['next_page_token']);
    }

    // Handle Import of Selected Items
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dsd_import_selected']) && !empty($_POST['place_ids'])) {
        dsd_process_selected_import($_POST['place_ids']);
    }

    // Fetch Data from Google Places API
    if (!empty($text_search)) {
        $results = fetch_google_places($text_search, $radius, $latitude, $longitude, $google_api_key, $next_page_token);

        if (!empty($results['results'])) {
            $_SESSION['next_page_token'] = $results['next_page_token'] ?? '';
            display_results_table($results['results']);
        } else {
            echo '<p style="color: red;">No results found for the given query.</p>';
        }
    }

    // Render Search Form
    render_search_form($text_search, $radius, $batch_limit, $latitude, $longitude);
}

// Helper Function to Render Search Form
function render_search_form($text_search, $radius, $batch_limit, $latitude, $longitude) {
    ?>
    <div class="wrap">
        <h1>Import Dive Shops & Businesses</h1>
        <form method="post" style="margin-bottom:20px;">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="text_search">Search Query</label></th>
                    <td><input type="text" id="text_search" name="text_search" required class="regular-text" value="<?php echo esc_attr($text_search); ?>"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="radius">Search Radius</label></th>
                    <td>
                        <select name="radius">
                            <option value="5000" <?php selected($radius, 5000); ?>>5 km</option>
                            <option value="10000" <?php selected($radius, 10000); ?>>10 km</option>
                            <option value="20000" <?php selected($radius, 20000); ?>>20 km</option>
                            <option value="50000" <?php selected($radius, 50000); ?>>50 km</option>
                        </select>
                        <small>Distance from search location.</small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="batch_limit">Results Limit</label></th>
                    <td>
                        <select name="batch_limit">
                            <option value="5" <?php selected($batch_limit, 5); ?>>5</option>
                            <option value="10" <?php selected($batch_limit, 10); ?>>10</option>
                            <option value="20" <?php selected($batch_limit, 20); ?>>20</option>
                            <option value="50" <?php selected($batch_limit, 50); ?>>50</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="latitude">Latitude</label></th>
                    <td><input type="text" id="latitude" name="latitude" class="regular-text" value="<?php echo esc_attr($latitude); ?>"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="longitude">Longitude</label></th>
                    <td><input type="text" id="longitude" name="longitude" class="regular-text" value="<?php echo esc_attr($longitude); ?>"></td>
                </tr>
            </table>
            <button type="submit" name="new_search" class="button button-primary">Search</button>
            <?php if (!empty($_SESSION['next_page_token'])): ?>
                <button type="submit" name="continue_search" class="button">Continue Search</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

// Helper Function to Display Results Table
function display_results_table($results) {
    ?>
    <form method="post">
        <table border="1">
            <tr>
                <th>Select</th>
                <th>Name</th>
                <th>Address</th>
                <th>Phone</th>
                <th>Rating</th>
                <th>Website</th>
            </tr>
            <?php foreach ($results as $place): ?>
                <tr>
                    <td><input type="checkbox" name="place_ids[]" value="<?php echo esc_attr($place['place_id']); ?>"></td>
                    <td><?php echo esc_html($place['name']); ?></td>
                    <td><?php echo esc_html($place['formatted_address'] ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($place['international_phone_number'] ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($place['rating'] ?? 'N/A'); ?></td>
                    <td><?php if (!empty($place['website'])): ?><a href="<?php echo esc_url($place['website']); ?>" target="_blank">Website</a><?php else: ?>N/A<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" name="dsd_import_selected" class="button button-primary">Import Selected</button>
    </form>
    <?php
}
