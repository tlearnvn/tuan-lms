<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Trình tạo PDF thuần PHP có nhúng font TrueType (Identity-H)
 * => xuất bảng điểm, phiếu báo điểm tiếng Việt CÓ DẤU đầy đủ, không cần thư viện ngoài.
 */

/** Bộ đọc font TrueType: lấy chỉ số glyph, độ rộng và dữ liệu để nhúng vào PDF */
class TtfFont
{
    public $data;
    public $tables = [];
    public $unitsPerEm = 1000;
    public $numGlyphs = 0;
    public $bbox = [0, -200, 1000, 900];
    public $ascent = 800;
    public $descent = -200;
    public $capHeight = 700;
    public $italicAngle = 0;
    public $indexToLocFormat = 0;
    private $cmapCache = [];
    private $widths = [];
    private $numberOfHMetrics = 0;
    private $hmtxOffset = 0;
    private $cmapOffset = 0;
    private $cmapFormat = 0;
    private $format4 = null;
    private $format12 = null;

    public function __construct($binary)
    {
        $this->data = $binary;
        $this->parseDirectory();
        $this->parseHead();
        $this->parseHhea();
        $this->parseMaxp();
        $this->parseOs2();
        $this->parsePost();
        $this->prepareCmap();
    }

    public static function fromFile($path)
    {
        $bin = @file_get_contents($path);
        if ($bin === false || strlen($bin) < 12) return null;
        try { return new self($bin); } catch (Exception $e) { return null; }
    }

    private function u8($o)  { return ord($this->data[$o]); }
    private function u16($o) { $v = unpack('n', substr($this->data, $o, 2)); return $v[1]; }
    private function s16($o) { $v = $this->u16($o); return $v >= 0x8000 ? $v - 0x10000 : $v; }
    private function u32($o) { $v = unpack('N', substr($this->data, $o, 4)); return $v[1]; }

    private function parseDirectory()
    {
        $num = $this->u16(4);
        for ($i = 0; $i < $num; $i++) {
            $o = 12 + $i * 16;
            $tag = substr($this->data, $o, 4);
            $this->tables[$tag] = ['offset' => $this->u32($o + 8), 'length' => $this->u32($o + 12)];
        }
    }

    private function has($tag) { return isset($this->tables[$tag]); }
    private function off($tag) { return $this->tables[$tag]['offset']; }

    private function parseHead()
    {
        if (!$this->has('head')) return;
        $o = $this->off('head');
        $this->unitsPerEm = $this->u16($o + 18) ?: 1000;
        $this->bbox = [$this->s16($o + 36), $this->s16($o + 38), $this->s16($o + 40), $this->s16($o + 42)];
        $this->indexToLocFormat = $this->s16($o + 50);
    }

    private function parseHhea()
    {
        if (!$this->has('hhea')) return;
        $o = $this->off('hhea');
        $this->ascent  = $this->s16($o + 4);
        $this->descent = $this->s16($o + 6);
        $this->numberOfHMetrics = $this->u16($o + 34);
        if ($this->has('hmtx')) $this->hmtxOffset = $this->off('hmtx');
    }

    private function parseMaxp()
    {
        if (!$this->has('maxp')) return;
        $this->numGlyphs = $this->u16($this->off('maxp') + 4);
    }

    private function parseOs2()
    {
        if (!$this->has('OS/2')) return;
        $o = $this->off('OS/2');
        $ver = $this->u16($o);
        $ta = $this->s16($o + 68);
        $td = $this->s16($o + 70);
        if ($ta) $this->ascent = $ta;
        if ($td) $this->descent = $td;
        if ($ver >= 2 && $this->tables['OS/2']['length'] >= 90) {
            $ch = $this->s16($o + 88);
            if ($ch) $this->capHeight = $ch;
        } else {
            $this->capHeight = (int)round($this->ascent * 0.86);
        }
    }

    private function parsePost()
    {
        if (!$this->has('post')) return;
        $o = $this->off('post');
        $int = $this->s16($o + 4);
        $frac = $this->u16($o + 6);
        $this->italicAngle = $int + $frac / 65536.0;
    }

