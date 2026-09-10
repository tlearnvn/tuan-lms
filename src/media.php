<?php
/**
 * Phục vụ tệp tin lưu trong MySQL (ảnh, tài liệu, video…).
 * Dùng: media.php?f=<id> (xem) hoặc media.php?f=<id>&dl=1 (tải về)
 */
define('LMS_ENTRY', true);
require_once __DIR__ . '/app/bootstrap.php';

$id = isset($_GET['f']) ? (int)$_GET['f'] : 0;
$file = $id ? Storage::meta($id) : null;

if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Không tìm thấy tệp.');
}

if (!Storage::canAccess($file)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Bạn không có quyền xem tệp này.');
}

// Tệp HTML/SVG do người dùng tải lên luôn tải về để tránh chạy mã trong cùng tên miền
$download = !empty($_GET['dl']);
$risky = in_array(strtolower((string)$file['ext']), ['html', 'htm', 'svg', 'xml', 'js'], true);
if ($risky && $file['visibility'] !== 'public') $download = true;

Storage::output($file, $download);
