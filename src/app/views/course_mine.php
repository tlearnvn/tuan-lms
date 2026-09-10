<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📚 Khoá học của tôi</h1>
        <div class="sub">Bạn đang tham gia <b><?= num(count($courses)) ?></b> khoá học.</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('catalog')) ?>">🔎 Tìm khoá học mới</a>
    </div>
</div>

<?php if (!$courses): ?>
    <div class="card"><?= empty_state('🎒', 'Chưa có khoá học nào',
        'Hãy ghi danh khoá học đầu tiên để bắt đầu hành trình học tập!',
        '<a class="btn btn-primary" href="' . e(url('catalog')) . '">Khám phá khoá học</a>') ?></div>
<?php else: ?>
    <div class="grid grid-auto">
        <?php foreach ($courses as $c):
            $pct = $c['item_count'] > 0 ? round($c['done_count'] / $c['item_count'] * 100) : 0; ?>
            <div class="course-card fade-up">
                <div class="course-cover" style="background:linear-gradient(135deg,<?= e($c['color'] ?: color_of($c['title'])) ?>,<?= e(color_of($c['code'])) ?>)">
                    <?php if ($c['cover_id']): ?><img src="<?= e(media_url($c['cover_id'])) ?>" alt="" loading="lazy">
                    <?php else: ?><span class="cover-pattern"></span><span style="position:relative;z-index:2"><?= e($c['category_icon'] ?: '📘') ?></span><?php endif; ?>
                    <span class="course-code"><?= e($c['code']) ?></span>
                </div>
                <div class="course-body">
                    <div class="mb-1 flex flex-wrap gap-1">
                        <?php if ($c['enroll_status'] === 'pending') echo chip('Chờ duyệt', 'chip-orange', '🕓'); ?>
                        <?php if ($c['enroll_status'] === 'completed') echo chip('Đã hoàn thành', 'chip-green', '🎓'); ?>
                        <?php if ($c['status'] === 'archived') echo chip('Đã lưu trữ', 'chip-gray'); ?>
                        <?php if ($c['category_name']) echo chip($c['category_name'], 'chip-purple'); ?>
                    </div>
                    <h3><a href="<?= e(url('course/view', ['id' => $c['id']])) ?>"><?= e($c['title']) ?></a></h3>
                    <div class="small muted mb-2">👩‍🏫 <?= e($c['teacher_name']) ?></div>
                    <div class="mt-auto">
                        <div class="flex-between small muted mb-1"><span>Tiến độ</span><b><?= $pct ?>%</b></div>
                        <?= progress_bar($pct) ?>
                        <div class="course-meta">
                            <span>🧩 <?= num($c['done_count']) ?>/<?= num($c['item_count']) ?> mục</span>
                            <span>👥 <?= num($c['student_count']) ?></span>
                            <span>📅 <?= e(fmt_date($c['enrolled_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
