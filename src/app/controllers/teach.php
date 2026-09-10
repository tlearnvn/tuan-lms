<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Khu vực giảng dạy: quản lý khoá học, nội dung, học viên, chấm bài */
require_once LMS_APP . '/Scorm.php';
require_once LMS_APP . '/Ai.php';

function teach_guard_course($courseId)
{
    Auth::requireTeacher();
    $course = DB::find('courses', $courseId);
    if (!$course) { render_404(); exit; }
    if (!Auth::canManageCourse($course)) { Auth::deny(); exit; }
    return $course;
}

// ------------------------------------------------------------------ khoá học
function teach_courses()
{
    Auth::requireTeacher();
    $uid = Auth::id();
    $where = Auth::isAdmin() && inp('all') === '1'
        ? '1'
        : '(c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))';
    $params = strpos($where, ':u') !== false ? ['u' => $uid, 'u2' => $uid] : [];

    $courses = DB::all("SELECT c.*, cat.name AS category_name, cat.color AS category_color, cat.icon AS category_icon,
                        u.full_name AS teacher_name,
                        (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
                        (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = 'pending') AS pending_count,
                        (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id) AS item_count,
                        (SELECT COUNT(*) FROM {P}submissions s JOIN {P}items i2 ON i2.id = s.item_id
                          WHERE i2.course_id = c.id AND s.status = 'submitted') AS ungraded
                        FROM {P}courses c
                        LEFT JOIN {P}categories cat ON cat.id = c.category_id
                        LEFT JOIN {P}users u ON u.id = c.owner_id
                        WHERE $where ORDER BY c.status, c.id DESC", $params);

    view('teach_courses', ['title' => 'Khoá học giảng dạy', 'courses' => $courses]);
}

function teach_course_edit()
{
    Auth::requireTeacher();
    $id = inp_int('id');
    $course = $id ? DB::find('courses', $id) : null;
    if ($id && !$course) { render_404(); return; }
    if ($course && !Auth::canManageCourse($course)) { Auth::deny(); return; }

    $errors = [];
    if (is_post()) {
        csrf_verify();
        $data = [
            'code'        => strtoupper(trim(inp('code'))),
            'title'       => inp('title'),
            'summary'     => inp('summary'),
            'description' => safe_html(inp('description')),
            'category_id' => inp_int('category_id') ?: null,
            'status'      => in_array(inp('status'), ['draft', 'published', 'archived'], true) ? inp('status') : 'draft',
            'visibility'  => inp('visibility') === 'private' ? 'private' : 'public',
            'enroll_mode' => in_array(inp('enroll_mode'), ['open', 'key', 'manual', 'approval'], true) ? inp('enroll_mode') : 'open',
            'enroll_key'  => inp('enroll_key') ?: null,
            'start_date'  => inp('start_date') ?: null,
            'end_date'    => inp('end_date') ?: null,
            'max_students'=> inp_int('max_students'),
            'color'       => preg_match('/^#[0-9A-Fa-f]{6}$/', inp('color')) ? inp('color') : '#6C5CE7',
            'updated_at'  => now(),
        ];

        if ($data['title'] === '') $errors[] = 'Vui lòng nhập tên khoá học.';
        if ($data['code'] === '') $data['code'] = strtoupper(substr(slugify($data['title']), 0, 10)) . '-' . random_code(4);
        if (!preg_match('/^[A-Z0-9._\-]{2,64}$/', $data['code'])) $errors[] = 'Mã khoá học chỉ gồm chữ HOA, số, dấu chấm, gạch ngang.';
        if (DB::exists('courses', 'code = :c' . ($course ? ' AND id <> :id' : ''),
            $course ? ['c' => $data['code'], 'id' => $course['id']] : ['c' => $data['code']])) {
            $errors[] = 'Mã khoá học đã tồn tại, vui lòng chọn mã khác.';
        }
        if ($data['enroll_mode'] === 'key' && !$data['enroll_key']) $data['enroll_key'] = random_code(6);

        // Ảnh bìa
        $coverId = $course ? (int)$course['cover_id'] : 0;
        if (!empty($_FILES['cover']['name'])) {
            if (!is_image_ext(ext_of($_FILES['cover']['name']))) $errors[] = 'Ảnh bìa phải là tệp hình ảnh.';
            else {
                list($fid, $err) = Storage::saveUpload($_FILES['cover'], 'public');
                if ($err) $errors[] = $err;
                else { if ($coverId) Storage::delete($coverId); $coverId = $fid; }
            }
        }
        if (inp('remove_cover') === '1' && $coverId) { Storage::delete($coverId); $coverId = 0; }
        $data['cover_id'] = $coverId ?: null;

        if (!$errors) {
            if ($course) {
                DB::update('courses', $data, 'id = :id', ['id' => $course['id']]);
                $cid = (int)$course['id'];
                flash_ok('Đã cập nhật khoá học. ✨');
            } else {
                $data['owner_id'] = Auth::id();
                $data['created_at'] = now();
                $cid = DB::insert('courses', $data);
                DB::insert('sections', ['course_id' => $cid, 'title' => 'Chương 1: Giới thiệu', 'position' => 1, 'visible' => 1]);
                flash_ok('Đã tạo khoá học mới! Hãy thêm nội dung cho lớp nhé 🎉');
            }
            Log::write($course ? 'course_update' : 'course_create', 'course', $cid);
            redirect(url('teach/content', ['id' => $cid]));
        }
    }

    $categories = DB::all('SELECT * FROM {P}categories ORDER BY position, name');
    view('teach_course_edit', [
        'title' => $course ? 'Cài đặt khoá học' : 'Tạo khoá học mới',
        'course' => $course, 'categories' => $categories, 'errors' => $errors,
    ]);
}

function teach_course_delete()
{
    Auth::requireTeacher();
    csrf_verify();
    $course = teach_guard_course(inp_int('id'));
    if (!Auth::isAdmin() && (int)$course['owner_id'] !== Auth::id()) {
        Auth::deny('Chỉ chủ sở hữu khoá học hoặc quản trị viên mới xoá được.');
    }

    $itemIds = DB::col('SELECT id FROM {P}items WHERE course_id = :c', ['c' => $course['id']]);
    foreach ($itemIds as $iid) teach_delete_item_data((int)$iid);

    DB::delete('sections', 'course_id = :c', ['c' => $course['id']]);
    DB::delete('enrollments', 'course_id = :c', ['c' => $course['id']]);
    DB::delete('course_teachers', 'course_id = :c', ['c' => $course['id']]);
    DB::delete('announcements', 'course_id = :c', ['c' => $course['id']]);
    DB::delete('completions', 'course_id = :c', ['c' => $course['id']]);
    $threads = DB::col('SELECT id FROM {P}threads WHERE course_id = :c', ['c' => $course['id']]);
    foreach ($threads as $t) DB::delete('posts', 'thread_id = :t', ['t' => $t]);
    DB::delete('threads', 'course_id = :c', ['c' => $course['id']]);
    if ($course['cover_id']) Storage::delete($course['cover_id']);
    DB::delete('courses', 'id = :c', ['c' => $course['id']]);

    Log::write('course_delete', 'course', $course['id'], $course['title']);
    flash_ok('Đã xoá khoá học và toàn bộ dữ liệu liên quan.');
    redirect(url('teach/courses'));
}

/** Xoá toàn bộ dữ liệu phụ thuộc của một mục nội dung */
function teach_delete_item_data($itemId)
{
    $item = DB::find('items', $itemId);
    if (!$item) return;

    // Bài nộp
    $subs = DB::col('SELECT id FROM {P}submissions WHERE item_id = :i', ['i' => $itemId]);
    foreach ($subs as $sid) {
        foreach (DB::col('SELECT file_id FROM {P}submission_files WHERE submission_id = :s', ['s' => $sid]) as $fid) {
            Storage::delete($fid);
        }
        DB::delete('submission_files', 'submission_id = :s', ['s' => $sid]);
        DB::delete('ai_jobs', 'submission_id = :s', ['s' => $sid]);
    }
    DB::delete('submissions', 'item_id = :i', ['i' => $itemId]);
    DB::delete('assignments', 'item_id = :i', ['i' => $itemId]);

    // Trắc nghiệm
    $quiz = DB::row('SELECT * FROM {P}quizzes WHERE item_id = :i', ['i' => $itemId]);
    if ($quiz) {
        foreach (DB::col('SELECT id FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']]) as $qid) {
            DB::delete('options', 'question_id = :q', ['q' => $qid]);
        }
        DB::delete('questions', 'quiz_id = :q', ['q' => $quiz['id']]);
        foreach (DB::col('SELECT id FROM {P}attempts WHERE quiz_id = :q', ['q' => $quiz['id']]) as $aid) {
            DB::delete('answers', 'attempt_id = :a', ['a' => $aid]);
        }
        DB::delete('attempts', 'quiz_id = :q', ['q' => $quiz['id']]);
        DB::delete('quizzes', 'id = :q', ['q' => $quiz['id']]);
    }

    // SCORM
    foreach (DB::col('SELECT id FROM {P}scorm_packages WHERE item_id = :i', ['i' => $itemId]) as $pid) {
        Scorm::deletePackage($pid);
    }

    // Tệp đính kèm
    foreach (DB::col('SELECT file_id FROM {P}item_files WHERE item_id = :i', ['i' => $itemId]) as $fid) Storage::delete($fid);
    DB::delete('item_files', 'item_id = :i', ['i' => $itemId]);
    if ($item['file_id']) Storage::delete($item['file_id']);

    DB::delete('completions', 'item_id = :i', ['i' => $itemId]);
    DB::delete('items', 'id = :i', ['i' => $itemId]);
}

