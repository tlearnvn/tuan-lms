<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Hỗ trợ học liệu / bài tập theo chuẩn SCORM 1.2 và SCORM 2004.
 * Gói .zip được giải nén và lưu toàn bộ vào MySQL; nội dung phát lại qua scorm.php.
 */
class Scorm
{
    /**
     * Nhập gói SCORM từ nội dung zip.
     * @return array ['ok'=>bool, 'error'=>string, 'package_id'=>int]
     */
    public static function import($itemId, $zipBinary, $originalName = 'scorm.zip')
    {
        $entries = Zip::entries($zipBinary);
        if (!$entries) return ['ok' => false, 'error' => 'Tệp không phải ZIP hợp lệ hoặc bị hỏng.'];

        // Tìm imsmanifest.xml (có thể nằm trong thư mục con)
        $manifestPath = null;
        foreach ($entries as $path => $info) {
            if (strtolower(basename($path)) === 'imsmanifest.xml') {
                if ($manifestPath === null || substr_count($path, '/') < substr_count($manifestPath, '/')) {
                    $manifestPath = $path;
                }
            }
        }
        if ($manifestPath === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy imsmanifest.xml — đây không phải gói SCORM.'];
        }
        $rootPrefix = trim(dirname($manifestPath), '.');
        $rootPrefix = ($rootPrefix === '' || $rootPrefix === '/') ? '' : rtrim($rootPrefix, '/') . '/';

        $manifestXml = Zip::extractEntry($zipBinary, $entries[$manifestPath]);
        $info = self::parseManifest($manifestXml);
        if (!$info['launch']) {
            // Dự phòng: tìm index.html / story.html ở gốc gói
            foreach (['index.html', 'index.htm', 'story.html', 'scormdriver/indexAPI.html'] as $cand) {
                if (isset($entries[$rootPrefix . $cand])) { $info['launch'] = $cand; break; }
            }
        }
        if (!$info['launch']) return ['ok' => false, 'error' => 'Không xác định được tệp khởi chạy trong gói SCORM.'];

        // Lưu gói gốc để giáo viên tải lại
        $zipFileId = Storage::saveData($originalName, $zipBinary, 'application/zip', 'auth');

        $pkgId = DB::insert('scorm_packages', [
            'item_id'     => (int)$itemId,
            'file_id'     => $zipFileId,
            'identifier'  => mb_substr((string)$info['identifier'], 0, 250, 'UTF-8'),
            'title'       => mb_substr((string)$info['title'], 0, 250, 'UTF-8'),
            'version'     => $info['version'],
            'launch_url'  => mb_substr($info['launch'], 0, 490, 'UTF-8'),
            'entry_count' => 0,
            'manifest'    => mb_substr((string)$manifestXml, 0, 500000, 'UTF-8'),
            'created_at'  => now(),
        ]);

        // Giải nén và lưu từng tệp
        $count = 0;
        $skipped = 0;
        foreach ($entries as $path => $entry) {
            if (substr($path, -1) === '/') continue;
            if ($rootPrefix !== '' && strpos($path, $rootPrefix) !== 0) continue;
            $rel = $rootPrefix === '' ? $path : substr($path, strlen($rootPrefix));
            if ($rel === '') continue;
            if (preg_match('#(^|/)__MACOSX/#', $path) || basename($path) === '.DS_Store') continue;

            $data = Zip::extractEntry($zipBinary, $entry);
            if ($data === null) { $skipped++; continue; }

            $fid = Storage::saveData(basename($rel), $data, guess_mime($rel), 'auth');
            DB::insert('scorm_files', ['package_id' => $pkgId, 'path' => $rel, 'file_id' => $fid]);
            $count++;
        }

        DB::update('scorm_packages', ['entry_count' => $count], 'id = :id', ['id' => $pkgId]);

        if ($count === 0) {
            self::deletePackage($pkgId);
            return ['ok' => false, 'error' => 'Không giải nén được nội dung gói SCORM.'];
        }

        return ['ok' => true, 'error' => $skipped ? ($skipped . ' tệp dùng kiểu nén không hỗ trợ đã bị bỏ qua.') : '',
                'package_id' => $pkgId, 'files' => $count, 'version' => $info['version'],
                'launch' => $info['launch'], 'items' => $info['items']];
    }

    /** Đọc imsmanifest.xml, trả về phiên bản, tệp khởi chạy và mục lục */
    public static function parseManifest($xml)
    {
        $out = ['identifier' => '', 'title' => '', 'version' => '1.2', 'launch' => '', 'items' => []];
        if (!$xml) return $out;

        // Bỏ tiền tố namespace để truy vấn đơn giản và ổn định
        $clean = preg_replace('/<(\/?)[A-Za-z0-9_.-]+:/', '<$1', $xml);
        $clean = preg_replace('/\s(?:xmlns|xsi)(:[A-Za-z0-9_.-]+)?\s*=\s*"[^"]*"/', '', $clean);
        $clean = preg_replace('/\s[A-Za-z0-9_.-]+:([A-Za-z0-9_.-]+\s*=\s*")/', ' $1', $clean);

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$doc) return $out;

