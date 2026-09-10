<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head">
    <h1>📣 Thông báo toàn hệ thống</h1>
    <div class="sub">Thông báo hiển thị trên bảng điều khiển của mọi thành viên.</div>
</div>

<div class="grid" style="grid-template-columns:1fr 380px;align-items:start">
    <div>
        <?php if (!$rows): ?>
            <div class="card"><?= empty_state('📭', 'Chưa có thông báo', 'Đăng thông báo đầu tiên cho toàn trường.') ?></div>
        <?php endif; ?>
        <?php foreach ($rows as $a): ?>
            <div class="card mb-2">
                <div class="flex-between">
                    <h3 style="margin-bottom:4px"><?= $a['pinned'] ? '📌 ' : '' ?><?= e($a['title']) ?></h3>
                    <form method="post" onsubmit="return confirm('Xoá thông báo này?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                    </form>
                </div>
                <div class="rich-content"><?= nl2html($a['content']) ?></div>
                <div class="tiny muted mt-2"><?= e($a['author']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-title"><span class="emoji">✏️</span> Đăng thông báo</div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Tiêu đề <span class="req">*</span></label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Nội dung</label>
                <textarea name="content" rows="6" data-editor data-autogrow></textarea>
            </div>
            <div class="check-row"><input type="checkbox" id="pinned" name="pinned" value="1"><label for="pinned">📌 Ghim lên đầu</label></div>
            <div class="check-row"><input type="checkbox" id="notify" name="notify" value="1" checked><label for="notify">🔔 Gửi thông báo tới mọi người dùng</label></div>
            <button class="btn btn-primary btn-block" type="submit">Đăng thông báo</button>
        </form>
    </div>
</div>
