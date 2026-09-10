<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$done = array_filter($attempts, function ($a) { return $a['status'] !== 'in_progress'; });
$maxAttempts = max(1, (int)$quiz['max_attempts']);
$closed = $item['close_at'] && strtotime($item['close_at']) < time();
$notOpen = $item['open_at'] && strtotime($item['open_at']) > time();
$canStart = !$closed && !$notOpen && (count($done) < $maxAttempts) && $questionCount > 0;
$best = null;
foreach ($done as $a) if ($best === null || (float)$a['score'] > (float)$best['score']) $best = $a;
?>
<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title']],
]) ?>

<div class="page-head flex-center">
    <span class="item-ico" style="width:52px;height:52px;font-size:24px;background:rgba(243,156,18,.14)">❓</span>
    <div>
        <h1 style="margin-bottom:2px"><?= e($item['title']) ?></h1>
        <div class="sub">Bài trắc nghiệm · <?= num($questionCount) ?> câu · <?= score_fmt($totalPoints) ?> điểm</div>
    </div>
</div>

<div class="grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
    <div>
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📋</span> Hướng dẫn làm bài</div>
            <?php if ($quiz['intro'] || $item['content']): ?>
                <div class="rich-content"><?= safe_html($quiz['intro'] ?: $item['content']) ?></div>
            <?php else: ?>
                <p class="muted">Em hãy đọc kỹ từng câu hỏi và chọn đáp án đúng nhất nhé!</p>
            <?php endif; ?>

            <ul class="small muted mt-2" style="padding-left:18px">
                <li>Số câu hỏi: <b><?= num($questionCount) ?></b> · Tổng điểm: <b><?= score_fmt($totalPoints) ?></b></li>
                <li>Thời gian: <b><?= $quiz['time_limit'] > 0 ? (int)$quiz['time_limit'] . ' phút' : 'Không giới hạn' ?></b></li>
                <li>Số lượt làm: <b><?= count($done) ?>/<?= $maxAttempts ?></b></li>
                <li>Cách tính điểm: <b><?= e(['highest' => 'Lấy điểm cao nhất', 'last' => 'Lấy lần cuối',
                    'first' => 'Lấy lần đầu', 'average' => 'Điểm trung bình các lần'][$quiz['grade_method']] ?? '') ?></b></li>
                <?php if ($item['close_at']): ?><li>Đóng bài lúc: <b><?= e(fmt_datetime($item['close_at'])) ?></b></li><?php endif; ?>
            </ul>
        </div>

        <?php if ($done): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">📊</span> Kết quả các lần làm</div>
                <div class="table-wrap" style="border:0">
                    <table class="data">
                        <thead><tr><th>Lần</th><th>Bắt đầu</th><th>Nộp lúc</th><th class="center">Điểm</th><th class="center">Đạt</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($done as $a):
                            $pct = $a['max_score'] > 0 ? round($a['score'] / $a['max_score'] * 100) : 0; ?>
                            <tr>
                                <td class="bold">#<?= (int)$a['number'] ?></td>
                                <td class="small"><?= e(fmt_datetime($a['started_at'])) ?></td>
                                <td class="small"><?= e(fmt_datetime($a['finished_at'])) ?></td>
                                <td class="center bold"><?= score_fmt($a['score']) ?>/<?= score_fmt($a['max_score']) ?>
                                    <span class="tiny muted">(<?= $pct ?>%)</span></td>
                                <td class="center"><?= $pct >= (float)$quiz['pass_score'] ? chip('Đạt', 'chip-green', '✓') : chip('Chưa đạt', 'chip-red') ?></td>
                                <td class="right"><a class="btn btn-ghost btn-sm" href="<?= e(url('quiz/result', ['a' => $a['id']])) ?>">Xem lại</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card mb-3 center">
            <?php if ($inProgress): ?>
                <div style="font-size:48px">⏳</div>
                <h3>Bạn đang làm dở bài này</h3>
                <p class="small muted">Tiếp tục lượt làm bài đang mở.</p>
                <a class="btn btn-warning btn-lg btn-block" href="<?= e(url('quiz/attempt', ['a' => $inProgress['id']])) ?>">Tiếp tục làm bài →</a>
            <?php elseif ($canStart): ?>
                <div style="font-size:48px">🚀</div>
                <h3>Sẵn sàng chưa nào?</h3>
                <p class="small muted">Hãy chuẩn bị giấy nháp và bắt đầu khi em đã sẵn sàng.</p>
                <form method="post" action="<?= e(url('quiz/start')) ?>" data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <button class="btn btn-primary btn-lg btn-block" type="submit">Bắt đầu làm bài</button>
                </form>
            <?php elseif ($notOpen): ?>
                <div style="font-size:48px">🔒</div>
                <h3>Chưa tới giờ mở bài</h3>
                <p class="small muted">Bài sẽ mở lúc <b><?= e(fmt_datetime($item['open_at'])) ?></b>.</p>
            <?php elseif ($closed): ?>
                <div style="font-size:48px">⛔</div>
                <h3>Bài đã đóng</h3>
                <p class="small muted">Đã quá thời hạn làm bài.</p>
            <?php elseif ($questionCount === 0): ?>
                <div style="font-size:48px">📭</div>
                <h3>Chưa có câu hỏi</h3>
                <p class="small muted">Giáo viên đang soạn câu hỏi cho bài này.</p>
            <?php else: ?>
                <div style="font-size:48px">✅</div>
                <h3>Đã hoàn thành</h3>
                <p class="small muted">Em đã dùng hết <?= $maxAttempts ?> lượt làm bài.</p>
            <?php endif; ?>
        </div>

        <?php if ($best): ?>
            <div class="card center">
                <div class="card-title" style="justify-content:center"><span class="emoji">🏆</span> Điểm tốt nhất</div>
                <?php $pct = $best['max_score'] > 0 ? round($best['score'] / $best['max_score'] * 100) : 0; ?>
                <?= svg_donut([
                    ['label' => 'Đạt', 'value' => $pct, 'color' => $pct >= (float)$quiz['pass_score'] ? '#00B894' : '#FF7675'],
                    ['label' => 'Còn lại', 'value' => 100 - $pct, 'color' => 'rgba(0,0,0,.06)'],
                ], ['center' => $pct . '%', 'sub' => score_fmt($best['score']) . '/' . score_fmt($best['max_score'])]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
