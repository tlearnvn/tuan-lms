<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php $c = $course ?: []; $v = function ($k, $d = '') use ($c) { return isset($c[$k]) && $c[$k] !== null ? $c[$k] : $d; }; ?>

<?= breadcrumbs([
    ['label' => 'Khoá học giảng dạy', 'url' => url('teach/courses')],
    ['label' => $course ? $course['title'] : 'Tạo mới'],
]) ?>

<div class="page-head">
    <h1><?= $course ? '⚙️ Cài đặt khoá học' : '✨ Tạo khoá học mới' ?></h1>
    <div class="sub">Điền thông tin để lớp học của bạn thật hấp dẫn với học sinh.</div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><span>⚠️</span><div><b>Kiểm tra lại:</b>
        <ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" data-warn-unsaved>
    <?= csrf_field() ?>
    <?php if ($course): ?><input type="hidden" name="id" value="<?= (int)$course['id'] ?>"><?php endif; ?>

    <div class="grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
        <div>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">📘</span> Thông tin chung</div>
                <div class="form-group">
                    <label>Tên khoá học <span class="req">*</span></label>
                    <input type="text" name="title" value="<?= e($v('title')) ?>" required placeholder="vd: Toán 10 – Đại số cơ bản">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Mã khoá học</label>
                        <input type="text" name="code" value="<?= e($v('code')) ?>" placeholder="Để trống sẽ tự sinh">
                    </div>
                    <div class="form-group">
                        <label>Danh mục</label>
                        <select name="category_id">
                            <option value="">— Không chọn —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= (int)$v('category_id') === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['icon'] . ' ' . $cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Mô tả ngắn</label>
                    <textarea name="summary" rows="2" placeholder="Một hai câu giới thiệu ngắn gọn về khoá học"><?= e($v('summary')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Giới thiệu chi tiết</label>
                    <textarea name="description" rows="8" data-editor data-autogrow
                        placeholder="Mục tiêu, đối tượng, yêu cầu đầu vào, cách đánh giá…&#10;Có thể dùng thẻ HTML cơ bản như <b>, <ul>, <li>…"><?= e($v('description')) ?></textarea>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🎫</span> Ghi danh</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hình thức ghi danh</label>
                        <select name="enroll_mode" id="enroll_mode" onchange="document.getElementById('key-row').style.display = this.value === 'key' ? '' : 'none'">
                            <?php foreach ([
                                'open' => 'Tự do — ai cũng vào được',
                                'key' => 'Cần mã ghi danh',
                                'approval' => 'Cần giáo viên duyệt',
                                'manual' => 'Chỉ giáo viên thêm học viên',
                            ] as $k => $lb): ?>
                                <option value="<?= $k ?>" <?= $v('enroll_mode', 'open') === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="key-row" style="<?= $v('enroll_mode') === 'key' ? '' : 'display:none' ?>">
                        <label>Mã ghi danh</label>
                        <input type="text" name="enroll_key" value="<?= e($v('enroll_key')) ?>" placeholder="Để trống sẽ tự sinh mã">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày bắt đầu</label>
                        <input type="date" name="start_date" value="<?= e($v('start_date')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Ngày kết thúc</label>
                        <input type="date" name="end_date" value="<?= e($v('end_date')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Sĩ số tối đa</label>
                        <input type="number" name="max_students" min="0" value="<?= (int)$v('max_students', 0) ?>">
                        <div class="form-hint">0 = không giới hạn</div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🚦</span> Trạng thái</div>
                <div class="form-group">
                    <label>Trạng thái khoá học</label>
                    <select name="status">
                        <option value="draft" <?= $v('status', 'draft') === 'draft' ? 'selected' : '' ?>>📝 Bản nháp (chỉ giáo viên thấy)</option>
                        <option value="published" <?= $v('status') === 'published' ? 'selected' : '' ?>>✅ Đang mở (học sinh vào học)</option>
                        <option value="archived" <?= $v('status') === 'archived' ? 'selected' : '' ?>>📦 Lưu trữ (chỉ xem lại)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Hiển thị trong danh mục công khai</label>
                    <select name="visibility">
                        <option value="public" <?= $v('visibility', 'public') === 'public' ? 'selected' : '' ?>>🌐 Công khai</option>
                        <option value="private" <?= $v('visibility') === 'private' ? 'selected' : '' ?>>🔒 Riêng tư (không hiện ở trang danh mục)</option>
                    </select>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🎨</span> Hình ảnh &amp; màu sắc</div>
                <?php if ($course && $course['cover_id']): ?>
                    <img src="<?= e(media_url($course['cover_id'])) ?>" alt="" style="width:100%;border-radius:12px;margin-bottom:10px">
                    <div class="check-row">
                        <input type="checkbox" id="remove_cover" name="remove_cover" value="1">
                        <label for="remove_cover">Xoá ảnh bìa hiện tại</label>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Ảnh bìa</label>
                    <div class="dropzone" style="padding:18px">
                        <input type="file" name="cover" accept="image/*" style="display:none">
                        <span class="dz-emoji">🖼️</span>
                        <div class="small">Chọn ảnh bìa (khuyến nghị 1200×400)</div>
                    </div>
                    <div class="file-preview"></div>
                </div>
                <div class="form-group">
                    <label>Màu chủ đạo</label>
                    <input type="color" name="color" value="<?= e($v('color', '#6C5CE7')) ?>">
                </div>
            </div>

            <div class="card">
                <button class="btn btn-primary btn-block btn-lg" type="submit">
                    <?= $course ? '💾 Lưu thay đổi' : '🚀 Tạo khoá học' ?>
                </button>
                <?php if ($course): ?>
                    <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('teach/content', ['id' => $course['id']])) ?>">🧱 Quản lý nội dung</a>
                    <?php if (Auth::isAdmin() || (int)$course['owner_id'] === Auth::id()): ?>
                        <form method="post" action="<?= e(url('teach/course/delete')) ?>" class="mt-2"
                              onsubmit="return confirm('CẢNH BÁO: Xoá khoá học sẽ xoá toàn bộ nội dung, bài nộp và điểm số. Bạn chắc chắn chứ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                            <button class="btn btn-ghost btn-block" style="color:var(--danger)" type="submit">🗑️ Xoá khoá học</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
