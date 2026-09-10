<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📊 Thống kê &amp; báo cáo</h1>
        <div class="sub">Bức tranh tổng thể về việc học của lớp.</div>
    </div>
    <?php if ($courses): ?>
        <form method="get" action="<?= e(base_url() . 'index.php') ?>" class="page-actions">
            <input type="hidden" name="r" value="teach/reports">
            <select name="course" onchange="this.form.submit()" style="min-width:250px">
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $course && (int)$course['id'] === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['code'] . ' — ' . $c['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($course): ?>
                <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">📗 Excel</a>
                <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'pdf'])) ?>">📕 PDF</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php if (!$course): ?>
    <div class="card"><?= empty_state('📈', 'Chưa có khoá học để thống kê',
        'Hãy tạo khoá học và thêm học viên trước nhé.',
        '<a class="btn btn-primary" href="' . e(url('teach/course/edit')) . '">Tạo khoá học</a>') ?></div>
<?php else: ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">👥</div>
        <div><div class="stat-value"><?= num(count($students)) ?></div><div class="stat-label">Học viên</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">📈</div>
        <div><div class="stat-value"><?= (int)$avgProgress ?>%</div><div class="stat-label">Tiến độ trung bình</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(232,67,147,.12)">🏅</div>
        <div><div class="stat-value"><?= $avgScore !== null ? score_fmt($avgScore) : '—' ?></div>
             <div class="stat-label">Điểm TB (thang 10)</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">🧩</div>
        <div><div class="stat-value"><?= num($itemCount) ?></div><div class="stat-label">Mục nội dung</div></div></div>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr">
    <div class="card">
        <div class="card-title"><span class="emoji">🥧</span> Phân bố xếp loại học lực</div>
        <?php
        $colors = ['Xuất sắc' => '#A55EEA', 'Giỏi' => '#00B894', 'Khá' => '#0984E3',
                   'Trung bình' => '#FDCB6E', 'Chưa đạt' => '#E74C3C', 'Chưa có điểm' => '#B2BEC3'];
        $segments = [];
        foreach ($ranks as $k => $vv) if ($vv > 0) $segments[] = ['label' => $k, 'value' => $vv, 'color' => $colors[$k]];
        ?>
        <div class="flex-center gap-3 flex-wrap" style="justify-content:center">
            <?= svg_donut($segments, ['center' => num(count($students)), 'sub' => 'học viên']) ?>
            <div>
                <?php foreach ($ranks as $k => $vv): ?>
                    <div class="legend-item mb-1">
                        <span class="legend-dot" style="background:<?= $colors[$k] ?>"></span>
                        <span><?= e($k) ?>: <b><?= num($vv) ?></b></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="emoji">📆</span> Hoạt động 14 ngày qua</div>
        <?= svg_bar_chart($series, ['height' => 230]) ?>
        <p class="tiny muted center mt-1">Số lượt nộp bài tập và hoàn thành trắc nghiệm mỗi ngày</p>
    </div>
</div>

<?php if ($itemStats): ?>
    <div class="card mt-3">
        <div class="card-title"><span class="emoji">📊</span> Số học viên hoàn thành theo từng mục có tính điểm</div>
        <?= svg_bar_chart($itemStats, ['height' => 260]) ?>
    </div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-title"><span class="emoji">📤</span> Xuất báo cáo</div>
    <div class="btn-group">
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">📗 Bảng điểm Excel (.xlsx)</a>
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'pdf'])) ?>">📕 Bảng điểm PDF</a>
        <a class="btn btn-ghost" href="<?= e(url('export/gradebook', ['id' => $course['id'], 'format' => 'csv'])) ?>">📄 CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('export/roster', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">👥 Danh sách lớp</a>
        <a class="btn btn-ghost" href="<?= e(url('export/progress', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">📈 Tiến độ học tập</a>
    </div>
</div>

<?php endif; ?>
