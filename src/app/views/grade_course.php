<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Sổ điểm'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📊 Sổ điểm lớp</h1>
        <div class="sub"><?= e($course['title']) ?> · <?= num(count($gb['students'])) ?> học viên ·
            <?= num(count($gb['items'])) ?> cột điểm</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">📗 Excel</a>
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'pdf'])) ?>">📕 PDF</a>
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'csv'])) ?>">📄 CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('teach/reports', ['course' => $course['id']])) ?>">📈 Thống kê</a>
    </div>
</div>

<?php
$valid = array_filter($gb['totals'], function ($t) { return $t['score10'] !== null; });
$avg = $valid ? array_sum(array_column($valid, 'score10')) / count($valid) : null;
?>
<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">👥</div>
        <div><div class="stat-value"><?= num(count($gb['students'])) ?></div><div class="stat-label">Học viên</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">🏅</div>
        <div><div class="stat-value"><?= $avg !== null ? score_fmt(round($avg, 2)) : '—' ?></div>
             <div class="stat-label">Điểm TB lớp (thang 10)</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">✅</div>
        <div><div class="stat-value"><?= num(count($valid)) ?></div><div class="stat-label">Học viên đã có điểm</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(253,203,110,.2)">📋</div>
        <div><div class="stat-value"><?= num(count($gb['items'])) ?></div><div class="stat-label">Đầu điểm</div></div></div>
</div>

<?php if (array_sum($dist) > 0): ?>
    <div class="card mb-3">
        <div class="card-title"><span class="emoji">📈</span> Phổ điểm lớp (thang 10)</div>
        <?= svg_bar_chart([
            ['label' => 'Dưới 5', 'value' => $dist[0], 'color' => '#E74C3C'],
            ['label' => '5 – 6.5', 'value' => $dist[1], 'color' => '#FDCB6E'],
            ['label' => '6.5 – 8', 'value' => $dist[2], 'color' => '#0984E3'],
            ['label' => '8 – 9', 'value' => $dist[3], 'color' => '#00B894'],
            ['label' => 'Từ 9', 'value' => $dist[4], 'color' => '#A55EEA'],
        ], ['height' => 220]) ?>
    </div>
<?php endif; ?>

<div class="card card-pad-0">
    <div class="card-header">
        <b>Bảng điểm chi tiết</b>
        <input type="search" placeholder="🔍 Tìm học sinh…" data-filter-table="#tbl-gb" style="max-width:240px">
    </div>

    <?php if (!$gb['students']): ?>
        <?= empty_state('🙋', 'Lớp chưa có học viên', 'Thêm học viên để bắt đầu ghi nhận điểm.') ?>
    <?php elseif (!$gb['items']): ?>
        <?= empty_state('📋', 'Chưa có cột điểm nào', 'Thêm bài tập hoặc bài trắc nghiệm có tính điểm vào khoá học.') ?>
    <?php else: ?>
        <div class="table-wrap table-sticky" style="border:0;border-radius:0;max-height:70vh">
            <table class="data" id="tbl-gb">
                <thead>
                    <tr>
                        <th class="col-fix" style="min-width:200px">Học sinh</th>
                        <?php foreach ($gb['items'] as $it): $m = item_type_meta($it['type']); ?>
                            <th class="center" style="min-width:88px" title="<?= e($it['title']) ?>">
                                <?= $m[0] ?><br><span style="font-weight:600"><?= e(str_limit($it['title'], 14, '')) ?></span>
                                <br><span class="tiny" style="text-transform:none">/<?= score_fmt($it['max_points']) ?><?= (float)$it['weight'] != 1 ? ' ×' . score_fmt($it['weight']) : '' ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="center" style="min-width:96px">Tổng</th>
                        <th class="center" style="min-width:80px">Thang 10</th>
                        <th class="center" style="min-width:100px">Xếp loại</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($gb['students'] as $st):
                    $uid = (int)$st['id'];
                    $t = $gb['totals'][$uid];
                    list($rank, $col) = grade_rank($t['score10']); ?>
                    <tr>
                        <td class="col-fix">
                            <div class="user-row"><?= avatar_tag($st, 30) ?>
                                <div><div class="u-name small"><?= e($st['full_name']) ?></div>
                                     <div class="u-sub"><?= e($st['username']) ?></div></div></div>
                        </td>
                        <?php foreach ($gb['items'] as $it):
                            $s = isset($gb['scores'][$uid][(int)$it['id']]) ? $gb['scores'][$uid][(int)$it['id']] : null;
                            $ratio = $it['max_points'] > 0 && $s !== null ? $s / $it['max_points'] : null; ?>
                            <td class="center">
                                <?php if ($s === null): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <b style="color:<?= $ratio >= 0.8 ? 'var(--success)' : ($ratio >= 0.5 ? 'var(--info)' : 'var(--danger)') ?>">
                                        <?= score_fmt($s) ?></b>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="center small"><?= $t['max'] > 0 ? score_fmt($t['got']) . '/' . score_fmt($t['max']) : '—' ?></td>
                        <td class="center bold" style="font-size:1.02rem"><?= $t['score10'] !== null ? score_fmt($t['score10']) : '—' ?></td>
                        <td class="center"><?= chip($rank, 'chip-' . $col) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
