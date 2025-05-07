<?php
function gpd_register_taxonomies() {
    register_taxonomy('gpd_region', 'gpd_place', [
        'hierarchical' => true,
        'labels' => [
            'name' => 'Regions',
            'singular_name' => 'Region',
            'add_new_item' => 'Add New Region',
            'edit_item' => 'Edit Region',
            'search_items' => 'Search Regions',
        ],
        'rewrite' => ['slug' => 'region'],
        'show_admin_column' => true,
    ]);

    register_taxonomy('gpd_destination', 'gpd_place', [
        'hierarchical' => true,
        'labels' => [
            'name' => 'Destinations',
            'singular_name' => 'Destination',
            'add_new_item' => 'Add New Destination',
            'edit_item' => 'Edit Destination',
            'search_items' => 'Search Destinations',
        ],
        'rewrite' => ['slug' => 'destination'],
        'show_admin_column' => true,
    ]);
}
add_action('init', 'gpd_register_taxonomies');
