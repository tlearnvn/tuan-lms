<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="auth-wrap">
    <div class="auth-art">
        <h2>Chào mừng trở lại! 👋</h2>
        <p style="font-size:1.05rem;opacity:.95">Đăng nhập để tiếp tục hành trình học tập của bạn tại
            <b><?= e(Settings::get('site_name')) ?></b>.</p>
        <img src="<?= e(asset('img/hero-study.svg')) ?>" alt="">
        <div class="flex flex-wrap gap-1">
            <span class="hero-badge">📚 Học liệu phong phú</span>
            <span class="hero-badge">🧩 SCORM</span>
            <span class="hero-badge">🤖 AI chấm bài</span>
        </div>
    </div>

    <div class="auth-form">
        <div class="auth-card">
            <div class="auth-logo">
                <?php if (logo_url()): ?><img src="<?= e(logo_url()) ?>" alt="Logo">
                <?php else: ?><span class="logo-fallback">🎓</span><?php endif; ?>
                <div>
                    <b style="font-size:1.05rem"><?= e(Settings::get('site_name')) ?></b>
                    <div class="tiny muted"><?= e(Settings::get('org_name')) ?></div>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-bottom:4px">Đăng nhập</h2>
                <p class="muted small">Nhập tên đăng nhập hoặc email của bạn.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><span>⚠️</span><div><?= e($error) ?></div></div>
                <?php endif; ?>

                <form method="post" data-once>
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="login">Tài khoản hoặc email <span class="req">*</span></label>
                        <input type="text" id="login" name="login" value="<?= e($login) ?>" required autofocus
                               placeholder="vd: nguyenvana hoặc email@truong.edu.vn">
                    </div>
                    <div class="form-group">
                        <label for="password">Mật khẩu <span class="req">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                    </div>
                    <div class="check-row">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Ghi nhớ đăng nhập trên thiết bị này</label>
                    </div>
                    <button class="btn btn-primary btn-block btn-lg" type="submit">Đăng nhập →</button>
                </form>

                <?php if (Settings::bool('allow_student_register') || Settings::bool('allow_teacher_register')): ?>
                    <p class="center small mt-3 mb-1">Chưa có tài khoản?
                        <a href="<?= e(url('auth/register')) ?>"><b>Đăng ký ngay</b></a></p>
                <?php endif; ?>
                <p class="center tiny muted mb-0">
                    Quên mật khẩu? Vui lòng liên hệ quản trị viên
                    <?php if (Settings::get('contact_email')): ?>
                        qua <a href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('contact_email')) ?></a>
                    <?php endif; ?>
                    để được cấp lại.
                </p>
            </div>

            <p class="center small muted mt-2">
                <a href="<?= e(url('home')) ?>">← Về trang chủ</a>
            </p>
        </div>
    </div>
</div>
