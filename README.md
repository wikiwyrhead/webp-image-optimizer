# WebP Image Optimizer

![Plugin Version](https://img.shields.io/badge/version-1.3.0-blue.svg)
![GPLv2 License](https://img.shields.io/badge/license-GPLv2-blue.svg)

## Description

The WebP Image Optimizer is a WordPress plugin that enhances your website's performance by converting and compressing uploaded images (JPEG, PNG, GIF) to WebP format. WebP is a modern image format that provides superior compression and quality characteristics, which helps in reducing the load time of your website.

Additionally, the plugin offers an optional feature to automatically set the image alt text based on the image filename, improving your site's SEO and accessibility. This feature can be easily enabled or disabled from the plugin's settings page.

## Features

- **Automatic WebP Conversion:** Converts JPEG, PNG, GIF, BMP, and TIFF images to WebP format upon upload.
- **Customizable Image Quality:** Set the compression quality for WebP images from 0-100.
- **Retention of Original Images:** Option to retain the original uploaded images.
- **Allowed Image Types:** Select which image types (JPEG, PNG, GIF, BMP, TIFF) to convert to WebP.
- **Automatic Alt Text Setting:** Option to set the alt text based on the image filename upon upload, with hyphens removed and text converted to sentence case.
- **PNG Lossless Toggle:** Choose lossless WebP for PNGs (preserves data) or lossy WebP for smaller files.
- **Contextual Help & Tips (new):** Collapsible help card on the plugin page (Tools > WebP Image Optimizer) with accessibility-friendly Show/Hide and per-user open/closed state persisting via AJAX.
- **In-admin Documentation Modal (new):** “Learn more” links open a polished modal with inline documentation and left-side navigation instead of navigating away.
- **Accessibility & UX (new):** ARIA attributes, keyboard Esc to close modal, and assets loaded only on the plugin page for performance.

## Installation

1. Upload the `webp-image-optimizer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Tools > WebP Image Optimizer to configure the plugin.

## Configuration

### Image Quality

Set the quality of WebP images (0-100). Higher values result in better quality but larger file sizes. The default value is 80.

### WebP Compression Method

Set the WebP compression method (0-6). Higher values result in better compression (smaller file sizes) but slower conversion times. The default value is 6.

### Retain Original Image

Choose whether to keep the original images after conversion to WebP. Recommended to keep originals until you verify WebP output across your theme.

### Allowed Image Types

Select which image types should be automatically converted to WebP. By default, JPEG, PNG, and GIF are enabled; BMP and TIFF are optional. SVG is not supported.

### Set Alt Text

Enable or disable automatic alt text generation for uploaded images, derived from the filename (hyphens removed, sentence case). You can refine alt text later in the Media Library.

### PNG Lossless

Use lossless WebP for PNG images when exact pixel preservation is required (e.g., UI assets). Lossless may ignore the quality setting and may produce larger files. Disable for lossy mode to achieve smaller file sizes.

## In-admin Help & Documentation

- A contextual “Help & Tips” card appears on the plugin page (Tools > WebP Image Optimizer). It can be collapsed/expanded with its state remembered per admin user.
- Each setting provides a “Learn more” link. Clicking it opens an in-admin modal with inline documentation and a left-hand table of contents.
- The modal supports keyboard navigation, closing via Esc/backdrop, and an “Open in new tab” link to the README anchor for external viewing.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD or ImageMagick PHP extension

## Support

For support, please create an issue on the [GitHub repository](https://github.com/wikiwyrhead/webp-image-optimizer) or contact the plugin author.

## Changelog

### Version 1.3.0

- Added contextual “Help & Tips” card with Show/Hide toggle and saved per-user open/closed state.
- Implemented in-admin documentation modal for “Learn more” with inline docs and left-side TOC.
- Moved inline admin styles/scripts into enqueued assets (`assets/css/admin.css`, `assets/js/admin.js`) and load only on the settings page.
- Improved accessibility (ARIA, keyboard support) and overall UX.

### Version 1.2.4

- Improved deletion: When deleting an image from the Media Library, all related files (original, WebP, and intermediate sizes) are now properly deleted from the file system.
- Lossy/lossless PNG handling: PNGs are now lossy by default unless lossless is explicitly selected. Lossless mode ignores the quality setting, as expected.
- Removed SVG from Allowed Image Types (SVG cannot be converted to WebP).
- Fixed admin settings: All checkboxes now save and reflect their state correctly, including 'Set Alt Text'.
- Improved conversion robustness: Always strips metadata, reduces color palette for lossy PNGs, and adds debug logging for file sizes before/after conversion.

### Version 1.2.3

- Added a test feature to the settings page to test the "Retain Original Image" feature.
- Updated the plugin description and features list.

### Version 1.2.2

- Added admin dashboard under 'Settings' for configuring plugin options:
  - Option to retain or delete original images after WebP conversion.
  - Ability to set image quality and WebP compression method.
  - Support for additional image types (BMP, TIFF, SVG).
  - Option to automatically set alt text based on the image filename.
- Extended image format support to include BMP, TIFF, and SVG.
- Implemented fallback to GD library if ImageMagick is unavailable.
- Added error handling and logging for conversion failures.
- Fixed bug where duplicate WebP files were created by disabling default WordPress image sizes to save disk space.
- Organized code into separate files for better maintainability.
- Updated plugin description, author information, and version.

### Version 1.2.0

- Added feature to automatically set image alt text based on the filename upon upload.
- Added a checkbox in the plugin settings to enable/disable the new feature.

### Version 1.1.1

- Fixed minor bugs related to WebP conversion and quality settings.

### Version 1.0.0

- Initial release with automatic WebP conversion and customizable settings.

## License

This plugin is licensed under the GPLv2 or later license. See the [LICENSE](LICENSE) file for details.
