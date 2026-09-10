<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title']],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📥 <?= e($item['title']) ?></h1>
        <div class="sub">Danh sách bài nộp của lớp · Thang điểm <?= score_fmt($item['max_points']) ?></div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('teach/item/edit', ['id' => $item['id']])) ?>">⚙️ Cấu hình bài tập</a>
        <a class="btn btn-ghost" href="<?= e(url('export/assignment', ['id' => $item['id'], 'format' => 'xlsx'])) ?>">📗 Xuất Excel</a>
        <a class="btn btn-ghost" href="<?= e(url('export/assignment', ['id' => $item['id'], 'format' => 'pdf'])) ?>">📕 Xuất PDF</a>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">👥</div>
        <div><div class="stat-value"><?= num($stats['total']) ?></div><div class="stat-label">Học viên trong lớp</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">📤</div>
        <div><div class="stat-value"><?= num($stats['submitted']) ?></div><div class="stat-label">Đã nộp bài</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">✅</div>
        <div><div class="stat-value"><?= num($stats['graded']) ?></div><div class="stat-label">Đã chấm</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(253,203,110,.2)">📊</div>
        <div><div class="stat-value"><?= $stats['avg'] !== null ? score_fmt(round($stats['avg'], 2)) : '—' ?></div>
             <div class="stat-label">Điểm trung bình</div></div></div>
</div>

<div class="card card-pad-0">
    <div class="card-header">
        <div class="flex-center gap-1 flex-wrap">
            <?php
            $filters = ['all' => 'Tất cả', 'submitted' => 'Chờ chấm', 'graded' => 'Đã chấm', 'missing' => 'Chưa nộp'];
            foreach ($filters as $k => $lb): ?>
                <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?>"
                   href="<?= e(url('assign/list', ['id' => $item['id'], 'filter' => $k])) ?>"><?= e($lb) ?></a>
            <?php endforeach; ?>
        </div>
        <input type="search" placeholder="🔍 Tìm học sinh…" data-filter-table="#tbl-subs" style="max-width:260px">
    </div>

    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data" id="tbl-subs">
            <thead>
                <tr>
                    <th style="width:34px">#</th>
                    <th>Học sinh</th>
                    <th class="center">Trạng thái</th>
                    <th class="center">Thời điểm nộp</th>
                    <th class="center">Tệp</th>
                    <th class="center">Điểm</th>
                    <th class="center">AI</th>
                    <th class="right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8"><?= empty_state('🗂️', 'Chưa có dữ liệu', 'Không có học sinh nào khớp bộ lọc này.') ?></td></tr>
            <?php else: foreach ($rows as $i => $r):
                $st = $r['student']; $s = $r['sub']; ?>
                <tr>
                    <td class="center muted"><?= $i + 1 ?></td>
                    <td>
                        <div class="user-row">
                            <?= avatar_tag($st, 34) ?>
                            <div><div class="u-name"><?= e($st['full_name']) ?></div>
                                 <div class="u-sub"><?= e($st['username']) ?></div></div>
                        </div>
                    </td>
                    <td class="center">
                        <?php if (!$s) echo chip('Chưa nộp', 'chip-gray');
                        elseif ($s['score'] !== null) echo chip('Đã chấm', 'chip-green', '✓');
                        else echo chip('Chờ chấm', 'chip-blue', '🕓'); ?>
                        <?php if ($s && $s['is_late']) echo ' ' . chip('Trễ', 'chip-orange'); ?>
                    </td>
                    <td class="center small"><?= $s ? e(fmt_datetime($s['submitted_at'])) : '—' ?></td>
                    <td class="center"><?= $s && $s['nfiles'] ? '📎 ' . (int)$s['nfiles'] : '—' ?></td>
                    <td class="center bold">
                        <?= $s && $s['score'] !== null ? score_fmt($s['score']) . '<span class="muted tiny">/' . score_fmt($item['max_points']) . '</span>' : '—' ?>
                    </td>
                    <td class="center">
                        <?php if ($s && $s['ai_score'] !== null) echo chip(score_fmt($s['ai_score']), 'chip-purple', '🤖');
                        elseif ($s && $s['ai_status'] === 'error') echo chip('Lỗi', 'chip-red', '🤖');
                        else echo '<span class="muted">—</span>'; ?>
                    </td>
                    <td class="right">
                        <?php if ($s): ?>
                            <a class="btn btn-sm <?= $s['score'] === null ? 'btn-primary' : 'btn-ghost' ?>"
                               href="<?= e(url('assign/grade', ['id' => $item['id'], 'sub' => $s['id']])) ?>">
                                <?= $s['score'] === null ? 'Chấm bài' : 'Xem lại' ?>
                            </a>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
