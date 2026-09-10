<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg)">
    <div class="card center pop-in" style="max-width:520px">
        <img src="<?= e(asset('img/success.svg')) ?>" alt="" style="max-width:200px">
        <h2>Đăng ký thành công! 🎉</h2>
        <p class="muted">Xin chào <b><?= e($name) ?></b>, tài khoản của bạn đã được tạo và
            đang chờ quản trị viên phê duyệt. Bạn sẽ đăng nhập được ngay sau khi được duyệt.</p>
        <div class="btn-group mt-2" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">Tới trang đăng nhập</a>
            <a class="btn btn-ghost" href="<?= e(url('home')) ?>">Về trang chủ</a>
        </div>
    </div>
</div>
