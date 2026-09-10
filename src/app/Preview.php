<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Xem trước tệp học liệu ngay trên web.
 * Ảnh, PDF, video, âm thanh do trình duyệt lo; các định dạng còn lại
 * (Word, Excel, PowerPoint, OpenDocument, văn bản, mã nguồn, tệp nén)
 * được đọc bằng PHP thuần trong app/Office.php rồi dựng thành HTML.
 */
class Preview
{
    /** Phần mở rộng → nhóm xem trước */
    public static function kind($ext)
    {
        $ext = strtolower((string)$ext);
        if ($ext === 'pdf') return 'pdf';
        if ($ext === 'svg') return 'code';                        // xem mã nguồn cho an toàn
        if (is_image_ext($ext)) return 'image';
        if (is_video_ext($ext)) return 'video';
        if (is_audio_ext($ext)) return 'audio';
        if ($ext === 'docx') return 'docx';
        if ($ext === 'xlsx' || $ext === 'xlsm') return 'xlsx';
        if ($ext === 'pptx') return 'pptx';
        if (in_array($ext, ['odt', 'ods', 'odp'], true)) return 'odf';
        if ($ext === 'csv' || $ext === 'tsv') return 'csv';
        if (in_array($ext, ['txt', 'md', 'markdown', 'log', 'srt', 'vtt', 'nfo'], true)) return 'text';
        if (in_array($ext, ['html', 'htm', 'xml', 'json', 'js', 'css', 'php', 'sql', 'py', 'java', 'c', 'cpp',
                            'cs', 'go', 'rb', 'sh', 'bat', 'ts', 'jsx', 'tsx', 'yml', 'yaml', 'ini', 'conf'], true)) return 'code';
        if ($ext === 'zip') return 'zip';
        return 'none';
    }

    /** Nhóm này có xem trước được không */
    public static function supports($ext)
    {
        return self::enabled() && self::kind($ext) !== 'none';
    }

    public static function enabled()
    {
        return Settings::bool('preview_enabled', true);
    }

    /** Nhãn ngắn mô tả kiểu xem trước */
    public static function kindLabel($kind)
    {
        $m = [
            'pdf' => 'Tài liệu PDF', 'image' => 'Hình ảnh', 'video' => 'Video', 'audio' => 'Âm thanh',
            'docx' => 'Văn bản Word', 'xlsx' => 'Bảng tính Excel', 'pptx' => 'Bài trình chiếu PowerPoint',
            'odf' => 'Tài liệu OpenDocument', 'csv' => 'Bảng dữ liệu', 'text' => 'Văn bản thuần',
            'code' => 'Mã nguồn', 'zip' => 'Tệp nén',
        ];
        return isset($m[$kind]) ? $m[$kind] : 'Tệp đính kèm';
    }

    /** Dung lượng tối đa (byte) còn bóc tách nội dung được */
    private static function maxBytes()
    {
        return max(1, Settings::int('preview_max_mb', 25)) * 1024 * 1024;
    }

