<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Hàm tiện ích dùng chung.
 * Tương thích PHP 7.4+ (hosting chia sẻ thường dùng 7.4 / 8.x).
 */

// ---------------------------------------------------------------- polyfill PHP < 8.0
if (!function_exists('str_contains')) {
    function str_contains($h, $n) { return $n === '' || strpos($h, $n) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n === '' || strncmp($h, $n, strlen($n)) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n) { return $n === '' || substr($h, -strlen($n)) === $n; }
}

// ---------------------------------------------------------------- chuỗi & HTML
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function str_limit($s, $len = 120, $end = '…')
{
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s)));
    if (mb_strlen($s, 'UTF-8') <= $len) return $s;
    return mb_substr($s, 0, $len, 'UTF-8') . $end;
}

/** Bỏ dấu tiếng Việt để tạo slug / tìm kiếm */
function vn_ascii($str)
{
    $map = [
        'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
        'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ', 'd' => 'đ',
        'A' => 'ÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬ', 'E' => 'ÉÈẺẼẸÊẾỀỂỄỆ', 'I' => 'ÍÌỈĨỊ',
        'O' => 'ÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢ', 'U' => 'ÚÙỦŨỤƯỨỪỬỮỰ', 'Y' => 'ÝỲỶỸỴ', 'D' => 'Đ',
    ];
    foreach ($map as $ascii => $chars) {
        $len = mb_strlen($chars, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $str = str_replace(mb_substr($chars, $i, 1, 'UTF-8'), $ascii, $str);
        }
    }
    return $str;
}

function slugify($str)
{
    $str = vn_ascii((string)$str);
    $str = preg_replace('/[^A-Za-z0-9]+/', '-', $str);
    return trim(strtolower($str), '-');
}

function random_code($len = 8)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $out;
}

// ---------------------------------------------------------------- URL
function base_url()
{
    static $base = null;
    if ($base !== null) return $base;
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $base = $dir . '/';
    return $base;
}

function url($route = '', $params = [])
{
    $u = base_url() . 'index.php';
    $qs = '';
    if ($route !== '' && $route !== null) {
        // Giữ nguyên dấu "/" trong tên tuyến để địa chỉ dễ đọc
        $qs = 'r=' . preg_replace('#[^a-zA-Z0-9_\-/]#', '', (string)$route);
    }
    if ($params) {
        $extra = http_build_query($params);
        if ($extra !== '') $qs .= ($qs === '' ? '' : '&') . $extra;
    }
    if ($qs !== '') $u .= '?' . $qs;
    return $u;
}

function asset($path) { return base_url() . 'assets/' . ltrim($path, '/'); }

function media_url($fileId, $download = false)
{
    if (!$fileId) return '';
    return base_url() . 'media.php?f=' . (int)$fileId . ($download ? '&dl=1' : '');
}

function current_url()
{
    return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : url('dashboard');
}

function redirect($url)
{
    if (!headers_sent()) header('Location: ' . $url);
    echo '<meta http-equiv="refresh" content="0;url=' . e($url) . '">';
    exit;
}

function back($fallback = null)
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
    redirect($ref ?: ($fallback ?: url('dashboard')));
}

// ---------------------------------------------------------------- input
function inp($key, $default = '')
{
    if (isset($_POST[$key])) return is_array($_POST[$key]) ? $_POST[$key] : trim((string)$_POST[$key]);
    if (isset($_GET[$key]))  return is_array($_GET[$key]) ? $_GET[$key] : trim((string)$_GET[$key]);
    return $default;
}
function inp_int($key, $default = 0) { $v = inp($key, null); return $v === null || $v === '' ? $default : (int)$v; }
function inp_float($key, $default = 0.0) { $v = inp($key, null); return $v === null || $v === '' ? $default : (float)str_replace(',', '.', $v); }
function inp_bool($key) { $v = inp($key, ''); return $v === '1' || $v === 'on' || $v === 'true' ? 1 : 0; }
function is_post() { return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST'; }
function is_ajax()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || inp('ajax') === '1';
}

