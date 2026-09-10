<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Xác thực & phân quyền.
 * Vai trò: admin (quản trị) – teacher (giáo viên) – student (học sinh).
 */
class Auth
{
    private static $user = null;
    private static $checked = false;

    public static function user()
    {
        if (self::$checked) return self::$user;
        self::$checked = true;

        $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$uid && !empty($_COOKIE['lms_remember'])) {
            self::loginByRememberCookie($_COOKIE['lms_remember']);
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        }
        if (!$uid) return null;

        $u = DB::row('SELECT * FROM {P}users WHERE id = :id', ['id' => $uid]);
        if (!$u || $u['status'] === 'locked') {
            self::logout(false);
            return null;
        }
        self::$user = $u;
        return $u;
    }

    public static function id() { $u = self::user(); return $u ? (int)$u['id'] : 0; }
    public static function check() { return self::user() !== null; }
    public static function role() { $u = self::user(); return $u ? $u['role'] : 'guest'; }
    public static function isAdmin() { return self::role() === 'admin'; }
    public static function isTeacher() { return in_array(self::role(), ['teacher', 'admin'], true); }
    public static function isStudent() { return self::role() === 'student'; }
    public static function name() { $u = self::user(); return $u ? $u['full_name'] : 'Khách'; }

    public static function attempt($login, $password, $remember = false)
    {
        $login = trim($login);
        $u = DB::row('SELECT * FROM {P}users WHERE username = :l OR email = :l2 LIMIT 1',
                     ['l' => $login, 'l2' => $login]);
        if (!$u) return [false, 'Tài khoản không tồn tại.'];
        if (!password_verify($password, $u['password'])) return [false, 'Mật khẩu chưa đúng.'];
        if ($u['status'] === 'locked') return [false, 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên.'];
        if ($u['status'] === 'pending') return [false, 'Tài khoản đang chờ quản trị viên phê duyệt.'];

        self::loginUser($u, $remember);
        return [true, ''];
    }

    public static function loginUser($u, $remember = false)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['login_at'] = time();
        self::$user = $u;
        self::$checked = true;

        DB::update('users', [
            'last_login'  => now(),
            'login_count' => (int)$u['login_count'] + 1,
        ], 'id = :id', ['id' => $u['id']]);

        if ($remember) self::setRememberCookie((int)$u['id']);
        Log::write('login', 'user', $u['id']);
    }

    private static function setRememberCookie($uid)
    {
        $days = max(1, Settings::int('remember_days', 30));
        $token = bin2hex(random_bytes(32));
        DB::update('users', [
            'remember_token'   => hash('sha256', $token),
            'remember_expires' => date('Y-m-d H:i:s', time() + $days * 86400),
        ], 'id = :id', ['id' => $uid]);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('lms_remember', $uid . ':' . $token, time() + $days * 86400, base_url(), '', $secure, true);
    }

    private static function loginByRememberCookie($cookie)
    {
        $parts = explode(':', (string)$cookie, 2);
        if (count($parts) !== 2) return;
        list($uid, $token) = $parts;
        $u = DB::row('SELECT * FROM {P}users WHERE id = :id AND status = "active" LIMIT 1', ['id' => (int)$uid]);
        if (!$u || empty($u['remember_token'])) return;
        if (!$u['remember_expires'] || strtotime($u['remember_expires']) < time()) return;
        if (!hash_equals($u['remember_token'], hash('sha256', $token))) return;
        self::loginUser($u, true);
    }

    public static function logout($log = true)
    {
        if ($log && self::id()) Log::write('logout', 'user', self::id());
        if (self::id()) {
            DB::update('users', ['remember_token' => null, 'remember_expires' => null], 'id = :id', ['id' => self::id()]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        setcookie('lms_remember', '', time() - 42000, base_url());
        @session_destroy();
        self::$user = null;
        self::$checked = true;
    }

    public static function hash($password) { return password_hash($password, PASSWORD_DEFAULT); }

    // ------------------------------------------------------------ chặn truy cập
    public static function requireLogin()
    {
        if (!self::check()) {
            if (is_ajax()) json_out(['ok' => false, 'error' => 'Bạn cần đăng nhập.'], 401);
            $_SESSION['redirect_after_login'] = current_url();
            flash_warn('Vui lòng đăng nhập để tiếp tục.');
            redirect(url('auth/login'));
        }
    }

    public static function requireTeacher()
    {
        self::requireLogin();
        if (!self::isTeacher()) self::deny();
    }

    public static function requireAdmin()
    {
        self::requireLogin();
        if (!self::isAdmin()) self::deny();
    }

    public static function deny($msg = 'Bạn không có quyền truy cập chức năng này.')
    {
        if (is_ajax()) json_out(['ok' => false, 'error' => $msg], 403);
        http_response_code(403);
        flash_err($msg);
        redirect(url('dashboard'));
    }

    // ------------------------------------------------------------ quyền trên khoá học
    /** Người dùng có quyền quản lý (sửa nội dung, chấm bài) khoá học không? */
    public static function canManageCourse($course)
    {
        if (!self::check()) return false;
        if (self::isAdmin()) return true;
        $cid = is_array($course) ? (int)$course['id'] : (int)$course;
        $ownerId = is_array($course) ? (int)$course['owner_id'] : (int)DB::val('SELECT owner_id FROM {P}courses WHERE id = :id', ['id' => $cid], 0);
        if ($ownerId === self::id()) return true;
        return DB::exists('course_teachers', 'course_id = :c AND user_id = :u', ['c' => $cid, 'u' => self::id()]);
    }

    /** Học viên đã ghi danh và đang hoạt động? */
    public static function isEnrolled($courseId)
    {
        if (!self::check()) return false;
        return DB::exists('enrollments', 'course_id = :c AND user_id = :u AND status IN ("active","completed")',
            ['c' => (int)$courseId, 'u' => self::id()]);
    }

    /** Có quyền xem nội dung khoá học? */
    public static function canViewCourse($course)
    {
        if (!is_array($course)) $course = DB::find('courses', $course);
        if (!$course) return false;
        if (self::canManageCourse($course)) return true;
        if (!self::check()) return false;
        if ($course['status'] !== 'published') return false;
        return self::isEnrolled($course['id']);
    }
}

/** Ghi nhật ký hoạt động */
class Log
{
    public static function write($action, $target = null, $targetId = null, $detail = null)
    {
        try {
            DB::insert('logs', [
                'user_id'    => Auth::id() ?: null,
                'action'     => substr($action, 0, 64),
                'target'     => $target ? substr($target, 0, 64) : null,
                'target_id'  => $targetId !== null ? (int)$targetId : null,
                'detail'     => $detail !== null ? substr((string)$detail, 0, 60000) : null,
                'ip'         => client_ip(),
                'created_at' => now(),
            ]);
        } catch (Exception $e) { /* không làm gián đoạn ứng dụng */ }
    }
}

/** Thông báo trong hệ thống */
class Notify
{
    public static function push($userId, $title, $body = '', $url = '', $icon = '🔔')
    {
        if (!$userId) return;
        try {
            DB::insert('notifications', [
                'user_id'    => (int)$userId,
                'title'      => mb_substr($title, 0, 250, 'UTF-8'),
                'body'       => $body ? mb_substr($body, 0, 1000, 'UTF-8') : null,
                'url'        => $url ?: null,
                'icon'       => $icon,
                'is_read'    => 0,
                'created_at' => now(),
            ]);
        } catch (Exception $e) {}
    }

    public static function pushMany($userIds, $title, $body = '', $url = '', $icon = '🔔')
    {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid) self::push($uid, $title, $body, $url, $icon);
        }
    }

    public static function unreadCount($userId)
    {
        return (int)DB::val('SELECT COUNT(*) FROM {P}notifications WHERE user_id = :u AND is_read = 0',
            ['u' => (int)$userId], 0);
    }
}
