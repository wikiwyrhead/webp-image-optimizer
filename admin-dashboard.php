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
        null,
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

function webp_image_optimizer_retain_original_render()
{
    $options = get_option('webp_image_optimizer_settings');
?>
    <input type='checkbox' name='webp_image_optimizer_settings[retain_original]' <?php checked(isset($options['retain_original'])); ?> value='1'>
    <label for="retain_original"><?php _e('Check to retain the original image after conversion to WebP.', 'webp-image-optimizer'); ?></label>
<?php
}

function webp_image_optimizer_quality_render()
{
    $options = get_option('webp_image_optimizer_settings');
    $quality = isset($options['quality']) ? $options['quality'] : 80;
?>
    <input type='number' name='webp_image_optimizer_settings[quality]' value='<?php echo esc_attr($quality); ?>' min='0' max='100'>
    <label for="quality"><?php _e('Set the compression quality (0-100) for WebP images.', 'webp-image-optimizer'); ?></label>
    <?php
}

function webp_image_optimizer_allowed_types_render()
{
    $options = get_option('webp_image_optimizer_settings');
    $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
    $all_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'];
    foreach ($all_types as $type) {
    ?>
        <input type='checkbox' name='webp_image_optimizer_settings[allowed_types][]' <?php checked(in_array($type, $allowed_types)); ?> value='<?php echo esc_attr($type); ?>'>
        <label for="allowed_types"><?php echo esc_html($type); ?></label><br>
    <?php
    }
}

function webp_image_optimizer_set_alt_text_render()
{
    $options = get_option('webp_image_optimizer_settings');
    ?>
    <input type='checkbox' name='webp_image_optimizer_settings[set_alt_text]' <?php checked(isset($options['set_alt_text'])); ?> value='1'>
    <label for="set_alt_text"><?php _e('Automatically set the image alt text from the filename (removes hyphens and converts to sentence case).', 'webp-image-optimizer'); ?></label>
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
            margin-bottom: 20px;
        }

        .wrap table.form-table th {
            font-weight: 600;
        }

        .wrap input[type="checkbox"] {
            margin-right: 10px;
        }

        .wrap .form-table tr {
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
        }

        .wrap .form-table tr:last-child {
            border-bottom: none;
        }
    </style>
<?php
}
