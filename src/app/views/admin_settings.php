<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>⚙️ Cấu hình hệ thống</h1>
        <div class="sub">Tuỳ biến thương hiệu, đăng ký tài khoản, phiên làm việc và tải tệp.</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/ai')) ?>">🤖 Cấu hình AI</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/categories')) ?>">🗂️ Danh mục</a>
    </div>
</div>

<form method="post" enctype="multipart/form-data" data-warn-unsaved>
    <?= csrf_field() ?>

    <div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
        <!-- Thương hiệu -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">🎨</span> Thương hiệu &amp; nhận diện</div>
            <div class="form-group">
                <label>Tên website <span class="req">*</span></label>
                <input type="text" name="site_name" value="<?= e(Settings::get('site_name')) ?>" required>
            </div>
            <div class="form-group">
                <label>Khẩu hiệu (hiển thị ở trang chủ)</label>
                <input type="text" name="site_tagline" value="<?= e(Settings::get('site_tagline')) ?>">
            </div>
            <div class="form-group">
                <label>Tên đơn vị / nhà trường</label>
                <input type="text" name="org_name" value="<?= e(Settings::get('org_name')) ?>">
            </div>
            <div class="form-group">
                <label>Dòng bản quyền chân trang</label>
                <input type="text" name="copyright" value="<?= e(Settings::get('copyright')) ?>">
                <div class="form-hint">Ví dụ: © <?= date('Y') ?> Trường THPT ABC. Bảo lưu mọi quyền.</div>
            </div>
            <div class="form-group">
                <label>Ghi chú chân trang</label>
                <input type="text" name="footer_note" value="<?= e(Settings::get('footer_note')) ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Màu chủ đạo</label>
                    <input type="color" name="primary_color" value="<?= e(Settings::get('primary_color', '#6C5CE7')) ?>">
                </div>
                <div class="form-group">
                    <label>Màu nhấn</label>
                    <input type="color" name="accent_color" value="<?= e(Settings::get('accent_color', '#FF7675')) ?>">
                </div>
            </div>
        </div>

        <!-- Hình ảnh -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">🖼️</span> Logo &amp; hình ảnh</div>
            <?php
            $imgFields = [
                ['logo', 'logo_id', 'Logo (hiện ở thanh bên và chân trang)', '160px'],
                ['favicon', 'favicon_id', 'Favicon (biểu tượng trên tab trình duyệt)', '48px'],
                ['hero', 'hero_image_id', 'Ảnh minh hoạ trang chủ', '100%'],
            ];
            foreach ($imgFields as $f):
                $cur = (int)Settings::get($f[1], 0); ?>
                <div class="form-group">
                    <label><?= e($f[2]) ?></label>
                    <?php if ($cur): ?>
                        <div class="flex-center gap-2 mb-1">
                            <img src="<?= e(media_url($cur)) ?>" alt="" style="max-width:<?= $f[3] ?>;max-height:90px;object-fit:contain;background:var(--bg-soft);border-radius:10px;padding:6px">
                            <label class="small"><input type="checkbox" name="remove_<?= $f[0] ?>" value="1"> Xoá ảnh này</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="<?= $f[0] ?>" accept="image/*">
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label>Trang chủ hiển thị</label>
                <div class="check-row">
                    <input type="checkbox" id="landing_show_stats" name="landing_show_stats" value="1" <?= Settings::bool('landing_show_stats', true) ? 'checked' : '' ?>>
                    <label for="landing_show_stats">📊 Khối số liệu thống kê</label>
                </div>
                <div class="check-row">
                    <input type="checkbox" id="landing_show_courses" name="landing_show_courses" value="1" <?= Settings::bool('landing_show_courses', true) ? 'checked' : '' ?>>
                    <label for="landing_show_courses">🎓 Danh sách khoá học nổi bật</label>
                </div>
            </div>
        </div>

        <!-- Đăng ký -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">🙋</span> Đăng ký tài khoản</div>
            <label class="switch mb-3">
                <input type="checkbox" name="allow_student_register" value="1" <?= Settings::bool('allow_student_register', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Cho phép học sinh tự đăng ký</b>
                    <span>Học sinh tạo tài khoản từ trang đăng ký</span></span>
            </label>
            <label class="switch mb-3">
                <input type="checkbox" name="allow_teacher_register" value="1" <?= Settings::bool('allow_teacher_register', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Cho phép giáo viên tự đăng ký</b>
                    <span>Tắt tuỳ chọn này nếu chỉ quản trị viên được tạo tài khoản giáo viên</span></span>
            </label>
            <label class="switch mb-3">
                <input type="checkbox" name="teacher_need_approval" value="1" <?= Settings::bool('teacher_need_approval', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Giáo viên đăng ký phải chờ duyệt</b>
                    <span>Tài khoản ở trạng thái "chờ duyệt" cho tới khi quản trị viên xác nhận</span></span>
            </label>
            <label class="switch mb-3">
                <input type="checkbox" name="student_need_approval" value="1" <?= Settings::bool('student_need_approval') ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Học sinh đăng ký phải chờ duyệt</b></span>
            </label>
            <div class="form-group">
                <label>Ghi chú hiển thị ở trang đăng ký</label>
                <textarea name="register_note" rows="3" placeholder="vd: Học sinh dùng email do nhà trường cấp để đăng ký."><?= e(Settings::get('register_note')) ?></textarea>
            </div>
        </div>

        <!-- Phiên làm việc -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">⏱️</span> Phiên làm việc &amp; bảo mật</div>
            <div class="form-group">
                <label>Thời gian sống của phiên (giây)</label>
                <input type="number" name="session_lifetime" min="1800" step="600" value="<?= Settings::int('session_lifetime', 43200) ?>">
                <div class="form-hint">Hiện tại: <b><?= round(Settings::int('session_lifetime', 43200) / 3600, 1) ?> giờ</b>.
                    Đặt dài (ví dụ 43200 = 12 giờ) để học sinh làm bài lâu không bị đăng xuất.</div>
            </div>
            <label class="switch mb-3">
                <input type="checkbox" name="session_keepalive" value="1" <?= Settings::bool('session_keepalive', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Tự động giữ phiên khi đang mở trang</b>
                    <span>Trình duyệt gửi tín hiệu 5 phút/lần để phiên không hết hạn</span></span>
            </label>
            <div class="form-group">
                <label>Số ngày ghi nhớ đăng nhập</label>
                <input type="number" name="remember_days" min="1" max="365" value="<?= Settings::int('remember_days', 30) ?>">
            </div>
            <div class="form-group">
                <label>Múi giờ</label>
                <select name="timezone">
                    <?php foreach (['Asia/Ho_Chi_Minh' => 'Việt Nam (GMT+7)', 'Asia/Bangkok' => 'Bangkok (GMT+7)',
                                    'Asia/Singapore' => 'Singapore (GMT+8)', 'UTC' => 'UTC'] as $tz => $lb): ?>
                        <option value="<?= e($tz) ?>" <?= Settings::get('timezone') === $tz ? 'selected' : '' ?>><?= e($lb) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Hiện tại hệ thống đang là <b><?= date('H:i:s d/m/Y') ?></b>.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Định dạng ngày</label>
                    <input type="text" name="date_format" value="<?= e(Settings::get('date_format', 'd/m/Y')) ?>">
                </div>
                <div class="form-group">
                    <label>Định dạng ngày giờ</label>
                    <input type="text" name="datetime_format" value="<?= e(Settings::get('datetime_format', 'H:i d/m/Y')) ?>">
                </div>
            </div>
        </div>

        <!-- Tệp tin -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📦</span> Tải tệp &amp; lưu trữ</div>
            <div class="form-group">
                <label>Dung lượng tối đa mỗi tệp (MB)</label>
                <input type="number" name="max_upload_mb" min="1" value="<?= Settings::int('max_upload_mb', 64) ?>">
                <div class="form-hint">Máy chủ hiện cho phép tối đa <b><?= human_size(server_upload_limit()) ?></b>
                    (upload_max_filesize / post_max_size). Sửa trong tệp <code>.user.ini</code> hoặc PHP Selector của cPanel nếu cần lớn hơn.</div>
            </div>
            <div class="form-group">
                <label>Định dạng tệp được phép</label>
                <textarea name="allowed_ext" rows="3"><?= e(Settings::get('allowed_ext')) ?></textarea>
                <div class="form-hint">Ngăn cách bằng dấu phẩy. Để trống nghĩa là cho phép mọi định dạng.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Kích thước mảnh lưu CSDL (KB)</label>
                    <input type="number" name="chunk_size_kb" min="64" max="4096" step="64" value="<?= Settings::int('chunk_size_kb', 512) ?>">
                    <div class="form-hint">Giảm xuống nếu hosting báo lỗi "max_allowed_packet".</div>
                </div>
                <div class="form-group">
                    <label>Số dòng mỗi trang</label>
                    <input type="number" name="items_per_page" min="5" max="200" value="<?= Settings::int('items_per_page', 20) ?>">
                </div>
            </div>
        </div>

        <!-- Xem trước tệp học liệu -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">👁️</span> Xem trước tệp học liệu</div>
            <label class="switch mb-3">
                <input type="checkbox" name="preview_enabled" value="1" <?= Settings::bool('preview_enabled', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Mở tệp đính kèm ngay trên web</b>
                    <span>Word, Excel, PowerPoint, OpenDocument, PDF, ảnh, video, âm thanh, văn bản, mã nguồn và tệp nén —
                        học sinh không cần tải về mới xem được</span></span>
            </label>
            <div class="form-group">
                <label>Chỉ xem trước tệp nhỏ hơn (MB)</label>
                <input type="number" name="preview_max_mb" min="1" max="512" value="<?= (int)Settings::int('preview_max_mb', 25) ?>">
                <div class="form-hint">Áp dụng cho các định dạng cần máy chủ bóc tách nội dung (Word, Excel, PowerPoint, văn bản, tệp nén).
                    Tệp lớn hơn mức này chỉ hiện nút tải về. Ảnh, PDF, video và âm thanh không bị giới hạn vì do trình duyệt phát trực tiếp.</div>
            </div>
        </div>

        <!-- Công thức toán & soạn thảo -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">∑</span> Công thức toán &amp; trình soạn thảo</div>
            <label class="switch mb-3">
                <input type="checkbox" name="enable_math" value="1" <?= Settings::bool('enable_math', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Bật hiển thị công thức LaTeX (MathJax)</b>
                    <span>Áp dụng cho bài giảng, học liệu, đề bài, câu hỏi trắc nghiệm và thảo luận</span></span>
            </label>
            <label class="switch mb-3">
                <input type="checkbox" name="math_dollar" value="1" <?= Settings::bool('math_dollar', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Cho phép dấu <code>$...$</code> cho công thức trong dòng</b>
                    <span>Tắt nếu nội dung có nhiều ký hiệu tiền tệ. Cú pháp <code>\\( ... \\)</code> luôn hoạt động.</span></span>
            </label>
            <label class="switch mb-3">
                <input type="checkbox" name="editor_enabled" value="1" <?= Settings::bool('editor_enabled', true) ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Hiện thanh công cụ soạn thảo</b>
                    <span>Nút chèn ảnh minh hoạ, bảng, video và mẫu công thức</span></span>
            </label>
            <div class="form-group">
                <label>Địa chỉ thư viện MathJax</label>
                <input type="url" name="mathjax_url" value="<?= e(Settings::get('mathjax_url')) ?>">
                <div class="form-hint">Mặc định dùng bản MathJax <b>cài kèm trong mã nguồn</b> nên chạy được cả khi không có Internet.
                    Muốn dùng bản đầy đủ trên CDN, điền:
                    <code>https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js</code></div>
            </div>
            <div class="alert alert-info"><span>💡</span><div>
                Ví dụ gõ trong bài giảng: <code>\\(x^2+y^2=z^2\\)</code> hoặc <code>$$\\int_a^b f(x)dx$$</code>.
                Thanh công cụ có sẵn hơn 20 mẫu công thức Toán – Lí – Hoá.
            </div></div>
        </div>

        <!-- Liên hệ & bảo trì -->
        <div class="card mb-3">
            <div class="card-title"><span class="emoji">📞</span> Liên hệ &amp; bảo trì</div>
            <div class="form-group">
                <label>Email liên hệ</label>
                <input type="email" name="contact_email" value="<?= e(Settings::get('contact_email')) ?>">
            </div>
            <div class="form-group">
                <label>Điện thoại</label>
                <input type="text" name="contact_phone" value="<?= e(Settings::get('contact_phone')) ?>">
            </div>
            <div class="form-group">
                <label>Địa chỉ</label>
                <input type="text" name="contact_address" value="<?= e(Settings::get('contact_address')) ?>">
            </div>
            <label class="switch mb-2">
                <input type="checkbox" name="maintenance" value="1" <?= Settings::bool('maintenance') ? 'checked' : '' ?>>
                <span class="track"></span>
                <span class="switch-label"><b>Bật chế độ bảo trì</b>
                    <span>Chỉ quản trị viên truy cập được hệ thống</span></span>
            </label>
            <div class="form-group">
                <label>Thông điệp bảo trì</label>
                <textarea name="maintenance_message" rows="2"><?= e(Settings::get('maintenance_message')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <button class="btn btn-primary btn-lg" type="submit">💾 Lưu toàn bộ cấu hình</button>
    </div>
</form>
