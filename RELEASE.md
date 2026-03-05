# WebP Image Optimizer v2.0.0

**Release Date:** March 5, 2026
**Tag:** `v2.0.0`
**Branch:** `development`

---

## 🚀 What's New

This is a major release introducing AI-powered alt text generation, bulk operations, a statistics dashboard, and a completely redesigned admin interface.

### AI-Powered Alt Text Generation

Generate SEO-friendly alt text for your images using vision AI models — automatically on upload, per-image from the Media Library, or in bulk for your entire library.

**8 Supported Providers:**

| Provider | Default Model | Free Tier |
|----------|--------------|-----------|
| NVIDIA Build ⭐ | `google/gemma-3-27b-it` | ✅ Free |
| OpenAI | `gpt-4o-mini` | Paid |
| Anthropic Claude | `claude-sonnet-4-20250514` | Paid |
| Google Gemini | `gemini-2.0-flash` | Free tier available |
| xAI Grok | `grok-2-vision-latest` | Paid |
| Mistral AI | `pixtral-large-latest` | Paid |
| Groq | `llama-3.2-90b-vision-preview` | Free tier available |
| OpenRouter | `openrouter/free` | Free models available |

### Bulk Operations

- **Bulk WebP Conversion** — Convert all existing non-WebP images in your Media Library with real-time progress bar, conversion log, and stop button.
- **Bulk AI Alt Text** — Generate alt text for all images missing it in one click. Sequential processing with progress tracking.

### Statistics Dashboard

- At-a-glance stats: total images, WebP count, unconverted images, bytes saved.
- Visual WebP coverage bar showing library conversion percentage.

### Admin UI Overhaul

- **Tabbed Interface** — Settings, Bulk Convert, and Statistics organized into clean tabs.
- **Design System** — Consistent card-based layout, progress bars, log panels, and stat cards.
- **Settings Link** — Quick access from the Plugins page.

### Additional Improvements

- **HEIF/HEIC Support** — Convert Apple HEIF/HEIC photos (requires ImageMagick with HEIC support).
- **Engine Auto-Detection** — Displays active engine (ImageMagick/GD) and supported formats.
- **50 MB File Size Limit** — Large files are safely skipped.
- **Improved File Cleanup** — All related files (original, WebP, intermediate sizes) properly deleted on attachment removal.
- **Debug Logging** — AI API calls logged to `debug.log` for troubleshooting.
- **Reasoning Model Fallback** — Handles providers that return reasoning tokens instead of content.

---

## 📋 Changelog

### Added
- AI alt text generation with 8 vision model providers
- Automatic AI alt text on image upload
- Per-image "Generate AI Alt Text" button in Media Library
- Bulk AI alt text generation for images missing alt text
- Test Connection button for AI provider verification
- Bulk WebP conversion with progress tracking
- Statistics dashboard with WebP coverage chart
- Tabbed admin interface (Settings / Bulk Convert / Statistics)
- HEIF/HEIC image format support
- Settings link on WordPress Plugins page
- 50 MB file size safety limit
- Debug logging for AI API calls

### Changed
- Complete admin UI redesign with card-based design system
- Updated plugin description to reflect new capabilities
- Author URI updated to arnelbg.com

### Fixed
- File cleanup now properly removes all related files on attachment delete
- Reasoning model responses (content:null) handled via fallback extraction

---

## 📦 Installation

1. Download the release ZIP or pull from the `development` branch.
2. Upload to `/wp-content/plugins/webp-image-optimizer/`.
3. Activate the plugin.
4. Go to **Tools → WebP Image Optimizer** to configure.

## ⚡ Quick Start for AI Alt Text

1. Go to **Tools → WebP Image Optimizer → Settings**.
2. Enable **AI Alt Text**.
3. Select **NVIDIA Build** (free, no credit card required).
4. Get an API key from [build.nvidia.com](https://build.nvidia.com) and paste it.
5. Click **Test Connection** → Save.
6. New uploads will automatically get AI-generated alt text.
7. For existing images, go to **Bulk Convert** tab → **Generate Missing Alt Text**.

---

**Full Changelog:** [v1.3.0...v2.0.0](https://github.com/wikiwyrhead/webp-image-optimizer/compare/v1.3.0...v2.0.0)
