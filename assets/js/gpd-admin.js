<?php
// ✅ Ensure this file is only loaded in the admin area
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ✅ Render Search History Page Function
function gpd_render_search_history_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access', 'gpd'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'gpd_cache'; // Adjusted to the correct table name

    // ✅ Debugging: Check if table exists
    error_log("Checking if table $table_name exists...");
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        error_log("Table $table_name does not exist. Please verify table creation.");
        echo '<div class="error"><p>Cache table does not exist. Please ensure the database is set up correctly.</p></div>';
        return;
    }

    // ✅ Handle Clear Cache Request
    if (isset($_GET['clear_cache']) && !empty($_GET['clear_cache'])) {
        $cache_id = intval($_GET['clear_cache']);
        error_log("Clearing cache entry with ID: $cache_id");
        $wpdb->delete($table_name, ['id' => $cache_id]);
        wp_redirect(admin_url('admin.php?page=gpd-search-history&message=Cache cleared'));
        exit;
    }

    // ✅ Handle Clear All Cache
    if (isset($_GET['clear_all_cache'])) {
        error_log("Clearing all cache entries...");
        $wpdb->query("TRUNCATE TABLE $table_name");
        wp_redirect(admin_url('admin.php?page=gpd-search-history&message=All cache cleared'));
        exit;
    }

    // ✅ Fetch Cache Entries
    error_log("Fetching cache entries from $table_name...");
    $cache_entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY cached_at DESC");

    ?>
    <div class="wrap">
        <h1>Search History</h1>

        <?php if (isset($_GET['message'])) : ?>
            <div class="updated notice is-dismissible"><p><?php echo esc_html($_GET['message']); ?></p></div>
        <?php endif; ?>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Destination</th>
                    <th>Last Cached</th>
                    <th>Pages Cached</th>
                    <th>Items Imported</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($cache_entries)) : ?>
                    <?php foreach ($cache_entries as $entry) : 
                        // ✅ Calculate Pages Cached and Items Imported
                        $response_data = json_decode($entry->response_json, true);
                        $pages_cached = isset($response_data) ? count($response_data) : 0;
                        $items_imported = $entry->items_imported ?? 0;

                        // ✅ Debugging: Log cache entry details
                        error_log("Cache Entry ID: {$entry->id}, Query Hash: {$entry->query_hash}, Pages Cached: $pages_cached, Items Imported: $items_imported");
                    ?>
                        <tr>
                            <td><?php echo esc_html($entry->query_hash); ?></td>
                            <td><?php echo esc_html($entry->cached_at); ?></td>
                            <td><?php echo esc_html($pages_cached); ?></td>
                            <td><?php echo esc_html($items_imported); ?></td>
                            <td>
                                <a href="admin.php?page=gpd-search-history&clear_cache=<?php echo esc_attr($entry->id); ?>" class="button button-secondary">Clear Cache</a>
                                <a href="admin.php?page=gpd-import&query=<?php echo esc_attr($entry->query_hash); ?>" class="button button-primary">Resume Import</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5">No cached searches found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <a href="admin.php?page=gpd-search-history&clear_all_cache=1" class="button button-primary">Clear All Cache</a>
        </p>
    </div>
    <?php
}
