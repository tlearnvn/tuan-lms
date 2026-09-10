<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('teach/content', ['id' => $course['id']])],
    ['label' => $item['title'], 'url' => url('quiz/view', ['id' => $item['id']])],
    ['label' => 'Câu hỏi'],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🧩 Soạn câu hỏi</h1>
        <div class="sub"><?= e($item['title']) ?> · <?= num(count($questions)) ?> câu ·
            tổng <?= score_fmt(array_sum(array_column($questions, 'points'))) ?> điểm</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('teach/item/edit', ['id' => $item['id']])) ?>">⚙️ Cấu hình bài</a>
        <a class="btn btn-ghost" href="<?= e(url('quiz/view', ['id' => $item['id']])) ?>">👁️ Xem kết quả lớp</a>
    </div>
</div>

<div class="grid" style="grid-template-columns:1fr 400px;align-items:start">
    <div>
        <?php if (!$questions): ?>
            <div class="card"><?= empty_state('❓', 'Chưa có câu hỏi nào', 'Dùng khung bên phải để thêm câu hỏi đầu tiên.') ?></div>
        <?php endif; ?>

        <?php foreach ($questions as $n => $q):
            $tl = ['single' => 'Chọn 1 đáp án', 'multi' => 'Chọn nhiều', 'truefalse' => 'Đúng/Sai',
                   'short' => 'Trả lời ngắn', 'essay' => 'Tự luận'];
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']; ?>
            <div class="question-card">
                <div class="flex" style="align-items:flex-start;gap:6px">
                    <span class="question-num"><?= $n + 1 ?></span>
                    <div class="flex-1">
                        <div class="flex-between flex-wrap mb-1">
                            <div class="flex gap-1 flex-wrap">
                                <?= chip($tl[$q['type']] ?? $q['type'], 'chip-purple') ?>
                                <?= chip(score_fmt($q['points']) . ' điểm', 'chip-gray') ?>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-ghost btn-sm" type="button"
                                    onclick='editQuestion(<?= json_encode([
                                        "id" => (int)$q["id"], "type" => $q["type"], "content" => $q["content"],
                                        "points" => (float)$q["points"], "feedback" => (string)$q["feedback"],
                                        "answer_key" => (string)$q["answer_key"],
                                        "options" => array_map(function ($o) { return ["content" => $o["content"], "correct" => (int)$o["correct"]]; }, $q["options"]),
                                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️</button>
                                <form method="post" action="<?= e(url('teach/question/delete')) ?>" style="display:inline"
                                      onsubmit="return confirm('Xoá câu hỏi này?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <div class="rich-content"><?= safe_html($q['content']) ?></div>
                        <?php if ($q['image_id']): ?>
                            <img src="<?= e(media_url($q['image_id'])) ?>" alt="" style="max-width:320px;border-radius:10px;margin:8px 0">
                        <?php endif; ?>

                        <?php if ($q['options']): ?>
                            <div class="mt-1">
                                <?php foreach ($q['options'] as $oi => $o): ?>
                                    <div class="answer-option <?= $o['correct'] ? 'correct' : '' ?>" style="cursor:default;padding:8px 12px;margin-bottom:5px">
                                        <span class="answer-letter"><?= isset($letters[$oi]) ? $letters[$oi] : ($oi + 1) ?></span>
                                        <span class="flex-1 small"><?= safe_html($o['content']) ?></span>
                                        <?php if ($o['correct']) echo '✅'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($q['answer_key']): ?>
                            <div class="small mt-1"><b>🔑 Đáp án:</b> <?= e($q['answer_key']) ?></div>
                        <?php endif; ?>
                        <?php if ($q['feedback']): ?>
                            <div class="small muted mt-1">💡 <?= e($q['feedback']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div>
        <div class="card" style="position:sticky;top:88px">
            <div class="card-title"><span class="emoji">➕</span> <span id="qf-title">Thêm câu hỏi</span></div>
            <form method="post" action="<?= e(url('teach/question/save')) ?>" enctype="multipart/form-data" id="qform">
                <?= csrf_field() ?>
                <input type="hidden" name="quiz" value="<?= (int)$quiz['id'] ?>">
                <input type="hidden" name="qid" id="qf-id" value="">

                <div class="form-group">
                    <label>Loại câu hỏi</label>
                    <select name="type" id="qf-type" onchange="switchQType(this.value)">
                        <option value="single">Trắc nghiệm — chọn 1 đáp án</option>
                        <option value="multi">Trắc nghiệm — chọn nhiều đáp án</option>
                        <option value="truefalse">Đúng / Sai</option>
                        <option value="short">Trả lời ngắn (tự động chấm)</option>
                        <option value="essay">Tự luận (giáo viên hoặc AI chấm)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nội dung câu hỏi <span class="req">*</span></label>
                    <textarea name="content" id="qf-content" rows="4" required data-editor data-autogrow placeholder="Nhập đề bài…"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm</label>
                        <input type="number" name="points" id="qf-points" step="0.25" min="0" value="1">
                    </div>
                    <div class="form-group">
                        <label>Ảnh minh hoạ</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                </div>

                <div id="qf-options">
                    <label>Phương án trả lời <span class="req">*</span></label>
                    <div id="opt-list"></div>
                    <button class="btn btn-ghost btn-sm btn-block mb-2" type="button" onclick="addOption()">➕ Thêm phương án</button>
                    <div class="form-hint">Tích ô vuông/tròn ở phương án đúng.</div>
                </div>

                <div id="qf-key" class="form-group hidden">
                    <label>Đáp án đúng</label>
                    <input type="text" name="answer_key" id="qf-answer-key" placeholder="Nhiều đáp án chấp nhận, ngăn cách bằng dấu |">
                    <div class="form-hint">Không phân biệt hoa thường và dấu tiếng Việt. Ví dụ: <code>Hà Nội|ha noi</code></div>
                </div>

                <div class="form-group">
                    <label>Giải thích sau khi làm bài</label>
                    <textarea name="feedback" id="qf-feedback" rows="2" placeholder="Giải thích đáp án cho học sinh"></textarea>
                </div>

                <button class="btn btn-primary btn-block" type="submit">💾 Lưu câu hỏi</button>
                <button class="btn btn-ghost btn-block mt-1 hidden" id="qf-cancel" type="button" onclick="resetQForm()">Huỷ sửa</button>
            </form>
        </div>
    </div>
</div>

<script>
var optIndex = 0;
function addOption(text, correct) {
    var multi = document.getElementById('qf-type').value === 'multi';
    var wrap = document.getElementById('opt-list');
    var div = document.createElement('div');
    div.className = 'flex-center gap-1 mb-1';
    div.innerHTML = '<input type="' + (multi ? 'checkbox' : 'radio') + '" name="correct[]" value="' + optIndex + '"'
        + (correct ? ' checked' : '') + ' style="width:19px;height:19px;accent-color:var(--primary);flex-shrink:0">'
        + '<input type="text" name="option[' + optIndex + ']" class="flex-1" placeholder="Nội dung phương án" value="'
        + (text ? String(text).replace(/"/g, '&quot;') : '') + '">'
        + '<button class="btn btn-ghost btn-sm" type="button" onclick="this.parentNode.remove()">✕</button>';
    wrap.appendChild(div);
    optIndex++;
}
function switchQType(t) {
    var opts = document.getElementById('qf-options');
    var key = document.getElementById('qf-key');
    if (t === 'short') { opts.classList.add('hidden'); key.classList.remove('hidden'); }
    else if (t === 'essay') { opts.classList.add('hidden'); key.classList.remove('hidden'); }
    else {
        opts.classList.remove('hidden'); key.classList.add('hidden');
        var boxes = document.querySelectorAll('#opt-list input[name="correct[]"]');
        boxes.forEach(function (b) { b.type = (t === 'multi') ? 'checkbox' : 'radio'; });
        if (t === 'truefalse' && boxes.length === 0) { addOption('Đúng', true); addOption('Sai', false); }
        else if (boxes.length === 0) { addOption('', true); addOption('', false); addOption('', false); addOption('', false); }
    }
    document.getElementById('qf-key').querySelector('label').textContent =
        t === 'essay' ? 'Đáp án gợi ý / tiêu chí chấm' : 'Đáp án đúng';
}
function editQuestion(q) {
    document.getElementById('qf-id').value = q.id;
    document.getElementById('qf-type').value = q.type;
    document.getElementById('qf-content').value = q.content.replace(/<[^>]*>/g, '');
    document.getElementById('qf-points').value = q.points;
    document.getElementById('qf-feedback').value = q.feedback || '';
    document.getElementById('qf-answer-key').value = q.answer_key || '';
    document.getElementById('opt-list').innerHTML = '';
    optIndex = 0;
    (q.options || []).forEach(function (o) { addOption(String(o.content).replace(/<[^>]*>/g, ''), o.correct); });
    switchQType(q.type);
    document.getElementById('qf-title').textContent = 'Sửa câu hỏi';
    document.getElementById('qf-cancel').classList.remove('hidden');
    document.getElementById('qform').scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function resetQForm() {
    document.getElementById('qform').reset();
    document.getElementById('qf-id').value = '';
    document.getElementById('opt-list').innerHTML = '';
    optIndex = 0;
    switchQType(document.getElementById('qf-type').value);
    document.getElementById('qf-title').textContent = 'Thêm câu hỏi';
    document.getElementById('qf-cancel').classList.add('hidden');
}
document.addEventListener('DOMContentLoaded', function () { switchQType('single'); });
</script>
