<?php
// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('webp_image_optimizer_settings');
// In case multisite, also delete network options if ever used in future (safe even if not set)
if (is_multisite()) {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('webp_image_optimizer_settings');
        restore_current_blog();
    }
}
