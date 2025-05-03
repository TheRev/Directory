<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Register the Custom Post Type (CPT)
function dsd_register_dive_shop_cpt() {
    $labels = array(
        'name'               => __('Dive Shops', 'dsd'),
        'singular_name'      => __('Dive Shop', 'dsd'),
        'menu_name'          => __('Dive Shops', 'dsd'),
        'name_admin_bar'     => __('Dive Shop', 'dsd'),
        'add_new'            => __('Add New', 'dsd'),
        'add_new_item'       => __('Add New Dive Shop', 'dsd'),
        'new_item'           => __('New Dive Shop', 'dsd'),
        'edit_item'          => __('Edit Dive Shop', 'dsd'),
        'view_item'          => __('View Dive Shop', 'dsd'),
        'all_items'          => __('All Dive Shops', 'dsd'),
        'search_items'       => __('Search Dive Shops', 'dsd'),
        'parent_item_colon'  => __('Parent Dive Shops:', 'dsd'),
        'not_found'          => __('No dive shops found.', 'dsd'),
        'not_found_in_trash' => __('No dive shops found in Trash.', 'dsd')
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'dive-shop'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'thumbnail')
    );

    register_post_type('dive_shop', $args);
}
add_action('init', 'dsd_register_dive_shop_cpt');
