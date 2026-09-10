<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head">
    <h1>👤 Hồ sơ cá nhân</h1>
    <div class="sub">Cập nhật thông tin và ảnh đại diện của bạn.</div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><span>⚠️</span><div>
        <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 340px;align-items:start">
    <form method="post" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <div class="card-title"><span class="emoji">📝</span> Thông tin cá nhân</div>
        <div class="form-group">
            <label>Họ và tên <span class="req">*</span></label>
            <input type="text" name="full_name" value="<?= e($u['full_name']) ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="<?= e($u['email']) ?>" required>
            </div>
            <div class="form-group">
                <label>Điện thoại</label>
                <input type="tel" name="phone" value="<?= e($u['phone']) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Ngày sinh</label>
                <input type="date" name="birthday" value="<?= e($u['birthday']) ?>">
            </div>
            <div class="form-group">
                <label>Giới tính</label>
                <select name="gender">
                    <option value="">— Không nêu —</option>
                    <option value="male" <?= $u['gender'] === 'male' ? 'selected' : '' ?>>Nam</option>
                    <option value="female" <?= $u['gender'] === 'female' ? 'selected' : '' ?>>Nữ</option>
                    <option value="other" <?= $u['gender'] === 'other' ? 'selected' : '' ?>>Khác</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lớp / Tổ chuyên môn</label>
                <input type="text" name="org_unit" value="<?= e($u['org_unit']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Giới thiệu bản thân</label>
            <textarea name="bio" rows="4" data-autogrow><?= e($u['bio']) ?></textarea>
        </div>
        <div class="form-group">
            <label>Ảnh đại diện</label>
            <input type="file" name="avatar" accept="image/*">
        </div>
        <button class="btn btn-primary btn-lg" type="submit">💾 Lưu hồ sơ</button>
    </form>

    <div>
        <div class="card center mb-3">
            <?= avatar_tag($u, 96) ?>
            <h3 class="mt-2" style="margin-bottom:2px"><?= e($u['full_name']) ?></h3>
            <div class="muted small"><?= e(role_label($u['role'])) ?><?= $u['org_unit'] ? ' · ' . e($u['org_unit']) : '' ?></div>
            <div class="grid grid-3 mt-3" style="gap:10px">
                <div><div class="bold" style="font-size:1.3rem"><?= num($stats['courses']) ?></div><div class="tiny muted">Khoá học</div></div>
                <div><div class="bold" style="font-size:1.3rem"><?= num($stats['submissions']) ?></div><div class="tiny muted">Bài đã nộp</div></div>
                <div><div class="bold" style="font-size:1.3rem"><?= num($stats['completed']) ?></div><div class="tiny muted">Đã hoàn thành</div></div>
            </div>
        </div>
        <div class="card">
            <div class="card-title"><span class="emoji">🔐</span> Bảo mật</div>
            <a class="btn btn-ghost btn-block mb-1" href="<?= e(url('user/password')) ?>">🔑 Đổi mật khẩu</a>
            <a class="btn btn-ghost btn-block mb-1" href="<?= e(url('export/mine', ['format' => 'pdf'])) ?>">📕 Tải phiếu kết quả (PDF)</a>
            <a class="btn btn-ghost btn-block" href="<?= e(url('export/mine', ['format' => 'xlsx'])) ?>">📗 Tải kết quả (Excel)</a>
            <div class="tiny muted mt-2">Đăng nhập gần nhất: <?= e(fmt_datetime($u['last_login'])) ?><br>
                Tổng số lần đăng nhập: <?= num($u['login_count']) ?></div>
        </div>
    </div>
</div>
