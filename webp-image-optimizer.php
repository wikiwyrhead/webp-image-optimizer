<?php
/**
 * Plugin Name: WebP Image Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/webp-image-optimizer
 * Description: Automatically converts and compresses uploaded images (JPEG, PNG, GIF) 
 * from their original format to WebP format to improve website performance. 
 * Additionally, it can automatically set the image alt text 
 * based on the image filename, with an option to enable or disable this feature.
 * Version: 1.2.3
 * Author: Arnel Go
 * Author URI: https://arnelgo.info/
 * License: GPLv2 or later
 * Text Domain: webp-image-optimizer
 */

// Register activation hook
register_activation_hook(__FILE__, 'webp_image_optimizer_activate');

function webp_image_optimizer_activate() {
    // Create includes directory if it doesn't exist
    $includes_dir = plugin_dir_path(__FILE__) . 'includes';
    if (!file_exists($includes_dir)) {
        wp_mkdir_p($includes_dir);
    }

    // Ensure we have proper permissions
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    if (!file_exists($base_dir)) {
        wp_mkdir_p($base_dir);
    }

    // Add a flag for first activation
    if (!get_option('webp_image_optimizer_settings')) {
        // Set default options
        $default_options = array(
            'quality' => 80,
            'method' => 6,
            'retain_original' => true,
            'allowed_types' => array('image/jpeg', 'image/png', 'image/gif'),
            'set_alt_text' => true
        );
        add_option('webp_image_optimizer_settings', $default_options);
    }
}

// Add menu item to Settings
add_action('admin_menu', 'webp_image_optimizer_menu');

function webp_image_optimizer_menu() {
    add_options_page(
        'WebP Image Optimizer Settings',
        'WebP Image Optimizer',
        'manage_options',
        'webp-image-optimizer',
        'webp_image_optimizer_settings_page'
    );
}

// Register settings
add_action('admin_init', 'webp_image_optimizer_settings_init');

