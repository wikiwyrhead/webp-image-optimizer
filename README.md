# WebP Image Optimizer

![Plugin Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![GPLv2 License](https://img.shields.io/badge/license-GPLv2-blue.svg)

## Description

WebP Image Optimizer is a comprehensive WordPress plugin that converts uploaded images to WebP format, generates AI-powered alt text, and provides a statistics dashboard -- all from a clean, tabbed admin interface. It supports 8 AI vision providers for automatic and bulk alt text generation, handles JPEG, PNG, GIF, BMP, TIFF, and HEIF/HEIC source formats, and uses ImageMagick (preferred) or GD as the conversion engine.

## Features

### Image Conversion
- **Automatic WebP Conversion** -- JPEG, PNG, GIF, BMP, TIFF, and HEIF/HEIC images are converted to WebP on upload.
- **Customizable Quality** -- Compression quality slider (0-100, default 80).
- **Compression Method** -- WebP method slider (0-6, default 6) to balance speed vs. file size.
- **PNG Lossless Mode** -- Optional lossless WebP for PNGs when pixel-perfect output is required.
- **Retain Originals** -- Optionally keep original files alongside WebP versions.
- **Engine Auto-Detection** -- Uses ImageMagick when available; falls back to GD automatically.
- **HEIF/HEIC Support** -- Converts Apple HEIF/HEIC photos when ImageMagick supports the format.
- **50 MB Safety Limit** -- Files larger than 50 MB are skipped to prevent server timeouts.

### AI Alt Text Generation (8 Providers)
- **Automatic on Upload** -- AI generates alt text the moment an image is uploaded (when enabled).
- **Per-Image Generate Button** -- "Generate AI Alt Text" / "Regenerate" button on every image in the Media Library.
- **Bulk AI Alt Text** -- One-click generation for all images missing alt text, with progress bar, log, and stop button.
- **8 Supported Providers:**
  - **NVIDIA Build** (recommended, free) -- Default model: `google/gemma-3-27b-it`
  - **OpenAI** -- Default model: `gpt-4o-mini`
  - **Anthropic Claude** -- Default model: `claude-sonnet-4-20250514`
  - **Google Gemini** -- Default model: `gemini-2.0-flash`
  - **xAI Grok** -- Default model: `grok-2-vision-latest`
  - **Mistral AI** -- Default model: `pixtral-large-latest`
  - **Groq** -- Default model: `llama-3.2-90b-vision-preview`
  - **OpenRouter** -- Default model: `openrouter/free`
- **Test Connection** -- Verify your API key and provider from the settings page.
- **Custom Model Override** -- Specify any model name supported by your chosen provider.

### Bulk Convert
- **Bulk WebP Conversion** -- Convert all existing unconverted images to WebP in one click.
- **Progress Tracking** -- Real-time progress bar, converted/skipped counts, and scrollable log.
- **Stop Button** -- Safely halt bulk conversion at any time.

### Statistics Dashboard
- **At-a-Glance Stats** -- Total images, WebP count, unconverted count, and total bytes saved.
- **WebP Coverage Chart** -- Visual bar showing the percentage of your library in WebP.

### Admin UI and UX
- **Tabbed Interface** -- Settings, Bulk Convert, and Statistics tabs.
- **Design System CSS** -- Consistent cards, buttons, progress bars, and log styling.
- **Contextual Help Card** -- Collapsible Help and Tips section with per-user state saved via AJAX.
- **In-Admin Documentation Modal** -- Learn more links open inline docs without leaving the page.
- **Accessibility** -- ARIA attributes, keyboard support (Esc to close), screen-reader-friendly labels.
- **Filename-Based Alt Text** -- Fallback option to derive alt text from the filename when AI is not configured.

## Installation

1. Upload the `webp-image-optimizer` folder to `/wp-content/plugins/`.
2. Activate via **Plugins > Installed Plugins**.
3. Navigate to **Tools > WebP Image Optimizer**.

## Configuration

### Settings Tab

| Setting | Description | Default |
|---------|-------------|---------|
| Image Quality | WebP compression quality (0-100) | 80 |
| Compression Method | WebP method (0-6, higher = smaller but slower) | 6 |
| Retain Original Image | Keep source files after conversion | Enabled |
| Allowed Image Types | Which MIME types to auto-convert (JPEG, PNG, GIF, BMP, TIFF) | JPEG, PNG, GIF |
| PNG Lossless | Use lossless WebP for PNGs | Disabled |
| Set Alt Text from Filename | Derive alt text from filename | Enabled |
| AI Alt Text | Enable AI-powered alt text generation | Disabled |
| AI Provider | Vision model provider | NVIDIA Build |
| AI API Key | Provider API key | -- |
| AI Model | Model identifier (auto-filled per provider) | Per provider |

### AI Setup (Quick Start)

1. In the **Settings** tab, enable **AI Alt Text**.
2. Select your **AI Provider** (NVIDIA Build is free and recommended).
3. Paste your **API Key**.
4. Click **Test Connection** to verify.
5. Save. New uploads will now get AI alt text automatically.

### Bulk Convert Tab

- **Bulk WebP Conversion** -- Converts all eligible non-WebP images. Shows eligible count, converted count, skipped count, and bytes saved.
- **Bulk AI Alt Text** -- Generates alt text for all images missing it. Shows missing count, generated count, and failed count. Only visible when AI is enabled and an API key is saved.

### Statistics Tab

View total images, WebP images, unconverted images, bytes saved, and a WebP coverage percentage bar.

## Requirements

- WordPress 5.0+
- PHP 7.0+
- GD or ImageMagick PHP extension
- For AI alt text: An API key from one of the 8 supported providers

## File Structure

```
webp-image-optimizer/
├── webp-image-optimizer.php   # Plugin entry point, AJAX handlers, Media Library button
├── uninstall.php              # Cleanup on uninstall
├── README.md                  # This file
├── assets/
│   ├── css/admin.css          # Admin design system styles
│   └── js/admin.js            # Admin interactivity (tabs, bulk, AI, modals)
└── includes/
    ├── class-admin.php        # Settings page, tabs, field renderers, sanitization
    └── class-image.php        # Image conversion, AI alt text, provider API calls
```

## Hooks and Filters

| Hook | Type | Description |
|------|------|-------------|
| `wp_generate_attachment_metadata` | Filter | Converts uploaded images and generates AI alt text |
| `delete_attachment` | Action | Deletes WebP and original files when attachment is removed |
| `wio_after_bulk_convert` | Action | Fires after each bulk-converted image |
| `attachment_fields_to_edit` | Filter | Adds Generate AI Alt Text button to Media Library |

## Support

For support, create an issue on the [GitHub repository](https://github.com/wikiwyrhead/webp-image-optimizer) or contact the plugin author.

## Changelog

### Version 2.0.0

**Major release -- AI Alt Text, Bulk Operations, and Complete UI Overhaul**

- **AI-Powered Alt Text Generation** with 8 vision model providers (NVIDIA Build, OpenAI, Anthropic Claude, Google Gemini, xAI Grok, Mistral AI, Groq, OpenRouter).
- **Automatic AI alt text on upload** -- generates alt text the moment an image is uploaded.
- **Per-image Generate AI Alt Text button** in Media Library attachment editor with regenerate support.
- **Bulk AI Alt Text** -- one-click generation for all images missing alt text with progress bar, log, and stop button.
- **Test Connection** button to verify API key and provider before saving.
- **HEIF/HEIC support** -- converts Apple HEIF/HEIC photos when ImageMagick supports the format.
- **Tabbed admin interface** -- Settings, Bulk Convert, and Statistics tabs.
- **Bulk WebP Conversion** -- convert all existing non-WebP images from the Media Library with progress tracking.
- **Statistics Dashboard** -- total images, WebP count, bytes saved, and WebP coverage chart.
- **Design system CSS** -- consistent cards, buttons, progress bars, logs, and stat cards.
- **Engine auto-detection** -- displays active engine (ImageMagick/GD) and supported formats on the settings page.
- **Improved file cleanup** -- properly deletes original, WebP, and intermediate sizes when an attachment is removed.
- **50 MB file size safety limit** to prevent server timeouts on very large files.
- **Debug logging** for AI API calls to assist troubleshooting.
- **Reasoning model fallback** -- handles providers that return reasoning tokens instead of content.

### Version 1.3.0

- Added contextual Help and Tips card with Show/Hide toggle and saved per-user open/closed state.
- Implemented in-admin documentation modal for Learn more with inline docs and left-side TOC.
- Moved inline admin styles/scripts into enqueued assets and load only on the settings page.
- Improved accessibility (ARIA, keyboard support) and overall UX.

### Version 1.2.4

- Improved deletion: all related files properly removed on attachment delete.
- Lossy/lossless PNG handling fixes.
- Removed SVG from allowed types.
- Fixed checkbox save states.
- Added debug logging for conversion.

### Version 1.2.3

- Added test feature for Retain Original Image setting.

### Version 1.2.2

- Admin dashboard under Settings for plugin options.
- Extended format support (BMP, TIFF).
- GD library fallback.
- Error handling and logging.
- Code organization into separate class files.

### Version 1.2.0

- Automatic alt text from filename.

### Version 1.1.1

- Minor bug fixes.

### Version 1.0.0

- Initial release.

## License

This plugin is licensed under the GPLv2 or later. See the [LICENSE](LICENSE) file for details.