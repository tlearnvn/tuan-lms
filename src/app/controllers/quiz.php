<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Bài trắc nghiệm: làm bài, tự động chấm, xem kết quả */
require_once LMS_APP . '/controllers/item.php';
require_once LMS_APP . '/Ai.php';

function quiz_get($itemId)
{
    $q = DB::row('SELECT * FROM {P}quizzes WHERE item_id = :i', ['i' => (int)$itemId]);
    if (!$q) {
        $id = DB::insert('quizzes', ['item_id' => (int)$itemId]);
        $q = DB::find('quizzes', $id);
    }
    return $q;
}

function quiz_view()
{
    list($item, $course, $canManage) = item_load(inp_int('id'));
    if ($item['type'] !== 'quiz') redirect(url('item/view', ['id' => $item['id']]));
    $quiz = quiz_get($item['id']);

    $questionCount = (int)DB::val('SELECT COUNT(*) FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']], 0);
    $totalPoints = (float)DB::val('SELECT COALESCE(SUM(points),0) FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']], 0);

    if ($canManage) {
        $attempts = DB::all('SELECT a.*, u.full_name, u.avatar_id, u.username FROM {P}attempts a
                             JOIN {P}users u ON u.id = a.user_id
                             WHERE a.quiz_id = :q ORDER BY a.id DESC', ['q' => $quiz['id']]);
        view('quiz_manage', [
            'title' => $item['title'], 'item' => $item, 'course' => $course, 'quiz' => $quiz,
            'questionCount' => $questionCount, 'totalPoints' => $totalPoints, 'attempts' => $attempts,
        ]);
        return;
    }

    $attempts = DB::all('SELECT * FROM {P}attempts WHERE quiz_id = :q AND user_id = :u ORDER BY number DESC',
                        ['q' => $quiz['id'], 'u' => Auth::id()]);
    $inProgress = null;
    foreach ($attempts as $a) if ($a['status'] === 'in_progress') { $inProgress = $a; break; }

    item_complete_mark($item, null, 'in_progress');

    view('quiz_view', [
        'title' => $item['title'], 'item' => $item, 'course' => $course, 'quiz' => $quiz,
        'questionCount' => $questionCount, 'totalPoints' => $totalPoints,
        'attempts' => $attempts, 'inProgress' => $inProgress,
    ]);
}

function quiz_start()
{
    Auth::requireLogin();
    csrf_verify();
    list($item, $course, $canManage) = item_load(inp_int('id'));
    $quiz = quiz_get($item['id']);

    if ($item['close_at'] && strtotime($item['close_at']) < time()) {
        flash_err('Bài trắc nghiệm đã đóng.');
        redirect(url('quiz/view', ['id' => $item['id']]));
    }

    $done = DB::all('SELECT * FROM {P}attempts WHERE quiz_id = :q AND user_id = :u AND status <> "in_progress"',
                    ['q' => $quiz['id'], 'u' => Auth::id()]);
    $running = DB::row('SELECT * FROM {P}attempts WHERE quiz_id = :q AND user_id = :u AND status = "in_progress" ORDER BY id DESC LIMIT 1',
                       ['q' => $quiz['id'], 'u' => Auth::id()]);
    if ($running) redirect(url('quiz/attempt', ['a' => $running['id']]));

    $max = max(1, (int)$quiz['max_attempts']);
    if (count($done) >= $max) {
        flash_err('Bạn đã dùng hết ' . $max . ' lượt làm bài.');
        redirect(url('quiz/view', ['id' => $item['id']]));
    }

    $questions = DB::col('SELECT id FROM {P}questions WHERE quiz_id = :q ORDER BY position, id', ['q' => $quiz['id']]);
    if (!$questions) {
        flash_err('Bài trắc nghiệm chưa có câu hỏi nào.');
        redirect(url('quiz/view', ['id' => $item['id']]));
    }
    if ($quiz['shuffle_q']) shuffle($questions);

    $order = ['q' => $questions, 'o' => []];
    if ($quiz['shuffle_a']) {
        foreach ($questions as $qid) {
            $opts = DB::col('SELECT id FROM {P}options WHERE question_id = :q ORDER BY position, id', ['q' => $qid]);
            shuffle($opts);
            $order['o'][$qid] = $opts;
        }
    }

    $attemptId = DB::insert('attempts', [
        'quiz_id'    => $quiz['id'],
        'item_id'    => $item['id'],
        'user_id'    => Auth::id(),
        'number'     => count($done) + 1,
        'started_at' => now(),
        'status'     => 'in_progress',
        'max_score'  => (float)DB::val('SELECT COALESCE(SUM(points),0) FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']], 0),
        'order_data' => json_encode($order, JSON_UNESCAPED_UNICODE),
    ]);
    Log::write('quiz_start', 'item', $item['id']);
    redirect(url('quiz/attempt', ['a' => $attemptId]));
}

