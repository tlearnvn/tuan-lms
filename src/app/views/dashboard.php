<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$u = Auth::user();
$hour = (int)date('G');
$greet = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
$emojiGreet = $hour < 11 ? '🌤️' : ($hour < 14 ? '☀️' : ($hour < 18 ? '🌇' : '🌙'));
?>

<section class="card mb-3" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;border:0;overflow:hidden;position:relative">
    <div class="flex-between flex-wrap" style="position:relative;z-index:2">
        <div>
            <div style="opacity:.9;font-size:.9rem"><?= $emojiGreet ?> <?= $greet ?>,</div>
            <h1 style="color:#fff;margin:2px 0 6px"><?= e($u['full_name']) ?>!</h1>
            <p style="margin:0;opacity:.93">
                Hôm nay là <?= e(['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'][(int)date('w')]) ?>,
                ngày <?= date('d/m/Y') ?> · <?= date('H:i') ?> (giờ Việt Nam)
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if (Auth::isTeacher()): ?>
                <a class="btn" style="background:#fff;color:var(--primary)" href="<?= e(url('teach/course/edit')) ?>">➕ Tạo khoá học</a>
            <?php endif; ?>
            <a class="btn btn-ghost" style="border-color:rgba(255,255,255,.6);color:#fff" href="<?= e(url('catalog')) ?>">🔎 Tìm khoá học</a>
        </div>
    </div>
</section>

<?php if (Auth::isTeacher() && isset($teachStats)): ?>
<div class="grid grid-4 mb-3">
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(108,92,231,.12)">🎓</div>
        <div><div class="stat-value"><?= num($teachStats['courses']) ?></div><div class="stat-label">Khoá đang giảng dạy</div></div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(0,184,148,.12)">👥</div>
        <div><div class="stat-value"><?= num($teachStats['students']) ?></div><div class="stat-label">Học viên</div></div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(214,48,49,.12)">📥</div>
        <div>
            <div class="stat-value"><?= num($teachStats['toGrade']) ?></div>
            <div class="stat-label">Bài chờ chấm</div>
            <?php if ($teachStats['toGrade']): ?>
                <a class="stat-trend text-danger" href="<?= e(url('teach/grading')) ?>">Chấm ngay →</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(9,132,227,.12)">📈</div>
        <div><div class="stat-value"><?= num(array_sum(array_column($submitSeries, 'value'))) ?></div>
             <div class="stat-label">Bài nộp 7 ngày qua</div></div>
    </div>
