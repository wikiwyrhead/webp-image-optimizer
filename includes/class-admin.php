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
                'set_alt_text' => true,
                'png_lossless' => false
            );
            add_option('webp_image_optimizer_settings', $default_options);
        }
    }

    public function add_menu()
    {
        // Move page from Settings to Tools menu
        add_management_page(
            'WebP Image Optimizer',
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
            'retain_original' => true,
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'set_alt_text' => true,
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
            <input type='checkbox' name='webp_image_optimizer_settings[retain_original]' <?php checked(isset($options['retain_original']) ? $options['retain_original'] : true); ?> value='1'>
            <?php _e('Check to retain the original image after conversion to WebP.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('If unchecked, the original image will be deleted after successful conversion to WebP.', 'webp-image-optimizer'); ?></p>
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#retain-original-image'); ?>" data-section="retain-original-image" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
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
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#image-quality'); ?>" data-section="image-quality" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
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
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#webp-compression-method'); ?>" data-section="webp-compression-method" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
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
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#allowed-image-types'); ?>" data-section="allowed-image-types" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
<?php
    }

    public function set_alt_text_render()
    {
        $options = get_option('webp_image_optimizer_settings');
?>
        <label for="set_alt_text">
            <?php $checked = isset($options['set_alt_text']) ? (bool)$options['set_alt_text'] : true; ?>
            <input type='checkbox' name='webp_image_optimizer_settings[set_alt_text]' <?php checked($checked); ?> value='1'>
            <?php _e('Check to automatically set image alt text based on the filename.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('This option helps with SEO by automatically generating descriptive alt text from the image filename.', 'webp-image-optimizer'); ?></p>
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#set-alt-text'); ?>" data-section="set-alt-text" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
<?php
    }

    public function png_lossless_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $checked = isset($options['png_lossless']) ? (bool)$options['png_lossless'] : false;
?>
        <label for="png_lossless">
            <input type='checkbox' name='webp_image_optimizer_settings[png_lossless]' <?php checked($checked); ?> value='1'>
            <?php _e('Check to use lossless WebP for PNG images. Uncheck for smaller, lossy WebP files.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('Lossless WebP preserves all PNG data but may not reduce file size much. Lossy WebP can be much smaller but may lose some quality or transparency.', 'webp-image-optimizer'); ?></p>
        <p class="description"><a class="wio-learn-more" href="<?php echo esc_url('https://github.com/wikiwyrhead/webp-image-optimizer#png-lossless'); ?>" data-section="png-lossless" target="_blank" rel="noopener noreferrer"><?php _e('Learn more', 'webp-image-optimizer'); ?></a></p>
<?php
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'webp-image-optimizer'));
        }
    ?>
        <div class="wrap">
            <h1><?php _e('WebP Image Optimizer', 'webp-image-optimizer'); ?></h1>
            <form action='options.php' method='post'>
                <?php
                wp_nonce_field('webp_image_optimizer_settings_nonce', 'webp_image_optimizer_nonce');
                settings_fields('webp_image_optimizer_settings');
                do_settings_sections('webp_image_optimizer_settings');
                submit_button();
                ?>
            </form>
            <?php
                // Inline help: show engine info and guidance
                $engine = 'None';
                $supported = [];
                if (extension_loaded('imagick')) {
                    $engine = 'ImageMagick';
                    $supported = ['JPEG', 'PNG', 'GIF', 'BMP', 'TIFF'];
                } elseif (extension_loaded('gd')) {
                    $engine = 'GD';
                    $supported = ['JPEG', 'PNG', 'GIF'];
                }
                $media_url = admin_url('upload.php');
                $site_health_url = function_exists('admin_url') ? admin_url('site-health.php') : '';
                $docs_url = 'https://github.com/wikiwyrhead/webp-image-optimizer#readme';
            ?>
            <div class="settings-card help-card" style="margin-top: 20px; padding: 0; background: #fff; border: 1px solid #ccd0d4; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,.05);">
                <div class="help-card-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #ccd0d4;">
                    <h2 style="margin:0;"><?php _e('Help & Tips', 'webp-image-optimizer'); ?></h2>
                    <button type="button" class="button" id="wio-help-toggle" aria-expanded="false" aria-controls="wio-help-content" style="margin-left:12px;" data-show="<?php echo esc_attr(__('Show', 'webp-image-optimizer')); ?>" data-hide="<?php echo esc_attr(__('Hide', 'webp-image-optimizer')); ?>">
                        <?php _e('Show', 'webp-image-optimizer'); ?>
                    </button>
                </div>
                <div id="wio-help-content" style="display:none;padding: 16px;">
                    <p><strong><?php _e('Current Engine:', 'webp-image-optimizer'); ?></strong> <?php echo esc_html($engine); ?> | <strong><?php _e('Supported types:', 'webp-image-optimizer'); ?></strong> <?php echo esc_html(implode(', ', $supported)); ?></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php _e('PNG Lossless preserves pixel data and may ignore the Quality setting. Disable for smaller, lossy PNG WebP files.', 'webp-image-optimizer'); ?></li>
                        <li><?php _e('If Imagick is unavailable, GD will be used. Some formats (e.g., TIFF/BMP) may not convert under GD builds.', 'webp-image-optimizer'); ?></li>
                        <li><?php _e('Retain Original keeps source files for safety. Uncheck to save disk space after verifying WebP output.', 'webp-image-optimizer'); ?></li>
                        <li><?php _e('Alt Text is derived from filenames. You can fine-tune it later in the Media Library.', 'webp-image-optimizer'); ?></li>
                    </ul>
                    <p style="margin-top:12px;">
                        <a href="<?php echo esc_url($media_url); ?>" class="button-link"><?php _e('Open Media Library', 'webp-image-optimizer'); ?></a>
                        <?php if (!empty($site_health_url)) : ?>
                            | <a href="<?php echo esc_url($site_health_url); ?>" class="button-link"><?php _e('Check Site Health', 'webp-image-optimizer'); ?></a>
                        <?php endif; ?>
                        | <a href="<?php echo esc_url($docs_url); ?>" target="_blank" rel="noopener noreferrer" class="button-link"><?php _e('Read Documentation', 'webp-image-optimizer'); ?></a>
                    </p>
                </div>
            </div>
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
            <!-- Modal for Learn More -->
            <div id="wio-modal" class="wio-modal" aria-hidden="true" role="dialog" aria-modal="true" style="display:none;">
                <div class="wio-modal__backdrop" data-close="1"></div>
                <div class="wio-modal__dialog" role="document">
                    <div class="wio-modal__header">
                        <h3 class="wio-modal__title"><?php _e('Documentation', 'webp-image-optimizer'); ?></h3>
                        <button type="button" class="button button-link wio-modal__close" aria-label="<?php esc_attr_e('Close', 'webp-image-optimizer'); ?>" data-close="1">&times;</button>
                    </div>
                    <div class="wio-modal__body" id="wio-modal-docs">
                        <div class="wio-docs">
                            <div class="wio-docs__layout">
                                <nav class="wio-docs__nav" aria-label="<?php esc_attr_e('Documentation navigation', 'webp-image-optimizer'); ?>">
                                    <ul>
                                        <li><a href="#image-quality" class="wio-docs__link" data-section="image-quality"><?php _e('Image Quality', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#webp-compression-method" class="wio-docs__link" data-section="webp-compression-method"><?php _e('WebP Compression Method', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#retain-original-image" class="wio-docs__link" data-section="retain-original-image"><?php _e('Retain Original Image', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#allowed-image-types" class="wio-docs__link" data-section="allowed-image-types"><?php _e('Allowed Image Types', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#set-alt-text" class="wio-docs__link" data-section="set-alt-text"><?php _e('Set Alt Text', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#png-lossless" class="wio-docs__link" data-section="png-lossless"><?php _e('PNG Lossless', 'webp-image-optimizer'); ?></a></li>
                                    </ul>
                                </nav>
                                <div class="wio-docs__content">
                                    <h3><?php _e('Configuration', 'webp-image-optimizer'); ?></h3>
                                    <p><?php _e('This page explains how each setting affects image output, site performance, and SEO. Use the tips and examples below to choose values that balance quality and speed for your specific site.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="image-quality"><?php _e('Image Quality', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Controls the trade-off between visual fidelity and file size. Range: 0–100. Higher values look better but create larger files.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('Recommended starting point: 80 (good balance for most sites).', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Photographs and blog images: 75–85.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Screenshots/graphics with text: 80–90 to keep crisp edges.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('If performance is critical, try 70–75 and compare before/after visually.', 'webp-image-optimizer'); ?></li>
                                    </ul>
                                    <p><?php _e('Tip: Always compare a few pages on desktop and mobile after changing this value to ensure text and faces remain clear.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="webp-compression-method"><?php _e('WebP Compression Method', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Encoder effort level from 0 (fastest) to 6 (best compression). Higher values reduce size a bit more but take longer to generate.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('Default 6 is safe for most sites and only affects conversion time, not request time.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('On very slow or resource-limited servers try 4–5 to speed up uploads.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('There is no visual difference between methods at the same quality—only file size and processing time change.', 'webp-image-optimizer'); ?></li>
                                    </ul>

                                    <h4 id="retain-original-image"><?php _e('Retain Original Image', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Keeps the original file next to the WebP. This is helpful for rollback, theme compatibility testing, and external tools that might still expect JPEG/PNG.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('Recommended: Keep originals on at first. Browse key pages and confirm images render correctly across your theme.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('After confirming, you may disable this to save disk space. You can re-enable at any time for future uploads.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Note: Keeping originals increases storage usage, especially for large libraries.', 'webp-image-optimizer'); ?></li>
                                    </ul>

                                    <h4 id="allowed-image-types"><?php _e('Allowed Image Types', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Choose which formats are converted automatically. JPEG, PNG, and GIF are enabled by default. You can include BMP and TIFF if you use them.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('SVG is not supported (it is vector-based and should be optimized separately).', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('BMP/TIFF are uncommon on the web and typically very large—enable only if needed.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Your server must have GD or Imagick; the plugin automatically uses what is available.', 'webp-image-optimizer'); ?></li>
                                    </ul>

                                    <h4 id="set-alt-text"><?php _e('Set Alt Text', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Generates alt text from the filename on upload (removes hyphens and applies sentence case). This improves accessibility and SEO by giving images meaningful descriptions.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('Example: "team-photo-2024.jpg" → "Team photo 2024".', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('You can refine alt text in the Media Library for important images (e.g., product shots).', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Avoid keyword stuffing—keep alt text concise and descriptive.', 'webp-image-optimizer'); ?></li>
                                    </ul>

                                    <h4 id="png-lossless"><?php _e('PNG Lossless', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Uses lossless WebP for PNG uploads, preserving every pixel. Ideal for UI assets, logos, diagrams, and images with sharp lines or text.', 'webp-image-optimizer'); ?></p>
                                    <ul>
                                        <li><?php _e('Lossless may ignore the quality value and often results in larger files than lossy.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('For photos or complex illustrations, lossy WebP is usually much smaller with minimal visual difference.', 'webp-image-optimizer'); ?></li>
                                        <li><?php _e('Transparency is preserved in both lossy and lossless WebP.', 'webp-image-optimizer'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wio-modal__footer">
                        <a href="#" target="_blank" rel="noopener noreferrer" id="wio-open-new" class="button button-secondary"><?php _e('Open in new tab', 'webp-image-optimizer'); ?></a>
                        <button type="button" class="button button-primary" data-close="1"><?php _e('Close', 'webp-image-optimizer'); ?></button>
                    </div>
                </div>
            </div>
            
        </div>
<?php
    }
}
