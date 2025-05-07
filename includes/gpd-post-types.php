<?php
function gpd_register_post_types() {
    register_post_type('gpd_place', [
        'labels' => [
            'name' => 'Places',
            'singular_name' => 'Place',
            'add_new_item' => 'Add New Place',
            'edit_item' => 'Edit Place',
            'new_item' => 'New Place',
            'view_item' => 'View Place',
            'search_items' => 'Search Places',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'rewrite' => ['slug' => 'places'],
    ]);
}
add_action('init', 'gpd_register_post_types');
