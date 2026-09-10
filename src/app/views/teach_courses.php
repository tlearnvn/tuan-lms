<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🎓 Khoá học tôi giảng dạy</h1>
        <div class="sub">Quản lý nội dung, học viên và điểm số của <?= num(count($courses)) ?> khoá học.</div>
    </div>
    <div class="page-actions">
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn-ghost" href="<?= e(url('teach/courses', ['all' => inp('all') === '1' ? '0' : '1'])) ?>">
                <?= inp('all') === '1' ? '👤 Chỉ khoá của tôi' : '🌐 Tất cả khoá học' ?></a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('teach/course/edit')) ?>">➕ Tạo khoá học mới</a>
    </div>
</div>

<?php if (!$courses): ?>
    <div class="card"><?= empty_state('🏗️', 'Chưa có khoá học nào',
        'Bắt đầu bằng việc tạo khoá học đầu tiên của bạn — chỉ mất vài phút!',
        '<a class="btn btn-primary btn-lg" href="' . e(url('teach/course/edit')) . '">➕ Tạo khoá học</a>') ?></div>
<?php else: ?>
    <div class="grid grid-auto">
        <?php foreach ($courses as $c): ?>
            <div class="course-card fade-up">
                <div class="course-cover" style="background:linear-gradient(135deg,<?= e($c['color'] ?: color_of($c['title'])) ?>,<?= e(color_of($c['code'])) ?>)">
                    <?php if ($c['cover_id']): ?><img src="<?= e(media_url($c['cover_id'])) ?>" alt="" loading="lazy">
                    <?php else: ?><span class="cover-pattern"></span><span style="position:relative;z-index:2"><?= e($c['category_icon'] ?: '📘') ?></span><?php endif; ?>
                    <span class="course-code"><?= e($c['code']) ?></span>
                </div>
                <div class="course-body">
                    <div class="mb-1 flex flex-wrap gap-1">
                        <?= chip(status_label($c['status']), $c['status'] === 'published' ? 'chip-green' : ($c['status'] === 'draft' ? 'chip-yellow' : 'chip-gray')) ?>
                        <?php if ($c['pending_count']) echo chip($c['pending_count'] . ' chờ duyệt', 'chip-orange', '🕓'); ?>
                        <?php if ($c['ungraded']) echo chip($c['ungraded'] . ' bài chờ chấm', 'chip-red', '📥'); ?>
                    </div>
                    <h3><a href="<?= e(url('course/view', ['id' => $c['id']])) ?>"><?= e($c['title']) ?></a></h3>
                    <div class="course-meta" style="margin-bottom:10px">
                        <span>👥 <?= num($c['student_count']) ?></span>
                        <span>🧩 <?= num($c['item_count']) ?></span>
                        <?php if (Auth::isAdmin() && $c['teacher_name']): ?><span>👩‍🏫 <?= e(str_limit($c['teacher_name'], 16)) ?></span><?php endif; ?>
                    </div>
                    <div class="btn-group" style="margin-top:auto">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('teach/content', ['id' => $c['id']])) ?>">🧱 Nội dung</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/students', ['id' => $c['id']])) ?>">👥</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('grade/course', ['id' => $c['id']])) ?>">📊</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/course/edit', ['id' => $c['id']])) ?>">⚙️</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
