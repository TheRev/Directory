<?php
// Admin Search History/Results page for GPD Directory Plugin

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function gpd_render_search_history_page() {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'gpd_search_sessions';

    // Sorting/filtering (expand as needed)
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_fetched';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

    // Pagination setup
    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;

    // Get total session count for pagination
    $total_sessions = (int) $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");

    // Fetch session data
    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $sessions_table ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $per_page, $offset
        ),
        ARRAY_A
    );

    // Helper: Get count of imported places for a session
    function gpd_count_imported_places($session_id) {
        global $wpdb;
        $places_table = $wpdb->prefix . 'gpd_places';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $places_table WHERE session_id = %d", $session_id)
        );
    }

    // Helper: Queue count (customize as needed)
    function gpd_count_in_queue($session) {
        if ($session['is_complete']) return 0;
        // Could estimate remaining via API, or just show "In Progress"
        return $session['last_page_token'] ? 'In Progress' : 'First Page';
    }

    // Admin notice feedback
    if (!empty($_GET['message'])) {
        $msg = sanitize_text_field($_GET['message']);
        echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Search History</h1>
        <p>This page shows all previous search sessions. You can resume incomplete searches or delete sessions and their imported places.</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Search Query</th>
                    <th>Radius</th>
                    <th>Last Searched</th>
                    <th>Imported</th>
                    <th>In Queue</th>
                    <th>Continue</th>
                    <th>Delete All</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($sessions): foreach ($sessions as $session): ?>
                <tr>
                    <td><?php echo esc_html($session['query']); ?></td>
                    <td><?php echo esc_html($session['radius']); ?></td>
                    <td><?php echo esc_html($session['last_fetched']); ?></td>
                    <td><?php echo gpd_count_imported_places($session['id']); ?></td>
                    <td><?php echo esc_html(gpd_count_in_queue($session)); ?></td>
                    <td>
                        <?php if (!$session['is_complete']): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gpd-import&session_id=' . $session['id'])); ?>" style="display:inline;">
                                <?php wp_nonce_field('gpd_resume_session_' . $session['id']); ?>
                                <input type="submit" class="button button-primary" value="Continue" />
                            </form>
                        <?php else: ?>
                            <span style="color:#888;">Complete</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this search session and all its places?');">
                            <input type="hidden" name="gpd_delete_session_id" value="<?php echo esc_attr($session['id']); ?>" />
                            <?php wp_nonce_field('gpd_delete_session_' . $session['id']); ?>
                            <input type="submit" class="button button-secondary" value="Delete" />
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No search sessions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
            // Pagination links
            $total_pages = ceil($total_sessions / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $paged
                ]);
                echo '</div></div>';
            }
        ?>
    </div>
    <?php
}

// Handle delete session POST action
add_action('admin_init', function() {
    if (isset($_POST['gpd_delete_session_id'])) {
        $session_id = intval($_POST['gpd_delete_session_id']);
        check_admin_referer('gpd_delete_session_' . $session_id);

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'gpd_search_sessions';
        $places_table = $wpdb->prefix . 'gpd_places';

        // Delete related places first
        $wpdb->delete($places_table, ['session_id' => $session_id]);
        // Then the session
        $wpdb->delete($sessions_table, ['id' => $session_id]);

        wp_redirect(add_query_arg('message', urlencode('Session deleted.'), admin_url('admin.php?page=gpd-search-history')));
        exit;
    }
});

// Add submenu page (call this from your admin_menu hook)
function gpd_add_search_history_menu() {
    add_submenu_page(
        'gpd-import', // parent slug (adjust as needed)
        'Search History',
        'Search History',
        'manage_options',
        'gpd-search-history',
        'gpd_render_search_history_page'
    );
}
add_action('admin_menu', 'gpd_add_search_history_menu');
