<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Bảng điều khiển theo vai trò */

function dashboard_index()
{
    Auth::requireLogin();
    $uid = Auth::id();
    $data = ['title' => 'Bảng điều khiển'];

    // ---------------------------------------------------------- khoá học của tôi
    $data['myCourses'] = DB::all(
        'SELECT c.*, u.full_name AS teacher_name, e.progress, e.status AS enroll_status,
                (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id AND i.visible = 1) AS item_count,
                (SELECT COUNT(*) FROM {P}completions cp WHERE cp.course_id = c.id AND cp.user_id = :u AND cp.status = "completed") AS done_count
         FROM {P}enrollments e
         JOIN {P}courses c ON c.id = e.course_id
         LEFT JOIN {P}users u ON u.id = c.owner_id
         WHERE e.user_id = :u2 AND e.status IN ("active","completed") AND c.status <> "archived"
         ORDER BY e.enrolled_at DESC LIMIT 6', ['u' => $uid, 'u2' => $uid]);

    // ---------------------------------------------------------- hạn nộp sắp tới
    $data['deadlines'] = DB::all(
        'SELECT i.id, i.title, i.type, i.max_points, c.title AS course_title, c.id AS course_id, a.due_at,
                (SELECT s.status FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u ORDER BY s.id DESC LIMIT 1) AS sub_status
         FROM {P}items i
         JOIN {P}courses c ON c.id = i.course_id
         LEFT JOIN {P}assignments a ON a.item_id = i.id
         JOIN {P}enrollments e ON e.course_id = c.id AND e.user_id = :u2 AND e.status = "active"
         WHERE i.type IN ("assignment","quiz") AND i.visible = 1 AND c.status = "published"
           AND (a.due_at IS NULL OR a.due_at >= NOW() - INTERVAL 3 DAY)
         ORDER BY (a.due_at IS NULL), a.due_at ASC LIMIT 6', ['u' => $uid, 'u2' => $uid]);

    // ---------------------------------------------------------- điểm gần đây
    $data['recentGrades'] = DB::all(
        'SELECT s.score, s.graded_at, s.status, i.title, i.max_points, c.title AS course_title, i.id AS item_id
         FROM {P}submissions s
         JOIN {P}items i ON i.id = s.item_id
         JOIN {P}courses c ON c.id = i.course_id
         WHERE s.user_id = :u AND s.score IS NOT NULL
         ORDER BY s.graded_at DESC LIMIT 5', ['u' => $uid]);

    // ---------------------------------------------------------- thông báo chung
    $data['announcements'] = DB::all(
        'SELECT an.*, u.full_name AS author, c.title AS course_title
         FROM {P}announcements an
         LEFT JOIN {P}users u ON u.id = an.user_id
         LEFT JOIN {P}courses c ON c.id = an.course_id
         WHERE an.course_id IS NULL
            OR an.course_id IN (SELECT course_id FROM {P}enrollments WHERE user_id = :u AND status IN ("active","completed"))
            OR an.course_id IN (SELECT id FROM {P}courses WHERE owner_id = :u2)
         ORDER BY an.pinned DESC, an.id DESC LIMIT 4', ['u' => $uid, 'u2' => $uid]);

    // ---------------------------------------------------------- thống kê học sinh
    $data['stats'] = [
        'courses'  => (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE user_id = :u AND status IN ("active","completed")', ['u' => $uid], 0),
        'done'     => (int)DB::val('SELECT COUNT(*) FROM {P}completions WHERE user_id = :u AND status = "completed"', ['u' => $uid], 0),
        'pending'  => (int)DB::val(
            'SELECT COUNT(*) FROM {P}items i
             JOIN {P}enrollments e ON e.course_id = i.course_id AND e.user_id = :u AND e.status = "active"
             LEFT JOIN {P}assignments a ON a.item_id = i.id
             WHERE i.type IN ("assignment","quiz") AND i.visible = 1
               AND NOT EXISTS (SELECT 1 FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u2 AND s.status <> "draft")
               AND NOT EXISTS (SELECT 1 FROM {P}attempts at WHERE at.item_id = i.id AND at.user_id = :u3 AND at.status <> "in_progress")',
            ['u' => $uid, 'u2' => $uid, 'u3' => $uid], 0),
        'avg'      => DB::val(
            'SELECT AVG(s.score / NULLIF(i.max_points,0) * 10) FROM {P}submissions s
             JOIN {P}items i ON i.id = s.item_id
             WHERE s.user_id = :u AND s.score IS NOT NULL', ['u' => $uid], null),
    ];

    // ---------------------------------------------------------- dành cho giáo viên
    if (Auth::isTeacher()) {
        $data['teachCourses'] = DB::all(
            'SELECT c.*, (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = "active") AS student_count,
                    (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id) AS item_count
             FROM {P}courses c
             WHERE c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2)
             ORDER BY c.updated_at DESC, c.id DESC LIMIT 6', ['u' => $uid, 'u2' => $uid]);

        $data['toGrade'] = DB::all(
            'SELECT s.id, s.submitted_at, s.is_late, u.full_name, u.avatar_id, i.title, i.id AS item_id, c.title AS course_title
             FROM {P}submissions s
             JOIN {P}users u ON u.id = s.user_id
             JOIN {P}items i ON i.id = s.item_id
             JOIN {P}courses c ON c.id = i.course_id
             WHERE s.status = "submitted"
               AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))
             ORDER BY s.submitted_at ASC LIMIT 8', ['u' => $uid, 'u2' => $uid]);

        $data['teachStats'] = [
            'courses'  => (int)DB::val('SELECT COUNT(*) FROM {P}courses c WHERE c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2)', ['u' => $uid, 'u2' => $uid], 0),
            'students' => (int)DB::val('SELECT COUNT(DISTINCT e.user_id) FROM {P}enrollments e JOIN {P}courses c ON c.id = e.course_id
                                        WHERE e.status = "active" AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))', ['u' => $uid, 'u2' => $uid], 0),
            'toGrade'  => (int)DB::val('SELECT COUNT(*) FROM {P}submissions s JOIN {P}items i ON i.id = s.item_id JOIN {P}courses c ON c.id = i.course_id
                                        WHERE s.status = "submitted" AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))', ['u' => $uid, 'u2' => $uid], 0),
        ];

        // Bài nộp 7 ngày gần nhất
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $n = (int)DB::val('SELECT COUNT(*) FROM {P}submissions s JOIN {P}items i ON i.id = s.item_id JOIN {P}courses c ON c.id = i.course_id
                               WHERE DATE(s.submitted_at) = :d AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))',
                              ['d' => $d, 'u' => $uid, 'u2' => $uid], 0);
            $series[] = ['label' => date('d/m', strtotime($d)), 'value' => $n];
        }
        $data['submitSeries'] = $series;
    }

    view('dashboard', $data);
}