function quiz_attempt()
{
    Auth::requireLogin();
    $attempt = DB::find('attempts', inp_int('a'));
    if (!$attempt || (int)$attempt['user_id'] !== Auth::id()) { render_404(); return; }
    $item = DB::find('items', $attempt['item_id']);
    $course = DB::find('courses', $item['course_id']);
    $quiz = DB::find('quizzes', $attempt['quiz_id']);

    if ($attempt['status'] !== 'in_progress') redirect(url('quiz/result', ['a' => $attempt['id']]));

    // Hết giờ thì tự nộp
    $deadline = null;
    if ($quiz['time_limit'] > 0) {
        $deadline = strtotime($attempt['started_at']) + (int)$quiz['time_limit'] * 60;
        if (time() > $deadline + 20) {
            quiz_do_finish($attempt, $quiz, $item, true);
            redirect(url('quiz/result', ['a' => $attempt['id']]));
        }
    }

    $order = json_decode_safe($attempt['order_data']);
    $qids = isset($order['q']) ? $order['q'] : DB::col('SELECT id FROM {P}questions WHERE quiz_id = :q ORDER BY position, id', ['q' => $quiz['id']]);
    $questions = [];
    foreach ($qids as $qid) {
        $q = DB::find('questions', $qid);
        if (!$q) continue;
        if ($q['type'] !== 'short' && $q['type'] !== 'essay') {
            $oids = isset($order['o'][$qid]) ? $order['o'][$qid] : null;
            if ($oids) {
                $q['options'] = [];
                foreach ($oids as $oid) {
                    $o = DB::find('options', $oid);
                    if ($o) $q['options'][] = $o;
                }
            } else {
                $q['options'] = DB::all('SELECT * FROM {P}options WHERE question_id = :q ORDER BY position, id', ['q' => $qid]);
            }
        } else {
            $q['options'] = [];
        }
        $questions[] = $q;
    }

    $answers = DB::pairs('SELECT question_id, response FROM {P}answers WHERE attempt_id = :a', ['a' => $attempt['id']]);

    view('quiz_attempt', [
        'title' => $item['title'], 'item' => $item, 'course' => $course, 'quiz' => $quiz,
        'attempt' => $attempt, 'questions' => $questions, 'answers' => $answers, 'deadline' => $deadline,
    ], 'blank');
}

/** Lưu tạm câu trả lời (AJAX) */
function quiz_save()
{
    Auth::requireLogin();
    csrf_verify();
    $attempt = DB::find('attempts', inp_int('a'));
    if (!$attempt || (int)$attempt['user_id'] !== Auth::id() || $attempt['status'] !== 'in_progress') {
        json_out(['ok' => false, 'error' => 'Lượt làm bài không hợp lệ.'], 400);
    }
    $qid = inp_int('q');
    $response = inp('v');
    if (is_array($response)) {
        $response = json_encode(array_values($response));
    } else {
        $qtype = DB::val('SELECT type FROM {P}questions WHERE id = :q', ['q' => $qid], '');
        if ($qtype === 'essay') $response = safe_html($response);
    }
    DB::q('INSERT INTO {P}answers (attempt_id, question_id, response) VALUES (:a, :q, :r)
           ON DUPLICATE KEY UPDATE response = VALUES(response)',
          ['a' => $attempt['id'], 'q' => $qid, 'r' => (string)$response]);
    json_out(['ok' => true, 'saved_at' => date('H:i:s')]);
}

