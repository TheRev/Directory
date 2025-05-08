<?php
function gpd_register_post_types() {
    register_post_type('gpd_shop', [
        'labels' => [
            'name' => 'Shops',
            'singular_name' => 'Shop',
            'add_new_item' => 'Add New Shop',
            'edit_item' => 'Edit Shop',
            'new_item' => 'New Shop',
            'view_item' => 'View Shop',
            'search_items' => 'Search Shops',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'rewrite' => ['slug' => 'shops'],
    ]);
}
add_action('init', 'gpd_register_post_types');
