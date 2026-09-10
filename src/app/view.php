<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Kết xuất giao diện: nạp tệp view rồi bọc trong bố cục tương ứng.
 */

/**
 * @param string $name   tên tệp trong app/views (không kèm .php)
 * @param array  $data   biến truyền vào view
 * @param string $layout app | public | blank
 */
function view($name, $data = [], $layout = 'app')
{
    $file = LMS_APP . '/views/' . $name . '.php';
    if (!file_exists($file)) {
        render_error('Không tìm thấy giao diện: ' . $name);
        return;
    }
    extract($data, EXTR_SKIP);
    $pageTitle = isset($data['title']) ? $data['title'] : '';
    ob_start();
    include $file;
    $content = ob_get_clean();

    $layoutFile = LMS_APP . '/views/layout_' . $layout . '.php';
    if (!file_exists($layoutFile)) $layoutFile = LMS_APP . '/views/layout_app.php';
    include $layoutFile;
}

function partial($name, $data = [])
{
    $file = LMS_APP . '/views/partials/' . $name . '.php';
    if (!file_exists($file)) return;
    extract($data, EXTR_SKIP);
    include $file;
}

function render_404()
{
    http_response_code(404);
    view('error', [
        'title' => 'Không tìm thấy trang',
        'code'  => '404',
        'emoji' => '🧭',
        'message' => 'Trang bạn tìm không tồn tại hoặc đã được chuyển đi nơi khác.',
    ], Auth::check() ? 'app' : 'public');
}

function render_error($detail = null)
{
    view('error', [
        'title' => 'Đã có lỗi xảy ra',
        'code'  => '500',
        'emoji' => '🐞',
        'message' => 'Hệ thống gặp sự cố khi xử lý yêu cầu của bạn. Vui lòng thử lại.',
        'detail' => $detail,
    ], Auth::check() ? 'app' : 'public');
}

function render_denied($message = 'Bạn không có quyền xem nội dung này.')
{
    http_response_code(403);
    view('error', [
        'title' => 'Không có quyền truy cập',
        'code'  => '403',
        'emoji' => '🔒',
        'message' => $message,
    ], Auth::check() ? 'app' : 'public');
    exit;
}

/** Tiêu đề trang đầy đủ */
function full_title($t = '')
{
    $site = Settings::get('site_name', 'LMS');
    return $t ? ($t . ' · ' . $site) : $site;
}

/** Đường dẫn logo (nếu quản trị viên đã tải lên) */
function logo_url()
{
    $id = (int)Settings::get('logo_id', 0);
    return $id ? media_url($id) : '';
}

function favicon_url()
{
    $id = (int)Settings::get('favicon_id', 0);
    return $id ? media_url($id) : asset('img/favicon.svg');
}

/** Thanh điều hướng bên trái theo vai trò */
function nav_items()
{
    $items = [];
    $items[] = ['label' => 'Bảng điều khiển', 'icon' => '🏠', 'route' => 'dashboard'];
    $items[] = ['label' => 'Khoá học của tôi', 'icon' => '📚', 'route' => 'course/mine'];
    $items[] = ['label' => 'Khám phá khoá học', 'icon' => '🔎', 'route' => 'catalog'];

    if (Auth::isStudent()) {
        $items[] = ['label' => 'Bài tập & hạn nộp', 'icon' => '📝', 'route' => 'user/deadlines'];
        $items[] = ['label' => 'Kết quả học tập', 'icon' => '🏅', 'route' => 'grade/mine'];
    }
    if (Auth::isTeacher()) {
        $items[] = ['divider' => 'Giảng dạy'];
        $items[] = ['label' => 'Khoá học giảng dạy', 'icon' => '🎓', 'route' => 'teach/courses'];
        $items[] = ['label' => 'Cần chấm bài', 'icon' => '✅', 'route' => 'teach/grading'];
        $items[] = ['label' => 'Thống kê & báo cáo', 'icon' => '📊', 'route' => 'teach/reports'];
    }
    if (Auth::isAdmin()) {
        $items[] = ['divider' => 'Quản trị'];
        $items[] = ['label' => 'Tổng quan hệ thống', 'icon' => '🛰️', 'route' => 'admin/index'];
        $items[] = ['label' => 'Người dùng', 'icon' => '👥', 'route' => 'admin/users'];
        $items[] = ['label' => 'Khoá học', 'icon' => '🗂️', 'route' => 'admin/courses'];
        $items[] = ['label' => 'Cấu hình chung', 'icon' => '⚙️', 'route' => 'admin/settings'];
        $items[] = ['label' => 'Trợ lý AI chấm bài', 'icon' => '🤖', 'route' => 'admin/ai'];
        $items[] = ['label' => 'Kho dữ liệu', 'icon' => '💾', 'route' => 'admin/storage'];
        $items[] = ['label' => 'Nhật ký hoạt động', 'icon' => '📜', 'route' => 'admin/logs'];
    }
    return $items;
}

function is_active_route($route)
{
    $cur = trim((string)inp('r', 'home'), '/');
    if ($route === $cur) return true;
    if ($route === 'admin/index' && $cur === 'admin') return true;
    $base = explode('/', $route);
    $curBase = explode('/', $cur);
    return count($base) > 1 && count($curBase) > 1 && $base[0] === $curBase[0] && $base[1] === $curBase[1];
}

/** Thẻ nhỏ hiển thị trạng thái */
function chip($text, $class = 'chip-gray', $icon = '')
{
    return '<span class="chip ' . e($class) . '">' . ($icon ? $icon . ' ' : '') . e($text) . '</span>';
}

/** Đường dẫn phân cấp */
function breadcrumbs($items)
{
    $h = '<nav class="breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        if ($i > 0) $h .= '<span class="breadcrumb-sep">›</span>';
        if ($i === $last || empty($it['url'])) {
            $h .= '<span class="breadcrumb-current">' . e($it['label']) . '</span>';
        } else {
            $h .= '<a href="' . e($it['url']) . '">' . e($it['label']) . '</a>';
        }
    }
    return $h . '</nav>';
}

function empty_state($emoji, $title, $desc = '', $actionHtml = '')
{
    return '<div class="empty-state"><div class="empty-emoji">' . $emoji . '</div>'
        . '<h3>' . e($title) . '</h3>'
        . ($desc ? '<p>' . e($desc) . '</p>' : '')
        . ($actionHtml ? '<div class="empty-action">' . $actionHtml . '</div>' : '')
        . '</div>';
}