        $out['identifier'] = (string)$doc['identifier'];

        // Phiên bản
        $schemaVersion = '';
        if (isset($doc->metadata->schemaversion)) $schemaVersion = trim((string)$doc->metadata->schemaversion);
        if ($schemaVersion === '' && isset($doc->metadata->schema)) $schemaVersion = trim((string)$doc->metadata->schema);
        if (stripos($schemaVersion, '2004') !== false || stripos($schemaVersion, '1.3') !== false
            || stripos($schemaVersion, 'CAM') !== false) {
            $out['version'] = '2004';
        } elseif (stripos($schemaVersion, '1.1') !== false) {
            $out['version'] = '1.1';
        } else {
            $out['version'] = '1.2';
        }

        $manifestBase = isset($doc['base']) ? (string)$doc['base'] : '';

        // Bảng tài nguyên
        $resources = [];
        if (isset($doc->resources->resource)) {
            foreach ($doc->resources->resource as $res) {
                $id = (string)$res['identifier'];
                $href = (string)$res['href'];
                $base = isset($res['base']) ? (string)$res['base'] : '';
                $resources[$id] = [
                    'href' => $href,
                    'base' => $base,
                    'type' => isset($res['scormtype']) ? strtolower((string)$res['scormtype']) : '',
                ];
            }
        }

        // Mục lục (organization)
        $org = null;
        if (isset($doc->organizations->organization)) {
            $defaultId = (string)$doc->organizations['default'];
            foreach ($doc->organizations->organization as $o) {
                if ($org === null) $org = $o;
                if ($defaultId !== '' && (string)$o['identifier'] === $defaultId) { $org = $o; break; }
            }
        }
        if ($org !== null) {
            $out['title'] = trim((string)$org->title);
            $walk = function ($node, $depth) use (&$walk, &$out, $resources, $manifestBase) {
                if (!isset($node->item)) return;
                foreach ($node->item as $it) {
                    $ref = (string)$it['identifierref'];
                    $href = '';
                    if ($ref !== '' && isset($resources[$ref])) {
                        $r = $resources[$ref];
                        $href = self::joinPath($manifestBase, self::joinPath($r['base'], $r['href']));
                    }
                    $params = (string)$it['parameters'];
                    if ($href !== '' && $params !== '') $href .= $params;
                    $out['items'][] = [
                        'id'    => (string)$it['identifier'],
                        'title' => trim((string)$it->title) ?: 'Nội dung',
                        'href'  => $href,
                        'depth' => $depth,
                        'visible' => !isset($it['isvisible']) || (string)$it['isvisible'] !== 'false',
                    ];
                    if ($href !== '' && $out['launch'] === '') $out['launch'] = $href;
                    $walk($it, $depth + 1);
                }
            };
            $walk($org, 0);
        }

