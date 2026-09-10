<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title'], 'url' => url('assign/list', ['id' => $item['id']])],
    ['label' => 'Chấm bài'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div class="flex-center">
        <?= avatar_tag($sub, 52) ?>
        <div>
            <h1 style="margin-bottom:2px"><?= e($sub['full_name']) ?></h1>
            <div class="sub"><?= e($item['title']) ?> · Lần nộp #<?= (int)$sub['attempt'] ?> ·
                <?= e(fmt_datetime($sub['submitted_at'])) ?>
                <?php if ($sub['is_late']) echo ' ' . chip('Nộp trễ', 'chip-orange'); ?>
            </div>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('assign/list', ['id' => $item['id']])) ?>">← Danh sách bài nộp</a>
    </div>
</div>

<div class="grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📄</span> Bài làm của học sinh</div>
            <?php if ($sub['content']): ?>
                <div class="rich-content" style="background:var(--bg-soft);padding:16px;border-radius:12px"><?= safe_html($sub['content']) ?></div>
            <?php endif; ?>

            <?php if ($files): ?>
                <div class="mt-3">
                    <?php foreach ($files as $f):
                        list($ic, $col) = file_icon($f['ext']);
                        $ext = strtolower((string)$f['ext']); ?>
                        <div class="file-item mb-2">
                            <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                            <div class="flex-1">
                                <div class="file-name"><?= e($f['name']) ?></div>
                                <div class="file-meta"><?= e(strtoupper($ext)) ?> · <?= human_size($f['size']) ?></div>
                            </div>
                            <a class="btn btn-ghost btn-sm" href="<?= e(media_url($f['id'], true)) ?>">⬇️ Tải</a>
                        </div>
                        <?php if ($ext === 'pdf'): ?>
                            <iframe src="<?= e(media_url($f['id'])) ?>" style="width:100%;height:66vh;border:1px solid var(--border);border-radius:12px;margin-bottom:14px"></iframe>
                        <?php elseif (is_image_ext($ext)): ?>
                            <img src="<?= e(media_url($f['id'])) ?>" alt="" style="max-width:100%;border-radius:12px;margin-bottom:14px">
                        <?php elseif (is_audio_ext($ext)): ?>
                            <audio controls style="width:100%;margin-bottom:14px"><source src="<?= e(media_url($f['id'])) ?>"></audio>
                        <?php elseif (is_video_ext($ext)): ?>
                            <video controls style="width:100%;border-radius:12px;margin-bottom:14px"><source src="<?= e(media_url($f['id'])) ?>"></video>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$sub['content'] && !$files): ?>
                <p class="muted center" style="padding:20px 0">Bài nộp không có nội dung.</p>
            <?php endif; ?>
        </div>

        <div id="ai-result-<?= (int)$sub['id'] ?>" class="mb-3 <?= $sub['ai_feedback'] ? '' : 'hidden' ?>">
            <?php if ($sub['ai_feedback']): ?>
                <div class="ai-panel">
                    <div class="ai-head"><span class="ai-avatar">🤖</span>
                        <div>Nhận xét của trợ lý AI
                            <?php if ($sub['ai_score'] !== null): ?>
                                <div class="small muted">Điểm đề xuất: <b><?= score_fmt($sub['ai_score']) ?>/<?= score_fmt($item['max_points']) ?></b>
                                    · <?= e(time_ago($sub['ai_at'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?= $sub['ai_feedback'] ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">✅</span> Chấm điểm</div>
            <form method="post" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="sub" value="<?= (int)$sub['id'] ?>">

                <div class="form-group">
                    <label for="score-input-<?= (int)$sub['id'] ?>">Điểm (tối đa <?= score_fmt($item['max_points']) ?>)</label>
                    <input type="number" step="0.01" min="0" max="<?= (float)$item['max_points'] ?>"
                           id="score-input-<?= (int)$sub['id'] ?>" name="score"
                           value="<?= $sub['score'] !== null ? (float)$sub['score'] : '' ?>" placeholder="vd: 8.5">
                </div>

                <div class="form-group">
                    <label for="feedback">Nhận xét cho học sinh</label>
                    <textarea id="feedback" name="feedback" rows="7" data-editor data-autogrow
                              placeholder="Nhận xét về bài làm, điểm mạnh và điều cần cải thiện…"><?= e($sub['feedback']) ?></textarea>
                </div>

                <div class="btn-group" style="width:100%">
                    <button class="btn btn-primary flex-1" type="submit">💾 Lưu điểm</button>
                    <?php if ($next): ?>
                        <button class="btn btn-success" type="submit" name="next_sub" value="<?= (int)$next['id'] ?>">Lưu &amp; bài kế →</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (Ai::enabled()): ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🤖</span> Trợ lý AI chấm bài</div>
                <p class="small muted">AI sẽ đọc bài làm, chấm theo tiêu chí và gợi ý nhận xét. Thầy cô vẫn là người quyết định điểm cuối cùng.</p>
                <button class="btn btn-info btn-block" type="button"
                        onclick="LMS.aiGrade(this, <?= json_encode(url('assign/ai')) ?>, <?= (int)$sub['id'] ?>)">
                    ✨ Nhờ AI chấm bài này
                </button>
                <div class="small muted mt-2">
                    Mô hình: <b><?= e(Settings::get('ai_model')) ?></b> · Token tối đa <?= num(Settings::int('ai_max_tokens', 64000)) ?>
                    · Timeout <?= (int)Settings::int('ai_timeout', 300) ?>s
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🤖</span> Trợ lý AI</div>
                <p class="small muted">Tính năng AI chấm bài chưa được bật.</p>
                <?php if (Auth::isAdmin()): ?>
                    <a class="btn btn-ghost btn-sm btn-block" href="<?= e(url('admin/ai')) ?>">Cấu hình ngay</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($asg && $asg['ai_rubric']): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">📐</span> Tiêu chí chấm</div>
                <div class="small" style="white-space:pre-wrap"><?= e($asg['ai_rubric']) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
