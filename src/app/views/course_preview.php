<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php $color = $course['color'] ?: color_of($course['title']); ?>
<div class="grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
    <div>
        <div class="card card-pad-0 mb-3">
            <div style="height:190px;position:relative;background:linear-gradient(135deg,<?= e($color) ?>,<?= e(color_of($course['code'])) ?>)">
                <?php if ($course['cover_id']): ?>
                    <img src="<?= e(media_url($course['cover_id'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                    <span class="cover-pattern" style="position:absolute;inset:0"></span>
                    <span style="position:absolute;inset:0;display:grid;place-items:center;font-size:64px">📘</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="flex-center flex-wrap gap-1 mb-2"><?= chip($course['code'], 'chip-purple') ?></div>
                <h1><?= e($course['title']) ?></h1>
                <?php if ($course['summary']): ?><p class="muted"><?= e($course['summary']) ?></p><?php endif; ?>
                <?php if ($course['description']): ?>
                    <div class="rich-content mt-2"><?= safe_html($course['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sections): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">🗂️</span> Nội dung khoá học</div>
                <?php foreach ($sections as $i => $s): ?>
                    <div class="section-head-bar" style="margin-bottom:8px">
                        <span class="section-num"><?= $i + 1 ?></span>
                        <div class="flex-1"><h3><?= e($s['title']) ?></h3>
                            <?php if ($s['summary']): ?><div class="small muted"><?= e($s['summary']) ?></div><?php endif; ?></div>
                        <span class="chip chip-gray"><?= (int)$s['n'] ?> mục</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">🎫</span> Ghi danh khoá học</div>
            <?php if (!Auth::check()): ?>
                <p class="small muted">Bạn cần đăng nhập để ghi danh khoá học này.</p>
                <a class="btn btn-primary btn-block" href="<?= e(url('auth/login')) ?>">Đăng nhập để tham gia</a>
            <?php elseif ($enrollment && $enrollment['status'] === 'pending'): ?>
                <div class="alert alert-info"><span>🕓</span><div>Yêu cầu ghi danh của bạn đang chờ giáo viên phê duyệt.</div></div>
            <?php elseif ($course['enroll_mode'] === 'manual'): ?>
                <div class="alert alert-warning"><span>🔒</span><div>Khoá học chỉ nhận học viên do giáo viên thêm vào danh sách.</div></div>
            <?php else: ?>
                <form method="post" action="<?= e(url('course/enroll')) ?>" data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                    <?php if ($course['enroll_mode'] === 'key'): ?>
                        <div class="form-group">
                            <label>Mã ghi danh <span class="req">*</span></label>
                            <input type="text" name="enroll_key" required placeholder="Nhập mã do giáo viên cung cấp">
                        </div>
                    <?php endif; ?>
                    <?php if ($course['enroll_mode'] === 'approval'): ?>
                        <p class="small muted">Yêu cầu của bạn sẽ được giáo viên duyệt trước khi vào lớp.</p>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-block btn-lg" type="submit">🎉 Tham gia ngay</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-title"><span class="emoji">ℹ️</span> Thông tin</div>
            <table class="data" style="font-size:.86rem">
                <tr><td class="muted">Học viên</td><td class="right bold"><?= num($counts['students']) ?></td></tr>
                <tr><td class="muted">Chương</td><td class="right bold"><?= num($counts['sections']) ?></td></tr>
                <tr><td class="muted">Mục nội dung</td><td class="right bold"><?= num($counts['items']) ?></td></tr>
                <?php if ($course['start_date']): ?><tr><td class="muted">Khai giảng</td><td class="right bold"><?= e(fmt_date($course['start_date'])) ?></td></tr><?php endif; ?>
                <?php if ($course['end_date']): ?><tr><td class="muted">Kết thúc</td><td class="right bold"><?= e(fmt_date($course['end_date'])) ?></td></tr><?php endif; ?>
            </table>
        </div>

        <?php if ($teacher): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">👩‍🏫</span> Giáo viên</div>
                <div class="user-row">
                    <?= avatar_tag($teacher, 52) ?>
                    <div>
                        <div class="u-name"><?= e($teacher['full_name']) ?></div>
                        <div class="u-sub"><?= e($teacher['org_unit'] ?: 'Giáo viên') ?></div>
                    </div>
                </div>
                <?php if ($teacher['bio']): ?><p class="small muted mt-2"><?= e($teacher['bio']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
