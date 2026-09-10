<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🔎 Khám phá khoá học</h1>
        <div class="sub">Tìm thấy <b><?= num($total) ?></b> khoá học đang mở.</div>
    </div>
</div>

<form class="card mb-3" method="get" action="<?= e(base_url() . 'index.php') ?>">
    <input type="hidden" name="r" value="catalog">
    <div class="form-row" style="align-items:end">
        <div class="form-group" style="margin:0">
            <label>Từ khoá</label>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tên khoá học, mã lớp…">
        </div>
        <div class="form-group" style="margin:0">
            <label>Danh mục</label>
            <select name="cat">
                <option value="">— Tất cả danh mục —</option>
                <?php foreach ($categories as $ct): ?>
                    <option value="<?= (int)$ct['id'] ?>" <?= $cat == $ct['id'] ? 'selected' : '' ?>>
                        <?= e($ct['icon'] . ' ' . $ct['name']) ?> (<?= (int)$ct['n'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <button class="btn btn-primary btn-block" type="submit">Tìm kiếm</button>
        </div>
    </div>
</form>

<?php if (!$courses): ?>
    <div class="card"><?= empty_state('🔍', 'Chưa tìm thấy khoá học nào', 'Thử đổi từ khoá hoặc chọn danh mục khác nhé.') ?></div>
<?php else: ?>
    <div class="grid grid-auto">
        <?php foreach ($courses as $c) partial('course_card', ['c' => $c]); ?>
    </div>
    <?= paginate_html($total, $per, $page, ['r' => 'catalog', 'q' => $q, 'cat' => $cat]) ?>
<?php endif; ?>
