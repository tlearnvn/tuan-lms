<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$types = array_filter(array_map('trim', explode(',', (string)$asg['submission_type'])));
$badge = deadline_badge($asg['due_at']);
$attemptsUsed = count(array_filter($subs, function ($s) { return $s['status'] !== 'draft'; }));
$maxAttempts = max(1, (int)$asg['max_attempts']);
$closed = ($asg['cutoff_at'] && strtotime($asg['cutoff_at']) < time())
    || ($asg['due_at'] && strtotime($asg['due_at']) < time() && !$asg['allow_late']);
$canSubmit = !$closed && $attemptsUsed < $maxAttempts;
$last = $subs ? $subs[0] : null;
?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title']],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div class="flex-center">
        <span class="item-ico" style="width:52px;height:52px;font-size:24px;background:rgba(214,48,49,.12)">📝</span>
        <div>
            <h1 style="margin-bottom:2px"><?= e($item['title']) ?></h1>
            <div class="sub">Bài tập · Thang điểm <?= score_fmt($item['max_points']) ?> · <?= e($badge['label']) ?></div>
        </div>
    </div>
    <div class="page-actions">
        <?= chip($badge['label'], $badge['class'], '⏰') ?>
        <?= chip('Lần nộp ' . $attemptsUsed . '/' . $maxAttempts, 'chip-purple', '🔁') ?>
    </div>
</div>

