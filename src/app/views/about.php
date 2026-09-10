<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="card" style="max-width:900px;margin:0 auto">
    <div class="center mb-3">
        <img src="<?= e(asset('img/teacher.svg')) ?>" alt="" style="max-width:280px">
        <h1><?= e(Settings::get('site_name')) ?></h1>
        <p class="muted"><?= e(Settings::get('site_tagline')) ?></p>
    </div>

    <h3>🏫 Đơn vị chủ quản</h3>
    <p><?= e(Settings::get('org_name')) ?></p>

    <h3>🎯 Chúng tôi làm gì</h3>
    <p>Hệ thống quản lý học tập (LMS) này giúp nhà trường tổ chức lớp học trực tuyến trọn vẹn:
       xây dựng khoá học, tải học liệu, giao và chấm bài tập, tổ chức kiểm tra trắc nghiệm,
       nhập gói SCORM, theo dõi tiến độ và xuất bảng điểm.</p>

    <h3>✨ Điểm nổi bật</h3>
    <ul>
        <li>Toàn bộ dữ liệu, kể cả tệp tin, được lưu an toàn trong cơ sở dữ liệu MySQL.</li>
        <li>Hỗ trợ học liệu và bài tập theo chuẩn SCORM 1.2 / SCORM 2004.</li>
        <li>Trợ lý AI chấm bài với API, model, token và timeout do quản trị viên tuỳ chỉnh.</li>
        <li>Xuất bảng điểm ra Excel (.xlsx), PDF và CSV — tiếng Việt có dấu đầy đủ.</li>
        <li>Thời gian hệ thống theo giờ Việt Nam (GMT+7); phiên đăng nhập dài để học sinh làm bài không bị gián đoạn.</li>
    </ul>

    <?php if (Settings::get('contact_email') || Settings::get('contact_phone') || Settings::get('contact_address')): ?>
        <h3>📞 Liên hệ</h3>
        <ul>
            <?php if (Settings::get('contact_address')): ?><li>Địa chỉ: <?= e(Settings::get('contact_address')) ?></li><?php endif; ?>
            <?php if (Settings::get('contact_phone')): ?><li>Điện thoại: <?= e(Settings::get('contact_phone')) ?></li><?php endif; ?>
            <?php if (Settings::get('contact_email')): ?><li>Email: <?= e(Settings::get('contact_email')) ?></li><?php endif; ?>
        </ul>
    <?php endif; ?>
</div>
