<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Hồ sơ cá nhân, mật khẩu, thông báo, lịch hạn nộp */

function user_profile()
{
    Auth::requireLogin();
    $u = Auth::user();
    $errors = [];

    if (is_post()) {
        csrf_verify();
        $fullName = inp('full_name');
        $email = inp('email');
        if ($fullName === '') $errors[] = 'Vui lòng nhập họ và tên.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email chưa hợp lệ.';
        if (!$errors && DB::exists('users', 'email = :e AND id <> :id', ['e' => $email, 'id' => $u['id']])) {
            $errors[] = 'Email đã được tài khoản khác sử dụng.';
        }

        $avatarId = (int)$u['avatar_id'];
        if (!$errors && !empty($_FILES['avatar']['name'])) {
            if (!is_image_ext(ext_of($_FILES['avatar']['name']))) {
                $errors[] = 'Ảnh đại diện phải là tệp hình ảnh (jpg, png, gif, webp).';
            } else {
                list($fid, $err) = Storage::saveUpload($_FILES['avatar'], 'public', $u['id']);
                if ($err) $errors[] = $err;
                else { if ($avatarId) Storage::delete($avatarId); $avatarId = $fid; }
            }
        }

        if (!$errors) {
            DB::update('users', [
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => inp('phone') ?: null,
                'birthday'  => inp('birthday') ?: null,
                'gender'    => in_array(inp('gender'), ['male', 'female', 'other'], true) ? inp('gender') : null,
                'org_unit'  => inp('org_unit') ?: null,
                'bio'       => inp('bio') ?: null,
                'avatar_id' => $avatarId ?: null,
                'updated_at' => now(),
            ], 'id = :id', ['id' => $u['id']]);
            flash_ok('Đã cập nhật hồ sơ cá nhân. ✨');
            redirect(url('user/profile'));
        }
    }

    $stats = [
        'courses'     => (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE user_id = :u AND status IN ("active","completed")', ['u' => $u['id']], 0),
        'submissions' => (int)DB::val('SELECT COUNT(*) FROM {P}submissions WHERE user_id = :u AND status <> "draft"', ['u' => $u['id']], 0),
        'completed'   => (int)DB::val('SELECT COUNT(*) FROM {P}completions WHERE user_id = :u AND status = "completed"', ['u' => $u['id']], 0),
    ];

    view('user_profile', ['title' => 'Hồ sơ cá nhân', 'u' => DB::find('users', $u['id']),
                          'errors' => $errors, 'stats' => $stats]);
}

function user_password()
{
    Auth::requireLogin();
    $errors = [];
    if (is_post()) {
        csrf_verify();
        $u = Auth::user();
        $old = (string)inp('old_password');
        $new = (string)inp('new_password');
        $new2 = (string)inp('new_password2');
        if (!password_verify($old, $u['password'])) $errors[] = 'Mật khẩu hiện tại chưa đúng.';
        if (mb_strlen($new) < 6) $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        if ($new !== $new2) $errors[] = 'Hai lần nhập mật khẩu mới chưa khớp.';
        if (!$errors) {
            DB::update('users', ['password' => Auth::hash($new), 'updated_at' => now()], 'id = :id', ['id' => $u['id']]);
            Log::write('change_password', 'user', $u['id']);
            flash_ok('Đã đổi mật khẩu thành công. 🔐');
            redirect(url('user/profile'));
        }
    }
    view('user_password', ['title' => 'Đổi mật khẩu', 'errors' => $errors]);
}

function user_notifications()
{
    Auth::requireLogin();
    if (inp('mark') === 'all') {
        DB::update('notifications', ['is_read' => 1], 'user_id = :u', ['u' => Auth::id()]);
        flash_ok('Đã đánh dấu tất cả là đã đọc.');
        redirect(url('user/notifications'));
    }
    $page = max(1, inp_int('page', 1));
    $per = 25;
    $total = (int)DB::val('SELECT COUNT(*) FROM {P}notifications WHERE user_id = :u', ['u' => Auth::id()], 0);
    $rows = DB::all('SELECT * FROM {P}notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
                    ['u' => Auth::id()]);
    DB::update('notifications', ['is_read' => 1], 'user_id = :u AND is_read = 0', ['u' => Auth::id()]);
    view('user_notifications', ['title' => 'Thông báo', 'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per]);
}

function user_theme()
{
    Auth::requireLogin();
    csrf_verify();
    $theme = inp('theme') === 'dark' ? 'dark' : 'light';
    DB::update('users', ['theme' => $theme], 'id = :id', ['id' => Auth::id()]);
    json_out(['ok' => true, 'theme' => $theme]);
}

/** Lịch bài tập & hạn nộp của học sinh */
function user_deadlines()
{
    Auth::requireLogin();
    $uid = Auth::id();

    $rows = DB::all('SELECT i.*, c.title AS course_title, c.code AS course_code, c.color AS course_color,
                            a.due_at, a.cutoff_at,
                            (SELECT s.status FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u ORDER BY s.id DESC LIMIT 1) AS sub_status,
                            (SELECT s.score FROM {P}submissions s WHERE s.item_id = i.id AND s.user_id = :u2 ORDER BY s.id DESC LIMIT 1) AS sub_score
                     FROM {P}items i
                     JOIN {P}courses c ON c.id = i.course_id
                     LEFT JOIN {P}assignments a ON a.item_id = i.id
                     JOIN {P}enrollments e ON e.course_id = c.id AND e.user_id = :u3 AND e.status IN ("active","completed")
                     WHERE i.type IN ("assignment","quiz") AND i.visible = 1 AND c.status = "published"
                     ORDER BY (a.due_at IS NULL), a.due_at ASC, i.id DESC',
                    ['u' => $uid, 'u2' => $uid, 'u3' => $uid]);

    view('user_deadlines', ['title' => 'Bài tập & hạn nộp', 'rows' => $rows]);
}
