<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Tải ảnh minh hoạ dùng trong bài giảng, học liệu, bài tập và thảo luận */

function upload_image()
{
    Auth::requireLogin();
    csrf_verify();

    if (empty($_FILES['image']['name'])) {
        json_out(['ok' => false, 'error' => 'Chưa chọn tệp ảnh.'], 400);
    }
    $ext = ext_of($_FILES['image']['name']);
    if (!is_image_ext($ext) || $ext === 'svg') {
        json_out(['ok' => false, 'error' => 'Chỉ chấp nhận ảnh JPG, PNG, GIF, WEBP hoặc BMP.'], 400);
    }
    $maxMb = min(20, max(1, Settings::int('max_upload_mb', 64)));
    if ((int)$_FILES['image']['size'] > $maxMb * 1024 * 1024) {
        json_out(['ok' => false, 'error' => 'Ảnh vượt quá ' . $maxMb . ' MB.'], 400);
    }

    // Ảnh minh hoạ hiển thị cho mọi học sinh trong bài giảng nên để chế độ "auth"
    list($fid, $err) = Storage::saveUpload($_FILES['image'], 'auth');
    if ($err) json_out(['ok' => false, 'error' => $err], 400);

    Log::write('upload_image', 'file', $fid, $_FILES['image']['name']);
    json_out([
        'ok'   => true,
        'id'   => $fid,
        'url'  => media_url($fid),
        'name' => $_FILES['image']['name'],
    ]);
}
