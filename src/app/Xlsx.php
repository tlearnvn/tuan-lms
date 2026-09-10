<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Tạo tệp Excel .xlsx bằng PHP thuần (không cần PhpSpreadsheet).
 * Hỗ trợ nhiều sheet, tiêu đề gộp ô, định dạng số, cố định dòng, tô màu.
 *
 * Chỉ số style dựng sẵn:
 *   0 mặc định · 1 tiêu đề bảng (trắng/nền tím) · 2 in đậm · 3 số 2 chữ số thập phân
 *   4 canh giữa · 5 tiêu đề lớn · 6 chữ nhỏ xám · 7 ô tô nhạt · 8 ngày giờ
 */
class Xlsx
{
    private $sheets = [];

    public function addSheet($name)
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/u', '', (string)$name);
        $name = mb_substr($name ?: 'Sheet', 0, 31, 'UTF-8');
        $this->sheets[] = ['name' => $name, 'rows' => [], 'cols' => [], 'merges' => [], 'freeze' => '', 'autofilter' => ''];
        return count($this->sheets) - 1;
    }

    /** @param array $widths mảng độ rộng cột (đơn vị ký tự) */
    public function setCols($sheet, $widths) { $this->sheets[$sheet]['cols'] = $widths; }

    public function freeze($sheet, $cell) { $this->sheets[$sheet]['freeze'] = $cell; }

    public function autofilter($sheet, $range) { $this->sheets[$sheet]['autofilter'] = $range; }

    public function merge($sheet, $range) { $this->sheets[$sheet]['merges'][] = $range; }

    /**
     * Thêm một dòng.
     * @param array $cells mỗi phần tử: giá trị vô hướng, hoặc ['v'=>..., 's'=>style, 't'=>'n|s']
     */
    public function addRow($sheet, $cells, $style = 0, $height = null)
    {
        $this->sheets[$sheet]['rows'][] = ['cells' => $cells, 'style' => $style, 'h' => $height];
        return count($this->sheets[$sheet]['rows']);
    }

    public function addEmptyRow($sheet) { $this->addRow($sheet, []); }

    public function rowCount($sheet) { return count($this->sheets[$sheet]['rows']); }

    public static function colLetter($i)
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $mod = ($i - 1) % 26;
            $s = chr(65 + $mod) . $s;
            $i = (int)(($i - $mod) / 26);
        }
        return $s;
    }

    private static function esc($s)
    {
        $s = (string)$s;
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], $s);
    }

    public function output()
    {
        $files = [];

        $files['[Content_Types].xml'] = $this->contentTypes();
        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $wsRels = '';
        $wbSheets = '';
        foreach ($this->sheets as $i => $s) {
            $n = $i + 1;
            $wbSheets .= '<sheet name="' . self::esc($s['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $wsRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $files['xl/worksheets/sheet' . $n . '.xml'] = $this->sheetXml($s);
        }
        $styleRid = count($this->sheets) + 1;
        $wsRels .= '<Relationship Id="rId' . $styleRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $wbSheets . '</sheets></workbook>';

        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wsRels . '</Relationships>';

        $files['xl/styles.xml'] = $this->stylesXml();
        $files['docProps/core.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>' . self::esc(setting('site_name', 'LMS')) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . self::esc(setting('site_name', 'LMS')) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>'
            . '</cp:coreProperties>';

        return Zip::create($files);
    }

    private function contentTypes()
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        foreach ($this->sheets as $i => $s) {
            $x .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $x . '</Types>';
    }

    private function sheetXml($s)
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($s['freeze']) {
            preg_match('/([A-Z]+)(\d+)/', $s['freeze'], $m);
            $xSplit = 0;
            $col = isset($m[1]) ? $m[1] : 'A';
            for ($i = 0; $i < strlen($col); $i++) $xSplit = $xSplit * 26 + (ord($col[$i]) - 64);
            $xSplit = max(0, $xSplit - 1);
            $ySplit = max(0, (int)(isset($m[2]) ? $m[2] : 1) - 1);
            $x .= '<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
                . '<pane xSplit="' . $xSplit . '" ySplit="' . $ySplit . '" topLeftCell="' . self::esc($s['freeze'])
                . '" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>';
        }

        $x .= '<sheetFormatPr defaultRowHeight="18"/>';

        if ($s['cols']) {
            $x .= '<cols>';
            foreach ($s['cols'] as $i => $w) {
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }

        $x .= '<sheetData>';
        foreach ($s['rows'] as $ri => $row) {
            $r = $ri + 1;
            $x .= '<row r="' . $r . '"' . ($row['h'] ? ' ht="' . (float)$row['h'] . '" customHeight="1"' : '') . '>';
            foreach ($row['cells'] as $ci => $cell) {
                $style = $row['style'];
                $type  = null;
                $val   = $cell;
                if (is_array($cell)) {
                    $val   = array_key_exists('v', $cell) ? $cell['v'] : '';
                    $style = array_key_exists('s', $cell) ? $cell['s'] : $style;
                    $type  = array_key_exists('t', $cell) ? $cell['t'] : null;
                }
                if ($val === null || $val === '') {
                    if ($style) $x .= '<c r="' . self::colLetter($ci) . $r . '" s="' . (int)$style . '"/>';
                    continue;
                }
                $ref = self::colLetter($ci) . $r;
                $isNum = ($type === 'n') || ($type === null && is_numeric($val) && !is_string($val))
                    || ($type === null && is_string($val) && preg_match('/^-?\d+(\.\d+)?$/', $val) && strlen($val) < 15);
                if ($isNum) {
                    $x .= '<c r="' . $ref . '" s="' . (int)$style . '"><v>' . (0 + $val) . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . (int)$style . '" t="inlineStr"><is><t xml:space="preserve">'
                        . self::esc($val) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';

        if ($s['autofilter']) $x .= '<autoFilter ref="' . self::esc($s['autofilter']) . '"/>';
        if ($s['merges']) {
            $x .= '<mergeCells count="' . count($s['merges']) . '">';
            foreach ($s['merges'] as $m) $x .= '<mergeCell ref="' . self::esc($m) . '"/>';
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        return $x . '</worksheet>';
    }

    private function stylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy\ hh:mm"/></numFmts>'
            . '<fonts count="6">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'                                     // 0
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'           // 1
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'                                  // 2
            . '<font><b/><sz val="16"/><color rgb="FF4A32C8"/><name val="Calibri"/></font>'           // 3
            . '<font><sz val="9"/><color rgb="FF7A7A8C"/><name val="Calibri"/></font>'                // 4
            . '<font><sz val="11"/><color rgb="FF1A1A2E"/><name val="Calibri"/></font>'               // 5
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF6C5CE7"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F0FF"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9D6F2"/></left><right style="thin"><color rgb="FFD9D6F2"/></right>'
            . '<top style="thin"><color rgb="FFD9D6F2"/></top><bottom style="thin"><color rgb="FFD9D6F2"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'          // 0
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 1
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>'         // 2
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' // 3
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 4
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'                          // 5
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'                          // 6
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 7
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>' // 8
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Gửi tệp xlsx về trình duyệt */
    public function download($filename)
    {
        $bin = $this->output();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $filename)
            . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: max-age=0, must-revalidate');
        echo $bin;
        exit;
    }
}
