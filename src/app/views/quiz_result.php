<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$pct = $attempt['max_score'] > 0 ? round($attempt['score'] / $attempt['max_score'] * 100) : 0;
$passed = $pct >= (float)$quiz['pass_score'];
$pending = 0;
foreach ($questions as $q) if ($q['answer'] && !$q['answer']['graded']) $pending++;
$letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title'], 'url' => url('quiz/view', ['id' => $item['id']])],
    ['label' => 'Kết quả'],
]) ?>

<div class="card mb-3" style="background:linear-gradient(135deg,<?= $passed ? '#00B894,#55EFC4' : '#E17055,#FDCB6E' ?>);color:#fff;border:0">
    <div class="flex-between flex-wrap">
        <div>
            <div style="font-size:44px"><?= $passed ? '🎉' : '💪' ?></div>
            <h1 style="color:#fff;margin:4px 0"><?= $passed ? 'Chúc mừng em!' : 'Cố lên nào!' ?></h1>
            <p style="margin:0;opacity:.95">
                <?= e($canManage ? $student['full_name'] : 'Em') ?> đạt
                <b><?= score_fmt($attempt['score']) ?>/<?= score_fmt($attempt['max_score']) ?></b> điểm (<?= $pct ?>%)
                <?php if ($pending): ?> · còn <?= $pending ?> câu chờ giáo viên chấm<?php endif; ?>
            </p>
        </div>
        <div style="text-align:center">
            <div style="font-size:3.4rem;font-weight:800;line-height:1"><?= $pct ?>%</div>
            <div style="opacity:.9"><?= $passed ? 'ĐẠT' : 'CHƯA ĐẠT' ?> (ngưỡng <?= score_fmt($quiz['pass_score']) ?>%)</div>
        </div>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(108,92,231,.12)">📝</div>
        <div><div class="stat-value"><?= count($questions) ?></div><div class="stat-label">Tổng số câu</div></div></div>
    <?php
    $right = 0; $wrong = 0; $blank = 0;
    foreach ($questions as $q) {
        $a = $q['answer'];
        if (!$a || trim((string)$a['response']) === '') { $blank++; continue; }
        if ($a['score'] !== null && (float)$a['score'] >= (float)$q['points']) $right++;
        elseif ($a['score'] !== null) $wrong++;
    }
    ?>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(0,184,148,.12)">✅</div>
        <div><div class="stat-value"><?= $right ?></div><div class="stat-label">Câu đúng hoàn toàn</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(231,76,60,.12)">❌</div>
        <div><div class="stat-value"><?= $wrong ?></div><div class="stat-label">Câu sai / chưa trọn điểm</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:rgba(149,165,166,.16)">⬜</div>
        <div><div class="stat-value"><?= $blank ?></div><div class="stat-label">Câu bỏ trống</div></div></div>
</div>

<?php if (!$showAnswers): ?>
    <div class="alert alert-info"><span>🔒</span><div>Giáo viên chưa cho phép xem lại đáp án chi tiết của bài này.</div></div>
