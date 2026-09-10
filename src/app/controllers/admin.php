<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Khu vực quản trị hệ thống */
require_once LMS_APP . '/Ai.php';

function admin_index()
{
    Auth::requireAdmin();

    $stats = [
        'users'    => (int)DB::val('SELECT COUNT(*) FROM {P}users', [], 0),
        'students' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "student"', [], 0),
        'teachers' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "teacher"', [], 0),
        'pending'  => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE status = "pending"', [], 0),
        'courses'  => (int)DB::val('SELECT COUNT(*) FROM {P}courses', [], 0),
        'published'=> (int)DB::val('SELECT COUNT(*) FROM {P}courses WHERE status = "published"', [], 0),
        'items'    => (int)DB::val('SELECT COUNT(*) FROM {P}items', [], 0),
        'subs'     => (int)DB::val('SELECT COUNT(*) FROM {P}submissions WHERE status <> "draft"', [], 0),
        'enrolls'  => (int)DB::val('SELECT COUNT(*) FROM {P}enrollments WHERE status = "active"', [], 0),
        'sessions' => (int)DB::val('SELECT COUNT(*) FROM {P}sessions WHERE last_activity > :t',
                                   ['t' => time() - 900], 0),
    ];
    $storage = Storage::stats();
    $dbSize = DB::storageStats();

    $series = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $series[] = [
            'label' => date('d/m', strtotime($d)),
            'value' => (int)DB::val('SELECT COUNT(*) FROM {P}logs WHERE DATE(created_at) = :d AND action = "login"', ['d' => $d], 0),
        ];
    }

    $pendingUsers = DB::all('SELECT * FROM {P}users WHERE status = "pending" ORDER BY id DESC LIMIT 8');
    $recentLogs = DB::all('SELECT l.*, u.full_name FROM {P}logs l LEFT JOIN {P}users u ON u.id = l.user_id
                           ORDER BY l.id DESC LIMIT 12');
    $topCourses = DB::all('SELECT c.id, c.title, c.code,
                            (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = "active") AS n
                           FROM {P}courses c ORDER BY n DESC LIMIT 6');

    view('admin_index', [
        'title' => 'Tổng quan hệ thống', 'stats' => $stats, 'storage' => $storage,
        'dbSize' => $dbSize, 'series' => $series, 'pendingUsers' => $pendingUsers,
        'recentLogs' => $recentLogs, 'topCourses' => $topCourses,
    ]);
}

// ------------------------------------------------------------------ người dùng
function admin_users()
{
    Auth::requireAdmin();

    if (is_post()) {
        csrf_verify();
        $action = inp('action');
        $uid = inp_int('user');
        if ($uid === Auth::id() && in_array($action, ['lock', 'delete'], true)) {
            flash_err('Bạn không thể tự khoá hoặc xoá tài khoản của mình.');
            redirect(url('admin/users'));
        }
        switch ($action) {
            case 'approve':
                DB::update('users', ['status' => 'active'], 'id = :id', ['id' => $uid]);
                Notify::push($uid, 'Tài khoản đã được duyệt 🎉', 'Bạn có thể đăng nhập và bắt đầu sử dụng hệ thống.', url('dashboard'), '✅');
                flash_ok('Đã duyệt tài khoản.');
                break;
            case 'lock':
                DB::update('users', ['status' => 'locked'], 'id = :id', ['id' => $uid]);
                DB::delete('sessions', 'user_id = :u', ['u' => $uid]);
                flash_ok('Đã khoá tài khoản.');
                break;
            case 'unlock':
                DB::update('users', ['status' => 'active'], 'id = :id', ['id' => $uid]);
                flash_ok('Đã mở khoá tài khoản.');
                break;
            case 'delete':
                $u = DB::find('users', $uid);
                if ($u) {
                    if ($u['avatar_id']) Storage::delete($u['avatar_id']);
                    DB::delete('enrollments', 'user_id = :u', ['u' => $uid]);
                    DB::delete('notifications', 'user_id = :u', ['u' => $uid]);
                    DB::delete('sessions', 'user_id = :u', ['u' => $uid]);
                    DB::delete('course_teachers', 'user_id = :u', ['u' => $uid]);
                    DB::delete('users', 'id = :u', ['u' => $uid]);
                    Log::write('user_delete', 'user', $uid, $u['username']);
                    flash_ok('Đã xoá tài khoản ' . e($u['username']) . '.');
                }
                break;
            case 'reset':
                $newPass = random_code(10);
                DB::update('users', ['password' => Auth::hash($newPass)], 'id = :id', ['id' => $uid]);
                flash_ok('Đã đặt lại mật khẩu. Mật khẩu mới: <b>' . e($newPass) . '</b> (hãy gửi cho người dùng).');
                break;
            case 'bulk_approve':
                DB::q('UPDATE {P}users SET status = "active" WHERE status = "pending"');
                flash_ok('Đã duyệt toàn bộ tài khoản đang chờ.');
                break;
        }
        redirect(url('admin/users', ['status' => inp('status'), 'role' => inp('role')]));
    }

    $q = inp('q');
    $role = inp('role');
    $status = inp('status');
    $page = max(1, inp_int('page', 1));
    $per = Settings::int('items_per_page', 20);

    $where = '1'; $params = [];
    if ($q !== '') {
        $where .= ' AND (full_name LIKE :q OR username LIKE :q2 OR email LIKE :q3)';
        $params['q'] = "%$q%"; $params['q2'] = "%$q%"; $params['q3'] = "%$q%";
    }
    if (in_array($role, ['admin', 'teacher', 'student'], true)) { $where .= ' AND role = :r'; $params['r'] = $role; }
    if (in_array($status, ['active', 'pending', 'locked'], true)) { $where .= ' AND status = :s'; $params['s'] = $status; }

    $total = (int)DB::val("SELECT COUNT(*) FROM {P}users WHERE $where", $params, 0);
    $users = DB::all("SELECT * FROM {P}users WHERE $where ORDER BY status = 'pending' DESC, id DESC
                      LIMIT " . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);

    $counts = [
        'all'     => (int)DB::val('SELECT COUNT(*) FROM {P}users', [], 0),
        'pending' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE status = "pending"', [], 0),
        'teacher' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "teacher"', [], 0),
        'student' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "student"', [], 0),
        'locked'  => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE status = "locked"', [], 0),
    ];

    view('admin_users', [
        'title' => 'Quản lý người dùng', 'users' => $users, 'total' => $total,
        'page' => $page, 'per' => $per, 'q' => $q, 'role' => $role, 'status' => $status, 'counts' => $counts,
    ]);
}

function admin_user_edit()
{
    Auth::requireAdmin();
    $id = inp_int('id');
    $user = $id ? DB::find('users', $id) : null;
    if ($id && !$user) { render_404(); return; }
    $errors = [];

    if (is_post()) {
        csrf_verify();
        $data = [
            'full_name' => inp('full_name'),
            'username'  => inp('username'),
            'email'     => inp('email'),
            'role'      => in_array(inp('role'), ['admin', 'teacher', 'student'], true) ? inp('role') : 'student',
            'status'    => in_array(inp('status'), ['active', 'pending', 'locked'], true) ? inp('status') : 'active',
            'phone'     => inp('phone') ?: null,
            'org_unit'  => inp('org_unit') ?: null,
            'updated_at'=> now(),
        ];
        $password = (string)inp('password');

        if ($data['full_name'] === '') $errors[] = 'Vui lòng nhập họ tên.';
        if (!preg_match('/^[a-zA-Z0-9._]{4,32}$/', $data['username'])) $errors[] = 'Tên đăng nhập không hợp lệ.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
        if (DB::exists('users', 'username = :u' . ($user ? ' AND id <> :id' : ''),
            $user ? ['u' => $data['username'], 'id' => $user['id']] : ['u' => $data['username']])) $errors[] = 'Tên đăng nhập đã tồn tại.';
        if (DB::exists('users', 'email = :e' . ($user ? ' AND id <> :id' : ''),
            $user ? ['e' => $data['email'], 'id' => $user['id']] : ['e' => $data['email']])) $errors[] = 'Email đã được sử dụng.';
        if (!$user && mb_strlen($password) < 6) $errors[] = 'Mật khẩu phải từ 6 ký tự.';
        if ($password !== '' && mb_strlen($password) < 6) $errors[] = 'Mật khẩu mới phải từ 6 ký tự.';

        if (!$errors) {
            if ($password !== '') $data['password'] = Auth::hash($password);
            if ($user) {
                DB::update('users', $data, 'id = :id', ['id' => $user['id']]);
                flash_ok('Đã cập nhật tài khoản.');
            } else {
                $data['created_at'] = now();
                $newId = DB::insert('users', $data);
                Log::write('user_create', 'user', $newId, $data['username']);
                flash_ok('Đã tạo tài khoản mới. 🎉');
            }
            redirect(url('admin/users'));
        }
    }

    view('admin_user_edit', ['title' => $user ? 'Sửa tài khoản' : 'Thêm tài khoản', 'user' => $user, 'errors' => $errors]);
}

// ------------------------------------------------------------------ khoá học & danh mục
function admin_courses()
{
    Auth::requireAdmin();
    if (is_post()) {
        csrf_verify();
        $cid = inp_int('course');
        if (inp('action') === 'status') {
            $st = in_array(inp('value'), ['draft', 'published', 'archived'], true) ? inp('value') : 'draft';
            DB::update('courses', ['status' => $st], 'id = :id', ['id' => $cid]);
            flash_ok('Đã đổi trạng thái khoá học.');
        }
        redirect(url('admin/courses'));
    }

    $q = inp('q');
    $where = '1'; $params = [];
    if ($q !== '') { $where .= ' AND (c.title LIKE :q OR c.code LIKE :q2)'; $params['q'] = "%$q%"; $params['q2'] = "%$q%"; }

    $courses = DB::all("SELECT c.*, u.full_name AS owner_name, cat.name AS category_name,
                        (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS students,
                        (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id) AS items
                        FROM {P}courses c
                        LEFT JOIN {P}users u ON u.id = c.owner_id
                        LEFT JOIN {P}categories cat ON cat.id = c.category_id
                        WHERE $where ORDER BY c.id DESC", $params);

    view('admin_courses', ['title' => 'Quản lý khoá học', 'courses' => $courses, 'q' => $q]);
}

function admin_categories()
{
    Auth::requireAdmin();
    if (is_post()) {
        csrf_verify();
        $action = inp('action');
        $id = inp_int('cid');
        if ($action === 'delete' && $id) {
            DB::update('courses', ['category_id' => null], 'category_id = :c', ['c' => $id]);
            DB::delete('categories', 'id = :c', ['c' => $id]);
            flash_ok('Đã xoá danh mục.');
        } else {
            $data = [
                'name'     => inp('name'),
                'color'    => preg_match('/^#[0-9A-Fa-f]{6}$/', inp('color')) ? inp('color') : '#6C5CE7',
                'icon'     => mb_substr(inp('icon', '📚'), 0, 8, 'UTF-8') ?: '📚',
                'position' => inp_int('position'),
            ];
            if ($data['name'] === '') flash_err('Vui lòng nhập tên danh mục.');
            elseif ($id) { DB::update('categories', $data, 'id = :c', ['c' => $id]); flash_ok('Đã cập nhật danh mục.'); }
            else { DB::insert('categories', $data); flash_ok('Đã thêm danh mục mới.'); }
        }
        redirect(url('admin/categories'));
    }

    $rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM {P}courses co WHERE co.category_id = c.id) AS n
                     FROM {P}categories c ORDER BY c.position, c.name');
    view('admin_categories', ['title' => 'Danh mục khoá học', 'rows' => $rows]);
}

// ------------------------------------------------------------------ cấu hình
function admin_settings()
{
    Auth::requireAdmin();

    if (is_post()) {
        csrf_verify();
        $keys = [
            'site_name', 'site_tagline', 'org_name', 'copyright', 'footer_note',
            'contact_email', 'contact_phone', 'contact_address',
            'primary_color', 'accent_color', 'date_format', 'datetime_format',
            'maintenance_message', 'register_note', 'allowed_ext', 'mathjax_url',
        ];
        foreach ($keys as $k) Settings::set($k, inp($k));

        $bools = ['allow_student_register', 'allow_teacher_register', 'teacher_need_approval',
                  'student_need_approval', 'maintenance', 'session_keepalive',
                  'landing_show_courses', 'landing_show_stats',
                  'enable_math', 'math_dollar', 'editor_enabled', 'preview_enabled'];
        foreach ($bools as $k) Settings::set($k, inp_bool($k));

        Settings::set('session_lifetime', max(1800, inp_int('session_lifetime', 43200)));
        Settings::set('remember_days', max(1, inp_int('remember_days', 30)));
        Settings::set('max_upload_mb', max(1, inp_int('max_upload_mb', 64)));
        Settings::set('chunk_size_kb', max(64, min(4096, inp_int('chunk_size_kb', 512))));
        Settings::set('items_per_page', max(5, inp_int('items_per_page', 20)));
        Settings::set('preview_max_mb', max(1, min(512, inp_int('preview_max_mb', 25))));
        Settings::set('grade_scale', inp_float('grade_scale', 10));

        $tz = inp('timezone');
        if (in_array($tz, timezone_identifiers_list(), true)) Settings::set('timezone', $tz);

        // Logo, favicon, ảnh trang chủ
        foreach (['logo' => 'logo_id', 'favicon' => 'favicon_id', 'hero' => 'hero_image_id'] as $field => $key) {
            if (!empty($_FILES[$field]['name'])) {
                if (!is_image_ext(ext_of($_FILES[$field]['name']))) {
                    flash_err('Tệp ' . $field . ' phải là hình ảnh.');
                } else {
                    list($fid, $err) = Storage::saveUpload($_FILES[$field], 'public');
                    if ($err) flash_err($err);
                    else {
                        $old = (int)Settings::get($key, 0);
                        Settings::set($key, $fid);
                        if ($old) Storage::delete($old);
                    }
                }
            }
            if (inp('remove_' . $field) === '1') {
                $old = (int)Settings::get($key, 0);
                if ($old) Storage::delete($old);
                Settings::set($key, '');
            }
        }

        Log::write('settings_update');
        flash_ok('Đã lưu cấu hình hệ thống. ✨');
        redirect(url('admin/settings'));
    }

    view('admin_settings', ['title' => 'Cấu hình hệ thống']);
}

function admin_ai()
{
    Auth::requireAdmin();
    $testResult = null;

    if (is_post()) {
        csrf_verify();
        if (inp('action') === 'test') {
            $override = [
                'provider'   => inp('ai_provider'),
                'endpoint'   => inp('ai_endpoint'),
                'api_key'    => inp('ai_api_key') !== '' ? inp('ai_api_key') : Settings::get('ai_api_key'),
                'model'      => inp('ai_model'),
                'max_tokens' => inp_int('ai_max_tokens', 64000),
                'timeout'    => inp_int('ai_timeout', 300),
                'headers'    => inp('ai_extra_headers'),
                'system'     => inp('ai_system_prompt'),
            ];
            $testResult = Ai::testConnection($override);
        } else {
            Settings::set('ai_enabled', inp_bool('ai_enabled'));
            Settings::set('ai_provider', in_array(inp('ai_provider'), ['openai', 'anthropic', 'gemini', 'custom'], true) ? inp('ai_provider') : 'openai');
            Settings::set('ai_endpoint', trim(inp('ai_endpoint')));
            if (inp('ai_api_key') !== '') Settings::set('ai_api_key', trim(inp('ai_api_key')));
            if (inp('clear_key') === '1') Settings::set('ai_api_key', '');
            Settings::set('ai_model', trim(inp('ai_model')));
            Settings::set('ai_max_tokens', max(256, inp_int('ai_max_tokens', 64000)));
            Settings::set('ai_timeout', max(10, min(3600, inp_int('ai_timeout', 300))));
            Settings::set('ai_temperature', max(0, min(2, inp_float('ai_temperature', 0.2))));
            Settings::set('ai_extra_headers', inp('ai_extra_headers'));
            Settings::set('ai_system_prompt', inp('ai_system_prompt'));
            Settings::set('ai_vision', inp_bool('ai_vision'));
            Settings::set('ai_auto_default', inp_bool('ai_auto_default'));
            Log::write('ai_settings_update');
            flash_ok('Đã lưu cấu hình trợ lý AI. 🤖');
            redirect(url('admin/ai'));
        }
    }

    $jobs = DB::all('SELECT j.*, u.full_name FROM {P}ai_jobs j LEFT JOIN {P}users u ON u.id = j.user_id
                     ORDER BY j.id DESC LIMIT 20');

    // Giữ lại giá trị vừa nhập khi bấm "Kiểm tra kết nối"
    $form = [];
    if ($testResult !== null) {
        foreach (['ai_provider', 'ai_endpoint', 'ai_model', 'ai_max_tokens', 'ai_timeout',
                  'ai_temperature', 'ai_extra_headers', 'ai_system_prompt'] as $k) {
            $form[$k] = inp($k);
        }
        $form['ai_enabled'] = inp_bool('ai_enabled');
        $form['ai_vision'] = inp_bool('ai_vision');
        $form['ai_auto_default'] = inp_bool('ai_auto_default');
    }

    view('admin_ai', ['title' => 'Trợ lý AI chấm bài', 'testResult' => $testResult,
                      'jobs' => $jobs, 'form' => $form]);
}

// ------------------------------------------------------------------ kho dữ liệu
function admin_storage()
{
    Auth::requireAdmin();

    if (is_post()) {
        csrf_verify();
        if (inp('action') === 'clean') {
            $orphans = Storage::orphans(500);
            $n = 0;
            foreach ($orphans as $f) { Storage::delete($f['id']); $n++; }
            Log::write('storage_clean', null, null, $n . ' tệp');
            flash_ok('Đã dọn ' . $n . ' tệp không còn được sử dụng.');
        } elseif (inp('action') === 'clean_sessions') {
            $n = DB::q('DELETE FROM {P}sessions WHERE last_activity < :t',
                       ['t' => time() - Settings::int('session_lifetime', 43200)])->rowCount();
            flash_ok('Đã dọn ' . $n . ' phiên hết hạn.');
        } elseif (inp('action') === 'clean_logs') {
            $n = DB::q('DELETE FROM {P}logs WHERE created_at < :t', ['t' => date('Y-m-d H:i:s', strtotime('-90 days'))])->rowCount();
            flash_ok('Đã xoá ' . $n . ' dòng nhật ký cũ hơn 90 ngày.');
        } elseif (inp('action') === 'delete_file') {
            Storage::delete(inp_int('file'));
            flash_ok('Đã xoá tệp.');
        }
        redirect(url('admin/storage'));
    }

    $stats = Storage::stats();
    $db = DB::storageStats();
    $byType = DB::all('SELECT ext, COUNT(*) AS n, SUM(size) AS s FROM {P}files
                       GROUP BY ext ORDER BY s DESC LIMIT 14');
    $biggest = DB::all('SELECT f.*, u.full_name AS owner_name FROM {P}files f
                        LEFT JOIN {P}users u ON u.id = f.owner_id ORDER BY f.size DESC LIMIT 20');
    $orphans = Storage::orphans(100);
    $sessions = SessionManager::activeSessions(20);

    view('admin_storage', [
        'title' => 'Kho dữ liệu', 'stats' => $stats, 'db' => $db, 'byType' => $byType,
        'biggest' => $biggest, 'orphans' => $orphans, 'sessions' => $sessions,
    ]);
}

function admin_logs()
{
    Auth::requireAdmin();
    $page = max(1, inp_int('page', 1));
    $per = 40;
    $action = inp('action_filter');
    $where = '1'; $params = [];
    if ($action !== '') { $where .= ' AND l.action = :a'; $params['a'] = $action; }

    $total = (int)DB::val("SELECT COUNT(*) FROM {P}logs l WHERE $where", $params, 0);
    $rows = DB::all("SELECT l.*, u.full_name, u.username FROM {P}logs l
                     LEFT JOIN {P}users u ON u.id = l.user_id
                     WHERE $where ORDER BY l.id DESC LIMIT " . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);
    $actions = DB::col('SELECT DISTINCT action FROM {P}logs ORDER BY action');

    view('admin_logs', ['title' => 'Nhật ký hoạt động', 'rows' => $rows, 'total' => $total,
                        'page' => $page, 'per' => $per, 'actions' => $actions, 'action' => $action]);
}

function admin_announce()
{
    Auth::requireAdmin();
    if (is_post()) {
        csrf_verify();
        if (inp('action') === 'delete') {
            DB::delete('announcements', 'id = :a AND course_id IS NULL', ['a' => inp_int('aid')]);
            flash_ok('Đã xoá thông báo.');
        } else {
            $title = inp('title');
            if ($title === '') flash_err('Vui lòng nhập tiêu đề.');
            else {
                DB::insert('announcements', [
                    'course_id' => null, 'user_id' => Auth::id(), 'title' => $title,
                    'content' => inp('content') ?: null, 'pinned' => inp_bool('pinned'), 'created_at' => now(),
                ]);
                if (inp_bool('notify')) {
                    $ids = DB::col('SELECT id FROM {P}users WHERE status = "active"');
                    Notify::pushMany($ids, '📣 ' . $title, str_limit(inp('content'), 120), url('dashboard'), '📣');
                }
                flash_ok('Đã đăng thông báo toàn hệ thống. 📣');
            }
        }
        redirect(url('admin/announce'));
    }
    $rows = DB::all('SELECT a.*, u.full_name AS author FROM {P}announcements a
                     LEFT JOIN {P}users u ON u.id = a.user_id
                     WHERE a.course_id IS NULL ORDER BY a.pinned DESC, a.id DESC');
    view('admin_announce', ['title' => 'Thông báo hệ thống', 'rows' => $rows]);
}
