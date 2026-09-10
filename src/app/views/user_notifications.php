<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🔔 Thông báo</h1>
        <div class="sub"><?= num($total) ?> thông báo</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('user/notifications', ['mark' => 'all'])) ?>">✓ Đánh dấu đã đọc tất cả</a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card"><?= empty_state('📭', 'Chưa có thông báo nào', 'Các cập nhật về lớp học sẽ hiện ở đây.') ?></div>
<?php else: ?>
    <div class="card card-pad-0">
        <?php foreach ($rows as $n): ?>
            <a class="flex" href="<?= e($n['url'] ?: url('dashboard')) ?>"
               style="gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);color:var(--text);align-items:flex-start;<?= $n['is_read'] ? '' : 'background:rgba(108,92,231,.05)' ?>">
                <span style="font-size:1.4rem"><?= e($n['icon']) ?></span>
                <span class="flex-1">
                    <b><?= e($n['title']) ?></b>
                    <?php if ($n['body']): ?><div class="small muted"><?= e($n['body']) ?></div><?php endif; ?>
                    <div class="tiny muted mt-1"><?= e(fmt_datetime($n['created_at'])) ?> · <?= e(time_ago($n['created_at'])) ?></div>
                </span>
                <?php if (!$n['is_read']) echo chip('Mới', 'chip-purple'); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?= paginate_html($total, $per, $page, ['r' => 'user/notifications']) ?>
<?php endif; ?>
