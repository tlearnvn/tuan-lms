<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg)">
    <div class="card center pop-in" style="max-width:520px">
        <div style="font-size:60px">🔒</div>
        <h2>Chức năng đăng ký đang tạm đóng</h2>
        <p class="muted">Quản trị viên hiện chưa mở đăng ký tài khoản mới.
            Vui lòng liên hệ nhà trường để được cấp tài khoản.</p>
        <?php if (Settings::get('contact_email')): ?>
            <p class="small">✉️ <?= e(Settings::get('contact_email')) ?></p>
        <?php endif; ?>
        <div class="btn-group mt-2" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Đăng nhập</a>
            <a class="btn btn-ghost" href="<?= e(url('home')) ?>">Về trang chủ</a>
        </div>
    </div>
</div>
