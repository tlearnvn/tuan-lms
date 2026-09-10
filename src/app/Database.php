<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Lớp truy cập MySQL bằng PDO.
 * Trong câu lệnh SQL dùng {P} để thay cho tiền tố bảng, ví dụ: SELECT * FROM {P}users
 */
class DB
{
    /** @var PDO */
    public static $pdo = null;
    public static $prefix = 'lms_';
    public static $queries = 0;
    public static $lastError = '';

    public static function connect($cfg)
    {
        self::$prefix = isset($cfg['prefix']) ? $cfg['prefix'] : 'lms_';
        $host = isset($cfg['host']) ? $cfg['host'] : 'localhost';
        $port = isset($cfg['port']) && $cfg['port'] ? (int)$cfg['port'] : 3306;
        $sock = isset($cfg['socket']) ? $cfg['socket'] : '';
        $name = isset($cfg['name']) ? $cfg['name'] : '';

        if ($sock) {
            $dsn = "mysql:unix_socket={$sock};dbname={$name};charset=utf8mb4";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        }

        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
        // Múi giờ Việt Nam cho mọi hàm NOW(), CURDATE()... của MySQL
        self::$pdo->exec("SET time_zone = '+07:00'");
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        self::$pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        return self::$pdo;
    }

    public static function connected() { return self::$pdo instanceof PDO; }

    public static function raw($sql) { return str_replace('{P}', self::$prefix, $sql); }

    /**
     * @return PDOStatement
     */
    public static function q($sql, $params = [])
    {
        self::$queries++;
        $st = self::$pdo->prepare(self::raw($sql));
        // PDO không thích tham số kiểu bool
        foreach ($params as $k => $v) {
            if (is_bool($v)) $params[$k] = $v ? 1 : 0;
        }
        $st->execute($params);
        return $st;
    }

    public static function all($sql, $params = [])
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function row($sql, $params = [])
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val($sql, $params = [], $default = null)
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function col($sql, $params = [])
    {
        return self::q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Trả về mảng key => value từ 2 cột đầu tiên */
    public static function pairs($sql, $params = [])
    {
        $out = [];
        foreach (self::q($sql, $params)->fetchAll(PDO::FETCH_NUM) as $r) {
            $out[$r[0]] = isset($r[1]) ? $r[1] : null;
        }
        return $out;
    }

    public static function insert($table, $data)
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . self::$prefix . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', array_map(function ($c) { return ':' . $c; }, $cols)) . ')';
        self::$queries++;
        $st = self::$pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(':' . $k, is_bool($v) ? ($v ? 1 : 0) : $v,
                ($v === null ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR)));
        }
        $st->execute();
        return (int)self::$pdo->lastInsertId();
    }

    public static function update($table, $data, $where, $whereParams = [])
    {
        if (!$data) return 0;
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            $sets[] = "`$k` = :s_$k";
            $params['s_' . $k] = is_bool($v) ? ($v ? 1 : 0) : $v;
        }
        foreach ($whereParams as $k => $v) $params[$k] = $v;
        $sql = 'UPDATE `' . self::$prefix . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        self::$queries++;
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function delete($table, $where, $params = [])
    {
        $sql = 'DELETE FROM `' . self::$prefix . $table . '` WHERE ' . $where;
        self::$queries++;
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function exists($table, $where, $params = [])
    {
        return (bool)self::val('SELECT 1 FROM `' . self::$prefix . $table . '` WHERE ' . $where . ' LIMIT 1', $params);
    }

    public static function count($table, $where = '1', $params = [])
    {
        return (int)self::val('SELECT COUNT(*) FROM `' . self::$prefix . $table . '` WHERE ' . $where, $params, 0);
    }

    public static function find($table, $id)
    {
        return self::row('SELECT * FROM `' . self::$prefix . $table . '` WHERE id = :id', ['id' => (int)$id]);
    }

    public static function begin()    { if (!self::$pdo->inTransaction()) self::$pdo->beginTransaction(); }
    public static function commit()   { if (self::$pdo->inTransaction()) self::$pdo->commit(); }
    public static function rollback() { if (self::$pdo->inTransaction()) self::$pdo->rollBack(); }

    public static function tableExists($table)
    {
        try {
            self::$pdo->query('SELECT 1 FROM `' . self::$prefix . $table . '` LIMIT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /** Tạo toàn bộ bảng theo lược đồ */
    public static function migrate()
    {
        require_once __DIR__ . '/schema.php';
        foreach (lms_schema_tables() as $sql) {
            self::$pdo->exec(self::raw($sql));
        }
        // Bổ sung cột còn thiếu khi nâng cấp phiên bản
        self::ensureColumns();
    }

    /** Bổ sung cột cho các bản cài đặt cũ (nâng cấp không mất dữ liệu) */
    public static function ensureColumns()
    {
        $add = [
            'users'       => ['theme' => "VARCHAR(16) NOT NULL DEFAULT 'light'", 'org_unit' => 'VARCHAR(190) NULL'],
            'courses'     => ['color' => "VARCHAR(16) NOT NULL DEFAULT '#6C5CE7'"],
            'submissions' => ['ai_status' => 'VARCHAR(20) NULL', 'ai_at' => 'DATETIME NULL'],
            'quizzes'     => ['ai_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        ];
        foreach ($add as $table => $cols) {
            if (!self::tableExists($table)) continue;
            $existing = [];
            foreach (self::all('SHOW COLUMNS FROM `' . self::$prefix . $table . '`') as $c) {
                $existing[strtolower($c['Field'])] = true;
            }
            foreach ($cols as $col => $def) {
                if (!isset($existing[strtolower($col)])) {
                    try {
                        self::$pdo->exec('ALTER TABLE `' . self::$prefix . $table . '` ADD COLUMN `' . $col . '` ' . $def);
                    } catch (Exception $e) { /* bỏ qua */ }
                }
            }
        }
    }

    /** Dung lượng dữ liệu đang dùng (byte) */
    public static function storageStats()
    {
        try {
            $rows = self::all("SELECT table_name AS t, data_length + index_length AS s
                               FROM information_schema.TABLES
                               WHERE table_schema = DATABASE() AND table_name LIKE :p",
                              ['p' => self::$prefix . '%']);
            $total = 0; $byTable = [];
            foreach ($rows as $r) { $total += (float)$r['s']; $byTable[$r['t']] = (float)$r['s']; }
            return ['total' => $total, 'tables' => $byTable];
        } catch (Exception $e) {
            return ['total' => 0, 'tables' => []];
        }
    }
}
