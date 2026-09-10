<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head">
    <h1>📝 Bài tập &amp; hạn nộp</h1>
    <div class="sub">Toàn bộ bài tập và bài trắc nghiệm của các lớp em đang học.</div>
</div>

<?php if (!$rows): ?>
    <div class="card"><?= empty_state('🎈', 'Không có bài nào', 'Em chưa có bài tập nào cần làm. Cứ thư giãn nhé!') ?></div>
<?php else: ?>
    <div class="card card-pad-0">
        <div class="card-header">
            <b>Tổng cộng <?= num(count($rows)) ?> bài</b>
            <input type="search" placeholder="🔍 Tìm bài…" data-filter-table="#tbl-dl" style="max-width:240px">
        </div>
        <div class="table-wrap" style="border:0;border-radius:0">
            <table class="data" id="tbl-dl">
                <thead><tr><th>Bài</th><th>Khoá học</th><th class="center">Hạn nộp</th>
                    <th class="center">Trạng thái</th><th class="center">Điểm</th><th class="right"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $m = item_type_meta($r['type']);
                    $badge = deadline_badge($r['due_at']);
                    $done = in_array($r['sub_status'], ['submitted', 'graded', 'returned'], true); ?>
                    <tr>
                        <td><span style="font-size:1.05rem"><?= $m[0] ?></span>
                            <a href="<?= e(url('item/view', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></td>
                        <td class="small muted"><?= e(str_limit($r['course_title'], 28)) ?></td>
                        <td class="center"><?= chip($badge['label'], $badge['class']) ?></td>
                        <td class="center"><?= $done ? chip('Đã nộp', 'chip-green', '✓') : chip('Chưa làm', 'chip-gray') ?></td>
                        <td class="center bold"><?= $r['sub_score'] !== null ? score_fmt($r['sub_score']) . '<span class="tiny muted">/' . score_fmt($r['max_points']) . '</span>' : '—' ?></td>
                        <td class="right"><a class="btn <?= $done ? 'btn-ghost' : 'btn-primary' ?> btn-sm"
                            href="<?= e(url('item/view', ['id' => $r['id']])) ?>"><?= $done ? 'Xem lại' : 'Làm bài' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
