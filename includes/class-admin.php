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
        $includes_dir = plugin_dir_path(__FILE__);
        if (!file_exists($includes_dir)) {
            wp_mkdir_p($includes_dir);
        }
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }
        if (!get_option('webp_image_optimizer_settings')) {
            $default_options = [
                'quality'         => 80,
                'method'          => 6,
                'retain_original' => true,
                'allowed_types'   => ['image/jpeg', 'image/png', 'image/gif'],
                'set_alt_text'    => true,
                'png_lossless'    => false,
                'ai_alt_text'     => false,
                'ai_provider'     => 'nvidia',
                'ai_api_key'      => '',
                'ai_model'        => '',
                'ai_prompt'       => '',
            ];
            add_option('webp_image_optimizer_settings', $default_options);
        }
    }

    public function add_menu()
    {
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
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );
        add_settings_section(
            'webp_image_optimizer_main_settings',
            '',
            '__return_false',
            'webp_image_optimizer_settings'
        );
        add_settings_field('retain_original', __('Retain Original Image', 'webp-image-optimizer'), [$this, 'retain_original_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('quality', __('Image Quality', 'webp-image-optimizer'), [$this, 'quality_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('method', __('WebP Compression Method', 'webp-image-optimizer'), [$this, 'method_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('allowed_types', __('Allowed Image Types', 'webp-image-optimizer'), [$this, 'allowed_types_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('set_alt_text', __('Set Alt Text', 'webp-image-optimizer'), [$this, 'set_alt_text_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('png_lossless', __('Use lossless WebP for PNGs', 'webp-image-optimizer'), [$this, 'png_lossless_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
        add_settings_field('ai_alt_text', __('AI Alt Text', 'webp-image-optimizer'), [$this, 'ai_alt_text_render'], 'webp_image_optimizer_settings', 'webp_image_optimizer_main_settings');
    }

    public function sanitize_settings($input)
    {
        $defaults = [
            'quality' => 80, 'method' => 6, 'retain_original' => true,
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'set_alt_text' => true, 'png_lossless' => false,
        ];
        $output = $defaults;
        $output['quality'] = isset($input['quality']) ? max(0, min(100, intval($input['quality']))) : $defaults['quality'];
        $output['method']  = isset($input['method']) ? max(0, min(6, intval($input['method']))) : $defaults['method'];
        $output['retain_original'] = !empty($input['retain_original']);
        $valid_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/heic', 'image/heif'];
        if (isset($input['allowed_types']) && is_array($input['allowed_types'])) {
            $output['allowed_types'] = array_values(array_intersect($input['allowed_types'], $valid_types));
        } else {
            $output['allowed_types'] = $defaults['allowed_types'];
        }
        $output['set_alt_text']  = !empty($input['set_alt_text']);
        $output['png_lossless']  = !empty($input['png_lossless']);

        // AI alt text settings
        $output['ai_alt_text'] = !empty($input['ai_alt_text']);
        $valid_providers = ['nvidia', 'anthropic', 'openai', 'xai', 'mistral', 'google', 'groq', 'openrouter'];
        $output['ai_provider'] = isset($input['ai_provider']) && in_array($input['ai_provider'], $valid_providers, true)
            ? $input['ai_provider'] : 'nvidia';

        // Only update API key if a new value was submitted (not the placeholder)
        if (isset($input['ai_api_key']) && $input['ai_api_key'] !== '' && strpos($input['ai_api_key'], '••••') === false) {
            $output['ai_api_key'] = sanitize_text_field($input['ai_api_key']);
        } else {
            // Keep existing key
            $existing = get_option('webp_image_optimizer_settings');
            $output['ai_api_key'] = isset($existing['ai_api_key']) ? $existing['ai_api_key'] : '';
        }

        $output['ai_model']  = isset($input['ai_model']) ? sanitize_text_field($input['ai_model']) : '';
        $output['ai_prompt'] = isset($input['ai_prompt']) ? sanitize_textarea_field($input['ai_prompt']) : '';

        // Clear stats cache on settings change
        delete_transient('wio_stats');
        return $output;
    }

    /* ─── Field renderers ───────────────────────────────────────────── */

    public function retain_original_render()
    {
        $options = get_option('webp_image_optimizer_settings');
?>
        <label>
            <input type="checkbox" name="webp_image_optimizer_settings[retain_original]" <?php checked(isset($options['retain_original']) ? $options['retain_original'] : true); ?> value="1">
            <?php _e('Keep the original image after conversion to WebP.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('If unchecked, the original image will be deleted after successful conversion.', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function quality_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $quality = isset($options['quality']) ? intval($options['quality']) : 80;
?>
        <div class="wio-range-field">
            <input type="range" name="webp_image_optimizer_settings[quality]" value="<?php echo esc_attr($quality); ?>" min="0" max="100" step="1" id="wio-quality-range">
            <output for="wio-quality-range" class="wio-range-value"><?php echo esc_html($quality); ?></output>
        </div>
        <p class="description"><?php _e('Higher values = better quality, larger files. Default: 80.', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function method_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $method = isset($options['method']) ? intval($options['method']) : 6;
?>
        <div class="wio-range-field">
            <input type="range" name="webp_image_optimizer_settings[method]" value="<?php echo esc_attr($method); ?>" min="0" max="6" step="1" id="wio-method-range">
            <output for="wio-method-range" class="wio-range-value"><?php echo esc_html($method); ?></output>
        </div>
        <p class="description"><?php _e('Higher values = better compression, slower conversion. Default: 6.', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function allowed_types_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $allowed = isset($options['allowed_types']) ? $options['allowed_types'] : ['image/jpeg', 'image/png', 'image/gif'];
        $info = \WIO_Plugin::detect_engine();
        $engine_types = $info['supported'];
        $all_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/heic', 'image/heif'];
        $labels = [
            'image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF',
            'image/bmp' => 'BMP', 'image/tiff' => 'TIFF', 'image/heic' => 'HEIC', 'image/heif' => 'HEIF',
        ];
?>
        <div class="wio-checkbox-grid">
        <?php foreach ($all_types as $type) :
            $label = isset($labels[$type]) ? $labels[$type] : $type;
            $supported = in_array($label, $engine_types, true) || in_array(strtolower($label), array_map('strtolower', $engine_types), true);
        ?>
            <label class="wio-type-label <?php echo $supported ? '' : 'wio-type-unsupported'; ?>">
                <input type="checkbox" name="webp_image_optimizer_settings[allowed_types][]" <?php checked(in_array($type, $allowed)); ?> value="<?php echo esc_attr($type); ?>" <?php echo $supported ? '' : 'disabled'; ?>>
                <?php echo esc_html($label); ?>
                <?php if (!$supported) : ?><span class="wio-badge wio-badge--muted"><?php _e('unsupported', 'webp-image-optimizer'); ?></span><?php endif; ?>
            </label>
        <?php endforeach; ?>
        </div>
        <p class="description"><?php _e('Formats marked unsupported require Imagick. SVG is not convertible.', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function set_alt_text_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $checked = isset($options['set_alt_text']) ? (bool)$options['set_alt_text'] : true;
?>
        <label>
            <input type="checkbox" name="webp_image_optimizer_settings[set_alt_text]" <?php checked($checked); ?> value="1">
            <?php _e('Auto-generate alt text from the filename on upload.', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('Improves SEO and accessibility. Example: "team-photo-2024.jpg" → "Team photo 2024".', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function png_lossless_render()
    {
        $options = get_option('webp_image_optimizer_settings');
        $checked = isset($options['png_lossless']) ? (bool)$options['png_lossless'] : false;
?>
        <label>
            <input type="checkbox" name="webp_image_optimizer_settings[png_lossless]" <?php checked($checked); ?> value="1">
            <?php _e('Use lossless WebP for PNG images (preserves every pixel).', 'webp-image-optimizer'); ?>
        </label>
        <p class="description"><?php _e('Lossless keeps full quality but larger files. Lossy is usually much smaller.', 'webp-image-optimizer'); ?></p>
<?php
    }

    public function ai_alt_text_render()
    {
        $options    = get_option('webp_image_optimizer_settings');
        $enabled    = isset($options['ai_alt_text']) ? (bool) $options['ai_alt_text'] : false;
        $provider   = isset($options['ai_provider']) ? $options['ai_provider'] : 'nvidia';
        $api_key    = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        $model      = isset($options['ai_model']) ? $options['ai_model'] : '';
        $prompt     = isset($options['ai_prompt']) ? $options['ai_prompt'] : '';
        $masked_key = $api_key ? '••••' . substr($api_key, -4) : '';

        $providers = [
            'nvidia'     => 'NVIDIA Build — recommended (free vision models)',
            'anthropic'  => 'Anthropic Claude',
            'openai'     => 'OpenAI',
            'xai'        => 'xAI Grok',
            'mistral'    => 'Mistral AI (Pixtral vision)',
            'google'     => 'Google Gemini',
            'groq'       => 'Groq (free tier available)',
            'openrouter' => 'OpenRouter (multi-provider)',
        ];
        $default_models = [
            'nvidia'     => 'google/gemma-3-27b-it',
            'anthropic'  => 'claude-sonnet-4-20250514',
            'openai'     => 'gpt-4o-mini',
            'xai'        => 'grok-2-vision-latest',
            'mistral'    => 'pixtral-large-latest',
            'google'     => 'gemini-2.0-flash',
            'groq'       => 'llama-3.2-90b-vision-preview',
            'openrouter' => 'openrouter/free',
        ];
?>
        <div class="wio-ai-settings">
            <label>
                <input type="checkbox" name="webp_image_optimizer_settings[ai_alt_text]" <?php checked($enabled); ?> value="1" id="wio-ai-toggle">
                <?php _e('Use AI vision model to generate descriptive alt text on upload.', 'webp-image-optimizer'); ?>
            </label>
            <p class="description"><?php _e('When enabled, each uploaded image is sent to an AI vision model which returns a short, descriptive alt text. Falls back to filename if the API call fails.', 'webp-image-optimizer'); ?></p>
            <p class="description"><strong><?php _e('Recommended:', 'webp-image-optimizer'); ?></strong> <?php _e('NVIDIA Build is tested and confirmed working with free vision models. Sign up at build.nvidia.com, get an API key, and select NVIDIA Build below. No credit card required.', 'webp-image-optimizer'); ?></p>

            <div class="wio-ai-fields" id="wio-ai-fields" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                <table class="wio-ai-table">
                    <tr>
                        <th><label for="wio-ai-provider"><?php _e('Provider', 'webp-image-optimizer'); ?></label></th>
                        <td>
                            <select name="webp_image_optimizer_settings[ai_provider]" id="wio-ai-provider">
                                <?php foreach ($providers as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($provider, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wio-ai-key"><?php _e('API Key', 'webp-image-optimizer'); ?></label></th>
                        <td>
                            <input type="password" name="webp_image_optimizer_settings[ai_api_key]" id="wio-ai-key" value="<?php echo esc_attr($masked_key); ?>" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e('Paste your API key…', 'webp-image-optimizer'); ?>">
                            <button type="button" class="button" id="wio-ai-test"><?php _e('Test Connection', 'webp-image-optimizer'); ?></button>
                            <span id="wio-ai-test-result"></span>
                            <p class="description">
                                <?php _e('Your API key is stored in the database and only sent to the selected provider.', 'webp-image-optimizer'); ?>
                                <span class="wio-ai-key-help" id="wio-ai-key-help"></span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wio-ai-model"><?php _e('Model', 'webp-image-optimizer'); ?></label></th>
                        <td>
                            <input type="text" name="webp_image_optimizer_settings[ai_model]" id="wio-ai-model" value="<?php echo esc_attr($model); ?>" class="regular-text" placeholder="<?php echo esc_attr($default_models[$provider] ?? ''); ?>">
                            <p class="description" id="wio-ai-model-help"><?php _e('Leave empty for the default model.', 'webp-image-optimizer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wio-ai-prompt"><?php _e('Custom Prompt', 'webp-image-optimizer'); ?></label></th>
                        <td>
                            <textarea name="webp_image_optimizer_settings[ai_prompt]" id="wio-ai-prompt" rows="3" class="large-text" placeholder="<?php esc_attr_e('Describe this image in one concise sentence for use as alt text. Focus on the main subject, action, and context. Keep it under 125 characters.', 'webp-image-optimizer'); ?>"><?php echo esc_textarea($prompt); ?></textarea>
                            <p class="description"><?php _e('Leave empty for the default prompt. The image will be attached to this prompt.', 'webp-image-optimizer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
<?php
    }

    /* ─── Settings page with tabs ───────────────────────────────────── */

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'webp-image-optimizer'));
        }

        $info      = \WIO_Plugin::detect_engine();
        $engine    = $info['engine'];
        $supported = $info['supported'];
        $stats     = \WIO_Plugin::get_stats();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        $valid_tabs = ['settings', 'bulk', 'stats'];
        if (!in_array($active_tab, $valid_tabs, true)) {
            $active_tab = 'settings';
        }
    ?>
        <div class="wrap wio-wrap">

            <!-- Header -->
            <div class="wio-header">
                <div class="wio-header__title">
                    <h1><span class="dashicons dashicons-images-alt2"></span> <?php _e('WebP Image Optimizer', 'webp-image-optimizer'); ?></h1>
                </div>
                <div class="wio-header__badge">
                    <span class="wio-engine-badge wio-engine-badge--<?php echo $engine !== 'none' ? 'ok' : 'error'; ?>">
                        <span class="dashicons dashicons-<?php echo $engine !== 'none' ? 'yes-alt' : 'warning'; ?>"></span>
                        <?php printf(__('Engine: %s', 'webp-image-optimizer'), esc_html(ucfirst($engine))); ?>
                    </span>
                    <span class="wio-engine-formats"><?php echo esc_html(implode(', ', $supported)); ?></span>
                </div>
            </div>

            <!-- Tabs -->
            <nav class="wio-tabs" role="tablist">
                <a href="<?php echo esc_url(add_query_arg('tab', 'settings')); ?>" class="wio-tab <?php echo $active_tab === 'settings' ? 'wio-tab--active' : ''; ?>" role="tab" aria-selected="<?php echo $active_tab === 'settings' ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php _e('Settings', 'webp-image-optimizer'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'bulk')); ?>" class="wio-tab <?php echo $active_tab === 'bulk' ? 'wio-tab--active' : ''; ?>" role="tab" aria-selected="<?php echo $active_tab === 'bulk' ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-update"></span> <?php _e('Bulk Convert', 'webp-image-optimizer'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'stats')); ?>" class="wio-tab <?php echo $active_tab === 'stats' ? 'wio-tab--active' : ''; ?>" role="tab" aria-selected="<?php echo $active_tab === 'stats' ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-chart-bar"></span> <?php _e('Statistics', 'webp-image-optimizer'); ?>
                </a>
            </nav>

            <!-- Tab: Settings -->
            <div class="wio-tab-content <?php echo $active_tab === 'settings' ? 'wio-tab-content--active' : ''; ?>" id="wio-panel-settings" role="tabpanel">
                <form action="options.php" method="post" class="wio-settings-form">
                    <?php
                    settings_fields('webp_image_optimizer_settings');
                    do_settings_sections('webp_image_optimizer_settings');
                    submit_button(__('Save Settings', 'webp-image-optimizer'));
                    ?>
                </form>
            </div>

            <!-- Tab: Bulk Convert -->
            <div class="wio-tab-content <?php echo $active_tab === 'bulk' ? 'wio-tab-content--active' : ''; ?>" id="wio-panel-bulk" role="tabpanel">
                <div class="wio-card">
                    <h2><?php _e('Bulk Convert Existing Images', 'webp-image-optimizer'); ?></h2>
                    <p class="wio-card__desc"><?php _e('Convert all eligible images in your Media Library to WebP format. Images already in WebP will be skipped. This process runs in the background one image at a time to avoid server timeouts.', 'webp-image-optimizer'); ?></p>

                    <div class="wio-bulk-status" id="wio-bulk-status">
                        <div class="wio-bulk-counts">
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-bulk-eligible">—</span>
                                <span class="wio-bulk-count__label"><?php _e('Eligible', 'webp-image-optimizer'); ?></span>
                            </div>
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-bulk-done">0</span>
                                <span class="wio-bulk-count__label"><?php _e('Converted', 'webp-image-optimizer'); ?></span>
                            </div>
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-bulk-skipped">0</span>
                                <span class="wio-bulk-count__label"><?php _e('Skipped', 'webp-image-optimizer'); ?></span>
                            </div>
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-bulk-saved">0 KB</span>
                                <span class="wio-bulk-count__label"><?php _e('Saved', 'webp-image-optimizer'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="wio-progress" id="wio-progress" style="display:none;">
                        <div class="wio-progress__bar">
                            <div class="wio-progress__fill" id="wio-progress-fill" style="width:0%"></div>
                        </div>
                        <p class="wio-progress__text" id="wio-progress-text"></p>
                    </div>

                    <div class="wio-bulk-log" id="wio-bulk-log" style="display:none;">
                        <h3><?php _e('Conversion Log', 'webp-image-optimizer'); ?></h3>
                        <div class="wio-bulk-log__entries" id="wio-bulk-log-entries"></div>
                    </div>

                    <div class="wio-bulk-actions">
                        <button type="button" class="button button-primary button-hero" id="wio-bulk-start">
                            <span class="dashicons dashicons-update"></span> <?php _e('Start Bulk Conversion', 'webp-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="wio-bulk-stop" style="display:none;">
                            <?php _e('Stop', 'webp-image-optimizer'); ?>
                        </button>
                    </div>
                </div>

                <!-- Bulk AI Alt Text -->
                <div class="wio-card" style="margin-top: 20px;">
                    <h2><span class="dashicons dashicons-format-image"></span> <?php _e('Bulk AI Alt Text', 'webp-image-optimizer'); ?></h2>
                    <?php
                    $ai_options = get_option('webp_image_optimizer_settings');
                    $ai_ready = !empty($ai_options['ai_alt_text']) && !empty($ai_options['ai_api_key']);
                    ?>
                    <?php if ($ai_ready) : ?>
                    <p class="wio-card__desc"><?php _e('Generate AI alt text for all images in your Media Library that are missing alt text. Images with existing alt text will be skipped.', 'webp-image-optimizer'); ?></p>

                    <div class="wio-bulk-status" id="wio-ai-bulk-status">
                        <div class="wio-bulk-counts">
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-ai-eligible">&mdash;</span>
                                <span class="wio-bulk-count__label"><?php _e('Missing Alt Text', 'webp-image-optimizer'); ?></span>
                            </div>
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-ai-done">0</span>
                                <span class="wio-bulk-count__label"><?php _e('Generated', 'webp-image-optimizer'); ?></span>
                            </div>
                            <div class="wio-bulk-count">
                                <span class="wio-bulk-count__number" id="wio-ai-skipped">0</span>
                                <span class="wio-bulk-count__label"><?php _e('Failed', 'webp-image-optimizer'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="wio-progress" id="wio-ai-progress" style="display:none;">
                        <div class="wio-progress__bar">
                            <div class="wio-progress__fill" id="wio-ai-progress-fill" style="width:0%"></div>
                        </div>
                        <p class="wio-progress__text" id="wio-ai-progress-text"></p>
                    </div>

                    <div class="wio-bulk-log" id="wio-ai-log" style="display:none;">
                        <h3><?php _e('Generation Log', 'webp-image-optimizer'); ?></h3>
                        <div class="wio-bulk-log__entries" id="wio-ai-log-entries"></div>
                    </div>

                    <div class="wio-bulk-actions">
                        <button type="button" class="button button-primary button-hero" id="wio-ai-bulk-start">
                            <span class="dashicons dashicons-format-image"></span> <?php _e('Generate Missing Alt Text', 'webp-image-optimizer'); ?>
                        </button>
                        <button type="button" class="button" id="wio-ai-bulk-stop" style="display:none;">
                            <?php _e('Stop', 'webp-image-optimizer'); ?>
                        </button>
                    </div>
                    <?php else : ?>
                    <p class="wio-card__desc"><?php _e('Enable AI Alt Text in the Settings tab and add your API key to use bulk AI alt text generation.', 'webp-image-optimizer'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Stats -->
            <div class="wio-tab-content <?php echo $active_tab === 'stats' ? 'wio-tab-content--active' : ''; ?>" id="wio-panel-stats" role="tabpanel">
                <div class="wio-stats-grid">
                    <div class="wio-stat-card">
                        <span class="dashicons dashicons-format-gallery wio-stat-card__icon"></span>
                        <div class="wio-stat-card__value"><?php echo esc_html(number_format_i18n($stats['total'])); ?></div>
                        <div class="wio-stat-card__label"><?php _e('Total Images', 'webp-image-optimizer'); ?></div>
                    </div>
                    <div class="wio-stat-card wio-stat-card--success">
                        <span class="dashicons dashicons-yes-alt wio-stat-card__icon"></span>
                        <div class="wio-stat-card__value"><?php echo esc_html(number_format_i18n($stats['webp'])); ?></div>
                        <div class="wio-stat-card__label"><?php _e('WebP Images', 'webp-image-optimizer'); ?></div>
                    </div>
                    <div class="wio-stat-card">
                        <span class="dashicons dashicons-image-filter wio-stat-card__icon"></span>
                        <div class="wio-stat-card__value"><?php echo esc_html(number_format_i18n($stats['non_webp'])); ?></div>
                        <div class="wio-stat-card__label"><?php _e('Unconverted', 'webp-image-optimizer'); ?></div>
                    </div>
                    <div class="wio-stat-card wio-stat-card--highlight">
                        <span class="dashicons dashicons-performance wio-stat-card__icon"></span>
                        <div class="wio-stat-card__value"><?php echo esc_html(size_format($stats['bytes_saved'])); ?></div>
                        <div class="wio-stat-card__label"><?php _e('Space Saved', 'webp-image-optimizer'); ?></div>
                    </div>
                </div>

                <?php if ($stats['total'] > 0) : ?>
                <div class="wio-card wio-conversion-ratio">
                    <h3><?php _e('Conversion Progress', 'webp-image-optimizer'); ?></h3>
                    <?php $pct = $stats['total'] > 0 ? round(($stats['webp'] / $stats['total']) * 100) : 0; ?>
                    <div class="wio-progress__bar wio-progress__bar--large">
                        <div class="wio-progress__fill wio-progress__fill--<?php echo $pct >= 80 ? 'good' : ($pct >= 40 ? 'ok' : 'low'); ?>" style="width:<?php echo esc_attr($pct); ?>%"></div>
                    </div>
                    <p class="wio-progress__summary">
                        <?php printf(
                            __('%1$s of %2$s images converted (%3$s%%)', 'webp-image-optimizer'),
                            '<strong>' . esc_html(number_format_i18n($stats['webp'])) . '</strong>',
                            '<strong>' . esc_html(number_format_i18n($stats['total'])) . '</strong>',
                            esc_html($pct)
                        ); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Help & Donation (always visible below tabs) -->
            <div class="wio-footer-cards">
                <div class="wio-card help-card">
                    <div class="help-card-header">
                        <h2><?php _e('Help & Tips', 'webp-image-optimizer'); ?></h2>
                        <button type="button" class="button" id="wio-help-toggle" aria-expanded="false" aria-controls="wio-help-content" data-show="<?php echo esc_attr(__('Show', 'webp-image-optimizer')); ?>" data-hide="<?php echo esc_attr(__('Hide', 'webp-image-optimizer')); ?>">
                            <?php _e('Show', 'webp-image-optimizer'); ?>
                        </button>
                    </div>
                    <div id="wio-help-content">
                        <ul class="wio-help-list">
                            <li><?php _e('PNG Lossless preserves pixel data and may ignore the Quality setting.', 'webp-image-optimizer'); ?></li>
                            <li><?php _e('If Imagick is unavailable, GD will be used. Some formats (e.g., TIFF/BMP/HEIC) require Imagick.', 'webp-image-optimizer'); ?></li>
                            <li><?php _e('Retain Original keeps source files for safety. Disable to save disk space once verified.', 'webp-image-optimizer'); ?></li>
                            <li><?php _e('Alt Text is derived from filenames. Fine-tune in the Media Library for key images.', 'webp-image-optimizer'); ?></li>
                            <li><?php _e('Bulk Convert processes images one at a time. You can stop and resume at any point.', 'webp-image-optimizer'); ?></li>
                        </ul>
                        <p class="wio-help-links">
                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="button-link"><?php _e('Media Library', 'webp-image-optimizer'); ?></a>
                            | <a href="<?php echo esc_url(admin_url('site-health.php')); ?>" class="button-link"><?php _e('Site Health', 'webp-image-optimizer'); ?></a>
                            | <a href="https://github.com/wikiwyrhead/webp-image-optimizer#readme" target="_blank" rel="noopener noreferrer" class="button-link"><?php _e('Documentation', 'webp-image-optimizer'); ?></a>
                        </p>
                    </div>
                </div>
                <div class="wio-card donation-card">
                    <h2><span class="dashicons dashicons-heart wio-heart-icon"></span> <?php _e('Support Development', 'webp-image-optimizer'); ?></h2>
                    <p><?php _e('If you find this plugin useful, please consider a small donation to support its continued development.', 'webp-image-optimizer'); ?></p>
                    <a href="https://www.paypal.com/paypalme/arnelborresgo" target="_blank" rel="noopener noreferrer" class="button button-primary">
                        <?php _e('Donate with PayPal', 'webp-image-optimizer'); ?>
                    </a>
                </div>
            </div>

            <!-- Documentation Modal -->
            <div id="wio-modal" class="wio-modal" aria-hidden="true" role="dialog" aria-modal="true">
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
                                        <li><a href="#webp-compression-method" class="wio-docs__link" data-section="webp-compression-method"><?php _e('Compression Method', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#retain-original-image" class="wio-docs__link" data-section="retain-original-image"><?php _e('Retain Original', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#allowed-image-types" class="wio-docs__link" data-section="allowed-image-types"><?php _e('Image Types', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#set-alt-text" class="wio-docs__link" data-section="set-alt-text"><?php _e('Alt Text', 'webp-image-optimizer'); ?></a></li>
                                        <li><a href="#png-lossless" class="wio-docs__link" data-section="png-lossless"><?php _e('PNG Lossless', 'webp-image-optimizer'); ?></a></li>
                                    </ul>
                                </nav>
                                <div class="wio-docs__content">
                                    <h3><?php _e('Configuration Guide', 'webp-image-optimizer'); ?></h3>

                                    <h4 id="image-quality"><?php _e('Image Quality', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Range: 0–100. Higher values look better but produce larger files. Recommended: 80 for most sites, 75–85 for photographs, 80–90 for screenshots.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="webp-compression-method"><?php _e('WebP Compression Method', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Effort level 0 (fastest) to 6 (best compression). Only affects conversion time, not serving speed. Default 6 is safe for most servers.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="retain-original-image"><?php _e('Retain Original Image', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Keeps originals alongside WebP for rollback. Recommended initially; disable once satisfied to save disk space.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="allowed-image-types"><?php _e('Allowed Image Types', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('JPEG, PNG, GIF on by default. BMP/TIFF are uncommon. HEIC/HEIF require Imagick. SVG is vector and not convertible.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="set-alt-text"><?php _e('Set Alt Text', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Generates alt text from filenames: "team-photo-2024.jpg" → "Team photo 2024". Refine important images manually for best SEO.', 'webp-image-optimizer'); ?></p>

                                    <h4 id="png-lossless"><?php _e('PNG Lossless', 'webp-image-optimizer'); ?></h4>
                                    <p><?php _e('Preserves every pixel—ideal for logos and diagrams. Produces larger files than lossy. Transparency preserved in both modes.', 'webp-image-optimizer'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wio-modal__footer">
                        <a href="https://github.com/wikiwyrhead/webp-image-optimizer#readme" target="_blank" rel="noopener noreferrer" id="wio-open-new" class="button button-secondary"><?php _e('Open in new tab', 'webp-image-optimizer'); ?></a>
                        <button type="button" class="button button-primary" data-close="1"><?php _e('Close', 'webp-image-optimizer'); ?></button>
                    </div>
                </div>
            </div>

        </div>
<?php
    }
}
