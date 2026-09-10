<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Kho tệp tin nằm hoàn toàn trong MySQL.
 * Mỗi tệp được cắt thành nhiều mảnh (mặc định 512KB) lưu ở bảng {P}file_chunks
 * để không vượt quá max_allowed_packet của hosting chia sẻ.
 */
class Storage
{
    public static function chunkSize()
    {
        $kb = Settings::int('chunk_size_kb', 512);
        return max(64, min(4096, $kb)) * 1024;
    }

    // ------------------------------------------------------------------ ghi
    /**
     * Lưu nội dung nhị phân vào CSDL.
     * @return int id tệp
     */
    public static function saveData($name, $binary, $mime = null, $visibility = 'auth', $ownerId = null)
    {
        $name = self::cleanName($name);
        $ext  = ext_of($name);
        $mime = $mime ?: guess_mime($name);
        $size = strlen($binary);

        $id = DB::insert('files', [
            'name'       => $name,
            'ext'        => $ext,
            'mime'       => $mime,
            'size'       => $size,
            'sha1'       => sha1($binary),
            'visibility' => $visibility,
            'owner_id'   => $ownerId ?: (Auth::id() ?: null),
            'created_at' => now(),
        ]);

        $chunk = self::chunkSize();
        $seq = 0;
        for ($off = 0; $off < $size || ($size === 0 && $seq === 0); $off += $chunk) {
            $part = substr($binary, $off, $chunk);
            self::writeChunk($id, $seq, $part === false ? '' : $part);
            $seq++;
            if ($size === 0) break;
        }
        return $id;
    }

    /** Lưu tệp tải lên từ form (đọc theo luồng, không nạp cả tệp vào RAM) */
    public static function saveUpload($file, $visibility = 'auth', $ownerId = null, $customName = null)
    {
        if (!is_array($file) || !isset($file['tmp_name'])) return [0, 'Dữ liệu tải lên không hợp lệ.'];
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) return [0, upload_error_message($file['error'])];
        if (!is_uploaded_file($file['tmp_name']) && !is_readable($file['tmp_name'])) return [0, 'Không đọc được tệp tải lên.'];

        $name = self::cleanName($customName ?: $file['name']);
        $ext  = ext_of($name);
        $size = (int)$file['size'];

        $maxMb = Settings::int('max_upload_mb', 64);
        if ($maxMb > 0 && $size > $maxMb * 1024 * 1024) {
            return [0, 'Tệp "' . $name . '" vượt quá giới hạn ' . $maxMb . ' MB.'];
        }

        $allowed = array_filter(array_map('trim', explode(',', (string)Settings::get('allowed_ext', ''))));
        if ($allowed && !in_array($ext, $allowed, true)) {
            return [0, 'Định dạng ".' . $ext . '" không được phép tải lên.'];
        }

        $mime = self::detectMime($file['tmp_name'], $name);

        $id = DB::insert('files', [
            'name'       => $name,
            'ext'        => $ext,
            'mime'       => $mime,
            'size'       => $size,
            'sha1'       => @sha1_file($file['tmp_name']) ?: null,
            'visibility' => $visibility,
            'owner_id'   => $ownerId ?: (Auth::id() ?: null),
            'created_at' => now(),
        ]);

