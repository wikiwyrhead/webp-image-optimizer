<?php
// Run only against a disposable WordPress installation. Never point this at production.
if (getenv('WIO_DISPOSABLE_TEST_SITE') !== 'yes' || !getenv('WIO_WP_ROOT')) {
    fwrite(STDERR, "Set WIO_DISPOSABLE_TEST_SITE=yes and WIO_WP_ROOT to an installed test site.\n");
    exit(1);
}
$_SERVER['HTTP_HOST'] = 'wio.test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require rtrim(getenv('WIO_WP_ROOT'), '/') . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/../webp-image-optimizer.php';
WIO_Plugin::get_instance()->init();
WebPImageOptimizer\Admin::activate();
$image = WebPImageOptimizer\Image::get_instance();
$count = 0;
$ids = [];
$token = 'wio-' . uniqid();
function verify($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
    echo 'PASS ' . ++$GLOBALS['count'] . ': ' . $message . PHP_EOL;
}
function picture($path) {
    $im = imagecreatetruecolor(400, 260);
    for ($x = 0; $x < 400; $x++) for ($y = 0; $y < 260; $y++) imagesetpixel($im, $x, $y, ((($x * 17) % 256) << 16) | ((($y * 13) % 256) << 8) | (($x * $y) % 256));
    if (substr($path, -4) === '.jpg') imagejpeg($im, $path, 95); else imagepng($im, $path, 0);
    imagedestroy($im);
}
function register_image($path, $mime) {
    $id = wp_insert_attachment(['post_title' => basename($path), 'post_status' => 'inherit', 'post_mime_type' => $mime], $path, 0, true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    $GLOBALS['ids'][] = $id;
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $path));
    return $id;
}
function upload_image($name) {
    $temp = tempnam(sys_get_temp_dir(), 'wio'); picture($temp);
    $file = ['name' => $name, 'tmp_name' => $temp, 'error' => 0, 'size' => filesize($temp)];
    $upload = wp_handle_sideload($file, ['test_form' => false]);
    if (isset($upload['error'])) throw new RuntimeException($upload['error']);
    $id = register_image($upload['file'], $upload['type']);
    return [$id, $upload];
}
$settings = ['quality' => 80, 'method' => 6, 'png_lossless' => false, 'retain_original' => true, 'allowed_types' => [], 'set_alt_text' => false, 'ai_alt_text' => false];
try {
    update_option('webp_image_optimizer_settings', $settings);
    list($disabled_id, $disabled) = upload_image($token . '-disabled.png');
    verify($disabled['type'] === 'image/png', 'Real sideload honors empty format selection');
    $settings['allowed_types'] = ['image/png', 'image/jpeg', 'image/gif'];
    update_option('webp_image_optimizer_settings', $settings);
    list($upload_id, $upload) = upload_image($token . '-new.png');
    verify($upload['type'] === 'image/webp' && wp_get_attachment_metadata($upload_id)['width'] === 400, 'Real sideload creates WebP attachment and valid metadata');
    verify((int) get_post_meta($upload_id, '_wio_bytes_saved', true) > 0, 'Real upload savings persist in WordPress postmeta');
    $retained = get_post_meta($upload_id, '_wio_retained_files', true);
    $uploads = wp_upload_dir(); $source = $uploads['basedir'] . '/' . $retained[0];
    verify(is_file($source), 'Retained upload source is tracked and exists');
    $other_path = preg_replace('/\.png$/', '.jpg', $source); picture($other_path);
    $other_id = register_image($other_path, 'image/jpeg');
    wp_delete_attachment($upload_id, true);
    verify(!is_file($source) && !is_file($upload['file']) && is_file($other_path), 'Real attachment deletion removes owned files and preserves unrelated same-basename image');
    $raw = $uploads['path'] . '/' . $token . '-bulk.png'; picture($raw);
    $bulk_id = register_image($raw, 'image/png');
    $old_meta = wp_get_attachment_metadata($bulk_id);
    $old_sizes = [];
    foreach ($old_meta['sizes'] as $size) $old_sizes[] = dirname($raw) . '/' . $size['file'];
    $settings['retain_original'] = false; update_option('webp_image_optimizer_settings', $settings);
    $result = $image->convert_existing($bulk_id);
    verify($result['success'], 'Real WordPress bulk conversion succeeds');
    verify(is_file($raw) && count(array_filter($old_sizes, 'is_file')) === count($old_sizes), 'Real bulk conversion preserves original and thumbnail URLs');
    verify(get_post_mime_type($bulk_id) === 'image/webp' && substr(wp_get_attachment_url($bulk_id), -5) === '.webp', 'WordPress MIME and attachment URL switch to WebP');
    $metadata = wp_get_attachment_metadata($bulk_id);
    $valid_sizes = true;
    foreach ($metadata['sizes'] as $size) {
        $path = dirname(get_attached_file($bulk_id)) . '/' . $size['file'];
        if (!is_file($path) || $size['mime-type'] !== 'image/webp') $valid_sizes = false;
    }
    verify($valid_sizes && !empty($metadata['sizes']), 'WordPress regenerates valid WebP thumbnails');
    $fail_path = $uploads['path'] . '/' . $token . '-failure.png'; picture($fail_path);
    $fail_id = register_image($fail_path, 'image/png');
    $reject = function($metadata, $id) use ($fail_id) {
        return $id === $fail_id && !empty($metadata['file']) && substr($metadata['file'], -5) === '.webp' ? [] : $metadata;
    };
    add_filter('wp_update_attachment_metadata', $reject, 99, 2);
    $result = $image->convert_existing($fail_id);
    remove_filter('wp_update_attachment_metadata', $reject, 99);
    verify(!$result['success'] && get_attached_file($fail_id) === $fail_path && get_post_mime_type($fail_id) === 'image/png' && is_file($fail_path), 'Real metadata storage rejection restores original path and MIME');
    list($delete_source_id, $delete_source) = upload_image($token . '-delete-source.png');
    $original = preg_replace('/\.webp$/', '.png', $delete_source['file']);
    verify(!is_file($original) && is_file($delete_source['file']), 'Retention disabled deletes only a new upload source');
    echo "All $count WordPress integration checks passed on WordPress $wp_version / PHP " . PHP_VERSION . ".\n";
} finally {
    foreach ($ids as $id) if (get_post($id)) wp_delete_attachment($id, true);
    $dir = wp_upload_dir()['path'];
    foreach (glob($dir . '/' . $token . '*') as $file) if (is_file($file)) unlink($file);
}
