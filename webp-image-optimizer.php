<?php

/**
 * Plugin Name: WebP Image Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/webp-image-optimizer
 * Description: Automatically converts and compresses uploaded images (JPEG, PNG, GIF) 
 * from their original format to WebP format to improve website performance. 
 * Additionally, it can automatically set the image alt text 
 * based on the image filename, with an option to enable or disable this feature.
 * Version: 1.2.2
 * Author: Arnel Go
 * Author URI: https://arnelgo.info/
 * License: GPLv2 or later
 * Text Domain: webp-image-optimizer
 */

// Include admin dashboard
include plugin_dir_path(__FILE__) . 'admin-dashboard.php';

// Disable WordPress default image sizes and back-sizing
function disable_default_image_sizes($sizes)
{
    unset($sizes['thumbnail']);      // Remove Thumbnail size
    unset($sizes['medium']);         // Remove Medium size
    unset($sizes['medium_large']);   // Remove Medium Large size
    unset($sizes['large']);          // Remove Large size
    // Note: 'full' represents the original upload size and cannot be removed here.
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'disable_default_image_sizes');

function disable_additional_image_sizes()
{
    remove_image_size('1536x1536');  // Remove 2x medium-large size
    remove_image_size('2048x2048');  // Remove 2x large size
}
add_action('init', 'disable_additional_image_sizes');

add_filter('big_image_size_threshold', '__return_false'); // Disable big image scaling

if (!isset($content_width)) {
    $content_width = 1920; // Set max content width to prevent large image generation
}

// Hook into the image upload process to convert images to WebP
add_filter('wp_handle_upload', 'webp_image_optimizer_handle_upload');

function webp_image_optimizer_handle_upload($upload)
{
    $options = get_option('webp_image_optimizer_settings');
    $retain_original = isset($options['retain_original']) ? $options['retain_original'] : false;
    $quality = isset($options['quality']) ? intval($options['quality']) : 80;
    $method = isset($options['method']) ? intval($options['method']) : 6;

    // Define allowed image types
    $allowed_types = isset($options['allowed_types']) && !empty($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];

    if (in_array($upload['type'], $allowed_types, true)) {
        $file_path = $upload['file'];
        $file_info = pathinfo($file_path);

        // Only convert the original full-size image
        if (strpos($file_path, '-scaled') === false && !preg_match('/-\d+x\d+\./', $file_path)) {

            // Check if ImageMagick or GD is available
            if (extension_loaded('imagick')) {
                $image = new Imagick($file_path);

                // Set WebP compression quality and method
                $image->setImageFormat('webp');
                $image->setOption('webp:method', $method);
                $image->setImageCompressionQuality($quality);

                $image->stripImage();

                $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');

                $image->writeImage($new_file_path);
                $image->clear();
                $image->destroy();
            } elseif (extension_loaded('gd')) {
                $image_editor = wp_get_image_editor($file_path);
                if (!is_wp_error($image_editor)) {
                    $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');

                    $saved_image = $image_editor->save($new_file_path, 'image/webp', array('quality' => $quality));
                }
            } else {
                error_log("No suitable image library (ImageMagick or GD) found for WebP conversion.");
                return $upload;
            }

            if (isset($new_file_path) && file_exists($new_file_path)) {
                $upload['file'] = $new_file_path;
                $upload['url'] = str_replace(basename($upload['url']), basename($new_file_path), $upload['url']);
                $upload['type'] = 'image/webp';

                // If retaining original, register it with the media library
                if ($retain_original) {
                    $attachment = array(
                        'guid' => $upload['url'],
                        'post_mime_type' => $upload['type'],
                        'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_path)),
                        'post_content' => '',
                        'post_status' => 'inherit',
                    );
                    wp_insert_attachment($attachment, $file_path);
                } elseif (file_exists($file_path)) {
                    @unlink($file_path); // Delete the original image if not retained
                }
            } else {
                error_log("WebP image conversion failed for file: " . $file_path);
            }
        }
    }

    return $upload;
}

// Hook into the image upload process to set alt text
add_action('add_attachment', 'webp_image_optimizer_set_image_alt_text_on_upload');

function webp_image_optimizer_set_image_alt_text_on_upload($post_ID)
{
    // Get the plugin settings
    $options = get_option('webp_image_optimizer_settings');
    $set_alt_text = isset($options['set_alt_text']) ? $options['set_alt_text'] : false;

    // Check if the setting to automatically set alt text is enabled
    if ($set_alt_text) {
        // Get the attachment post
        $attachment = get_post($post_ID);

        // Ensure it's an image
        if (wp_attachment_is_image($post_ID)) {
            // Get the attachment's title
            $title = $attachment->post_title;

            // Replace hyphens with spaces
            $title = str_replace('-', ' ', $title);

            // Convert to sentence case
            $alt_text = ucfirst(strtolower($title));

            // Update the attachment post meta with the new alt text
            update_post_meta($post_ID, '_wp_attachment_image_alt', $alt_text);
        }
    }
}
