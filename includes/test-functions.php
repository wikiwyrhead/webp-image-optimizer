<?php
/**
 * Test functions for WebP Image Optimizer
 * 
 * These functions help verify that the plugin is working correctly
 */

// Don't allow direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test the "Retain Original Image" functionality
 */
function webp_image_optimizer_test_retain_original() {
    // Check if we're in the admin area and have the right permissions
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    // Get the current setting
    $options = get_option('webp_image_optimizer_settings');
    $retain_original = isset($options['retain_original']) ? $options['retain_original'] : false;
    
    // Output the test results
    ?>
    <div class="notice notice-info is-dismissible">
        <h3><?php _e('WebP Image Optimizer - "Retain Original Image" Test', 'webp-image-optimizer'); ?></h3>
        <p><?php _e('Current setting:', 'webp-image-optimizer'); ?> <strong><?php echo $retain_original ? __('Enabled', 'webp-image-optimizer') : __('Disabled', 'webp-image-optimizer'); ?></strong></p>
        <p><?php _e('When this setting is enabled:', 'webp-image-optimizer'); ?></p>
        <ul style="list-style-type: disc; padding-left: 20px;">
            <li><?php _e('Original images will be kept after conversion to WebP', 'webp-image-optimizer'); ?></li>
            <li><?php _e('Both original and WebP versions will be available in the media library', 'webp-image-optimizer'); ?></li>
            <li><?php _e('More disk space will be used', 'webp-image-optimizer'); ?></li>
        </ul>
        <p><?php _e('When this setting is disabled:', 'webp-image-optimizer'); ?></p>
        <ul style="list-style-type: disc; padding-left: 20px;">
            <li><?php _e('Original images will be deleted after successful conversion to WebP', 'webp-image-optimizer'); ?></li>
            <li><?php _e('Only WebP versions will be available in the media library', 'webp-image-optimizer'); ?></li>
            <li><?php _e('Disk space will be saved', 'webp-image-optimizer'); ?></li>
        </ul>
        <p>
            <a href="<?php echo admin_url('options-general.php?page=webp-image-optimizer'); ?>" class="button button-primary">
                <?php _e('Change Setting', 'webp-image-optimizer'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * AJAX handler for the test button
 */
function webp_image_optimizer_ajax_test_retain_original() {
    check_ajax_referer('webp_test_retain_original', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    
    // Get the current setting
    $options = get_option('webp_image_optimizer_settings');
    $retain_original = isset($options['retain_original']) ? $options['retain_original'] : false;
    
    // Check the media library for original and WebP images
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
        'posts_per_page' => 10,
        'post_status' => 'inherit'
    );
    
    $query = new WP_Query($args);
    $webp_count = 0;
    $original_count = 0;
    
    foreach ($query->posts as $post) {
        if (get_post_mime_type($post->ID) === 'image/webp') {
            $webp_count++;
        } else {
            $original_count++;
        }
    }
    
    $message = sprintf(
        __('Test Results: Found %d WebP images and %d original images in your media library. The "Retain Original Image" setting is currently %s.', 'webp-image-optimizer'),
        $webp_count,
        $original_count,
        $retain_original ? __('enabled', 'webp-image-optimizer') : __('disabled', 'webp-image-optimizer')
    );
    
    wp_send_json_success(array('message' => $message));
}

// Register the AJAX handler
add_action('wp_ajax_test_retain_original', 'webp_image_optimizer_ajax_test_retain_original');
