<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Khoá học: danh sách, xem nội dung, ghi danh */

function course_mine()
{
    Auth::requireLogin();
    $uid = Auth::id();
    $courses = DB::all(
        'SELECT c.*, u.full_name AS teacher_name, e.status AS enroll_status, e.enrolled_at,
                cat.name AS category_name, cat.color AS category_color, cat.icon AS category_icon,
                (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id AND i.visible = 1) AS item_count,
                (SELECT COUNT(*) FROM {P}completions cp WHERE cp.course_id = c.id AND cp.user_id = :u AND cp.status = "completed") AS done_count,
                (SELECT COUNT(*) FROM {P}enrollments e2 WHERE e2.course_id = c.id AND e2.status = "active") AS student_count
         FROM {P}enrollments e
         JOIN {P}courses c ON c.id = e.course_id
         LEFT JOIN {P}users u ON u.id = c.owner_id
         LEFT JOIN {P}categories cat ON cat.id = c.category_id
         WHERE e.user_id = :u2 AND e.status IN ("active","completed","pending")
         ORDER BY c.status = "archived", e.enrolled_at DESC', ['u' => $uid, 'u2' => $uid]);

    view('course_mine', ['title' => 'Khoá học của tôi', 'courses' => $courses]);
}

function course_view()
{
    $id = inp_int('id');
    $course = DB::find('courses', $id);
    if (!$course) { render_404(); return; }

    $canManage = Auth::canManageCourse($course);
    $enrolled = Auth::isEnrolled($id);
    $enrollment = Auth::check()
        ? DB::row('SELECT * FROM {P}enrollments WHERE course_id = :c AND user_id = :u', ['c' => $id, 'u' => Auth::id()])
        : null;

    if (!$canManage && $course['status'] !== 'published') {
        render_denied('Khoá học này chưa được mở.');
        return;
    }

    // Người chưa ghi danh: xem trang giới thiệu
    if (!$canManage && !$enrolled) {
        $teacher = DB::find('users', $course['owner_id']);
        $counts = [
            'students' => (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE course_id = :c AND status = "active"', ['c' => $id], 0),
            'items'    => (int)DB::val('SELECT COUNT(*) FROM {P}items WHERE course_id = :c AND visible = 1', ['c' => $id], 0),
            'sections' => (int)DB::val('SELECT COUNT(*) FROM {P}sections WHERE course_id = :c', ['c' => $id], 0),
        ];
        $sections = DB::all('SELECT s.*, (SELECT COUNT(*) FROM {P}items i WHERE i.section_id = s.id AND i.visible = 1) AS n
                             FROM {P}sections s WHERE s.course_id = :c ORDER BY s.position, s.id', ['c' => $id]);
        view('course_preview', [
            'title' => $course['title'], 'course' => $course, 'teacher' => $teacher,
            'counts' => $counts, 'sections' => $sections, 'enrollment' => $enrollment,
        ], Auth::check() ? 'app' : 'public');
        return;
    }

    $sections = DB::all('SELECT * FROM {P}sections WHERE course_id = :c ORDER BY position, id', ['c' => $id]);
    $itemWhere = $canManage ? '' : ' AND i.visible = 1';
    $items = DB::all("SELECT i.*,
                        (SELECT a.due_at FROM {P}assignments a WHERE a.item_id = i.id) AS due_at,
                        (SELECT cp.status FROM {P}completions cp WHERE cp.item_id = i.id AND cp.user_id = :u) AS done,
                        (SELECT s.status FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u2 ORDER BY s.id DESC LIMIT 1) AS sub_status,
                        (SELECT s.score FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u3 ORDER BY s.id DESC LIMIT 1) AS sub_score
                      FROM {P}items i WHERE i.course_id = :c $itemWhere ORDER BY i.position, i.id",
                     ['c' => $id, 'u' => Auth::id(), 'u2' => Auth::id(), 'u3' => Auth::id()]);

    $bySection = [];
    foreach ($items as $it) {
        $sid = $it['section_id'] ? (int)$it['section_id'] : 0;
        $bySection[$sid][] = $it;
    }

    $announcements = DB::all('SELECT an.*, u.full_name AS author FROM {P}announcements an
                              LEFT JOIN {P}users u ON u.id = an.user_id
                              WHERE an.course_id = :c ORDER BY an.pinned DESC, an.id DESC LIMIT 5', ['c' => $id]);

    $teachers = DB::all('SELECT u.* FROM {P}users u WHERE u.id = :owner
                         UNION SELECT u2.* FROM {P}users u2
                         JOIN {P}course_teachers ct ON ct.user_id = u2.id AND ct.course_id = :c',
                        ['owner' => $course['owner_id'], 'c' => $id]);

    $totalVisible = 0; $doneCount = 0;
    foreach ($items as $it) {
        if (!$it['visible']) continue;
        $totalVisible++;
        if ($it['done'] === 'completed') $doneCount++;
    }
    $progress = $totalVisible ? round($doneCount / $totalVisible * 100) : 0;

    if (!$canManage && $enrollment && (int)$enrollment['progress'] !== (int)$progress) {
        DB::update('enrollments', ['progress' => $progress], 'id = :id', ['id' => $enrollment['id']]);
    }

    $studentCount = (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE course_id = :c AND status = "active"', ['c' => $id], 0);

    view('course_view', [
        'title' => $course['title'], 'course' => $course, 'sections' => $sections,
        'bySection' => $bySection, 'canManage' => $canManage, 'announcements' => $announcements,
        'teachers' => $teachers, 'progress' => $progress, 'doneCount' => $doneCount,
        'totalVisible' => $totalVisible, 'studentCount' => $studentCount,
    ]);
}

function course_enroll()
{
    Auth::requireLogin();
    $id = inp_int('id');
    $course = DB::find('courses', $id);
    if (!$course || $course['status'] !== 'published') { render_404(); return; }

    if (DB::exists('enrollments', 'course_id = :c AND user_id = :u AND status IN ("active","completed")',
        ['c' => $id, 'u' => Auth::id()])) {
        redirect(url('course/view', ['id' => $id]));
    }

    if ($course['enroll_mode'] === 'manual') {
        flash_warn('Khoá học này chỉ nhận học viên do giáo viên thêm vào.');
        redirect(url('course/view', ['id' => $id]));
    }

    if ($course['max_students'] > 0) {
        $n = (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE course_id = :c AND status = "active"', ['c' => $id], 0);
        if ($n >= (int)$course['max_students']) {
            flash_err('Lớp đã đủ sĩ số tối đa (' . (int)$course['max_students'] . ' học viên).');
            redirect(url('course/view', ['id' => $id]));
        }
    }

    if (is_post()) {
        csrf_verify();
        if ($course['enroll_mode'] === 'key') {
            if (trim((string)inp('enroll_key')) !== (string)$course['enroll_key']) {
                flash_err('Mã ghi danh chưa đúng, vui lòng hỏi lại giáo viên.');
                redirect(url('course/view', ['id' => $id]));
            }
        }
        $status = $course['enroll_mode'] === 'approval' ? 'pending' : 'active';
        $existing = DB::row('SELECT * FROM {P}enrollments WHERE course_id = :c AND user_id = :u', ['c' => $id, 'u' => Auth::id()]);
        if ($existing) {
            DB::update('enrollments', ['status' => $status, 'enrolled_at' => now()], 'id = :id', ['id' => $existing['id']]);
        } else {
            DB::insert('enrollments', [
                'course_id' => $id, 'user_id' => Auth::id(), 'status' => $status,
                'progress' => 0, 'enrolled_at' => now(),
            ]);
        }
        Log::write('enroll', 'course', $id);
        Notify::push($course['owner_id'],
            $status === 'pending' ? 'Có yêu cầu ghi danh mới' : 'Học viên mới ghi danh',
            Auth::name() . ' · ' . $course['title'],
            url('teach/students', ['id' => $id]), '🙋');

        if ($status === 'pending') {
            flash_info('Đã gửi yêu cầu ghi danh. Vui lòng chờ giáo viên duyệt nhé!');
        } else {
            flash_ok('Ghi danh thành công! Chúc bạn học thật vui 🎉');
        }
        redirect(url('course/view', ['id' => $id]));
    }
    redirect(url('course/view', ['id' => $id]));
}

function course_leave()
{
    Auth::requireLogin();
    csrf_verify();
    $id = inp_int('id');
    DB::update('enrollments', ['status' => 'removed'], 'course_id = :c AND user_id = :u',
        ['c' => $id, 'u' => Auth::id()]);
    flash_info('Bạn đã rời khỏi khoá học.');
    redirect(url('course/mine'));
}
