<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Xuất dữ liệu ra Excel (.xlsx), PDF và CSV */
require_once LMS_APP . '/Xlsx.php';
require_once LMS_APP . '/controllers/grade.php';

/** Tiêu đề chung cho mọi tệp xuất ra */
function export_header_lines($title, $subtitle = '')
{
    return [
        Settings::get('org_name'),
        Settings::get('site_name'),
        $title,
        $subtitle,
        'Xuất lúc ' . date('H:i') . ' ngày ' . date('d/m/Y') . ' (giờ Việt Nam) · Người xuất: ' . Auth::name(),
    ];
}

function export_filename($base, $ext)
{
    return slugify($base) . '-' . date('Ymd-His') . '.' . $ext;
}

/** Gửi CSV có BOM để Excel đọc đúng tiếng Việt */
function export_csv($filename, $rows)
{
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

/** Khởi tạo tài liệu PDF có tiêu đề và chân trang chuẩn */
function export_pdf_new($title, $subtitle = '', $orientation = 'P')
{
    require_once LMS_APP . '/Pdf.php';
    $pdf = new Pdf($orientation, 'A4');
    $pdf->title = $title;

    $org = Settings::get('org_name');
    $site = Settings::get('site_name');
    $logoId = (int)Settings::get('logo_id', 0);
    $logoBin = $logoId ? Storage::read($logoId) : '';
    $primary = Settings::get('primary_color', '#6C5CE7');

    $pdf->headerCallback = function ($p) use ($org, $site, $title, $subtitle, $logoBin, $primary) {
        $p->setFillColor($primary);
        $p->rect(0, 0, $p->pageWidth, 4, 'F');
        $x = $p->marginLeft;
        if ($logoBin) {
            $r = $p->image($logoBin, $x, 16, 34);
            if ($r) $x += 44;
        }
        $p->setTextColor(30, 30, 46);
        $p->setFont('B', 12);
        $p->text($x, 28, mb_strtoupper($org, 'UTF-8'));
        $p->setFont('R', 9);
        $p->setTextColor(110, 112, 140);
        $p->text($x, 42, $site);
        $p->setTextColor(30, 30, 46);
        $p->setDrawColor('#E0DEF5');
        $p->line($p->marginLeft, 52, $p->pageWidth - $p->marginRight, 52);
        $p->y = 66;
        if ($p->pageNo() === 1) {
            $p->setFont('B', 15);
            $p->cell(0, 22, $title, 0, 1, 'C');
            if ($subtitle !== '') {
                $p->setFont('R', 10);
                $p->setTextColor(110, 112, 140);
                $p->cell(0, 15, $subtitle, 0, 1, 'C');
                $p->setTextColor(30, 30, 46);
            }
            $p->setFont('R', 8);
            $p->setTextColor(140, 142, 168);
            $p->cell(0, 12, 'Xuất lúc ' . date('H:i d/m/Y') . ' (giờ Việt Nam) · Người xuất: ' . Auth::name(), 0, 1, 'C');
            $p->setTextColor(30, 30, 46);
            $p->y += 6;
        }
    };
    return $pdf;
}

function export_pdf_footer($pdf)
{
    $copyright = Settings::get('copyright');
    $pdf->setFont('R', 8);
    $pdf->setTextColor(150, 152, 175);
    $y = $pdf->pageHeight - 26;
    $pdf->setDrawColor('#E8E6F5');
    $pdf->line($pdf->marginLeft, $y - 8, $pdf->pageWidth - $pdf->marginRight, $y - 8);
    $pdf->text($pdf->marginLeft, $y + 2, $copyright);
    $t = 'Trang ' . $pdf->pageNo();
    $pdf->text($pdf->pageWidth - $pdf->marginRight - $pdf->getStringWidth($t), $y + 2, $t);
    $pdf->setTextColor(30, 30, 46);
}

// ------------------------------------------------------------------ sổ điểm
function export_gradebook()
{
    Auth::requireTeacher();
    $courseId = inp_int('id');
    $course = DB::find('courses', $courseId);
    if (!$course || !Auth::canManageCourse($course)) { Auth::deny(); return; }

    $gb = gradebook_data($courseId);
    $format = inp('format', 'xlsx');
    $title = 'BẢNG ĐIỂM TỔNG HỢP';
    $subtitle = $course['title'] . ' (' . $course['code'] . ')';

    // Dữ liệu bảng
    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Lớp/Đơn vị'];
    foreach ($gb['items'] as $it) $head[] = $it['title'] . ' (/' . score_fmt($it['max_points']) . ')';
    $head[] = 'Tổng điểm';
    $head[] = 'Thang 10';
    $head[] = 'Xếp loại';

    $body = [];
    foreach ($gb['students'] as $n => $st) {
        $uid = (int)$st['id'];
        $t = $gb['totals'][$uid];
        list($rank) = grade_rank($t['score10']);
        $row = [$n + 1, $st['full_name'], $st['username'], $st['org_unit']];
        foreach ($gb['items'] as $it) {
            $s = isset($gb['scores'][$uid][(int)$it['id']]) ? $gb['scores'][$uid][(int)$it['id']] : null;
            $row[] = $s === null ? '' : $s;
        }
        $row[] = $t['max'] > 0 ? round($t['got'], 2) : '';
        $row[] = $t['score10'];
        $row[] = $rank;
        $body[] = $row;
    }

    if ($format === 'csv') {
        $rows = [$head];
        foreach ($body as $r) $rows[] = $r;
        export_csv(export_filename('bang-diem-' . $course['code'], 'csv'), $rows);
    }

    if ($format === 'pdf') {
        $pdf = export_pdf_new($title, $subtitle, 'L');
        $pdf->marginTop = 66;
        $pdf->addPage();

        $nCols = count($head);
        $fixed = [26, 132, 74, 74];
        $rest = $pdf->contentWidth() - array_sum($fixed) - 60 - 46 - 62;
        $each = count($gb['items']) > 0 ? max(34, $rest / count($gb['items'])) : 0;
        $widths = $fixed;
        foreach ($gb['items'] as $it) $widths[] = $each;
        $widths[] = 60; $widths[] = 46; $widths[] = 62;

        $align = ['C', 'L', 'L', 'L'];
        foreach ($gb['items'] as $it) $align[] = 'C';
        $align[] = 'C'; $align[] = 'C'; $align[] = 'C';

        $rows = [];
        foreach ($body as $r) {
            $row = [];
            foreach ($r as $ci => $val) {
                if ($ci >= 4 && $ci < 4 + count($gb['items'])) $val = $val === '' ? '—' : score_fmt($val);
                elseif ($ci === $nCols - 3) $val = $val === '' ? '—' : score_fmt($val);
                elseif ($ci === $nCols - 2) $val = $val === null ? '—' : ['v' => score_fmt($val), 'bold' => true];
                $row[] = $val;
            }
            $rows[] = $row;
        }

        $pdf->table($head, $rows, $widths, [
            'align' => $align, 'headBg' => Settings::get('primary_color', '#6C5CE7'),
            'rowHeight' => 20, 'fontSize' => 8, 'headFontSize' => 7.5,
        ]);

        // Thống kê nhanh
        $valid = array_filter($gb['totals'], function ($t) { return $t['score10'] !== null; });
        $avg = $valid ? array_sum(array_column($valid, 'score10')) / count($valid) : null;
        $pdf->checkBreak(70);
        $pdf->ln(14);
        $pdf->setFont('B', 10);
        $pdf->cell(0, 16, 'TỔNG HỢP', 0, 1);
        $pdf->setFont('R', 9);
        $pdf->cell(0, 14, '· Sĩ số: ' . count($gb['students']) . ' học viên · Đã có điểm: ' . count($valid)
            . ' · Điểm trung bình lớp: ' . ($avg !== null ? score_fmt(round($avg, 2)) : '—') . '/10', 0, 1);

        $ranks = [];
        foreach ($gb['totals'] as $t) {
            list($rk) = grade_rank($t['score10']);
            if ($t['score10'] === null) $rk = 'Chưa có điểm';
            $ranks[$rk] = (isset($ranks[$rk]) ? $ranks[$rk] : 0) + 1;
        }
        $parts = [];
        foreach ($ranks as $k => $v) $parts[] = $k . ': ' . $v;
        $pdf->cell(0, 14, '· Xếp loại — ' . implode(' · ', $parts), 0, 1);

        $pdf->ln(24);
        $pdf->setFont('R', 9);
        $w = $pdf->contentWidth() / 2;
        $pdf->x = $pdf->marginLeft + $w;
        $pdf->cell($w, 14, '............, ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'), 0, 1, 'C');
        $pdf->x = $pdf->marginLeft + $w;
        $pdf->setFont('B', 10);
        $pdf->cell($w, 16, 'GIÁO VIÊN PHỤ TRÁCH', 0, 1, 'C');
        $pdf->setFont('R', 9);
        $pdf->x = $pdf->marginLeft + $w;
        $pdf->cell($w, 46, '(Ký và ghi rõ họ tên)', 0, 1, 'C');
        $pdf->x = $pdf->marginLeft + $w;
        $pdf->setFont('B', 10);
        $pdf->cell($w, 14, Auth::name(), 0, 1, 'C');

        export_pdf_footer($pdf);
        Log::write('export_gradebook_pdf', 'course', $courseId);
        $pdf->output(export_filename('bang-diem-' . $course['code'], 'pdf'), 'D');
    }

    // ---------------- Excel
    $x = new Xlsx();
    $s = $x->addSheet('Bảng điểm');
    $lastCol = Xlsx::colLetter(count($head) - 1);

    $x->addRow($s, [['v' => Settings::get('org_name'), 's' => 5]], 5, 24);
    $x->merge($s, 'A1:' . $lastCol . '1');
    $x->addRow($s, [['v' => $title . ' — ' . $subtitle, 's' => 2]], 2, 20);
    $x->merge($s, 'A2:' . $lastCol . '2');
    $x->addRow($s, [['v' => 'Xuất lúc ' . date('H:i d/m/Y') . ' (giờ Việt Nam) · Người xuất: ' . Auth::name(), 's' => 6]], 6);
    $x->merge($s, 'A3:' . $lastCol . '3');
    $x->addEmptyRow($s);

    $x->addRow($s, $head, 1, 34);
    $headerRow = $x->rowCount($s);
    foreach ($body as $r) {
        $cells = [];
        foreach ($r as $ci => $val) {
            if ($ci === 0) $cells[] = ['v' => $val, 's' => 4];
            elseif ($ci >= 4) $cells[] = ['v' => $val === '' ? '' : $val, 's' => $ci >= count($head) - 3 ? 7 : 3, 't' => is_numeric($val) ? 'n' : 's'];
            else $cells[] = $val;
        }
        $x->addRow($s, $cells);
    }

    $widths = [6, 26, 16, 16];
    foreach ($gb['items'] as $it) $widths[] = 13;
    $widths[] = 12; $widths[] = 10; $widths[] = 14;
    $x->setCols($s, $widths);
    $x->freeze($s, 'C' . ($headerRow + 1));
    $x->autofilter($s, 'A' . $headerRow . ':' . $lastCol . $headerRow);

    // Trang thống kê
    $s2 = $x->addSheet('Thống kê');
    $x->setCols($s2, [30, 18, 18]);
    $x->addRow($s2, [['v' => 'THỐNG KÊ LỚP ' . $course['title'], 's' => 5]], 5, 24);
    $x->addEmptyRow($s2);
    $valid = array_filter($gb['totals'], function ($t) { return $t['score10'] !== null; });
    $avg = $valid ? array_sum(array_column($valid, 'score10')) / count($valid) : null;
    $x->addRow($s2, ['Chỉ số', 'Giá trị'], 1);
    $x->addRow($s2, ['Tổng số học viên', count($gb['students'])]);
    $x->addRow($s2, ['Số học viên đã có điểm', count($valid)]);
    $x->addRow($s2, ['Điểm trung bình lớp (thang 10)', $avg !== null ? round($avg, 2) : '—']);
    $x->addRow($s2, ['Số đầu điểm', count($gb['items'])]);
    $x->addEmptyRow($s2);
    $x->addRow($s2, ['Xếp loại', 'Số lượng', 'Tỉ lệ %'], 1);
    $ranks = ['Xuất sắc' => 0, 'Giỏi' => 0, 'Khá' => 0, 'Trung bình' => 0, 'Chưa đạt' => 0, 'Chưa có điểm' => 0];
    foreach ($gb['totals'] as $t) {
        list($rk) = grade_rank($t['score10']);
        if ($t['score10'] === null) $rk = 'Chưa có điểm';
        if (isset($ranks[$rk])) $ranks[$rk]++;
    }
    $tot = max(1, count($gb['students']));
    foreach ($ranks as $k => $v) $x->addRow($s2, [$k, $v, round($v / $tot * 100, 1)]);

    Log::write('export_gradebook_xlsx', 'course', $courseId);
    $x->download(export_filename('bang-diem-' . $course['code'], 'xlsx'));
}