function quiz_finish()
{
    Auth::requireLogin();
    csrf_verify();
    $attempt = DB::find('attempts', inp_int('a'));
    if (!$attempt || (int)$attempt['user_id'] !== Auth::id()) { render_404(); return; }
    if ($attempt['status'] !== 'in_progress') redirect(url('quiz/result', ['a' => $attempt['id']]));

    $item = DB::find('items', $attempt['item_id']);
    $quiz = DB::find('quizzes', $attempt['quiz_id']);

    // Ghi nhận toàn bộ câu trả lời gửi kèm
    $posted = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
    foreach ($posted as $qid => $val) {
        if (is_array($val)) {
            $val = json_encode(array_values($val));
        } else {
            $qtype = DB::val('SELECT type FROM {P}questions WHERE id = :q', ['q' => (int)$qid], '');
            if ($qtype === 'essay') $val = safe_html($val);
        }
        DB::q('INSERT INTO {P}answers (attempt_id, question_id, response) VALUES (:a, :q, :r)
               ON DUPLICATE KEY UPDATE response = VALUES(response)',
              ['a' => $attempt['id'], 'q' => (int)$qid, 'r' => (string)$val]);
    }

    quiz_do_finish($attempt, $quiz, $item, false);
    redirect(url('quiz/result', ['a' => $attempt['id']]) . '#success');
}

/** Tính điểm tự động */
function quiz_do_finish($attempt, $quiz, $item, $timeout = false)
{
    $questions = DB::all('SELECT * FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']]);
    $answers = DB::pairs('SELECT question_id, response FROM {P}answers WHERE attempt_id = :a', ['a' => $attempt['id']]);

    $total = 0; $needManual = false;
    foreach ($questions as $q) {
        $resp = isset($answers[$q['id']]) ? $answers[$q['id']] : '';
        $points = (float)$q['points'];
        $score = 0; $graded = 1;

        if ($q['type'] === 'single' || $q['type'] === 'truefalse') {
            $correct = DB::col('SELECT id FROM {P}options WHERE question_id = :q AND correct = 1', ['q' => $q['id']]);
            if ($resp !== '' && in_array((int)$resp, array_map('intval', $correct), true)) $score = $points;

        } elseif ($q['type'] === 'multi') {
            $correct = array_map('intval', DB::col('SELECT id FROM {P}options WHERE question_id = :q AND correct = 1', ['q' => $q['id']]));
            $all = array_map('intval', DB::col('SELECT id FROM {P}options WHERE question_id = :q', ['q' => $q['id']]));
            $picked = array_map('intval', json_decode_safe($resp, []));
            $picked = array_values(array_intersect($picked, $all));
            $nCorrect = count(array_intersect($picked, $correct));
            $nWrong = count(array_diff($picked, $correct));
            if ($correct) {
                $ratio = ($nCorrect - $nWrong) / count($correct);
                $score = round(max(0, $ratio) * $points, 2);
            }

        } elseif ($q['type'] === 'short') {
            $keys = array_filter(array_map('trim', explode('|', (string)$q['answer_key'])));
            $norm = function ($s) {
                return preg_replace('/\s+/u', ' ', mb_strtolower(trim(vn_ascii((string)$s)), 'UTF-8'));
            };
            foreach ($keys as $k) {
                if ($norm($k) !== '' && $norm($k) === $norm($resp)) { $score = $points; break; }
            }

        } else { // essay
            $graded = 0;
            $needManual = true;
            $score = null;
        }

        DB::q('INSERT INTO {P}answers (attempt_id, question_id, response, score, graded) VALUES (:a, :q, :r, :s, :g)
               ON DUPLICATE KEY UPDATE score = VALUES(score), graded = VALUES(graded)',
              ['a' => $attempt['id'], 'q' => $q['id'], 'r' => (string)$resp, 's' => $score, 'g' => $graded]);
        if ($score !== null) $total += $score;
    }

    $maxScore = (float)DB::val('SELECT COALESCE(SUM(points),0) FROM {P}questions WHERE quiz_id = :q', ['q' => $quiz['id']], 0);
    DB::update('attempts', [
        'finished_at' => now(),
        'score'       => round($total, 2),
        'max_score'   => $maxScore,
        'status'      => $needManual ? 'submitted' : 'graded',
    ], 'id = :id', ['id' => $attempt['id']]);

    // Ghi điểm vào sổ theo phương pháp tính điểm
    quiz_sync_grade($quiz, $item, (int)$attempt['user_id']);

    Log::write($timeout ? 'quiz_timeout' : 'quiz_submit', 'item', $item['id'], 'score=' . $total);

    // Chấm câu tự luận bằng AI nếu bật
    if ($needManual && $quiz['ai_enabled'] && Ai::enabled()) {
        quiz_ai_grade_attempt((int)$attempt['id']);
    }
}

