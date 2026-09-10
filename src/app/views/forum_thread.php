<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Thảo luận', 'url' => url('forum/index', ['course' => $course['id']])],
    ['label' => str_limit($thread['title'], 40)],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1><?= $thread['pinned'] ? '📌 ' : '' ?><?= $thread['locked'] ? '🔒 ' : '' ?><?= e($thread['title']) ?></h1>
        <div class="sub">✍️ <?= e($thread['full_name']) ?> · <?= e(fmt_datetime($thread['created_at'])) ?> ·
            👁️ <?= num($thread['views']) ?> lượt xem</div>
    </div>
    <?php if ($canManage || (int)$thread['user_id'] === Auth::id()): ?>
        <div class="page-actions">
            <?php if ($canManage): ?>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
                    <input type="hidden" name="action" value="toggle_pin">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= $thread['pinned'] ? 'Bỏ ghim' : '📌 Ghim' ?></button>
                </form>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
                    <input type="hidden" name="action" value="toggle_lock">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= $thread['locked'] ? '🔓 Mở khoá' : '🔒 Khoá' ?></button>
                </form>
            <?php endif; ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Xoá chủ đề và toàn bộ phản hồi?')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
                <input type="hidden" name="action" value="delete_thread">
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️ Xoá</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="flex" style="gap:12px;align-items:flex-start">
        <?= avatar_tag($thread, 46) ?>
        <div class="flex-1">
            <div class="flex-center gap-1 mb-1">
                <b><?= e($thread['full_name']) ?></b>
                <?= chip(role_label($thread['role']), $thread['role'] === 'student' ? 'chip-green' : 'chip-blue') ?>
                <span class="tiny muted"><?= e(time_ago($thread['created_at'])) ?></span>
            </div>
            <div class="rich-content"><?= safe_html($thread['content']) ?></div>
        </div>
    </div>
</div>

<h3>💬 <?= num(count($posts)) ?> phản hồi</h3>
<?php foreach ($posts as $p): ?>
    <div class="card mb-2">
        <div class="flex" style="gap:12px;align-items:flex-start">
            <?= avatar_tag($p, 38) ?>
            <div class="flex-1">
                <div class="flex-between">
                    <div class="flex-center gap-1">
                        <b class="small"><?= e($p['full_name']) ?></b>
                        <?= chip(role_label($p['role']), $p['role'] === 'student' ? 'chip-green' : 'chip-blue') ?>
                        <span class="tiny muted"><?= e(time_ago($p['created_at'])) ?></span>
                    </div>
                    <?php if ($canManage || (int)$p['user_id'] === Auth::id()): ?>
                        <form method="post" onsubmit="return confirm('Xoá phản hồi này?')">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="rich-content mt-1"><?= safe_html($p['content']) ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$thread['locked'] || $canManage): ?>
    <div class="card mt-3">
        <div class="card-title"><span class="emoji">✏️</span> Viết phản hồi</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
            <input type="hidden" name="action" value="reply">
            <div class="form-group">
                <textarea name="content" rows="5" data-editor data-autogrow required placeholder="Chia sẻ ý kiến của bạn…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Gửi phản hồi</button>
        </form>
    </div>
<?php else: ?>
    <div class="alert alert-warning"><span>🔒</span><div>Chủ đề đã bị khoá, không nhận thêm phản hồi.</div></div>
<?php endif; ?>
