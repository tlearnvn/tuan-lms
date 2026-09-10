<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head">
    <h1>🔑 Đổi mật khẩu</h1>
    <div class="sub">Nên dùng mật khẩu dài, có chữ hoa, chữ thường và số.</div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><span>⚠️</span><div>
        <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post" class="card" style="max-width:520px">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Mật khẩu hiện tại <span class="req">*</span></label>
        <input type="password" name="old_password" required autocomplete="current-password">
    </div>
    <div class="form-group">
        <label>Mật khẩu mới <span class="req">*</span></label>
        <input type="password" name="new_password" required autocomplete="new-password" placeholder="Ít nhất 6 ký tự">
    </div>
    <div class="form-group">
        <label>Nhập lại mật khẩu mới <span class="req">*</span></label>
        <input type="password" name="new_password2" required autocomplete="new-password">
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Đổi mật khẩu</button>
</form>