    /**
     * HTML xem trước cho một tệp. Nơi gọi phải tự kiểm tra quyền trước.
     * @param array $file  bản ghi bảng files
     * @param array $opts  ['height' => '70vh', 'maxRows' => 300]
     */
    public static function render($file, $opts = [])
    {
        if (!$file) return self::note('🚫', 'Không tìm thấy tệp.');
        $ext    = strtolower((string)$file['ext']);
        $kind   = self::kind($ext);
        $height = isset($opts['height']) ? $opts['height'] : '70vh';
        $url    = media_url($file['id']);

        if (!self::enabled()) {
            return self::download($file, 'Chức năng xem trước đang tắt.');
        }

        switch ($kind) {
            case 'image':
                return '<div class="pv-media pv-image"><img src="' . e($url) . '" alt="' . e($file['name'])
                    . '" style="max-height:' . e($height) . '"></div>';

            case 'pdf':
                return '<iframe class="pv-frame" src="' . e($url) . '#view=FitH" style="height:' . e($height) . '"'
                    . ' title="' . e($file['name']) . '"></iframe>'
                    . '<p class="tiny muted mt-1">Trình duyệt không mở được PDF? '
                    . '<a href="' . e($url) . '" target="_blank" rel="noopener">Mở ở tab mới</a> hoặc tải về.</p>';

            case 'video':
                return '<div class="pv-media"><video class="pv-video" controls preload="metadata" style="max-height:' . e($height) . '">'
                    . '<source src="' . e($url) . '" type="' . e($file['mime']) . '">'
                    . 'Trình duyệt không phát được video này.</video></div>';

            case 'audio':
                return '<div class="pv-audio"><audio controls style="width:100%"><source src="' . e($url) . '" type="'
                    . e($file['mime']) . '"></audio></div>';
        }

        // Định dạng Office đời cũ (nhị phân) không mở được bằng PHP thuần
        if (in_array($ext, ['doc', 'xls', 'ppt'], true)) {
            $moi = ['doc' => '.docx', 'xls' => '.xlsx', 'ppt' => '.pptx'];
            return self::download($file, 'Định dạng .' . $ext . ' đời cũ chưa xem trước được trên web. '
                . 'Hãy mở bằng Word/Excel/PowerPoint rồi lưu lại dưới dạng ' . $moi[$ext]
                . ' và tải lên lại để cả lớp xem ngay trên trang.');
        }

        // Các nhóm còn lại cần đọc nội dung từ CSDL
        if ((int)$file['size'] > self::maxBytes()) {
            return self::download($file, 'Tệp lớn hơn ' . Settings::int('preview_max_mb', 25)
                . ' MB nên hệ thống không mở xem trước. Hãy tải về để xem đầy đủ.');
        }

        require_once LMS_APP . '/Office.php';
        $bin = Storage::read($file['id']);
        if ($bin === '' && (int)$file['size'] > 0) return self::download($file, 'Không đọc được nội dung tệp.');

        switch ($kind) {
            case 'docx': return self::docx($bin, $file, $height);
            case 'xlsx': return self::xlsx($bin, $file, $opts);
            case 'pptx': return self::pptx($bin, $file);
            case 'odf':  return self::odf($bin, $file);
            case 'csv':  return self::csv($bin, $file, $opts);
            case 'text': return self::text($bin, $file, false);
            case 'code': return self::text($bin, $file, true);
            case 'zip':  return self::zip($bin, $file);
        }
        return self::download($file);
    }

    // ------------------------------------------------------------------ Word
    private static function docx($bin, $file, $height)
    {
        $html = Office::docxHtml($bin);
        if ($html === null) return self::download($file, 'Tệp Word này không đọc được (có thể là định dạng .doc cũ).');
        if (trim(strip_tags($html)) === '' && strpos($html, '<img') === false) {
            return self::download($file, 'Tài liệu không có nội dung chữ để hiển thị.');
        }
        return '<div class="pv-doc rich-content" style="max-height:' . e($height) . '">' . $html . '</div>'
            . self::hint('Bản xem trước giữ chữ, bảng và hình minh hoạ; cách trình bày có thể khác tệp gốc. Tải về để xem đúng bản in.');
    }

    // ----------------------------------------------------------------- Excel
    private static function xlsx($bin, $file, $opts)
    {
        $maxRows = isset($opts['maxRows']) ? (int)$opts['maxRows'] : 300;
        $sheets = Office::xlsxSheets($bin, $maxRows, 40);
        if ($sheets === null) return self::download($file, 'Tệp Excel này không đọc được (có thể là định dạng .xls cũ).');
        if (!$sheets) return self::download($file, 'Bảng tính không có trang nào để hiển thị.');

        $uid = 'pvx' . (int)$file['id'];
        $tabs = '';
        $panes = '';
        foreach ($sheets as $i => $sh) {
            $tabs .= '<button type="button" class="pv-tab' . ($i === 0 ? ' active' : '') . '" data-pv-tab="' . $uid . '-' . $i . '">'
                . e($sh['name'] !== '' ? $sh['name'] : ('Trang ' . ($i + 1))) . '</button>';

            $body = '';
            $width = 0;
            foreach ($sh['rows'] as $r) $width = max($width, count($r));
            foreach ($sh['rows'] as $ri => $r) {
                $cells = '';
                for ($c = 0; $c < $width; $c++) {
                    $v = isset($r[$c]) ? $r[$c] : '';
                    $tag = $ri === 0 ? 'th' : 'td';
                    $num = $v !== '' && is_numeric(str_replace(',', '.', $v));
                    $cells .= '<' . $tag . ($num ? ' class="right"' : '') . '>' . e($v) . '</' . $tag . '>';
                }
                $body .= '<tr>' . $cells . '</tr>';
            }
            $panes .= '<div class="pv-pane' . ($i === 0 ? '' : ' hidden') . '" id="' . $uid . '-' . $i . '">'
                . ($body === '' ? '<p class="muted center" style="padding:24px 0">Trang tính trống.</p>'
                    : '<div class="table-wrap"><table class="data pv-sheet">' . $body . '</table></div>')
                . ($sh['more'] ? self::hint('Chỉ hiển thị ' . $maxRows . ' hàng và 40 cột đầu tiên. Tải tệp về để xem toàn bộ.') : '')
                . '</div>';
        }
        return '<div class="pv-tabs">' . $tabs . '</div>' . $panes;
    }

