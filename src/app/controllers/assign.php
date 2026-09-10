<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Bài tập: giao bài, nộp bài, chấm bài (thủ công và bằng AI) */
require_once LMS_APP . '/controllers/item.php';
require_once LMS_APP . '/Ai.php';

function assign_view()
{
    list($item, $course, $canManage) = item_load(inp_int('id'));
    if ($item['type'] !== 'assignment') redirect(url('item/view', ['id' => $item['id']]));

    $asg = DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $item['id']]);
    if (!$asg) {
        $asgId = DB::insert('assignments', ['item_id' => $item['id'], 'submission_type' => 'file,text']);
        $asg = DB::find('assignments', $asgId);
    }

    if ($canManage) { assign_list(); return; }

    $subs = DB::all('SELECT * FROM {P}submissions WHERE item_id = :i AND user_id = :u ORDER BY attempt DESC, id DESC',
                    ['i' => $item['id'], 'u' => Auth::id()]);
    $files = [];
    foreach ($subs as $s) {
        $files[$s['id']] = DB::all('SELECT f.* FROM {P}submission_files sf JOIN {P}files f ON f.id = sf.file_id
                                    WHERE sf.submission_id = :s', ['s' => $s['id']]);
    }
    $attachments = DB::all('SELECT f.* FROM {P}item_files itf JOIN {P}files f ON f.id = itf.file_id
                            WHERE itf.item_id = :i ORDER BY itf.position, itf.id', ['i' => $item['id']]);

    item_complete_mark($item, null, 'in_progress');

    view('assign_view', [
        'title' => $item['title'], 'item' => $item, 'course' => $course, 'asg' => $asg,
        'subs' => $subs, 'subFiles' => $files, 'attachments' => $attachments,
    ]);
}

function assign_submit()
{
    Auth::requireLogin();
    csrf_verify();
    list($item, $course, $canManage) = item_load(inp_int('id'));
    $asg = DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $item['id']]);
    if (!$asg) { flash_err('Bài tập chưa được cấu hình.'); back(); }

    $uid = Auth::id();
    $now = time();
    $isLate = 0;

    if ($asg['due_at'] && strtotime($asg['due_at']) < $now) {
        if (!$asg['allow_late']) { flash_err('Đã quá hạn nộp bài.'); back(); }
        $isLate = 1;
    }
    if ($asg['cutoff_at'] && strtotime($asg['cutoff_at']) < $now) {
        flash_err('Bài tập đã đóng, không thể nộp thêm.'); back();
    }

    $prev = DB::all('SELECT * FROM {P}submissions WHERE item_id = :i AND user_id = :u AND status <> "draft"',
                    ['i' => $item['id'], 'u' => $uid]);
    $maxAttempts = max(1, (int)$asg['max_attempts']);
    if (count($prev) >= $maxAttempts) {
        flash_err('Bạn đã nộp đủ ' . $maxAttempts . ' lần cho bài tập này.');
        back();
    }

    $types = array_filter(array_map('trim', explode(',', (string)$asg['submission_type'])));
    $content = in_array('text', $types, true) ? safe_html(inp('content')) : '';

    // Kiểm tra có nội dung hay không
    $hasFiles = false;
    if (!empty($_FILES['files']['name'][0])) $hasFiles = true;
    if (trim(strip_tags($content)) === '' && !$hasFiles) {
        flash_err('Vui lòng nhập bài làm hoặc chọn tệp để nộp.');
        back();
    }

    $subId = DB::insert('submissions', [
        'item_id'      => $item['id'],
        'user_id'      => $uid,
        'attempt'      => count($prev) + 1,
        'content'      => $content ?: null,
        'status'       => 'submitted',
        'submitted_at' => now(),
        'is_late'      => $isLate,
        'created_at'   => now(),
    ]);

    $errors = [];
    if ($hasFiles && in_array('file', $types, true)) {
        $allowed = array_filter(array_map('trim', explode(',', strtolower((string)$asg['allowed_ext']))));
        $maxFiles = max(1, (int)$asg['max_files']);
        $count = 0;
        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($name === '' || $count >= $maxFiles) continue;
            if ($allowed && !in_array(ext_of($name), $allowed, true)) {
                $errors[] = 'Tệp "' . $name . '" không thuộc định dạng cho phép (' . implode(', ', $allowed) . ').';
                continue;
            }
            $f = [
                'name' => $_FILES['files']['name'][$i], 'type' => $_FILES['files']['type'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i], 'error' => $_FILES['files']['error'][$i],
                'size' => $_FILES['files']['size'][$i],
            ];
            list($fid, $err) = Storage::saveUpload($f, 'private', $uid);
            if ($err) { $errors[] = $err; continue; }
            DB::insert('submission_files', ['submission_id' => $subId, 'file_id' => $fid]);
            $count++;
        }
    }

    item_complete_mark($item);
    Log::write('submit_assignment', 'item', $item['id']);

    // Báo cho giáo viên
    $teacherIds = DB::col('SELECT owner_id FROM {P}courses WHERE id = :c', ['c' => $course['id']]);
    $teacherIds = array_merge($teacherIds, DB::col('SELECT user_id FROM {P}course_teachers WHERE course_id = :c', ['c' => $course['id']]));
    Notify::pushMany($teacherIds, 'Bài nộp mới: ' . $item['title'],
        Auth::name() . ($isLate ? ' (nộp trễ)' : ''), url('assign/grade', ['id' => $item['id'], 'sub' => $subId]), '📥');

    foreach ($errors as $er) flash_warn($er);

    // Chấm tự động bằng AI nếu được bật
    if ($asg['ai_enabled'] && $asg['ai_auto'] && Ai::enabled()) {
        $res = Ai::gradeSubmission($subId);
        if ($res['ok'] && $asg['ai_apply'] && $res['score'] !== null) {
            DB::update('submissions', [
                'score'     => $res['score'],
                'feedback'  => $res['feedback'],
                'status'    => 'graded',
                'graded_at' => now(),
            ], 'id = :id', ['id' => $subId]);
            item_complete_mark($item, $res['score']);
            flash_ok('Nộp bài thành công! Trợ lý AI đã chấm sơ bộ bài của bạn 🤖');
            redirect(url('assign/view', ['id' => $item['id']]) . '#success');
        }
    }

    flash_ok('Nộp bài thành công! 🎉 Chúc mừng bạn đã hoàn thành.');
    redirect(url('assign/view', ['id' => $item['id']]) . '#success');
}

