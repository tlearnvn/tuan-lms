<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title'], 'url' => url('scorm/play', ['id' => $item['id']])],
    ['label' => 'Kết quả lớp'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📊 Kết quả SCORM</h1>
        <div class="sub"><?= e($item['title']) ?> · gói <?= e($pkg['title'] ?: $pkg['identifier']) ?> · SCORM <?= e($pkg['version']) ?></div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('export/scorm', ['id' => $item['id'], 'format' => 'xlsx'])) ?>">📗 Xuất Excel</a>
        <?php if ($pkg['file_id']): ?>
            <a class="btn btn-ghost" href="<?= e(media_url($pkg['file_id'], true)) ?>">⬇️ Tải gói gốc</a>
        <?php endif; ?>
    </div>
</div>

<?php
$completed = 0; $started = 0; $scores = [];
foreach ($rows as $r) {
    $st = strtolower((string)$r['sum']['status']);
    if ($r['sum']['count'] > 0) $started++;
    if (in_array($st, ['completed', 'passed'], true)) $completed++;
    if ($r['sum']['score'] !== null && is_numeric($r['sum']['score'])) $scores[] = (float)$r['sum']['score'];
}
?>
<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">👥</div>
        <div><div class="stat-value"><?= num(count($rows)) ?></div><div class="stat-label">Học viên</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">▶️</div>
        <div><div class="stat-value"><?= num($started) ?></div><div class="stat-label">Đã bắt đầu học</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">✅</div>
        <div><div class="stat-value"><?= num($completed) ?></div><div class="stat-label">Đã hoàn thành</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(232,67,147,.12)">🏅</div>
        <div><div class="stat-value"><?= $scores ? score_fmt(round(array_sum($scores) / count($scores), 2)) : '—' ?></div>
             <div class="stat-label">Điểm trung bình</div></div></div>
</div>

<div class="card card-pad-0">
    <div class="card-header">
        <b>Chi tiết theo học viên</b>
        <input type="search" placeholder="🔍 Tìm học sinh…" data-filter-table="#tbl-scorm" style="max-width:240px">
    </div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data" id="tbl-scorm">
            <thead><tr><th>Học sinh</th><th class="center">Trạng thái</th><th class="center">Điểm</th>
                <th class="center">Thời gian học</th><th class="center">Vị trí đang học</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $s = $r['sum']; $st = strtolower((string)$s['status']); ?>
                <tr>
                    <td><div class="user-row"><?= avatar_tag($r['student'], 32) ?>
                        <div><div class="u-name"><?= e($r['student']['full_name']) ?></div>
                             <div class="u-sub"><?= e($r['student']['username']) ?></div></div></div></td>
                    <td class="center">
                        <?php
                        if (in_array($st, ['completed', 'passed'], true)) echo chip(Scorm::statusLabel($st), 'chip-green', '✓');
                        elseif ($st === 'failed') echo chip(Scorm::statusLabel($st), 'chip-red');
                        elseif ($s['count'] > 0) echo chip(Scorm::statusLabel($st), 'chip-yellow', '⏳');
                        else echo chip('Chưa bắt đầu', 'chip-gray');
                        ?>
                    </td>
                    <td class="center bold"><?= $s['score'] !== null ? e($s['score']) . ($s['score_max'] ? '/' . e($s['score_max']) : '') : '—' ?></td>
                    <td class="center small"><?= e($s['time'] ?: '—') ?></td>
                    <td class="center small muted"><?= e(str_limit($s['location'] ?: '—', 30)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
