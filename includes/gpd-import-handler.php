<?php
// ✅ AJAX Handler - Import Places
add_action('wp_ajax_gpd_import_places', 'gpd_import_places_handler');

// ✅ Helper to extract address parts (protected from redeclaration)
// if (!function_exists('gpd_extract_address_part')) {
//    function gpd_extract_address_part($components, $type) {
  //      foreach ($components as $comp) {
    //        if (in_array($type, $comp['types'])) {
      //          return sanitize_text_field($comp['longText'] ?? '');
        //    }
//        }
  //      return '';
 //   }
// }

function gpd_import_places_handler() {
    global $wpdb;

    $places = isset($_POST['places_json']) ? json_decode(stripslashes($_POST['places_json']), true) : [];

    if (!is_array($places)) {
        wp_send_json_error(['message' => 'Invalid place data.']);
    }

    $imported = 0;

    foreach ($places as $place) {
        if (empty($place['name'])) continue;

        $place_id = sanitize_text_field($place['name']);

        // Check for duplicates
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gpd_businesses WHERE place_id = %s",
            $place_id
        ));

        if ($exists) continue;

        // ✅ Extract structured address parts
        $components = $place['addressComponents'] ?? [];

        $street      = gpd_extract_address_part($components, 'route');
        $city        = gpd_extract_address_part($components, 'locality');
        $state       = gpd_extract_address_part($components, 'administrative_area_level_1');
        $postal_code = gpd_extract_address_part($components, 'postal_code');
        $country     = gpd_extract_address_part($components, 'country');

        // ✅ Use locality (city) as destination
        $destination = strtolower($city ?: 'unassigned');

        // ✅ Insert into database
        $wpdb->insert("{$wpdb->prefix}gpd_businesses", [
            'place_id'           => $place_id,
            'name'               => sanitize_text_field($place['displayName']['text'] ?? ''),
            'address'            => sanitize_text_field($place['formattedAddress'] ?? ''),
            'destination'        => $destination,
            'latitude'           => $place['location']['latitude'] ?? null,
            'longitude'          => $place['location']['longitude'] ?? null,
            'types'              => maybe_serialize($place['types'] ?? []),
            'rating'             => $place['rating'] ?? null,
            'user_ratings_total' => $place['userRatingCount'] ?? null,
            'website'            => sanitize_text_field($place['websiteUri'] ?? ''),
            'phone_number'       => sanitize_text_field($place['internationalPhoneNumber'] ?? ''),
            'google_maps_url'    => sanitize_text_field($place['googleMapsUri'] ?? ''),
            'business_status'    => sanitize_text_field($place['businessStatus'] ?? ''),
            'is_scraped'         => 0,
            'scraped_fields'     => null,
            'street'             => $street,
            'city'               => $city,
            'state'              => $state,
            'postal_code'        => $postal_code,
            'country'            => $country
        ]);

        $imported++;
    }

    wp_send_json_success([
        'message'  => "Successfully imported {$imported} place(s).",
        'imported' => $imported
    ]);

    wp_die(); // ✅ Always use wp_die in AJAX handlers
}
