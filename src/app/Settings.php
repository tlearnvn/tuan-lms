<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Cấu hình hệ thống lưu trong bảng {P}settings – cho phép tuỳ biến tên web,
 * tên đơn vị, logo, footer, đăng ký giáo viên, AI chấm bài, thời gian phiên...
 */
class Settings
{
    private static $data = null;
    private static $loaded = false;

    public static function load($force = false)
    {
        if (self::$loaded && !$force) return self::$data;
        require_once __DIR__ . '/schema.php';
        $defaults = lms_default_settings();
        $data = $defaults;
        try {
            foreach (DB::pairs('SELECT k, v FROM {P}settings') as $k => $v) {
                $data[$k] = $v;
            }
        } catch (Exception $e) {
            // Chưa cài đặt xong – dùng giá trị mặc định
        }
        self::$data = $data;
        self::$loaded = true;
        return self::$data;
    }

    public static function all()
    {
        if (!self::$loaded) self::load();
        return self::$data;
    }

    public static function get($key, $default = null)
    {
        if (!self::$loaded) self::load();
        if (array_key_exists($key, self::$data)) {
            $v = self::$data[$key];
            return ($v === null || $v === '') && $default !== null ? $default : $v;
        }
        return $default;
    }

    public static function bool($key, $default = false)
    {
        $v = self::get($key, $default ? '1' : '0');
        return $v === '1' || $v === 1 || $v === true || $v === 'true';
    }

    public static function int($key, $default = 0)
    {
        $v = self::get($key, null);
        return ($v === null || $v === '') ? $default : (int)$v;
    }

    public static function set($key, $value)
    {
        DB::q('INSERT INTO {P}settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            ['k' => $key, 'v' => (string)$value]);
        if (self::$loaded) self::$data[$key] = (string)$value;
    }

    public static function setMany($pairs)
    {
        foreach ($pairs as $k => $v) self::set($k, $v);
    }

    /** Ghi giá trị mặc định lần đầu cài đặt */
    public static function seedDefaults()
    {
        require_once __DIR__ . '/schema.php';
        foreach (lms_default_settings() as $k => $v) {
            DB::q('INSERT IGNORE INTO {P}settings (k, v) VALUES (:k, :v)', ['k' => $k, 'v' => (string)$v]);
        }
    }
}

/** Hàm tắt cho tiện dùng trong view */
function setting($key, $default = null) { return Settings::get($key, $default); }
function setting_bool($key, $default = false) { return Settings::bool($key, $default); }
function setting_int($key, $default = 0) { return Settings::int($key, $default); }