// ------------------------------------------------------------------ danh sách lớp
function export_roster()
{
    Auth::requireTeacher();
    $course = DB::find('courses', inp_int('id'));
    if (!$course || !Auth::canManageCourse($course)) { Auth::deny(); return; }

    $students = DB::all('SELECT u.*, e.status AS enroll_status, e.enrolled_at, e.progress
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status <> "removed" ORDER BY u.full_name', ['c' => $course['id']]);

    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Email', 'Điện thoại', 'Lớp/Đơn vị', 'Trạng thái', 'Ngày ghi danh', 'Tiến độ (%)'];
    $body = [];
    foreach ($students as $n => $s) {
        $body[] = [$n + 1, $s['full_name'], $s['username'], $s['email'], $s['phone'], $s['org_unit'],
                   status_label($s['enroll_status']), fmt_date($s['enrolled_at']), (int)$s['progress']];
    }

    if (inp('format') === 'csv') {
        $rows = [$head];
        foreach ($body as $r) $rows[] = $r;
        export_csv(export_filename('danh-sach-lop-' . $course['code'], 'csv'), $rows);
    }
    if (inp('format') === 'pdf') {
        $pdf = export_pdf_new('DANH SÁCH HỌC VIÊN', $course['title'] . ' (' . $course['code'] . ')', 'P');
        $pdf->marginTop = 66;
        $pdf->addPage();
        $pdf->table($head, $body, [26, 120, 66, 120, 62, 60, 50], [
            'align' => ['C', 'L', 'L', 'L', 'C', 'L', 'C'], 'fontSize' => 8,
            'headBg' => Settings::get('primary_color', '#6C5CE7'),
        ]);
        export_pdf_footer($pdf);
        $pdf->output(export_filename('danh-sach-' . $course['code'], 'pdf'), 'D');
    }

    $x = new Xlsx();
    $s = $x->addSheet('Danh sách lớp');
    $x->setCols($s, [6, 26, 16, 28, 14, 16, 14, 14, 11]);
    $x->addRow($s, [['v' => 'DANH SÁCH HỌC VIÊN — ' . $course['title'], 's' => 5]], 5, 24);
    $x->merge($s, 'A1:I1');
    $x->addRow($s, [['v' => 'Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->download(export_filename('danh-sach-' . $course['code'], 'xlsx'));
}

// ------------------------------------------------------------------ tiến độ
function export_progress()
{
    Auth::requireTeacher();
    $course = DB::find('courses', inp_int('id'));
    if (!$course || !Auth::canManageCourse($course)) { Auth::deny(); return; }

    $items = DB::all('SELECT * FROM {P}items WHERE course_id = :c AND visible = 1 ORDER BY position, id', ['c' => $course['id']]);
    $students = DB::all('SELECT u.id, u.full_name, u.username FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed") ORDER BY u.full_name', ['c' => $course['id']]);
    $done = [];
    foreach (DB::all('SELECT user_id, item_id, status FROM {P}completions WHERE course_id = :c', ['c' => $course['id']]) as $r) {
        $done[(int)$r['user_id']][(int)$r['item_id']] = $r['status'];
    }

    $head = ['STT', 'Họ và tên', 'Tài khoản'];
    foreach ($items as $it) $head[] = $it['title'];
    $head[] = 'Hoàn thành';
    $head[] = 'Tỉ lệ %';

    $body = [];
    foreach ($students as $n => $st) {
        $row = [$n + 1, $st['full_name'], $st['username']];
        $c = 0;
        foreach ($items as $it) {
            $ok = isset($done[(int)$st['id']][(int)$it['id']]) && $done[(int)$st['id']][(int)$it['id']] === 'completed';
            if ($ok) $c++;
            $row[] = $ok ? 'x' : '';
        }
        $row[] = $c;
        $row[] = count($items) ? round($c / count($items) * 100, 1) : 0;
        $body[] = $row;
    }

    if (inp('format') === 'csv') {
        $rows = [$head];
        foreach ($body as $r) $rows[] = $r;
        export_csv(export_filename('tien-do-' . $course['code'], 'csv'), $rows);
    }

    $x = new Xlsx();
    $s = $x->addSheet('Tiến độ học tập');
    $x->addRow($s, [['v' => 'TIẾN ĐỘ HỌC TẬP — ' . $course['title'], 's' => 5]], 5, 24);
    $x->addRow($s, [['v' => 'Dấu "x" là đã hoàn thành mục nội dung · Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 40);
    foreach ($body as $r) $x->addRow($s, $r);
    $w = [6, 26, 16];
    foreach ($items as $it) $w[] = 12;
    $w[] = 12; $w[] = 10;
    $x->setCols($s, $w);
    $x->freeze($s, 'D5');
    $x->download(export_filename('tien-do-' . $course['code'], 'xlsx'));
}

// ------------------------------------------------------------------ một bài tập
function export_assignment()
{
    Auth::requireTeacher();
    $item = DB::find('items', inp_int('id'));
    if (!$item) { render_404(); return; }
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canManageCourse($course)) { Auth::deny(); return; }

    $students = DB::all('SELECT u.id, u.full_name, u.username, u.org_unit
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed") ORDER BY u.full_name',
                        ['c' => $course['id']]);
    $subs = [];
    foreach (DB::all('SELECT * FROM {P}submissions WHERE item_id = :i AND status <> "draft" ORDER BY attempt DESC, id DESC',
                     ['i' => $item['id']]) as $s) {
        if (!isset($subs[(int)$s['user_id']])) $subs[(int)$s['user_id']] = $s;
    }

    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Lớp', 'Trạng thái', 'Thời điểm nộp', 'Nộp trễ', 'Điểm', 'Điểm AI', 'Nhận xét'];
    $body = [];
    foreach ($students as $n => $st) {
        $s = isset($subs[(int)$st['id']]) ? $subs[(int)$st['id']] : null;
        $body[] = [
            $n + 1, $st['full_name'], $st['username'], $st['org_unit'],
            $s ? ($s['score'] !== null ? 'Đã chấm' : 'Chờ chấm') : 'Chưa nộp',
            $s ? fmt_datetime($s['submitted_at']) : '',
            $s && $s['is_late'] ? 'x' : '',
            $s && $s['score'] !== null ? (float)$s['score'] : '',
            $s && $s['ai_score'] !== null ? (float)$s['ai_score'] : '',
            $s ? str_limit(strip_tags((string)$s['feedback']), 300) : '',
        ];
    }

    if (inp('format') === 'csv') {
        $rows = [$head];
        foreach ($body as $r) $rows[] = $r;
        export_csv(export_filename('bai-tap-' . $item['title'], 'csv'), $rows);
    }
    if (inp('format') === 'pdf') {
        $pdf = export_pdf_new('KẾT QUẢ BÀI TẬP', $item['title'] . ' — ' . $course['title'], 'L');
        $pdf->marginTop = 66;
        $pdf->addPage();
        $h2 = ['STT', 'Họ và tên', 'Tài khoản', 'Trạng thái', 'Thời điểm nộp', 'Trễ', 'Điểm', 'Điểm AI'];
        $b2 = [];
        foreach ($body as $r) $b2[] = [$r[0], $r[1], $r[2], $r[4], $r[5], $r[6], $r[7] === '' ? '—' : score_fmt($r[7]), $r[8] === '' ? '—' : score_fmt($r[8])];
        $pdf->table($h2, $b2, [28, 160, 90, 80, 110, 40, 60, 60],
            ['align' => ['C', 'L', 'L', 'C', 'C', 'C', 'C', 'C'], 'fontSize' => 8.5,
             'headBg' => Settings::get('primary_color', '#6C5CE7')]);
        export_pdf_footer($pdf);
        $pdf->output(export_filename('bai-tap-' . $item['title'], 'pdf'), 'D');
    }

    $x = new Xlsx();
    $s = $x->addSheet('Kết quả bài tập');
    $x->setCols($s, [6, 26, 16, 14, 14, 18, 8, 10, 10, 50]);
    $x->addRow($s, [['v' => 'KẾT QUẢ BÀI TẬP: ' . $item['title'], 's' => 5]], 5, 24);
    $x->merge($s, 'A1:J1');
    $x->addRow($s, [['v' => $course['title'] . ' · Thang điểm ' . score_fmt($item['max_points'])
        . ' · Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->download(export_filename('bai-tap-' . $item['title'], 'xlsx'));
}

// ------------------------------------------------------------------ trắc nghiệm
function export_quiz()
{
    Auth::requireTeacher();
    $item = DB::find('items', inp_int('id'));
    if (!$item) { render_404(); return; }
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canManageCourse($course)) { Auth::deny(); return; }

    $rows = DB::all('SELECT a.*, u.full_name, u.username, u.org_unit FROM {P}attempts a
                     JOIN {P}users u ON u.id = a.user_id
                     WHERE a.item_id = :i AND a.status <> "in_progress"
                     ORDER BY u.full_name, a.number', ['i' => $item['id']]);

    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Lớp', 'Lần làm', 'Bắt đầu', 'Nộp lúc', 'Điểm', 'Điểm tối đa', 'Tỉ lệ %'];
    $body = [];
    foreach ($rows as $n => $r) {
        $body[] = [$n + 1, $r['full_name'], $r['username'], $r['org_unit'], (int)$r['number'],
                   fmt_datetime($r['started_at']), fmt_datetime($r['finished_at']),
                   $r['score'] !== null ? (float)$r['score'] : '', (float)$r['max_score'],
                   $r['max_score'] > 0 ? round($r['score'] / $r['max_score'] * 100, 1) : 0];
    }

    if (inp('format') === 'csv') {
        $out = [$head];
        foreach ($body as $r) $out[] = $r;
        export_csv(export_filename('trac-nghiem-' . $item['title'], 'csv'), $out);
    }

    $x = new Xlsx();
    $s = $x->addSheet('Kết quả trắc nghiệm');
    $x->setCols($s, [6, 26, 16, 14, 9, 18, 18, 10, 12, 10]);
    $x->addRow($s, [['v' => 'KẾT QUẢ TRẮC NGHIỆM: ' . $item['title'], 's' => 5]], 5, 24);
    $x->merge($s, 'A1:J1');
    $x->addRow($s, [['v' => $course['title'] . ' · Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->download(export_filename('trac-nghiem-' . $item['title'], 'xlsx'));
}

// ------------------------------------------------------------------ SCORM
function export_scorm()
{
    Auth::requireTeacher();
    require_once LMS_APP . '/Scorm.php';
    $item = DB::find('items', inp_int('id'));
    if (!$item) { render_404(); return; }
    $course = DB::find('courses', $item['course_id']);
    if (!Auth::canManageCourse($course)) { Auth::deny(); return; }
    $pkg = Scorm::package($item['id']);
    if (!$pkg) { flash_err('Chưa có gói SCORM.'); back(); }

    $students = DB::all('SELECT u.id, u.full_name, u.username, u.org_unit
                         FROM {P}enrollments e JOIN {P}users u ON u.id = e.user_id
                         WHERE e.course_id = :c AND e.status IN ("active","completed") ORDER BY u.full_name',
                        ['c' => $course['id']]);

    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Lớp', 'Trạng thái', 'Điểm', 'Điểm tối đa', 'Thời gian học', 'Vị trí'];
    $body = [];
    foreach ($students as $n => $st) {
        $s = Scorm::summary($pkg['id'], $st['id']);
        $body[] = [$n + 1, $st['full_name'], $st['username'], $st['org_unit'],
                   Scorm::statusLabel($s['status']), $s['score'], $s['score_max'], $s['time'], $s['location']];
    }

    if (inp('format') === 'csv') {
        $out = [$head];
        foreach ($body as $r) $out[] = $r;
        export_csv(export_filename('scorm-' . $item['title'], 'csv'), $out);
    }

    $x = new Xlsx();
    $s = $x->addSheet('Kết quả SCORM');
    $x->setCols($s, [6, 26, 16, 14, 18, 10, 12, 16, 24]);
    $x->addRow($s, [['v' => 'KẾT QUẢ SCORM: ' . $item['title'], 's' => 5]], 5, 24);
    $x->merge($s, 'A1:I1');
    $x->addRow($s, [['v' => $course['title'] . ' · SCORM ' . $pkg['version'] . ' · Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->download(export_filename('scorm-' . $item['title'], 'xlsx'));
}

// ------------------------------------------------------------------ người dùng (quản trị)
function export_users()
{
    Auth::requireAdmin();
    $rows = DB::all('SELECT * FROM {P}users ORDER BY role, full_name');
    $head = ['STT', 'Họ và tên', 'Tài khoản', 'Email', 'Vai trò', 'Trạng thái', 'Điện thoại', 'Lớp/Đơn vị', 'Đăng nhập gần nhất', 'Ngày tạo'];
    $body = [];
    foreach ($rows as $n => $u) {
        $body[] = [$n + 1, $u['full_name'], $u['username'], $u['email'], role_label($u['role']),
                   status_label($u['status']), $u['phone'], $u['org_unit'],
                   fmt_datetime($u['last_login'], ''), fmt_datetime($u['created_at'], '')];
    }

    if (inp('format') === 'csv') {
        $out = [$head];
        foreach ($body as $r) $out[] = $r;
        export_csv(export_filename('nguoi-dung', 'csv'), $out);
    }
    if (inp('format') === 'pdf') {
        $pdf = export_pdf_new('DANH SÁCH NGƯỜI DÙNG', Settings::get('site_name'), 'L');
        $pdf->marginTop = 66;
        $pdf->addPage();
        $h2 = ['STT', 'Họ và tên', 'Tài khoản', 'Email', 'Vai trò', 'Trạng thái', 'Đăng nhập gần nhất'];
        $b2 = [];
        foreach ($body as $r) $b2[] = [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[8]];
        $pdf->table($h2, $b2, [28, 150, 90, 170, 80, 74, 100],
            ['align' => ['C', 'L', 'L', 'L', 'C', 'C', 'C'], 'fontSize' => 8.5,
             'headBg' => Settings::get('primary_color', '#6C5CE7')]);
        export_pdf_footer($pdf);
        $pdf->output(export_filename('nguoi-dung', 'pdf'), 'D');
    }

    $x = new Xlsx();
    $s = $x->addSheet('Người dùng');
    $x->setCols($s, [6, 26, 16, 28, 14, 14, 14, 18, 18, 18]);
    $x->addRow($s, [['v' => 'DANH SÁCH NGƯỜI DÙNG — ' . Settings::get('site_name'), 's' => 5]], 5, 24);
    $x->merge($s, 'A1:J1');
    $x->addRow($s, [['v' => 'Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->autofilter($s, 'A4:J4');
    $x->download(export_filename('nguoi-dung', 'xlsx'));
}

// ------------------------------------------------------------------ kết quả của học sinh
function export_mine()
{
    Auth::requireLogin();
    $uid = Auth::id();
    $courses = DB::all('SELECT c.* FROM {P}enrollments e JOIN {P}courses c ON c.id = e.course_id
                        WHERE e.user_id = :u AND e.status IN ("active","completed") ORDER BY c.title', ['u' => $uid]);

    $head = ['Khoá học', 'Đầu điểm', 'Loại', 'Điểm', 'Điểm tối đa', 'Thang 10'];
    $body = [];
    foreach ($courses as $c) {
        $items = DB::all('SELECT * FROM {P}items WHERE course_id = :c AND graded = 1 ORDER BY position, id', ['c' => $c['id']]);
        foreach ($items as $it) {
            $score = DB::val('SELECT MAX(score) FROM {P}submissions WHERE item_id = :i AND user_id = :u AND score IS NOT NULL',
                             ['i' => $it['id'], 'u' => $uid], null);
            if ($score === null) {
                $score = DB::val('SELECT score FROM {P}completions WHERE item_id = :i AND user_id = :u AND score IS NOT NULL',
                                 ['i' => $it['id'], 'u' => $uid], null);
            }
            $m = item_type_meta($it['type']);
            $body[] = [$c['title'], $it['title'], $m[1], $score === null ? '' : (float)$score, (float)$it['max_points'],
                       ($score !== null && $it['max_points'] > 0) ? round($score / $it['max_points'] * 10, 2) : ''];
        }
    }

    if (inp('format') === 'pdf') {
        $pdf = export_pdf_new('PHIẾU KẾT QUẢ HỌC TẬP', Auth::name(), 'P');
        $pdf->marginTop = 66;
        $pdf->addPage();
        $b2 = [];
        foreach ($body as $r) {
            $b2[] = [str_limit($r[0], 24), str_limit($r[1], 30), $r[3] === '' ? '—' : score_fmt($r[3]),
                     score_fmt($r[4]), $r[5] === '' ? '—' : score_fmt($r[5])];
        }
        $pdf->table(['Khoá học', 'Đầu điểm', 'Điểm', 'Tối đa', 'Thang 10'], $b2, [150, 170, 55, 55, 60],
            ['align' => ['L', 'L', 'C', 'C', 'C'], 'fontSize' => 8.5,
             'headBg' => Settings::get('primary_color', '#6C5CE7')]);
        export_pdf_footer($pdf);
        $pdf->output(export_filename('ket-qua-hoc-tap', 'pdf'), 'D');
    }

    $x = new Xlsx();
    $s = $x->addSheet('Kết quả học tập');
    $x->setCols($s, [30, 34, 18, 10, 12, 10]);
    $x->addRow($s, [['v' => 'KẾT QUẢ HỌC TẬP — ' . Auth::name(), 's' => 5]], 5, 24);
    $x->merge($s, 'A1:F1');
    $x->addRow($s, [['v' => 'Xuất lúc ' . date('H:i d/m/Y'), 's' => 6]], 6);
    $x->addEmptyRow($s);
    $x->addRow($s, $head, 1, 30);
    foreach ($body as $r) $x->addRow($s, $r);
    $x->freeze($s, 'A5');
    $x->download(export_filename('ket-qua-hoc-tap', 'xlsx'));
}
