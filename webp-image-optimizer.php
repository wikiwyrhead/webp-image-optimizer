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
 * Version: 1.2.4
 * Author: Arnel Go
 * Author URI: https://arnelgo.info/
 * License: GPLv2 or later
 * Text Domain: webp-image-optimizer
 */

// Define plugin constants
const WIO_VERSION = '1.2.4';
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
        }
        \WebPImageOptimizer\Image::get_instance();
    }
}

// Initialize the plugin
new WIO_Plugin();
