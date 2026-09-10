<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$color = $course['color'] ?: color_of($course['title']);
?>
<?= breadcrumbs([
    ['label' => 'Khoá học', 'url' => url('course/mine')],
    ['label' => $course['title']],
]) ?>

<section class="card mb-3 card-pad-0" style="overflow:hidden">
    <div style="height:150px;position:relative;background:linear-gradient(135deg,<?= e($color) ?>,<?= e(color_of($course['code'])) ?>)">
        <?php if ($course['cover_id']): ?>
            <img src="<?= e(media_url($course['cover_id'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
            <span class="cover-pattern" style="position:absolute;inset:0"></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="flex-between flex-wrap">
            <div class="flex-1">
                <div class="flex-center flex-wrap gap-1 mb-1">
                    <?= chip($course['code'], 'chip-purple') ?>
                    <?= chip(status_label($course['status']), $course['status'] === 'published' ? 'chip-green' : 'chip-gray') ?>
                    <?php if ($course['start_date']): ?><?= chip('Bắt đầu ' . fmt_date($course['start_date']), 'chip-blue') ?><?php endif; ?>
                </div>
                <h1 style="margin-bottom:6px"><?= e($course['title']) ?></h1>
                <?php if ($course['summary']): ?><p class="muted"><?= e($course['summary']) ?></p><?php endif; ?>
                <div class="flex flex-wrap gap-2 small muted">
                    <span>👥 <?= num($studentCount) ?> học viên</span>
                    <span>🧩 <?= num($totalVisible) ?> mục nội dung</span>
                    <?php foreach ($teachers as $t): ?>
                        <span>👩‍🏫 <?= e($t['full_name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="page-actions">
                <?php if ($canManage): ?>
                    <a class="btn btn-primary" href="<?= e(url('teach/content', ['id' => $course['id']])) ?>">🧱 Quản lý nội dung</a>
                    <a class="btn btn-ghost" href="<?= e(url('teach/students', ['id' => $course['id']])) ?>">👥 Học viên</a>
                    <a class="btn btn-ghost" href="<?= e(url('grade/course', ['id' => $course['id']])) ?>">📊 Sổ điểm</a>
                    <a class="btn btn-ghost" href="<?= e(url('teach/course/edit', ['id' => $course['id']])) ?>">⚙️ Cài đặt</a>
                <?php else: ?>
                    <a class="btn btn-ghost" href="<?= e(url('grade/mine', ['course' => $course['id']])) ?>">🏅 Điểm của tôi</a>
                    <a class="btn btn-ghost" href="<?= e(url('forum/index', ['course' => $course['id']])) ?>">💬 Thảo luận</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$canManage): ?>
            <div class="mt-3">
                <div class="flex-between small mb-1">
                    <b>Tiến độ học tập của bạn</b>
                    <span><?= num($doneCount) ?>/<?= num($totalVisible) ?> mục · <b><?= $progress ?>%</b></span>
                </div>
                <?= progress_bar($progress) ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
    <div>
        <?php if ($course['description']): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">📋</span> Giới thiệu khoá học</div>
                <div class="rich-content"><?= safe_html($course['description']) ?></div>
            </div>
        <?php endif; ?>

        <?php
        $sectionList = $sections;
        $hasNoSection = !empty($bySection[0]);
        if ($hasNoSection) array_unshift($sectionList, ['id' => 0, 'title' => 'Nội dung chung', 'summary' => '', 'visible' => 1]);
        $num = 0;
        if (!$sectionList): ?>
            <div class="card"><?= empty_state('📭', 'Khoá học chưa có nội dung',
                $canManage ? 'Hãy thêm chương và học liệu đầu tiên cho lớp của bạn.' : 'Giáo viên đang chuẩn bị bài giảng, em quay lại sau nhé!',
                $canManage ? '<a class="btn btn-primary" href="' . e(url('teach/content', ['id' => $course['id']])) . '">Thêm nội dung</a>' : '') ?></div>
        <?php endif;

        foreach ($sectionList as $s):
            $sid = (int)$s['id'];
            $list = isset($bySection[$sid]) ? $bySection[$sid] : [];
            if (!$list && !$canManage) continue;
            if (!$s['visible'] && !$canManage) continue;
            $num++;
        ?>
            <div class="section-block">
                <div class="section-head-bar">
                    <span class="section-num"><?= $num ?></span>
                    <div class="flex-1">
                        <h3><?= e($s['title']) ?></h3>
                        <?php if (!empty($s['summary'])): ?><div class="small muted"><?= e($s['summary']) ?></div><?php endif; ?>
                    </div>
                    <span class="chip chip-gray"><?= count($list) ?> mục</span>
                    <?php if (!$s['visible']): ?><?= chip('Đang ẩn', 'chip-orange', '🙈') ?><?php endif; ?>
                </div>

                <?php if (!$list): ?>
                    <p class="small muted" style="padding-left:14px">Chương này chưa có nội dung.</p>
                <?php else: ?>
                    <div class="item-list">
                        <?php foreach ($list as $it):
                            $meta = item_type_meta($it['type']);
                            $done = $it['done'] === 'completed';
                            $badge = $it['due_at'] ? deadline_badge($it['due_at']) : null; ?>
                            <div class="item-row<?= $it['visible'] ? '' : ' hidden-item' ?>">
                                <span class="item-ico" style="background:<?= e($meta[2]) ?>1a;color:<?= e($meta[2]) ?>"><?= $meta[0] ?></span>
                                <div class="item-main">
                                    <a href="<?= e(url('item/view', ['id' => $it['id']])) ?>"><?= e($it['title']) ?></a>
                                    <div class="item-sub">
                                        <span><?= e($meta[1]) ?></span>
                                        <?php if ($it['graded']): ?><span>· Thang <?= score_fmt($it['max_points']) ?> điểm</span><?php endif; ?>
                                        <?php if ($badge): ?><span>· <?= e($badge['label']) ?></span><?php endif; ?>
                                        <?php if (!$it['visible']): ?><span>· 🙈 Đang ẩn với học sinh</span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <?php if ($it['sub_score'] !== null): ?>
                                        <?= chip(score_fmt($it['sub_score']) . '/' . score_fmt($it['max_points']), 'chip-green', '🏅') ?>
                                    <?php elseif (in_array($it['sub_status'], ['submitted', 'graded'], true)): ?>
                                        <?= chip('Đã nộp', 'chip-blue', '✓') ?>
                                    <?php endif; ?>
                                    <?php if ($done): ?><span class="done-tick" title="Đã hoàn thành">✅</span><?php endif; ?>
                                    <?php if ($canManage): ?>
                                        <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/item/edit', ['id' => $it['id']])) ?>">✏️</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📢</span> Thông báo lớp</div>
            <?php if ($canManage): ?>
                <a class="btn btn-ghost btn-sm btn-block mb-2" href="<?= e(url('teach/announce', ['id' => $course['id']])) ?>">➕ Đăng thông báo</a>
            <?php endif; ?>
            <?php if (!$announcements): ?>
                <p class="small muted center" style="padding:8px 0">Chưa có thông báo nào.</p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($announcements as $a): ?>
                        <div class="timeline-item">
                            <div class="bold small"><?= $a['pinned'] ? '📌 ' : '' ?><?= e($a['title']) ?></div>
                            <div class="small muted"><?= rich_text(str_limit($a['content'], 200)) ?></div>
                            <div class="timeline-time"><?= e($a['author']) ?> · <?= e(time_ago($a['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-title"><span class="emoji">👩‍🏫</span> Giáo viên phụ trách</div>
            <?php foreach ($teachers as $t): ?>
                <div class="user-row mb-2">
                    <?= avatar_tag($t, 42) ?>
                    <div>
                        <div class="u-name"><?= e($t['full_name']) ?></div>
                        <div class="u-sub"><?= e($t['org_unit'] ?: role_label($t['role'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-title"><span class="emoji">💬</span> Không gian lớp học</div>
            <a class="btn btn-ghost btn-sm btn-block mb-1" href="<?= e(url('forum/index', ['course' => $course['id']])) ?>">Diễn đàn thảo luận</a>
            <a class="btn btn-ghost btn-sm btn-block mb-1" href="<?= e(url('grade/' . ($canManage ? 'course' : 'mine'), ['id' => $course['id'], 'course' => $course['id']])) ?>">Bảng điểm</a>
            <?php if (!$canManage): ?>
                <form method="post" action="<?= e(url('course/leave')) ?>"
                      onsubmit="return confirm('Bạn chắc chắn muốn rời khoá học này?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                    <button class="btn btn-ghost btn-sm btn-block" type="submit">🚪 Rời khoá học</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