        if ($out['launch'] === '') {
            foreach ($resources as $r) {
                if ($r['href'] !== '' && ($r['type'] === 'sco' || $r['type'] === '')) {
                    $out['launch'] = self::joinPath($manifestBase, self::joinPath($r['base'], $r['href']));
                    break;
                }
            }
        }
        if ($out['title'] === '') $out['title'] = $out['identifier'];
        return $out;
    }

    private static function joinPath($base, $path)
    {
        $base = trim((string)$base);
        $path = trim((string)$path);
        if ($base === '' || $path === '') return Zip::normalizePathKeepQuery($path);
        return Zip::normalizePathKeepQuery(rtrim($base, '/') . '/' . ltrim($path, '/'));
    }

    // ------------------------------------------------------------------ phát nội dung
    public static function package($itemId)
    {
        return DB::row('SELECT * FROM {P}scorm_packages WHERE item_id = :i ORDER BY id DESC LIMIT 1', ['i' => (int)$itemId]);
    }

    public static function fileByPath($packageId, $path)
    {
        $path = Zip::normalizePath($path);
        $row = DB::row('SELECT f.* FROM {P}scorm_files sf JOIN {P}files f ON f.id = sf.file_id
                        WHERE sf.package_id = :p AND sf.path = :path LIMIT 1',
                       ['p' => (int)$packageId, 'path' => $path]);
        if ($row) return $row;
        // Không phân biệt hoa thường (một số gói tạo trên Windows)
        return DB::row('SELECT f.* FROM {P}scorm_files sf JOIN {P}files f ON f.id = sf.file_id
                        WHERE sf.package_id = :p AND LOWER(sf.path) = LOWER(:path) LIMIT 1',
                       ['p' => (int)$packageId, 'path' => $path]);
    }

    public static function deletePackage($packageId)
    {
        $packageId = (int)$packageId;
        $fileIds = DB::col('SELECT file_id FROM {P}scorm_files WHERE package_id = :p', ['p' => $packageId]);
        foreach ($fileIds as $fid) Storage::delete($fid);
        $zip = DB::val('SELECT file_id FROM {P}scorm_packages WHERE id = :p', ['p' => $packageId]);
        if ($zip) Storage::delete($zip);
        DB::delete('scorm_files', 'package_id = :p', ['p' => $packageId]);
        DB::delete('scorm_tracks', 'package_id = :p', ['p' => $packageId]);
        DB::delete('scorm_packages', 'id = :p', ['p' => $packageId]);
    }

    // ------------------------------------------------------------------ dữ liệu học tập (CMI)
    public static function getTracks($packageId, $userId, $sco = 'default')
    {
        return DB::pairs('SELECT element, value FROM {P}scorm_tracks
                          WHERE package_id = :p AND user_id = :u AND sco = :s',
                         ['p' => (int)$packageId, 'u' => (int)$userId, 's' => $sco]);
    }

    public static function saveTracks($packageId, $itemId, $userId, $sco, $data)
    {
        foreach ($data as $element => $value) {
            $element = mb_substr((string)$element, 0, 180, 'UTF-8');
            if ($element === '') continue;
            DB::q('INSERT INTO {P}scorm_tracks (package_id, item_id, user_id, sco, element, value, updated_at)
                   VALUES (:p, :i, :u, :s, :e, :v, :t)
                   ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', [
                'p' => (int)$packageId, 'i' => (int)$itemId, 'u' => (int)$userId,
                's' => mb_substr((string)$sco, 0, 90, 'UTF-8'), 'e' => $element,
                'v' => mb_substr((string)$value, 0, 60000, 'UTF-8'), 't' => now(),
            ]);
        }
        self::syncCompletion($packageId, $itemId, $userId);
    }

    /** Quy đổi dữ liệu CMI thành trạng thái hoàn thành + điểm để vào sổ điểm */
    public static function syncCompletion($packageId, $itemId, $userId)
    {
        $rows = DB::all('SELECT sco, element, value FROM {P}scorm_tracks
                         WHERE package_id = :p AND user_id = :u', ['p' => (int)$packageId, 'u' => (int)$userId]);
        $status = 'in_progress';
        $raw = null; $max = null; $scaled = null;
        foreach ($rows as $r) {
            $el = strtolower($r['element']);
            $v = trim((string)$r['value']);
            if ($el === 'cmi.core.lesson_status' || $el === 'cmi.completion_status') {
                if (in_array(strtolower($v), ['completed', 'passed'], true)) $status = 'completed';
            }
            if ($el === 'cmi.success_status' && in_array(strtolower($v), ['passed'], true)) $status = 'completed';
            if ($el === 'cmi.core.score.raw' || $el === 'cmi.score.raw') { if (is_numeric($v)) $raw = (float)$v; }
            if ($el === 'cmi.core.score.max' || $el === 'cmi.score.max') { if (is_numeric($v)) $max = (float)$v; }
            if ($el === 'cmi.score.scaled') { if (is_numeric($v)) $scaled = (float)$v; }
        }

        $item = DB::row('SELECT id, course_id, max_points, graded FROM {P}items WHERE id = :i', ['i' => (int)$itemId]);
        if (!$item) return;
        $points = null;
        $maxPoints = (float)$item['max_points'];
        if ($scaled !== null) {
            $points = round($scaled * $maxPoints, 2);
        } elseif ($raw !== null) {
            $ref = ($max !== null && $max > 0) ? $max : 100;
            $points = round($raw / $ref * $maxPoints, 2);
        }

        DB::q('INSERT INTO {P}completions (course_id, item_id, user_id, status, score, updated_at)
               VALUES (:c, :i, :u, :s, :sc, :t)
               ON DUPLICATE KEY UPDATE status = VALUES(status), score = VALUES(score), updated_at = VALUES(updated_at)', [
            'c' => (int)$item['course_id'], 'i' => (int)$itemId, 'u' => (int)$userId,
            's' => $status, 'sc' => $points, 't' => now(),
        ]);
    }

    /** Tóm tắt kết quả của học sinh trên một gói */
    public static function summary($packageId, $userId)
    {
        $tracks = DB::pairs('SELECT element, value FROM {P}scorm_tracks
                             WHERE package_id = :p AND user_id = :u ORDER BY id',
                            ['p' => (int)$packageId, 'u' => (int)$userId]);
        $get = function ($keys) use ($tracks) {
            foreach ($keys as $k) if (isset($tracks[$k]) && $tracks[$k] !== '') return $tracks[$k];
            return null;
        };
        return [
            'status'    => $get(['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status']),
            'score'     => $get(['cmi.core.score.raw', 'cmi.score.raw']),
            'score_max' => $get(['cmi.core.score.max', 'cmi.score.max']),
            'time'      => $get(['cmi.core.total_time', 'cmi.total_time', 'cmi.core.session_time']),
            'location'  => $get(['cmi.core.lesson_location', 'cmi.location']),
            'count'     => count($tracks),
        ];
    }

    public static function statusLabel($s)
    {
        $m = ['completed' => 'Đã hoàn thành', 'passed' => 'Đạt', 'failed' => 'Chưa đạt',
              'incomplete' => 'Đang học dở', 'browsed' => 'Đã xem qua', 'not attempted' => 'Chưa bắt đầu',
              'unknown' => 'Chưa xác định'];
        $k = strtolower((string)$s);
        return isset($m[$k]) ? $m[$k] : ($s ? $s : 'Chưa bắt đầu');
    }
}