    // ------------------------------------------------------------------ cmap
    private function prepareCmap()
    {
        if (!$this->has('cmap')) return;
        $base = $this->off('cmap');
        $n = $this->u16($base + 2);
        $best = null; $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $o = $base + 4 + $i * 8;
            $pid = $this->u16($o);
            $eid = $this->u16($o + 2);
            $sub = $base + $this->u32($o + 4);
            $fmt = $this->u16($sub);
            $score = -1;
            if ($pid == 3 && $eid == 10 && $fmt == 12) $score = 5;
            elseif ($pid == 3 && $eid == 1 && $fmt == 4) $score = 4;
            elseif ($pid == 0 && $fmt == 12) $score = 3;
            elseif ($pid == 0 && $fmt == 4) $score = 2;
            elseif ($fmt == 4 || $fmt == 12) $score = 1;
            if ($score > $bestScore) { $bestScore = $score; $best = [$sub, $fmt]; }
        }
        if (!$best) return;
        list($this->cmapOffset, $this->cmapFormat) = $best;
        if ($this->cmapFormat == 4) $this->loadFormat4();
        elseif ($this->cmapFormat == 12) $this->loadFormat12();
    }

    private function loadFormat4()
    {
        $o = $this->cmapOffset;
        $segX2 = $this->u16($o + 6);
        $seg = (int)($segX2 / 2);
        $endO   = $o + 14;
        $startO = $endO + $segX2 + 2;
        $deltaO = $startO + $segX2;
        $rangeO = $deltaO + $segX2;
        $f = ['seg' => $seg, 'end' => [], 'start' => [], 'delta' => [], 'range' => [], 'rangeO' => $rangeO];
        for ($i = 0; $i < $seg; $i++) {
            $f['end'][$i]   = $this->u16($endO + $i * 2);
            $f['start'][$i] = $this->u16($startO + $i * 2);
            $f['delta'][$i] = $this->u16($deltaO + $i * 2);
            $f['range'][$i] = $this->u16($rangeO + $i * 2);
        }
        $this->format4 = $f;
    }

    private function loadFormat12()
    {
        $o = $this->cmapOffset;
        $nGroups = $this->u32($o + 12);
        $groups = [];
        $max = min($nGroups, 20000);
        for ($i = 0; $i < $max; $i++) {
            $g = $o + 16 + $i * 12;
            $groups[] = [$this->u32($g), $this->u32($g + 4), $this->u32($g + 8)];
        }
        $this->format12 = $groups;
    }

    /** Mã Unicode -> chỉ số glyph */
    public function glyphOf($code)
    {
        if (isset($this->cmapCache[$code])) return $this->cmapCache[$code];
        $gid = 0;
        if ($this->format12 !== null) {
            foreach ($this->format12 as $g) {
                if ($code >= $g[0] && $code <= $g[1]) { $gid = $g[2] + ($code - $g[0]); break; }
            }
        }
        if (!$gid && $this->format4 !== null && $code <= 0xFFFF) {
            $f = $this->format4;
            for ($i = 0; $i < $f['seg']; $i++) {
                if ($f['end'][$i] >= $code) {
                    if ($f['start'][$i] > $code) break;
                    if ($f['range'][$i] == 0) {
                        $gid = ($code + $f['delta'][$i]) & 0xFFFF;
                    } else {
                        $addr = $f['rangeO'] + $i * 2 + $f['range'][$i] + ($code - $f['start'][$i]) * 2;
                        if ($addr + 1 < strlen($this->data)) {
                            $g = $this->u16($addr);
                            $gid = $g ? (($g + $f['delta'][$i]) & 0xFFFF) : 0;
                        }
                    }
                    break;
                }
            }
        }
        $this->cmapCache[$code] = $gid;
        return $gid;
    }

    /** Độ rộng glyph theo đơn vị 1/1000 em */
    public function glyphWidth($gid)
    {
        if (isset($this->widths[$gid])) return $this->widths[$gid];
        $w = 0;
        if ($this->hmtxOffset && $this->numberOfHMetrics > 0) {
            $i = min($gid, $this->numberOfHMetrics - 1);
            $o = $this->hmtxOffset + $i * 4;
            if ($o + 1 < strlen($this->data)) $w = $this->u16($o);
        }
        $w = (int)round($w * 1000 / $this->unitsPerEm);
        $this->widths[$gid] = $w;
        return $w;
    }

    public function scale($v) { return (int)round($v * 1000 / $this->unitsPerEm); }

    /**
     * Bỏ các bảng không cần khi nhúng vào PDF (name, post, GSUB, GPOS, kern, DSIG...)
     * giúp giảm đáng kể dung lượng tệp PDF mà vẫn đúng chuẩn.
     */
    public function embeddable()
    {
        $keep = ['glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'cvt ', 'fpgm', 'prep', 'gasp'];
        $present = [];
        foreach ($keep as $tag) {
            if (isset($this->tables[$tag])) {
                $present[$tag] = substr($this->data, $this->tables[$tag]['offset'], $this->tables[$tag]['length']);
            }
        }
        if (!isset($present['glyf']) || !isset($present['loca'])) return $this->data; // font CFF: nhúng nguyên bản

        ksort($present);
        $num = count($present);
        $searchRange = 16;
        $entrySelector = 0;
        while ($searchRange * 2 <= $num * 16) { $searchRange *= 2; $entrySelector++; }
        $rangeShift = $num * 16 - $searchRange;

        $header = pack('Nnnnn', 0x00010000, $num, $searchRange, $entrySelector, $rangeShift);
        $offset = 12 + $num * 16;
        $dir = ''; $body = '';
        foreach ($present as $tag => $content) {
            $len = strlen($content);
            $padded = $content . str_repeat("\0", (4 - $len % 4) % 4);
            $dir .= $tag . pack('NNN', $this->checksum($padded), $offset, $len);
            $body .= $padded;
            $offset += strlen($padded);
        }
        return $header . $dir . $body;
    }

    private function checksum($s)
    {
        $sum = 0;
        $n = strlen($s) / 4;
        for ($i = 0; $i < $n; $i++) {
            $v = unpack('N', substr($s, $i * 4, 4));
            $sum = ($sum + $v[1]) & 0xFFFFFFFF;
        }
        return $sum;
    }
}