/** Quy đổi điểm bài trắc nghiệm về thang điểm của mục và ghi nhận hoàn thành */
function quiz_sync_grade($quiz, $item, $userId)
{
    $rows = DB::all('SELECT score, max_score FROM {P}attempts WHERE quiz_id = :q AND user_id = :u AND status <> "in_progress"',
                    ['q' => $quiz['id'], 'u' => $userId]);
    if (!$rows) return;
    $ratios = [];
    foreach ($rows as $r) {
        $mx = (float)$r['max_score'];
        $ratios[] = $mx > 0 ? (float)$r['score'] / $mx : 0;
    }
    switch ($quiz['grade_method']) {
        case 'last':    $ratio = end($ratios); break;
        case 'first':   $ratio = $ratios[0]; break;
        case 'average': $ratio = array_sum($ratios) / count($ratios); break;
        default:        $ratio = max($ratios);
    }
    $points = round($ratio * (float)$item['max_points'], 2);
    DB::q('INSERT INTO {P}completions (course_id, item_id, user_id, status, score, updated_at)
           VALUES (:c, :i, :u, "completed", :s, :t)
           ON DUPLICATE KEY UPDATE status = "completed", score = VALUES(score), updated_at = VALUES(updated_at)',
          ['c' => (int)$item['course_id'], 'i' => (int)$item['id'], 'u' => $userId, 's' => $points, 't' => now()]);
}

function quiz_result()
{
    Auth::requireLogin();
    $attempt = DB::find('attempts', inp_int('a'));
    if (!$attempt) { render_404(); return; }
    $item = DB::find('items', $attempt['item_id']);
    $course = DB::find('courses', $item['course_id']);
    $canManage = Auth::canManageCourse($course);
    if (!$canManage && (int)$attempt['user_id'] !== Auth::id()) { render_denied(); return; }

    $quiz = DB::find('quizzes', $attempt['quiz_id']);
    $showAnswers = $canManage || $quiz['show_result'] === 'immediate'
        || ($quiz['show_result'] === 'after_close' && $item['close_at'] && strtotime($item['close_at']) < time());

    $order = json_decode_safe($attempt['order_data']);
    $qids = isset($order['q']) ? $order['q'] : DB::col('SELECT id FROM {P}questions WHERE quiz_id = :q ORDER BY position, id', ['q' => $quiz['id']]);
    $questions = [];
    foreach ($qids as $qid) {
        $q = DB::find('questions', $qid);
        if (!$q) continue;
        $q['options'] = DB::all('SELECT * FROM {P}options WHERE question_id = :q ORDER BY position, id', ['q' => $qid]);
        $q['answer'] = DB::row('SELECT * FROM {P}answers WHERE attempt_id = :a AND question_id = :q',
                               ['a' => $attempt['id'], 'q' => $qid]);
        $questions[] = $q;
    }

    $student = DB::find('users', $attempt['user_id']);

    view('quiz_result', [
        'title' => 'Kết quả: ' . $item['title'], 'item' => $item, 'course' => $course,
        'quiz' => $quiz, 'attempt' => $attempt, 'questions' => $questions,
        'showAnswers' => $showAnswers, 'canManage' => $canManage, 'student' => $student,
    ]);
}

