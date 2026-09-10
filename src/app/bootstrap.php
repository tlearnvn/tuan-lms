<?php
/**
 * Khởi động ứng dụng: nạp cấu hình, kết nối MySQL, thiết lập phiên và múi giờ Việt Nam.
 */

define('LMS_VERSION', '1.1.1');
define('LMS_ROOT', dirname(__DIR__));
define('LMS_APP', __DIR__);

if (!defined('LMS_ENTRY')) define('LMS_ENTRY', true);

mb_internal_encoding('UTF-8');
if (function_exists('mb_http_output')) mb_http_output('UTF-8');

// Múi giờ Việt Nam cho toàn hệ thống
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once LMS_APP . '/helpers.php';
require_once LMS_APP . '/Database.php';
require_once LMS_APP . '/Settings.php';
require_once LMS_APP . '/Session.php';
require_once LMS_APP . '/Auth.php';
require_once LMS_APP . '/Storage.php';
require_once LMS_APP . '/Zip.php';

// ---------------------------------------------------------------- cấu hình
$configFile = LMS_ROOT . '/config.php';
if (!file_exists($configFile)) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        redirect(base_url() . 'install.php');
    }
    $GLOBALS['LMS_CONFIG'] = null;
    return;
}

$config = require $configFile;
$GLOBALS['LMS_CONFIG'] = $config;

// Hiển thị lỗi: bật khi debug, còn lại ghi vào log
$debug = !empty($config['debug']);
if ($debug) {
    error_reporting(E_ALL);
    @ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING);
    @ini_set('display_errors', '0');
}
@ini_set('log_errors', '1');

// ---------------------------------------------------------------- kết nối CSDL
try {
    DB::connect($config['db']);
} catch (Exception $e) {
    http_response_code(500);
    $msg = $debug ? $e->getMessage() : 'Vui lòng kiểm tra lại thông tin trong tệp config.php.';
    echo '<!doctype html><meta charset="utf-8"><title>Lỗi kết nối CSDL</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;padding:28px;'
        . 'border-radius:16px;background:#fff5f5;border:1px solid #ffd0d0">'
        . '<h2 style="margin:0 0 10px">😥 Không kết nối được cơ sở dữ liệu</h2>'
        . '<p style="color:#555;line-height:1.6">' . e($msg) . '</p></div>';
    exit;
}

// ---------------------------------------------------------------- cấu hình & phiên
Settings::load();

$tz = Settings::get('timezone', 'Asia/Ho_Chi_Minh');
if ($tz && in_array($tz, timezone_identifiers_list(), true)) date_default_timezone_set($tz);

// Thời gian chạy thoải mái cho việc tải tệp lớn / chấm bài bằng AI
@set_time_limit(0);
@ini_set('max_execution_time', '0');

SessionManager::start();

// ---------------------------------------------------------------- chế độ bảo trì
if (Settings::bool('maintenance') && basename($_SERVER['SCRIPT_NAME']) === 'index.php') {
    $r = isset($_GET['r']) ? $_GET['r'] : '';
    $allow = in_array($r, ['auth/login', 'auth/logout'], true);
    if (!$allow && !Auth::isAdmin()) {
        http_response_code(503);
        $siteName = e(Settings::get('site_name', 'LMS'));
        echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>' . $siteName . ' — Bảo trì</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '">'
            . '<div class="maintenance"><div class="maintenance-card"><div class="maintenance-emoji">🛠️</div>'
            . '<h1>' . $siteName . '</h1><p>' . e(Settings::get('maintenance_message')) . '</p>'
            . '<a class="btn btn-primary" href="' . e(url('auth/login')) . '">Đăng nhập quản trị</a></div></div>';
        exit;
    }
}
