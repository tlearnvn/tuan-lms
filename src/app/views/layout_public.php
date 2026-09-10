<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php $u = Auth::user(); ?><!doctype html>
<html lang="vi">
<head><?php partial('head', ['pageTitle' => isset($pageTitle) ? $pageTitle : '']); ?></head>
<body style="display:flex;flex-direction:column;min-height:100vh">

<header class="public-header">
    <div class="public-header-inner">
        <a class="sidebar-brand" style="border:0;padding:0;min-height:auto" href="<?= e(url('home')) ?>">
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

        <nav class="public-nav">
            <a href="<?= e(url('home')) ?>">Trang chủ</a>
            <a href="<?= e(url('catalog')) ?>">Khoá học</a>
            <a href="<?= e(url('about')) ?>">Giới thiệu</a>
            <button class="icon-btn" type="button" data-theme-toggle title="Đổi giao diện">🌙</button>
            <?php if ($u): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('dashboard')) ?>">Vào học ngay</a>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/login')) ?>">Đăng nhập</a>
                <?php if (Settings::bool('allow_student_register') || Settings::bool('allow_teacher_register')): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('auth/register')) ?>">Đăng ký</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="public-main" style="flex:1;width:100%">
    <?php $flashes = take_flashes(); if ($flashes): ?>
        <div class="flash-stack">
            <?php foreach ($flashes as $f):
                $ic = ['success' => '🎉', 'danger' => '⚠️', 'warning' => '💡', 'info' => 'ℹ️']; ?>
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

<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <div class="flex-center mb-2">
                <?php if (logo_url()): ?><img src="<?= e(logo_url()) ?>" alt="" style="width:38px;height:38px;object-fit:contain">
                <?php else: ?><span class="logo-fallback" style="width:38px;height:38px;border-radius:11px;display:grid;place-items:center;font-size:20px;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff">🎓</span><?php endif; ?>
                <b><?= e(Settings::get('site_name')) ?></b>
            </div>
            <p class="small muted"><?= e(Settings::get('footer_note')) ?></p>
            <?php if (Settings::get('contact_address')): ?><p class="small muted">📍 <?= e(Settings::get('contact_address')) ?></p><?php endif; ?>
            <?php if (Settings::get('contact_phone')): ?><p class="small muted">☎️ <?= e(Settings::get('contact_phone')) ?></p><?php endif; ?>
            <?php if (Settings::get('contact_email')): ?><p class="small muted">✉️ <?= e(Settings::get('contact_email')) ?></p><?php endif; ?>
        </div>
        <div>
            <h4>Khám phá</h4>
            <a href="<?= e(url('catalog')) ?>">Danh mục khoá học</a>
            <a href="<?= e(url('about')) ?>">Về chúng tôi</a>
            <a href="<?= e(url('auth/login')) ?>">Đăng nhập hệ thống</a>
        </div>
        <div>
            <h4>Đơn vị chủ quản</h4>
            <p class="small muted"><?= e(Settings::get('org_name')) ?></p>
        </div>
    </div>
    <div class="footer-bottom">
        <span><?= e(Settings::get('copyright')) ?></span>
        <span>Thiết kế thân thiện · Giờ Việt Nam (GMT+7) · <?= date('H:i d/m/Y') ?></span>
    </div>
</footer>

<script>
window.LMS = window.LMS || {};
LMS.csrf = <?= json_encode(csrf_token()) ?>;
LMS.urls = { ping: <?= json_encode(url('ping')) ?>, theme: <?= json_encode(url('user/theme')) ?>, uploadImage: <?= json_encode(url('upload/image')) ?> };
</script>
<script src="<?= e(asset('js/app.js')) ?>?v=<?= LMS_VERSION ?>"></script>
<script src="<?= e(asset('js/editor.js')) ?>?v=<?= LMS_VERSION ?>"></script>
</body>
</html>
