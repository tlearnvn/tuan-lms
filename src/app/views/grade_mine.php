<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🏅 Kết quả học tập của tôi</h1>
        <div class="sub">Theo dõi điểm số từng bài và điểm trung bình chung.</div>
    </div>
    <?php if (count($courses) > 1): ?>
        <form method="get" action="<?= e(base_url() . 'index.php') ?>" class="page-actions">
            <input type="hidden" name="r" value="grade/mine">
            <select name="course" onchange="this.form.submit()" style="min-width:230px">
                <option value="">— Tất cả khoá học —</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<?php if ($overall10 !== null): list($rank, $col) = grade_rank($overall10); ?>
    <div class="card mb-3" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;border:0">
        <div class="flex-between flex-wrap">
            <div>
                <div style="opacity:.9">Điểm trung bình chung của em</div>
                <div style="font-size:3rem;font-weight:800;line-height:1.1"><?= score_fmt($overall10) ?><span style="font-size:1.2rem;opacity:.8">/10</span></div>
                <div class="hero-badge" style="display:inline-block;margin-top:8px">Xếp loại: <?= e($rank) ?></div>
            </div>
            <div style="font-size:64px"><?= $overall10 >= 8 ? '🏆' : ($overall10 >= 6.5 ? '🌟' : '💪') ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="card"><?= empty_state('📭', 'Chưa có dữ liệu điểm', 'Em hãy tham gia khoá học và hoàn thành bài tập nhé!') ?></div>
<?php endif; ?>

<?php foreach ($rows as $r): list($rank, $col) = grade_rank($r['score10']); ?>
    <div class="card mb-3 card-pad-0">
        <div class="card-header">
            <div>
                <b><?= e($r['course']['title']) ?></b>
                <div class="tiny muted"><?= e($r['course']['code']) ?></div>
            </div>
            <div class="flex-center gap-1">
                <?php if ($r['score10'] !== null): ?>
                    <span class="bold" style="font-size:1.2rem"><?= score_fmt($r['score10']) ?>/10</span>
                    <?= chip($rank, 'chip-' . $col) ?>
                <?php else: ?>
                    <?= chip('Chưa có điểm', 'chip-gray') ?>
                <?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('course/view', ['id' => $r['course']['id']])) ?>">Vào lớp</a>
            </div>
        </div>
        <?php if (!$r['items']): ?>
            <p class="muted center" style="padding:18px">Khoá học chưa có đầu điểm nào.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data">
                    <thead><tr><th>Đầu điểm</th><th class="center">Loại</th><th class="center">Điểm</th>
                        <th class="center">Thang 10</th><th class="center">Đánh giá</th></tr></thead>
                    <tbody>
                    <?php foreach ($r['items'] as $row):
                        $it = $row['item']; $m = item_type_meta($it['type']);
                        $s10 = ($row['score'] !== null && $it['max_points'] > 0) ? round($row['score'] / $it['max_points'] * 10, 2) : null;
                        list($rk, $cl) = grade_rank($s10); ?>
                        <tr>
                            <td><a href="<?= e(url('item/view', ['id' => $it['id']])) ?>"><?= e($it['title']) ?></a>
                                <?php if ((float)$it['weight'] != 1): ?><span class="tiny muted"> (hệ số <?= score_fmt($it['weight']) ?>)</span><?php endif; ?></td>
                            <td class="center"><?= $m[0] ?> <span class="small muted"><?= e($m[1]) ?></span></td>
                            <td class="center bold"><?= $row['score'] !== null ? score_fmt($row['score']) . '<span class="tiny muted">/' . score_fmt($it['max_points']) . '</span>' : '<span class="muted">—</span>' ?></td>
                            <td class="center"><?= $s10 !== null ? score_fmt($s10) : '—' ?></td>
                            <td class="center"><?= $s10 !== null ? chip($rk, 'chip-' . $cl) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
