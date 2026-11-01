<?php
// Isolated WordPress API doubles with real image encoding. No site or API keys needed.
define('ABSPATH', __DIR__ . '/');
define('WIO_MAX_CONVERT_SIZE', 50 * 1024 * 1024);
$root = sys_get_temp_dir() . '/wio-test-' . uniqid();
mkdir($root);
$options = ['quality' => 80, 'method' => 6, 'png_lossless' => false, 'retain_original' => false, 'allowed_types' => ['image/png', 'image/jpeg', 'image/gif'], 'set_alt_text' => false];
$meta = $attachments = [];
$fail_metadata = $fail_post = false;
function add_filter() {}
function add_action() {}
function do_action() {}
function get_option($name) { return $GLOBALS['options']; }
function wp_upload_dir() { return ['basedir' => $GLOBALS['root']]; }
function get_attached_file($id) { return $GLOBALS['attachments'][$id]['file'] ?? false; }
function update_attached_file($id, $file) { $GLOBALS['attachments'][$id]['file'] = $file; return true; }
function get_post_mime_type($id) { return $GLOBALS['attachments'][$id]['mime'] ?? ''; }
function wp_attachment_is_image($id) { return strpos(get_post_mime_type($id), 'image/') === 0; }
function wp_update_post($post, $error = false) {
    if ($GLOBALS['fail_post']) { $GLOBALS['fail_post'] = false; return new WP_Error(); }
    $GLOBALS['attachments'][$post['ID']]['mime'] = $post['post_mime_type']; return $post['ID'];
}
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function wp_get_attachment_metadata($id) { return get_post_meta($id, '_wp_attachment_metadata', true); }
function wp_update_attachment_metadata($id, $value) { return update_post_meta($id, '_wp_attachment_metadata', $value); }
function wp_generate_attachment_metadata($id, $file) { return $GLOBALS['fail_metadata'] ? [] : ['width' => 100, 'height' => 100, 'sizes' => []]; }
function delete_transient($key) {}
function size_format($size) { return (string) $size; }
function wp_delete_file($file) { if (is_file($file)) unlink($file); }
function wp_unique_filename($dir, $name) { $i = 0; $base = pathinfo($name, PATHINFO_FILENAME); while (file_exists($dir . '/' . $name)) $name = $base . '-' . ++$i . '.webp'; return $name; }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function sanitize_textarea_field($s) { return trim(strip_tags($s)); }
function get_the_title($id) { return 'Test Image'; }
function wp_check_filetype($file) { return ['type' => 'image/png']; }
function wp_json_encode($v) { return json_encode($v); }
function home_url() { return 'https://example.test'; }
function wp_remote_post($url, $args) { return $GLOBALS['http_response']; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
// Exercise the GD path through a minimal WordPress image-editor adapter.
function wp_get_image_editor($file) { return new TestGDEditor($file); }
class TestGDEditor {
    private $image;
    private $quality = 80;
    public function __construct($file) { $this->image = @imagecreatefromstring(file_get_contents($file)); }
    public function set_quality($quality) { $this->quality = $quality; }
    public function maybe_exif_rotate() {}
    public function save($path, $mime) {
        if (!$this->image || !imagewebp($this->image, $path, $this->quality)) return new WP_Error();
        return ['path' => $path];
    }
}
class WP_Error {}
function is_wp_error($r) { return $r instanceof WP_Error; }
class FakeDB {
    public $postmeta = 'postmeta';
    public $shared = false;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return $this->shared ? 999 : null; }
}
$wpdb = new FakeDB();
require __DIR__ . '/../includes/class-image.php';
require __DIR__ . '/../includes/class-admin.php';
$image = WebPImageOptimizer\Image::get_instance();
$admin = WebPImageOptimizer\Admin::get_instance();
$count = 0;
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); echo 'PASS ' . ++$GLOBALS['count'] . ': ' . $message . PHP_EOL; }
function fixture($name) {
    $file = $GLOBALS['root'] . '/' . $name;
    $im = imagecreatetruecolor(160, 100);
    for ($x = 0; $x < 160; $x++) for ($y = 0; $y < 100; $y++) imagesetpixel($im, $x, $y, (($x * 17) << 16) | (($y * 13) << 8) | (($x * $y) % 256));
    imagepng($im, $file, 0); imagedestroy($im); return $file;
}
try {
    $saved = $admin->sanitize_settings([]);
    check($saved['allowed_types'] === [], 'Unchecking every source format remains disabled');
    $options['allowed_types'] = [];
    $f = fixture('disabled.png');
    $upload = ['file' => $f, 'url' => 'https://example.test/disabled.png', 'type' => 'image/png'];
    check($image->handle_upload($upload) === $upload, 'Empty allowed formats prevents automatic conversion');
    $options['allowed_types'] = ['image/png', 'image/jpeg', 'image/gif'];
    $options['ai_provider'] = 'openai'; $options['ai_api_key'] = 'secret';
    check($admin->sanitize_settings(['ai_provider' => 'openai', 'ai_clear_key' => 1])['ai_api_key'] === '', 'Saved key can be removed');
    check($admin->sanitize_settings(['ai_provider' => 'google'])['ai_api_key'] === '', 'Switching providers does not reuse the previous secret');
    check($admin->sanitize_settings(['ai_provider' => 'openai'])['ai_api_key'] === 'secret', 'Unchanged provider retains its saved key');
    $r = $image->convert_to_webp($f, 'image/png', $options);
    $decoded = $r['success'] ? imagecreatefromwebp($r['webp_path']) : false;
    check($decoded && imagesx($decoded) === 160 && imagesy($decoded) === 100, 'Real image converts into decodable WebP: ' . json_encode($r));
    check(is_file($f), 'Conversion primitive preserves source');
    $bad = $root . '/bad.png'; file_put_contents($bad, 'not an image');
    check(!$image->convert_to_webp($bad, 'image/png', $options)['success'], 'Corrupt image returns failure without uncaught exception');
    $attachments[1] = ['file' => fixture('existing.png'), 'mime' => 'image/png'];
    $old = $attachments[1]['file']; file_put_contents($root . '/existing-80x80.png', 'old thumbnail');
    $meta[1]['_wp_attachment_metadata'] = ['sizes' => [['file' => 'existing-80x80.png']]];
    check($image->convert_existing(1)['success'], 'Bulk conversion succeeds');
    check(is_file($old) && is_file($root . '/existing-80x80.png'), 'Bulk conversion preserves old source and thumbnail URLs even with retention disabled');
    check(count($meta[1]['_wio_retained_files']) === 2, 'Bulk source files are explicitly tracked');
    $attachments[2] = ['file' => fixture('rollback.png'), 'mime' => 'image/png'];
    $old = $attachments[2]; $fail_metadata = true;
    check(!$image->convert_existing(2)['success'] && $attachments[2] === $old && is_file($old['file']), 'Metadata failure rolls attachment path and MIME back');
    $fail_metadata = false; $fail_post = true;
    check(!$image->convert_existing(2)['success'] && $attachments[2] === $old, 'Post update failure rolls back safely');
    $new = fixture('upload.png'); $u = $image->handle_upload(['file' => $new, 'url' => 'https://example.test/upload.png', 'type' => 'image/png']);
    $attachments[3] = ['file' => $u['file'], 'mime' => $u['type']];
    $image->set_image_alt_text_on_metadata([], 3);
    check(!empty($meta[3]['_wio_bytes_saved']), 'Upload savings reach attachment metadata even with alt text disabled');
    $independent = fixture('existing.jpg');
    $image->delete_related_files_on_remove(1);
    check(is_file($independent) && !is_file($root . '/existing.png'), 'Cleanup removes tracked sources and preserves same-basename attachments');
    $outside = tempnam(sys_get_temp_dir(), 'wio-outside');
    $meta[4]['_wio_retained_files'] = ['../' . basename($outside)];
    $image->delete_related_files_on_remove(4);
    check(is_file($outside), 'Cleanup rejects paths outside uploads'); unlink($outside);
    $shared = fixture('shared.png'); $meta[5]['_wio_retained_files'] = ['shared.png']; $wpdb->shared = true;
    $image->delete_related_files_on_remove(5);
    check(is_file($shared), 'Cleanup preserves a file referenced by another attachment'); $wpdb->shared = false;
    $options['retain_original'] = true;
    $new = fixture('retained-upload.png'); $u = $image->handle_upload(['file' => $new, 'url' => 'https://example.test/retained-upload.png', 'type' => 'image/png']);
    $attachments[6] = ['file' => $u['file'], 'mime' => $u['type']]; $image->set_image_alt_text_on_metadata([], 6);
    check(in_array('retained-upload.png', $meta[6]['_wio_retained_files'], true), 'Retained new upload source is tracked');
    $options['ai_model'] = ''; $options['ai_prompt'] = ''; $options['ai_alt_text'] = true;
    $http_response = ['code' => 200, 'body' => json_encode(['choices' => [['message' => ['content' => null, 'reasoning' => 'Use "A made-up description".']]]])];
    check(!$image->generate_ai_alt_text_for_existing(6)['success'], 'Reasoning-only responses are never published as alt text');
    $http_response['body'] = json_encode(['choices' => [['message' => ['content' => str_repeat('é', 140)]]]]);
    $r = $image->generate_ai_alt_text_for_existing(6);
    check($r['success'] && preg_match_all('/./u', $r['alt_text']) === 125, 'Unicode alt text is limited safely without requiring mbstring');
    unset($meta[6]['_wp_attachment_image_alt']);
    $image->set_image_alt_text_on_metadata([], 6);
    check(!empty($meta[6]['_wp_attachment_image_alt']), 'AI upload alt text works independently of filename fallback');
    if (extension_loaded('imagick')) {
        $gif = new Imagick();
        foreach (['red', 'blue'] as $color) { $frame = new Imagick(); $frame->newImage(30, 30, $color, 'gif'); $gif->addImage($frame); }
        $gif->writeImages($root . '/animation.gif', true);
        check(!$image->convert_to_webp($root . '/animation.gif', 'image/gif', $options)['success'], 'Animated GIF is preserved');
    } else {
        check(!$image->convert_to_webp($shared, 'image/gif', $options)['success'], 'GD preserves GIFs when frame detection is unavailable');
    }
    echo "All $count checks passed.\n";
} finally {
    foreach (glob($root . '/*') as $file) unlink($file);
    rmdir($root);
}
