# WebP Image Optimizer

![Plugin Version](https://img.shields.io/badge/version-1.2.3-blue.svg)
![GPLv2 License](https://img.shields.io/badge/license-GPLv2-blue.svg)

## Description

The WebP Image Optimizer is a WordPress plugin that enhances your website's performance by converting and compressing uploaded images (JPEG, PNG, GIF) to WebP format. WebP is a modern image format that provides superior compression and quality characteristics, which helps in reducing the load time of your website.

Additionally, the plugin offers an optional feature to automatically set the image alt text based on the image filename, improving your site's SEO and accessibility. This feature can be easily enabled or disabled from the plugin's settings page.

## Features

- **Automatic WebP Conversion:** Converts JPEG, PNG, GIF, BMP, TIFF, and SVG images to WebP format upon upload.
- **Customizable Image Quality:** Set the compression quality for WebP images from 0-100.
- **Retention of Original Images:** Option to retain the original uploaded images.
- **Allowed Image Types:** Select which image types (JPEG, PNG, GIF, BMP, TIFF, SVG) to convert to WebP.
- **Automatic Alt Text Setting:** Option to set the alt text based on the image filename upon upload, with hyphens removed and text converted to sentence case.
- **Test Feature:** Test the "Retain Original Image" feature directly from the settings page.

## Installation

1. Upload the `webp-image-optimizer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > WebP Image Optimizer to configure the plugin.

## Configuration

1. **Image Quality:** Set the quality of WebP images (0-100). Higher values result in better quality but larger file sizes.
2. **WebP Compression Method:** Set the compression method (0-6). Higher values result in better compression but slower conversion times.
3. **Retain Original Image:** Choose whether to keep the original images after conversion to WebP.
4. **Allowed Image Types:** Select which image types should be automatically converted to WebP.
5. **Set Alt Text:** Enable or disable automatic alt text generation for uploaded images.

## Testing

You can test the "Retain Original Image" feature by clicking the "Run Test" button on the settings page. This will check your media library and show you how many WebP and original images are present.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD or ImageMagick PHP extension

## Support

For support, please create an issue on the [GitHub repository](https://github.com/wikiwyrhead/webp-image-optimizer) or contact the plugin author.

## Changelog

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
