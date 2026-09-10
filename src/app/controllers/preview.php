<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Xem trước tệp học liệu — trả HTML cho hộp thoại (AJAX) hoặc mở thành trang riêng */

function preview_file()
{
    $id = inp_int('f');
    $file = $id ? Storage::meta($id) : null;

    if (!$file) {
        if (is_ajax()) json_out(['ok' => false, 'error' => 'Không tìm thấy tệp.'], 404);
        render_404();
        return;
    }
    if (!Storage::canAccess($file)) {
        if (is_ajax()) json_out(['ok' => false, 'error' => 'Bạn không có quyền xem tệp này.'], 403);
        render_denied('Bạn không có quyền xem tệp này.');
        return;
    }

    $ext  = strtolower((string)$file['ext']);
    $kind = Preview::kind($ext);
    $html = Preview::render($file, ['height' => is_ajax() ? '68vh' : '76vh']);

    if (is_ajax()) {
        json_out([
            'ok'       => true,
            'id'       => (int)$file['id'],
            'name'     => $file['name'],
            'ext'      => strtoupper($ext),
            'size'     => human_size($file['size']),
            'kind'     => $kind,
            'label'    => Preview::kindLabel($kind),
            'html'     => $html,
            'download' => media_url($file['id'], true),
            'open'     => media_url($file['id']),
        ]);
    }

    // Lưu ý: view() dùng biến $file nội bộ nên đặt tên khác cho dữ liệu truyền vào
    view('preview_file', [
        'title' => $file['name'],
        'doc'   => $file,
        'kind'  => $kind,
        'html'  => $html,
    ], Auth::check() ? 'app' : 'public');
}
