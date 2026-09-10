<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Đọc nội dung các định dạng văn phòng (đều là tệp ZIP chứa XML) bằng PHP thuần
 * để xem trước ngay trên web mà không cần cài thêm thư viện hay dịch vụ ngoài.
 *
 *   .docx → HTML (tiêu đề, in đậm/nghiêng, danh sách, bảng, ảnh minh hoạ)
 *   .xlsx → danh sách trang tính, mỗi trang là một mảng hàng × cột
 *   .pptx → danh sách slide kèm tiêu đề và các dòng chữ
 *   .odt / .ods / .odp → bóc chữ theo đoạn
 */
class Office
{
    /** Nạp XML sau khi bỏ tiền tố namespace (w:p → p, r:embed → embed) */
    public static function xml($s)
    {
        if ($s === null || $s === '') return null;
        $clean = preg_replace('/<(\/?)[A-Za-z0-9_.\-]+:/', '<$1', $s);
        $clean = preg_replace('/\s(?:xmlns|xsi)(:[A-Za-z0-9_.\-]+)?\s*=\s*"[^"]*"/', '', $clean);
        $clean = preg_replace('/\s[A-Za-z0-9_.\-]+:([A-Za-z0-9_.\-]+\s*=\s*")/', ' $1', $clean);

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $doc ?: null;
    }

    /** Lấy nội dung một mục trong zip (đã liệt kê sẵn) */
    public static function entry($bin, $entries, $path)
    {
        return isset($entries[$path]) ? Zip::extractEntry($bin, $entries[$path]) : null;
    }

    /** Đọc tệp .rels → [rId => ['target' => …, 'type' => …]] */
    public static function rels($xmlStr)
    {
        $out = [];
        $doc = self::xml($xmlStr);
        if (!$doc) return $out;
        foreach ($doc->Relationship as $r) {
            $out[(string)$r['Id']] = ['target' => (string)$r['Target'], 'type' => (string)$r['Type']];
        }
        return $out;
    }

    // ================================================================= WORD
    /**
     * Chuyển .docx thành HTML gọn.
     * @return string|null null nếu không đọc được
     */
    public static function docxHtml($bin, $opts = [])
    {
        $maxImage = isset($opts['maxImageBytes']) ? (int)$opts['maxImageBytes'] : 800000;
        $entries = Zip::entries($bin);
        $xml = self::entry($bin, $entries, 'word/document.xml');
        if ($xml === null) return null;

        $doc = self::xml($xml);
        if (!$doc) return null;

        $ctx = [
            'bin'      => $bin,
            'entries'  => $entries,
            'rels'     => self::rels(self::entry($bin, $entries, 'word/_rels/document.xml.rels')),
            'maxImage' => $maxImage,
            'imgUsed'  => 0,
        ];
        $body = isset($doc->body) ? $doc->body : $doc;
        return self::docxBlocks($body, $ctx);
    }

    /** Duyệt các khối trong thân tài liệu (đoạn văn, bảng, khối nội dung) */
    private static function docxBlocks($node, &$ctx)
    {
        $html = '';
        $list = null;           // đang gom các gạch đầu dòng liên tiếp
        foreach ($node->children() as $child) {
            $name = $child->getName();
            if ($name === 'sdt') {
                if (isset($child->sdtContent)) {
                    if ($list !== null) { $html .= '<ul class="doc-list">' . $list . '</ul>'; $list = null; }
                    $html .= self::docxBlocks($child->sdtContent, $ctx);
                }
                continue;
            }
            if ($name === 'tbl') {
                if ($list !== null) { $html .= '<ul class="doc-list">' . $list . '</ul>'; $list = null; }
                $html .= self::docxTable($child, $ctx);
                continue;
            }
            if ($name !== 'p') continue;

            $para = self::docxPara($child, $ctx);
            if ($para === null) continue;
            if ($para['list']) {
                $list = ($list === null ? '' : $list) . $para['html'];
            } else {
                if ($list !== null) { $html .= '<ul class="doc-list">' . $list . '</ul>'; $list = null; }
                $html .= $para['html'];
            }
        }
        if ($list !== null) $html .= '<ul class="doc-list">' . $list . '</ul>';
        return $html;
    }

