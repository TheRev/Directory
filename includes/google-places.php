<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Fetch results from Google Places API
function dsd_fetch_google_places($location, $radius) {
    // Get the API key from settings
    $api_key = get_option('dsd_google_places_api_key', '');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('Google Places API key is not set.', 'dsd'));
    }

    // Build the API endpoint
    $endpoint = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
    $args = array(
        'location' => urlencode($location),
        'radius' => $radius,
        'type' => 'store',
        'key' => $api_key
    );

    $url = add_query_arg($args, $endpoint);
    $response = wp_remote_get($url);

    // Handle API errors
    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['error_message'])) {
        return new WP_Error('api_error', $data['error_message']);
    }

    return $data['results']; // Return the list of results
}

// Display results from Google Places API in a table
function dsd_display_places_results($results) {
    if (empty($results)) {
        echo '<div class="notice notice-warning"><p>' . __('No results found.', 'dsd') . '</p></div>';
        return;
    }

    ?>
    <form method="post" action="">
        <?php wp_nonce_field('dsd_import_selected_nonce'); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Select', 'dsd'); ?></th>
                    <th><?php _e('Name', 'dsd'); ?></th>
                    <th><?php _e('Address', 'dsd'); ?></th>
                    <th><?php _e('Rating', 'dsd'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result) : ?>
                    <tr>
                        <td><input type="checkbox" name="dsd_selected_places[]" value="<?php echo esc_attr($result['place_id']); ?>" /></td>
                        <td><?php echo esc_html($result['name']); ?></td>
                        <td><?php echo esc_html($result['vicinity']); ?></td>
                        <td><?php echo !empty($result['rating']) ? esc_html($result['rating']) : __('N/A', 'dsd'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button(__('Import Selected', 'dsd')); ?>
    </form>
    <?php
}