// ------------------------------------------------------------------ nội dung
function teach_content()
{
    $course = teach_guard_course(inp_int('id'));
    $sections = DB::all('SELECT * FROM {P}sections WHERE course_id = :c ORDER BY position, id', ['c' => $course['id']]);
    $items = DB::all('SELECT i.*, (SELECT a.due_at FROM {P}assignments a WHERE a.item_id = i.id) AS due_at,
                        (SELECT COUNT(*) FROM {P}submissions s WHERE s.item_id = i.id AND s.status <> "draft") AS sub_count,
                        (SELECT COUNT(*) FROM {P}questions q JOIN {P}quizzes qz ON qz.id = q.quiz_id WHERE qz.item_id = i.id) AS q_count
                      FROM {P}items i WHERE i.course_id = :c ORDER BY i.position, i.id', ['c' => $course['id']]);
    $bySection = [];
    foreach ($items as $it) $bySection[(int)$it['section_id']][] = $it;

    view('teach_content', [
        'title' => 'Nội dung: ' . $course['title'], 'course' => $course,
        'sections' => $sections, 'bySection' => $bySection,
    ]);
}

function teach_section()
{
    Auth::requireTeacher();
    csrf_verify();
    $course = teach_guard_course(inp_int('course'));
    $action = inp('action', 'save');
    $id = inp_int('sid');

    if ($action === 'delete' && $id) {
        DB::update('items', ['section_id' => null], 'section_id = :s', ['s' => $id]);
        DB::delete('sections', 'id = :s AND course_id = :c', ['s' => $id, 'c' => $course['id']]);
        flash_ok('Đã xoá chương. Các mục bên trong được chuyển về “Nội dung chung”.');
    } elseif ($action === 'move' && $id) {
        $dir = inp('dir') === 'up' ? -1 : 1;
        $cur = DB::find('sections', $id);
        if ($cur) {
            $list = DB::all('SELECT id, position FROM {P}sections WHERE course_id = :c ORDER BY position, id', ['c' => $course['id']]);
            foreach ($list as $i => $s) {
                if ((int)$s['id'] === $id) {
                    $j = $i + $dir;
                    if ($j >= 0 && $j < count($list)) {
                        DB::update('sections', ['position' => $i + 1], 'id = :id', ['id' => $list[$j]['id']]);
                        DB::update('sections', ['position' => $j + 1], 'id = :id', ['id' => $id]);
                    }
                    break;
                }
            }
        }
    } else {
        $data = [
            'title'   => inp('title') ?: 'Chương mới',
            'summary' => inp('summary') ?: null,
            'visible' => inp_bool('visible'),
        ];
        if ($id) {
            DB::update('sections', $data, 'id = :s AND course_id = :c', ['s' => $id, 'c' => $course['id']]);
            flash_ok('Đã cập nhật chương.');
        } else {
            $max = (int)DB::val('SELECT COALESCE(MAX(position),0) FROM {P}sections WHERE course_id = :c', ['c' => $course['id']], 0);
            $data['course_id'] = $course['id'];
            $data['position'] = $max + 1;
            DB::insert('sections', $data);
            flash_ok('Đã thêm chương mới. 🎈');
        }
    }
    redirect(url('teach/content', ['id' => $course['id']]));
}

function teach_item_edit()
{
    Auth::requireTeacher();
    $id = inp_int('id');
    $item = $id ? DB::find('items', $id) : null;
    if ($id && !$item) { render_404(); return; }

    $courseId = $item ? (int)$item['course_id'] : inp_int('course');
    $course = teach_guard_course($courseId);
    $errors = [];
    $notices = [];

    if (is_post()) {
        csrf_verify();
        $type = inp('type', $item ? $item['type'] : 'page');
        if (!in_array($type, ['page', 'file', 'video', 'link', 'scorm', 'assignment', 'quiz', 'forum'], true)) $type = 'page';

        $data = [
            'course_id'  => $course['id'],
            'section_id' => inp_int('section_id') ?: null,
            'type'       => $type,
            'title'      => inp('title'),
            'summary'    => inp('summary') ?: null,
            'content'    => safe_html(inp('content')) ?: null,
            'url'        => inp('url') ?: null,
            'visible'    => inp_bool('visible'),
            'open_at'    => inp('open_at') ? str_replace('T', ' ', inp('open_at')) . ':00' : null,
            'close_at'   => inp('close_at') ? str_replace('T', ' ', inp('close_at')) . ':00' : null,
            'graded'     => in_array($type, ['assignment', 'quiz'], true) ? 1 : inp_bool('graded'),
            'max_points' => inp_float('max_points', 10),
            'weight'     => inp_float('weight', 1),
            'updated_at' => now(),
        ];
        if ($data['title'] === '') $errors[] = 'Vui lòng nhập tên cho mục nội dung.';
        if ($type === 'link' && !$data['url']) $errors[] = 'Vui lòng nhập địa chỉ liên kết.';

        if (!$errors) {
            if ($item) {
                DB::update('items', $data, 'id = :id', ['id' => $item['id']]);
                $itemId = (int)$item['id'];
            } else {
                $max = (int)DB::val('SELECT COALESCE(MAX(position),0) FROM {P}items WHERE course_id = :c', ['c' => $course['id']], 0);
                $data['position'] = $max + 1;
                $data['created_at'] = now();
                $itemId = DB::insert('items', $data);
            }

            // Tệp chính
            if (!empty($_FILES['main_file']['name'])) {
                list($fid, $err) = Storage::saveUpload($_FILES['main_file'], 'auth');
                if ($err) $errors[] = $err;
                else {
                    $old = $item ? (int)$item['file_id'] : 0;
                    DB::update('items', ['file_id' => $fid], 'id = :id', ['id' => $itemId]);
                    if ($old) Storage::delete($old);
                }
            }

            // Tệp đính kèm bổ sung
            if (!empty($_FILES['files']['name'][0])) {
                foreach ($_FILES['files']['name'] as $i => $name) {
                    if ($name === '') continue;
                    $f = ['name' => $name, 'type' => $_FILES['files']['type'][$i], 'tmp_name' => $_FILES['files']['tmp_name'][$i],
                          'error' => $_FILES['files']['error'][$i], 'size' => $_FILES['files']['size'][$i]];
                    list($fid, $err) = Storage::saveUpload($f, 'auth');
                    if ($err) { $errors[] = $err; continue; }
                    DB::insert('item_files', ['item_id' => $itemId, 'file_id' => $fid, 'position' => $i]);
                }
            }

            // Gói SCORM
            if ($type === 'scorm' && !empty($_FILES['scorm_zip']['name'])) {
                if (ext_of($_FILES['scorm_zip']['name']) !== 'zip') {
                    $errors[] = 'Gói SCORM phải là tệp .zip.';
                } elseif ($_FILES['scorm_zip']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = upload_error_message($_FILES['scorm_zip']['error']);
                } else {
                    $bin = @file_get_contents($_FILES['scorm_zip']['tmp_name']);
                    if ($bin === false) $errors[] = 'Không đọc được tệp gói SCORM.';
                    else {
                        foreach (DB::col('SELECT id FROM {P}scorm_packages WHERE item_id = :i', ['i' => $itemId]) as $old) {
                            Scorm::deletePackage($old);
                        }
                        $res = Scorm::import($itemId, $bin, $_FILES['scorm_zip']['name']);
                        if (!$res['ok']) $errors[] = $res['error'];
                        else {
                            $notices[] = 'Đã nhập gói SCORM ' . $res['version'] . ' với ' . $res['files'] . ' tệp. '
                                . 'Tệp khởi chạy: ' . $res['launch'] . ($res['error'] ? ' — ' . $res['error'] : '');
                        }
                    }
                }
            }

            // Cấu hình bài tập
            if ($type === 'assignment') {
                $types = [];
                if (inp_bool('sub_file')) $types[] = 'file';
                if (inp_bool('sub_text')) $types[] = 'text';
                if (!$types) $types = ['file', 'text'];
                $asgData = [
                    'instructions'    => safe_html(inp('instructions')) ?: null,
                    'due_at'          => inp('due_at') ? str_replace('T', ' ', inp('due_at')) . ':00' : null,
                    'cutoff_at'       => inp('cutoff_at') ? str_replace('T', ' ', inp('cutoff_at')) . ':00' : null,
                    'allow_late'      => inp_bool('allow_late'),
                    'submission_type' => implode(',', $types),
                    'max_files'       => max(1, inp_int('max_files', 5)),
                    'allowed_ext'     => inp('allowed_ext') ?: null,
                    'max_attempts'    => max(1, inp_int('max_attempts', 1)),
                    'ai_enabled'      => inp_bool('ai_enabled'),
                    'ai_auto'         => inp_bool('ai_auto'),
                    'ai_apply'        => inp_bool('ai_apply'),
                    'ai_rubric'       => inp('ai_rubric') ?: null,
                ];
                if (DB::exists('assignments', 'item_id = :i', ['i' => $itemId])) {
                    DB::update('assignments', $asgData, 'item_id = :i', ['i' => $itemId]);
                } else {
                    $asgData['item_id'] = $itemId;
                    DB::insert('assignments', $asgData);
                }
            }

            // Cấu hình trắc nghiệm
            if ($type === 'quiz') {
                $qData = [
                    'intro'        => safe_html(inp('intro')) ?: null,
                    'time_limit'   => max(0, inp_int('time_limit')),
                    'max_attempts' => max(1, inp_int('quiz_attempts', 1)),
                    'shuffle_q'    => inp_bool('shuffle_q'),
                    'shuffle_a'    => inp_bool('shuffle_a'),
                    'show_result'  => in_array(inp('show_result'), ['immediate', 'after_close', 'never'], true) ? inp('show_result') : 'immediate',
                    'pass_score'   => max(0, min(100, inp_float('pass_score', 50))),
                    'grade_method' => in_array(inp('grade_method'), ['highest', 'last', 'average', 'first'], true) ? inp('grade_method') : 'highest',
                    'ai_enabled'   => inp_bool('quiz_ai'),
                ];
                if (DB::exists('quizzes', 'item_id = :i', ['i' => $itemId])) {
                    DB::update('quizzes', $qData, 'item_id = :i', ['i' => $itemId]);
                } else {
                    $qData['item_id'] = $itemId;
                    DB::insert('quizzes', $qData);
                }
            }

            Log::write($item ? 'item_update' : 'item_create', 'item', $itemId);
            foreach ($notices as $n) flash_info($n);
            foreach ($errors as $er) flash_warn($er);
            if (!$errors) flash_ok('Đã lưu nội dung. ✅');

            if (inp('save_and_new') === '1') redirect(url('teach/item/edit', ['course' => $course['id'], 'type' => $type]));
            if ($type === 'quiz') redirect(url('teach/questions', ['id' => $itemId]));
            redirect(url('teach/content', ['id' => $course['id']]));
        }
    }

    $sections = DB::all('SELECT * FROM {P}sections WHERE course_id = :c ORDER BY position, id', ['c' => $course['id']]);
    $asg = $item ? DB::row('SELECT * FROM {P}assignments WHERE item_id = :i', ['i' => $item['id']]) : null;
    $quiz = $item ? DB::row('SELECT * FROM {P}quizzes WHERE item_id = :i', ['i' => $item['id']]) : null;
    $pkg = $item ? Scorm::package($item['id']) : null;
    $files = $item ? DB::all('SELECT f.*, itf.id AS link_id FROM {P}item_files itf JOIN {P}files f ON f.id = itf.file_id
                              WHERE itf.item_id = :i ORDER BY itf.position', ['i' => $item['id']]) : [];
    $mainFile = ($item && $item['file_id']) ? Storage::meta($item['file_id']) : null;

    view('teach_item_edit', [
        'title' => $item ? 'Sửa: ' . $item['title'] : 'Thêm nội dung mới',
        'course' => $course, 'item' => $item, 'sections' => $sections, 'errors' => $errors,
        'asg' => $asg, 'quiz' => $quiz, 'pkg' => $pkg, 'files' => $files, 'mainFile' => $mainFile,
        'defaultType' => inp('type', 'page'),
    ]);
}

function teach_item_delete()
{
    Auth::requireTeacher();
    csrf_verify();
    $item = DB::find('items', inp_int('id'));
    if (!$item) { render_404(); return; }
    $course = teach_guard_course($item['course_id']);
    teach_delete_item_data((int)$item['id']);
    Log::write('item_delete', 'item', $item['id'], $item['title']);
    flash_ok('Đã xoá mục nội dung.');
    redirect(url('teach/content', ['id' => $course['id']]));
}

function teach_item_move()
{
    Auth::requireTeacher();
    csrf_verify();
    $item = DB::find('items', inp_int('id'));
    if (!$item) json_out(['ok' => false], 404);
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canManageCourse($course)) json_out(['ok' => false], 403);

    $dir = inp('dir') === 'up' ? -1 : 1;
    $list = DB::all('SELECT id FROM {P}items WHERE course_id = :c AND COALESCE(section_id,0) = :s ORDER BY position, id',
                    ['c' => $course['id'], 's' => (int)$item['section_id']]);
    foreach ($list as $i => $row) {
        if ((int)$row['id'] === (int)$item['id']) {
            $j = $i + $dir;
            if ($j < 0 || $j >= count($list)) break;
            $tmp = $list[$j]['id'];
            DB::update('items', ['position' => ($i + 1) * 10], 'id = :id', ['id' => $tmp]);
            DB::update('items', ['position' => ($j + 1) * 10], 'id = :id', ['id' => $item['id']]);
            break;
        }
    }
    if (is_ajax()) json_out(['ok' => true]);
    redirect(url('teach/content', ['id' => $course['id']]));
}

function teach_item_file_delete()
{
    Auth::requireTeacher();
    csrf_verify();
    $linkId = inp_int('link');
    $row = DB::row('SELECT itf.*, i.course_id FROM {P}item_files itf JOIN {P}items i ON i.id = itf.item_id WHERE itf.id = :l',
                   ['l' => $linkId]);
    if (!$row) { back(); }
    teach_guard_course($row['course_id']);
    Storage::delete($row['file_id']);
    DB::delete('item_files', 'id = :l', ['l' => $linkId]);
    flash_ok('Đã xoá tệp đính kèm.');
    redirect(url('teach/item/edit', ['id' => $row['item_id']]));
}

// ------------------------------------------------------------------ học viên
function teach_students()
{
    $course = teach_guard_course(inp_int('id'));

    if (is_post()) {
        csrf_verify();
        $action = inp('action');
        if ($action === 'add') {
            $keys = preg_split('/[\s,;]+/u', trim(inp('users')));
            $added = 0; $missing = [];
            foreach ($keys as $k) {
                if ($k === '') continue;
                $u = DB::row('SELECT id FROM {P}users WHERE username = :k OR email = :k2', ['k' => $k, 'k2' => $k]);
                if (!$u) { $missing[] = $k; continue; }
                DB::q('INSERT INTO {P}enrollments (course_id, user_id, status, enrolled_at) VALUES (:c, :u, "active", :t)
                       ON DUPLICATE KEY UPDATE status = "active"',
                      ['c' => $course['id'], 'u' => $u['id'], 't' => now()]);
                Notify::push($u['id'], 'Bạn được thêm vào khoá học', $course['title'],
                    url('course/view', ['id' => $course['id']]), '🎓');
                $added++;
            }
            flash_ok('Đã thêm ' . $added . ' học viên vào lớp.');
            if ($missing) flash_warn('Không tìm thấy tài khoản: ' . implode(', ', array_slice($missing, 0, 10)));
        } elseif ($action === 'approve') {
            DB::update('enrollments', ['status' => 'active'], 'id = :e AND course_id = :c',
                       ['e' => inp_int('enroll'), 'c' => $course['id']]);
            flash_ok('Đã duyệt yêu cầu ghi danh.');
        } elseif ($action === 'remove') {
            DB::delete('enrollments', 'id = :e AND course_id = :c', ['e' => inp_int('enroll'), 'c' => $course['id']]);
            flash_ok('Đã gỡ học viên khỏi lớp.');
        } elseif ($action === 'add_teacher') {
            $u = DB::row('SELECT id, role FROM {P}users WHERE username = :k OR email = :k2',
                         ['k' => inp('teacher'), 'k2' => inp('teacher')]);
            if (!$u) flash_err('Không tìm thấy tài khoản giáo viên.');
            elseif ($u['role'] === 'student') flash_err('Tài khoản này không phải giáo viên.');
            else {
                DB::q('INSERT IGNORE INTO {P}course_teachers (course_id, user_id) VALUES (:c, :u)',
                      ['c' => $course['id'], 'u' => $u['id']]);
                flash_ok('Đã thêm giáo viên đồng phụ trách.');
            }
        } elseif ($action === 'remove_teacher') {
            DB::delete('course_teachers', 'course_id = :c AND user_id = :u',
                       ['c' => $course['id'], 'u' => inp_int('user')]);
            flash_ok('Đã gỡ giáo viên khỏi khoá học.');
        }
        redirect(url('teach/students', ['id' => $course['id']]));
    }

    $students = DB::all('SELECT e.*, u.full_name, u.username, u.email, u.avatar_id, u.org_unit, u.last_login,
                            (SELECT COUNT(*) FROM {P}completions cp WHERE cp.course_id = e.course_id AND cp.user_id = u.id AND cp.status = "completed") AS done
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status <> "removed"
                         ORDER BY e.status = "pending" DESC, u.full_name', ['c' => $course['id']]);
    $itemCount = (int)DB::val('SELECT COUNT(*) FROM {P}items WHERE course_id = :c AND visible = 1', ['c' => $course['id']], 0);
    $coTeachers = DB::all('SELECT u.* FROM {P}course_teachers ct JOIN {P}users u ON u.id = ct.user_id WHERE ct.course_id = :c',
                          ['c' => $course['id']]);

    view('teach_students', [
        'title' => 'Học viên: ' . $course['title'], 'course' => $course,
        'students' => $students, 'itemCount' => $itemCount, 'coTeachers' => $coTeachers,
    ]);
}

// ------------------------------------------------------------------ chấm bài
function teach_grading()
{
    Auth::requireTeacher();
    $uid = Auth::id();
    $rows = DB::all('SELECT s.*, u.full_name, u.avatar_id, u.username, i.title AS item_title, i.id AS item_id,
                            i.max_points, c.title AS course_title, c.id AS course_id
                     FROM {P}submissions s
                     JOIN {P}users u ON u.id = s.user_id
                     JOIN {P}items i ON i.id = s.item_id
                     JOIN {P}courses c ON c.id = i.course_id
                     WHERE s.status = "submitted"
                       AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))
                     ORDER BY s.submitted_at ASC LIMIT 200', ['u' => $uid, 'u2' => $uid]);

    $quizPending = DB::all('SELECT a.*, u.full_name, u.avatar_id, i.title AS item_title, i.id AS item_id, c.title AS course_title
                            FROM {P}attempts a
                            JOIN {P}users u ON u.id = a.user_id
                            JOIN {P}items i ON i.id = a.item_id
                            JOIN {P}courses c ON c.id = i.course_id
                            WHERE a.status = "submitted"
                              AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))
                            ORDER BY a.finished_at ASC LIMIT 100', ['u' => $uid, 'u2' => $uid]);

    view('teach_grading', ['title' => 'Cần chấm bài', 'rows' => $rows, 'quizPending' => $quizPending]);
}

