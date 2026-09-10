<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🗂️ Quản lý khoá học</h1>
        <div class="sub">Tổng <?= num(count($courses)) ?> khoá học trong hệ thống.</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/categories')) ?>">🏷️ Danh mục</a>
        <a class="btn btn-primary" href="<?= e(url('teach/course/edit')) ?>">➕ Tạo khoá học</a>
    </div>
</div>

<div class="card card-pad-0">
    <div class="card-header">
        <form method="get" action="<?= e(base_url() . 'index.php') ?>" class="flex-center gap-1">
            <input type="hidden" name="r" value="admin/courses">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="🔍 Tìm khoá học…" style="min-width:240px">
            <button class="btn btn-ghost btn-sm" type="submit">Tìm</button>
        </form>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data">
            <thead><tr><th>Khoá học</th><th>Giáo viên</th><th>Danh mục</th><th class="center">Học viên</th>
                <th class="center">Nội dung</th><th class="center">Trạng thái</th><th class="right"></th></tr></thead>
            <tbody>
            <?php if (!$courses): ?>
                <tr><td colspan="7"><?= empty_state('📚', 'Chưa có khoá học nào', 'Tạo khoá học đầu tiên cho hệ thống.') ?></td></tr>
            <?php else: foreach ($courses as $c): ?>
                <tr>
                    <td>
                        <div class="flex-center gap-1">
                            <span style="width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#fff;background:<?= e($c['color'] ?: '#6C5CE7') ?>">📘</span>
                            <div><a href="<?= e(url('course/view', ['id' => $c['id']])) ?>"><b><?= e($c['title']) ?></b></a>
                                 <div class="tiny muted"><?= e($c['code']) ?></div></div>
                        </div>
                    </td>
                    <td class="small"><?= e($c['owner_name']) ?></td>
                    <td class="small muted"><?= e($c['category_name'] ?: '—') ?></td>
                    <td class="center bold"><?= num($c['students']) ?></td>
                    <td class="center"><?= num($c['items']) ?></td>
                    <td class="center">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="course" value="<?= (int)$c['id'] ?>">
                            <select name="value" onchange="this.form.submit()" style="padding:5px 8px;font-size:.8rem">
                                <option value="draft" <?= $c['status'] === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                                <option value="published" <?= $c['status'] === 'published' ? 'selected' : '' ?>>Đang mở</option>
                                <option value="archived" <?= $c['status'] === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
                            </select>
                        </form>
                    </td>
                    <td class="right nowrap">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/content', ['id' => $c['id']])) ?>">🧱</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('grade/course', ['id' => $c['id']])) ?>">📊</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/course/edit', ['id' => $c['id']])) ?>">⚙️</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