    // ------------------------------------------------------------ PowerPoint
    private static function pptx($bin, $file)
    {
        $slides = Office::pptxSlides($bin);
        if ($slides === null) return self::download($file, 'Tệp trình chiếu này không đọc được (có thể là định dạng .ppt cũ).');
        if (!$slides) return self::download($file, 'Bài trình chiếu không có slide nào.');

        $out = '<div class="pv-slides">';
        foreach ($slides as $s) {
            $out .= '<div class="pv-slide"><div class="pv-slide-no">Slide ' . (int)$s['no'] . '</div>';
            if ($s['title'] !== '') $out .= '<h4 class="pv-slide-title">' . e($s['title']) . '</h4>';
            if ($s['lines']) {
                $out .= '<ul class="pv-slide-lines">';
                foreach ($s['lines'] as $l) $out .= '<li>' . e($l) . '</li>';
                $out .= '</ul>';
            } elseif ($s['title'] === '') {
                $out .= '<p class="muted tiny">(Slide chỉ có hình ảnh)</p>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';
        return $out . self::hint('Bản xem trước hiển thị nội dung chữ của từng slide. Tải về để xem đúng hình ảnh và hiệu ứng.');
    }

    // ---------------------------------------------------------- OpenDocument
    private static function odf($bin, $file)
    {
        $paras = Office::odfParagraphs($bin);
        if ($paras === null) return self::download($file, 'Tệp OpenDocument này không đọc được.');
        if (!$paras) return self::download($file, 'Tài liệu không có nội dung chữ để hiển thị.');

        // Bảng tính: dựng lại thành bảng, mỗi trang tính một tiêu đề
        if (strtolower((string)$file['ext']) === 'ods') return self::odfSheet($paras);

        $out = '<div class="pv-doc rich-content">';
        foreach ($paras as $p) {
            if (strpos($p, "\t") !== false) {
                $out .= '<p class="pv-odf-row">' . implode('<span class="pv-sep">·</span>',
                    array_map('e', array_filter(array_map('trim', explode("\t", $p)),
                        function ($x) { return $x !== ''; }))) . '</p>';
            } else {
                $out .= '<p>' . e($p) . '</p>';
            }
        }
        $out .= '</div>';
        return $out . self::hint('Bản xem trước chỉ lấy phần chữ. Tải về để xem đúng bố cục.');
    }

    /** Các dòng ngăn bằng tab của .ods → bảng HTML */
    private static function odfSheet($paras)
    {
        $out = '';
        $rows = '';
        $head = true;
        $flush = function () use (&$rows) {
            if ($rows === '') return '';
            $h = '<div class="table-wrap mb-2"><table class="data pv-sheet">' . $rows . '</table></div>';
            $rows = '';
            return $h;
        };
        foreach ($paras as $p) {
            if (preg_match('/^\[(.*)\]$/u', trim($p), $m)) {
                $out .= $flush();
                $out .= '<div class="pv-sheet-name">' . e($m[1]) . '</div>';
                $head = true;
                continue;
            }
            $tag = $head ? 'th' : 'td';
            $head = false;
            $tr = '';
            foreach (explode("\t", $p) as $c) {
                $c = trim($c);
                $num = $c !== '' && is_numeric(str_replace(',', '.', $c));
                $tr .= '<' . $tag . ($num ? ' class="right"' : '') . '>' . e($c) . '</' . $tag . '>';
            }
            $rows .= '<tr>' . $tr . '</tr>';
        }
        $out .= $flush();
        if ($out === '') return self::note('📄', 'Bảng tính không có dữ liệu.');
        return $out . self::hint('Bản xem trước lấy giá trị từng ô, không kèm công thức và định dạng. Tải về để xem đầy đủ.');
    }

    // ------------------------------------------------------------------- CSV
    private static function csv($bin, $file, $opts)
    {
        $maxRows = isset($opts['maxRows']) ? (int)$opts['maxRows'] : 300;
        $txt = self::toUtf8($bin);
        $txt = preg_replace("/^\xEF\xBB\xBF/", '', $txt);
        $lines = preg_split("/\r\n|\n|\r/", $txt);

        $head = isset($lines[0]) ? $lines[0] : '';
        $sep = strtolower((string)$file['ext']) === 'tsv' ? "\t" : self::guessSep($head);

        $body = '';
        $n = 0;
        $more = false;
        foreach ($lines as $i => $line) {
            if ($line === '' && $i === count($lines) - 1) continue;
            if ($n >= $maxRows) { $more = true; break; }
            $cells = str_getcsv($line, $sep, '"', '\\');
            $tag = $n === 0 ? 'th' : 'td';
            $tr = '';
            foreach ($cells as $c) {
                $num = trim($c) !== '' && is_numeric(str_replace([' ', ','], ['', '.'], $c));
                $tr .= '<' . $tag . ($num ? ' class="right"' : '') . '>' . e($c) . '</' . $tag . '>';
            }
            $body .= '<tr>' . $tr . '</tr>';
            $n++;
        }
        if ($body === '') return self::download($file, 'Tệp không có dữ liệu.');
        return '<div class="table-wrap"><table class="data pv-sheet">' . $body . '</table></div>'
            . ($more ? self::hint('Chỉ hiển thị ' . $maxRows . ' dòng đầu tiên. Tải tệp về để xem toàn bộ.') : '');
    }

    private static function guessSep($line)
    {
        $best = ','; $max = -1;
        foreach ([',', ';', "\t", '|'] as $s) {
            $c = substr_count($line, $s);
            if ($c > $max) { $max = $c; $best = $s; }
        }
        return $best;
    }

    // ---------------------------------------------------- văn bản & mã nguồn
    private static function text($bin, $file, $numbered)
    {
        $limit = 400000;
        $cut = strlen($bin) > $limit;
        $txt = self::toUtf8($cut ? substr($bin, 0, $limit) : $bin);
        $txt = str_replace("\r\n", "\n", $txt);
        if (trim($txt) === '') return self::download($file, 'Tệp không có nội dung chữ.');

        if ($numbered) {
            $lines = explode("\n", $txt);
            $out = '<div class="pv-code"><table class="pv-code-tbl">';
            foreach ($lines as $i => $l) {
                $out .= '<tr><td class="pv-ln">' . ($i + 1) . '</td><td><pre>' . e($l === '' ? ' ' : $l) . '</pre></td></tr>';
                if ($i >= 4000) { $cut = true; break; }
            }
            $out .= '</table></div>';
        } else {
            $out = '<div class="pv-text"><pre>' . e($txt) . '</pre></div>';
        }
        return $out . ($cut ? self::hint('Nội dung quá dài nên chỉ hiển thị phần đầu. Tải tệp về để xem đầy đủ.') : '');
    }

    private static function toUtf8($s)
    {
        if (mb_check_encoding($s, 'UTF-8')) return $s;
        $conv = @mb_convert_encoding($s, 'UTF-8', 'Windows-1258, Windows-1252, ISO-8859-1');
        return $conv !== false ? $conv : preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $s);
    }

    // ------------------------------------------------------------------- ZIP
    private static function zip($bin, $file)
    {
        $list = Office::zipList($bin);
        if (!$list) return self::download($file, 'Không đọc được danh sách tệp bên trong.');

        $rows = '';
        $total = 0;
        foreach ($list as $it) {
            if (substr($it['path'], -1) === '/') continue;
            $total += $it['size'];
            list($ic, $col) = file_icon(ext_of($it['path']));
            $rows .= '<tr><td><span class="pv-zip-ico" style="background:' . e($col) . '1a">' . $ic . '</span> '
                . e($it['path']) . '</td><td class="right nowrap">' . human_size($it['size']) . '</td></tr>';
        }
        if ($rows === '') return self::download($file, 'Tệp nén rỗng.');
        return '<div class="table-wrap"><table class="data pv-sheet">'
            . '<thead><tr><th>Tệp bên trong</th><th class="right" style="width:120px">Dung lượng</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . self::hint('Tổng ' . human_size($total) . ' sau khi giải nén. Tải tệp về rồi giải nén để mở nội dung.');
    }

    // ------------------------------------------------------------------ phụ
    private static function hint($text)
    {
        return '<p class="pv-hint tiny muted">💡 ' . e($text) . '</p>';
    }

    private static function note($emoji, $text)
    {
        return '<div class="pv-empty"><div class="pv-empty-emoji">' . $emoji . '</div><p>' . e($text) . '</p></div>';
    }

    /** Khối "không xem trước được" kèm nút tải về */
    private static function download($file, $reason = '')
    {
        list($ic) = file_icon($file['ext']);
        return '<div class="pv-empty"><div class="pv-empty-emoji">' . $ic . '</div>'
            . '<h4>' . e($file['name']) . '</h4>'
            . '<p>' . e($reason !== '' ? $reason : 'Định dạng này chưa xem trước trực tiếp được trên web.') . '</p>'
            . '<p class="tiny muted">' . e(strtoupper((string)$file['ext'])) . ' · ' . human_size($file['size']) . '</p>'
            . '<a class="btn btn-primary" href="' . e(media_url($file['id'], true)) . '">⬇️ Tải tệp về máy</a></div>';
    }
}