class Pdf
{
    const PT_MM = 2.834645669;

    public $pageWidth, $pageHeight;
    public $marginLeft = 40, $marginRight = 40, $marginTop = 52, $marginBottom = 46;
    public $x = 0, $y = 0;
    public $lineWidth = 0.7;
    public $title = '';
    public $headerCallback = null;
    public $footerCallback = null;
    public $autoBreak = true;

    private $pages = [];
    private $current = -1;
    private $objects = [];
    private $fonts = [];        // key => ['ttf'=>TtfFont,'obj'=>n,'gids'=>[]]
    private $fontKey = 'R';
    private $fontSize = 11;
    private $textColor = '0 0 0 rg';
    private $fillColor = '1 1 1 rg';
    private $drawColor = '0 0 0 RG';
    private $images = [];
    private $pageLabels = [];

    public function __construct($orientation = 'P', $format = 'A4')
    {
        $sizes = [
            'A4' => [595.28, 841.89],
            'A5' => [419.53, 595.28],
            'LETTER' => [612.0, 792.0],
        ];
        $s = isset($sizes[strtoupper($format)]) ? $sizes[strtoupper($format)] : $sizes['A4'];
        if (strtoupper($orientation) === 'L') {
            $this->pageWidth = $s[1]; $this->pageHeight = $s[0];
        } else {
            $this->pageWidth = $s[0]; $this->pageHeight = $s[1];
        }
        $this->loadFonts();
    }

    private function loadFonts()
    {
        $dir = __DIR__ . '/../assets/fonts/';
        $r = TtfFont::fromFile($dir . 'DejaVuSans.ttf');
        $b = TtfFont::fromFile($dir . 'DejaVuSans-Bold.ttf');
        if ($r) $this->fonts['R'] = ['ttf' => $r, 'gids' => [], 'obj' => 0, 'name' => 'DejaVuSans'];
        if ($b) $this->fonts['B'] = ['ttf' => $b, 'gids' => [], 'obj' => 0, 'name' => 'DejaVuSans-Bold'];
        if (!$this->fonts) throw new Exception('Không tìm thấy font trong assets/fonts/ để xuất PDF.');
        if (!isset($this->fonts['B'])) $this->fonts['B'] = $this->fonts['R'];
        if (!isset($this->fonts['R'])) $this->fonts['R'] = $this->fonts['B'];
    }

    public function fontAvailable() { return !empty($this->fonts); }

