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
    <?php else:
        // Nhiều cột điểm → ghim cụm cột tổng kết bên phải và nhắc người dùng cuộn ngang
        $wide = count($gb['items']) >= 6;
        $endC = $wide ? ' col-end' : '';
        ?>
        <?php if ($wide): ?>
            <div class="gb-hint">
                <span>↔️ Bảng có <b><?= num(count($gb['items'])) ?></b> cột điểm — <b>cuộn ngang</b> để xem hết.
                    Cột <b>Học sinh</b> và cụm <b>Tổng · Thang 10 · Xếp loại</b> luôn được ghim tại chỗ.</span>
                <span class="tiny">Mỗi cột được đánh số; xem tên đầy đủ ở bảng <b>Chú thích cột điểm</b> bên dưới.</span>
            </div>
        <?php endif; ?>
        <div class="table-wrap table-sticky" style="border:0;border-radius:0;max-height:70vh">
            <table class="data" id="tbl-gb">
                <thead>
                    <tr>
                        <th class="col-fix">Học sinh</th>
                        <?php foreach ($gb['items'] as $k => $it): $m = item_type_meta($it['type']); ?>
                            <th class="center gb-col" style="min-width:92px"
                                title="<?= e(($k + 1) . '. ' . $it['title'] . ' — ' . $m[1] . ' · tối đa ' . score_fmt($it['max_points']) . ' điểm · hệ số ' . score_fmt((float)$it['weight'] ?: 1)) ?>">
                                <span class="gb-num"><?= $k + 1 ?></span> <?= $m[0] ?>
                                <br><span class="gb-tt"><?= e(str_limit($it['title'], 14)) ?></span>
                                <br><span class="tiny" style="text-transform:none">/<?= score_fmt($it['max_points']) ?><?= (float)$it['weight'] != 1 ? ' ×' . score_fmt($it['weight']) : '' ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="center<?= $endC ?> col-end-3">Tổng</th>
                        <th class="center<?= $endC ?> col-end-2">Thang 10</th>
                        <th class="center<?= $endC ?> col-end-1">Xếp loại</th>
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
                        <td class="center small<?= $endC ?> col-end-3"><?= $t['max'] > 0 ? score_fmt($t['got']) . '/' . score_fmt($t['max']) : '—' ?></td>
                        <td class="center bold<?= $endC ?> col-end-2" style="font-size:1.02rem"><?= $t['score10'] !== null ? score_fmt($t['score10']) : '—' ?></td>
                        <td class="center<?= $endC ?> col-end-1"><?= chip($rank, 'chip-' . $col) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($gb['students'] && $gb['items']): ?>
    <details class="card mt-3" <?= count($gb['items']) >= 6 ? 'open' : '' ?>>
        <summary class="gb-legend-sum"><span class="emoji">🔢</span> Chú thích cột điểm
            <span class="tiny">(<?= num(count($gb['items'])) ?> đầu điểm)</span></summary>
        <div class="table-wrap mt-2">
            <table class="data">
                <thead><tr>
                    <th class="center" style="width:60px">Cột</th>
                    <th>Tên đầu điểm</th>
                    <th style="width:170px">Loại</th>
                    <th class="center" style="width:110px">Điểm tối đa</th>
                    <th class="center" style="width:90px">Hệ số</th>
                </tr></thead>
                <tbody>
                <?php foreach ($gb['items'] as $k => $it): $m = item_type_meta($it['type']); ?>
                    <tr>
                        <td class="center"><span class="gb-num"><?= $k + 1 ?></span></td>
                        <td><a href="<?= e(url('item/view', ['id' => $it['id']])) ?>"><?= e($it['title']) ?></a></td>
                        <td><?= $m[0] ?> <?= e($m[1]) ?></td>
                        <td class="center"><?= score_fmt($it['max_points']) ?></td>
                        <td class="center"><?= score_fmt((float)$it['weight'] ?: 1) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
<?php endif; ?>