        $fh = fopen($file['tmp_name'], 'rb');
        if (!$fh) { DB::delete('files', 'id = :id', ['id' => $id]); return [0, 'Không mở được tệp tải lên.']; }
        $chunk = self::chunkSize();
        $seq = 0;
        while (!feof($fh)) {
            $data = fread($fh, $chunk);
            if ($data === false) break;
            if ($data === '' && $seq > 0) break;
            self::writeChunk($id, $seq, $data);
            $seq++;
        }
        fclose($fh);
        if ($seq === 0) self::writeChunk($id, 0, '');
        return [$id, ''];
    }

    private static function writeChunk($fileId, $seq, $data)
    {
        $st = DB::$pdo->prepare(DB::raw('INSERT INTO {P}file_chunks (file_id, seq, data) VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE data = VALUES(data)'));
        $st->bindValue(1, (int)$fileId, PDO::PARAM_INT);
        $st->bindValue(2, (int)$seq, PDO::PARAM_INT);
        $st->bindValue(3, $data, PDO::PARAM_LOB);
        $st->execute();
    }

    private static function cleanName($name)
    {
        $name = basename(str_replace('\\', '/', (string)$name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = str_replace(['"', "'", '<', '>'], '', $name);
        if ($name === '' || $name === '.' || $name === '..') $name = 'tep-' . date('YmdHis');
        return mb_substr($name, 0, 250, 'UTF-8');
    }

    private static function detectMime($path, $name)
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = (string)@finfo_file($fi, $path); @finfo_close($fi); }
        }
        $guess = guess_mime($name);
        // Ưu tiên phần mở rộng cho các định dạng Office/zip vì finfo hay trả về application/zip
        if (!$mime || $mime === 'application/octet-stream' || $mime === 'application/zip') {
            if ($guess !== 'application/octet-stream') return $guess;
        }
        return $mime ?: $guess;
    }

    // ------------------------------------------------------------------ đọc
    public static function meta($id)
    {
        return DB::row('SELECT * FROM {P}files WHERE id = :id', ['id' => (int)$id]);
    }

    /** Đọc toàn bộ nội dung (chỉ dùng cho tệp nhỏ / xử lý nội bộ) */
    public static function read($id)
    {
        $out = '';
        $st = DB::q('SELECT data FROM {P}file_chunks WHERE file_id = :f ORDER BY seq ASC', ['f' => (int)$id]);
        while (($row = $st->fetch()) !== false) $out .= $row['data'];
        return $out;
    }

    /** Xuất tệp ra trình duyệt, hỗ trợ HTTP Range để tua video/audio */
    public static function output($file, $forceDownload = false, $cacheSeconds = 604800)
    {
        $id   = (int)$file['id'];
        $size = (int)$file['size'];
        $mime = $file['mime'] ?: 'application/octet-stream';
        $name = $file['name'];
        $chunk = self::chunkSize();

        while (ob_get_level() > 0) ob_end_clean();

        $etag = '"f' . $id . '-' . ($file['sha1'] ?: $size) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=' . (int)$cacheSeconds);
        header('X-Content-Type-Options: nosniff');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        $start = 0; $end = $size - 1; $partial = false;
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') $start = (int)$m[1];
            if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $partial = true;
        }

        $disposition = $forceDownload ? 'attachment' : 'inline';
        // Tên tệp tiếng Việt: dùng RFC 5987
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $name)
            . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Accept-Ranges: bytes');

        if ($partial) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . ($end - $start + 1));

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') exit;

        @set_time_limit(0);
        $firstSeq = (int)floor($start / $chunk);
        $lastSeq  = (int)floor($end / $chunk);

        for ($seq = $firstSeq; $seq <= $lastSeq; $seq++) {
            $data = DB::val('SELECT data FROM {P}file_chunks WHERE file_id = :f AND seq = :s', ['f' => $id, 's' => $seq], '');
            if ($data === null) $data = '';
            $chunkStart = $seq * $chunk;
            $from = max(0, $start - $chunkStart);
            $to   = min(strlen($data) - 1, $end - $chunkStart);
            if ($to < $from) continue;
            echo substr($data, $from, $to - $from + 1);
            if (connection_aborted()) break;
            flush();
        }
        exit;
    }

    // ------------------------------------------------------------------ xoá
    public static function delete($id)
    {
        $id = (int)$id;
        if (!$id) return;
        DB::delete('file_chunks', 'file_id = :f', ['f' => $id]);
        DB::delete('files', 'id = :f', ['f' => $id]);
    }

    // ------------------------------------------------------------------ quyền
    /** Kiểm tra quyền tải/xem một tệp */
    public static function canAccess($file)
    {
        if (!$file) return false;
        if ($file['visibility'] === 'public') return true;
        if (!Auth::check()) return false;
        if (Auth::isAdmin()) return true;
        if ((int)$file['owner_id'] === Auth::id()) return true;
        if ($file['visibility'] === 'auth') return true;

        $fid = (int)$file['id'];
        // Tệp bài nộp: giáo viên phụ trách khoá học được xem
        $courseIds = DB::col('SELECT DISTINCT i.course_id
            FROM {P}submission_files sf
            JOIN {P}submissions s ON s.id = sf.submission_id
            JOIN {P}items i ON i.id = s.item_id
            WHERE sf.file_id = :f', ['f' => $fid]);
        foreach ($courseIds as $cid) {
            if (Auth::canManageCourse((int)$cid)) return true;
        }
        return false;
    }

    // ------------------------------------------------------------------ thống kê
    public static function stats()
    {
        $r = DB::row('SELECT COUNT(*) AS n, COALESCE(SUM(size),0) AS s FROM {P}files');
        return ['count' => (int)$r['n'], 'bytes' => (float)$r['s']];
    }

    /** Tệp không còn được tham chiếu ở bất kỳ đâu */
    public static function orphans($limit = 200)
    {
        return DB::all('SELECT f.* FROM {P}files f
            WHERE NOT EXISTS (SELECT 1 FROM {P}items i WHERE i.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}item_files it WHERE it.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}submission_files sf WHERE sf.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}scorm_files sc WHERE sc.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}scorm_packages sp WHERE sp.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}courses c WHERE c.cover_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}users u WHERE u.avatar_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}questions q WHERE q.image_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM {P}settings s WHERE s.v = CAST(f.id AS CHAR) AND s.k IN ("logo_id","favicon_id","hero_image_id"))
            ORDER BY f.id DESC LIMIT ' . (int)$limit);
    }

    /** Trích xuất văn bản để gửi cho AI chấm bài (docx, txt, html, pdf đơn giản) */
    public static function extractText($file, $maxChars = 120000)
    {
        $ext = strtolower((string)$file['ext']);
        if (in_array($ext, ['txt', 'md', 'csv', 'json', 'xml'], true)) {
            return mb_substr(self::read($file['id']), 0, $maxChars, 'UTF-8');
        }
        if (in_array($ext, ['html', 'htm'], true)) {
            $html = self::read($file['id']);
            $txt = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
            return mb_substr(preg_replace('/\n{3,}/', "\n\n", $txt), 0, $maxChars, 'UTF-8');
        }
        if ($ext === 'docx') {
            $bin = self::read($file['id']);
            $entries = Zip::read($bin);
            $xml = isset($entries['word/document.xml']) ? $entries['word/document.xml'] : '';
            if ($xml === '') return '';
            $xml = preg_replace('~<w:p[ >]~', "\n<w:p ", $xml);
            $xml = preg_replace('~<w:tab[^>]*/>~', "\t", $xml);
            $xml = preg_replace('~<w:br[^>]*/>~', "\n", $xml);
            $txt = html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8');
            $txt = preg_replace('/[ \t]{2,}/', ' ', $txt);
            $txt = preg_replace('/\n{3,}/', "\n\n", trim($txt));
            return mb_substr($txt, 0, $maxChars, 'UTF-8');
        }
        if ($ext === 'pdf') {
            return mb_substr(self::pdfText(self::read($file['id'])), 0, $maxChars, 'UTF-8');
        }
        return '';
    }

    /** Bóc chữ thô từ PDF (các luồng nội dung nén Flate) – đủ cho AI đọc bài làm */
    private static function pdfText($bin)
    {
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $bin, $m)) {
            foreach ($m[1] as $stream) {
                $data = @gzuncompress($stream);
                if ($data === false) $data = @gzinflate(substr($stream, 2));
                if ($data === false) $data = $stream;
                if (!is_string($data)) continue;
                if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $data, $t)) {
                    foreach ($t[0] as $s) {
                        $s = substr($s, 1, -1);
                        $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
                        $text .= $s;
                    }
                    $text .= "\n";
                }
            }
        }
        $text = preg_replace('/\n{3,}/', "\n\n", trim($text));
        return $text;
    }
}
