<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => 'Khoá học giảng dạy', 'url' => url('teach/courses')],
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Nội dung'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🧱 Xây dựng nội dung</h1>
        <div class="sub"><?= e($course['title']) ?> · <?= e($course['code']) ?></div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('course/view', ['id' => $course['id']])) ?>">👁️ Xem như học sinh</a>
        <a class="btn btn-ghost" href="<?= e(url('teach/announce', ['id' => $course['id']])) ?>">📢 Thông báo</a>
        <a class="btn btn-primary" href="<?= e(url('teach/item/edit', ['course' => $course['id']])) ?>">➕ Thêm nội dung</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-title"><span class="emoji">⚡</span> Thêm nhanh</div>
    <div class="flex flex-wrap gap-1">
        <?php foreach (['page' => '📖 Trang nội dung', 'file' => '📎 Tệp học liệu', 'video' => '🎬 Video',
                        'link' => '🔗 Liên kết', 'scorm' => '🧩 Gói SCORM', 'assignment' => '📝 Bài tập',
                        'quiz' => '❓ Trắc nghiệm', 'forum' => '💬 Diễn đàn'] as $t => $lb): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/item/edit', ['course' => $course['id'], 'type' => $t])) ?>"><?= $lb ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">
    <div>
        <?php
        $all = $sections;
        if (!empty($bySection[0])) $all[] = ['id' => 0, 'title' => 'Nội dung chung (chưa xếp chương)', 'summary' => '', 'visible' => 1, 'position' => 999];
        if (!$all): ?>
            <div class="card"><?= empty_state('🗂️', 'Chưa có chương nào',
                'Hãy tạo chương đầu tiên để sắp xếp bài học gọn gàng.') ?></div>
        <?php endif;

        foreach ($all as $idx => $s):
            $sid = (int)$s['id'];
            $list = isset($bySection[$sid]) ? $bySection[$sid] : []; ?>
            <div class="section-block">
                <div class="section-head-bar">
                    <span class="section-num"><?= $sid ? $idx + 1 : '•' ?></span>
                    <div class="flex-1">
                        <h3><?= e($s['title']) ?> <?php if (!$s['visible']) echo chip('Ẩn', 'chip-orange'); ?></h3>
                        <?php if (!empty($s['summary'])): ?><div class="small muted"><?= e($s['summary']) ?></div><?php endif; ?>
                    </div>
                    <span class="chip chip-gray"><?= count($list) ?> mục</span>
                    <?php if ($sid): ?>
                        <div class="btn-group">
                            <form method="post" action="<?= e(url('teach/section')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="course" value="<?= (int)$course['id'] ?>">
                                <input type="hidden" name="sid" value="<?= $sid ?>">
                                <input type="hidden" name="action" value="move">
                                <button class="btn btn-ghost btn-sm" name="dir" value="up" title="Lên">↑</button>
                                <button class="btn btn-ghost btn-sm" name="dir" value="down" title="Xuống">↓</button>
                            </form>
                            <button class="btn btn-ghost btn-sm" type="button" onclick="editSection(<?= $sid ?>, <?= json_encode($s['title']) ?>, <?= json_encode((string)$s['summary']) ?>, <?= (int)$s['visible'] ?>)">✏️</button>
                            <form method="post" action="<?= e(url('teach/section')) ?>" style="display:inline"
                                  onsubmit="return confirm('Xoá chương này? Các mục bên trong sẽ chuyển về Nội dung chung.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="course" value="<?= (int)$course['id'] ?>">
                                <input type="hidden" name="sid" value="<?= $sid ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="item-list">
                    <?php if (!$list): ?>
                        <div class="item-row" style="justify-content:center;color:var(--text-mute);font-size:.86rem">
                            Chưa có mục nào — <a href="<?= e(url('teach/item/edit', ['course' => $course['id'], 'section' => $sid])) ?>">thêm ngay</a>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($list as $it): $meta = item_type_meta($it['type']); ?>
                        <div class="item-row<?= $it['visible'] ? '' : ' hidden-item' ?>">
                            <span class="item-ico" style="background:<?= e($meta[2]) ?>1a;color:<?= e($meta[2]) ?>"><?= $meta[0] ?></span>
                            <div class="item-main">
                                <a href="<?= e(url('item/view', ['id' => $it['id']])) ?>"><?= e($it['title']) ?></a>
                                <div class="item-sub">
                                    <span><?= e($meta[1]) ?></span>
                                    <?php if ($it['graded']): ?><span>· <?= score_fmt($it['max_points']) ?> điểm</span><?php endif; ?>
                                    <?php if ($it['due_at']): ?><span>· Hạn <?= e(fmt_datetime($it['due_at'])) ?></span><?php endif; ?>
                                    <?php if ($it['sub_count']): ?><span>· 📥 <?= (int)$it['sub_count'] ?> bài nộp</span><?php endif; ?>
                                    <?php if ($it['type'] === 'quiz'): ?><span>· ❓ <?= (int)$it['q_count'] ?> câu hỏi</span><?php endif; ?>
                                    <?php if (!$it['visible']): ?><span>· 🙈 Đang ẩn</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <form method="post" action="<?= e(url('teach/item/move')) ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" name="dir" value="up" title="Lên">↑</button>
                                    <button class="btn btn-ghost btn-sm" name="dir" value="down" title="Xuống">↓</button>
                                </form>
                                <?php if ($it['type'] === 'quiz'): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/questions', ['id' => $it['id']])) ?>" title="Câu hỏi">🧩</a>
                                <?php elseif ($it['type'] === 'assignment'): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('assign/list', ['id' => $it['id']])) ?>" title="Chấm bài">✅</a>
                                <?php elseif ($it['type'] === 'scorm'): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('scorm/report', ['id' => $it['id']])) ?>" title="Kết quả">📊</a>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/item/edit', ['id' => $it['id']])) ?>" title="Sửa">✏️</a>
                                <form method="post" action="<?= e(url('teach/item/delete')) ?>" style="display:inline"
                                      onsubmit="return confirm('Xoá mục này cùng toàn bộ bài nộp và điểm liên quan?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit" title="Xoá">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">➕</span> <span id="sec-form-title">Thêm chương mới</span></div>
            <form method="post" action="<?= e(url('teach/section')) ?>" id="sec-form">
                <?= csrf_field() ?>
                <input type="hidden" name="course" value="<?= (int)$course['id'] ?>">
                <input type="hidden" name="sid" id="sec-id" value="">
                <div class="form-group">
                    <label>Tên chương</label>
                    <input type="text" name="title" id="sec-title" required placeholder="vd: Chương 2 – Hàm số bậc nhất">
                </div>
                <div class="form-group">
                    <label>Mô tả ngắn</label>
                    <textarea name="summary" id="sec-summary" rows="2"></textarea>
                </div>
                <label class="switch mb-2">
                    <input type="checkbox" name="visible" id="sec-visible" value="1" checked>
                    <span class="track"></span>
                    <span class="switch-label"><b>Hiển thị với học sinh</b></span>
                </label>
                <button class="btn btn-primary btn-block" type="submit">Lưu chương</button>
                <button class="btn btn-ghost btn-block mt-1 hidden" type="button" id="sec-cancel" onclick="resetSection()">Huỷ sửa</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title"><span class="emoji">💡</span> Gợi ý</div>
            <ul class="small muted" style="padding-left:18px;margin:0">
                <li>Chia bài học theo chương giúp học sinh dễ theo dõi tiến độ.</li>
                <li>Đặt lịch mở/đóng cho bài tập để tự động hoá lớp học.</li>
                <li>Gói SCORM tải lên dưới dạng .zip có chứa <code>imsmanifest.xml</code>.</li>
                <li>Bật AI chấm bài trong phần cấu hình của từng bài tập.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function editSection(id, title, summary, visible) {
    document.getElementById('sec-id').value = id;
    document.getElementById('sec-title').value = title;
    document.getElementById('sec-summary').value = summary;
    document.getElementById('sec-visible').checked = !!visible;
    document.getElementById('sec-form-title').textContent = 'Sửa chương';
    document.getElementById('sec-cancel').classList.remove('hidden');
    document.getElementById('sec-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function resetSection() {
    document.getElementById('sec-id').value = '';
    document.getElementById('sec-form').reset();
    document.getElementById('sec-form-title').textContent = 'Thêm chương mới';
    document.getElementById('sec-cancel').classList.add('hidden');
}
</script>
