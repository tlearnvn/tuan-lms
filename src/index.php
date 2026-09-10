<?php
/**
 * Bộ điều phối chính của hệ thống LMS.
 * Mọi đường dẫn đều đi qua index.php?r=... nên chạy được trên mọi hosting (không cần mod_rewrite).
 */
define('LMS_ENTRY', true);
require_once __DIR__ . '/app/bootstrap.php';
require_once LMS_APP . '/view.php';

$route = trim((string)inp('r', 'home'), '/');
if ($route === '') $route = 'home';
if (!preg_match('#^[a-z0-9_\-/]+$#i', $route)) $route = 'home';

// Bản đồ đường dẫn -> tệp điều khiển
$map = [
    'home'      => 'pages',      'catalog'   => 'pages',      'about' => 'pages',
    'auth'      => 'auth',
    'dashboard' => 'dashboard',
    'course'    => 'course',
    'item'      => 'item',
    'assign'    => 'assign',
    'quiz'      => 'quiz',
    'scorm'     => 'scormplay',
    'forum'     => 'forum',
    'grade'     => 'grade',
    'user'      => 'user',
    'teach'     => 'teach',
    'admin'     => 'admin',
    'export'    => 'export',
    'upload'    => 'upload',
    'preview'   => 'preview',
    'ping'      => 'system',
];

$parts = explode('/', $route);
$group = $parts[0];
$file = isset($map[$group]) ? $map[$group] : null;

if ($file === null) {
    render_404();
    exit;
}

require_once LMS_APP . '/controllers/' . $file . '.php';

$fn = str_replace('-', '_', implode('_', $parts));
if (count($parts) === 1) $fn = $group . '_index';

if (!function_exists($fn)) {
    render_404();
    exit;
}

try {
    $fn();
} catch (Throwable $e) {
    $debug = !empty($GLOBALS['LMS_CONFIG']['debug']);
    error_log('[LMS] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (is_ajax()) json_out(['ok' => false, 'error' => $debug ? $e->getMessage() : 'Đã có lỗi xảy ra.'], 500);
    http_response_code(500);
    render_error($debug ? $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() : null);
}
