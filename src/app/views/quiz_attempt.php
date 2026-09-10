<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="topbar" style="position:sticky;top:0">
    <div class="flex-center">
        <?php if (logo_url()): ?><img src="<?= e(logo_url()) ?>" alt="" style="width:32px;height:32px;object-fit:contain">
        <?php else: ?><span class="logo-fallback" style="width:32px;height:32px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff">🎓</span><?php endif; ?>
        <b><?= e($item['title']) ?></b>
    </div>
    <div class="topbar-spacer"></div>
    <span class="chip chip-purple">Lần làm #<?= (int)$attempt['number'] ?></span>
    <span class="chip chip-blue" id="save-state">Đã lưu tự động</span>
</div>

<div class="content quiz-shell">
    <div class="quiz-timer">
        <span style="font-size:1.4rem">⏱️</span>
        <div class="flex-1">
            <?php if ($deadline): ?>
                <div class="small muted">Thời gian còn lại</div>
                <div class="time" id="quiz-time">--:--</div>
            <?php else: ?>
                <div class="small muted">Bài làm không giới hạn thời gian</div>
                <div class="time" style="font-size:1rem">Hãy làm bài thật cẩn thận nhé!</div>
            <?php endif; ?>
        </div>
        <div class="question-nav" id="qnav">
            <?php foreach ($questions as $i => $q): ?>
                <button type="button" class="qnav-btn<?= !empty($answers[$q['id']]) ? ' answered' : '' ?>"
                        data-q="<?= (int)$q['id'] ?>" onclick="document.getElementById('q-<?= (int)$q['id'] ?>').scrollIntoView({behavior:'smooth',block:'center'})">
                    <?= $i + 1 ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post" action="<?= e(url('quiz/finish')) ?>" id="quiz-form" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="a" value="<?= (int)$attempt['id'] ?>">

        <?php foreach ($questions as $i => $q):
            $saved = isset($answers[$q['id']]) ? $answers[$q['id']] : '';
            $savedMulti = $q['type'] === 'multi' ? array_map('intval', json_decode_safe($saved, [])) : [];
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']; ?>
            <div class="question-card fade-up" id="q-<?= (int)$q['id'] ?>">
                <div class="flex" style="align-items:flex-start;gap:4px">
                    <span class="question-num"><?= $i + 1 ?></span>
                    <div class="flex-1">
                        <div class="rich-content" style="margin-bottom:10px"><?= safe_html($q['content']) ?></div>
                        <?php if ($q['image_id']): ?>
                            <img src="<?= e(media_url($q['image_id'])) ?>" alt="" style="max-width:100%;border-radius:12px;margin-bottom:12px">
                        <?php endif; ?>

                        <div class="small muted mb-2">
                            <?= chip(score_fmt($q['points']) . ' điểm', 'chip-purple') ?>
                            <?php
                            $tl = ['single' => 'Chọn 1 đáp án', 'multi' => 'Chọn nhiều đáp án',
                                   'truefalse' => 'Đúng / Sai', 'short' => 'Trả lời ngắn', 'essay' => 'Tự luận'];
                            echo chip($tl[$q['type']] ?? '', 'chip-gray'); ?>
                        </div>

                        <?php if ($q['type'] === 'short'): ?>
                            <input type="text" name="answer[<?= (int)$q['id'] ?>]" value="<?= e($saved) ?>"
                                   data-qid="<?= (int)$q['id'] ?>" class="q-input" placeholder="Nhập câu trả lời của em…">

                        <?php elseif ($q['type'] === 'essay'): ?>
                            <textarea name="answer[<?= (int)$q['id'] ?>]" rows="7" data-qid="<?= (int)$q['id'] ?>"
                                      class="q-input" data-editor data-autogrow placeholder="Em viết bài làm ở đây…"><?= e($saved) ?></textarea>

                        <?php else: ?>
                            <?php foreach ($q['options'] as $oi => $o):
                                $isMulti = $q['type'] === 'multi';
                                $checked = $isMulti ? in_array((int)$o['id'], $savedMulti, true) : ((string)$saved === (string)$o['id']); ?>
                                <label class="answer-option<?= $checked ? ' selected' : '' ?>">
                                    <input type="<?= $isMulti ? 'checkbox' : 'radio' ?>"
                                           name="answer[<?= (int)$q['id'] ?>]<?= $isMulti ? '[]' : '' ?>"
                                           value="<?= (int)$o['id'] ?>" data-qid="<?= (int)$q['id'] ?>"
                                           class="q-choice" <?= $checked ? 'checked' : '' ?>>
                                    <span class="answer-letter"><?= isset($letters[$oi]) ? $letters[$oi] : ($oi + 1) ?></span>
                                    <span class="flex-1"><?= safe_html($o['content']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card center">
            <h3>Đã làm xong chưa nào? 🎯</h3>
            <p class="muted small">Hãy kiểm tra lại các câu chưa tô màu ở thanh điều hướng phía trên trước khi nộp bài.</p>
            <button class="btn btn-success btn-lg" type="submit"
                    onclick="return confirm('Em chắc chắn muốn nộp bài chứ? Sau khi nộp sẽ không sửa được nữa.')">
                🚀 Nộp bài
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var saveUrl = <?= json_encode(url('quiz/save')) ?>;
    var attempt = <?= (int)$attempt['id'] ?>;
    var state = document.getElementById('save-state');

    function markNav(qid, has) {
        var btn = document.querySelector('.qnav-btn[data-q="' + qid + '"]');
        if (btn) btn.classList.toggle('answered', !!has);
    }
    function save(qid, value) {
        state.textContent = 'Đang lưu…'; state.className = 'chip chip-yellow';
        var fd = new FormData();
        fd.append('a', attempt); fd.append('q', qid);
        if (Array.isArray(value)) value.forEach(function (v) { fd.append('v[]', v); });
        else fd.append('v', value);
        LMS.post(saveUrl, fd, function (res) {
            state.textContent = res.ok ? ('Đã lưu ' + res.saved_at) : 'Lỗi lưu bài';
            state.className = 'chip ' + (res.ok ? 'chip-green' : 'chip-red');
        }, function () { state.textContent = 'Mất kết nối'; state.className = 'chip chip-red'; });
    }

    LMS.$$('.q-choice').forEach(function (input) {
        input.addEventListener('change', function () {
            var qid = input.getAttribute('data-qid');
            var group = LMS.$$('.q-choice[data-qid="' + qid + '"]');
            group.forEach(function (g) { g.closest('.answer-option').classList.toggle('selected', g.checked); });
            if (input.type === 'checkbox') {
                var vals = group.filter(function (g) { return g.checked; }).map(function (g) { return g.value; });
                markNav(qid, vals.length > 0);
                save(qid, vals);
            } else {
                markNav(qid, true);
                save(qid, input.value);
            }
        });
    });

    LMS.$$('.q-input').forEach(function (el) {
        var timer = null;
        el.addEventListener('input', function () {
            markNav(el.getAttribute('data-qid'), el.value.trim() !== '');
            clearTimeout(timer);
            timer = setTimeout(function () { save(el.getAttribute('data-qid'), el.value); }, 900);
        });
    });

    <?php if ($deadline): ?>
    LMS.startTimer({
        el: 'quiz-time',
        seconds: <?= max(0, $deadline - time()) ?>,
        onExpire: function () {
            LMS.toast('warning', 'Đã hết giờ làm bài — hệ thống đang nộp bài giúp em.');
            setTimeout(function () { document.getElementById('quiz-form').submit(); }, 1200);
        }
    });
    <?php endif; ?>
});
</script>
