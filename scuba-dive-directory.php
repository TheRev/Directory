<?php
/*
Plugin Name: Scuba Dive Directory
Description: A plugin to manage scuba dive shops using Google Places API and custom data enrichment.
Version: 1.0
Author: TheRev
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/db-schema.php';
require_once plugin_dir_path(__FILE__) . 'includes/cpt-dive-shop.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings-page.php'; // New settings page

// Activation Hook: Setup database and CPT
register_activation_hook(__FILE__, 'dsd_activate_plugin');
function dsd_activate_plugin() {
    // Create custom database tables
    dsd_create_db_schema();

    // Flush rewrite rules for Custom Post Types
    flush_rewrite_rules();
}

// Deactivation Hook: Cleanup
register_deactivation_hook(__FILE__, 'dsd_deactivate_plugin');
function dsd_deactivate_plugin() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
