<?php
/**
 * Plugin Name: Google Places Directory
 * Description: A plugin to import and display places from the Google Places API.
 * Version: 1.0.0
 * Author: Joseph Cox
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Load core plugin files
require_once plugin_dir_path(__FILE__) . 'includes/search-history.php';
require_once plugin_dir_path(__FILE__) . 'includes/gpd-post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/gpd-taxonomies.php';
require_once plugin_dir_path(__FILE__) . 'includes/gpd-install.php'; // Custom DB tables
require_once plugin_dir_path(__FILE__) . 'includes/gpd-admin-ui.php';
require_once plugin_dir_path(__FILE__) . 'includes/gpd-import-handler.php';

function gpd_enqueue_admin_assets($hook) {
    // Only load on our plugin admin pages
    if (strpos($hook, 'google-places') === false) {
        return;
    }

    wp_enqueue_style('gpd-admin-style', plugin_dir_url(__FILE__) . 'assets/css/gpd-admin.css');
    wp_enqueue_script('gpd-admin-js', plugin_dir_url(__FILE__) . 'assets/js/gpd-admin.js', array('jquery'), null, true);

    // Localize JS for AJAX calls
    wp_localize_script('gpd-admin-js', 'gpd_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gpd_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'gpd_enqueue_admin_assets');

// Plugin activation: register CPT, taxonomies, and install tables
register_activation_hook(__FILE__, function() {
    gpd_register_post_types();
    gpd_register_taxonomies();
    gpd_install_database_table();
    flush_rewrite_rules();
});
