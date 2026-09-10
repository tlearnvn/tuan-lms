<?php
/**
 * Máy chủ nội dung SCORM.
 * Đường dẫn dạng: scorm.php/<mã gói>/<đường dẫn trong gói>
 * (dùng PATH_INFO để các liên kết tương đối bên trong gói hoạt động chính xác)
 * Dự phòng: scorm.php?pkg=<mã gói>&path=<đường dẫn>
 */
define('LMS_ENTRY', true);
require_once __DIR__ . '/app/bootstrap.php';
require_once LMS_APP . '/Scorm.php';

Auth::requireLogin();

$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
if ($pathInfo === '' && isset($_SERVER['ORIG_PATH_INFO'])) $pathInfo = $_SERVER['ORIG_PATH_INFO'];

// Dự phòng cho máy chủ không thiết lập PATH_INFO: đọc trực tiếp từ địa chỉ yêu cầu
if ($pathInfo === '' && !empty($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    $q = strpos($uri, '?');
    if ($q !== false) $uri = substr($uri, 0, $q);
    $pos = stripos($uri, 'scorm.php/');
    if ($pos !== false) $pathInfo = substr($uri, $pos + strlen('scorm.php'));
}

$pkgId = 0;
$path = '';

if ($pathInfo !== '') {
    $p = ltrim($pathInfo, '/');
    $slash = strpos($p, '/');
    if ($slash === false) { $pkgId = (int)$p; }
    else { $pkgId = (int)substr($p, 0, $slash); $path = substr($p, $slash + 1); }
} else {
    $pkgId = isset($_GET['pkg']) ? (int)$_GET['pkg'] : 0;
    $path = isset($_GET['path']) ? (string)$_GET['path'] : '';
}

$path = rawurldecode($path);
$pkg = $pkgId ? DB::find('scorm_packages', $pkgId) : null;
if (!$pkg) { http_response_code(404); exit('Không tìm thấy gói SCORM.'); }

$item = DB::find('items', $pkg['item_id']);
if (!$item) { http_response_code(404); exit('Nội dung không tồn tại.'); }

$course = DB::find('courses', $item['course_id']);
if (!Auth::canViewCourse($course)) { http_response_code(403); exit('Bạn chưa ghi danh khoá học này.'); }

if ($path === '') $path = (string)$pkg['launch_url'];
// Bỏ phần tham số truy vấn nếu tệp khởi chạy có kèm ?param
$qpos = strpos($path, '?');
if ($qpos !== false) $path = substr($path, 0, $qpos);

$file = Scorm::fileByPath($pkg['id'], $path);
if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Không tìm thấy tệp trong gói SCORM: ' . htmlspecialchars($path));
}

Storage::output($file, false, 86400);