/** Giáo viên chấm câu tự luận */
function quiz_mark()
{
    Auth::requireTeacher();
    $attempt = DB::find('attempts', inp_int('a'));
    if (!$attempt) { render_404(); return; }
    $item = DB::find('items', $attempt['item_id']);
    if (!Auth::canManageCourse((int)$item['course_id'])) { Auth::deny(); return; }

    if (is_post()) {
        csrf_verify();
        $scores = isset($_POST['score']) ? $_POST['score'] : [];
        $notes = isset($_POST['note']) ? $_POST['note'] : [];
        $total = 0;
        foreach ($scores as $qid => $sc) {
            $q = DB::find('questions', (int)$qid);
            if (!$q) continue;
            $val = $sc === '' ? null : max(0, min((float)$q['points'], (float)$sc));
            DB::update('answers', [
                'score' => $val,
                'feedback' => isset($notes[$qid]) ? mb_substr((string)$notes[$qid], 0, 2000, 'UTF-8') : null,
                'graded' => $val === null ? 0 : 1,
            ], 'attempt_id = :a AND question_id = :q', ['a' => $attempt['id'], 'q' => (int)$qid]);
        }
        $total = (float)DB::val('SELECT COALESCE(SUM(score),0) FROM {P}answers WHERE attempt_id = :a', ['a' => $attempt['id']], 0);
        $pending = (int)DB::val('SELECT COUNT(*) FROM {P}answers WHERE attempt_id = :a AND graded = 0', ['a' => $attempt['id']], 0);
        DB::update('attempts', ['score' => round($total, 2), 'status' => $pending ? 'submitted' : 'graded'],
                   'id = :id', ['id' => $attempt['id']]);
        $quiz = DB::find('quizzes', $attempt['quiz_id']);
        quiz_sync_grade($quiz, $item, (int)$attempt['user_id']);
        Notify::push($attempt['user_id'], 'Bài trắc nghiệm đã chấm xong: ' . $item['title'],
            'Điểm: ' . score_fmt($total) . '/' . score_fmt($attempt['max_score']),
            url('quiz/result', ['a' => $attempt['id']]), '🏅');
        flash_ok('Đã lưu điểm các câu tự luận. ✅');
        redirect(url('quiz/result', ['a' => $attempt['id']]));
    }
    redirect(url('quiz/result', ['a' => $attempt['id']]));
}

/** Dùng AI chấm các câu tự luận trong một lượt làm bài */
function quiz_ai_grade_attempt($attemptId)
{
    $rows = DB::all('SELECT a.*, q.content, q.points, q.answer_key, q.type
                     FROM {P}answers a JOIN {P}questions q ON q.id = a.question_id
                     WHERE a.attempt_id = :a AND q.type = "essay" AND a.graded = 0', ['a' => (int)$attemptId]);
    foreach ($rows as $r) {
        if (trim((string)$r['response']) === '') continue;
        $res = Ai::gradeEssayAnswer($r, $r['response'], $r['points']);
        if (!empty($res['ok']) && $res['score'] !== null) {
            DB::update('answers', [
                'score' => $res['score'],
                'feedback' => '🤖 ' . mb_substr((string)$res['comment'], 0, 2000, 'UTF-8'),
                'graded' => 1,
            ], 'id = :id', ['id' => $r['id']]);
        }
    }
    $attempt = DB::find('attempts', $attemptId);
    if (!$attempt) return;
    $total = (float)DB::val('SELECT COALESCE(SUM(score),0) FROM {P}answers WHERE attempt_id = :a', ['a' => $attemptId], 0);
    $pending = (int)DB::val('SELECT COUNT(*) FROM {P}answers WHERE attempt_id = :a AND graded = 0', ['a' => $attemptId], 0);
    DB::update('attempts', ['score' => round($total, 2), 'status' => $pending ? 'submitted' : 'graded'],
               'id = :id', ['id' => $attemptId]);
    $item = DB::find('items', $attempt['item_id']);
    $quiz = DB::find('quizzes', $attempt['quiz_id']);
    if ($item && $quiz) quiz_sync_grade($quiz, $item, (int)$attempt['user_id']);
}

function quiz_ai()
{
    Auth::requireTeacher();
    csrf_verify();
    if (!Ai::enabled()) json_out(['ok' => false, 'error' => 'Trợ lý AI chưa được bật.']);
    $attempt = DB::find('attempts', inp_int('id'));
    if (!$attempt) json_out(['ok' => false, 'error' => 'Không tìm thấy lượt làm bài.'], 404);
    $item = DB::find('items', $attempt['item_id']);
    if (!Auth::canManageCourse((int)$item['course_id'])) json_out(['ok' => false, 'error' => 'Không có quyền.'], 403);
    quiz_ai_grade_attempt((int)$attempt['id']);
    $a = DB::find('attempts', $attempt['id']);
    json_out(['ok' => true, 'score' => $a['score'],
              'html' => '<div class="alert alert-success"><span>🤖</span><div>AI đã chấm xong các câu tự luận. '
                        . 'Tổng điểm hiện tại: <b>' . score_fmt($a['score']) . '/' . score_fmt($a['max_score'])
                        . '</b>. Tải lại trang để xem chi tiết.</div></div>']);
}
