<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Hiển thị một mục nội dung trong khoá học */

/** Lấy mục + kiểm tra quyền; trả về [item, course, canManage] hoặc dừng */
function item_load($id, $requireOpen = true)
{
    $item = DB::find('items', $id);
    if (!$item) { render_404(); exit; }
    $course = DB::find('courses', $item['course_id']);
    if (!$course) { render_404(); exit; }

    $canManage = Auth::canManageCourse($course);
    if (!$canManage) {
        Auth::requireLogin();
        if (!Auth::canViewCourse($course)) {
            render_denied('Bạn cần ghi danh khoá học để xem nội dung này.');
            exit;
        }
        if (!$item['visible']) { render_denied('Nội dung này đang được giáo viên ẩn.'); exit; }
        if ($requireOpen && $item['open_at'] && strtotime($item['open_at']) > time()) {
            render_denied('Nội dung sẽ mở vào ' . fmt_datetime($item['open_at']) . '.');
            exit;
        }
    }
    return [$item, $course, $canManage];
}

function item_complete_mark($item, $score = null, $status = 'completed')
{
    if (!Auth::check()) return;
    if (Auth::canManageCourse((int)$item['course_id'])) return;
    DB::q('INSERT INTO {P}completions (course_id, item_id, user_id, status, score, updated_at)
           VALUES (:c, :i, :u, :s, :sc, :t)
           ON DUPLICATE KEY UPDATE
             status = IF(VALUES(status) = "completed", "completed", status),
             score = COALESCE(VALUES(score), score), updated_at = VALUES(updated_at)', [
        'c' => (int)$item['course_id'], 'i' => (int)$item['id'], 'u' => Auth::id(),
        's' => $status, 'sc' => $score, 't' => now(),
    ]);
}

function item_view()
{
    list($item, $course, $canManage) = item_load(inp_int('id'));

    switch ($item['type']) {
        case 'assignment': redirect(url('assign/view', ['id' => $item['id']]));
        case 'quiz':       redirect(url('quiz/view', ['id' => $item['id']]));
        case 'scorm':      redirect(url('scorm/play', ['id' => $item['id']]));
        case 'forum':      redirect(url('forum/index', ['course' => $course['id'], 'item' => $item['id']]));
    }

    $files = DB::all('SELECT f.*, itf.id AS link_id FROM {P}item_files itf
                      JOIN {P}files f ON f.id = itf.file_id
                      WHERE itf.item_id = :i ORDER BY itf.position, itf.id', ['i' => $item['id']]);
    if ($item['file_id']) {
        $main = Storage::meta($item['file_id']);
        if ($main) array_unshift($files, $main);
    }

    item_complete_mark($item);

    // Mục kế tiếp / trước đó để học liên tục
    $siblings = DB::all('SELECT id, title, type, position FROM {P}items
                         WHERE course_id = :c ' . ($canManage ? '' : 'AND visible = 1 ') . 'ORDER BY position, id',
                        ['c' => $course['id']]);
    $prev = $next = null;
    foreach ($siblings as $i => $s) {
        if ((int)$s['id'] === (int)$item['id']) {
            $prev = $i > 0 ? $siblings[$i - 1] : null;
            $next = isset($siblings[$i + 1]) ? $siblings[$i + 1] : null;
            break;
        }
    }

    view('item_view', [
        'title' => $item['title'], 'item' => $item, 'course' => $course,
        'canManage' => $canManage, 'files' => $files, 'prev' => $prev, 'next' => $next,
    ]);
}

/** Đánh dấu đã hoàn thành thủ công (AJAX) */
function item_done()
{
    Auth::requireLogin();
    csrf_verify();
    $item = DB::find('items', inp_int('id'));
    if (!$item) json_out(['ok' => false, 'error' => 'Không tìm thấy nội dung.'], 404);
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canViewCourse($course)) json_out(['ok' => false, 'error' => 'Không có quyền.'], 403);
    item_complete_mark($item);
    json_out(['ok' => true]);
}
