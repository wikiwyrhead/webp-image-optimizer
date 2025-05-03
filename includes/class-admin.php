<?php

namespace WebPImageOptimizer;

if (!defined('ABSPATH')) exit;

class Admin
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public static function activate()
    {
        // Create includes directory if it doesn't exist
        $includes_dir = plugin_dir_path(__FILE__);
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

    public function add_menu()
    {
        add_options_page(
            'WebP Image Optimizer Settings',
            'WebP Image Optimizer',
            'manage_options',
            'webp-image-optimizer',
            [$this, 'settings_page']
        );
    }

    public function settings_init()
    {
        register_setting(
            'webp_image_optimizer_settings',
            'webp_image_optimizer_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]
        );
        add_settings_section(
            'webp_image_optimizer_main_settings',
            __('Main Settings', 'webp-image-optimizer'),
            [$this, 'section_callback'],
            'webp_image_optimizer_settings'
        );
        add_settings_field(
            'retain_original',
            __('Retain Original Image', 'webp-image-optimizer'),
            [$this, 'retain_original_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
        add_settings_field(
            'quality',
            __('Image Quality', 'webp-image-optimizer'),
            [$this, 'quality_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
        add_settings_field(
            'method',
            __('WebP Compression Method', 'webp-image-optimizer'),
            [$this, 'method_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
        add_settings_field(
            'allowed_types',
            __('Allowed Image Types', 'webp-image-optimizer'),
            [$this, 'allowed_types_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
        add_settings_field(
            'set_alt_text',
            __('Set Alt Text', 'webp-image-optimizer'),
            [$this, 'set_alt_text_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
        // Add new setting for PNG lossless/lossy
        add_settings_field(
            'png_lossless',
            __('Use lossless WebP for PNGs', 'webp-image-optimizer'),
            [$this, 'png_lossless_render'],
            'webp_image_optimizer_settings',
            'webp_image_optimizer_main_settings'
        );
    }

    public function sanitize_settings($input)
    {
        $defaults = [
            'quality' => 80,
            'method' => 6,
            'retain_original' => false,
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'set_alt_text' => false,
            'png_lossless' => false
        ];
        $output = $defaults;
        $output['quality'] = isset($input['quality']) ? max(0, min(100, intval($input['quality']))) : $defaults['quality'];
        $output['method'] = isset($input['method']) ? max(0, min(6, intval($input['method']))) : $defaults['method'];
        $output['retain_original'] = !empty($input['retain_original']) ? true : false;
        $output['allowed_types'] = isset($input['allowed_types']) && is_array($input['allowed_types']) ? $input['allowed_types'] : $defaults['allowed_types'];
        $output['set_alt_text'] = !empty($input['set_alt_text']) ? true : false;
        $output['png_lossless'] = !empty($input['png_lossless']) ? true : false;
        return $output;
    }

    public function section_callback()
    {
        echo '<p>' . __('Configure the WebP Image Optimizer settings below:', 'webp-image-optimizer') . '</p>';
    }

    public function retain_original_render()
    {
        $options = get_option('webp_image_optimizer_settings');
?>
        <label for="retain_original">
            <input type='checkbox' name='webp_image_optimizer_settings[retain_original]' <?php checked(isset($options['retain_original']) ? $options['retain_original'] : false); ?> value='1'>
            <?php _e('Check to retain the original image after conversion to WebP.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('If unchecked, the original image will be deleted after successful conversion to WebP.', 'webp-image-optimizer'); ?></p>
    <?php
    }

    public function quality_render()
    {
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

    public function method_render()
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

    public function allowed_types_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
        // Removed SVG from the list
        $all_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff'];
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
        <p class="description"><?php _e('By default, JPEG, PNG, and GIF images are converted. You can extend support to other image formats like BMP and TIFF. SVG is not supported.', 'webp-image-optimizer'); ?></p>
    <?php
    }

    public function set_alt_text_render()
    {
        $options = get_option('webp_image_optimizer_settings');
    ?>
        <label for="set_alt_text">
            <input type='checkbox' name='webp_image_optimizer_settings[set_alt_text]' <?php checked(!empty($options['set_alt_text'])); ?> value='1'>
            <?php _e('Check to automatically set image alt text based on the filename.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('This option helps with SEO by automatically generating descriptive alt text from the image filename.', 'webp-image-optimizer'); ?></p>
    <?php
    }

    public function png_lossless_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $checked = isset($options['png_lossless']) ? (bool)$options['png_lossless'] : true;
    ?>
        <label for="png_lossless">
            <input type='checkbox' name='webp_image_optimizer_settings[png_lossless]' <?php checked($checked); ?> value='1'>
            <?php _e('Check to use lossless WebP for PNG images. Uncheck for smaller, lossy WebP files.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('Lossless WebP preserves all PNG data but may not reduce file size much. Lossy WebP can be much smaller but may lose some quality or transparency.', 'webp-image-optimizer'); ?></p>
    <?php
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'webp-image-optimizer'));
        }
    ?>
        <div class="wrap">
            <h1><?php _e('WebP Image Optimizer Settings', 'webp-image-optimizer'); ?></h1>
            <form action='options.php' method='post'>
                <?php
                wp_nonce_field('webp_image_optimizer_settings_nonce', 'webp_image_optimizer_nonce');
                settings_fields('webp_image_optimizer_settings');
                do_settings_sections('webp_image_optimizer_settings');
                submit_button();
                ?>
            </form>
            <div class="settings-card donation-card" style="margin-top: 30px; margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
                <h2><span class="dashicons dashicons-heart" style="color: #d63638;"></span> <?php _e('Support the Development', 'webp-image-optimizer'); ?></h2>
                <div class="card-content">
                    <div class="donation-content" style="text-align:center;">
                        <p><?php _e('If you find this plugin useful, please consider making a donation to support continued development and maintenance. Your contribution helps keep this plugin updated and compatible with the latest WordPress versions.', 'webp-image-optimizer'); ?></p>
                        <div class="donation-button" style="margin: 20px 0;">
                            <a href="https://www.paypal.com/paypalme/arnelborresgo" target="_blank" class="button button-primary" style="display:inline-flex;align-items:center;justify-content:center;padding:8px 20px;font-size:14px;line-height:2;text-decoration:none;background-color:#0073aa;border-color:#0073aa;color:#fff;border-radius:4px;transition:all 0.3s ease;">
                                <span class="dashicons dashicons-paypal" style="margin-right:8px;font-size:18px;width:18px;height:18px;"></span>
                                <?php _e('Donate with PayPal', 'webp-image-optimizer'); ?>
                            </a>
                        </div>
                        <p class="donation-email" style="margin-top:12px;font-size:12px;color:#646970;font-style:italic;">PayPal Email: arnel.b.go@gmail.com</p>
                    </div>
                </div>
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

                .donation-card {
                    margin-top: 24px;
                }

                .donation-content {
                    text-align: center;
                }

                .donation-button {
                    margin: 20px 0;
                }

                .donation-button .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 8px 20px;
                    height: auto;
                    font-size: 14px;
                    line-height: 2;
                    text-decoration: none;
                    background-color: #0073aa;
                    border-color: #0073aa;
                    color: #fff;
                    border-radius: 4px;
                    transition: all 0.3s ease;
                }

                .donation-button .button:hover {
                    background-color: #005177;
                    border-color: #005177;
                }

                .donation-button .dashicons {
                    margin-right: 8px;
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                }

                .donation-email {
                    margin-top: 12px;
                    font-size: 12px;
                    color: #646970;
                    font-style: italic;
                }

                @media screen and (max-width: 782px) {
                    .donation-card {
                        margin-top: 20px;
                    }

                    .donation-button .button {
                        padding: 6px 16px;
                        font-size: 12px;
                    }

                    .donation-email {
                        font-size: 11px;
                    }
                }
            </style>
        </div>
<?php
    }
}
