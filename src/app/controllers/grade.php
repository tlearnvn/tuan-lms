<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Sổ điểm và kết quả học tập */

/**
 * Dữ liệu sổ điểm của một khoá học.
 * @return array ['course','items','students','scores'[uid][itemId],'totals'[uid]]
 */
function gradebook_data($courseId)
{
    $course = DB::find('courses', $courseId);
    if (!$course) return null;

    $items = DB::all('SELECT * FROM {P}items WHERE course_id = :c AND graded = 1 ORDER BY position, id', ['c' => $courseId]);
    $students = DB::all('SELECT u.id, u.full_name, u.username, u.email, u.avatar_id, u.org_unit, e.status AS enroll_status
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed")
                         ORDER BY u.full_name', ['c' => $courseId]);

    $scores = [];
    // Điểm bài tập
    foreach (DB::all('SELECT s.user_id, s.item_id, MAX(s.score) AS score
                      FROM {P}submissions s JOIN {P}items i ON i.id = s.item_id
                      WHERE i.course_id = :c AND s.score IS NOT NULL
                      GROUP BY s.user_id, s.item_id', ['c' => $courseId]) as $r) {
        $scores[(int)$r['user_id']][(int)$r['item_id']] = (float)$r['score'];
    }
    // Điểm trắc nghiệm và SCORM (lưu ở bảng hoàn thành)
    foreach (DB::all('SELECT cp.user_id, cp.item_id, cp.score FROM {P}completions cp
                      JOIN {P}items i ON i.id = cp.item_id
                      WHERE cp.course_id = :c AND cp.score IS NOT NULL AND i.graded = 1', ['c' => $courseId]) as $r) {
        $uid = (int)$r['user_id']; $iid = (int)$r['item_id'];
        if (!isset($scores[$uid][$iid])) $scores[$uid][$iid] = (float)$r['score'];
    }

    // Tổng hợp theo hệ số
    $totals = [];
    foreach ($students as $st) {
        $uid = (int)$st['id'];
        $got = 0; $max = 0; $count = 0;
        foreach ($items as $it) {
            $w = (float)$it['weight'] ?: 1;
            if (isset($scores[$uid][(int)$it['id']])) {
                $got += $scores[$uid][(int)$it['id']] * $w;
                $max += (float)$it['max_points'] * $w;
                $count++;
            }
        }
        $score10 = $max > 0 ? round($got / $max * 10, 2) : null;
        $totals[$uid] = ['got' => $got, 'max' => $max, 'score10' => $score10, 'count' => $count];
    }

    return ['course' => $course, 'items' => $items, 'students' => $students,
            'scores' => $scores, 'totals' => $totals];
}

function grade_course()
{
    Auth::requireTeacher();
    $courseId = inp_int('id');
    $course = DB::find('courses', $courseId);
    if (!$course) { render_404(); return; }
    if (!Auth::canManageCourse($course)) { Auth::deny(); return; }

    $gb = gradebook_data($courseId);

    // Phổ điểm theo thang 10
    $dist = [0, 0, 0, 0, 0];
    foreach ($gb['totals'] as $t) {
        if ($t['score10'] === null) continue;
        $s = $t['score10'];
        if ($s < 5) $dist[0]++;
        elseif ($s < 6.5) $dist[1]++;
        elseif ($s < 8) $dist[2]++;
        elseif ($s < 9) $dist[3]++;
        else $dist[4]++;
    }

    view('grade_course', [
        'title' => 'Sổ điểm: ' . $course['title'],
        'course' => $course, 'gb' => $gb, 'dist' => $dist,
    ]);
}

function grade_mine()
{
    Auth::requireLogin();
    $uid = Auth::id();
    $courseId = inp_int('course');

    $courses = DB::all('SELECT c.* FROM {P}enrollments e JOIN {P}courses c ON c.id = e.course_id
                        WHERE e.user_id = :u AND e.status IN ("active","completed") ORDER BY c.title', ['u' => $uid]);

    $rows = [];
    $overall = ['got' => 0, 'max' => 0];
    foreach ($courses as $c) {
        if ($courseId && (int)$c['id'] !== $courseId) continue;
        $items = DB::all('SELECT * FROM {P}items WHERE course_id = :c AND graded = 1 ORDER BY position, id', ['c' => $c['id']]);
        $list = [];
        $got = 0; $max = 0;
        foreach ($items as $it) {
            $score = DB::val('SELECT MAX(score) FROM {P}submissions WHERE item_id = :i AND user_id = :u AND score IS NOT NULL',
                             ['i' => $it['id'], 'u' => $uid], null);
            if ($score === null) {
                $score = DB::val('SELECT score FROM {P}completions WHERE item_id = :i AND user_id = :u AND score IS NOT NULL',
                                 ['i' => $it['id'], 'u' => $uid], null);
            }
            $w = (float)$it['weight'] ?: 1;
            if ($score !== null) { $got += (float)$score * $w; $max += (float)$it['max_points'] * $w; }
            $list[] = ['item' => $it, 'score' => $score === null ? null : (float)$score];
        }
        $overall['got'] += $got;
        $overall['max'] += $max;
        $rows[] = ['course' => $c, 'items' => $list, 'got' => $got, 'max' => $max,
                   'score10' => $max > 0 ? round($got / $max * 10, 2) : null];
    }

    view('grade_mine', [
        'title' => 'Kết quả học tập', 'rows' => $rows, 'courses' => $courses,
        'courseId' => $courseId,
        'overall10' => $overall['max'] > 0 ? round($overall['got'] / $overall['max'] * 10, 2) : null,
    ]);
}
