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
        // Use wp_generate_attachment_metadata instead of add_attachment
        // because _wp_attached_file meta is not yet set when add_attachment fires.
        add_filter('wp_generate_attachment_metadata', [$this, 'set_image_alt_text_on_metadata'], 20, 3);
        add_action('delete_attachment', [$this, 'delete_related_files_on_remove']);
    }

    /**
     * Return a relative upload path for safe debug logging.
     */
    private function relative_upload_path($file_path)
    {
        $uploads = wp_upload_dir();
        $basedir = $uploads['basedir'];
        if (strpos($file_path, $basedir) === 0) {
            return substr($file_path, strlen($basedir) + 1);
        }
        return basename($file_path);
    }

    /**
     * Get plugin settings with defaults.
     *
     * @return array
     */
    private function get_settings()
    {
        $options = get_option('webp_image_optimizer_settings');
        return [
            'quality'         => isset($options['quality']) ? max(0, min(100, intval($options['quality']))) : 80,
            'method'          => isset($options['method']) ? max(0, min(6, intval($options['method']))) : 6,
            'retain_original' => isset($options['retain_original']) ? (bool) $options['retain_original'] : true,
            'allowed_types'   => isset($options['allowed_types']) && !empty($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'],
            'png_lossless'    => isset($options['png_lossless']) ? (bool) $options['png_lossless'] : false,
            'set_alt_text'    => isset($options['set_alt_text']) ? (bool) $options['set_alt_text'] : true,
            'ai_alt_text'     => isset($options['ai_alt_text']) ? (bool) $options['ai_alt_text'] : false,
            'ai_provider'     => isset($options['ai_provider']) ? $options['ai_provider'] : 'openrouter',
            'ai_api_key'      => isset($options['ai_api_key']) ? $options['ai_api_key'] : '',
            'ai_model'        => isset($options['ai_model']) ? $options['ai_model'] : '',
            'ai_prompt'       => isset($options['ai_prompt']) ? $options['ai_prompt'] : '',
        ];
    }

    /**
     * Core conversion: take a source file path + mime type and produce a WebP.
     *
     * @param string $file_path  Absolute path to the source image.
     * @param string $mime_type  MIME type of the source.
     * @param array  $settings   Plugin settings array.
     * @return array{success: bool, webp_path?: string, original_size?: int, webp_size?: int, message?: string}
     */
    public function convert_to_webp($file_path, $mime_type, $settings)
    {
        $file_info = pathinfo($file_path);
        $quality     = $settings['quality'];
        $method      = $settings['method'];
        $png_lossless = $settings['png_lossless'];

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return ['success' => false, 'message' => 'File not readable'];
        }

        if (isset($file_info['extension']) && strtolower($file_info['extension']) === 'webp') {
            return ['success' => false, 'message' => 'Already WebP'];
        }

        $file_size = filesize($file_path);
        if ($file_size > WIO_MAX_CONVERT_SIZE) {
            return ['success' => false, 'message' => 'File exceeds size threshold (' . size_format($file_size) . ')'];
        }

        // Skip animated GIFs
        if ($mime_type === 'image/gif' && extension_loaded('imagick')) {
            $gif_check = null;
            try {
                $gif_check = new \Imagick($file_path);
                if ($gif_check->getNumberImages() > 1) {
                    return ['success' => false, 'message' => 'Animated GIF'];
                }
            } finally {
                if ($gif_check) {
                    $gif_check->clear();
                    $gif_check->destroy();
                }
            }
        }

        $new_file_path = null;

        if (extension_loaded('imagick')) {
            $image = new \Imagick($file_path);
            try {
                $image->setImageFormat('webp');
                $image->stripImage();
                if ($mime_type === 'image/png') {
                    $image->setOption('webp:lossless', $png_lossless ? 'true' : 'false');
                }
                $image->setOption('webp:method', $method);
                $image->setImageCompressionQuality($quality);
                $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');
                if (!$image->writeImage($new_file_path)) {
                    return ['success' => false, 'message' => 'Imagick write failed'];
                }
            } finally {
                $image->clear();
                $image->destroy();
            }
        } elseif (extension_loaded('gd')) {
            $image_editor = wp_get_image_editor($file_path);
            if (is_wp_error($image_editor)) {
                return ['success' => false, 'message' => 'GD editor error: ' . $image_editor->get_error_message()];
            }
            if ($mime_type === 'image/png' && $png_lossless && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO: GD does not support true lossless WebP. Using quality 100 as approximation.');
            }
            $gd_quality = ($mime_type === 'image/png' && $png_lossless) ? 100 : $quality;
            if (method_exists($image_editor, 'set_quality')) {
                $image_editor->set_quality($gd_quality);
            }
            $new_file_path = $file_info['dirname'] . '/' . wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');
            $saved_image = $image_editor->save($new_file_path, 'image/webp');
            if (is_wp_error($saved_image)) {
                return ['success' => false, 'message' => 'GD save error: ' . $saved_image->get_error_message()];
            }
        } else {
            return ['success' => false, 'message' => 'No image engine available'];
        }

        if (!$new_file_path || !file_exists($new_file_path)) {
            return ['success' => false, 'message' => 'WebP file not created'];
        }

        $original_size = filesize($file_path);
        $webp_size     = filesize($new_file_path);

        // If WebP is larger, discard it
        if ($webp_size >= $original_size) {
            wp_delete_file($new_file_path);
            return ['success' => false, 'message' => 'WebP larger than original'];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WIO: Converted ' . $this->relative_upload_path($file_path) . ' → ' . size_format($original_size) . ' → ' . size_format($webp_size));
        }

        return [
            'success'       => true,
            'webp_path'     => $new_file_path,
            'original_size' => $original_size,
            'webp_size'     => $webp_size,
        ];
    }

    /**
     * Hook into wp_handle_upload to convert new uploads.
     */
    public function handle_upload($upload)
    {
        try {
            $settings = $this->get_settings();

            if (!in_array($upload['type'], $settings['allowed_types'], true)) {
                return $upload;
            }

            $file_path = $upload['file'];
            if (strpos($file_path, '-scaled') !== false || preg_match('/-\d+x\d+\./', $file_path)) {
                return $upload;
            }

            $result = $this->convert_to_webp($file_path, $upload['type'], $settings);

            if (!$result['success']) {
                return $upload;
            }

            // Track bytes saved
            $bytes_saved = $result['original_size'] - $result['webp_size'];

            $upload['file'] = $result['webp_path'];
            $upload['url']  = str_replace(basename($upload['url']), basename($result['webp_path']), $upload['url']);
            $upload['type'] = 'image/webp';

            if (!$settings['retain_original'] && file_exists($file_path)) {
                wp_delete_file($file_path);
            }

            // Store bytes saved temporarily; will be saved to postmeta on add_attachment
            $upload['_wio_bytes_saved'] = $bytes_saved;

            /**
             * Fires after a newly uploaded image is converted to WebP.
             *
             * @param array $upload The modified upload array.
             * @param array $result Conversion result.
             */
            do_action('wio_after_conversion', $upload, $result);

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO: Exception during conversion - ' . $e->getMessage());
            }
        }
        return $upload;
    }

    /**
     * Convert an existing Media Library attachment to WebP (bulk conversion).
     *
     * @param int $attachment_id
     * @return array{success: bool, message?: string, bytes_saved?: int}
     */
    public function convert_existing($attachment_id)
    {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        $mime = get_post_mime_type($attachment_id);
        $settings = $this->get_settings();

        $result = $this->convert_to_webp($file, $mime, $settings);
        if (!$result['success']) {
            return $result;
        }

        $webp_path   = $result['webp_path'];
        $bytes_saved = $result['original_size'] - $result['webp_size'];

        // Update the attachment to point to the WebP file
        $uploads = wp_upload_dir();
        $relative_path = str_replace($uploads['basedir'] . '/', '', $webp_path);
        update_attached_file($attachment_id, $webp_path);
        wp_update_post([
            'ID'             => $attachment_id,
            'post_mime_type' => 'image/webp',
        ]);

        // Regenerate metadata for the new file
        $metadata = wp_generate_attachment_metadata($attachment_id, $webp_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        // Store bytes saved
        update_post_meta($attachment_id, '_wio_bytes_saved', $bytes_saved);

        // Delete original if setting says so
        if (!$settings['retain_original'] && file_exists($file) && $file !== $webp_path) {
            wp_delete_file($file);
        }

        // Clear stats cache
        delete_transient('wio_stats');

        return [
            'success'     => true,
            'bytes_saved' => $bytes_saved,
            'message'     => size_format($result['original_size']) . ' → ' . size_format($result['webp_size']),
        ];
    }

    /**
     * Set alt text when attachment metadata is generated.
     *
     * Hooked to wp_generate_attachment_metadata (not add_attachment) because
     * _wp_attached_file meta is not available yet when add_attachment fires,
     * which breaks get_attached_file() and therefore AI alt text generation.
     *
     * @param array  $metadata      Attachment metadata.
     * @param int    $attachment_id  Attachment post ID.
     * @param string $context        'create' on initial upload, 'update' on regeneration.
     * @return array Unmodified metadata.
     */
    public function set_image_alt_text_on_metadata($metadata, $attachment_id, $context = 'create')
    {
        $is_debug = defined('WP_DEBUG') && WP_DEBUG;

        if ($is_debug) {
            error_log('WIO AI: === set_image_alt_text_on_metadata fired for #' . $attachment_id . ' context=' . $context);
        }

        // Only run on initial upload, not thumbnail regeneration
        if ($context !== 'create') {
            if ($is_debug) error_log('WIO AI: Skipped — context is "' . $context . '"');
            return $metadata;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            if ($is_debug) error_log('WIO AI: Skipped — not an image (mime: ' . get_post_mime_type($attachment_id) . ')');
            return $metadata;
        }

        $settings = $this->get_settings();
        if (!$settings['set_alt_text']) {
            if ($is_debug) error_log('WIO AI: Skipped — set_alt_text is disabled');
            return $metadata;
        }

        // Don't overwrite existing alt text (e.g. set manually before upload finished)
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            if ($is_debug) error_log('WIO AI: Skipped — alt text already exists: "' . $existing_alt . '"');
            return $metadata;
        }

        // Try AI-generated alt text first
        if ($settings['ai_alt_text'] && !empty($settings['ai_api_key'])) {
            $file = get_attached_file($attachment_id);

            if ($is_debug) {
                error_log('WIO AI: Attempting AI generation for #' . $attachment_id);
                error_log('WIO AI: File: ' . ($file ?: '(empty)') . ' exists=' . ($file && file_exists($file) ? 'yes' : 'no'));
                error_log('WIO AI: Provider=' . $settings['ai_provider'] . ' Model=' . ($settings['ai_model'] ?: '(default)'));
            }

            if ($file && file_exists($file)) {
                $ai_alt = $this->generate_ai_alt_text($file, $settings);
                if (!empty($ai_alt)) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($ai_alt));
                    if ($is_debug) error_log('WIO AI: SUCCESS — alt text set to: "' . $ai_alt . '"');
                    return $metadata;
                }
                if ($is_debug) error_log('WIO AI: generate_ai_alt_text returned empty — falling back to filename.');
            } else {
                if ($is_debug) error_log('WIO AI: File not found, cannot send to AI.');
            }
        } else {
            if ($is_debug) {
                error_log('WIO AI: AI disabled or no key — ai_alt_text=' . ($settings['ai_alt_text'] ? 'true' : 'false') . ' api_key=' . (!empty($settings['ai_api_key']) ? '(set, ' . strlen($settings['ai_api_key']) . ' chars)' : '(empty)'));
            }
        }

        // Fallback: derive alt text from filename
        $filename = get_the_title($attachment_id);
        if (empty($filename)) {
            $attached_file = get_attached_file($attachment_id);
            $filename = $attached_file ? pathinfo($attached_file, PATHINFO_FILENAME) : '';
        }
        if (empty($filename)) {
            return $metadata;
        }
        $filename = preg_replace('/\.[^.]+$/', '', $filename);
        $filename = str_replace(array('-', '_'), ' ', $filename);
        $alt_text = ucfirst(strtolower(trim($filename)));
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        }

        return $metadata;
    }

    /**
     * Call a vision LLM to generate alt text for an image file.
     *
     * @param string $file_path Absolute path to the image.
     * @param array  $settings  Plugin settings.
     * @return string Alt text on success, empty string on failure.
     */
    private function generate_ai_alt_text($file_path, $settings)
    {
        $provider = $settings['ai_provider'];
        $api_key  = $settings['ai_api_key'];
        $model    = $settings['ai_model'];
        $prompt   = $settings['ai_prompt'];

        if (empty($api_key)) {
            return '';
        }

        // Defaults per provider
        $defaults = [
            'nvidia'     => ['model' => 'google/gemma-3-27b-it', 'url' => 'https://integrate.api.nvidia.com/v1/chat/completions'],
            'anthropic'  => ['model' => 'claude-sonnet-4-20250514', 'url' => 'https://api.anthropic.com/v1/messages'],
            'openai'     => ['model' => 'gpt-4o-mini', 'url' => 'https://api.openai.com/v1/chat/completions'],
            'xai'        => ['model' => 'grok-2-vision-latest', 'url' => 'https://api.x.ai/v1/chat/completions'],
            'mistral'    => ['model' => 'pixtral-large-latest', 'url' => 'https://api.mistral.ai/v1/chat/completions'],
            'google'     => ['model' => 'gemini-2.0-flash', 'url' => ''],
            'groq'       => ['model' => 'llama-3.2-90b-vision-preview', 'url' => 'https://api.groq.com/openai/v1/chat/completions'],
            'openrouter' => ['model' => 'openrouter/free', 'url' => 'https://openrouter.ai/api/v1/chat/completions'],
        ];

        if (!isset($defaults[$provider])) {
            return '';
        }

        $model = !empty($model) ? $model : $defaults[$provider]['model'];

        if (empty($prompt)) {
            $prompt = 'Describe this image in one concise sentence for use as alt text on a webpage. Focus on the main subject, action, and context. Keep it under 125 characters. Return only the alt text, no quotes or extra formatting.';
        }

        // Read and encode image
        $image_data = file_get_contents($file_path);
        if ($image_data === false) {
            return '';
        }

        // Limit to 4MB base64 payload to avoid huge requests
        if (strlen($image_data) > 4 * 1024 * 1024) {
            return '';
        }

        $mime = wp_check_filetype($file_path)['type'];
        if (empty($mime)) {
            $mime = 'image/jpeg';
        }
        $base64 = base64_encode($image_data);
        $data_uri = 'data:' . $mime . ';base64,' . $base64;

        // Google Gemini uses a different API format
        if ($provider === 'google') {
            return $this->call_google_gemini($api_key, $model, $prompt, $base64, $mime);
        }

        // Anthropic Claude uses a different API format
        if ($provider === 'anthropic') {
            return $this->call_anthropic_claude($api_key, $model, $prompt, $base64, $mime);
        }

        // OpenAI-compatible format (works for NVIDIA, OpenAI, xAI, Mistral, Groq, OpenRouter)
        $body = [
            'model'    => $model,
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $data_uri]],
                    ],
                ],
            ],
            'max_tokens' => 1000,
        ];

        // For OpenRouter: tell reasoning models to keep reasoning minimal
        if ($provider === 'openrouter') {
            $body['reasoning'] = ['effort' => 'low'];
        }

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = home_url();
            $headers['X-Title']      = 'WebP Image Optimizer';
        }

        $url = $defaults[$provider]['url'];

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI: Request failed — ' . $response->get_error_message());
            }
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $resp_body = wp_remote_retrieve_body($response);
        $data = json_decode($resp_body, true);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WIO AI: HTTP ' . $code . ' Response (first 500 chars): ' . substr($resp_body, 0, 500));
        }

        if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
            // Some reasoning models (e.g. nemotron) put output in 'reasoning' and leave 'content' null.
            // Try to extract a usable sentence from the reasoning field.
            if ($code === 200 && !empty($data['choices'][0]['message']['reasoning'])) {
                $reasoning = trim($data['choices'][0]['message']['reasoning']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WIO AI: content was null but reasoning found (' . strlen($reasoning) . ' chars). Attempting to extract alt text from reasoning.');
                }
                // Try to find a quoted string that looks like the final alt text
                if (preg_match('/["\']([A-Z][^"\'\']{10,124})["\'\']/u', $reasoning, $m)) {
                    $alt = trim($m[1]);
                    if (mb_strlen($alt) > 10) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('WIO AI: Extracted from reasoning: "' . $alt . '"');
                        }
                        if (mb_strlen($alt) > 125) {
                            $alt = mb_substr($alt, 0, 122) . '...';
                        }
                        return $alt;
                    }
                }
                // If no quoted string, grab the last sentence as a heuristic
                $sentences = preg_split('/(?<=[.!?])\s+/', $reasoning, -1, PREG_SPLIT_NO_EMPTY);
                if (!empty($sentences)) {
                    $last = trim(end($sentences), '"\'\'\n\r ');
                    if (mb_strlen($last) >= 10 && mb_strlen($last) <= 200) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('WIO AI: Using last sentence from reasoning: "' . $last . '"');
                        }
                        if (mb_strlen($last) > 125) {
                            $last = mb_substr($last, 0, 122) . '...';
                        }
                        return $last;
                    }
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WIO AI: Could not extract usable alt text from reasoning.');
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI: Failed — HTTP ' . $code . ' — no usable content found');
            }
            return '';
        }

        $alt = trim($data['choices'][0]['message']['content']);
        // Strip wrapping quotes if present
        $alt = trim($alt, '"\'\'');
        // Enforce 125 char limit
        if (mb_strlen($alt) > 125) {
            $alt = mb_substr($alt, 0, 122) . '...';
        }

        return $alt;
    }

    /**
     * Call Google Gemini generateContent endpoint.
     */
    private function call_google_gemini($api_key, $model, $prompt, $base64_image, $mime_type)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mime_type, 'data' => $base64_image]],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 150,
            ],
        ];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI Gemini: Request failed — ' . $response->get_error_message());
            }
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI Gemini: HTTP ' . $code . ' — ' . wp_json_encode($data));
            }
            return '';
        }

        $alt = trim($data['candidates'][0]['content']['parts'][0]['text']);
        $alt = trim($alt, '"\'\'');
        if (mb_strlen($alt) > 125) {
            $alt = mb_substr($alt, 0, 122) . '...';
        }

        return $alt;
    }

    /**
     * Call Anthropic Claude messages endpoint.
     */
    private function call_anthropic_claude($api_key, $model, $prompt, $base64_image, $mime_type)
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $body = [
            'model'      => $model,
            'max_tokens' => 1000,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime_type, 'data' => $base64_image]],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version'  => '2023-06-01',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI Claude: Request failed — ' . $response->get_error_message());
            }
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($data['content'][0]['text'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WIO AI Claude: HTTP ' . $code . ' — ' . substr(wp_json_encode($data), 0, 500));
            }
            return '';
        }

        $alt = trim($data['content'][0]['text']);
        $alt = trim($alt, '"\'\'\'');
        if (mb_strlen($alt) > 125) {
            $alt = mb_substr($alt, 0, 122) . '...';
        }

        return $alt;
    }

    /**
     * Test an AI provider connection. Returns success/error message.
     *
     * @param string $provider  Provider key.
     * @param string $api_key   API key.
     * @param string $model     Model name (optional).
     * @return array{success: bool, message: string}
     */
    public function test_ai_connection($provider, $api_key, $model = '')
    {
        $defaults = [
            'nvidia'     => ['model' => 'google/gemma-3-27b-it', 'url' => 'https://integrate.api.nvidia.com/v1/chat/completions'],
            'anthropic'  => ['model' => 'claude-sonnet-4-20250514', 'url' => 'https://api.anthropic.com/v1/messages'],
            'openai'     => ['model' => 'gpt-4o-mini', 'url' => 'https://api.openai.com/v1/chat/completions'],
            'xai'        => ['model' => 'grok-2-vision-latest', 'url' => 'https://api.x.ai/v1/chat/completions'],
            'mistral'    => ['model' => 'pixtral-large-latest', 'url' => 'https://api.mistral.ai/v1/chat/completions'],
            'google'     => ['model' => 'gemini-2.0-flash', 'url' => ''],
            'groq'       => ['model' => 'llama-3.2-90b-vision-preview', 'url' => 'https://api.groq.com/openai/v1/chat/completions'],
            'openrouter' => ['model' => 'openrouter/free', 'url' => 'https://openrouter.ai/api/v1/chat/completions'],
        ];

        if (!isset($defaults[$provider])) {
            return ['success' => false, 'message' => 'Unknown provider'];
        }

        $model = !empty($model) ? $model : $defaults[$provider]['model'];

        if ($provider === 'google') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);
            $body = [
                'contents' => [['parts' => [['text' => 'Say hello in one word.']]]],
                'generationConfig' => ['maxOutputTokens' => 10],
            ];
            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]);
        } elseif ($provider === 'anthropic') {
            $url = 'https://api.anthropic.com/v1/messages';
            $body = [
                'model'      => $model,
                'max_tokens' => 10,
                'messages'   => [['role' => 'user', 'content' => 'Say hello in one word.']],
            ];
            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $api_key,
                    'anthropic-version'  => '2023-06-01',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]);
        } else {
            $url = $defaults[$provider]['url'];
            $body = [
                'model'    => $model,
                'messages' => [['role' => 'user', 'content' => 'Say hello in one word.']],
                'max_tokens' => 10,
            ];
            $headers = [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ];
            if ($provider === 'openrouter') {
                $headers['HTTP-Referer'] = home_url();
                $headers['X-Title']      = 'WebP Image Optimizer';
            }
            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]);
        }

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['success' => true, 'message' => 'Connected to ' . $model];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $err = '';
        if (isset($data['error']['message'])) {
            $err = $data['error']['message'];
        } elseif (isset($data['message'])) {
            $err = $data['message'];
        }
        return ['success' => false, 'message' => 'HTTP ' . $code . ($err ? ': ' . $err : '')];
    }

    /**
     * Generate AI alt text for an existing attachment.
     *
     * @param int  $attachment_id
     * @param bool $overwrite  Whether to overwrite existing alt text.
     * @return array{success: bool, message: string, alt_text?: string}
     */
    public function generate_ai_alt_text_for_existing($attachment_id, $overwrite = false)
    {
        if (!wp_attachment_is_image($attachment_id)) {
            return ['success' => false, 'message' => 'Not an image'];
        }

        $settings = $this->get_settings();

        if (empty($settings['ai_api_key'])) {
            return ['success' => false, 'message' => 'No API key configured'];
        }

        if (!$overwrite) {
            $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($existing)) {
                return ['success' => false, 'message' => 'Alt text already exists: "' . $existing . '"'];
            }
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        $alt = $this->generate_ai_alt_text($file, $settings);
        if (empty($alt)) {
            return ['success' => false, 'message' => 'AI returned empty response'];
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        return ['success' => true, 'message' => $alt, 'alt_text' => $alt];
    }

    public function delete_related_files_on_remove($post_ID)
    {
        $file = get_attached_file($post_ID);
        if (!$file) return;
        $file_info = pathinfo($file);
        $base_dir  = $file_info['dirname'];
        $base_name = $file_info['filename'];
        $extensions = WIO_SUPPORTED_EXTENSIONS;

        // Delete all files with the same base name and allowed extensions
        foreach ($extensions as $ext) {
            $candidate = $base_dir . DIRECTORY_SEPARATOR . $base_name . '.' . $ext;
            if (file_exists($candidate)) {
                wp_delete_file($candidate);
            }
        }

        // Use WordPress attachment metadata to find all registered intermediate sizes
        $metadata = wp_get_attachment_metadata($post_ID);
        $deleted_files = [];
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_data) {
                if (!empty($size_data['file'])) {
                    $thumb_path = $base_dir . DIRECTORY_SEPARATOR . $size_data['file'];
                    if (file_exists($thumb_path) && !isset($deleted_files[$thumb_path])) {
                        wp_delete_file($thumb_path);
                        $deleted_files[$thumb_path] = true;
                    }
                    $thumb_webp = preg_replace('/\.[^.]+$/', '.webp', $thumb_path);
                    if ($thumb_webp !== $thumb_path && file_exists($thumb_webp) && !isset($deleted_files[$thumb_webp])) {
                        wp_delete_file($thumb_webp);
                        $deleted_files[$thumb_webp] = true;
                    }
                }
            }
        }

        // Catch -scaled and -rotated variants
        $suffixes = ['-scaled', '-rotated'];
        foreach ($extensions as $ext) {
            foreach ($suffixes as $suffix) {
                $candidate = $base_dir . DIRECTORY_SEPARATOR . $base_name . $suffix . '.' . $ext;
                if (file_exists($candidate) && !isset($deleted_files[$candidate])) {
                    wp_delete_file($candidate);
                    $deleted_files[$candidate] = true;
                }
            }
        }

        // Fallback glob for remaining WP thumbnail patterns
        foreach ($extensions as $tExt) {
            $pattern = $base_dir . DIRECTORY_SEPARATOR . $base_name . '-*.' . $tExt;
            foreach (glob($pattern) as $thumb) {
                if (!isset($deleted_files[$thumb]) && preg_match('/-\d+x\d+\.' . preg_quote($tExt, '/') . '$/', $thumb)) {
                    wp_delete_file($thumb);
                    $deleted_files[$thumb] = true;
                }
            }
        }

        // Delete the original file stored in meta if different
        $meta_file = get_post_meta($post_ID, '_wp_attached_file', true);
        if ($meta_file) {
            $uploads = wp_upload_dir();
            $original_path = $uploads['basedir'] . DIRECTORY_SEPARATOR . $meta_file;
            if (file_exists($original_path) && $original_path !== $file) {
                wp_delete_file($original_path);
            }
        }

        // Clean up bytes-saved meta
        delete_post_meta($post_ID, '_wio_bytes_saved');
    }
}
