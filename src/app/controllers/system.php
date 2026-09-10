<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Tiện ích hệ thống: giữ phiên đăng nhập không bị hết hạn khi học sinh đang làm bài */

function ping_index()
{
    $_SESSION['_ping'] = time();
    json_out([
        'ok'   => true,
        'user' => Auth::id(),
        'time' => date('H:i:s d/m/Y'),
    ]);
}
