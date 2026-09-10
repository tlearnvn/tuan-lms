<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$u = Auth::user();
$unread = $u ? Notify::unreadCount($u['id']) : 0;
$notifs = $u ? DB::all('SELECT * FROM {P}notifications WHERE user_id = :u ORDER BY id DESC LIMIT 8', ['u' => $u['id']]) : [];
$pendingUsers = Auth::isAdmin() ? (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE status = "pending"', [], 0) : 0;
$toGrade = Auth::isTeacher() ? (int)DB::val(
    'SELECT COUNT(*) FROM {P}submissions s
     JOIN {P}items i ON i.id = s.item_id
     JOIN {P}courses c ON c.id = i.course_id
     WHERE s.status = "submitted"
       AND (c.owner_id = :u OR EXISTS(SELECT 1 FROM {P}course_teachers ct WHERE ct.course_id = c.id AND ct.user_id = :u2))',
    ['u' => Auth::id(), 'u2' => Auth::id()], 0) : 0;
?><!doctype html>
<html lang="vi">
<head><?php partial('head', ['pageTitle' => isset($pageTitle) ? $pageTitle : '']); ?></head>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="sidebar-brand" href="<?= e(url('dashboard')) ?>">
            <?php if (logo_url()): ?>
                <img src="<?= e(logo_url()) ?>" alt="Logo">
            <?php else: ?>
                <span class="logo-fallback">🎓</span>
            <?php endif; ?>
            <span>
                <b><?= e(Settings::get('site_name')) ?></b>
                <span><?= e(str_limit(Settings::get('org_name'), 34)) ?></span>
            </span>
        </a>

        <nav class="sidebar-nav">
            <?php foreach (nav_items() as $it):
                if (isset($it['divider'])): ?>
                    <div class="nav-divider"><?= e($it['divider']) ?></div>
                <?php continue; endif;
                $badge = '';
                if ($it['route'] === 'admin/users' && $pendingUsers) $badge = $pendingUsers;
                if ($it['route'] === 'teach/grading' && $toGrade) $badge = $toGrade;
            ?>
                <a class="nav-item<?= is_active_route($it['route']) ? ' active' : '' ?>" href="<?= e(url($it['route'])) ?>">
                    <span class="nav-icon"><?= $it['icon'] ?></span>
                    <span><?= e($it['label']) ?></span>
                    <?php if ($badge !== ''): ?><span class="nav-badge"><?= e($badge) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <?= e(Settings::get('org_name')) ?><br>
            <span class="tiny">Phiên bản <?= LMS_VERSION ?></span>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" aria-label="Mở menu">☰</button>
            <div class="topbar-title"><?= e(isset($pageTitle) ? $pageTitle : 'Bảng điều khiển') ?></div>
            <div class="topbar-spacer"></div>

            <a class="icon-btn" href="<?= e(url('catalog')) ?>" title="Tìm khoá học">🔍</a>
            <button class="icon-btn" type="button" data-theme-toggle title="Đổi giao diện sáng/tối">🌙</button>

            <div class="dropdown">
                <button class="icon-btn" type="button" data-dropdown title="Thông báo">
                    🔔<?php if ($unread): ?><span class="icon-dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-head">Thông báo</div>
                    <?php if (!$notifs): ?>
                        <div class="dropdown-item muted small">Chưa có thông báo nào 🌤️</div>
                    <?php else: foreach ($notifs as $n): ?>
                        <a class="dropdown-item" href="<?= e($n['url'] ? $n['url'] : url('user/notifications')) ?>"
                           style="<?= $n['is_read'] ? '' : 'background:rgba(108,92,231,.07)' ?>">
                            <span style="font-size:1.1rem"><?= e($n['icon']) ?></span>
                            <span class="flex-1">
                                <b class="small"><?= e(str_limit($n['title'], 46)) ?></b>
                                <span class="tiny muted" style="display:block"><?= e(time_ago($n['created_at'])) ?></span>
                            </span>
                        </a>
                    <?php endforeach; endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= e(url('user/notifications')) ?>">📬 Xem tất cả thông báo</a>
                </div>
            </div>

            <div class="dropdown">
                <button class="user-chip" type="button" data-dropdown>
                    <?= avatar_tag($u, 32) ?>
                    <span style="text-align:left">
                        <?= e(str_limit($u['full_name'], 18)) ?>
                        <span class="u-role"><?= e(role_label($u['role'])) ?></span>
                    </span>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="<?= e(url('user/profile')) ?>">👤 Hồ sơ cá nhân</a>
                    <a class="dropdown-item" href="<?= e(url('grade/mine')) ?>">🏅 Kết quả học tập</a>
                    <a class="dropdown-item" href="<?= e(url('user/password')) ?>">🔑 Đổi mật khẩu</a>
                    <?php if (Auth::isAdmin()): ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?= e(url('admin/settings')) ?>">⚙️ Cấu hình hệ thống</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= e(url('auth/logout')) ?>">🚪 Đăng xuất</a>
                </div>
            </div>
        </header>

        <main class="content">
            <?php $flashes = take_flashes(); if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $f):
                        $ic = ['success' => '🎉', 'danger' => '⚠️', 'warning' => '💡', 'info' => 'ℹ️'];
                    ?>
                        <div class="alert alert-<?= e($f['type']) ?>">
                            <span><?= isset($ic[$f['type']]) ? $ic[$f['type']] : 'ℹ️' ?></span>
                            <div class="flex-1"><?= $f['msg'] ?></div>
                            <button type="button" class="alert-x" aria-label="Đóng">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<script>
window.LMS = window.LMS || {};
LMS.csrf = <?= json_encode(csrf_token()) ?>;
LMS.keepalive = <?= Settings::bool('session_keepalive', true) ? 'true' : 'false' ?>;
LMS.urls = { ping: <?= json_encode(url('ping')) ?>, theme: <?= json_encode(url('user/theme')) ?>,
             uploadImage: <?= json_encode(url('upload/image')) ?>, preview: <?= json_encode(url('preview/file')) ?> };
</script>
<script src="<?= e(asset('js/app.js')) ?>?v=<?= LMS_VERSION ?>"></script>
<script src="<?= e(asset('js/editor.js')) ?>?v=<?= LMS_VERSION ?>"></script>
<script src="<?= e(asset('js/preview.js')) ?>?v=<?= LMS_VERSION ?>"></script>
</body>
</html>
