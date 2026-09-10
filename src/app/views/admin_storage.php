<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>💾 Kho dữ liệu</h1>
        <div class="sub">Toàn bộ tệp tin được lưu trong MySQL — không dùng thư mục ghi trên hosting.</div>
    </div>
    <div class="page-actions">
        <form method="post" style="display:inline" onsubmit="return confirm('Dọn các tệp không còn được sử dụng?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="clean">
            <button class="btn btn-ghost" type="submit">🧹 Dọn tệp thừa (<?= count($orphans) ?>)</button>
        </form>
        <form method="post" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="clean_sessions">
            <button class="btn btn-ghost" type="submit">🕒 Dọn phiên hết hạn</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Xoá nhật ký cũ hơn 90 ngày?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="clean_logs">
            <button class="btn btn-ghost" type="submit">📜 Dọn nhật ký cũ</button>
        </form>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">📁</div>
        <div><div class="stat-value"><?= num($stats['count']) ?></div><div class="stat-label">Tệp trong CSDL</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">📦</div>
        <div><div class="stat-value" style="font-size:1.4rem"><?= human_size($stats['bytes']) ?></div>
             <div class="stat-label">Tổng dung lượng tệp</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(9,132,227,.12)">🗄️</div>
        <div><div class="stat-value" style="font-size:1.4rem"><?= human_size($db['total']) ?></div>
             <div class="stat-label">Kích thước CSDL</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(253,203,110,.2)">🟢</div>
        <div><div class="stat-value"><?= num(count($sessions)) ?></div><div class="stat-label">Phiên đang hoạt động</div></div></div>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
    <div class="card">
        <div class="card-title"><span class="emoji">📊</span> Dung lượng theo định dạng</div>
        <?php if ($byType): ?>
            <?= svg_bar_chart(array_map(function ($t) {
                return ['label' => strtoupper($t['ext'] ?: '?'), 'value' => round($t['s'] / 1048576, 2)];
            }, $byType), ['height' => 240]) ?>
            <p class="tiny muted center">Đơn vị: MB</p>
        <?php else: ?>
            <p class="muted small">Chưa có tệp nào.</p>
        <?php endif; ?>
    </div>

    <div class="card card-pad-0">
        <div class="card-header"><b>🗄️ Bảng dữ liệu lớn nhất</b></div>
        <div class="table-wrap" style="border:0;border-radius:0;max-height:320px">
            <table class="data">
                <thead><tr><th>Bảng</th><th class="right">Dung lượng</th></tr></thead>
                <tbody>
                <?php arsort($db['tables']); $n = 0;
                foreach ($db['tables'] as $t => $s): if ($n++ > 12) break; ?>
                    <tr><td class="small"><?= e($t) ?></td><td class="right small bold"><?= human_size($s) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-pad-0 mt-3">
    <div class="card-header"><b>📦 Tệp lớn nhất</b>
        <input type="search" placeholder="🔍 Tìm tệp…" data-filter-table="#tbl-files" style="max-width:220px"></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data" id="tbl-files">
            <thead><tr><th>Tên tệp</th><th class="center">Loại</th><th class="center">Dung lượng</th>
                <th>Chủ sở hữu</th><th class="center">Ngày tải lên</th><th class="right"></th></tr></thead>
            <tbody data-preview-group>
            <?php foreach ($biggest as $f): list($ic, $col) = file_icon($f['ext']); ?>
                <tr>
                    <td><span class="file-ico" style="width:28px;height:28px;font-size:15px;background:<?= e($col) ?>1a;display:inline-grid"><?= $ic ?></span>
                        <?= e(str_limit($f['name'], 46)) ?></td>
                    <td class="center small"><?= e(strtoupper($f['ext'])) ?></td>
                    <td class="center bold"><?= human_size($f['size']) ?></td>
                    <td class="small muted"><?= e($f['owner_name'] ?: '—') ?></td>
                    <td class="center small"><?= e(fmt_date($f['created_at'])) ?></td>
                    <td class="right nowrap">
                        <?php if (Preview::supports($f['ext'])): ?>
                            <button type="button" class="btn btn-ghost btn-sm" data-preview="<?= (int)$f['id'] ?>" title="Xem trước">👁️</button>
                        <?php endif; ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(media_url($f['id'], true)) ?>" title="Tải về">⬇️</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$biggest): ?><tr><td colspan="6"><?= empty_state('📁', 'Chưa có tệp nào', '') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($orphans): ?>
    <div class="card card-pad-0 mt-3">
        <div class="card-header"><b>🧹 Tệp không còn được sử dụng (<?= count($orphans) ?>)</b>
            <span class="small muted">Tổng <?= human_size(array_sum(array_column($orphans, 'size'))) ?></span></div>
        <div class="table-wrap" style="border:0;border-radius:0;max-height:340px">
            <table class="data">
                <thead><tr><th>Tên tệp</th><th class="center">Dung lượng</th><th class="center">Ngày tải</th><th class="right"></th></tr></thead>
                <tbody>
                <?php foreach ($orphans as $f): ?>
                    <tr>
                        <td class="small"><?= e(str_limit($f['name'], 50)) ?></td>
                        <td class="center small"><?= human_size($f['size']) ?></td>
                        <td class="center small"><?= e(fmt_date($f['created_at'])) ?></td>
                        <td class="right">
                            <form method="post" onsubmit="return confirm('Xoá tệp này?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_file">
                                <input type="hidden" name="file" value="<?= (int)$f['id'] ?>">
                                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card card-pad-0 mt-3">
    <div class="card-header"><b>🟢 Phiên đang hoạt động</b>
        <span class="small muted">Thời gian phiên: <?= round(Settings::int('session_lifetime', 43200) / 3600, 1) ?> giờ</span></div>
    <div class="table-wrap" style="border:0;border-radius:0;max-height:340px">
        <table class="data">
            <thead><tr><th>Người dùng</th><th class="center">Vai trò</th><th class="center">Hoạt động cuối</th>
                <th class="center">IP</th><th>Trình duyệt</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td class="small"><?= e($s['full_name'] ?: 'Khách') ?></td>
                    <td class="center"><?= $s['role'] ? chip(role_label($s['role']), 'chip-blue') : '—' ?></td>
                    <td class="center small"><?= e(time_ago($s['last_activity'])) ?></td>
                    <td class="center tiny muted"><?= e($s['ip']) ?></td>
                    <td class="tiny muted"><?= e(str_limit($s['ua'], 60)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