function webp_image_optimizer_settings_init() {
    register_setting('webp_image_optimizer_settings', 'webp_image_optimizer_settings');

    add_settings_section(
        'webp_image_optimizer_main_settings',
        __('Main Settings', 'webp-image-optimizer'),
        'webp_image_optimizer_section_callback',
        'webp_image_optimizer_settings'
    );

    add_settings_field(
        'retain_original',
        __('Retain Original Image', 'webp-image-optimizer'),
        'webp_image_optimizer_retain_original_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );

    add_settings_field(
        'quality',
        __('Image Quality', 'webp-image-optimizer'),
        'webp_image_optimizer_quality_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );

    add_settings_field(
        'method',
        __('WebP Compression Method', 'webp-image-optimizer'),
        'webp_image_optimizer_method_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );

    add_settings_field(
        'allowed_types',
        __('Allowed Image Types', 'webp-image-optimizer'),
        'webp_image_optimizer_allowed_types_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );

    add_settings_field(
        'set_alt_text',
        __('Set Alt Text', 'webp-image-optimizer'),
        'webp_image_optimizer_set_alt_text_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );
}

function webp_image_optimizer_section_callback() {
    echo '<p>' . __('Configure the WebP Image Optimizer settings below:', 'webp-image-optimizer') . '</p>';
}

function webp_image_optimizer_retain_original_render() {
    $options = get_option('webp_image_optimizer_settings');
    ?>
    <label for="retain_original">
        <input type='checkbox' name='webp_image_optimizer_settings[retain_original]' <?php checked(isset($options['retain_original']) ? $options['retain_original'] : false); ?> value='1'>
        <?php _e('Check to retain the original image after conversion to WebP.', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('If unchecked, the original image will be deleted after successful conversion to WebP.', 'webp-image-optimizer'); ?></p>
    <?php
}

function webp_image_optimizer_quality_render() {
    $options = get_option('webp_image_optimizer_settings');
    $quality = isset($options['quality']) ? intval($options['quality']) : 80;
    ?>
    <label for="quality">
        <input type='number' name='webp_image_optimizer_settings[quality]' value='<?php echo esc_attr($quality); ?>' min='0' max='100' step='1'>
        <?php _e('Set the quality of WebP images (0-100).', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('Higher values result in better quality but larger file sizes. The default value is 80.', 'webp-image-optimizer'); ?></p>
    <?php
}

function webp_image_optimizer_method_render() {
    $options = get_option('webp_image_optimizer_settings');
    $method = isset($options['method']) ? intval($options['method']) : 6;
    ?>
    <label for="method">
        <input type='number' name='webp_image_optimizer_settings[method]' value='<?php echo esc_attr($method); ?>' min='0' max='6' step='1'>
        <?php _e('Set the WebP compression method (0-6).', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('Higher values result in better compression (smaller file sizes) but slower conversion times. The default value is 6.', 'webp-image-optimizer'); ?></p>
    <?php
}

function webp_image_optimizer_allowed_types_render() {
    $options = get_option('webp_image_optimizer_settings');
    $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
    $all_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'];
    ?>
    <p><?php _e('Select the image types that should be automatically converted to WebP:', 'webp-image-optimizer'); ?></p>
    <?php
    foreach ($all_types as $type) {
        ?>
        <label for="allowed_types">
            <input type='checkbox' name='webp_image_optimizer_settings[allowed_types][]' <?php checked(in_array($type, $allowed_types)); ?> value='<?php echo esc_attr($type); ?>'>
            <?php echo esc_html($type); ?>
        </label><br>
        <?php
    }
    ?>
    <p class="description"><?php _e('By default, JPEG, PNG, and GIF images are converted. You can extend support to other image formats like BMP, TIFF, and SVG.', 'webp-image-optimizer'); ?></p>
    <?php
}

function webp_image_optimizer_set_alt_text_render() {
    $options = get_option('webp_image_optimizer_settings');
    ?>
    <label for="set_alt_text">
        <input type='checkbox' name='webp_image_optimizer_settings[set_alt_text]' <?php checked(isset($options['set_alt_text'])); ?> value='1'>
        <?php _e('Check to automatically set image alt text based on the filename.', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('This option helps with SEO by automatically generating descriptive alt text from the image filename.', 'webp-image-optimizer'); ?></p>
    <?php
}

function webp_image_optimizer_settings_page() {
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'webp-image-optimizer'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('WebP Image Optimizer Settings', 'webp-image-optimizer'); ?></h1>
        <form action='options.php' method='post'>
            <?php
            // Add nonce for security
            wp_nonce_field('webp_image_optimizer_settings_nonce', 'webp_image_optimizer_nonce');
            settings_fields('webp_image_optimizer_settings');
            do_settings_sections('webp_image_optimizer_settings');
            submit_button();
            ?>
        </form>
    </div>
    <style>
        .wrap h1 {
            font-size: 2em;
            color: #0073aa;
        }

        .wrap form {
            background-color: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .wrap label {
            font-weight: bold;
        }

        .description {
            font-style: italic;
            color: #555;
        }
    </style>
    <?php
}

// Hook into the upload process
add_filter('wp_handle_upload', 'webp_image_optimizer_handle_upload');

// Main function to convert uploaded images to WebP
function webp_image_optimizer_handle_upload($upload) {
    try {
        // Get plugin options
        $options = get_option('webp_image_optimizer_settings');
        $quality = isset($options['quality']) ? intval($options['quality']) : 80;
        $method = isset($options['method']) ? intval($options['method']) : 6;
        $retain_original = isset($options['retain_original']) ? $options['retain_original'] : false;
        
        // Validate quality and method values
        $quality = max(0, min(100, $quality));
        $method = max(0, min(6, $method));

        // Define allowed image types
        $allowed_types = isset($options['allowed_types']) && !empty($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($upload['type'], $allowed_types, true)) {
            $file_path = $upload['file'];
            $file_info = pathinfo($file_path);

            // Verify file exists and is readable
            if (!file_exists($file_path) || !is_readable($file_path)) {
                return $upload;
            }

            // Skip if already WebP
            if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
                return $upload;
            }

            // Only convert the original full-size image
            if (strpos($file_path, '-scaled') === false && !preg_match('/-\d+x\d+\./', $file_path)) {

                // Check if ImageMagick or GD is available
                if (extension_loaded('imagick')) {
                    $image = new Imagick($file_path);

                    // Set WebP compression quality and method
                    $image->setImageFormat('webp');
                    $image->setOption('webp:method', $method);
                    $image->setImageCompressionQuality($quality);

                    // Enable lossless compression for PNG images
                    if ($upload['type'] === 'image/png') {
                        $image->setOption('webp:lossless', 'true');
                    }

                    $image->stripImage();

                    $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');

                    if (!$image->writeImage($new_file_path)) {
                        return $upload;
                    }

                    $image->clear();
                    $image->destroy();
                } elseif (extension_loaded('gd')) {
                    $image_editor = wp_get_image_editor($file_path);
                    if (is_wp_error($image_editor)) {
                        return $upload;
                    }

                    $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');
                    $saved_image = $image_editor->save($new_file_path, 'image/webp', array('quality' => $quality));

                    if (is_wp_error($saved_image)) {
                        return $upload;
                    }
                } else {
                    return $upload;
                }

                if (isset($new_file_path) && file_exists($new_file_path)) {
                    // Update upload data to point to the new WebP file
                    $upload['file'] = $new_file_path;
                    $upload['url'] = str_replace(basename($upload['url']), basename($new_file_path), $upload['url']);
                    $upload['type'] = 'image/webp';

                    // If not retaining original, delete it
                    if (!$retain_original && file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail and return original upload
    }

    return $upload;
}

// Hook into the image upload process to set alt text
add_action('add_attachment', 'webp_image_optimizer_set_image_alt_text_on_upload');

// Function to set alt text based on filename
function webp_image_optimizer_set_image_alt_text_on_upload($post_ID) {
    // Check if this is an image
    if (!wp_attachment_is_image($post_ID)) {
        return;
    }

    // Get plugin options
    $options = get_option('webp_image_optimizer_settings');
    $set_alt_text = isset($options['set_alt_text']) ? $options['set_alt_text'] : false;

    // Only proceed if the option is enabled
    if (!$set_alt_text) {
        return;
    }

    // Get the image filename
    $filename = get_the_title($post_ID);

    // Remove extension
    $filename = preg_replace('/\.[^.]+$/', '', $filename);

    // Replace hyphens and underscores with spaces
    $filename = str_replace(array('-', '_'), ' ', $filename);

    // Convert to sentence case (first letter uppercase, rest lowercase)
    $alt_text = ucfirst(strtolower($filename));

    // Update the alt text
    update_post_meta($post_ID, '_wp_attachment_image_alt', $alt_text);
}
