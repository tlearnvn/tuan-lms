<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Đọc/ghi tệp ZIP bằng PHP thuần (không cần extension zip).
 * Dùng cho: giải nén gói SCORM lấy từ CSDL, tạo tệp Excel (.xlsx).
 */
class Zip
{
    /**
     * Đọc toàn bộ tệp zip trong bộ nhớ.
     * @return array [đường dẫn => nội dung]
     */
    public static function read($binary)
    {
        $entries = [];
        foreach (self::entries($binary) as $path => $info) {
            $data = self::extractEntry($binary, $info);
            if ($data !== null) $entries[$path] = $data;
        }
        return $entries;
    }

    /**
     * Liệt kê các mục trong zip mà không giải nén nội dung.
     * @return array [path => ['offset','csize','size','method','crc']]
     */
    public static function entries($binary)
    {
        $entries = [];
        $len = strlen($binary);
        if ($len < 22) return $entries;

        // Tìm End Of Central Directory
        $eocd = -1;
        $max = min($len, 66000);
        for ($i = $len - 22; $i >= $len - $max && $i >= 0; $i--) {
            if (substr($binary, $i, 4) === "PK\x05\x06") { $eocd = $i; break; }
        }
        if ($eocd < 0) return $entries;

        $h = unpack('vdisk/vcddisk/vnument/vtotal/Vcdsize/Vcdoffset/vcomment', substr($binary, $eocd + 4, 18));
        $offset = $h['cdoffset'];
        $count  = $h['total'];

        // Một số zip có phần đệm ở đầu: hiệu chỉnh offset
        if ($offset + $h['cdsize'] !== $eocd && $eocd - $h['cdsize'] >= 0) {
            $offset = $eocd - $h['cdsize'];
        }

        for ($n = 0; $n < $count; $n++) {
            if ($offset + 46 > $len) break;
            if (substr($binary, $offset, 4) !== "PK\x01\x02") break;
            $c = unpack('vver/vminver/vflag/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen/vcommentlen/vdisk/vinattr/Vexattr/Vlocaloffset',
                substr($binary, $offset + 4, 42));
            $name = substr($binary, $offset + 46, $c['namelen']);
            $entries[self::normalizePath($name)] = [
                'offset' => $c['localoffset'],
                'csize'  => $c['csize'],
                'size'   => $c['size'],
                'method' => $c['method'],
                'crc'    => $c['crc'],
                'flag'   => $c['flag'],
            ];
            $offset += 46 + $c['namelen'] + $c['extralen'] + $c['commentlen'];
        }
        return $entries;
    }

    public static function extractEntry($binary, $info)
    {
        $off = $info['offset'];
        if (substr($binary, $off, 4) !== "PK\x03\x04") return null;
        $l = unpack('vver/vflag/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen', substr($binary, $off + 4, 26));
        $dataOff = $off + 30 + $l['namelen'] + $l['extralen'];
        $csize = $info['csize'] ?: $l['csize'];
        $raw = substr($binary, $dataOff, $csize);
        $method = $info['method'];
        if ($method === 0) return $raw;
        if ($method === 8) {
            $out = @gzinflate($raw);
            return $out === false ? '' : $out;
        }
        if ($method === 12 && function_exists('bzdecompress')) {
            $out = @bzdecompress($raw);
            return is_string($out) ? $out : '';
        }
        return null; // phương pháp nén không hỗ trợ
    }

    /** Chuẩn hoá đường dẫn trong zip: bỏ ./, ../, ký tự lạ */
    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', (string)$path);
        $path = preg_replace('#/+#', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $p) {
            if ($p === '' || $p === '.') continue;
            if ($p === '..') { array_pop($parts); continue; }
            $parts[] = $p;
        }
        return implode('/', $parts);
    }

    /** Như normalizePath nhưng giữ nguyên phần ?query (URL khởi chạy SCORM có thể kèm tham số) */
    public static function normalizePathKeepQuery($path)
    {
        $path = (string)$path;
        $q = '';
        $pos = strpos($path, '?');
        if ($pos !== false) { $q = substr($path, $pos); $path = substr($path, 0, $pos); }
        $hash = '';
        $pos = strpos($path, '#');
        if ($pos !== false) { $hash = substr($path, $pos); $path = substr($path, 0, $pos); }
        return self::normalizePath($path) . $q . $hash;
    }

    /**
     * Tạo tệp zip từ mảng [đường dẫn => nội dung].
     * @return string nội dung nhị phân của zip
     */
    public static function create($files)
    {
        $out = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($content);
            $size = strlen($content);
            $comp = gzdeflate($content, 6);
            if ($comp === false || strlen($comp) >= $size) { $comp = $content; $method = 0; }
            else { $method = 8; }
            $csize = strlen($comp);

            $dosTime = self::dosTime(time());

            $local = "PK\x03\x04" . pack('vvvvvVVVvv',
                20, 0, $method, $dosTime['time'], $dosTime['date'], $crc, $csize, $size, strlen($name), 0) . $name;
            $out .= $local . $comp;

            $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV',
                0x0314, 20, 0, $method, $dosTime['time'], $dosTime['date'], $crc, $csize, $size,
                strlen($name), 0, 0, 0, 0, 0x81a40000 & 0xFFFFFFFF, $offset) . $name;

            $offset += strlen($local) + $csize;
            $count++;
        }

        $out .= $central;
        $out .= "PK\x05\x06" . pack('vvvvVVv', 0, 0, $count, $count, strlen($central), $offset, 0);
        return $out;
    }

    private static function dosTime($ts)
    {
        $y = (int)date('Y', $ts);
        if ($y < 1980) $y = 1980;
        return [
            'time' => ((int)date('H', $ts) << 11) | ((int)date('i', $ts) << 5) | ((int)date('s', $ts) >> 1),
            'date' => (($y - 1980) << 9) | ((int)date('n', $ts) << 5) | (int)date('j', $ts),
        ];
    }
}
