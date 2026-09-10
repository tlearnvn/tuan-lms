<?php
/**
 * Mẫu tệp cấu hình.
 * Bình thường bạn KHÔNG cần sửa tệp này – hãy mở install.php trên trình duyệt
 * để trình cài đặt tự tạo tệp config.php.
 *
 * Nếu hosting không cho phép ghi tệp, hãy sao chép tệp này thành config.php
 * rồi điền thông tin cơ sở dữ liệu.
 */

return [
    'db' => [
        'host'   => 'localhost',   // Đa số hosting dùng localhost
        'port'   => 3306,
        'name'   => 'ten_co_so_du_lieu',
        'user'   => 'ten_nguoi_dung',
        'pass'   => 'mat_khau',
        'prefix' => 'lms_',        // Tiền tố bảng
        // 'socket' => '/var/lib/mysql/mysql.sock', // chỉ dùng khi hosting yêu cầu
    ],

    // Bật true khi cần xem chi tiết lỗi lúc phát triển
    'debug' => false,
];
