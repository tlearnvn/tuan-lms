<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => 'Thảo luận'],
]) ?>

<div class="page-head">
    <h1>💬 Diễn đàn lớp học</h1>
    <div class="sub"><?= e($course['title']) ?> · <?= num(count($threads)) ?> chủ đề</div>
</div>

<div class="grid" style="grid-template-columns:1fr 380px;align-items:start">
    <div>
        <?php if (!$threads): ?>
            <div class="card"><?= empty_state('💭', 'Chưa có chủ đề nào', 'Hãy là người đầu tiên đặt câu hỏi cho lớp!') ?></div>
        <?php endif; ?>
        <?php foreach ($threads as $t): ?>
            <div class="card mb-2 card-hover">
                <div class="flex" style="gap:12px;align-items:flex-start">
                    <?= avatar_tag($t, 42) ?>
                    <div class="flex-1">
                        <h3 style="margin-bottom:3px">
                            <?= $t['pinned'] ? '📌 ' : '' ?><?= $t['locked'] ? '🔒 ' : '' ?>
                            <a href="<?= e(url('forum/thread', ['id' => $t['id']])) ?>"><?= e($t['title']) ?></a>
                        </h3>
                        <div class="small muted"><?= e(str_limit($t['content'], 140)) ?></div>
                        <div class="item-sub mt-1">
                            <span>✍️ <?= e($t['full_name']) ?></span>
                            <span>· <?= e(time_ago($t['created_at'])) ?></span>
                            <span>· 💬 <?= num($t['reply_count']) ?> phản hồi</span>
                            <span>· 👁️ <?= num($t['views']) ?></span>
                            <?php if ($t['last_post']): ?><span>· Mới nhất <?= e(time_ago($t['last_post'])) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-title"><span class="emoji">✏️</span> Tạo chủ đề mới</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="course" value="<?= (int)$course['id'] ?>">
            <div class="form-group">
                <label>Tiêu đề <span class="req">*</span></label>
                <input type="text" name="title" required placeholder="vd: Em chưa hiểu bài tập 3">
            </div>
            <div class="form-group">
                <label>Nội dung</label>
                <textarea name="content" rows="6" data-editor data-autogrow placeholder="Mô tả rõ câu hỏi của bạn…"></textarea>
            </div>
            <?php if (Auth::canManageCourse($course)): ?>
                <div class="check-row"><input type="checkbox" id="pinned" name="pinned" value="1"><label for="pinned">📌 Ghim chủ đề</label></div>
            <?php endif; ?>
            <button class="btn btn-primary btn-block" type="submit">Đăng chủ đề</button>
        </form>
    </div>
</div>
