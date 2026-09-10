<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Trình quản lý phiên lưu trong MySQL.
 *  - Toàn bộ dữ liệu phiên nằm trong CSDL (không phụ thuộc thư mục tmp của hosting).
 *  - Thời gian sống của phiên do quản trị viên cấu hình (mặc định 12 giờ) nên
 *    học sinh làm bài dài vẫn không bị đăng xuất.
 */
class DbSessionHandler implements SessionHandlerInterface
{
    private $lifetime;

    public function __construct($lifetime) { $this->lifetime = max(600, (int)$lifetime); }

    #[\ReturnTypeWillChange]
    public function open($path, $name) { return true; }

    #[\ReturnTypeWillChange]
    public function close() { return true; }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        try {
            $row = DB::row('SELECT payload, last_activity FROM {P}sessions WHERE id = :id', ['id' => $id]);
            if (!$row) return '';
            if ((int)$row['last_activity'] + $this->lifetime < time()) {
                DB::delete('sessions', 'id = :id', ['id' => $id]);
                return '';
            }
            return (string)$row['payload'];
        } catch (Exception $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        try {
            $uid = null;
            if (preg_match('/user_id\|i:(\d+);/', (string)$data, $m)) $uid = (int)$m[1];
            DB::q('INSERT INTO {P}sessions (id, user_id, ip, ua, payload, last_activity)
                   VALUES (:id, :uid, :ip, :ua, :p, :t)
                   ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip = VALUES(ip),
                       ua = VALUES(ua), payload = VALUES(payload), last_activity = VALUES(last_activity)', [
                'id'  => $id,
                'uid' => $uid,
                'ip'  => client_ip(),
                'ua'  => substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 250),
                'p'   => (string)$data,
                't'   => time(),
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
        try { DB::delete('sessions', 'id = :id', ['id' => $id]); } catch (Exception $e) {}
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime)
    {
        try {
            // Dọn rác nhẹ nhàng: chỉ chạy ngẫu nhiên để không tốn tài nguyên hosting
            DB::q('DELETE FROM {P}sessions WHERE last_activity < :t LIMIT 500', ['t' => time() - $this->lifetime]);
        } catch (Exception $e) {}
        return true;
    }
}

class SessionManager
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $lifetime = Settings::int('session_lifetime', 43200);
        if ($lifetime < 600) $lifetime = 600;

        @ini_set('session.gc_maxlifetime', $lifetime);
        @ini_set('session.gc_probability', 1);
        @ini_set('session.gc_divisor', 200);
        @ini_set('session.use_strict_mode', 1);
        @ini_set('session.cookie_httponly', 1);
        @ini_set('session.use_only_cookies', 1);

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $params = [
            'lifetime' => $lifetime,
            'path'     => base_url(),
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params($lifetime, base_url(), '', $secure, true);
        }

        session_name('lmssid');

        if (DB::connected()) {
            try {
                $handler = new DbSessionHandler($lifetime);
                session_set_save_handler($handler, true);
            } catch (Exception $e) { /* dùng handler mặc định */ }
        }

        @session_start();

        // Gia hạn cookie mỗi lần truy cập để phiên "trượt" theo hoạt động
        if (isset($_COOKIE[session_name()])) {
            if (PHP_VERSION_ID >= 70300) {
                @setcookie(session_name(), session_id(), [
                    'expires'  => time() + $lifetime,
                    'path'     => base_url(),
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                @setcookie(session_name(), session_id(), time() + $lifetime, base_url(), '', $secure, true);
            }
        }
    }

    /** Danh sách phiên đang hoạt động (dành cho trang quản trị) */
    public static function activeSessions($limit = 50)
    {
        $lifetime = Settings::int('session_lifetime', 43200);
        return DB::all('SELECT s.*, u.full_name, u.username, u.role
                        FROM {P}sessions s LEFT JOIN {P}users u ON u.id = s.user_id
                        WHERE s.last_activity > :t ORDER BY s.last_activity DESC LIMIT ' . (int)$limit,
                       ['t' => time() - $lifetime]);
    }
}
