<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<!doctype html>
<html lang="vi">
<head><?php partial('head', ['pageTitle' => isset($pageTitle) ? $pageTitle : '']); ?></head>
<body>
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

<script>
window.LMS = window.LMS || {};
LMS.csrf = <?= json_encode(csrf_token()) ?>;
LMS.keepalive = true;
LMS.urls = { ping: <?= json_encode(url('ping')) ?>, theme: <?= json_encode(url('user/theme')) ?>,
             uploadImage: <?= json_encode(url('upload/image')) ?>, preview: <?= json_encode(url('preview/file')) ?> };
</script>
<script src="<?= e(asset('js/app.js')) ?>?v=<?= LMS_VERSION ?>"></script>
<script src="<?= e(asset('js/editor.js')) ?>?v=<?= LMS_VERSION ?>"></script>
<script src="<?= e(asset('js/preview.js')) ?>?v=<?= LMS_VERSION ?>"></script>
</body>
</html>
