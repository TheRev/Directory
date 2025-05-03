<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add settings page
function dsd_add_settings_page() {
    add_menu_page(
        __('Scuba Directory Settings', 'dsd'),
        __('Scuba Directory', 'dsd'),
        'manage_options',
        'dsd-settings',
        'dsd_render_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'dsd_add_settings_page');

// Render settings page
function dsd_render_settings_page() {
    // Save settings
    if (isset($_POST['dsd_save_settings'])) {
        check_admin_referer('dsd_settings_nonce');

        // Save API key (if not empty)
        $new_api_key = sanitize_text_field($_POST['dsd_google_places_api_key']);
        if (!empty($new_api_key) && $new_api_key !== str_repeat('*', strlen($new_api_key))) {
            update_option('dsd_google_places_api_key', $new_api_key);
        }
        echo '<div class="updated"><p>' . __('Settings saved.', 'dsd') . '</p></div>';
    }

    // Retrieve current API key
    $api_key = get_option('dsd_google_places_api_key', '');
    $masked_key = !empty($api_key) ? str_repeat('*', strlen($api_key) - 4) . substr($api_key, -4) : '';

    ?>
    <div class="wrap">
        <h1><?php _e('Scuba Directory Settings', 'dsd'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('dsd_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dsd_google_places_api_key"><?php _e('Google Places API Key', 'dsd'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="dsd_google_places_api_key" id="dsd_google_places_api_key" 
                               value="<?php echo esc_attr($masked_key); ?>" class="regular-text" />
                        <p class="description"><?php _e('Enter your Google Places API key. The key is masked for security.', 'dsd'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'dsd')); ?>
        </form>
    </div>
    <?php
}