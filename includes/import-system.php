<?php
// Import Page for Google Places API Integration
// This file handles the UI and logic for importing businesses from Google Places API (Text Search).

// Check for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch form inputs
    $textQuery = $_POST['text_query'] ?? '';
    $radius = $_POST['radius'] ?? '5000'; // Default to 5 km (5000 meters)
    $resultsPerPage = $_POST['results_per_page'] ?? '5';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    // Validate required fields
    if (empty($textQuery)) {
        echo "<p style='color: red;'>Text Query is required!</p>";
    } else {
        // Call Google Places API with the form inputs
        $apiKey = 'YOUR_GOOGLE_PLACES_API_KEY';
        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=" . urlencode($textQuery) . "&radius=$radius&key=$apiKey";

        // Optional location parameter
        if (!empty($latitude) && !empty($longitude)) {
            $url .= "&location=$latitude,$longitude";
        }

        // Fetch data from the API
        $response = file_get_contents($url);

        if ($response === FALSE) {
            echo "<p style='color: red;'>Failed to fetch data from Google Places API.</p>";
        } else {
            $results = json_decode($response, true);
            // Display results in a table
            if (!empty($results['results'])) {
                echo "<h3>Search Results</h3>";
                echo "<form method='POST' action='import-handler.php'>";
                echo "<table border='1'>";
                echo "<tr><th>Select</th><th>Name</th><th>Address</th><th>Phone</th><th>Rating</th><th>Website</th></tr>";

                foreach ($results['results'] as $place) {
                    echo "<tr>";
                    echo "<td><input type='checkbox' name='place_ids[]' value='{$place['place_id']}'></td>";
                    echo "<td>{$place['name']}</td>";
                    echo "<td>{$place['formatted_address']}</td>";
                    echo "<td>" . ($place['international_phone_number'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($place['rating'] ?? 'N/A') . "</td>";
                    echo "<td><a href='{$place['website']}' target='_blank'>Website</a></td>";
                    echo "</tr>";
                }

                echo "</table>";
                echo "<button type='submit'>Import Selected Businesses</button>";
                echo "</form>";
            } else {
                echo "<p style='color: red;'>No results found for the given query.</p>";
            }
        }
    }
}

?>

<h2>Google Places Import Page</h2>
<form method="POST" action="">
    <label for="text_query">Text Query:</label>
    <input type="text" id="text_query" name="text_query" placeholder="Scuba Diving Shops Cozumel" required>
    <small>Enter the type of business or location you want to search for.</small>
    <br><br>

    <label for="radius">Radius:</label>
    <select id="radius" name="radius">
        <option value="5000">5 km</option>
        <option value="15000">15 km</option>
        <option value="30000">30 km</option>
        <option value="50000">50 km</option>
    </select>
    <small>Select the search radius in kilometers.</small>
    <br><br>

    <label for="results_per_page">Results Per Page:</label>
    <select id="results_per_page" name="results_per_page">
        <option value="5">5</option>
        <option value="10">10</option>
        <option value="15">15</option>
        <option value="20">20</option>
    </select>
    <small>Choose how many businesses to display per page.</small>
    <br><br>

    <label for="latitude">Optional Location - Latitude:</label>
    <input type="text" id="latitude" name="latitude" placeholder="20.5083">
    <small>Enter latitude to refine the search (optional).</small>
    <br><br>

    <label for="longitude">Optional Location - Longitude:</label>
    <input type="text" id="longitude" name="longitude" placeholder="-86.9458">
    <small>Enter longitude to refine the search (optional).</small>
    <br><br>

    <button type="submit">Search</button>
</form>