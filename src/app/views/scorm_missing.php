<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="content">
    <div class="card center" style="max-width:620px;margin:40px auto">
        <img src="<?= e(asset('img/scorm.svg')) ?>" alt="" style="max-width:180px">
        <h2>Chưa có gói SCORM</h2>
        <p class="muted">Mục "<b><?= e($item['title']) ?></b>" được đặt kiểu SCORM nhưng chưa có gói nội dung nào được tải lên.</p>
        <div class="btn-group" style="justify-content:center">
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= e(url('teach/item/edit', ['id' => $item['id']])) ?>">📦 Tải gói SCORM lên</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(url('course/view', ['id' => $course['id']])) ?>">← Về khoá học</a>
        </div>
    </div>
</div>
