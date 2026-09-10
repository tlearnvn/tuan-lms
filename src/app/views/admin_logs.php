<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>📜 Nhật ký hoạt động</h1>
        <div class="sub"><?= num($total) ?> bản ghi</div>
    </div>
    <form method="get" action="<?= e(base_url() . 'index.php') ?>" class="page-actions">
        <input type="hidden" name="r" value="admin/logs">
        <select name="action_filter" onchange="this.form.submit()">
            <option value="">— Tất cả hoạt động —</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card card-pad-0">
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data">
            <thead><tr><th class="center">Thời gian</th><th>Người dùng</th><th>Hoạt động</th>
                <th>Đối tượng</th><th>Chi tiết</th><th class="center">IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $l): ?>
                <tr>
                    <td class="center small nowrap"><?= e(fmt_datetime($l['created_at'])) ?></td>
                    <td class="small"><?= e($l['full_name'] ?: 'Khách') ?>
                        <?php if ($l['username']): ?><span class="muted">(<?= e($l['username']) ?>)</span><?php endif; ?></td>
                    <td><?= chip($l['action'], 'chip-purple') ?></td>
                    <td class="small muted"><?= e($l['target'] ? $l['target'] . '#' . $l['target_id'] : '—') ?></td>
                    <td class="small muted"><?= e(str_limit($l['detail'], 60)) ?></td>
                    <td class="center tiny muted"><?= e($l['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6"><?= empty_state('📭', 'Chưa có nhật ký', '') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate_html($total, $per, $page, ['r' => 'admin/logs', 'action_filter' => $action]) ?>
