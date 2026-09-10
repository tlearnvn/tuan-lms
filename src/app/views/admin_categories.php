<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head">
    <h1>🏷️ Danh mục khoá học</h1>
    <div class="sub">Nhóm khoá học theo môn học hoặc lĩnh vực.</div>
</div>

<div class="grid" style="grid-template-columns:1fr 360px;align-items:start">
    <div class="card card-pad-0">
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data">
                <thead><tr><th class="center">Thứ tự</th><th>Danh mục</th><th class="center">Số khoá học</th><th class="right"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="center muted"><?= (int)$r['position'] ?></td>
                        <td><span style="font-size:1.2rem"><?= e($r['icon']) ?></span>
                            <b style="color:<?= e($r['color']) ?>"><?= e($r['name']) ?></b></td>
                        <td class="center"><?= num($r['n']) ?></td>
                        <td class="right nowrap">
                            <button class="btn btn-ghost btn-sm" type="button"
                                onclick="editCat(<?= (int)$r['id'] ?>, <?= json_encode($r['name']) ?>, <?= json_encode($r['color']) ?>, <?= json_encode($r['icon']) ?>, <?= (int)$r['position'] ?>)">✏️</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Xoá danh mục này?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="cid" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="emoji">➕</span> <span id="cat-title">Thêm danh mục</span></div>
        <form method="post" id="cat-form">
            <?= csrf_field() ?>
            <input type="hidden" name="cid" id="cat-id" value="">
            <div class="form-group">
                <label>Tên danh mục <span class="req">*</span></label>
                <input type="text" name="name" id="cat-name" required placeholder="vd: Vật lí">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Biểu tượng</label>
                    <input type="text" name="icon" id="cat-icon" value="📚" maxlength="8">
                </div>
                <div class="form-group">
                    <label>Màu</label>
                    <input type="color" name="color" id="cat-color" value="#6C5CE7">
                </div>
                <div class="form-group">
                    <label>Thứ tự</label>
                    <input type="number" name="position" id="cat-position" value="0">
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Lưu danh mục</button>
            <button class="btn btn-ghost btn-block mt-1 hidden" id="cat-cancel" type="button" onclick="resetCat()">Huỷ</button>
        </form>
    </div>
</div>

<script>
function editCat(id, name, color, icon, pos) {
    document.getElementById('cat-id').value = id;
    document.getElementById('cat-name').value = name;
    document.getElementById('cat-color').value = color;
    document.getElementById('cat-icon').value = icon;
    document.getElementById('cat-position').value = pos;
    document.getElementById('cat-title').textContent = 'Sửa danh mục';
    document.getElementById('cat-cancel').classList.remove('hidden');
}
function resetCat() {
    document.getElementById('cat-form').reset();
    document.getElementById('cat-id').value = '';
    document.getElementById('cat-title').textContent = 'Thêm danh mục';
    document.getElementById('cat-cancel').classList.add('hidden');
}
</script>
