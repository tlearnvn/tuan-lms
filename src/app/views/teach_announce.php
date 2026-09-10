<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Thông báo'],
]) ?>

<div class="page-head">
    <h1>📢 Thông báo lớp học</h1>
    <div class="sub"><?= e($course['title']) ?></div>
</div>

<div class="grid" style="grid-template-columns:1fr 360px;align-items:start">
    <div>
        <?php if (!$rows): ?>
            <div class="card"><?= empty_state('📭', 'Chưa có thông báo', 'Đăng thông báo đầu tiên để nhắc nhở cả lớp.') ?></div>
        <?php else: foreach ($rows as $a): ?>
            <div class="card mb-2">
                <div class="flex-between flex-wrap">
                    <h3 style="margin-bottom:4px"><?= $a['pinned'] ? '📌 ' : '' ?><?= e($a['title']) ?></h3>
                    <form method="post" onsubmit="return confirm('Xoá thông báo này?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                    </form>
                </div>
                <div class="rich-content"><?= nl2html($a['content']) ?></div>
                <div class="tiny muted mt-2">✍️ <?= e($a['author']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span class="emoji">✏️</span> Đăng thông báo mới</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
            <div class="form-group">
                <label>Tiêu đề <span class="req">*</span></label>
                <input type="text" name="title" required placeholder="vd: Lịch kiểm tra giữa kỳ">
            </div>
            <div class="form-group">
                <label>Nội dung</label>
                <textarea name="content" rows="6" data-editor data-autogrow placeholder="Nội dung thông báo gửi tới cả lớp…"></textarea>
            </div>
            <div class="check-row">
                <input type="checkbox" id="pinned" name="pinned" value="1">
                <label for="pinned">📌 Ghim lên đầu</label>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Gửi tới cả lớp</button>
            <p class="tiny muted mt-2">Tất cả học viên đang hoạt động sẽ nhận được thông báo trong hệ thống.</p>
        </form>
    </div>
</div>
