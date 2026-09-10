<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Học viên'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>👥 Học viên lớp</h1>
        <div class="sub"><?= e($course['title']) ?> · <?= num(count($students)) ?> người</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('grade/course', ['id' => $course['id']])) ?>">📊 Sổ điểm</a>
        <a class="btn btn-ghost" href="<?= e(url('export/roster', ['id' => $course['id'], 'format' => 'xlsx'])) ?>">📗 Xuất danh sách</a>
    </div>
</div>

<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
    <div class="card card-pad-0">
        <div class="card-header">
            <b>Danh sách học viên</b>
            <input type="search" placeholder="🔍 Tìm học sinh…" data-filter-table="#tbl-students" style="max-width:240px">
        </div>
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data" id="tbl-students">
                <thead><tr><th style="width:34px">#</th><th>Học viên</th><th>Lớp/Đơn vị</th>
                    <th class="center">Tiến độ</th><th class="center">Trạng thái</th><th class="right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="6"><?= empty_state('🙋', 'Lớp chưa có học viên', 'Thêm học viên bằng tên đăng nhập hoặc email ở khung bên phải.') ?></td></tr>
                <?php else: foreach ($students as $n => $s):
                    $pct = $itemCount ? round($s['done'] / $itemCount * 100) : 0; ?>
                    <tr>
                        <td class="center muted"><?= $n + 1 ?></td>
                        <td><div class="user-row"><?= avatar_tag($s, 34) ?>
                            <div><div class="u-name"><?= e($s['full_name']) ?></div>
                                 <div class="u-sub"><?= e($s['username']) ?> · <?= e($s['email']) ?></div></div></div></td>
                        <td class="small"><?= e($s['org_unit'] ?: '—') ?></td>
                        <td class="center" style="min-width:130px">
                            <?= progress_bar($pct) ?>
                            <span class="tiny muted"><?= $s['done'] ?>/<?= $itemCount ?> mục</span>
                        </td>
                        <td class="center">
                            <?php if ($s['status'] === 'pending') echo chip('Chờ duyệt', 'chip-orange', '🕓');
                            elseif ($s['status'] === 'completed') echo chip('Hoàn thành', 'chip-green', '🎓');
                            else echo chip('Đang học', 'chip-blue'); ?>
                        </td>
                        <td class="right nowrap">
                            <?php if ($s['status'] === 'pending'): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="enroll" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-success btn-sm" type="submit">✓ Duyệt</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Gỡ học viên này khỏi lớp?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="enroll" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">➕</span> Thêm học viên</div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Tên đăng nhập hoặc email</label>
                    <textarea name="users" rows="4" placeholder="Mỗi dòng một tài khoản, hoặc ngăn cách bằng dấu phẩy:&#10;hocsinh01&#10;an.nguyen@truong.edu.vn"></textarea>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Thêm vào lớp</button>
            </form>
        </div>

        <?php if ($course['enroll_mode'] === 'key' && $course['enroll_key']): ?>
            <div class="card mb-3 center">
                <div class="card-title" style="justify-content:center"><span class="emoji">🔑</span> Mã ghi danh</div>
                <div style="font-size:1.8rem;font-weight:800;letter-spacing:.16em;color:var(--primary)"><?= e($course['enroll_key']) ?></div>
                <button class="btn btn-ghost btn-sm mt-2" type="button" data-copy="<?= e($course['enroll_key']) ?>">📋 Sao chép mã</button>
                <p class="tiny muted mt-2">Chia sẻ mã này để học sinh tự ghi danh vào lớp.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title"><span class="emoji">👩‍🏫</span> Giáo viên đồng phụ trách</div>
            <?php foreach ($coTeachers as $t): ?>
                <div class="flex-between mb-2">
                    <div class="user-row"><?= avatar_tag($t, 32) ?>
                        <div><div class="u-name small"><?= e($t['full_name']) ?></div>
                             <div class="u-sub"><?= e($t['username']) ?></div></div></div>
                    <form method="post" onsubmit="return confirm('Gỡ giáo viên này?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                        <input type="hidden" name="action" value="remove_teacher">
                        <input type="hidden" name="user" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <form method="post" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                <input type="hidden" name="action" value="add_teacher">
                <div class="form-group">
                    <input type="text" name="teacher" placeholder="Tên đăng nhập / email giáo viên">
                </div>
                <button class="btn btn-ghost btn-block btn-sm" type="submit">Thêm giáo viên</button>
            </form>
        </div>
    </div>
</div>
