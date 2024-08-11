# WebP Image Optimizer

![Plugin Version](https://img.shields.io/badge/version-1.2.2-blue.svg)
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

## Installation

1. **Download the Plugin:**

   - Clone or download the repository from GitHub.

2. **Upload the Plugin to WordPress:**

   - Navigate to `Plugins > Add New` in your WordPress dashboard.
   - Click `Upload Plugin` and select the downloaded ZIP file.
   - Install and activate the plugin.

3. **Configure the Plugin:**
   - Go to `Settings > WebP Image Optimizer` to configure the plugin settings.

## Usage

- **WebP Conversion:** Once activated, the plugin automatically converts uploaded images to WebP format based on the settings.
- **Alt Text Setting:** If enabled, the plugin sets the image alt text based on the filename.

## Changelog

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