    // ------------------------------------------------------------------ trang
    public function addPage()
    {
        $this->current++;
        $this->pages[$this->current] = '';
        $this->x = $this->marginLeft;
        $this->y = $this->marginTop;
        $this->applyState();
        if ($this->headerCallback) {
            $cb = $this->headerCallback;
            $cb($this);
        }
    }

    public function pageNo() { return $this->current + 1; }
    public function pageCount() { return count($this->pages); }

    private function out($s) { $this->pages[$this->current] .= $s . "\n"; }

    private function applyState()
    {
        $this->out($this->textColor);
        $this->out($this->drawColor);
        $this->out(sprintf('%.2F w', $this->lineWidth));
    }

    public function contentWidth() { return $this->pageWidth - $this->marginLeft - $this->marginRight; }

    public function checkBreak($h)
    {
        if (!$this->autoBreak) return false;
        if ($this->y + $h > $this->pageHeight - $this->marginBottom) {
            $this->addPage();
            return true;
        }
        return false;
    }

    // ------------------------------------------------------------------ trạng thái vẽ
    public function setFont($style = 'R', $size = null)
    {
        $style = strtoupper($style);
        if ($style === 'BOLD') $style = 'B';
        if (!isset($this->fonts[$style])) $style = 'R';
        $this->fontKey = $style;
        if ($size !== null) $this->fontSize = (float)$size;
    }

    public function setFontSize($size) { $this->fontSize = (float)$size; }
    public function getFontSize() { return $this->fontSize; }

    public function setTextColor($r, $g = null, $b = null)
    {
        list($r, $g, $b) = $this->rgb($r, $g, $b);
        $this->textColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        if ($this->current >= 0) $this->out($this->textColor);
    }

    public function setFillColor($r, $g = null, $b = null)
    {
        list($r, $g, $b) = $this->rgb($r, $g, $b);
        $this->fillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }

    public function setDrawColor($r, $g = null, $b = null)
    {
        list($r, $g, $b) = $this->rgb($r, $g, $b);
        $this->drawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        if ($this->current >= 0) $this->out($this->drawColor);
    }

    public function setLineWidth($w)
    {
        $this->lineWidth = (float)$w;
        if ($this->current >= 0) $this->out(sprintf('%.2F w', $w));
    }

    private function rgb($r, $g, $b)
    {
        if (is_string($r)) {
            $h = ltrim($r, '#');
            if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
            return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
        }
        if ($g === null) return [$r, $r, $r];
        return [$r, $g, $b];
    }

