<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php $u = $user ?: []; $v = function ($k, $d = '') use ($u) { return isset($u[$k]) && $u[$k] !== null ? $u[$k] : $d; }; ?>
<?= breadcrumbs([['label' => 'Người dùng', 'url' => url('admin/users')], ['label' => $user ? $user['full_name'] : 'Thêm mới']]) ?>

<div class="page-head">
    <h1><?= $user ? '✏️ Sửa tài khoản' : '➕ Thêm tài khoản' ?></h1>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><span>⚠️</span><div>
        <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post" style="max-width:760px">
    <?= csrf_field() ?>
    <?php if ($user): ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><?php endif; ?>

    <div class="card mb-3">
        <div class="card-title"><span class="emoji">👤</span> Thông tin tài khoản</div>
        <div class="form-group">
            <label>Họ và tên <span class="req">*</span></label>
            <input type="text" name="full_name" value="<?= e($v('full_name')) ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Tên đăng nhập <span class="req">*</span></label>
                <input type="text" name="username" value="<?= e($v('username')) ?>" required>
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="<?= e($v('email')) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Vai trò</label>
                <select name="role">
                    <option value="student" <?= $v('role', 'student') === 'student' ? 'selected' : '' ?>>🧑‍🎓 Học sinh</option>
                    <option value="teacher" <?= $v('role') === 'teacher' ? 'selected' : '' ?>>👩‍🏫 Giáo viên</option>
                    <option value="admin" <?= $v('role') === 'admin' ? 'selected' : '' ?>>👑 Quản trị viên</option>
                </select>
            </div>
            <div class="form-group">
                <label>Trạng thái</label>
                <select name="status">
                    <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>✓ Hoạt động</option>
                    <option value="pending" <?= $v('status') === 'pending' ? 'selected' : '' ?>>🕓 Chờ duyệt</option>
                    <option value="locked" <?= $v('status') === 'locked' ? 'selected' : '' ?>>🔒 Đã khoá</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Điện thoại</label>
                <input type="text" name="phone" value="<?= e($v('phone')) ?>">
            </div>
            <div class="form-group">
                <label>Lớp / Tổ chuyên môn</label>
                <input type="text" name="org_unit" value="<?= e($v('org_unit')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Mật khẩu <?= $user ? '(để trống nếu không đổi)' : '<span class="req">*</span>' ?></label>
            <input type="password" name="password" <?= $user ? '' : 'required' ?> autocomplete="new-password">
        </div>
    </div>

    <div class="card">
        <div class="btn-group">
            <button class="btn btn-primary btn-lg" type="submit">💾 Lưu</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/users')) ?>">← Quay lại</a>
        </div>
    </div>
</form>