    /** Một đoạn văn → thẻ p / h / li */
    private static function docxPara($p, &$ctx)
    {
        $pPr    = isset($p->pPr) ? $p->pPr : null;
        $style  = $pPr && isset($pPr->pStyle) ? strtolower(str_replace([' ', '-', '_'], '', (string)$pPr->pStyle['val'])) : '';
        $isList = $pPr && isset($pPr->numPr);
        $level  = $isList && isset($pPr->numPr->ilvl) ? (int)$pPr->numPr->ilvl['val'] : 0;
        $align  = $pPr && isset($pPr->jc) ? (string)$pPr->jc['val'] : '';

        $inner = '';
        foreach ($p->children() as $c) {
            $n = $c->getName();
            if ($n === 'r') {
                $inner .= self::docxRun($c, $ctx);
            } elseif ($n === 'hyperlink') {
                $txt = '';
                foreach ($c->children() as $hc) if ($hc->getName() === 'r') $txt .= self::docxRun($hc, $ctx);
                $rid = (string)$c['id'];
                $href = ($rid !== '' && isset($ctx['rels'][$rid])) ? $ctx['rels'][$rid]['target'] : '';
                if ($txt === '') continue;
                $inner .= ($href !== '' && preg_match('~^(https?:|mailto:)~i', $href))
                    ? '<a href="' . e($href) . '" target="_blank" rel="noopener">' . $txt . '</a>' : $txt;
            } elseif ($n === 'ins' || $n === 'smartTag') {
                foreach ($c->children() as $hc) if ($hc->getName() === 'r') $inner .= self::docxRun($hc, $ctx);
            }
        }

        if (trim(strip_tags($inner, '<img><br>')) === '' && strpos($inner, '<img') === false) {
            return $isList ? null : ['list' => false, 'html' => ''];
        }

        $st = $align !== '' && in_array($align, ['center', 'right', 'both'], true)
            ? ' style="text-align:' . ($align === 'both' ? 'justify' : $align) . '"' : '';

        // Tiêu đề xét trước danh sách: nhiều trình soạn thảo đánh số chương bằng numPr
        if (preg_match('/^heading([1-6])$/', $style, $m)) {
            $h = min(6, (int)$m[1] + 2);   // Heading 1 của Word ≈ h3 trong trang
            return ['list' => false, 'html' => '<h' . $h . $st . '>' . $inner . '</h' . $h . '>'];
        }
        if ($isList) {
            $ml = $level > 0 ? ' style="margin-left:' . ($level * 22) . 'px"' : '';
            return ['list' => true, 'html' => '<li' . $ml . '>' . $inner . '</li>'];
        }
        if ($style === 'title') return ['list' => false, 'html' => '<h2' . $st . '>' . $inner . '</h2>'];
        if ($style === 'subtitle') return ['list' => false, 'html' => '<p class="muted"' . $st . '>' . $inner . '</p>'];
        if (strpos($style, 'quote') !== false) return ['list' => false, 'html' => '<blockquote>' . $inner . '</blockquote>'];

        return ['list' => false, 'html' => '<p' . $st . '>' . $inner . '</p>'];
    }

