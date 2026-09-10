<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$launchUrl = base_url() . 'scorm.php/' . (int)$pkg['id'] . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim((string)$launch, '/'))));
$visibleItems = array_values(array_filter($manifest['items'], function ($i) { return !empty($i['href']) && !empty($i['visible']); }));
?>
<?php /* API SCORM phải sẵn sàng TRƯỚC khi iframe nội dung bắt đầu tải */ ?>
<script>
window.SCORM_CONFIG = {
    itemId: <?= (int)$item['id'] ?>,
    sco: <?= json_encode($sco) ?>,
    userId: <?= (int)Auth::id() ?>,
    userName: <?= json_encode(Auth::name()) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    trackUrl: <?= json_encode(url('scorm/track')) ?>,
    data: <?= json_encode($tracks ? $tracks : new stdClass(), JSON_UNESCAPED_UNICODE) ?>,
    debug: false
};
window.SCORM_ON_SAVE = function (d) {
    var st = d['cmi.core.lesson_status'] || d['cmi.completion_status'];
    var el = document.getElementById('scorm-lesson-status');
    if (el && st) {
        var map = { completed: 'Đã hoàn thành', passed: 'Đạt', failed: 'Chưa đạt',
                    incomplete: 'Đang học dở', browsed: 'Đã xem qua', 'not attempted': 'Chưa bắt đầu' };
        el.textContent = map[String(st).toLowerCase()] || st;
    }
};
function toggleFull() {
    var el = document.getElementById('scorm-wrap');
    if (!document.fullscreenElement) { if (el.requestFullscreen) el.requestFullscreen(); }
    else { document.exitFullscreen(); }
}
</script>
<script src="<?= e(asset('js/scorm-api.js')) ?>?v=<?= LMS_VERSION ?>"></script>

<div class="topbar" style="position:sticky;top:0">
    <a class="icon-btn" href="<?= e(url('course/view', ['id' => $course['id']])) ?>" title="Quay lại khoá học">←</a>
    <div class="flex-center">
        <span style="font-size:1.2rem">🧩</span>
        <div>
            <b><?= e($item['title']) ?></b>
            <div class="tiny muted"><?= e($course['title']) ?> · SCORM <?= e($pkg['version']) ?></div>
        </div>
    </div>
    <div class="topbar-spacer"></div>
    <span class="chip chip-blue" id="scorm-status">Đang tải nội dung…</span>
    <?php if ($canManage): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('scorm/report', ['id' => $item['id']])) ?>">📊 Kết quả lớp</a>
    <?php endif; ?>
    <button class="icon-btn" type="button" onclick="if(window.SCORM_COMMIT) SCORM_COMMIT();" title="Lưu tiến độ">💾</button>
    <button class="icon-btn" type="button" onclick="toggleFull()" title="Toàn màn hình">⛶</button>
</div>

<div class="content" style="max-width:1600px">
    <div class="grid" style="grid-template-columns:<?= count($visibleItems) > 1 ? '260px 1fr' : '1fr' ?>;align-items:start">
        <?php if (count($visibleItems) > 1): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">🗂️</span> Mục lục</div>
                <div class="scorm-toc">
                    <?php foreach ($visibleItems as $mi): ?>
                        <a class="<?= $mi['id'] === $sco ? 'active' : '' ?>"
                           href="<?= e(url('scorm/play', ['id' => $item['id'], 'sco' => $mi['id']])) ?>"
                           style="padding-left:<?= 12 + (int)$mi['depth'] * 14 ?>px">
                            <?= e($mi['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div>
            <div class="scorm-frame-wrap" id="scorm-wrap">
                <div class="scorm-bar">
                    <span class="small muted">Trạng thái:</span>
                    <b class="small" id="scorm-lesson-status"><?= e(Scorm::statusLabel(arr_get($summary, 'status'))) ?></b>
                    <?php if ($summary['score'] !== null): ?>
                        <?= chip('Điểm ' . $summary['score'] . ($summary['score_max'] ? '/' . $summary['score_max'] : ''), 'chip-green', '🏅') ?>
                    <?php endif; ?>
                    <div class="topbar-spacer"></div>
                    <span class="small muted">Tiến độ được lưu tự động vào hệ thống</span>
                </div>
                <iframe class="scorm-frame" id="scorm-frame" src="<?= e($launchUrl) ?>"
                        allow="autoplay; fullscreen; microphone; camera" allowfullscreen></iframe>
            </div>

            <div class="flex-between mt-2 flex-wrap gap-1">
                <a class="btn btn-ghost" href="<?= e(url('course/view', ['id' => $course['id']])) ?>">← Về khoá học</a>
                <div class="small muted">Nếu nội dung không hiện, hãy thử tải lại trang hoặc mở
                    <a href="<?= e($launchUrl) ?>" target="_blank" rel="noopener">trong tab mới</a>.</div>
            </div>
        </div>
    </div>
</div>