// ------------------------------------------------------------------ câu hỏi
function teach_questions()
{
    Auth::requireTeacher();
    $item = DB::find('items', inp_int('id'));
    if (!$item) { render_404(); return; }
    $course = teach_guard_course($item['course_id']);
    require_once LMS_APP . '/controllers/quiz.php';
    $quiz = quiz_get($item['id']);

    $questions = DB::all('SELECT * FROM {P}questions WHERE quiz_id = :q ORDER BY position, id', ['q' => $quiz['id']]);
    foreach ($questions as &$q) {
        $q['options'] = DB::all('SELECT * FROM {P}options WHERE question_id = :q ORDER BY position, id', ['q' => $q['id']]);
    }
    unset($q);

    view('teach_questions', [
        'title' => 'Câu hỏi: ' . $item['title'], 'item' => $item, 'course' => $course,
        'quiz' => $quiz, 'questions' => $questions,
    ]);
}

function teach_question_save()
{
    Auth::requireTeacher();
    csrf_verify();
    $quiz = DB::find('quizzes', inp_int('quiz'));
    if (!$quiz) { render_404(); return; }
    $item = DB::find('items', $quiz['item_id']);
    $course = teach_guard_course($item['course_id']);

    $qid = inp_int('qid');
    $type = in_array(inp('type'), ['single', 'multi', 'truefalse', 'short', 'essay'], true) ? inp('type') : 'single';
    $data = [
        'quiz_id'    => $quiz['id'],
        'type'       => $type,
        'content'    => safe_html(inp('content')),
        'points'     => max(0, inp_float('points', 1)),
        'feedback'   => inp('feedback') ?: null,
        'answer_key' => inp('answer_key') ?: null,
    ];
    if (trim(strip_tags($data['content'])) === '') {
        flash_err('Nội dung câu hỏi không được để trống.');
        redirect(url('teach/questions', ['id' => $item['id']]));
    }

    if ($qid) {
        DB::update('questions', $data, 'id = :id AND quiz_id = :q', ['id' => $qid, 'q' => $quiz['id']]);
    } else {
        $max = (int)DB::val('SELECT COALESCE(MAX(position),0) FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']], 0);
        $data['position'] = $max + 1;
        $qid = DB::insert('questions', $data);
    }

    // Ảnh minh hoạ
    if (!empty($_FILES['image']['name']) && is_image_ext(ext_of($_FILES['image']['name']))) {
        list($fid, $err) = Storage::saveUpload($_FILES['image'], 'auth');
        if (!$err) {
            $old = (int)DB::val('SELECT image_id FROM {P}questions WHERE id = :id', ['id' => $qid], 0);
            DB::update('questions', ['image_id' => $fid], 'id = :id', ['id' => $qid]);
            if ($old) Storage::delete($old);
        }
    }

    // Phương án trả lời
    if (in_array($type, ['single', 'multi', 'truefalse'], true)) {
        DB::delete('options', 'question_id = :q', ['q' => $qid]);
        $opts = isset($_POST['option']) ? $_POST['option'] : [];
        $correct = isset($_POST['correct']) ? (array)$_POST['correct'] : [];
        if ($type === 'truefalse' && !$opts) {
            $opts = ['Đúng', 'Sai'];
            $correct = [inp('tf_answer') === '1' ? 0 : 1];
        }
        $pos = 0;
        foreach ($opts as $i => $text) {
            if (trim((string)$text) === '') continue;
            DB::insert('options', [
                'question_id' => $qid,
                'content'     => safe_html($text),
                'correct'     => in_array((string)$i, array_map('strval', $correct), true) ? 1 : 0,
                'position'    => $pos++,
            ]);
        }
    }

    flash_ok('Đã lưu câu hỏi. ✅');
    redirect(url('teach/questions', ['id' => $item['id']]));
}

