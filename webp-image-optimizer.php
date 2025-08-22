<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Plugin Name: WebP Image Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/webp-image-optimizer
 * Description: Automatically converts and compresses uploaded images (JPEG, PNG, GIF) 
 * from their original format to WebP format to improve website performance. 
 * Additionally, it can automatically set the image alt text 
 * based on the image filename, with an option to enable or disable this feature.
 * Version: 1.3.0
 * Author: Arnel Go
 * Author URI: https://arnelgo.info/
 * License: GPLv2 or later
 * Text Domain: webp-image-optimizer
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Define plugin constants
const WIO_VERSION = '1.3.0';
define('WIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes from the /includes directory
spl_autoload_register(function ($class) {
    $prefix = 'WebPImageOptimizer\\';
    $base_dir = __DIR__ . '/includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Main plugin loader class
class WIO_Plugin
{
    public function __construct()
    {
        // Activation hook
        register_activation_hook(__FILE__, ['WebPImageOptimizer\\Admin', 'activate']);
        // Load admin and image logic
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        if (is_admin()) {
            \WebPImageOptimizer\Admin::get_instance();
            add_action('admin_notices', [$this, 'engine_status_notice']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_ajax_wio_save_help_state', [$this, 'ajax_save_help_state']);
        }
        \WebPImageOptimizer\Image::get_instance();
    }

    /**
     * Show a status notice on the settings page indicating active image engine and supported formats.
     */
    public function engine_status_notice()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'webp-image-optimizer') {
            return;
        }
        $engine = 'None';
        $supported = [];
        if (extension_loaded('imagick')) {
            $engine = 'ImageMagick';
            $supported = ['JPEG', 'PNG', 'GIF', 'BMP', 'TIFF'];
        } elseif (extension_loaded('gd')) {
            $engine = 'GD';
            // GD support depends on build; list common ones
            $supported = ['JPEG', 'PNG', 'GIF'];
        }
        $message = sprintf(
            'WebP Image Optimizer status: Engine: %s. Supported source types: %s.',
            esc_html($engine),
            esc_html(implode(', ', $supported))
        );
        echo '<div class="notice notice-info"><p>' . $message . '</p></div>';
    }

    public function enqueue_admin_assets($hook)
    {
        // Only load on our settings page
        $is_settings = isset($_GET['page']) && $_GET['page'] === 'webp-image-optimizer';
        if (!$is_settings) return;

        $version = defined('WIO_VERSION') ? WIO_VERSION : '1.3.0';
        $base = plugin_dir_url(__FILE__);
        wp_enqueue_style('wio-admin', $base . 'assets/css/admin.css', [], $version);
        wp_enqueue_script('wio-admin', $base . 'assets/js/admin.js', ['jquery'], $version, true);

        $help_open = (bool) get_user_meta(get_current_user_id(), 'wio_help_open', true);
        wp_localize_script('wio-admin', 'wioAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wio_help_nonce'),
            'is_open'  => $help_open,
            'labels'   => [
                'show' => __('Show', 'webp-image-optimizer'),
                'hide' => __('Hide', 'webp-image-optimizer'),
            ],
        ]);
    }

    public function ajax_save_help_state()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_help_nonce', 'nonce');
        $open = isset($_POST['open']) && $_POST['open'] === '1';
        update_user_meta(get_current_user_id(), 'wio_help_open', $open);
        wp_send_json_success(['saved' => true, 'open' => $open]);
    }
}

// Initialize the plugin
new WIO_Plugin();
