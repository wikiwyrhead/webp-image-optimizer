<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Plugin Name: WebP Image Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/webp-image-optimizer
 * Description: Automatically converts uploaded images to WebP format with bulk conversion, 
 * statistics dashboard, HEIF/HEIC support, and AI-powered alt text generation via 8 providers 
 * (NVIDIA Build, OpenAI, Anthropic Claude, Google Gemini, xAI Grok, Mistral, Groq, OpenRouter).
 * Version: 2.0.0
 * Author: Arnel Go
 * Author URI: https://www.arnelbg.com/
 * License: GPLv2 or later
 * Text Domain: webp-image-optimizer
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Define plugin constants
const WIO_VERSION = '2.0.0';
define('WIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIO_PLUGIN_URL', plugin_dir_url(__FILE__));

/** Supported file extensions for cleanup and conversion */
const WIO_SUPPORTED_EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'heic', 'heif'];

/** Maximum file size (bytes) for conversion – files larger than this are skipped */
const WIO_MAX_CONVERT_SIZE = 50 * 1024 * 1024;

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
        register_activation_hook(__FILE__, ['WebPImageOptimizer\\Admin', 'activate']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        load_plugin_textdomain('webp-image-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
        if (is_admin()) {
            \WebPImageOptimizer\Admin::get_instance();
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_ajax_wio_save_help_state', [$this, 'ajax_save_help_state']);
            add_action('wp_ajax_wio_bulk_convert', [$this, 'ajax_bulk_convert']);
            add_action('wp_ajax_wio_bulk_status', [$this, 'ajax_bulk_status']);
            add_action('wp_ajax_wio_test_ai', [$this, 'ajax_test_ai']);
            add_action('wp_ajax_wio_generate_alt', [$this, 'ajax_generate_alt']);
            add_action('wp_ajax_wio_bulk_alt_status', [$this, 'ajax_bulk_alt_status']);
            add_filter('attachment_fields_to_edit', [$this, 'add_ai_alt_button'], 10, 2);
        }
        \WebPImageOptimizer\Image::get_instance();
    }

    /**
     * Detect the active image engine and supported source formats.
     *
     * @return array{engine: string, supported: string[]}
     */
    public static function detect_engine()
    {
        if (extension_loaded('imagick')) {
            $supported = ['JPEG', 'PNG', 'GIF', 'BMP', 'TIFF'];
            // Check if Imagick supports HEIC
            try {
                $formats = \Imagick::queryFormats('HEIC');
                if (!empty($formats)) {
                    $supported[] = 'HEIC/HEIF';
                }
            } catch (\Exception $e) {
                // HEIC not available
            }
            return ['engine' => 'ImageMagick', 'supported' => $supported];
        } elseif (extension_loaded('gd')) {
            return ['engine' => 'GD', 'supported' => ['JPEG', 'PNG', 'GIF']];
        }
        return ['engine' => 'None', 'supported' => []];
    }

    /**
     * Gather conversion statistics from the Media Library.
     *
     * @return array{total_images: int, webp_count: int, non_webp: int, bytes_saved: int}
     */
    public static function get_stats()
    {
        $stats = get_transient('wio_stats');
        if ($stats !== false) {
            return $stats;
        }

        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
        $webp  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = 'image/webp'");

        // Estimate bytes saved from stored meta
        $bytes_saved = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(CAST(m.meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} m WHERE m.meta_key = '_wio_bytes_saved'"
        );

        $stats = [
            'total'       => $total,
            'webp'        => $webp,
            'non_webp'    => max(0, $total - $webp),
            'bytes_saved' => $bytes_saved,
        ];
        set_transient('wio_stats', $stats, 5 * MINUTE_IN_SECONDS);
        return $stats;
    }

    public function enqueue_admin_assets($hook)
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $is_plugin_page = ($page === 'webp-image-optimizer');
        $is_media_page = in_array($hook, ['upload.php', 'post.php'], true);

        if (!$is_plugin_page && !$is_media_page) return;

        $version = WIO_VERSION;
        $base = plugin_dir_url(__FILE__);

        if ($is_plugin_page) {
            wp_enqueue_style('wio-admin', $base . 'assets/css/admin.css', [], $version);
        }

        wp_enqueue_script('wio-admin', $base . 'assets/js/admin.js', ['jquery'], $version, true);

        $help_open = (bool) get_user_meta(get_current_user_id(), 'wio_help_open', true);
        wp_localize_script('wio-admin', 'wioAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wio_nonce'),
            'is_open'  => $help_open,
            'stats'    => self::get_stats(),
            'labels'   => [
                'show'       => __('Show', 'webp-image-optimizer'),
                'hide'       => __('Hide', 'webp-image-optimizer'),
                'converting' => __('Converting…', 'webp-image-optimizer'),
                'done'       => __('Done!', 'webp-image-optimizer'),
                'error'      => __('Error', 'webp-image-optimizer'),
                'confirm_bulk' => __('Convert all eligible images in your Media Library to WebP? This may take a while.', 'webp-image-optimizer'),
            ],
        ]);
    }

    /**
     * AJAX handler: test AI provider connection.
     */
    public function ajax_test_ai()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        $api_key  = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $model    = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';

        // If the key looks masked, use the stored one
        if (empty($api_key) || strpos($api_key, '••••') !== false) {
            $options = get_option('webp_image_optimizer_settings');
            $api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        }

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('No API key provided.', 'webp-image-optimizer')]);
        }

        $image_handler = \WebPImageOptimizer\Image::get_instance();
        $result = $image_handler->test_ai_connection($provider, $api_key, $model);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_save_help_state()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');
        $open = isset($_POST['open']) && $_POST['open'] === '1';
        update_user_meta(get_current_user_id(), 'wio_help_open', $open);
        wp_send_json_success(['saved' => true, 'open' => $open]);
    }

    /**
     * AJAX handler: convert a single attachment by ID (called in a loop by JS).
     */
    public function ajax_bulk_convert()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            wp_send_json_error(['message' => 'File not found']);
        }

        $mime = get_post_mime_type($attachment_id);
        if ($mime === 'image/webp') {
            wp_send_json_error(['message' => 'Already WebP']);
        }

        $options = get_option('webp_image_optimizer_settings');
        $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime, $allowed_types, true)) {
            wp_send_json_error(['message' => 'Type not allowed']);
        }

        // Use the Image class to convert
        $image_handler = \WebPImageOptimizer\Image::get_instance();
        $result = $image_handler->convert_existing($attachment_id);

        // Clear stats cache
        delete_transient('wio_stats');

        /**
         * Fires after a single image is bulk-converted.
         *
         * @param int   $attachment_id The attachment post ID.
         * @param array $result        Conversion result array.
         */
        do_action('wio_after_bulk_convert', $attachment_id, $result);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler: return IDs of non-WebP images eligible for bulk conversion.
     */
    public function ajax_bulk_status()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');

        $options = get_option('webp_image_optimizer_settings');
        $allowed_types = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];

        if (empty($allowed_types)) {
            wp_send_json_success(['ids' => [], 'total' => 0]);
        }

        global $wpdb;
        $placeholders = implode(', ', array_fill(0, count($allowed_types), '%s'));
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ($placeholders) ORDER BY ID ASC",
            $allowed_types
        );
        $ids = $wpdb->get_col($query);

        wp_send_json_success(['ids' => array_map('intval', $ids), 'total' => count($ids)]);
    }

    /**
     * AJAX handler: generate AI alt text for a single attachment.
     */
    public function ajax_generate_alt()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $overwrite = !empty($_POST['overwrite']);

        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $image_handler = \WebPImageOptimizer\Image::get_instance();
        $result = $image_handler->generate_ai_alt_text_for_existing($attachment_id, $overwrite);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler: return IDs of images missing alt text.
     */
    public function ajax_bulk_alt_status()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('wio_nonce', 'nonce');

        global $wpdb;
        // Find image attachments that have no _wp_attachment_image_alt meta or it's empty
        $ids = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND (m.meta_value IS NULL OR m.meta_value = '')
            ORDER BY p.ID ASC"
        );

        wp_send_json_success(['ids' => array_map('intval', $ids), 'total' => count($ids)]);
    }

    /**
     * Add "Settings" link on the Plugins page.
     */
    public function add_settings_link($links)
    {
        $url = admin_url('tools.php?page=webp-image-optimizer');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . __('Settings', 'webp-image-optimizer') . '</a>');
        return $links;
    }

    /**
     * Add "Generate AI Alt Text" button to attachment edit fields in Media Library.
     */
    public function add_ai_alt_button($form_fields, $post)
    {
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        $options = get_option('webp_image_optimizer_settings');
        $ai_enabled = !empty($options['ai_alt_text']) && !empty($options['ai_api_key']);

        if (!$ai_enabled) {
            return $form_fields;
        }

        $current_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $btn_label = empty($current_alt) ? __('Generate AI Alt Text', 'webp-image-optimizer') : __('Regenerate AI Alt Text', 'webp-image-optimizer');

        $form_fields['wio_ai_alt'] = [
            'label' => __('AI Alt Text', 'webp-image-optimizer'),
            'input' => 'html',
            'html'  => '<button type="button" class="button wio-generate-alt-btn" data-id="' . esc_attr($post->ID) . '">' . esc_html($btn_label) . '</button> <span class="wio-alt-result" id="wio-alt-result-' . esc_attr($post->ID) . '"></span>',
        ];

        return $form_fields;
    }
}

// Initialize the plugin
WIO_Plugin::get_instance();