    // ------------------------------------------------------------------ đo & mã hoá chữ
    /** Chuyển chuỗi UTF-8 thành chuỗi hex glyph cho Identity-H */
    private function encode($text)
    {
        $font = &$this->fonts[$this->fontKey];
        $hex = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            $code = $this->uniOrd($ch);
            $gid = $font['ttf']->glyphOf($code);
            if ($gid === 0 && $code !== 32) {
                $gid = $font['ttf']->glyphOf(0x003F); // dấu ? cho ký tự thiếu
            }
            $font['gids'][$gid] = $code;
            $hex .= sprintf('%04X', $gid);
        }
        return $hex;
    }

    private function uniOrd($c)
    {
        $k = mb_convert_encoding($c, 'UCS-4BE', 'UTF-8');
        $v = unpack('N', $k);
        return $v[1];
    }

    public function getStringWidth($text, $size = null)
    {
        $size = $size === null ? $this->fontSize : $size;
        $ttf = $this->fonts[$this->fontKey]['ttf'];
        $w = 0;
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $code = $this->uniOrd(mb_substr($text, $i, 1, 'UTF-8'));
            $w += $ttf->glyphWidth($ttf->glyphOf($code));
        }
        return $w * $size / 1000;
    }

    /** Cắt chuỗi cho vừa độ rộng, thêm dấu … */
    public function fit($text, $maxWidth, $size = null)
    {
        if ($this->getStringWidth($text, $size) <= $maxWidth) return $text;
        $len = mb_strlen($text, 'UTF-8');
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $try = $out . mb_substr($text, $i, 1, 'UTF-8');
            if ($this->getStringWidth($try . '…', $size) > $maxWidth) break;
            $out = $try;
        }
        return rtrim($out) . '…';
    }

    /** Ngắt dòng theo độ rộng */
    public function wrap($text, $maxWidth, $size = null)
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $para) {
            $words = preg_split('/\s+/u', trim($para));
            $line = '';
            foreach ($words as $w) {
                if ($w === '') continue;
                $try = $line === '' ? $w : $line . ' ' . $w;
                if ($this->getStringWidth($try, $size) <= $maxWidth) {
                    $line = $try;
                } else {
                    if ($line !== '') $lines[] = $line;
                    // từ quá dài: cắt cứng
                    while ($this->getStringWidth($w, $size) > $maxWidth && mb_strlen($w, 'UTF-8') > 1) {
                        $cut = '';
                        for ($i = 0; $i < mb_strlen($w, 'UTF-8'); $i++) {
                            $t = $cut . mb_substr($w, $i, 1, 'UTF-8');
                            if ($this->getStringWidth($t, $size) > $maxWidth) break;
                            $cut = $t;
                        }
                        if ($cut === '') break;
                        $lines[] = $cut;
                        $w = mb_substr($w, mb_strlen($cut, 'UTF-8'), null, 'UTF-8');
                    }
                    $line = $w;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    // ------------------------------------------------------------------ vẽ
    /** Viết chữ tại toạ độ (gốc trên–trái, đơn vị point) */
    public function text($x, $y, $txt)
    {
        if ($txt === '' || $txt === null) return;
        $hex = $this->encode($txt);
        $py = $this->pageHeight - $y;
        $res = ($this->fontKey === 'B') ? 'F2' : 'F1';
        $this->out(sprintf('BT /%s %.2F Tf %.2F %.2F Td <%s> Tj ET', $res, $this->fontSize, $x, $py, $hex));
    }

    public function rect($x, $y, $w, $h, $style = 'S')
    {
        $py = $this->pageHeight - $y - $h;
        if ($style === 'F' || $style === 'FD') $this->out($this->fillColor);
        $op = $style === 'F' ? 'f' : ($style === 'FD' ? 'B' : 'S');
        $this->out(sprintf('%.2F %.2F %.2F %.2F re %s', $x, $py, $w, $h, $op));
        if ($style === 'F' || $style === 'FD') $this->out($this->textColor);
    }

    public function roundRect($x, $y, $w, $h, $r, $style = 'F')
    {
        $py = $this->pageHeight - $y - $h;
        $k = 0.5523 * $r;
        if ($style === 'F' || $style === 'FD') $this->out($this->fillColor);
        $s = sprintf('%.2F %.2F m ', $x + $r, $py);
        $s .= sprintf('%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $w - $r, $py, $x + $w - $r + $k, $py, $x + $w, $py + $r - $k, $x + $w, $py + $r);
        $s .= sprintf('%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $w, $py + $h - $r, $x + $w, $py + $h - $r + $k, $x + $w - $r + $k, $py + $h, $x + $w - $r, $py + $h);
        $s .= sprintf('%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $r, $py + $h, $x + $r - $k, $py + $h, $x, $py + $h - $r + $k, $x, $py + $h - $r);
        $s .= sprintf('%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c ', $x, $py + $r, $x, $py + $r - $k, $x + $r - $k, $py, $x + $r, $py);
        $s .= $style === 'F' ? 'f' : ($style === 'FD' ? 'B' : 'S');
        $this->out($s);
        if ($style === 'F' || $style === 'FD') $this->out($this->textColor);
    }

    public function line($x1, $y1, $x2, $y2)
    {
        $this->out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $this->pageHeight - $y1, $x2, $this->pageHeight - $y2));
    }

    /**
     * Ô chữ nhật có chữ bên trong.
     * @param float $w 0 = hết chiều ngang còn lại
     */
    public function cell($w, $h, $txt = '', $border = 0, $ln = 0, $align = 'L', $fill = false)
    {
        if ($w <= 0) $w = $this->pageWidth - $this->marginRight - $this->x;
        if ($fill) $this->rect($this->x, $this->y, $w, $h, 'F');
        if ($border) {
            if ($border === 1 || $border === true) $this->rect($this->x, $this->y, $w, $h, 'S');
            else {
                $b = (string)$border;
                if (strpos($b, 'T') !== false) $this->line($this->x, $this->y, $this->x + $w, $this->y);
                if (strpos($b, 'B') !== false) $this->line($this->x, $this->y + $h, $this->x + $w, $this->y + $h);
                if (strpos($b, 'L') !== false) $this->line($this->x, $this->y, $this->x, $this->y + $h);
                if (strpos($b, 'R') !== false) $this->line($this->x + $w, $this->y, $this->x + $w, $this->y + $h);
            }
        }
        if ($txt !== '' && $txt !== null) {
            $txt = $this->fit((string)$txt, $w - 8);
            $tw = $this->getStringWidth($txt);
            if ($align === 'C')      $tx = $this->x + ($w - $tw) / 2;
            elseif ($align === 'R')  $tx = $this->x + $w - $tw - 4;
            else                     $tx = $this->x + 4;
            $ty = $this->y + ($h + $this->fontSize * 0.72) / 2;
            $this->text($tx, $ty, $txt);
        }
        if ($ln == 1)      { $this->x = $this->marginLeft; $this->y += $h; }
        elseif ($ln == 2)  { $this->y += $h; }
        else               { $this->x += $w; }
    }

    /** Đoạn văn nhiều dòng */
    public function multiCell($w, $lineHeight, $txt, $align = 'L')
    {
        if ($w <= 0) $w = $this->pageWidth - $this->marginRight - $this->x;
        foreach ($this->wrap($txt, $w - 8) as $line) {
            $this->checkBreak($lineHeight);
            $tw = $this->getStringWidth($line);
            if ($align === 'C')     $tx = $this->x + ($w - $tw) / 2;
            elseif ($align === 'R') $tx = $this->x + $w - $tw - 4;
            else                    $tx = $this->x + 4;
            $this->text($tx, $this->y + $lineHeight * 0.72, $line);
            $this->y += $lineHeight;
        }
        $this->x = $this->marginLeft;
    }

    public function ln($h = null) { $this->x = $this->marginLeft; $this->y += ($h === null ? $this->fontSize * 1.5 : $h); }

    /**
     * Bảng dữ liệu có tiêu đề lặp lại ở mỗi trang.
     * @param array $head  ['Cột 1', 'Cột 2'...]
     * @param array $rows  mảng các mảng giá trị
     * @param array $widths độ rộng từng cột (point)
     * @param array $opts  ['align'=>['L','C'...], 'headBg'=>'#6C5CE7', 'zebra'=>true, 'rowHeight'=>18, 'fontSize'=>9]
     */
    public function table($head, $rows, $widths, $opts = [])
    {
        $align   = isset($opts['align']) ? $opts['align'] : [];
        $headBg  = isset($opts['headBg']) ? $opts['headBg'] : '#6C5CE7';
        $zebra   = !isset($opts['zebra']) || $opts['zebra'];
        $rh      = isset($opts['rowHeight']) ? $opts['rowHeight'] : 20;
        $fs      = isset($opts['fontSize']) ? $opts['fontSize'] : 9;
        $headFs  = isset($opts['headFontSize']) ? $opts['headFontSize'] : $fs;

        $drawHead = function () use ($head, $widths, $headBg, $rh, $headFs, $align) {
            $this->setFont('B', $headFs);
            $this->setFillColor($headBg);
            $this->setTextColor(255, 255, 255);
            $this->setDrawColor($headBg);
            $this->x = $this->marginLeft;
            foreach ($head as $i => $h) {
                $this->cell($widths[$i], $rh, $h, 1, 0, 'C', true);
            }
            $this->x = $this->marginLeft;
            $this->y += $rh;
            $this->setTextColor(30, 30, 46);
            $this->setDrawColor('#DCD9F0');
        };

        $drawHead();
        $this->setFont('R', $fs);

        foreach ($rows as $ri => $row) {
            if ($this->y + $rh > $this->pageHeight - $this->marginBottom) {
                $this->addPage();
                $drawHead();
                $this->setFont('R', $fs);
            }
            $fill = $zebra && ($ri % 2 === 1);
            if ($fill) $this->setFillColor('#F6F5FF');
            $this->x = $this->marginLeft;
            foreach ($row as $i => $v) {
                if (!isset($widths[$i])) break;
                $a = isset($align[$i]) ? $align[$i] : 'L';
                $style = 'R';
                if (is_array($v)) {
                    if (isset($v['bold']) && $v['bold']) $style = 'B';
                    if (isset($v['color'])) $this->setTextColor($v['color']);
                    $v = isset($v['v']) ? $v['v'] : '';
                }
                $this->setFont($style, $fs);
                $this->cell($widths[$i], $rh, $v, 1, 0, $a, $fill);
                $this->setTextColor(30, 30, 46);
            }
            $this->x = $this->marginLeft;
            $this->y += $rh;
        }
        $this->setFont('R', $fs);
    }

    // ------------------------------------------------------------------ ảnh
    /** Nhúng ảnh (mọi định dạng GD đọc được sẽ chuyển sang JPEG) */
    public function image($binary, $x, $y, $w, $h = 0)
    {
        if (!$binary) return;
        $key = md5($binary) . '_' . (int)$w;
        if (!isset($this->images[$key])) {
            $info = $this->prepareImage($binary);
            if (!$info) return;
            $this->images[$key] = $info;
        }
        $img = $this->images[$key];
        if ($h <= 0) $h = $w * $img['h'] / max(1, $img['w']);
        $py = $this->pageHeight - $y - $h;
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%s Do Q',
            $w, $h, $x, $py, $img['idx']));
        return [$w, $h];
    }

    private function prepareImage($binary)
    {
        $size = @getimagesizefromstring($binary);
        if (!$size) return null;
        $w = $size[0]; $h = $size[1];
        $jpeg = null;
        if ($size[2] === IMAGETYPE_JPEG) {
            $jpeg = $binary;
        } elseif (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($binary);
            if (!$im) return null;
            // nền trắng cho ảnh trong suốt
            $canvas = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
            imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($canvas, null, 88);
            $jpeg = ob_get_clean();
            imagedestroy($im);
            imagedestroy($canvas);
        }
        if (!$jpeg) return null;
        return ['data' => $jpeg, 'w' => $w, 'h' => $h, 'idx' => count($this->images) + 1];
    }

    // ------------------------------------------------------------------ xuất tệp
    private function addObject($content)
    {
        $this->objects[] = $content;
        return count($this->objects); // số hiệu object bắt đầu từ 1
    }

    public function build()
    {
        $this->objects = [];
        $nObjPages = $this->addObject(''); // 1: /Pages (điền sau)
        $catalog   = $this->addObject("<< /Type /Catalog /Pages $nObjPages 0 R >>");

        // Font
        $fontRefs = [];
        $usedKeys = [];
        foreach (['R' => 'F1', 'B' => 'F2'] as $k => $res) {
            if (!isset($this->fonts[$k])) continue;
            $usedKeys[$k] = $res;
        }
        foreach ($usedKeys as $k => $res) {
            $fontRefs[$res] = $this->buildFont($k);
        }

        // Ảnh
        $imgRefs = [];
        foreach ($this->images as $img) {
            $n = $this->addStream($img['data'], "/Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode", false);
            $imgRefs['I' . $img['idx']] = $n;
        }

        $resParts = [];
        $fr = '';
        foreach ($fontRefs as $res => $obj) $fr .= "/$res $obj 0 R ";
        $resParts[] = '/Font << ' . $fr . '>>';
        if ($imgRefs) {
            $ir = '';
            foreach ($imgRefs as $res => $obj) $ir .= "/$res $obj 0 R ";
            $resParts[] = '/XObject << ' . $ir . '>>';
        }
        $resources = '<< ' . implode(' ', $resParts) . ' /ProcSet [/PDF /Text /ImageB /ImageC] >>';

        $kids = [];
        foreach ($this->pages as $content) {
            $streamObj = $this->addStream($content, '', true);
            $pageObj = $this->addObject(sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>",
                $nObjPages, $this->pageWidth, $this->pageHeight, $resources, $streamObj));
            $kids[] = $pageObj;
        }

        $kidsStr = implode(' 0 R ', $kids) . ' 0 R';
        $this->objects[$nObjPages - 1] = "<< /Type /Pages /Count " . count($kids) . " /Kids [$kidsStr] >>";

        $info = $this->addObject("<< /Title (" . $this->escStr($this->title) . ") /Producer (LMS PHP) /CreationDate (D:" . date('YmdHis') . "+07'00') >>");

        // Lắp ráp
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($this->objects as $i => $obj) {
            $offsets[$i + 1] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xrefPos = strlen($out);
        $n = count($this->objects) + 1;
        $out .= "xref\n0 $n\n0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size $n /Root $catalog 0 R /Info $info 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $out;
    }

    private function addStream($content, $dictExtra = '', $compress = true)
    {
        $filter = '';
        if ($compress && function_exists('gzcompress')) {
            $z = gzcompress($content, 6);
            if ($z !== false && strlen($z) < strlen($content)) { $content = $z; $filter = '/Filter /FlateDecode '; }
        }
        $dict = '<< ' . $filter . ($dictExtra ? $dictExtra . ' ' : '') . '/Length ' . strlen($content) . ' >>';
        return $this->addObject($dict . "\nstream\n" . $content . "\nendstream");
    }

    private function buildFont($key)
    {
        $f = $this->fonts[$key];
        /** @var TtfFont $ttf */
        $ttf = $f['ttf'];
        $gids = $f['gids'];
        if (!$gids) $gids = [0 => 0];
        ksort($gids);

        // Nhúng tệp font (đã lược bớt bảng thừa)
        $raw = $ttf->embeddable();
        $comp = function_exists('gzcompress') ? gzcompress($raw, 6) : $raw;
        $useFlate = ($comp !== false && strlen($comp) < strlen($raw));
        $fontFile = $this->addObject('<< ' . ($useFlate ? '/Filter /FlateDecode ' : '')
            . '/Length ' . strlen($useFlate ? $comp : $raw) . ' /Length1 ' . strlen($raw) . " >>\nstream\n"
            . ($useFlate ? $comp : $raw) . "\nendstream");

        $sc = 1000 / $ttf->unitsPerEm;
        $bbox = sprintf('[%d %d %d %d]',
            round($ttf->bbox[0] * $sc), round($ttf->bbox[1] * $sc),
            round($ttf->bbox[2] * $sc), round($ttf->bbox[3] * $sc));
        $flags = 32; // nonsymbolic
        $descriptor = $this->addObject(sprintf(
            "<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox %s /ItalicAngle %d /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>",
            $f['name'], $flags, $bbox, (int)$ttf->italicAngle,
            round($ttf->ascent * $sc), round($ttf->descent * $sc), round($ttf->capHeight * $sc),
            ($key === 'B' ? 120 : 80), $fontFile));

        // Bảng độ rộng
        $w = '';
        $prev = -10; $group = [];
        $flush = function (&$w, $start, $group) {
            if (!$group) return;
            $w .= $start . ' [' . implode(' ', $group) . '] ';
        };
        $startGid = null;
        foreach (array_keys($gids) as $gid) {
            if ($startGid === null) { $startGid = $gid; $group = [$ttf->glyphWidth($gid)]; $prev = $gid; continue; }
            if ($gid === $prev + 1) { $group[] = $ttf->glyphWidth($gid); }
            else { $flush($w, $startGid, $group); $startGid = $gid; $group = [$ttf->glyphWidth($gid)]; }
            $prev = $gid;
        }
        $flush($w, $startGid, $group);

        // Bản đồ ToUnicode để sao chép / tìm kiếm chữ trong PDF
        $bf = '';
        $count = 0; $chunks = [];
        foreach ($gids as $gid => $code) {
            $bf .= sprintf("<%04X> <%04X>\n", $gid, $code);
            $count++;
            if ($count % 100 === 0) { $chunks[] = $bf; $bf = ''; }
        }
        if ($bf !== '') $chunks[] = $bf;
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
        foreach ($chunks as $c) {
            $n = substr_count($c, "\n");
            $cmap .= "$n beginbfchar\n" . $c . "endbfchar\n";
        }
        $cmap .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $toUni = $this->addStream($cmap, '', true);

        $cidFont = $this->addObject(sprintf(
            "<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /DW 1000 /W [%s] /CIDToGIDMap /Identity >>",
            $f['name'], $descriptor, trim($w)));

        return $this->addObject(sprintf(
            "<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>",
            $f['name'], $cidFont, $toUni));
    }

    private function escStr($s)
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], (string)$s);
    }

    /** Xuất ra trình duyệt: D = tải về, I = xem trực tiếp, S = trả chuỗi */
    public function output($filename = 'tai-lieu.pdf', $dest = 'D')
    {
        $bin = $this->build();
        if ($dest === 'S') return $bin;
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($dest === 'I' ? 'inline' : 'attachment')
            . '; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $filename)
            . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bin;
        exit;
    }
}
