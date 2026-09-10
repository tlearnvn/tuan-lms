<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🛰️ Tổng quan hệ thống</h1>
        <div class="sub"><?= e(Settings::get('site_name')) ?> · <?= e(Settings::get('org_name')) ?> ·
            <?= date('H:i d/m/Y') ?> (GMT+7)</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/settings')) ?>">⚙️ Cấu hình</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/announce')) ?>">📣 Thông báo</a>
        <a class="btn btn-primary" href="<?= e(url('admin/user/edit')) ?>">➕ Thêm người dùng</a>
    </div>
</div>

<?php if ($stats['pending']): ?>
    <div class="alert alert-warning">
        <span>🕓</span>
        <div><b><?= num($stats['pending']) ?> tài khoản</b> đang chờ phê duyệt.
            <a href="<?= e(url('admin/users', ['status' => 'pending'])) ?>">Xem và duyệt ngay →</a></div>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <?php
    $cards = [
        ['👥', 'Người dùng', $stats['users'], '#6C5CE7', 'rgba(108,92,231,.12)', url('admin/users')],
        ['🎓', 'Khoá học', $stats['courses'], '#00B894', 'rgba(0,184,148,.12)', url('admin/courses')],
        ['🧩', 'Mục nội dung', $stats['items'], '#0984E3', 'rgba(9,132,227,.12)', ''],
        ['📥', 'Lượt nộp bài', $stats['subs'], '#E84393', 'rgba(232,67,147,.12)', ''],
    ];
    foreach ($cards as $c): ?>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:<?= $c[4] ?>"><?= $c[0] ?></div>
            <div>
                <div class="stat-value" style="color:<?= $c[3] ?>"><?= num($c[2]) ?></div>
                <div class="stat-label"><?= $c[1] ?></div>
                <?php if ($c[5]): ?><a class="stat-trend" href="<?= e($c[5]) ?>">Quản lý →</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,206,201,.12)">👩‍🏫</div>
        <div><div class="stat-value"><?= num($stats['teachers']) ?></div><div class="stat-label">Giáo viên</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(253,203,110,.2)">🧑‍🎓</div>
        <div><div class="stat-value"><?= num($stats['students']) ?></div><div class="stat-label">Học sinh</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(162,155,254,.2)">🟢</div>
        <div><div class="stat-value"><?= num($stats['sessions']) ?></div><div class="stat-label">Đang trực tuyến (15 phút)</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(225,112,85,.14)">💾</div>
        <div><div class="stat-value" style="font-size:1.3rem"><?= human_size($dbSize['total']) ?></div>
             <div class="stat-label">Dung lượng CSDL
                 <a href="<?= e(url('admin/storage')) ?>">chi tiết →</a></div></div></div>
</div>

<div class="grid" style="grid-template-columns:1.4fr 1fr;align-items:start">
    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📈</span> Lượt đăng nhập 14 ngày gần nhất</div>
            <?= svg_bar_chart($series, ['height' => 230]) ?>
        </div>

        <?php if ($pendingUsers): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🕓</span> Tài khoản chờ duyệt</div>
                <?php foreach ($pendingUsers as $u): ?>
                    <div class="flex-between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                        <div class="user-row">
                            <?= avatar_tag($u, 36) ?>
                            <div>
                                <div class="u-name"><?= e($u['full_name']) ?></div>
                                <div class="u-sub"><?= e(role_label($u['role'])) ?> · <?= e($u['email']) ?> · <?= e(time_ago($u['created_at'])) ?></div>
                            </div>
                        </div>
                        <form method="post" action="<?= e(url('admin/users')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-success btn-sm" type="submit">✓ Duyệt</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('admin/users')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_approve">
                    <button class="btn btn-ghost btn-block btn-sm" type="submit">Duyệt tất cả</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title"><span class="emoji">🏆</span> Khoá học đông học viên nhất</div>
            <?php if (!$topCourses): ?><p class="muted small">Chưa có dữ liệu.</p><?php endif; ?>
            <?php foreach ($topCourses as $c): ?>
                <div class="flex-between" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <div><a href="<?= e(url('course/view', ['id' => $c['id']])) ?>"><b class="small"><?= e($c['title']) ?></b></a>
                        <div class="tiny muted"><?= e($c['code']) ?></div></div>
                    <?= chip(num($c['n']) . ' học viên', 'chip-purple') ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">🚦</span> Trạng thái nhanh</div>
            <table class="data" style="font-size:.86rem">
                <tr><td class="muted">Đăng ký học sinh</td><td class="right">
                    <?= Settings::bool('allow_student_register') ? chip('Đang mở', 'chip-green') : chip('Đang đóng', 'chip-red') ?></td></tr>
                <tr><td class="muted">Đăng ký giáo viên</td><td class="right">
                    <?= Settings::bool('allow_teacher_register') ? chip('Đang mở', 'chip-green') : chip('Đang đóng', 'chip-red') ?></td></tr>
                <tr><td class="muted">Duyệt giáo viên</td><td class="right">
                    <?= Settings::bool('teacher_need_approval') ? chip('Bắt buộc', 'chip-blue') : chip('Tự động', 'chip-gray') ?></td></tr>
                <tr><td class="muted">Trợ lý AI</td><td class="right">
                    <?= Settings::bool('ai_enabled') ? chip(Settings::get('ai_model'), 'chip-purple', '🤖') : chip('Tắt', 'chip-gray') ?></td></tr>
                <tr><td class="muted">Thời gian phiên</td><td class="right bold"><?= round(Settings::int('session_lifetime', 43200) / 3600, 1) ?> giờ</td></tr>
                <tr><td class="muted">Giới hạn tệp</td><td class="right bold"><?= Settings::int('max_upload_mb', 64) ?> MB</td></tr>
                <tr><td class="muted">Chế độ bảo trì</td><td class="right">
                    <?= Settings::bool('maintenance') ? chip('Đang bật', 'chip-orange') : chip('Tắt', 'chip-gray') ?></td></tr>
                <tr><td class="muted">Múi giờ</td><td class="right bold"><?= e(Settings::get('timezone')) ?></td></tr>
                <tr><td class="muted">Phiên bản</td><td class="right bold"><?= LMS_VERSION ?> · PHP <?= PHP_VERSION ?></td></tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title"><span class="emoji">📜</span> Hoạt động gần đây</div>
            <div class="timeline">
                <?php foreach ($recentLogs as $l): ?>
                    <div class="timeline-item">
                        <div class="small"><b><?= e($l['full_name'] ?: 'Hệ thống') ?></b> — <?= e($l['action']) ?></div>
                        <div class="timeline-time"><?= e(time_ago($l['created_at'])) ?> · <?= e($l['ip']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('admin/logs')) ?>">Xem toàn bộ nhật ký</a>
        </div>
    </div>
</div>
