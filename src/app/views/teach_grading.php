<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>✅ Cần chấm bài</h1>
        <div class="sub"><?= num(count($rows)) ?> bài tập và <?= num(count($quizPending)) ?> bài trắc nghiệm đang chờ.</div>
    </div>
</div>

<?php if (!$rows && !$quizPending): ?>
    <div class="card"><?= empty_state('🎊', 'Tuyệt vời! Không còn bài nào chờ chấm',
        'Thầy cô đã chấm hết bài rồi. Nghỉ ngơi một chút nhé!') ?></div>
<?php endif; ?>

<?php if ($rows): ?>
    <div class="card card-pad-0 mb-3">
        <div class="card-header">
            <b>📝 Bài tập chờ chấm (<?= count($rows) ?>)</b>
            <input type="search" placeholder="🔍 Tìm…" data-filter-table="#tbl-grading" style="max-width:220px">
        </div>
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data" id="tbl-grading">
                <thead><tr><th>Học sinh</th><th>Bài tập</th><th>Khoá học</th>
                    <th class="center">Nộp lúc</th><th class="center">AI</th><th class="right"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><div class="user-row"><?= avatar_tag($r, 32) ?>
                            <div><div class="u-name"><?= e($r['full_name']) ?></div>
                                 <div class="u-sub"><?= e($r['username']) ?></div></div></div></td>
                        <td><a href="<?= e(url('assign/list', ['id' => $r['item_id']])) ?>"><?= e(str_limit($r['item_title'], 34)) ?></a></td>
                        <td class="small muted"><?= e(str_limit($r['course_title'], 28)) ?></td>
                        <td class="center small"><?= e(time_ago($r['submitted_at'])) ?>
                            <?php if ($r['is_late']) echo '<br>' . chip('Trễ', 'chip-orange'); ?></td>
                        <td class="center"><?= $r['ai_score'] !== null ? chip(score_fmt($r['ai_score']), 'chip-purple', '🤖') : '<span class="muted">—</span>' ?></td>
                        <td class="right"><a class="btn btn-primary btn-sm" href="<?= e(url('assign/grade', ['id' => $r['item_id'], 'sub' => $r['id']])) ?>">Chấm ngay</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($quizPending): ?>
    <div class="card card-pad-0">
        <div class="card-header"><b>❓ Bài trắc nghiệm có câu tự luận chờ chấm (<?= count($quizPending) ?>)</b></div>
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data">
                <thead><tr><th>Học sinh</th><th>Bài</th><th>Khoá học</th><th class="center">Nộp lúc</th>
                    <th class="center">Điểm tạm</th><th class="right"></th></tr></thead>
                <tbody>
                <?php foreach ($quizPending as $a): ?>
                    <tr>
                        <td><div class="user-row"><?= avatar_tag($a, 32) ?>
                            <div class="u-name"><?= e($a['full_name']) ?></div></div></td>
                        <td><?= e(str_limit($a['item_title'], 34)) ?></td>
                        <td class="small muted"><?= e(str_limit($a['course_title'], 28)) ?></td>
                        <td class="center small"><?= e(time_ago($a['finished_at'])) ?></td>
                        <td class="center bold"><?= score_fmt($a['score']) ?>/<?= score_fmt($a['max_score']) ?></td>
                        <td class="right"><a class="btn btn-primary btn-sm" href="<?= e(url('quiz/result', ['a' => $a['id']])) ?>">Chấm tự luận</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