/** Danh sách bài nộp (giáo viên) */
function assign_list()
{
    list($item, $course, $canManage) = item_load(inp_int('id'), false);
    if (!$canManage) Auth::deny();

    $asg = DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $item['id']]);
    $filter = inp('filter', 'all');

    $students = DB::all('SELECT u.id, u.full_name, u.username, u.avatar_id, u.email
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed")
                         ORDER BY u.full_name', ['c' => $course['id']]);

    $subs = DB::all('SELECT s.*, (SELECT COUNT(*) FROM {P}submission_files sf WHERE sf.submission_id = s.id) AS nfiles
                     FROM {P}submissions s WHERE s.item_id = :i AND s.status <> "draft"
                     ORDER BY s.attempt DESC, s.id DESC', ['i' => $item['id']]);
    $latest = [];
    foreach ($subs as $s) {
        if (!isset($latest[$s['user_id']])) $latest[$s['user_id']] = $s;
    }

    $rows = [];
    foreach ($students as $st) {
        $s = isset($latest[$st['id']]) ? $latest[$st['id']] : null;
        if ($filter === 'submitted' && (!$s || $s['status'] !== 'submitted')) continue;
        if ($filter === 'graded' && (!$s || $s['score'] === null)) continue;
        if ($filter === 'missing' && $s) continue;
        $rows[] = ['student' => $st, 'sub' => $s];
    }

    $stats = [
        'total'     => count($students),
        'submitted' => count($latest),
        'graded'    => count(array_filter($latest, function ($s) { return $s['score'] !== null; })),
        'late'      => count(array_filter($latest, function ($s) { return (int)$s['is_late'] === 1; })),
    ];
    $scores = [];
    foreach ($latest as $s) if ($s['score'] !== null) $scores[] = (float)$s['score'];
    $stats['avg'] = $scores ? array_sum($scores) / count($scores) : null;

    view('assign_list', [
        'title' => 'Chấm bài: ' . $item['title'], 'item' => $item, 'course' => $course,
        'asg' => $asg, 'rows' => $rows, 'stats' => $stats, 'filter' => $filter,
    ]);
}

/** Chấm một bài nộp */
function assign_grade()
{
    list($item, $course, $canManage) = item_load(inp_int('id'), false);
    if (!$canManage) Auth::deny();

    $subId = inp_int('sub');
    $sub = DB::row('SELECT s.*, u.full_name, u.username, u.avatar_id, u.email
                    FROM {P}submissions s JOIN {P}users u ON u.id = s.user_id
                    WHERE s.id = :s AND s.item_id = :i', ['s' => $subId, 'i' => $item['id']]);
    if (!$sub) { flash_err('Không tìm thấy bài nộp.'); redirect(url('assign/list', ['id' => $item['id']])); }

    if (is_post()) {
        csrf_verify();
        $score = inp('score') === '' ? null : max(0, min((float)$item['max_points'], inp_float('score')));
        $feedback = safe_html(inp('feedback'));
        DB::update('submissions', [
            'score'     => $score,
            'feedback'  => $feedback ?: null,
            'status'    => $score !== null ? 'graded' : 'submitted',
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ], 'id = :id', ['id' => $sub['id']]);

        if ($score !== null) {
            item_complete_mark_for($item, (int)$sub['user_id'], $score);
            Notify::push($sub['user_id'], 'Bài tập đã được chấm: ' . $item['title'],
                'Điểm: ' . score_fmt($score) . '/' . score_fmt($item['max_points']),
                url('assign/view', ['id' => $item['id']]), '🏅');
        }
        Log::write('grade_submission', 'submission', $sub['id'], 'score=' . $score);
        flash_ok('Đã lưu điểm và nhận xét. ✅');

        $nextId = inp_int('next_sub');
        redirect($nextId ? url('assign/grade', ['id' => $item['id'], 'sub' => $nextId])
                         : url('assign/list', ['id' => $item['id']]));
    }

    $files = DB::all('SELECT f.* FROM {P}submission_files sf JOIN {P}files f ON f.id = sf.file_id
                      WHERE sf.submission_id = :s', ['s' => $sub['id']]);
    $asg = DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $item['id']]);

    // Bài kế tiếp cần chấm
    $next = DB::row('SELECT s.id FROM {P}submissions s WHERE s.item_id = :i AND s.status = "submitted" AND s.id <> :s
                     ORDER BY s.submitted_at ASC LIMIT 1', ['i' => $item['id'], 's' => $sub['id']]);

    view('assign_grade', [
        'title' => 'Chấm bài: ' . $sub['full_name'], 'item' => $item, 'course' => $course,
        'sub' => $sub, 'files' => $files, 'asg' => $asg, 'next' => $next,
    ]);
}

/** Đánh dấu hoàn thành cho học sinh khác (giáo viên chấm) */
function item_complete_mark_for($item, $userId, $score = null)
{
    DB::q('INSERT INTO {P}completions (course_id, item_id, user_id, status, score, updated_at)
           VALUES (:c, :i, :u, "completed", :sc, :t)
           ON DUPLICATE KEY UPDATE status = "completed", score = VALUES(score), updated_at = VALUES(updated_at)', [
        'c' => (int)$item['course_id'], 'i' => (int)$item['id'], 'u' => (int)$userId,
        'sc' => $score, 't' => now(),
    ]);
}

/** Gọi AI chấm bài (AJAX) */
function assign_ai()
{
    Auth::requireTeacher();
    csrf_verify();
    if (!Ai::enabled()) json_out(['ok' => false, 'error' => 'Trợ lý AI chưa được bật. Vào Quản trị → Trợ lý AI để cấu hình.']);

    $subId = inp_int('id');
    $sub = DB::find('submissions', $subId);
    if (!$sub) json_out(['ok' => false, 'error' => 'Không tìm thấy bài nộp.'], 404);
    $item = DB::find('items', $sub['item_id']);
    if (!Auth::canManageCourse((int)$item['course_id'])) json_out(['ok' => false, 'error' => 'Không có quyền.'], 403);

    $res = Ai::gradeSubmission($subId);
    if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']]);

    $html = '<div class="ai-panel"><div class="ai-head"><span class="ai-avatar">🤖</span>'
        . '<div>Nhận xét của trợ lý AI'
        . ($res['score'] !== null ? '<div class="small muted">Điểm đề xuất: <b>' . score_fmt($res['score'])
            . '/' . score_fmt($item['max_points']) . '</b></div>' : '')
        . '</div></div>' . $res['feedback'] . '</div>';

    json_out(['ok' => true, 'score' => $res['score'], 'html' => $html]);
}

/** Học sinh tự yêu cầu AI nhận xét (nếu giáo viên cho phép) */
function assign_ai_self()
{
    Auth::requireLogin();
    csrf_verify();
    $subId = inp_int('id');
    $sub = DB::find('submissions', $subId);
    if (!$sub || (int)$sub['user_id'] !== Auth::id()) json_out(['ok' => false, 'error' => 'Không tìm thấy bài nộp.'], 404);
    $asg = DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $sub['item_id']]);
    if (!$asg || !$asg['ai_enabled'] || !Ai::enabled()) {
        json_out(['ok' => false, 'error' => 'Chức năng AI nhận xét chưa được bật cho bài tập này.']);
    }
    $item = DB::find('items', $sub['item_id']);
    $res = Ai::gradeSubmission($subId);
    if (!$res['ok']) json_out(['ok' => false, 'error' => $res['error']]);

    if ($asg['ai_apply'] && $res['score'] !== null) {
        DB::update('submissions', [
            'score' => $res['score'], 'feedback' => $res['feedback'],
            'status' => 'graded', 'graded_at' => now(),
        ], 'id = :id', ['id' => $subId]);
    }
    $html = '<div class="ai-panel"><div class="ai-head"><span class="ai-avatar">🤖</span>'
        . '<div>Trợ lý AI nhận xét bài làm của em'
        . ($res['score'] !== null ? '<div class="small muted">Điểm tham khảo: <b>' . score_fmt($res['score'])
            . '/' . score_fmt($item['max_points']) . '</b></div>' : '')
        . '</div></div>' . $res['feedback'] . '</div>';
    json_out(['ok' => true, 'score' => $res['score'], 'html' => $html]);
}