function json_out($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------- flash & CSRF
function flash($type, $msg)
{
    if (!isset($_SESSION['_flash'])) $_SESSION['_flash'] = [];
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_ok($m)   { flash('success', $m); }
function flash_err($m)  { flash('danger', $m); }
function flash_info($m) { flash('info', $m); }
function flash_warn($m) { flash('warning', $m); }

function take_flashes()
{
    $f = isset($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
    unset($_SESSION['_flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify()
{
    $tok = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if (!hash_equals((string)csrf_token(), (string)$tok)) {
        if (is_ajax()) json_out(['ok' => false, 'error' => 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.'], 419);
        http_response_code(419);
        exit('<h2 style="font-family:sans-serif">Phiên làm việc đã hết hạn</h2><p style="font-family:sans-serif">Vui lòng quay lại và thử lại.</p>');
    }
}

// ---------------------------------------------------------------- thời gian (giờ Việt Nam)
function now() { return date('Y-m-d H:i:s'); }

function fmt_date($dt, $empty = '—')
{
    if (!$dt || $dt === '0000-00-00' || $dt === '0000-00-00 00:00:00') return $empty;
    $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
    if (!$ts) return $empty;
    return date(setting('date_format', 'd/m/Y'), $ts);
}

function fmt_datetime($dt, $empty = '—')
{
    if (!$dt || $dt === '0000-00-00 00:00:00') return $empty;
    $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
    if (!$ts) return $empty;
    return date(setting('datetime_format', 'H:i d/m/Y'), $ts);
}

function time_ago($dt)
{
    $ts = is_numeric($dt) ? (int)$dt : strtotime((string)$dt);
    if (!$ts) return '—';
    $d = time() - $ts;
    if ($d < 0) {
        $d = -$d;
        if ($d < 60) return 'trong giây lát';
        if ($d < 3600) return 'còn ' . floor($d / 60) . ' phút';
        if ($d < 86400) return 'còn ' . floor($d / 3600) . ' giờ';
        if ($d < 2592000) return 'còn ' . floor($d / 86400) . ' ngày';
        return fmt_datetime($dt);
    }
    if ($d < 60) return 'vừa xong';
    if ($d < 3600) return floor($d / 60) . ' phút trước';
    if ($d < 86400) return floor($d / 3600) . ' giờ trước';
    if ($d < 2592000) return floor($d / 86400) . ' ngày trước';
    return fmt_datetime($dt);
}

/** Khoảng thời gian còn lại tới hạn nộp */
function deadline_badge($dt)
{
    if (!$dt) return ['label' => 'Không giới hạn', 'class' => 'chip-gray'];
    $ts = strtotime($dt);
    $left = $ts - time();
    if ($left < 0)      return ['label' => 'Đã hết hạn ' . time_ago($dt), 'class' => 'chip-red'];
    if ($left < 86400)  return ['label' => 'Còn ' . max(1, floor($left / 3600)) . ' giờ', 'class' => 'chip-orange'];
    if ($left < 604800) return ['label' => 'Còn ' . floor($left / 86400) . ' ngày', 'class' => 'chip-yellow'];
    return ['label' => 'Hạn ' . fmt_datetime($dt), 'class' => 'chip-green'];
}

// ---------------------------------------------------------------- số & dung lượng
function human_size($bytes)
{
    $bytes = (float)$bytes;
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) { $bytes /= 1024; $i++; }
    return ($i === 0 ? (int)$bytes : number_format($bytes, $bytes >= 100 ? 0 : 1, ',', '.')) . ' ' . $u[$i];
}

function num($n, $dec = 0) { return number_format((float)$n, $dec, ',', '.'); }

function score_fmt($s, $empty = '—')
{
    if ($s === null || $s === '') return $empty;
    $s = (float)$s;
    return rtrim(rtrim(number_format($s, 2, ',', '.'), '0'), ',');
}

/** Xếp loại theo thang 10 */
function grade_rank($score10)
{
    if ($score10 === null || $score10 === '') return ['—', 'gray'];
    $s = (float)$score10;
    if ($s >= 9) return ['Xuất sắc', 'purple'];
    if ($s >= 8) return ['Giỏi', 'green'];
    if ($s >= 6.5) return ['Khá', 'blue'];
    if ($s >= 5) return ['Trung bình', 'orange'];
    return ['Chưa đạt', 'red'];
}

// ---------------------------------------------------------------- tệp tin
function file_icon($ext)
{
    $ext = strtolower((string)$ext);
    $map = [
        'pdf' => ['📕', '#e74c3c'],
        'doc' => ['📘', '#2b5797'], 'docx' => ['📘', '#2b5797'], 'odt' => ['📘', '#2b5797'], 'rtf' => ['📘', '#2b5797'],
        'xls' => ['📗', '#1e7145'], 'xlsx' => ['📗', '#1e7145'], 'ods' => ['📗', '#1e7145'], 'csv' => ['📗', '#1e7145'],
        'ppt' => ['📙', '#d24726'], 'pptx' => ['📙', '#d24726'], 'odp' => ['📙', '#d24726'],
        'zip' => ['🗜️', '#8e44ad'], 'rar' => ['🗜️', '#8e44ad'], '7z' => ['🗜️', '#8e44ad'],
        'jpg' => ['🖼️', '#16a085'], 'jpeg' => ['🖼️', '#16a085'], 'png' => ['🖼️', '#16a085'],
        'gif' => ['🖼️', '#16a085'], 'webp' => ['🖼️', '#16a085'], 'svg' => ['🖼️', '#16a085'], 'bmp' => ['🖼️', '#16a085'],
        'mp3' => ['🎵', '#e67e22'], 'wav' => ['🎵', '#e67e22'], 'ogg' => ['🎵', '#e67e22'], 'm4a' => ['🎵', '#e67e22'],
        'mp4' => ['🎬', '#c0392b'], 'webm' => ['🎬', '#c0392b'], 'mkv' => ['🎬', '#c0392b'],
        'avi' => ['🎬', '#c0392b'], 'mov' => ['🎬', '#c0392b'],
        'txt' => ['📄', '#7f8c8d'], 'md' => ['📄', '#7f8c8d'],
        'html' => ['🌐', '#2980b9'], 'htm' => ['🌐', '#2980b9'],
    ];
    return isset($map[$ext]) ? $map[$ext] : ['📎', '#636e72'];
}

function is_image_ext($ext)
{
    return in_array(strtolower((string)$ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
}
function is_video_ext($ext) { return in_array(strtolower((string)$ext), ['mp4', 'webm', 'ogv', 'mov', 'mkv'], true); }
function is_audio_ext($ext) { return in_array(strtolower((string)$ext), ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true); }

function ext_of($filename)
{
    $e = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $e);
}

function guess_mime($filename)
{
    $map = [
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8',
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8', 'xml' => 'application/xml; charset=utf-8',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv; charset=utf-8', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject', 'swf' => 'application/x-shockwave-flash',
    ];
    $e = ext_of($filename);
    return isset($map[$e]) ? $map[$e] : 'application/octet-stream';
}

/** Kích thước upload tối đa thực tế của server */
function server_upload_limit()
{
    $toBytes = function ($v) {
        $v = trim((string)$v);
        if ($v === '') return 0;
        $last = strtolower(substr($v, -1));
        $n = (float)$v;
        if ($last === 'g') $n *= 1024 * 1024 * 1024;
        elseif ($last === 'm') $n *= 1024 * 1024;
        elseif ($last === 'k') $n *= 1024;
        return (int)$n;
    };
    $a = $toBytes(ini_get('upload_max_filesize'));
    $b = $toBytes(ini_get('post_max_size'));
    $vals = array_filter([$a, $b]);
    return $vals ? min($vals) : 8 * 1024 * 1024;
}

function upload_error_message($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:  return 'Tệp vượt quá dung lượng cho phép của máy chủ (' . human_size(server_upload_limit()) . ').';
        case UPLOAD_ERR_PARTIAL:    return 'Tệp chỉ được tải lên một phần, vui lòng thử lại.';
        case UPLOAD_ERR_NO_FILE:    return 'Chưa chọn tệp nào.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Máy chủ thiếu thư mục tạm.';
        case UPLOAD_ERR_CANT_WRITE: return 'Máy chủ không ghi được tệp tạm.';
        case UPLOAD_ERR_EXTENSION:  return 'Một phần mở rộng PHP đã chặn tệp tải lên.';
        default: return 'Lỗi tải tệp không xác định (mã ' . (int)$code . ').';
    }
}

// ---------------------------------------------------------------- mảng
function arr_get($arr, $key, $default = null)
{
    return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
}

function json_decode_safe($s, $default = [])
{
    if (!$s) return $default;
    $d = json_decode($s, true);
    return is_array($d) ? $d : $default;
}

function pluck($rows, $key, $valKey = null)
{
    $out = [];
    foreach ($rows as $r) {
        if ($valKey === null) $out[] = $r[$key];
        else $out[$r[$key]] = $r[$valKey];
    }
    return $out;
}

function index_by($rows, $key)
{
    $out = [];
    foreach ($rows as $r) $out[$r[$key]] = $r;
    return $out;
}

function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k]);
            return substr(trim($ip[0]), 0, 45);
        }
    }
    return '';
}

// ---------------------------------------------------------------- văn bản an toàn
/**
 * Cho phép một tập thẻ HTML an toàn khi hiển thị nội dung do người dùng nhập.
 * Các đoạn công thức LaTeX ($...$, $$...$$, \( \), \[ \]) được bảo vệ nguyên vẹn
 * để dấu "<" trong công thức (ví dụ $a < b$) không bị hiểu nhầm là thẻ HTML.
 */
function safe_html($html)
{
    $html = (string)$html;
    $store = [];
    $html = math_protect($html, $store);

    $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote>'
        . '<a><img><table><thead><tbody><tr><th><td><caption><pre><code><hr><span><div><sub><sup>'
        . '<iframe><video><audio><source><figure><figcaption><mark><small><del><ins>';
    $html = strip_tags($html, $allowed);
    // Loại bỏ thuộc tính sự kiện và javascript:
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*(\2)/i', '$1="#"', $html);

    return math_restore($html, $store);
}

/** Thay các đoạn công thức bằng ô giữ chỗ trước khi lọc HTML */
function math_protect($text, &$store)
{
    $patterns = [
        '/\$\$(.+?)\$\$/su',                 // $$ ... $$
        '/\\\\\[(.+?)\\\\\]/su',             // \[ ... \]
        '/\\\\\((.+?)\\\\\)/su',             // \( ... \)
        '/(?<![\\\\\w$])\$(?!\s)([^$\r\n]{1,400}?)(?<!\s)\$(?![\w$])/u', // $ ... $
        '/\\\\begin\{([a-z*]+)\}(.+?)\\\\end\{\1\}/su',                  // môi trường LaTeX
    ];
    foreach ($patterns as $p) {
        $text = preg_replace_callback($p, function ($m) use (&$store) {
            $key = "\x02MATH" . count($store) . "\x03";
            $store[$key] = $m[0];
            return $key;
        }, $text);
        if ($text === null) return (string)$text;
    }
    return $text;
}

function math_restore($text, $store)
{
    if (!$store) return $text;
    return strtr($text, $store);
}

/**
 * Hiển thị nội dung do người dùng nhập: nhận cả HTML lẫn văn bản thuần,
 * giữ nguyên công thức LaTeX (kể cả ký tự & trong \begin{cases}...).
 */
function rich_text($s)
{
    $s = (string)$s;
    if ($s === '') return '';
    if (preg_match('/<(p|br|div|ul|ol|li|b|i|u|s|strong|em|table|img|figure|blockquote|pre|code|h[1-6])\b/i', $s)) {
        return safe_html($s);
    }
    $store = [];
    $t = math_protect($s, $store);
    $t = nl2br(e($t));
    return math_restore($t, $store);
}

/** Văn bản thuần -> HTML (giữ xuống dòng, tự động tạo liên kết) */
function nl2html($text)
{
    $t = e($text);
    $t = preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" target="_blank" rel="noopener">$1</a>', $t);
    return nl2br($t);
}

function initials($name)
{
    $parts = preg_split('/\s+/u', trim((string)$name));
    if (!$parts || $parts[0] === '') return '?';
    $last = $parts[count($parts) - 1];
    $first = $parts[0];
    if (count($parts) === 1) return mb_strtoupper(mb_substr($first, 0, 1, 'UTF-8'), 'UTF-8');
    return mb_strtoupper(mb_substr($last, 0, 1, 'UTF-8') . mb_substr($first, 0, 1, 'UTF-8'), 'UTF-8');
}

/** Màu ổn định theo chuỗi (avatar, thẻ khoá học) */
function color_of($str)
{
    $palette = ['#6C5CE7', '#00B894', '#0984E3', '#E17055', '#D63031', '#E84393',
                '#00CEC9', '#FDCB6E', '#A29BFE', '#55EFC4', '#FF7675', '#74B9FF'];
    $h = 0;
    $s = (string)$str;
    for ($i = 0; $i < strlen($s); $i++) $h = ($h * 31 + ord($s[$i])) % 100000;
    return $palette[$h % count($palette)];
}

function avatar_tag($user, $size = 40)
{
    $name = is_array($user) ? (isset($user['full_name']) ? $user['full_name'] : '') : (string)$user;
    $avatarId = is_array($user) ? (isset($user['avatar_id']) ? $user['avatar_id'] : 0) : 0;
    $st = 'width:' . (int)$size . 'px;height:' . (int)$size . 'px;font-size:' . max(10, (int)($size * 0.38)) . 'px';
    if ($avatarId) {
        return '<img class="avatar" style="' . $st . '" src="' . e(media_url($avatarId)) . '" alt="' . e($name) . '">';
    }
    return '<span class="avatar avatar-text" style="' . $st . ';background:' . color_of($name) . '">' . e(initials($name)) . '</span>';
}

function role_label($role)
{
    $m = ['admin' => 'Quản trị viên', 'teacher' => 'Giáo viên', 'student' => 'Học sinh'];
    return isset($m[$role]) ? $m[$role] : $role;
}

function status_label($s)
{
    $m = ['active' => 'Hoạt động', 'pending' => 'Chờ duyệt', 'locked' => 'Đã khoá',
          'draft' => 'Bản nháp', 'published' => 'Đang mở', 'archived' => 'Lưu trữ',
          'submitted' => 'Đã nộp', 'graded' => 'Đã chấm', 'returned' => 'Đã trả bài',
          'completed' => 'Hoàn thành', 'in_progress' => 'Đang học', 'removed' => 'Đã gỡ'];
    return isset($m[$s]) ? $m[$s] : $s;
}

function item_type_meta($type)
{
    $m = [
        'page'       => ['📖', 'Trang nội dung', '#6C5CE7'],
        'file'       => ['📎', 'Tệp học liệu', '#0984E3'],
        'video'      => ['🎬', 'Video bài giảng', '#E17055'],
        'link'       => ['🔗', 'Liên kết ngoài', '#00B894'],
        'scorm'      => ['🧩', 'Gói SCORM', '#E84393'],
        'assignment' => ['📝', 'Bài tập', '#D63031'],
        'quiz'       => ['❓', 'Bài trắc nghiệm', '#F39C12'],
        'forum'      => ['💬', 'Diễn đàn thảo luận', '#00CEC9'],
    ];
    return isset($m[$type]) ? $m[$type] : ['📄', 'Nội dung', '#636e72'];
}

// ---------------------------------------------------------------- phân trang
function paginate_html($total, $perPage, $page, $baseParams = [])
{
    $pages = max(1, (int)ceil($total / max(1, $perPage)));
    if ($pages <= 1) return '';
    $route = isset($baseParams['r']) ? $baseParams['r'] : '';
    unset($baseParams['r']);
    $mk = function ($p) use ($route, $baseParams) {
        return url($route, array_merge($baseParams, ['page' => $p]));
    };
    $h = '<nav class="pager">';
    $h .= '<a class="pager-btn' . ($page <= 1 ? ' disabled' : '') . '" href="' . e($mk(max(1, $page - 1))) . '">‹ Trước</a>';
    $start = max(1, $page - 2); $end = min($pages, $page + 2);
    if ($start > 1) $h .= '<a class="pager-btn" href="' . e($mk(1)) . '">1</a>' . ($start > 2 ? '<span class="pager-dots">…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $h .= '<a class="pager-btn' . ($i == $page ? ' active' : '') . '" href="' . e($mk($i)) . '">' . $i . '</a>';
    }
    if ($end < $pages) $h .= ($end < $pages - 1 ? '<span class="pager-dots">…</span>' : '') . '<a class="pager-btn" href="' . e($mk($pages)) . '">' . $pages . '</a>';
    $h .= '<a class="pager-btn' . ($page >= $pages ? ' disabled' : '') . '" href="' . e($mk(min($pages, $page + 1))) . '">Sau ›</a>';
    $h .= '</nav>';
    return $h;
}

// ---------------------------------------------------------------- biểu đồ SVG (không phụ thuộc thư viện ngoài)
function svg_bar_chart($data, $opts = [])
{
    $w = arr_get($opts, 'width', 640);
    $h = arr_get($opts, 'height', 240);
    $color = arr_get($opts, 'color', 'var(--primary)');
    $pad = 34; $padBottom = 42; $padTop = 18;
    $n = max(1, count($data));
    $max = 0;
    foreach ($data as $d) $max = max($max, (float)$d['value']);
    $max = $max <= 0 ? 1 : $max;
    $innerW = $w - $pad * 2;
    $innerH = $h - $padBottom - $padTop;
    $bw = $innerW / $n;
    $s = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img">';
    for ($g = 0; $g <= 4; $g++) {
        $y = $padTop + $innerH * $g / 4;
        $s .= '<line x1="' . $pad . '" y1="' . round($y, 1) . '" x2="' . ($w - $pad) . '" y2="' . round($y, 1) . '" class="chart-grid"/>';
        $s .= '<text x="' . ($pad - 6) . '" y="' . round($y + 4, 1) . '" class="chart-axis" text-anchor="end">' . num(round($max * (4 - $g) / 4)) . '</text>';
    }
    $i = 0;
    foreach ($data as $d) {
        $v = (float)$d['value'];
        $bh = $innerH * ($v / $max);
        $x = $pad + $bw * $i + $bw * 0.18;
        $bwx = $bw * 0.64;
        $y = $padTop + $innerH - $bh;
        $c = isset($d['color']) ? $d['color'] : $color;
        $s .= '<rect class="chart-bar" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bwx, 1) . '" height="' . round(max(0, $bh), 1) . '" rx="5" fill="' . e($c) . '"><title>' . e($d['label'] . ': ' . $v) . '</title></rect>';
        if ($v > 0) $s .= '<text x="' . round($x + $bwx / 2, 1) . '" y="' . round($y - 5, 1) . '" class="chart-value" text-anchor="middle">' . e(num($v, is_float($v) && floor($v) != $v ? 1 : 0)) . '</text>';
        $s .= '<text x="' . round($x + $bwx / 2, 1) . '" y="' . ($h - 14) . '" class="chart-axis" text-anchor="middle">' . e(str_limit($d['label'], 14, '')) . '</text>';
        $i++;
    }
    $s .= '</svg>';
    return $s;
}

function svg_donut($segments, $opts = [])
{
    $size = arr_get($opts, 'size', 180);
    $stroke = arr_get($opts, 'stroke', 26);
    $r = ($size - $stroke) / 2;
    $cx = $cy = $size / 2;
    $circ = 2 * M_PI * $r;
    $total = 0;
    foreach ($segments as $sg) $total += (float)$sg['value'];
    $s = '<svg class="donut" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . round($r, 2) . '" fill="none" stroke="rgba(0,0,0,.07)" stroke-width="' . $stroke . '"/>';
    if ($total > 0) {
        $offset = 0;
        foreach ($segments as $sg) {
            $len = $circ * ((float)$sg['value'] / $total);
            if ($len <= 0) continue;
            $s .= '<circle class="donut-seg" cx="' . $cx . '" cy="' . $cy . '" r="' . round($r, 2) . '" fill="none"'
                . ' stroke="' . e($sg['color']) . '" stroke-width="' . $stroke . '"'
                . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '"'
                . ' stroke-dashoffset="' . round(-$offset, 2) . '"'
                . ' transform="rotate(-90 ' . $cx . ' ' . $cy . ')" stroke-linecap="butt">'
                . '<title>' . e($sg['label'] . ': ' . $sg['value']) . '</title></circle>';
            $offset += $len;
        }
    }
    $center = arr_get($opts, 'center', num($total));
    $sub = arr_get($opts, 'sub', '');
    $s .= '<text x="' . $cx . '" y="' . ($cy + ($sub ? 0 : 6)) . '" text-anchor="middle" class="donut-center">' . e($center) . '</text>';
    if ($sub) $s .= '<text x="' . $cx . '" y="' . ($cy + 20) . '" text-anchor="middle" class="donut-sub">' . e($sub) . '</text>';
    $s .= '</svg>';
    return $s;
}

function svg_sparkline($values, $opts = [])
{
    $w = arr_get($opts, 'width', 260);
    $h = arr_get($opts, 'height', 60);
    $color = arr_get($opts, 'color', '#6C5CE7');
    $n = count($values);
    if ($n < 2) return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '"></svg>';
    $max = max($values); $min = min($values);
    $range = ($max - $min) ?: 1;
    $pts = []; $area = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $w * $i / ($n - 1);
        $y = $h - 6 - ($h - 14) * (($values[$i] - $min) / $range);
        $pts[] = round($x, 1) . ',' . round($y, 1);
        $area[] = round($x, 1) . ',' . round($y, 1);
    }
    $areaPath = 'M0,' . $h . ' L' . implode(' L', $area) . ' L' . $w . ',' . $h . ' Z';
    $s = '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">';
    $s .= '<path d="' . $areaPath . '" fill="' . e($color) . '" opacity=".12"/>';
    $s .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . e($color) . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
    $s .= '</svg>';
    return $s;
}

function progress_bar($percent, $label = null, $color = null)
{
    $p = max(0, min(100, (float)$percent));
    $c = $color ?: ($p >= 80 ? '#00B894' : ($p >= 50 ? '#0984E3' : ($p >= 25 ? '#FDCB6E' : '#FF7675')));
    $h = '<div class="progress" title="' . e($label !== null ? $label : round($p) . '%') . '">';
    $h .= '<div class="progress-fill" style="width:' . round($p, 1) . '%;background:' . e($c) . '"></div>';
    $h .= '</div>';
    return $h;
}
