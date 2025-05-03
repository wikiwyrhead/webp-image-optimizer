<?php

namespace WebPImageOptimizer;

if (!defined('ABSPATH')) exit;

class Image
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
        add_filter('wp_handle_upload', [$this, 'handle_upload']);
        add_action('add_attachment', [$this, 'set_image_alt_text_on_upload']);
        add_action('delete_attachment', [$this, 'delete_related_files_on_remove']);
    }

    public function handle_upload($upload)
    {
        try {
            $options = get_option('webp_image_optimizer_settings');
            $quality = isset($options['quality']) ? intval($options['quality']) : 80;
            $method = isset($options['method']) ? intval($options['method']) : 6;
            $retain_original = isset($options['retain_original']) ? $options['retain_original'] : false;
            $quality = max(0, min(100, $quality));
            $method = max(0, min(6, $method));
            $allowed_types = isset($options['allowed_types']) && !empty($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
            $png_lossless = isset($options['png_lossless']) ? (bool)$options['png_lossless'] : true;
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $original_size = file_exists($upload['file']) ? filesize($upload['file']) : 0;
                error_log('WIO: Conversion settings - quality: ' . $quality . ', method: ' . $method . ', retain_original: ' . ($retain_original ? 'true' : 'false') . ', png_lossless: ' . ($png_lossless ? 'true' : 'false') . ', original_size: ' . $original_size);
            }
            if (in_array($upload['type'], $allowed_types, true)) {
                $file_path = $upload['file'];
                $file_info = pathinfo($file_path);
                if (!file_exists($file_path) || !is_readable($file_path)) {
                    return $upload;
                }
                if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
                    return $upload;
                }
                if (strpos($file_path, '-scaled') === false && !preg_match('/-\d+x\d+\./', $file_path)) {
                    if (extension_loaded('imagick')) {
                        $image = new \Imagick($file_path);
                        $image->setImageFormat('webp');
                        // Always strip metadata
                        $image->stripImage();
                        // For PNGs, default to lossy unless user explicitly wants lossless
                        if ($upload['type'] === 'image/png') {
                            if ($png_lossless) {
                                $image->setOption('webp:lossless', 'true');
                            } else {
                                $image->setOption('webp:lossless', 'false');
                            }
                        }
                        $image->setOption('webp:method', $method);
                        $image->setImageCompressionQuality($quality);
                        // Optionally reduce color palette for PNGs (like TinyPNG)
                        if ($upload['type'] === 'image/png' && !$png_lossless) {
                            $image->quantizeImage(256, \Imagick::COLORSPACE_RGB, 0, false, false);
                        }
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
                        // Always strip metadata (GD does this by default)
                        $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');
                        $saved_image = $image_editor->save($new_file_path, 'image/webp', array('quality' => $quality));
                        if (is_wp_error($saved_image)) {
                            return $upload;
                        }
                    } else {
                        return $upload;
                    }
                    if (isset($new_file_path) && file_exists($new_file_path)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            $webp_size = filesize($new_file_path);
                            error_log('WIO: WebP file created: ' . $new_file_path . ' | size: ' . $webp_size);
                        }
                        $upload['file'] = $new_file_path;
                        $upload['url'] = str_replace(basename($upload['url']), basename($new_file_path), $upload['url']);
                        $upload['type'] = 'image/webp';
                        if (!$retain_original && file_exists($file_path)) {
                            @unlink($file_path);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail and return original upload
        }
        return $upload;
    }

    public function set_image_alt_text_on_upload($post_ID)
    {
        if (!wp_attachment_is_image($post_ID)) {
            return;
        }
        $options = get_option('webp_image_optimizer_settings');
        $set_alt_text = isset($options['set_alt_text']) ? $options['set_alt_text'] : false;
        if (!$set_alt_text) {
            return;
        }
        $filename = get_the_title($post_ID);
        $filename = preg_replace('/\.[^.]+$/', '', $filename);
        $filename = str_replace(array('-', '_'), ' ', $filename);
        $alt_text = ucfirst(strtolower($filename));
        update_post_meta($post_ID, '_wp_attachment_image_alt', $alt_text);
    }

    public function delete_related_files_on_remove($post_ID)
    {
        $file = get_attached_file($post_ID);
        if (!$file) return;
        $file_info = pathinfo($file);
        $base_dir = $file_info['dirname'];
        $base_name = $file_info['filename'];
        $extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff'];
        // Delete all files with the same base name and allowed extensions
        foreach ($extensions as $ext) {
            $candidate = $base_dir . DIRECTORY_SEPARATOR . $base_name . '.' . $ext;
            if (file_exists($candidate)) {
                @unlink($candidate);
            }
        }
        // Also delete all intermediate sizes (e.g., -150x150)
        $pattern = $base_dir . DIRECTORY_SEPARATOR . $base_name . '-*.*';
        foreach (glob($pattern) as $thumb) {
            @unlink($thumb);
        }
        // Delete the original file if it has a different extension and is stored in _wp_attached_file meta
        $meta_file = get_post_meta($post_ID, '_wp_attached_file', true);
        if ($meta_file) {
            $uploads = wp_upload_dir();
            $original_path = $uploads['basedir'] . DIRECTORY_SEPARATOR . $meta_file;
            if (file_exists($original_path) && $original_path !== $file) {
                @unlink($original_path);
            }
        }
    }
}