function teach_question_delete()
{
    Auth::requireTeacher();
    csrf_verify();
    $q = DB::find('questions', inp_int('qid'));
    if (!$q) { back(); }
    $quiz = DB::find('quizzes', $q['quiz_id']);
    $item = DB::find('items', $quiz['item_id']);
    teach_guard_course($item['course_id']);
    if ($q['image_id']) Storage::delete($q['image_id']);
    DB::delete('options', 'question_id = :q', ['q' => $q['id']]);
    DB::delete('answers', 'question_id = :q', ['q' => $q['id']]);
    DB::delete('questions', 'id = :q', ['q' => $q['id']]);
    flash_ok('Đã xoá câu hỏi.');
    redirect(url('teach/questions', ['id' => $item['id']]));
}

// ------------------------------------------------------------------ thông báo lớp
function teach_announce()
{
    $course = teach_guard_course(inp_int('id'));
    if (is_post()) {
        csrf_verify();
        if (inp('action') === 'delete') {
            DB::delete('announcements', 'id = :a AND course_id = :c', ['a' => inp_int('aid'), 'c' => $course['id']]);
            flash_ok('Đã xoá thông báo.');
        } else {
            $title = inp('title');
            if ($title === '') { flash_err('Vui lòng nhập tiêu đề thông báo.'); }
            else {
                DB::insert('announcements', [
                    'course_id' => $course['id'], 'user_id' => Auth::id(),
                    'title' => $title, 'content' => inp('content') ?: null,
                    'pinned' => inp_bool('pinned'), 'created_at' => now(),
                ]);
                $students = DB::col('SELECT user_id FROM {P}enrollments WHERE course_id = :c AND status = "active"', ['c' => $course['id']]);
                Notify::pushMany($students, '📢 ' . $title, str_limit(inp('content'), 120),
                    url('course/view', ['id' => $course['id']]), '📢');
                flash_ok('Đã đăng thông báo tới ' . count($students) . ' học viên. 📢');
            }
        }
        redirect(url('teach/announce', ['id' => $course['id']]));
    }

    $rows = DB::all('SELECT an.*, u.full_name AS author FROM {P}announcements an
                     LEFT JOIN {P}users u ON u.id = an.user_id
                     WHERE an.course_id = :c ORDER BY an.pinned DESC, an.id DESC', ['c' => $course['id']]);
    view('teach_announce', ['title' => 'Thông báo lớp', 'course' => $course, 'rows' => $rows]);
}

// ------------------------------------------------------------------ báo cáo
function teach_reports()
{
    Auth::requireTeacher();
    $uid = Auth::id();
    $courseId = inp_int('course');

    $courses = DB::all('SELECT c.id, c.title, c.code FROM {P}courses c
                        WHERE c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2)
                        ORDER BY c.title', ['u' => $uid, 'u2' => $uid]);
    if (!$courseId && $courses) $courseId = (int)$courses[0]['id'];
    if (!$courseId) {
        view('teach_reports', ['title' => 'Thống kê & báo cáo', 'courses' => [], 'course' => null]);
        return;
    }
    $course = teach_guard_course($courseId);

    $students = DB::all('SELECT u.id, u.full_name FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed")', ['c' => $courseId]);
    $items = DB::all('SELECT * FROM {P}items WHERE course_id = :c AND graded = 1 ORDER BY position, id', ['c' => $courseId]);
    $itemCount = (int)DB::val('SELECT COUNT(*) FROM {P}items WHERE course_id = :c AND visible = 1', ['c' => $courseId], 0);

    // Phân bố xếp loại
    $ranks = ['Xuất sắc' => 0, 'Giỏi' => 0, 'Khá' => 0, 'Trung bình' => 0, 'Chưa đạt' => 0, 'Chưa có điểm' => 0];
    $progressSum = 0;
    $scores10 = [];
    foreach ($students as $st) {
        $sum = DB::row('SELECT COALESCE(SUM(cp.score),0) AS got, COALESCE(SUM(i.max_points),0) AS max
                        FROM {P}completions cp JOIN {P}items i ON i.id = cp.item_id
                        WHERE cp.user_id = :u AND cp.course_id = :c AND i.graded = 1 AND cp.score IS NOT NULL',
                       ['u' => $st['id'], 'c' => $courseId]);
        $done = (int)DB::val('SELECT COUNT(*) FROM {P}completions WHERE user_id = :u AND course_id = :c AND status = "completed"',
                             ['u' => $st['id'], 'c' => $courseId], 0);
        $progressSum += $itemCount ? min(100, $done / $itemCount * 100) : 0;
        if ($sum && (float)$sum['max'] > 0) {
            $s10 = round((float)$sum['got'] / (float)$sum['max'] * 10, 2);
            $scores10[] = $s10;
            list($rank) = grade_rank($s10);
            if (isset($ranks[$rank])) $ranks[$rank]++;
        } else {
            $ranks['Chưa có điểm']++;
        }
    }

    // Tỉ lệ nộp bài theo mục
    $itemStats = [];
    foreach ($items as $it) {
        if ($it['type'] === 'assignment') {
            $n = (int)DB::val('SELECT COUNT(DISTINCT user_id) FROM {P}submissions WHERE item_id = :i AND status <> "draft"', ['i' => $it['id']], 0);
        } elseif ($it['type'] === 'quiz') {
            $n = (int)DB::val('SELECT COUNT(DISTINCT user_id) FROM {P}attempts WHERE item_id = :i AND status <> "in_progress"', ['i' => $it['id']], 0);
        } else {
            $n = (int)DB::val('SELECT COUNT(*) FROM {P}completions WHERE item_id = :i AND status = "completed"', ['i' => $it['id']], 0);
        }
        $itemStats[] = ['label' => str_limit($it['title'], 16, ''), 'value' => $n];
    }

    // Hoạt động 14 ngày
    $series = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $n = (int)DB::val('SELECT COUNT(*) FROM {P}submissions s JOIN {P}items i ON i.id = s.item_id
                           WHERE i.course_id = :c AND DATE(s.submitted_at) = :d', ['c' => $courseId, 'd' => $d], 0);
        $n += (int)DB::val('SELECT COUNT(*) FROM {P}attempts a WHERE a.item_id IN (SELECT id FROM {P}items WHERE course_id = :c)
                            AND DATE(a.finished_at) = :d', ['c' => $courseId, 'd' => $d], 0);
        $series[] = ['label' => date('d/m', strtotime($d)), 'value' => $n];
    }

    view('teach_reports', [
        'title' => 'Thống kê & báo cáo', 'courses' => $courses, 'course' => $course,
        'students' => $students, 'items' => $items, 'ranks' => $ranks, 'itemStats' => $itemStats,
        'series' => $series, 'avgProgress' => $students ? round($progressSum / count($students)) : 0,
        'avgScore' => $scores10 ? round(array_sum($scores10) / count($scores10), 2) : null,
        'itemCount' => $itemCount,
    ]);
}
