<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title']],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>❓ <?= e($item['title']) ?></h1>
        <div class="sub"><?= num($questionCount) ?> câu hỏi · Tổng <?= score_fmt($totalPoints) ?> điểm ·
            quy đổi về thang <?= score_fmt($item['max_points']) ?></div>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('teach/questions', ['id' => $item['id']])) ?>">🧩 Ngân hàng câu hỏi</a>
        <a class="btn btn-ghost" href="<?= e(url('teach/item/edit', ['id' => $item['id']])) ?>">⚙️ Cấu hình</a>
        <a class="btn btn-ghost" href="<?= e(url('export/quiz', ['id' => $item['id'], 'format' => 'xlsx'])) ?>">📗 Xuất Excel</a>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <?php
    $done = array_filter($attempts, function ($a) { return $a['status'] !== 'in_progress'; });
    $scores = [];
    foreach ($done as $a) if ($a['max_score'] > 0) $scores[] = $a['score'] / $a['max_score'] * 100;
    $avg = $scores ? array_sum($scores) / count($scores) : null;
    $pass = $scores ? count(array_filter($scores, function ($s) use ($quiz) { return $s >= (float)$quiz['pass_score']; })) : 0;
    ?>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">🧑‍🎓</div>
        <div><div class="stat-value"><?= num(count($done)) ?></div><div class="stat-label">Lượt làm bài</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">📊</div>
        <div><div class="stat-value"><?= $avg !== null ? round($avg) . '%' : '—' ?></div><div class="stat-label">Điểm trung bình</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">🎯</div>
        <div><div class="stat-value"><?= num($pass) ?></div><div class="stat-label">Lượt đạt</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(253,203,110,.2)">⏱️</div>
        <div><div class="stat-value"><?= $quiz['time_limit'] > 0 ? (int)$quiz['time_limit'] . '′' : '∞' ?></div>
             <div class="stat-label">Thời gian làm bài</div></div></div>
</div>

<?php if ($scores): ?>
    <div class="card mb-3">
        <div class="card-title"><span class="emoji">📈</span> Phổ điểm (theo %)</div>
        <?php
        $buckets = [0, 0, 0, 0, 0];
        foreach ($scores as $s) {
            if ($s < 20) $buckets[0]++;
            elseif ($s < 40) $buckets[1]++;
            elseif ($s < 60) $buckets[2]++;
            elseif ($s < 80) $buckets[3]++;
            else $buckets[4]++;
        }
        $data = [
            ['label' => '0–20%', 'value' => $buckets[0], 'color' => '#E74C3C'],
            ['label' => '20–40%', 'value' => $buckets[1], 'color' => '#E17055'],
            ['label' => '40–60%', 'value' => $buckets[2], 'color' => '#FDCB6E'],
            ['label' => '60–80%', 'value' => $buckets[3], 'color' => '#0984E3'],
            ['label' => '80–100%', 'value' => $buckets[4], 'color' => '#00B894'],
        ];
        echo svg_bar_chart($data, ['height' => 220]);
        ?>
    </div>
<?php endif; ?>

<div class="card card-pad-0">
    <div class="card-header">
        <b>Danh sách lượt làm bài</b>
        <input type="search" placeholder="🔍 Tìm học sinh…" data-filter-table="#tbl-attempts" style="max-width:240px">
    </div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data" id="tbl-attempts">
            <thead><tr><th>Học sinh</th><th class="center">Lần</th><th class="center">Bắt đầu</th>
                <th class="center">Nộp lúc</th><th class="center">Điểm</th><th class="center">Trạng thái</th><th class="right"></th></tr></thead>
            <tbody>
            <?php if (!$attempts): ?>
                <tr><td colspan="7"><?= empty_state('📭', 'Chưa có ai làm bài', 'Kết quả sẽ hiển thị ở đây sau khi học sinh nộp bài.') ?></td></tr>
            <?php else: foreach ($attempts as $a):
                $p = $a['max_score'] > 0 ? round($a['score'] / $a['max_score'] * 100) : 0; ?>
                <tr>
                    <td><div class="user-row"><?= avatar_tag($a, 32) ?>
                        <div><div class="u-name"><?= e($a['full_name']) ?></div>
                             <div class="u-sub"><?= e($a['username']) ?></div></div></div></td>
                    <td class="center">#<?= (int)$a['number'] ?></td>
                    <td class="center small"><?= e(fmt_datetime($a['started_at'])) ?></td>
                    <td class="center small"><?= e(fmt_datetime($a['finished_at'])) ?></td>
                    <td class="center bold"><?= $a['score'] !== null ? score_fmt($a['score']) . '/' . score_fmt($a['max_score']) . ' <span class="tiny muted">(' . $p . '%)</span>' : '—' ?></td>
                    <td class="center">
                        <?php if ($a['status'] === 'in_progress') echo chip('Đang làm', 'chip-yellow', '⏳');
                        elseif ($a['status'] === 'submitted') echo chip('Chờ chấm tự luận', 'chip-blue', '🕓');
                        else echo chip('Đã chấm', 'chip-green', '✓'); ?>
                    </td>
                    <td class="right"><a class="btn btn-ghost btn-sm" href="<?= e(url('quiz/result', ['a' => $a['id']])) ?>">Xem bài</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
