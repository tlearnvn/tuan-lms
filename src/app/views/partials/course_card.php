<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** @var array $c khoá học */
$color = $c['color'] ?: color_of($c['title']);
$canView = Auth::check();
$link = url('course/view', ['id' => $c['id']]);
?>
<article class="course-card fade-up">
    <div class="course-cover" style="background:linear-gradient(135deg, <?= e($color) ?>, <?= e(color_of($c['code'])) ?>)">
        <?php if (!empty($c['cover_id'])): ?>
            <img src="<?= e(media_url($c['cover_id'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
        <?php else: ?>
            <span class="cover-pattern"></span>
            <span style="position:relative;z-index:2"><?= !empty($c['category_icon']) ? e($c['category_icon']) : '📘' ?></span>
        <?php endif; ?>
        <span class="course-code"><?= e($c['code']) ?></span>
    </div>
    <div class="course-body">
        <?php if (!empty($c['category_name'])): ?>
            <div class="mb-1">
                <span class="chip" style="background:<?= e($c['category_color'] ?: '#EDEFF7') ?>22;color:<?= e($c['category_color'] ?: '#5B6180') ?>">
                    <?= e($c['category_name']) ?>
                </span>
            </div>
        <?php endif; ?>
        <h3><a href="<?= e($link) ?>"><?= e($c['title']) ?></a></h3>
        <?php if (!empty($c['summary'])): ?>
            <p class="course-desc"><?= e(str_limit($c['summary'], 92)) ?></p>
        <?php endif; ?>
        <div class="course-meta">
            <?php if (!empty($c['teacher_name'])): ?><span>👩‍🏫 <?= e(str_limit($c['teacher_name'], 20)) ?></span><?php endif; ?>
            <span>👥 <?= num(isset($c['student_count']) ? $c['student_count'] : 0) ?> học viên</span>
            <span>🧩 <?= num(isset($c['item_count']) ? $c['item_count'] : 0) ?> mục</span>
        </div>
    </div>
</article>