<div class="grid" style="grid-template-columns:1.6fr 1fr;align-items:start">
    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📋</span> Yêu cầu của bài tập</div>
            <?php if ($item['summary']): ?><p class="muted"><?= nl2html($item['summary']) ?></p><?php endif; ?>
            <div class="rich-content"><?= safe_html($asg['instructions'] ?: $item['content']) ?></div>

            <?php if ($attachments): ?>
                <div class="mt-3">
                    <b class="small">📎 Tài liệu kèm theo</b>
                    <div class="grid mt-1" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px">
                        <?php foreach ($attachments as $f): list($ic, $col) = file_icon($f['ext']); ?>
                            <a class="file-item" href="<?= e(media_url($f['id'], true)) ?>">
                                <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                                <span class="flex-1">
                                    <span class="file-name"><?= e($f['name']) ?></span>
                                    <span class="file-meta"><?= human_size($f['size']) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canSubmit): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">✍️</span> Nộp bài làm</div>
                <?php if ($asg['due_at'] && strtotime($asg['due_at']) < time()): ?>
                    <div class="alert alert-warning"><span>⏰</span><div>Đã quá hạn nộp — bài của bạn sẽ được đánh dấu <b>nộp trễ</b>.</div></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('assign/submit')) ?>" enctype="multipart/form-data" data-once data-warn-unsaved>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                    <?php if (in_array('text', $types, true)): ?>
                        <div class="form-group">
                            <label for="content">Bài làm trực tiếp</label>
                            <textarea id="content" name="content" rows="9" data-editor data-autogrow
                                      placeholder="Em gõ bài làm ở đây…"></textarea>
                            <div class="form-hint">Có thể dùng các thẻ định dạng cơ bản như &lt;b&gt;, &lt;i&gt;, &lt;ul&gt;…</div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('file', $types, true)): ?>
                        <div class="form-group">
                            <label>Tệp bài làm</label>
                            <div class="dropzone">
                                <input type="file" name="files[]" multiple style="display:none">
                                <span class="dz-emoji">📤</span>
                                <div>Kéo thả tệp vào đây hoặc <b>bấm để chọn tệp</b></div>
                                <div class="small muted mt-1">
                                    Tối đa <?= (int)$asg['max_files'] ?> tệp · mỗi tệp ≤ <?= (int)Settings::int('max_upload_mb', 64) ?> MB
                                    <?php if ($asg['allowed_ext']): ?><br>Định dạng cho phép: <?= e($asg['allowed_ext']) ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="file-preview"></div>
                        </div>
                    <?php endif; ?>

                    <button class="btn btn-primary btn-lg" type="submit">🚀 Nộp bài</button>
                </form>
            </div>
        <?php elseif ($closed): ?>
            <div class="alert alert-warning"><span>🔒</span><div>Bài tập đã đóng, không nhận bài nộp mới.</div></div>
        <?php else: ?>
            <div class="alert alert-info"><span>✅</span><div>Bạn đã sử dụng hết <?= $maxAttempts ?> lượt nộp cho bài tập này.</div></div>
        <?php endif; ?>

        <?php if ($subs): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">📚</span> Các lần nộp của bạn</div>
                <?php foreach ($subs as $s): ?>
                    <div class="card mb-2" style="box-shadow:none;background:var(--bg-soft)">
                        <div class="flex-between flex-wrap mb-2">
                            <b>Lần nộp #<?= (int)$s['attempt'] ?> · <?= e(fmt_datetime($s['submitted_at'])) ?></b>
                            <div class="flex-center gap-1">
                                <?php if ($s['is_late']) echo chip('Nộp trễ', 'chip-orange'); ?>
                                <?php if ($s['score'] !== null): ?>
                                    <?= chip(score_fmt($s['score']) . '/' . score_fmt($item['max_points']) . ' điểm', 'chip-green', '🏅') ?>
                                <?php else: ?>
                                    <?= chip('Chờ chấm', 'chip-blue', '🕓') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($s['content']): ?>
                            <div class="rich-content" style="background:var(--card);padding:14px;border-radius:12px"><?= safe_html($s['content']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($subFiles[$s['id']])): ?>
                            <div class="grid mt-2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px">
                                <?php foreach ($subFiles[$s['id']] as $f): list($ic, $col) = file_icon($f['ext']); ?>
                                    <a class="file-item" href="<?= e(media_url($f['id'], true)) ?>">
                                        <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                                        <span class="flex-1"><span class="file-name"><?= e(str_limit($f['name'], 30)) ?></span>
                                        <span class="file-meta"><?= human_size($f['size']) ?></span></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($s['feedback']): ?>
                            <div class="mt-2" style="background:var(--card);padding:14px;border-radius:12px">
                                <b class="small">💬 Nhận xét của giáo viên</b>
                                <div class="rich-content small mt-1"><?= safe_html($s['feedback']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($s['ai_feedback'] && $asg['ai_enabled']): ?>
                            <div class="ai-panel mt-2">
                                <div class="ai-head"><span class="ai-avatar">🤖</span>
                                    <div>Nhận xét của trợ lý AI
                                        <?php if ($s['ai_score'] !== null): ?>
                                            <div class="small muted">Điểm tham khảo: <b><?= score_fmt($s['ai_score']) ?>/<?= score_fmt($item['max_points']) ?></b></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?= $s['ai_feedback'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">ℹ️</span> Thông tin bài tập</div>
            <table class="data" style="font-size:.86rem">
                <tr><td class="muted">Hạn nộp</td><td class="right bold"><?= e(fmt_datetime($asg['due_at'], 'Không giới hạn')) ?></td></tr>
                <?php if ($asg['cutoff_at']): ?>
                    <tr><td class="muted">Đóng bài</td><td class="right bold"><?= e(fmt_datetime($asg['cutoff_at'])) ?></td></tr>
                <?php endif; ?>
                <tr><td class="muted">Nộp trễ</td><td class="right bold"><?= $asg['allow_late'] ? 'Được phép' : 'Không' ?></td></tr>
                <tr><td class="muted">Số lần nộp</td><td class="right bold"><?= $attemptsUsed ?>/<?= $maxAttempts ?></td></tr>
                <tr><td class="muted">Điểm tối đa</td><td class="right bold"><?= score_fmt($item['max_points']) ?></td></tr>
                <tr><td class="muted">Hình thức</td><td class="right bold"><?= e(implode(' + ', array_map(function ($t) {
                    return ['file' => 'Nộp tệp', 'text' => 'Gõ trực tiếp', 'scorm' => 'SCORM'][$t] ?? $t; }, $types))) ?></td></tr>
            </table>
        </div>

        <?php if ($asg['ai_enabled'] && Ai::enabled() && $last && $last['score'] === null): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🤖</span> Trợ lý AI</div>
                <p class="small muted">Nhờ trợ lý AI đọc và nhận xét bài làm của em ngay lập tức.</p>
                <button class="btn btn-info btn-block" type="button"
                        onclick="LMS.aiGrade(this, <?= json_encode(url('assign/ai_self')) ?>, <?= (int)$last['id'] ?>)">
                    ✨ Nhờ AI nhận xét bài
                </button>
                <div id="ai-result-<?= (int)$last['id'] ?>" class="mt-2 hidden"></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title"><span class="emoji">💡</span> Mẹo làm bài tốt</div>
            <ul class="small muted" style="padding-left:18px;margin:0">
                <li>Đọc kỹ yêu cầu và tiêu chí chấm trước khi làm.</li>
                <li>Đặt tên tệp rõ ràng: <i>HoTen_Lop_TenBai</i>.</li>
                <li>Nộp sớm để tránh sự cố mạng phút chót.</li>
                <li>Kiểm tra lại chính tả và cách trình bày.</li>
            </ul>
        </div>
    </div>
</div>
