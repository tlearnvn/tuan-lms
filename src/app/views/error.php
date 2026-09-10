<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="error-page">
    <div class="error-emoji"><?= isset($emoji) ? $emoji : '🤔' ?></div>
    <div class="error-code"><?= e(isset($code) ? $code : '') ?></div>
    <h2><?= e($title) ?></h2>
    <p class="muted"><?= e(isset($message) ? $message : '') ?></p>
    <?php if (!empty($detail)): ?>
        <pre style="text-align:left;max-width:760px;margin:18px auto;background:#1E2140;color:#EAECFB;padding:16px;border-radius:12px;overflow:auto;font-size:.8rem"><?= e($detail) ?></pre>
    <?php endif; ?>
    <div class="btn-group mt-3">
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? 'dashboard' : 'home')) ?>">🏠 Về trang chủ</a>
        <a class="btn btn-ghost" href="javascript:history.back()">↩️ Quay lại</a>
    </div>
</div>