    /** Một "run" chữ → HTML có định dạng */
    private static function docxRun($r, &$ctx)
    {
        $out = '';
        foreach ($r->children() as $c) {
            $n = $c->getName();
            if ($n === 't') {
                $out .= e((string)$c);
            } elseif ($n === 'tab') {
                $out .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            } elseif ($n === 'br' || $n === 'cr') {
                $out .= '<br>';
            } elseif ($n === 'noBreakHyphen') {
                $out .= '-';
            } elseif ($n === 'sym') {
                $code = hexdec((string)$c['char']);
                if ($code > 31) $out .= e(mb_convert_encoding('&#' . $code . ';', 'UTF-8', 'HTML-ENTITIES'));
            } elseif ($n === 'drawing' || $n === 'pict' || $n === 'object') {
                $out .= self::docxImage($c, $ctx);
            }
        }
        if ($out === '') return '';

        $rPr = isset($r->rPr) ? $r->rPr : null;
        if ($rPr) {
            if (isset($rPr->vertAlign)) {
                $va = (string)$rPr->vertAlign['val'];
                if ($va === 'superscript') $out = '<sup>' . $out . '</sup>';
                elseif ($va === 'subscript') $out = '<sub>' . $out . '</sub>';
            } elseif (isset($rPr->position)) {
                // LibreOffice ghi chỉ số trên/dưới bằng w:position thay vì w:vertAlign
                $pos = (int)$rPr->position['val'];
                if ($pos > 0) $out = '<sup>' . $out . '</sup>';
                elseif ($pos < 0) $out = '<sub>' . $out . '</sub>';
            }
            if (isset($rPr->strike) && self::onOff($rPr->strike)) $out = '<s>' . $out . '</s>';
            if (isset($rPr->u) && strtolower((string)$rPr->u['val']) !== 'none') $out = '<u>' . $out . '</u>';
            if (isset($rPr->i) && self::onOff($rPr->i)) $out = '<em>' . $out . '</em>';
            if (isset($rPr->b) && self::onOff($rPr->b)) $out = '<strong>' . $out . '</strong>';
            if (isset($rPr->highlight)) $out = '<mark>' . $out . '</mark>';
        }
        return $out;
    }

    /** Thẻ <w:b/> không có val nghĩa là bật; val="0"/"false" là tắt */
    private static function onOff($node)
    {
        $v = isset($node['val']) ? strtolower((string)$node['val']) : '';
        return !in_array($v, ['0', 'false', 'off'], true);
    }

    /** Ảnh trong tài liệu → thẻ img dạng data URI (bỏ qua ảnh quá lớn) */
    private static function docxImage($node, &$ctx)
    {
        $rid = '';
        foreach ($node->xpath('.//blip') as $b) {
            if (isset($b['embed'])) { $rid = (string)$b['embed']; break; }
        }
        if ($rid === '') {
            foreach ($node->xpath('.//imagedata') as $b) {
                if (isset($b['id'])) { $rid = (string)$b['id']; break; }
            }
        }
        if ($rid === '' || !isset($ctx['rels'][$rid])) return '';

        $target = $ctx['rels'][$rid]['target'];
        if (preg_match('~^https?://~i', $target)) {
            return '<img src="' . e($target) . '" alt="" loading="lazy">';
        }
        $path = Zip::normalizePath(strpos($target, '/') === 0 ? ltrim($target, '/') : 'word/' . $target);
        if (!isset($ctx['entries'][$path])) return '';
        if ((int)$ctx['entries'][$path]['size'] > $ctx['maxImage']) return '';
        if ($ctx['imgUsed'] > 5 * 1024 * 1024) return '';    // tổng dung lượng ảnh nhúng trong một trang

        $ext = ext_of($path);
        $mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                  'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp'];
        if (!isset($mimes[$ext])) return '';