</div>
<?php else: ?>
<div class="grid grid-4 mb-3">
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(108,92,231,.12)">📚</div>
        <div><div class="stat-value"><?= num($stats['courses']) ?></div><div class="stat-label">Khoá học đang theo</div></div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(0,184,148,.12)">✅</div>
        <div><div class="stat-value"><?= num($stats['done']) ?></div><div class="stat-label">Mục đã hoàn thành</div></div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(253,203,110,.2)">📝</div>
        <div><div class="stat-value"><?= num($stats['pending']) ?></div><div class="stat-label">Bài chưa làm</div></div>
    </div>
    <div class="stat-card fade-up">
        <div class="stat-icon" style="background:rgba(232,67,147,.12)">🏅</div>
        <div>
            <div class="stat-value"><?= $stats['avg'] !== null ? score_fmt(round($stats['avg'], 2)) : '—' ?></div>
            <div class="stat-label">Điểm trung bình (thang 10)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid" style="grid-template-columns:1.55fr 1fr;align-items:start">
    <div>
        <?php if (Auth::isTeacher() && !empty($teachCourses)): ?>
            <div class="flex-between mb-2">
                <h2 style="margin:0">🎓 Khoá học tôi giảng dạy</h2>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/courses')) ?>">Xem tất cả</a>
            </div>
            <div class="grid grid-auto mb-4">
                <?php foreach ($teachCourses as $c) partial('course_card', ['c' => $c]); ?>
            </div>
        <?php endif; ?>

        <?php if ($myCourses || !Auth::isTeacher()): ?>
        <div class="flex-between mb-2">
            <h2 style="margin:0">📚 Khoá học của tôi</h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('course/mine')) ?>">Xem tất cả</a>
        </div>

        <?php if (!$myCourses): ?>
            <div class="card"><?= empty_state('🎒', 'Bạn chưa tham gia khoá học nào',
                'Hãy khám phá danh mục khoá học và ghi danh lớp đầu tiên nhé!',
                '<a class="btn btn-primary" href="' . e(url('catalog')) . '">Khám phá khoá học</a>') ?></div>
        <?php else: ?>
            <div class="grid grid-auto mb-4">
                <?php foreach ($myCourses as $c):
                    $pct = $c['item_count'] > 0 ? round($c['done_count'] / $c['item_count'] * 100) : 0; ?>
                    <div class="course-card fade-up">
                        <div class="course-cover" style="background:linear-gradient(135deg,<?= e($c['color'] ?: color_of($c['title'])) ?>,<?= e(color_of($c['code'])) ?>)">
                            <?php if ($c['cover_id']): ?>
                                <img src="<?= e(media_url($c['cover_id'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="cover-pattern"></span><span style="position:relative;z-index:2">📘</span>
                            <?php endif; ?>
                            <span class="course-code"><?= e($c['code']) ?></span>
                        </div>
                        <div class="course-body">
                            <h3><a href="<?= e(url('course/view', ['id' => $c['id']])) ?>"><?= e($c['title']) ?></a></h3>
                            <div class="small muted mb-1">👩‍🏫 <?= e($c['teacher_name']) ?></div>
                            <div class="mt-auto">
                                <div class="flex-between small muted mb-1">
                                    <span>Tiến độ</span><b><?= $pct ?>%</b>
                                </div>
                                <?= progress_bar($pct) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (Auth::isTeacher() && !empty($toGrade)): ?>
            <div class="card mb-3">
                <div class="flex-between mb-2">
                    <h3 style="margin:0">📥 Bài đang chờ chấm</h3>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('teach/grading')) ?>">Tất cả</a>
                </div>
                <div class="item-list">
                    <?php foreach ($toGrade as $s): ?>
                        <div class="item-row">
                            <?= avatar_tag(['full_name' => $s['full_name'], 'avatar_id' => $s['avatar_id']], 38) ?>
                            <div class="item-main">
                                <a href="<?= e(url('assign/grade', ['id' => $s['item_id'], 'sub' => $s['id']])) ?>"><?= e($s['full_name']) ?></a>
                                <div class="item-sub">
                                    <span><?= e(str_limit($s['title'], 40)) ?></span>
                                    <span>· <?= e(str_limit($s['course_title'], 26)) ?></span>
                                    <span>· <?= e(time_ago($s['submitted_at'])) ?></span>
                                    <?php if ($s['is_late']) echo chip('Nộp trễ', 'chip-orange'); ?>
                                </div>
                            </div>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('assign/grade', ['id' => $s['item_id'], 'sub' => $s['id']])) ?>">Chấm</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (Auth::isTeacher() && !empty($submitSeries)): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">📈</span> Lượt nộp bài 7 ngày gần nhất</div>
                <?= svg_bar_chart($submitSeries, ['height' => 210]) ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">⏰</span> Sắp đến hạn</div>
            <?php if (!$deadlines): ?>
                <p class="muted small center" style="padding:12px 0">Tuyệt vời! Bạn không có bài nào sắp hết hạn 🎈</p>
            <?php else: ?>
                <div class="item-list">
                    <?php foreach ($deadlines as $d):
                        $meta = item_type_meta($d['type']);
                        $badge = deadline_badge($d['due_at']);
                        $done = in_array($d['sub_status'], ['submitted', 'graded', 'returned'], true); ?>
                        <div class="item-row" style="padding:10px 12px">
                            <span class="item-ico" style="background:<?= e($meta[2]) ?>1a"><?= $meta[0] ?></span>
                            <div class="item-main">
                                <a href="<?= e(url('item/view', ['id' => $d['id']])) ?>"><?= e(str_limit($d['title'], 36)) ?></a>
                                <div class="item-sub">
                                    <span><?= e(str_limit($d['course_title'], 24)) ?></span>
                                    <?php if ($done) echo chip('Đã nộp', 'chip-green', '✓'); else echo chip($badge['label'], $badge['class']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('user/deadlines')) ?>">Xem lịch đầy đủ</a>
        </div>

        <?php if ($recentGrades): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🏅</span> Điểm mới nhận</div>
                <?php foreach ($recentGrades as $g):
                    $p10 = $g['max_points'] > 0 ? round($g['score'] / $g['max_points'] * 10, 2) : null;
                    list($rank, $col) = grade_rank($p10); ?>
                    <div class="flex-between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                        <div class="flex-1">
                            <div class="small bold"><?= e(str_limit($g['title'], 34)) ?></div>
                            <div class="tiny muted"><?= e(str_limit($g['course_title'], 30)) ?> · <?= e(time_ago($g['graded_at'])) ?></div>
                        </div>
                        <div class="right">
                            <div class="bold" style="font-size:1.05rem"><?= score_fmt($g['score']) ?><span class="tiny muted">/<?= score_fmt($g['max_points']) ?></span></div>
                            <?= chip($rank, 'chip-' . $col) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a class="btn btn-ghost btn-sm btn-block mt-2" href="<?= e(url('grade/mine')) ?>">Xem toàn bộ kết quả</a>
            </div>
        <?php endif; ?>

        <?php if ($announcements): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">📢</span> Thông báo</div>
                <div class="timeline">
                    <?php foreach ($announcements as $a): ?>
                        <div class="timeline-item">
                            <div class="bold small"><?= $a['pinned'] ? '📌 ' : '' ?><?= e($a['title']) ?></div>
                            <div class="small muted"><?= e(str_limit($a['content'], 120)) ?></div>
                            <div class="timeline-time"><?= e($a['author']) ?> · <?= e(time_ago($a['created_at'])) ?>
                                <?= $a['course_title'] ? '· ' . e($a['course_title']) : '' ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
