<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>👥 Quản lý người dùng</h1>
        <div class="sub">Tổng <?= num($counts['all']) ?> tài khoản · <?= num($counts['teacher']) ?> giáo viên ·
            <?= num($counts['student']) ?> học sinh</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('export/users', ['format' => 'xlsx'])) ?>">📗 Xuất Excel</a>
        <a class="btn btn-ghost" href="<?= e(url('export/users', ['format' => 'pdf'])) ?>">📕 Xuất PDF</a>
        <a class="btn btn-primary" href="<?= e(url('admin/user/edit')) ?>">➕ Thêm tài khoản</a>
    </div>
</div>

<div class="flex flex-wrap gap-1 mb-3">
    <a class="btn btn-sm <?= $status === '' && $role === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/users')) ?>">Tất cả (<?= num($counts['all']) ?>)</a>
    <a class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/users', ['status' => 'pending'])) ?>">🕓 Chờ duyệt (<?= num($counts['pending']) ?>)</a>
    <a class="btn btn-sm <?= $role === 'teacher' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/users', ['role' => 'teacher'])) ?>">👩‍🏫 Giáo viên (<?= num($counts['teacher']) ?>)</a>
    <a class="btn btn-sm <?= $role === 'student' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/users', ['role' => 'student'])) ?>">🧑‍🎓 Học sinh (<?= num($counts['student']) ?>)</a>
    <a class="btn btn-sm <?= $status === 'locked' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/users', ['status' => 'locked'])) ?>">🔒 Đã khoá (<?= num($counts['locked']) ?>)</a>
</div>

<div class="card card-pad-0">
    <div class="card-header">
        <form method="get" action="<?= e(base_url() . 'index.php') ?>" class="flex-center gap-1">
            <input type="hidden" name="r" value="admin/users">
            <input type="hidden" name="role" value="<?= e($role) ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="🔍 Tìm tên, tài khoản, email…" style="min-width:240px">
            <button class="btn btn-ghost btn-sm" type="submit">Tìm</button>
        </form>
        <?php if ($counts['pending']): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_approve">
                <button class="btn btn-success btn-sm" type="submit">✓ Duyệt tất cả tài khoản chờ</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data">
            <thead><tr><th>Người dùng</th><th class="center">Vai trò</th><th class="center">Trạng thái</th>
                <th class="center">Đăng nhập gần nhất</th><th class="center">Lần đăng nhập</th><th class="right">Thao tác</th></tr></thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6"><?= empty_state('🔍', 'Không tìm thấy người dùng', 'Thử đổi bộ lọc hoặc từ khoá tìm kiếm.') ?></td></tr>
            <?php else: foreach ($users as $u): ?>
                <tr>
                    <td><div class="user-row"><?= avatar_tag($u, 36) ?>
                        <div><div class="u-name"><?= e($u['full_name']) ?></div>
                             <div class="u-sub"><?= e($u['username']) ?> · <?= e($u['email']) ?>
                                <?= $u['org_unit'] ? ' · ' . e($u['org_unit']) : '' ?></div></div></div></td>
                    <td class="center"><?php
                        $rc = ['admin' => 'chip-purple', 'teacher' => 'chip-blue', 'student' => 'chip-green'];
                        echo chip(role_label($u['role']), $rc[$u['role']] ?? 'chip-gray'); ?></td>
                    <td class="center"><?php
                        if ($u['status'] === 'active') echo chip('Hoạt động', 'chip-green', '✓');
                        elseif ($u['status'] === 'pending') echo chip('Chờ duyệt', 'chip-orange', '🕓');
                        else echo chip('Đã khoá', 'chip-red', '🔒'); ?></td>
                    <td class="center small"><?= e(fmt_datetime($u['last_login'])) ?></td>
                    <td class="center"><?= num($u['login_count']) ?></td>
                    <td class="right nowrap">
                        <?php if ($u['status'] === 'pending'): ?>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?><input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-success btn-sm" type="submit">✓ Duyệt</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/user/edit', ['id' => $u['id']])) ?>">✏️</a>
                        <div class="dropdown" style="display:inline-block">
                            <button class="btn btn-ghost btn-sm" type="button" data-dropdown>⋯</button>
                            <div class="dropdown-menu">
                                <form method="post" onsubmit="return confirm('Đặt lại mật khẩu cho người dùng này?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                                    <button class="dropdown-item" type="submit" style="width:100%;border:0;background:none;cursor:pointer;font-family:var(--font)">🔑 Đặt lại mật khẩu</button>
                                </form>
                                <?php if ($u['status'] !== 'locked'): ?>
                                    <form method="post" onsubmit="return confirm('Khoá tài khoản này?')">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="lock">
                                        <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                                        <button class="dropdown-item" type="submit" style="width:100%;border:0;background:none;cursor:pointer;font-family:var(--font)">🔒 Khoá tài khoản</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                                        <button class="dropdown-item" type="submit" style="width:100%;border:0;background:none;cursor:pointer;font-family:var(--font)">🔓 Mở khoá</button>
                                    </form>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <form method="post" onsubmit="return confirm('XOÁ VĨNH VIỄN tài khoản này? Hành động không thể hoàn tác.')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
                                    <button class="dropdown-item" type="submit" style="width:100%;border:0;background:none;cursor:pointer;color:var(--danger);font-family:var(--font)">🗑️ Xoá tài khoản</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= paginate_html($total, $per, $page, ['r' => 'admin/users', 'q' => $q, 'role' => $role, 'status' => $status]) ?>
