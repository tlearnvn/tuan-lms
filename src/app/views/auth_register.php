<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="auth-wrap">
    <div class="auth-art">
        <h2>Bắt đầu hành trình học tập 🚀</h2>
        <p style="font-size:1.05rem;opacity:.95">Tạo tài khoản để tham gia lớp học, làm bài tập và theo dõi kết quả của mình.</p>
        <img src="<?= e(asset('img/teacher.svg')) ?>" alt="">
        <?php if (Settings::get('register_note')): ?>
            <div class="hero-badge" style="display:block;padding:14px 18px"><?= nl2html(Settings::get('register_note')) ?></div>
        <?php endif; ?>
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
                <h2 style="margin-bottom:4px">Đăng ký tài khoản</h2>
                <p class="muted small">Điền thông tin bên dưới, chỉ mất một phút thôi!</p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <span>⚠️</span>
                        <div><b>Vui lòng kiểm tra lại:</b>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" data-once>
                    <?= csrf_field() ?>

                    <?php if ($allowStudent && $allowTeacher): ?>
                        <div class="form-group">
                            <label>Bạn đăng ký với vai trò <span class="req">*</span></label>
                            <div class="grid grid-2" style="gap:10px">
                                <label class="answer-option" style="margin:0">
                                    <input type="radio" name="role" value="student" <?= $data['role'] === 'student' ? 'checked' : '' ?>>
                                    <span><b>🧑‍🎓 Học sinh</b><br><span class="tiny muted">Tham gia lớp, làm bài tập</span></span>
                                </label>
                                <label class="answer-option" style="margin:0">
                                    <input type="radio" name="role" value="teacher" <?= $data['role'] === 'teacher' ? 'checked' : '' ?>>
                                    <span><b>👩‍🏫 Giáo viên</b><br><span class="tiny muted">Tạo khoá học, chấm bài</span></span>
                                </label>
                            </div>
                            <?php if (Settings::bool('teacher_need_approval', true)): ?>
                                <div class="form-hint">💡 Tài khoản giáo viên cần quản trị viên phê duyệt trước khi sử dụng.</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="role" value="<?= $allowTeacher ? 'teacher' : 'student' ?>">
                        <div class="alert alert-info"><span>ℹ️</span><div>
                            Hệ thống đang mở đăng ký cho <b><?= $allowTeacher ? 'giáo viên' : 'học sinh' ?></b>.
                        </div></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="full_name">Họ và tên <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= e($data['full_name']) ?>" required placeholder="Nguyễn Văn A">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Tên đăng nhập <span class="req">*</span></label>
                            <input type="text" id="username" name="username" value="<?= e($data['username']) ?>" required placeholder="nguyenvana">
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="req">*</span></label>
                            <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required placeholder="email@truong.edu.vn">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mật khẩu <span class="req">*</span></label>
                            <input type="password" id="password" name="password" required placeholder="Ít nhất 6 ký tự">
                        </div>
                        <div class="form-group">
                            <label for="password2">Nhập lại mật khẩu <span class="req">*</span></label>
                            <input type="password" id="password2" name="password2" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="org_unit">Lớp / Tổ chuyên môn</label>
                            <input type="text" id="org_unit" name="org_unit" value="<?= e($data['org_unit']) ?>" placeholder="vd: 10A1 hoặc Tổ Toán">
                        </div>
                    </div>

                    <button class="btn btn-primary btn-block btn-lg" type="submit">Tạo tài khoản 🎈</button>
                </form>

                <p class="center small mt-3 mb-0">Đã có tài khoản?
                    <a href="<?= e(url('auth/login')) ?>"><b>Đăng nhập</b></a></p>
            </div>

            <p class="center small muted mt-2"><a href="<?= e(url('home')) ?>">← Về trang chủ</a></p>
        </div>
    </div>
</div>
