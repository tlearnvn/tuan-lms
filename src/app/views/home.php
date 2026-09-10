<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<section class="hero">
    <div class="hero-inner">
        <div>
            <span class="hero-badge">✨ Nền tảng học tập trực tuyến</span>
            <h1 class="mt-2"><?= e(Settings::get('site_name')) ?></h1>
            <p><?= e(Settings::get('site_tagline')) ?></p>
            <div class="btn-group mt-3">
                <?php if (Auth::check()): ?>
                    <a class="btn btn-lg" style="background:#fff;color:var(--primary)" href="<?= e(url('dashboard')) ?>">🚀 Vào lớp học của tôi</a>
                <?php else: ?>
                    <?php if (Settings::bool('allow_student_register') || Settings::bool('allow_teacher_register')): ?>
                        <a class="btn btn-lg" style="background:#fff;color:var(--primary)" href="<?= e(url('auth/register')) ?>">🎒 Đăng ký học ngay</a>
                    <?php endif; ?>
                    <a class="btn btn-lg btn-ghost" style="border-color:rgba(255,255,255,.6);color:#fff" href="<?= e(url('auth/login')) ?>">Đăng nhập</a>
                <?php endif; ?>
                <a class="btn btn-lg btn-ghost" style="border-color:rgba(255,255,255,.6);color:#fff" href="<?= e(url('catalog')) ?>">📚 Xem khoá học</a>
            </div>
            <div class="hero-badges">
                <span class="hero-badge">🧩 Hỗ trợ SCORM 1.2 &amp; 2004</span>
                <span class="hero-badge">🤖 AI chấm bài tự động</span>
                <span class="hero-badge">📊 Xuất bảng điểm Excel &amp; PDF</span>
            </div>
        </div>
        <div class="hero-art">
            <?php $heroId = (int)Settings::get('hero_image_id', 0); ?>
            <img src="<?= e($heroId ? media_url($heroId) : asset('img/hero-study.svg')) ?>" alt="Học sinh học trực tuyến">
        </div>
    </div>
</section>

<?php if (Settings::bool('landing_show_stats', true)): ?>
<div class="grid grid-4 mb-4">
    <?php
    $cards = [
        ['🎓', 'Khoá học đang mở', $stats['courses'], '#6C5CE7', 'rgba(108,92,231,.12)'],
        ['🧑‍🎓', 'Học viên', $stats['students'], '#00B894', 'rgba(0,184,148,.12)'],
        ['👩‍🏫', 'Giáo viên', $stats['teachers'], '#0984E3', 'rgba(9,132,227,.12)'],
        ['🧩', 'Học liệu &amp; bài tập', $stats['items'], '#E84393', 'rgba(232,67,147,.12)'],
    ];
    foreach ($cards as $c): ?>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:<?= $c[4] ?>"><?= $c[0] ?></div>
            <div>
                <div class="stat-value" style="color:<?= $c[3] ?>"><?= num($c[2]) ?></div>
                <div class="stat-label"><?= $c[1] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-head">
    <h2>Mọi thứ một lớp học cần 🎈</h2>
    <p>Hệ thống được xây dựng cho thầy cô và học sinh Việt Nam — dễ dùng, đầy đủ và vui mắt.</p>
</div>

<div class="feature-grid">
    <?php
    $features = [
        ['📚', 'Kho học liệu đa dạng', 'Tải lên tài liệu Word, PDF, PowerPoint, video, âm thanh, hình ảnh — tất cả lưu an toàn trong cơ sở dữ liệu.', 'rgba(108,92,231,.12)'],
        ['🧩', 'Chuẩn SCORM', 'Nhập gói SCORM 1.2 / 2004, ghi nhận tiến độ và điểm số của từng học sinh theo chuẩn quốc tế.', 'rgba(232,67,147,.12)'],
        ['📝', 'Bài tập &amp; nộp bài', 'Giao bài tập theo hạn, cho nộp tệp hoặc gõ trực tiếp, cho phép nộp trễ và nhiều lần nộp.', 'rgba(214,48,49,.12)'],
        ['🤖', 'AI chấm bài', 'Kết nối API AI tuỳ chọn để chấm điểm, nhận xét chi tiết theo tiêu chí do thầy cô đặt ra.', 'rgba(9,132,227,.12)'],
        ['❓', 'Trắc nghiệm thông minh', 'Ngân hàng câu hỏi nhiều dạng, giới hạn thời gian, trộn đề và tự động chấm điểm.', 'rgba(243,156,18,.12)'],
        ['📊', 'Sổ điểm &amp; báo cáo', 'Theo dõi tiến độ, thống kê trực quan, xuất bảng điểm ra Excel, PDF hoặc CSV chỉ với một cú nhấp.', 'rgba(0,184,148,.12)'],
        ['💬', 'Thảo luận &amp; thông báo', 'Diễn đàn theo lớp, thông báo tới từng học sinh, nhắc hạn nộp bài đúng lúc.', 'rgba(0,206,201,.12)'],
        ['🎨', 'Tuỳ biến thương hiệu', 'Thay tên web, tên đơn vị, logo, màu sắc và dòng bản quyền chân trang theo đúng nhận diện của trường.', 'rgba(162,155,254,.16)'],
    ];
    foreach ($features as $f): ?>
        <div class="feature fade-up">
            <div class="feature-icon" style="background:<?= $f[3] ?>"><?= $f[0] ?></div>
            <h3><?= $f[1] ?></h3>
            <p><?= $f[2] ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($categories): ?>
<div class="section-head">
    <h2>Danh mục môn học 🗂️</h2>
    <p>Chọn lĩnh vực em quan tâm để bắt đầu hành trình học tập.</p>
</div>
<div class="flex flex-wrap gap-1" style="justify-content:center">
    <?php foreach ($categories as $cat): ?>
        <a class="btn btn-ghost" href="<?= e(url('catalog', ['cat' => $cat['id']])) ?>">
            <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
            <span class="chip chip-purple"><?= num($cat['n']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (Settings::bool('landing_show_courses', true) && $courses): ?>
<div class="section-head">
    <h2>Khoá học nổi bật 🌟</h2>
    <p>Những lớp học đang được nhiều bạn theo học nhất.</p>
</div>
<div class="grid grid-auto">
    <?php foreach ($courses as $c) partial('course_card', ['c' => $c]); ?>
</div>
<div class="center mt-3">
    <a class="btn btn-primary" href="<?= e(url('catalog')) ?>">Xem tất cả khoá học →</a>
</div>
<?php endif; ?>

<section class="card mt-4" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;border:0">
    <div class="flex-between flex-wrap">
        <div>
            <h2 style="color:#fff;margin-bottom:6px">Sẵn sàng bắt đầu chưa? 🎉</h2>
            <p style="margin:0;opacity:.94">Tạo tài khoản miễn phí và tham gia lớp học chỉ trong một phút.</p>
        </div>
        <div class="btn-group">
            <?php if (!Auth::check() && (Settings::bool('allow_student_register') || Settings::bool('allow_teacher_register'))): ?>
                <a class="btn btn-lg" style="background:#fff;color:var(--primary)" href="<?= e(url('auth/register')) ?>">Đăng ký ngay</a>
            <?php else: ?>
                <a class="btn btn-lg" style="background:#fff;color:var(--primary)" href="<?= e(url(Auth::check() ? 'dashboard' : 'auth/login')) ?>">Vào học</a>
            <?php endif; ?>
        </div>
    </div>
</section>
