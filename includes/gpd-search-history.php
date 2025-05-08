<?php
if (!defined('ABSPATH')) exit;

function gpd_render_search_history_page() {
    global $wpdb;

    $sessions_table   = $wpdb->prefix . 'gpd_search_sessions';
    $cache_table      = $wpdb->prefix . 'gpd_cache';
    $businesses_table = $wpdb->prefix . 'gpd_businesses';

    // Fetch all sessions
    $sessions = $wpdb->get_results("SELECT * FROM $sessions_table ORDER BY last_fetched DESC", ARRAY_A);

    ?>
    <div class="wrap">
        <h1>Search History</h1>
        <p>This page shows previous search sessions.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Search Query</th>
                    <th>Destination</th>
                    <th>Radius</th>
                    <th>Last Searched</th>
                    <th>Cached Shops</th>
                    <th>Imported Shops</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($sessions)): ?>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?php echo esc_html($session['query']); ?></td>
                        <td><?php echo esc_html(isset($session['destination']) ? $session['destination'] : ''); ?></td>
                        <td><?php echo esc_html($session['radius']); ?></td>
                        <td><?php echo esc_html($session['last_fetched']); ?></td>
                        <td>
                            <?php
                                // Use the destination column for robust matching
                                $destination = isset($session['destination']) ? $session['destination'] : $session['query'];
                                $cached_count = $wpdb->get_var(
                                    $wpdb->prepare("SELECT COUNT(*) FROM $cache_table WHERE destination = %s", $destination)
                                );
                                // If no exact match and destination is set, try LIKE as fallback
                                if (!$cached_count && !empty($destination)) {
                                    $cached_count = $wpdb->get_var(
                                        $wpdb->prepare("SELECT COUNT(*) FROM $cache_table WHERE destination LIKE %s", '%' . $destination . '%')
                                    );
                                }
                                echo intval($cached_count);
                            ?>
                        </td>
                        <td>
                            <?php
                                $imported_count = $wpdb->get_var(
                                    $wpdb->prepare("SELECT COUNT(*) FROM $businesses_table WHERE destination = %s", $destination)
                                );
                                // If no exact match and destination is set, try LIKE as fallback
                                if (!$imported_count && !empty($destination)) {
                                    $imported_count = $wpdb->get_var(
                                        $wpdb->prepare("SELECT COUNT(*) FROM $businesses_table WHERE destination LIKE %s", '%' . $destination . '%')
                                    );
                                }
                                echo intval($imported_count);
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gpd-import&session_id=' . $session['id'])); ?>" class="button button-primary">Continue</a>
                            &nbsp;
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this session and all its cached/imported shops?');">
                                <input type="hidden" name="gpd_delete_session_id" value="<?php echo esc_attr($session['id']); ?>" />
                                <?php wp_nonce_field('gpd_delete_session_' . $session['id']); ?>
                                <input type="submit" class="button button-secondary" value="Delete" />
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No search sessions found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Handle delete session POST action
add_action('admin_init', function() {
    if (isset($_POST['gpd_delete_session_id'])) {
        $session_id = intval($_POST['gpd_delete_session_id']);
        check_admin_referer('gpd_delete_session_' . $session_id);

        global $wpdb;
        $sessions_table   = $wpdb->prefix . 'gpd_search_sessions';
        $cache_table      = $wpdb->prefix . 'gpd_cache';
        $businesses_table = $wpdb->prefix . 'gpd_businesses';

        // Get the session data for deletion
        $session = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $sessions_table WHERE id = %d", $session_id),
            ARRAY_A
        );
        if ($session) {
            $destination = isset($session['destination']) ? $session['destination'] : $session['query'];
            $wpdb->delete($cache_table, ['destination' => $destination]);
            $wpdb->delete($businesses_table, ['destination' => $destination]);
        }
        // Delete the session
        $wpdb->delete($sessions_table, ['id' => $session_id]);

        wp_redirect(add_query_arg('message', urlencode('Session and related shops deleted.'), admin_url('admin.php?page=gpd-search-history')));
        exit;
    }
});

if (!function_exists('gpd_add_search_history_menu')) {
    function gpd_add_search_history_menu() {
        add_submenu_page(
            'gpd-import',
            'Search History',
            'Search History',
            'manage_options',
            'gpd-search-history',
            'gpd_render_search_history_page'
        );
    }
    add_action('admin_menu', 'gpd_add_search_history_menu');
}
