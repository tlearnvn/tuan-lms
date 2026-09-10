<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Đăng nhập, đăng ký, đăng xuất */

function auth_login()
{
    if (Auth::check()) redirect(url('dashboard'));

    $error = '';
    $login = '';
    if (is_post()) {
        csrf_verify();
        $login = inp('login');
        $password = (string)inp('password');
        $remember = inp_bool('remember');
        if ($login === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
        } else {
            list($ok, $msg) = Auth::attempt($login, $password, $remember);
            if ($ok) {
                $to = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : url('dashboard');
                unset($_SESSION['redirect_after_login']);
                flash_ok('Chào mừng <b>' . e(Auth::name()) . '</b> quay lại! 🎉');
                redirect($to);
            }
            $error = $msg;
            Log::write('login_failed', 'user', null, $login);
        }
    }

    view('auth_login', ['title' => 'Đăng nhập', 'error' => $error, 'login' => $login], 'blank');
}

function auth_register()
{
    if (Auth::check()) redirect(url('dashboard'));

    $allowStudent = Settings::bool('allow_student_register', true);
    $allowTeacher = Settings::bool('allow_teacher_register', true);

    if (!$allowStudent && !$allowTeacher) {
        view('auth_closed', ['title' => 'Đăng ký tài khoản'], 'blank');
        return;
    }

    $errors = [];
    $data = ['username' => '', 'email' => '', 'full_name' => '', 'role' => $allowStudent ? 'student' : 'teacher',
             'phone' => '', 'org_unit' => ''];

    if (is_post()) {
        csrf_verify();
        foreach ($data as $k => $_) $data[$k] = inp($k);
        $password = (string)inp('password');
        $password2 = (string)inp('password2');

        if ($data['role'] === 'teacher' && !$allowTeacher) $errors[] = 'Hiện tại hệ thống không mở đăng ký cho giáo viên.';
        if ($data['role'] === 'student' && !$allowStudent) $errors[] = 'Hiện tại hệ thống không mở đăng ký cho học sinh.';
        if (!in_array($data['role'], ['student', 'teacher'], true)) $errors[] = 'Vai trò không hợp lệ.';

        if ($data['full_name'] === '') $errors[] = 'Vui lòng nhập họ và tên.';
        if (!preg_match('/^[a-zA-Z0-9._]{4,32}$/', $data['username'])) {
            $errors[] = 'Tên đăng nhập từ 4–32 ký tự, chỉ gồm chữ cái, số, dấu chấm và gạch dưới.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Địa chỉ email chưa hợp lệ.';
        if (mb_strlen($password) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        if ($password !== $password2) $errors[] = 'Hai lần nhập mật khẩu chưa khớp nhau.';

        if (!$errors) {
            if (DB::exists('users', 'username = :u', ['u' => $data['username']])) $errors[] = 'Tên đăng nhập đã có người dùng.';
            if (DB::exists('users', 'email = :e', ['e' => $data['email']])) $errors[] = 'Email này đã được đăng ký.';
        }

        if (!$errors) {
            $needApproval = ($data['role'] === 'teacher')
                ? Settings::bool('teacher_need_approval', true)
                : Settings::bool('student_need_approval', false);

            $uid = DB::insert('users', [
                'username'   => $data['username'],
                'email'      => $data['email'],
                'password'   => Auth::hash($password),
                'full_name'  => $data['full_name'],
                'role'       => $data['role'],
                'status'     => $needApproval ? 'pending' : 'active',
                'phone'      => $data['phone'] ?: null,
                'org_unit'   => $data['org_unit'] ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Log::write('register', 'user', $uid, $data['role']);

            // Báo cho quản trị viên
            $admins = DB::col('SELECT id FROM {P}users WHERE role = "admin" AND status = "active"');
            Notify::pushMany($admins,
                ($needApproval ? 'Tài khoản chờ duyệt: ' : 'Thành viên mới: ') . $data['full_name'],
                role_label($data['role']) . ' · ' . $data['email'],
                url('admin/users', ['status' => $needApproval ? 'pending' : 'active']),
                $needApproval ? '🕓' : '🙋');

            if ($needApproval) {
                view('auth_pending', ['title' => 'Đăng ký thành công', 'name' => $data['full_name']], 'blank');
                return;
            }
            $u = DB::find('users', $uid);
            Auth::loginUser($u);
            flash_ok('Tạo tài khoản thành công. Chúc bạn học vui! 🎊');
            redirect(url('dashboard') . '#success');
        }
    }

    view('auth_register', [
        'title' => 'Đăng ký tài khoản',
        'errors' => $errors, 'data' => $data,
        'allowStudent' => $allowStudent, 'allowTeacher' => $allowTeacher,
    ], 'blank');
}

function auth_logout()
{
    Auth::logout();
    flash_info('Bạn đã đăng xuất. Hẹn gặp lại! 👋');
    redirect(url('home'));
}
