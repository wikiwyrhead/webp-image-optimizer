<?php

// Add menu item to Settings
add_action('admin_menu', 'webp_image_optimizer_menu');

function webp_image_optimizer_menu()
{
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

function webp_image_optimizer_settings_init()
{
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
        __('Set Alt Text from Filename', 'webp-image-optimizer'),
        'webp_image_optimizer_set_alt_text_render',
        'webp_image_optimizer_settings',
        'webp_image_optimizer_main_settings'
    );
}

function webp_image_optimizer_section_callback()
{
    echo '<p>' . __('Configure your WebP image optimization settings below. Each option has a detailed explanation to help you make the best choice for your site.', 'webp-image-optimizer') . '</p>';
}

function webp_image_optimizer_retain_original_render()
{
    $options = get_option('webp_image_optimizer_settings');
?>
    <label for="retain_original">
        <input type='checkbox' name='webp_image_optimizer_settings[retain_original]' <?php checked(isset($options['retain_original'])); ?> value='1'>
        <?php _e('Check this box to retain the original image file after conversion to WebP.', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('If unchecked, the original image will be deleted after successful conversion to WebP, saving disk space.', 'webp-image-optimizer'); ?></p>
<?php
}

function webp_image_optimizer_quality_render()
{
    $options = get_option('webp_image_optimizer_settings');
    $quality = isset($options['quality']) ? intval($options['quality']) : 80;
?>
    <label for="quality">
        <input type='number' name='webp_image_optimizer_settings[quality]' value='<?php echo esc_attr($quality); ?>' min='0' max='100' step='1'>
        <?php _e('Set the desired quality for the WebP images (0-100).', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('A higher quality setting will result in larger file sizes but better image quality. The default value is 80.', 'webp-image-optimizer'); ?></p>
<?php
}

function webp_image_optimizer_method_render()
{
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

function webp_image_optimizer_allowed_types_render()
{
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

function webp_image_optimizer_set_alt_text_render()
{
    $options = get_option('webp_image_optimizer_settings');
?>
    <label for="set_alt_text">
        <input type='checkbox' name='webp_image_optimizer_settings[set_alt_text]' <?php checked(isset($options['set_alt_text'])); ?> value='1'>
        <?php _e('Check to automatically set image alt text based on the filename.', 'webp-image-optimizer'); ?>
    </label>
    <p class="description"><?php _e('This option helps with SEO by automatically generating descriptive alt text from the image filename.', 'webp-image-optimizer'); ?></p>
<?php
}

function webp_image_optimizer_settings_page()
{
?>
    <div class="wrap">
        <h1><?php _e('WebP Image Optimizer Settings', 'webp-image-optimizer'); ?></h1>
        <form action='options.php' method='post'>
            <?php
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
