<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php list($ic, $col) = file_icon($doc['ext']); ?>

<div class="page-head flex-between flex-wrap">
    <div class="flex-center">
        <span class="item-ico" style="width:52px;height:52px;font-size:24px;background:<?= e($col) ?>1a"><?= $ic ?></span>
        <div>
            <h1 style="margin-bottom:2px"><?= e($doc['name']) ?></h1>
            <div class="sub"><?= e(Preview::kindLabel($kind)) ?> ·
                <?= e(strtoupper((string)$doc['ext'])) ?> · <?= human_size($doc['size']) ?></div>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(media_url($doc['id'])) ?>" target="_blank" rel="noopener">↗️ Mở tab mới</a>
        <a class="btn btn-primary" href="<?= e(media_url($doc['id'], true)) ?>">⬇️ Tải về</a>
    </div>
</div>

<div class="card card-pad-0">
    <div class="pv-body"><?= $html ?></div>
</div>

<div class="mt-3">
    <a class="btn btn-ghost" href="javascript:history.back()">← Quay lại</a>
</div>