<?php else: ?>
    <form method="post" action="<?= e(url('quiz/mark')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="a" value="<?= (int)$attempt['id'] ?>">

        <?php foreach ($questions as $i => $q):
            $a = $q['answer'];
            $resp = $a ? $a['response'] : '';
            $multi = $q['type'] === 'multi' ? array_map('intval', json_decode_safe($resp, [])) : []; ?>
            <div class="question-card">
                <div class="flex" style="align-items:flex-start;gap:4px">
                    <span class="question-num"><?= $i + 1 ?></span>
                    <div class="flex-1">
                        <div class="flex-between flex-wrap mb-1">
                            <div class="rich-content flex-1"><?= safe_html($q['content']) ?></div>
                            <div class="right nowrap">
                                <?php if ($a && $a['score'] !== null): ?>
                                    <b style="font-size:1.05rem;color:<?= (float)$a['score'] >= (float)$q['points'] ? 'var(--success)' : ((float)$a['score'] > 0 ? '#d68910' : 'var(--danger)') ?>">
                                        <?= score_fmt($a['score']) ?>/<?= score_fmt($q['points']) ?>
                                    </b>
                                <?php else: ?>
                                    <?= chip('Chờ chấm', 'chip-blue', '🕓') ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($q['image_id']): ?>
                            <img src="<?= e(media_url($q['image_id'])) ?>" alt="" style="max-width:100%;border-radius:12px;margin-bottom:10px">
                        <?php endif; ?>

                        <?php if ($q['type'] === 'short' || $q['type'] === 'essay'): ?>
                            <div class="card" style="box-shadow:none;background:var(--bg-soft)">
                                <b class="small">Bài làm của <?= $canManage ? 'học sinh' : 'em' ?>:</b>
                                <div class="rich-content small mt-1"><?= $resp !== '' ? nl2html($resp) : '<i class="muted">(bỏ trống)</i>' ?></div>
                            </div>
                            <?php if ($q['answer_key']): ?>
                                <div class="alert alert-success mt-2"><span>🔑</span><div>
                                    <b>Đáp án tham khảo:</b> <?= nl2html($q['answer_key']) ?></div></div>
                            <?php endif; ?>
                            <?php if ($canManage && $q['type'] === 'essay'): ?>
                                <div class="form-row mt-2">
                                    <div class="form-group" style="max-width:180px">
                                        <label>Điểm câu này (tối đa <?= score_fmt($q['points']) ?>)</label>
                                        <input type="number" step="0.25" min="0" max="<?= (float)$q['points'] ?>"
                                               name="score[<?= (int)$q['id'] ?>]" value="<?= $a && $a['score'] !== null ? (float)$a['score'] : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Nhận xét</label>
                                        <input type="text" name="note[<?= (int)$q['id'] ?>]" value="<?= e($a ? $a['feedback'] : '') ?>">
                                    </div>
                                </div>
                            <?php elseif ($a && $a['feedback']): ?>
                                <div class="alert alert-info mt-2"><span>💬</span><div><?= nl2html($a['feedback']) ?></div></div>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php foreach ($q['options'] as $oi => $o):
                                $isPicked = $q['type'] === 'multi' ? in_array((int)$o['id'], $multi, true) : ((string)$resp === (string)$o['id']);
                                $cls = '';
                                if ($o['correct']) $cls = 'correct';
                                elseif ($isPicked) $cls = 'wrong'; ?>
                                <div class="answer-option <?= $cls ?>" style="cursor:default">
                                    <span class="answer-letter"><?= isset($letters[$oi]) ? $letters[$oi] : ($oi + 1) ?></span>
                                    <span class="flex-1"><?= safe_html($o['content']) ?></span>
                                    <span class="nowrap">
                                        <?php if ($isPicked) echo chip('Em chọn', 'chip-blue'); ?>
                                        <?php if ($o['correct']) echo ' ✅'; elseif ($isPicked) echo ' ❌'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($q['feedback']): ?>
                            <div class="alert alert-warning mt-2"><span>💡</span><div><?= nl2html($q['feedback']) ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($canManage && $pending): ?>
            <div class="card center">
                <button class="btn btn-primary btn-lg" type="submit">💾 Lưu điểm câu tự luận</button>
                <?php if (Ai::enabled()): ?>
                    <button class="btn btn-info btn-lg" type="button"
                            onclick="LMS.aiGrade(this, <?= json_encode(url('quiz/ai')) ?>, <?= (int)$attempt['id'] ?>)">🤖 Nhờ AI chấm tự luận</button>
                    <div id="ai-result-<?= (int)$attempt['id'] ?>" class="mt-2 hidden"></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>

<div class="flex-between mt-3">
    <a class="btn btn-ghost" href="<?= e(url('quiz/view', ['id' => $item['id']])) ?>">← Về trang bài trắc nghiệm</a>
    <a class="btn btn-primary" href="<?= e(url('course/view', ['id' => $course['id']])) ?>">Tiếp tục học →</a>
</div>