        $data = Zip::extractEntry($ctx['bin'], $ctx['entries'][$path]);
        if ($data === null || $data === '') return '';
        $ctx['imgUsed'] += strlen($data);
        return '<img src="data:' . $mimes[$ext] . ';base64,' . base64_encode($data) . '" alt="" loading="lazy">';
    }

    /** Bảng trong Word */
    private static function docxTable($tbl, &$ctx)
    {
        $rows = '';
        foreach ($tbl->tr as $tr) {
            $cells = '';
            foreach ($tr->tc as $tc) {
                $span = isset($tc->tcPr->gridSpan) ? (int)$tc->tcPr->gridSpan['val'] : 1;
                $inner = self::docxBlocks($tc, $ctx);
                $cells .= '<td' . ($span > 1 ? ' colspan="' . $span . '"' : '') . '>' . ($inner ?: '&nbsp;') . '</td>';
            }
            if ($cells !== '') $rows .= '<tr>' . $cells . '</tr>';
        }
        if ($rows === '') return '';
        return '<div class="doc-table-wrap"><table class="doc-table">' . $rows . '</table></div>';
    }

    // ================================================================ EXCEL
    /**
     * Đọc .xlsx.
     * @return array|null [['name'=>…, 'rows'=>[[ô,…],…], 'more'=>bool], …]
     */
    public static function xlsxSheets($bin, $maxRows = 300, $maxCols = 40)
    {
        $entries = Zip::entries($bin);
        $wbXml = self::entry($bin, $entries, 'xl/workbook.xml');
        if ($wbXml === null) return null;
        $wb = self::xml($wbXml);
        if (!$wb || !isset($wb->sheets)) return null;

        $rels   = self::rels(self::entry($bin, $entries, 'xl/_rels/workbook.xml.rels'));
        $shared = self::xlsxShared(self::entry($bin, $entries, 'xl/sharedStrings.xml'));
        $dates  = self::xlsxDateStyles(self::entry($bin, $entries, 'xl/styles.xml'));

        $out = [];
        $n = 0;
        foreach ($wb->sheets->sheet as $sh) {
            if (++$n > 12) break;
            if (strtolower((string)$sh['state']) === 'hidden') continue;
            $rid = (string)$sh['id'];
            $target = ($rid !== '' && isset($rels[$rid])) ? $rels[$rid]['target'] : '';
            $path = $target === '' ? '' : Zip::normalizePath(strpos($target, '/') === 0 ? ltrim($target, '/') : 'xl/' . $target);
            if ($path === '' || !isset($entries[$path])) continue;

            $data = self::xlsxSheet(self::entry($bin, $entries, $path), $shared, $dates, $maxRows, $maxCols);
            $out[] = ['name' => (string)$sh['name'], 'rows' => $data['rows'], 'more' => $data['more']];
        }
        return $out;
    }

    /** Bảng chuỗi dùng chung */
    private static function xlsxShared($xmlStr)
    {
        $out = [];
        $doc = self::xml($xmlStr);
        if (!$doc) return $out;
        foreach ($doc->si as $si) {
            $s = '';
            if (isset($si->t)) $s = (string)$si->t;
            if (isset($si->r)) { $s = ''; foreach ($si->r as $r) $s .= (string)$r->t; }
            $out[] = $s;
        }
        return $out;
    }

    /** Chỉ số kiểu ô nào đang định dạng ngày/giờ */
    private static function xlsxDateStyles($xmlStr)
    {
        $out = [];
        $doc = self::xml($xmlStr);
        if (!$doc) return $out;

        $builtin = [14 => 'd', 15 => 'd', 16 => 'd', 17 => 'd', 22 => 'dt',
                    18 => 't', 19 => 't', 20 => 't', 21 => 't', 45 => 't', 46 => 't', 47 => 't'];
        $custom = [];
        if (isset($doc->numFmts)) {
            foreach ($doc->numFmts->numFmt as $f) {
                $code = strtolower((string)$f['formatCode']);
                if (preg_match('/[dy]/', $code) || strpos($code, 'h') !== false) {
                    $custom[(int)$f['numFmtId']] = (strpos($code, 'y') !== false || strpos($code, 'd') !== false)
                        ? (strpos($code, 'h') !== false ? 'dt' : 'd') : 't';
                }
            }
        }
        if (!isset($doc->cellXfs)) return $out;
        $i = 0;
        foreach ($doc->cellXfs->xf as $xf) {
            $id = (int)$xf['numFmtId'];
            if (isset($builtin[$id])) $out[$i] = $builtin[$id];
            elseif (isset($custom[$id])) $out[$i] = $custom[$id];
            $i++;
        }
        return $out;
    }

    /** Một trang tính → mảng hàng */
    private static function xlsxSheet($xmlStr, $shared, $dates, $maxRows, $maxCols)
    {
        $rows = [];
        $more = false;
        $doc = self::xml($xmlStr);
        if (!$doc || !isset($doc->sheetData)) return ['rows' => $rows, 'more' => $more];

        $count = 0;
        foreach ($doc->sheetData->row as $row) {
            if ($count >= $maxRows) { $more = true; break; }
            $line = [];
            foreach ($row->c as $c) {
                $col = self::colIndex((string)$c['r']);
                if ($col >= $maxCols) { $more = true; continue; }
                $line[$col] = self::xlsxCell($c, $shared, $dates);
            }
            if ($line) {
                $max = max(array_keys($line));
                $full = [];
                for ($i = 0; $i <= $max; $i++) $full[] = isset($line[$i]) ? $line[$i] : '';
                $rows[] = $full;
            } else {
                $rows[] = [];
            }
            $count++;
        }
        // bỏ các hàng trống ở cuối
        while ($rows && trim(implode('', end($rows))) === '') array_pop($rows);
        return ['rows' => $rows, 'more' => $more];
    }

    private static function xlsxCell($c, $shared, $dates)
    {
        $t = (string)$c['t'];
        if ($t === 's') {
            $i = (int)$c->v;
            return isset($shared[$i]) ? $shared[$i] : '';
        }
        if ($t === 'inlineStr') {
            if (isset($c->is->t)) return (string)$c->is->t;
            $s = '';
            if (isset($c->is->r)) foreach ($c->is->r as $r) $s .= (string)$r->t;
            return $s;
        }
        if ($t === 'b') return ((string)$c->v === '1') ? 'TRUE' : 'FALSE';
        if ($t === 'str' || $t === 'e') return (string)$c->v;

        $v = (string)$c->v;
        if ($v === '') return '';
        $style = isset($c['s']) ? (int)$c['s'] : -1;
        if (isset($dates[$style]) && is_numeric($v) && (float)$v > 0) {
            $ts = ((float)$v - 25569) * 86400;
            $fmt = $dates[$style] === 't' ? 'H:i:s' : ($dates[$style] === 'dt' ? 'd/m/Y H:i' : 'd/m/Y');
            return gmdate($fmt, (int)round($ts));
        }
        return $v;
    }

    /** "B7" → 1 (chỉ số cột bắt đầu từ 0) */
    public static function colIndex($ref)
    {
        $n = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = strtoupper($ref[$i]);
            if ($ch < 'A' || $ch > 'Z') break;
            $n = $n * 26 + (ord($ch) - 64);
        }
        return max(0, $n - 1);
    }

    // =========================================================== POWERPOINT
    /**
     * Đọc .pptx.
     * @return array|null [['title'=>…, 'lines'=>[…], 'notes'=>…], …]
     */
    public static function pptxSlides($bin, $maxSlides = 80)
    {
        $entries = Zip::entries($bin);
        $paths = [];
        foreach (array_keys($entries) as $p) {
            if (preg_match('~^ppt/slides/slide(\d+)\.xml$~', $p, $m)) $paths[(int)$m[1]] = $p;
        }
        if (!$paths) return null;
        ksort($paths, SORT_NUMERIC);

        $out = [];
        foreach ($paths as $no => $p) {
            if (count($out) >= $maxSlides) break;
            $doc = self::xml(self::entry($bin, $entries, $p));
            if (!$doc) { $out[] = ['no' => $no, 'title' => '', 'lines' => []]; continue; }

            $shapes = [];
            foreach ($doc->xpath('//sp') as $si => $sp) {
                $ph = $sp->xpath('.//nvSpPr/nvPr/ph');
                $type = ($ph && isset($ph[0]['type'])) ? strtolower((string)$ph[0]['type']) : '';
                $texts = [];
                foreach ($sp->xpath('.//txBody/p') as $para) {
                    $s = '';
                    foreach ($para->xpath('.//t') as $t) $s .= (string)$t;
                    $s = trim($s);
                    if ($s !== '') $texts[] = $s;
                }
                if (!$texts) continue;
                $off = $sp->xpath('.//spPr/xfrm/off');
                $shapes[] = [
                    'ph'    => $type,
                    'y'     => ($off && isset($off[0]['y'])) ? (float)$off[0]['y'] : 1e12,
                    'order' => $si,
                    'lines' => $texts,
                ];
            }

            // Tiêu đề: ưu tiên ô giữ chỗ "title"; nếu tệp không đánh dấu (hay gặp ở
            // bản xuất từ LibreOffice) thì lấy khối chữ một dòng nằm trên cùng.
            $title = '';
            $titleIdx = -1;
            foreach ($shapes as $k => $s) {
                if ($s['ph'] === 'title' || $s['ph'] === 'ctrtitle') { $titleIdx = $k; break; }
            }
            if ($titleIdx < 0 && count($shapes) > 1) {
                $top = null;
                foreach ($shapes as $k => $s) {
                    if ($top === null || $s['y'] < $shapes[$top]['y']) $top = $k;
                }
                if ($top !== null && count($shapes[$top]['lines']) === 1) $titleIdx = $top;
            }
            if ($titleIdx >= 0) {
                $title = array_shift($shapes[$titleIdx]['lines']);
            }

            $lines = [];
            foreach ($shapes as $s) foreach ($s['lines'] as $l) $lines[] = $l;
            // chữ trong bảng và sơ đồ nằm ngoài <sp>
            foreach ($doc->xpath('//graphicFrame//tbl//tc') as $tc) {
                $s = '';
                foreach ($tc->xpath('.//t') as $t) $s .= (string)$t . ' ';
                $s = trim($s);
                if ($s !== '') $lines[] = $s;
            }
            $out[] = ['no' => $no, 'title' => $title, 'lines' => $lines];
        }
        return $out;
    }

    // ========================================================= OPENDOCUMENT
    /**
     * Bóc chữ từ tệp OpenDocument (.odt/.ods/.odp).
     * @return array|null danh sách đoạn văn bản
     */
    public static function odfParagraphs($bin, $maxParas = 1500)
    {
        $entries = Zip::entries($bin);
        $xml = self::entry($bin, $entries, 'content.xml');
        if ($xml === null) return null;

        // Chỉ lấy phần thân, bỏ khai báo kiểu dáng ở đầu tệp
        $pos = strpos($xml, '<office:body');
        if ($pos !== false) $xml = substr($xml, $pos);
        $isSheet = strpos($xml, '<office:spreadsheet') !== false;

        // Giữ ranh giới đoạn/ô rồi bóc thẻ — đủ để đọc nội dung, không dựng lại bố cục
        $xml = preg_replace('~<table:table\b[^>]*table:name="([^"]*)"[^>]*>~', "\n\n[$1]\n", $xml);
        $xml = preg_replace('~<table:table-row\b[^>]*>~', "\n", $xml);
        $xml = preg_replace('~<table:table-cell\b[^>]*>~', "\t", $xml);
        $xml = preg_replace('~<text:(p|h)\b[^>]*>~', $isSheet ? ' ' : "\n", $xml);
        $xml = str_replace('<text:tab/>', "\t", $xml);
        $xml = preg_replace('~<text:s\b[^>]*/>~', ' ', $xml);
        $xml = preg_replace('~<text:line-break\s*/>~', "\n", $xml);
        $txt = html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8');

        $out = [];
        foreach (explode("\n", $txt) as $line) {
            $line = preg_replace('/\t{2,}/', "\t", rtrim($line, " \t"));
            $line = preg_replace('/[ ]{2,}/', ' ', $line);
            if (trim($line, " \t") === '') continue;
            $out[] = ltrim($line, "\t");
            if (count($out) >= $maxParas) break;
        }
        return $out;
    }

    // ================================================================== ZIP
    /**
     * Liệt kê nội dung tệp nén .zip.
     * @return array [['path'=>…, 'size'=>…, 'dir'=>bool], …]
     */
    public static function zipList($bin, $limit = 500)
    {
        $out = [];
        foreach (Zip::entries($bin) as $path => $info) {
            $out[] = [
                'path' => $path,
                'size' => (int)$info['size'],
                'dir'  => substr($path, -1) === '/' || (int)$info['size'] === 0 && substr($path, -1) === '/',
            ];
            if (count($out) >= $limit) break;
        }
        usort($out, function ($a, $b) { return strcmp($a['path'], $b['path']); });
        return $out;
    }
}
