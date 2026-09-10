<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
$i = $item ?: [];
$v = function ($k, $d = '') use ($i) { return isset($i[$k]) && $i[$k] !== null ? $i[$k] : $d; };
$a = $asg ?: [];
$av = function ($k, $d = '') use ($a) { return isset($a[$k]) && $a[$k] !== null ? $a[$k] : $d; };
$q = $quiz ?: [];
$qv = function ($k, $d = '') use ($q) { return isset($q[$k]) && $q[$k] !== null ? $q[$k] : $d; };
$type = $item ? $item['type'] : $defaultType;
$dt = function ($s) { return $s ? str_replace(' ', 'T', substr($s, 0, 16)) : ''; };
$subTypes = array_filter(array_map('trim', explode(',', (string)$av('submission_type', 'file,text'))));
?>

<?= breadcrumbs([
    ['label' => $course['title'], 'url' => url('teach/content', ['id' => $course['id']])],
    ['label' => $item ? $item['title'] : 'Thêm nội dung'],
]) ?>

<div class="page-head">
    <h1><?= $item ? '✏️ Chỉnh sửa nội dung' : '✨ Thêm nội dung mới' ?></h1>
    <div class="sub"><?= e($course['title']) ?></div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><span>⚠️</span><div>
        <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" data-warn-unsaved>
    <?= csrf_field() ?>
    <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>
    <input type="hidden" name="course" value="<?= (int)$course['id'] ?>">

    <div class="grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
        <div>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🧩</span> Loại nội dung</div>
                <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px">
                    <?php foreach (['page', 'file', 'video', 'link', 'scorm', 'assignment', 'quiz', 'forum'] as $t):
                        $m = item_type_meta($t); ?>
                        <label class="answer-option" style="margin:0;flex-direction:column;text-align:center;gap:4px;padding:12px 8px">
                            <input type="radio" name="type" value="<?= $t ?>" <?= $type === $t ? 'checked' : '' ?>
                                   onchange="switchType(this.value)" style="display:none">
                            <span style="font-size:24px"><?= $m[0] ?></span>
                            <span class="small bold"><?= e($m[1]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-title"><span class="emoji">📝</span> Thông tin chung</div>
                <div class="form-group">
                    <label>Tiêu đề <span class="req">*</span></label>
                    <input type="text" name="title" value="<?= e($v('title')) ?>" required placeholder="vd: Bài 1 – Mệnh đề và tập hợp">
                </div>
                <div class="form-group">
                    <label>Mô tả ngắn</label>
                    <textarea name="summary" rows="2" placeholder="Một dòng giới thiệu hiện dưới tiêu đề"><?= e($v('summary')) ?></textarea>
                </div>

                <div class="form-group tp tp-page tp-file tp-video tp-link tp-forum">
                    <label>Nội dung chi tiết</label>
                    <textarea name="content" rows="10" data-editor data-autogrow
                        placeholder="Nội dung bài học. Có thể dùng thẻ HTML: <h3>, <b>, <ul>, <img>, <table>, <iframe>…"><?= e($v('content')) ?></textarea>
                    <div class="form-hint">Mẹo: dán mã nhúng &lt;iframe&gt; của YouTube, Google Drive hoặc H5P vào đây đều hiển thị được.</div>
                </div>

                <div class="form-group tp tp-video tp-link">
                    <label>Đường dẫn (URL)</label>
                    <input type="url" name="url" value="<?= e($v('url')) ?>" placeholder="https://www.youtube.com/watch?v=…">
                    <div class="form-hint">Hỗ trợ tự nhận diện YouTube và Vimeo.</div>
                </div>
            </div>

            <!-- Tệp học liệu -->
            <div class="card mb-3 tp tp-file tp-video tp-page">
                <div class="card-title"><span class="emoji">📎</span> Tệp học liệu</div>
                <?php if ($mainFile): ?>
                    <div class="file-item mb-2">
                        <?php list($ic, $col) = file_icon($mainFile['ext']); ?>
                        <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                        <div class="flex-1"><div class="file-name"><?= e($mainFile['name']) ?></div>
                            <div class="file-meta"><?= human_size($mainFile['size']) ?> · tệp chính hiện tại</div></div>
                        <?php if (Preview::supports($mainFile['ext'])): ?>
                            <button type="button" class="btn btn-ghost btn-sm" data-preview="<?= (int)$mainFile['id'] ?>">👁️ Xem trước</button>
                        <?php endif; ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(media_url($mainFile['id'], true)) ?>" title="Tải về">⬇️</a>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Tệp chính<?= $mainFile ? ' (chọn tệp mới để thay thế)' : '' ?></label>
                    <div class="dropzone" style="padding:20px">
                        <input type="file" name="main_file" style="display:none">
                        <span class="dz-emoji">📤</span>
                        <div>Kéo thả hoặc <b>bấm để chọn tệp</b></div>
                        <div class="small muted mt-1">Tối đa <?= Settings::int('max_upload_mb', 64) ?> MB ·
                            máy chủ đang cho phép <?= human_size(server_upload_limit()) ?></div>
                    </div>
                    <div class="file-preview"></div>
                </div>
            </div>

            <!-- Gói SCORM -->
            <div class="card mb-3 tp tp-scorm">
                <div class="card-title"><span class="emoji">🧩</span> Gói SCORM</div>
                <?php if ($pkg): ?>
                    <div class="alert alert-success"><span>✅</span><div>
                        Đã có gói: <b><?= e($pkg['title'] ?: $pkg['identifier']) ?></b><br>
                        Phiên bản SCORM <?= e($pkg['version']) ?> · <?= num($pkg['entry_count']) ?> tệp ·
                        khởi chạy <code><?= e($pkg['launch_url']) ?></code><br>
                        <a href="<?= e(url('scorm/play', ['id' => $item['id']])) ?>">▶️ Chạy thử</a> ·
                        <a href="<?= e(url('scorm/report', ['id' => $item['id']])) ?>">📊 Kết quả lớp</a>
                        <?php if ($pkg['file_id']): ?> · <a href="<?= e(media_url($pkg['file_id'], true)) ?>">⬇️ Tải gói gốc</a><?php endif; ?>
                    </div></div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Tệp gói SCORM (.zip)<?= $pkg ? ' — tải lên gói mới sẽ thay thế gói cũ' : '' ?></label>
                    <div class="dropzone" style="padding:20px">
                        <input type="file" name="scorm_zip" accept=".zip,application/zip" style="display:none">
                        <span class="dz-emoji">🧩</span>
                        <div>Chọn tệp <b>.zip</b> xuất từ Articulate, iSpring, Adobe Captivate, H5P…</div>
                        <div class="small muted mt-1">Gói phải chứa tệp <code>imsmanifest.xml</code>. Hỗ trợ SCORM 1.2 và SCORM 2004.</div>
                    </div>
                    <div class="file-preview"></div>
                </div>
                <div class="alert alert-info"><span>💡</span><div>Toàn bộ tệp trong gói sẽ được giải nén và lưu vào cơ sở dữ liệu MySQL —
                    không cần thư mục ghi trên hosting.</div></div>
            </div>

            <!-- Bài tập -->
            <div class="card mb-3 tp tp-assignment">
                <div class="card-title"><span class="emoji">📝</span> Cấu hình bài tập</div>
                <div class="form-group">
                    <label>Đề bài / yêu cầu</label>
                    <textarea name="instructions" rows="8" data-editor data-autogrow
                        placeholder="Mô tả rõ yêu cầu, hình thức trình bày, tiêu chí đánh giá…"><?= e($av('instructions')) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hạn nộp</label>
                        <input type="datetime-local" name="due_at" value="<?= e($dt($av('due_at'))) ?>">
                    </div>
                    <div class="form-group">
                        <label>Đóng nhận bài</label>
                        <input type="datetime-local" name="cutoff_at" value="<?= e($dt($av('cutoff_at'))) ?>">
                        <div class="form-hint">Sau thời điểm này không nhận bài dù cho phép nộp trễ.</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Số lần được nộp</label>
                        <input type="number" name="max_attempts" min="1" value="<?= (int)$av('max_attempts', 1) ?>">
                    </div>
                    <div class="form-group">
                        <label>Số tệp tối đa mỗi lần</label>
                        <input type="number" name="max_files" min="1" value="<?= (int)$av('max_files', 5) ?>">
                    </div>
                    <div class="form-group">
                        <label>Định dạng cho phép</label>
                        <input type="text" name="allowed_ext" value="<?= e($av('allowed_ext')) ?>" placeholder="pdf,docx,jpg — để trống là cho phép tất cả">
                    </div>
                </div>
                <div class="form-group">
                    <label>Hình thức nộp</label>
                    <div class="check-row">
                        <input type="checkbox" id="sub_file" name="sub_file" value="1" <?= in_array('file', $subTypes, true) ? 'checked' : '' ?>>
                        <label for="sub_file">📎 Nộp tệp đính kèm</label>
                    </div>
                    <div class="check-row">
                        <input type="checkbox" id="sub_text" name="sub_text" value="1" <?= in_array('text', $subTypes, true) ? 'checked' : '' ?>>
                        <label for="sub_text">⌨️ Gõ bài trực tiếp trên web</label>
                    </div>
                    <div class="check-row">
                        <input type="checkbox" id="allow_late" name="allow_late" value="1" <?= $av('allow_late', 1) ? 'checked' : '' ?>>
                        <label for="allow_late">⏰ Cho phép nộp trễ (đánh dấu "nộp trễ")</label>
                    </div>
                </div>
            </div>

            <!-- AI chấm bài -->
            <div class="card mb-3 tp tp-assignment">
                <div class="card-title"><span class="emoji">🤖</span> Trợ lý AI chấm bài</div>
                <?php if (!Ai::enabled()): ?>
                    <div class="alert alert-warning"><span>💡</span><div>
                        Tính năng AI đang tắt ở cấp hệ thống.
                        <?php if (Auth::isAdmin()): ?><a href="<?= e(url('admin/ai')) ?>">Bật ngay trong Quản trị → Trợ lý AI</a>.
                        <?php else: ?>Hãy liên hệ quản trị viên để bật.<?php endif; ?>
                    </div></div>
                <?php endif; ?>
                <?php // Bài tập mới: lấy mặc định từ cấu hình hệ thống
                $aiOn   = $asg ? (bool)$av('ai_enabled') : Settings::bool('ai_enabled');
                $aiAuto = $asg ? (bool)$av('ai_auto') : Settings::bool('ai_auto_default'); ?>
                <label class="switch mb-2">
                    <input type="checkbox" name="ai_enabled" value="1" <?= $aiOn ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Cho phép AI chấm bài tập này</b>
                        <span>Giáo viên có nút "Nhờ AI chấm" ở màn hình chấm bài</span></span>
                </label>
                <label class="switch mb-2">
                    <input type="checkbox" name="ai_auto" value="1" <?= $aiAuto ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Tự động chấm ngay khi học sinh nộp</b>
                        <span>Học sinh nhận nhận xét tức thì</span></span>
                </label>
                <label class="switch mb-3">
                    <input type="checkbox" name="ai_apply" value="1" <?= $av('ai_apply') ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Lấy luôn điểm AI làm điểm chính thức</b>
                        <span>Nếu tắt, điểm AI chỉ mang tính tham khảo cho giáo viên</span></span>
                </label>
                <div class="form-group">
                    <label>Tiêu chí chấm (rubric) gửi cho AI</label>
                    <textarea name="ai_rubric" rows="6" data-autogrow placeholder="Ví dụ:
- Nội dung đúng yêu cầu đề bài: 4 điểm
- Lập luận chặt chẽ, có dẫn chứng: 3 điểm
- Trình bày rõ ràng, đúng chính tả: 2 điểm
- Sáng tạo, mở rộng: 1 điểm"><?= e($av('ai_rubric')) ?></textarea>
                </div>
            </div>

            <!-- Trắc nghiệm -->
            <div class="card mb-3 tp tp-quiz">
                <div class="card-title"><span class="emoji">❓</span> Cấu hình bài trắc nghiệm</div>
                <div class="form-group">
                    <label>Hướng dẫn làm bài</label>
                    <textarea name="intro" rows="4" data-editor data-autogrow><?= e($qv('intro')) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Thời gian làm bài (phút)</label>
                        <input type="number" name="time_limit" min="0" value="<?= (int)$qv('time_limit', 0) ?>">
                        <div class="form-hint">0 = không giới hạn</div>
                    </div>
                    <div class="form-group">
                        <label>Số lượt làm</label>
                        <input type="number" name="quiz_attempts" min="1" value="<?= (int)$qv('max_attempts', 1) ?>">
                    </div>
                    <div class="form-group">
                        <label>Ngưỡng đạt (%)</label>
                        <input type="number" name="pass_score" min="0" max="100" step="1" value="<?= (float)$qv('pass_score', 50) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cách tính điểm khi làm nhiều lần</label>
                        <select name="grade_method">
                            <?php foreach (['highest' => 'Điểm cao nhất', 'last' => 'Lần làm cuối',
                                            'first' => 'Lần làm đầu', 'average' => 'Trung bình các lần'] as $k => $lb): ?>
                                <option value="<?= $k ?>" <?= $qv('grade_method', 'highest') === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cho xem đáp án</label>
                        <select name="show_result">
                            <option value="immediate" <?= $qv('show_result', 'immediate') === 'immediate' ? 'selected' : '' ?>>Ngay sau khi nộp</option>
                            <option value="after_close" <?= $qv('show_result') === 'after_close' ? 'selected' : '' ?>>Sau khi đóng bài</option>
                            <option value="never" <?= $qv('show_result') === 'never' ? 'selected' : '' ?>>Không cho xem</option>
                        </select>
                    </div>
                </div>
                <div class="check-row">
                    <input type="checkbox" id="shuffle_q" name="shuffle_q" value="1" <?= $qv('shuffle_q') ? 'checked' : '' ?>>
                    <label for="shuffle_q">🔀 Trộn thứ tự câu hỏi</label>
                </div>
                <div class="check-row">
                    <input type="checkbox" id="shuffle_a" name="shuffle_a" value="1" <?= $qv('shuffle_a') ? 'checked' : '' ?>>
                    <label for="shuffle_a">🔀 Trộn thứ tự phương án trả lời</label>
                </div>
                <div class="check-row">
                    <input type="checkbox" id="quiz_ai" name="quiz_ai" value="1" <?= $qv('ai_enabled') ? 'checked' : '' ?>>
                    <label for="quiz_ai">🤖 Dùng AI chấm các câu tự luận</label>
                </div>
                <?php if ($item): ?>
                    <a class="btn btn-primary mt-2" href="<?= e(url('teach/questions', ['id' => $item['id']])) ?>">🧩 Soạn câu hỏi →</a>
                <?php else: ?>
                    <div class="alert alert-info mt-2"><span>💡</span><div>Sau khi lưu, bạn sẽ được chuyển tới màn hình soạn câu hỏi.</div></div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">⚙️</span> Hiển thị &amp; điểm</div>
                <div class="form-group">
                    <label>Thuộc chương</label>
                    <select name="section_id">
                        <option value="">— Nội dung chung —</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"
                                <?= (int)$v('section_id', inp_int('section')) === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= e($s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="switch mb-3">
                    <input type="checkbox" name="visible" value="1" <?= $v('visible', 1) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Hiển thị với học sinh</b><span>Tắt để soạn nháp</span></span>
                </label>
                <div class="form-row">
                    <div class="form-group">
                        <label>Mở lúc</label>
                        <input type="datetime-local" name="open_at" value="<?= e($dt($v('open_at'))) ?>">
                    </div>
                    <div class="form-group">
                        <label>Đóng lúc</label>
                        <input type="datetime-local" name="close_at" value="<?= e($dt($v('close_at'))) ?>">
                    </div>
                </div>
                <div class="check-row tp tp-page tp-file tp-video tp-link tp-forum tp-scorm">
                    <input type="checkbox" id="graded" name="graded" value="1"
                        <?= ($item ? $v('graded') : ($type === 'scorm')) ? 'checked' : '' ?>>
                    <label for="graded">🏅 Tính điểm vào sổ điểm</label>
                </div>
                <div class="form-hint tp tp-scorm" style="margin:-6px 0 12px">
                    Bật khi dùng gói SCORM làm <b>bài tập chấm điểm</b>; tắt nếu chỉ là học liệu tham khảo.
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm tối đa</label>
                        <input type="number" name="max_points" step="0.5" min="0" value="<?= (float)$v('max_points', 10) ?>">
                    </div>
                    <div class="form-group">
                        <label>Hệ số</label>
                        <input type="number" name="weight" step="0.5" min="0" value="<?= (float)$v('weight', 1) ?>">
                    </div>
                </div>
            </div>

            <?php if ($item && $files): ?>
                <div class="card mb-3">
                    <div class="card-title"><span class="emoji">📎</span> Tệp đính kèm hiện có</div>
                    <?php foreach ($files as $f): list($ic, $col) = file_icon($f['ext']); ?>
                        <div class="file-item mb-1">
                            <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                            <div class="flex-1"><div class="file-name"><?= e(str_limit($f['name'], 26)) ?></div>
                                <div class="file-meta"><?= human_size($f['size']) ?></div></div>
                            <form method="post" action="<?= e(url('teach/item/file-delete')) ?>" style="display:inline"
                                  onsubmit="return confirm('Xoá tệp này?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="link" value="<?= (int)$f['link_id'] ?>">
                                <button class="btn btn-ghost btn-sm" style="color:var(--danger)" type="submit">🗑️</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-title"><span class="emoji">📥</span> Thêm tệp đính kèm</div>
                <div class="dropzone" style="padding:18px">
                    <input type="file" name="files[]" multiple style="display:none">
                    <span class="dz-emoji">📚</span>
                    <div class="small">Chọn một hoặc nhiều tệp tài liệu kèm theo</div>
                </div>
                <div class="file-preview"></div>
            </div>

            <div class="card">
                <button class="btn btn-primary btn-block btn-lg" type="submit">💾 Lưu nội dung</button>
                <button class="btn btn-ghost btn-block mt-2" type="submit" name="save_and_new" value="1">💾 Lưu &amp; thêm mục mới</button>
                <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('teach/content', ['id' => $course['id']])) ?>">← Quay lại</a>
            </div>
        </div>
    </div>
</form>

<script>
function switchType(t) {
    LMS.$$('.tp').forEach(function (el) { el.style.display = 'none'; });
    LMS.$$('.tp-' + t).forEach(function (el) { el.style.display = ''; });
    LMS.$$('input[name=type]').forEach(function (r) {
        r.closest('.answer-option').classList.toggle('selected', r.value === t);
    });
}
document.addEventListener('DOMContentLoaded', function () { switchType(<?= json_encode($type) ?>); });
</script>
