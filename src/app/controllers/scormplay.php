<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Trình phát học liệu SCORM và ghi nhận tiến độ */
require_once LMS_APP . '/controllers/item.php';
require_once LMS_APP . '/Scorm.php';

function scorm_play()
{
    list($item, $course, $canManage) = item_load(inp_int('id'));
    $pkg = Scorm::package($item['id']);
    if (!$pkg) {
        view('scorm_missing', ['title' => $item['title'], 'item' => $item, 'course' => $course, 'canManage' => $canManage]);
        return;
    }

    $manifest = Scorm::parseManifest($pkg['manifest']);
    $tracks = Scorm::getTracks($pkg['id'], Auth::id());
    $summary = Scorm::summary($pkg['id'], Auth::id());

    $sco = inp('sco', '');
    $launch = $pkg['launch_url'];
    if ($sco !== '') {
        foreach ($manifest['items'] as $mi) {
            if ($mi['id'] === $sco && $mi['href']) { $launch = $mi['href']; break; }
        }
    }

    item_complete_mark($item, null, 'in_progress');

    view('scorm_play', [
        'title' => $item['title'], 'item' => $item, 'course' => $course, 'pkg' => $pkg,
        'manifest' => $manifest, 'tracks' => $tracks, 'summary' => $summary,
        'launch' => $launch, 'sco' => $sco ?: 'default', 'canManage' => $canManage,
    ], 'blank');
}

/** Nhận dữ liệu CMI từ nội dung SCORM */
function scorm_track()
{
    Auth::requireLogin();
    csrf_verify();
    $item = DB::find('items', inp_int('item'));
    if (!$item) json_out(['ok' => false, 'error' => 'Không tìm thấy nội dung.'], 404);
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canViewCourse($course)) json_out(['ok' => false, 'error' => 'Không có quyền.'], 403);

    $pkg = Scorm::package($item['id']);
    if (!$pkg) json_out(['ok' => false, 'error' => 'Gói SCORM không tồn tại.'], 404);

    $data = json_decode_safe(inp('data'), []);
    if (!$data) json_out(['ok' => true, 'saved' => 0]);

    Scorm::saveTracks($pkg['id'], $item['id'], Auth::id(), inp('sco', 'default'), $data);
    json_out(['ok' => true, 'saved' => count($data), 'time' => date('H:i:s')]);
}

/** Báo cáo kết quả SCORM của cả lớp (giáo viên) */
function scorm_report()
{
    list($item, $course, $canManage) = item_load(inp_int('id'), false);
    if (!$canManage) { Auth::deny(); return; }
    $pkg = Scorm::package($item['id']);
    if (!$pkg) { flash_err('Mục này chưa có gói SCORM.'); redirect(url('course/view', ['id' => $course['id']])); }

    $students = DB::all('SELECT u.id, u.full_name, u.username, u.avatar_id
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed") ORDER BY u.full_name',
                        ['c' => $course['id']]);
    $rows = [];
    foreach ($students as $s) {
        $rows[] = ['student' => $s, 'sum' => Scorm::summary($pkg['id'], $s['id'])];
    }

    view('scorm_report', [
        'title' => 'Kết quả SCORM: ' . $item['title'], 'item' => $item, 'course' => $course,
        'pkg' => $pkg, 'rows' => $rows,
    ]);
}
