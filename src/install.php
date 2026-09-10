<?php
/**
 * Trình cài đặt hệ thống LMS.
 * Chỉ cần tải toàn bộ mã nguồn lên hosting rồi mở địa chỉ .../install.php trên trình duyệt.
 */
define('LMS_ENTRY', true);
define('LMS_INSTALLING', true);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(E_ALL);
@ini_set('display_errors', '1');
@set_time_limit(0);

require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/schema.php';

$configFile = __DIR__ . '/config.php';
$alreadyInstalled = file_exists($configFile);

// ---------------------------------------------------------------- kiểm tra môi trường
function req_checks()
{
    $checks = [];
    $checks[] = ['PHP >= 7.4', PHP_VERSION_ID >= 70400, 'Đang dùng PHP ' . PHP_VERSION, true];
    $checks[] = ['Phần mở rộng PDO MySQL', extension_loaded('pdo_mysql'), 'Bắt buộc để kết nối cơ sở dữ liệu', true];
    $checks[] = ['Phần mở rộng mbstring', extension_loaded('mbstring'), 'Xử lý tiếng Việt có dấu', true];
    $checks[] = ['Phần mở rộng json', extension_loaded('json'), 'Xử lý dữ liệu JSON', true];
    $checks[] = ['Phần mở rộng zlib', function_exists('gzdeflate'), 'Nén tệp Excel/PDF và giải nén SCORM', true];
    $checks[] = ['Ghi được tệp config.php', is_writable(__DIR__), 'Thư mục cài đặt cần quyền ghi (755/775)', true];
    $checks[] = ['Phần mở rộng cURL', function_exists('curl_init'), 'Cần cho tính năng AI chấm bài', false];
    $checks[] = ['Thư viện GD', extension_loaded('gd'), 'Chèn logo vào tệp PDF xuất ra', false];
    $checks[] = ['Phần mở rộng fileinfo', extension_loaded('fileinfo'), 'Nhận diện đúng loại tệp tải lên', false];
    $checks[] = ['Kho font PDF', file_exists(__DIR__ . '/assets/fonts/DejaVuSans.ttf'), 'assets/fonts/DejaVuSans.ttf – xuất PDF tiếng Việt', false];
    return $checks;
}

$step = isset($_POST['step']) ? (int)$_POST['step'] : (isset($_GET['step']) ? (int)$_GET['step'] : 1);
$errors = [];
$done = false;

$form = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'db_prefix' => 'lms_',
    'site_name' => 'Trường học số', 'org_name' => 'Trung tâm Giáo dục & Đào tạo',
    'admin_name' => 'Quản trị viên', 'admin_user' => 'admin', 'admin_email' => '', 'admin_pass' => '', 'admin_pass2' => '',
];
foreach ($form as $k => $v) if (isset($_POST[$k])) $form[$k] = trim((string)$_POST[$k]);

if ($step === 3 && !$alreadyInstalled) {
    // -------------------------------------------------- kiểm tra dữ liệu nhập
    if ($form['db_name'] === '') $errors[] = 'Chưa nhập tên cơ sở dữ liệu.';
    if ($form['db_user'] === '') $errors[] = 'Chưa nhập tên người dùng CSDL.';
    if (!preg_match('/^[a-zA-Z0-9_]{0,20}$/', $form['db_prefix'])) $errors[] = 'Tiền tố bảng chỉ gồm chữ, số và dấu gạch dưới.';
    if ($form['site_name'] === '') $errors[] = 'Chưa nhập tên website.';
    if (!preg_match('/^[a-zA-Z0-9._]{4,32}$/', $form['admin_user'])) $errors[] = 'Tên đăng nhập quản trị từ 4–32 ký tự (chữ, số, dấu chấm, gạch dưới).';
    if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email quản trị chưa hợp lệ.';
    if (mb_strlen($form['admin_pass']) < 6) $errors[] = 'Mật khẩu quản trị phải từ 6 ký tự trở lên.';
    if ($form['admin_pass'] !== $form['admin_pass2']) $errors[] = 'Hai lần nhập mật khẩu chưa khớp.';

    if (!$errors) {
        try {
            DB::connect([
                'host' => $form['db_host'], 'port' => $form['db_port'], 'name' => $form['db_name'],
                'user' => $form['db_user'], 'pass' => $form['db_pass'], 'prefix' => $form['db_prefix'],
            ]);
        } catch (Exception $e) {
            $errors[] = 'Không kết nối được cơ sở dữ liệu: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            DB::migrate();

            // Ghi cấu hình mặc định
            foreach (lms_default_settings() as $k => $v) {
                DB::q('INSERT IGNORE INTO {P}settings (k, v) VALUES (:k, :v)', ['k' => $k, 'v' => (string)$v]);
            }
            DB::q('UPDATE {P}settings SET v = :v WHERE k = "site_name"', ['v' => $form['site_name']]);
            DB::q('UPDATE {P}settings SET v = :v WHERE k = "org_name"', ['v' => $form['org_name']]);
            DB::q('UPDATE {P}settings SET v = :v WHERE k = "copyright"', ['v' => '© ' . date('Y') . ' ' . $form['site_name'] . '. Bảo lưu mọi quyền.']);

            // Tài khoản quản trị
            $exists = DB::val('SELECT id FROM {P}users WHERE username = :u OR email = :e',
                              ['u' => $form['admin_user'], 'e' => $form['admin_email']]);
            if ($exists) {
                DB::update('users', [
                    'password'   => password_hash($form['admin_pass'], PASSWORD_DEFAULT),
                    'full_name'  => $form['admin_name'],
                    'role'       => 'admin',
                    'status'     => 'active',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $exists]);
            } else {
                DB::insert('users', [
                    'username'   => $form['admin_user'],
                    'email'      => $form['admin_email'],
                    'password'   => password_hash($form['admin_pass'], PASSWORD_DEFAULT),
                    'full_name'  => $form['admin_name'],
                    'role'       => 'admin',
                    'status'     => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Danh mục mẫu
            if ((int)DB::val('SELECT COUNT(*) FROM {P}categories', [], 0) === 0) {
                $cats = [
                    ['Toán học', '#0984E3', '➗', 1], ['Ngữ văn', '#E84393', '📖', 2],
                    ['Tiếng Anh', '#6C5CE7', '🌏', 3], ['Khoa học tự nhiên', '#00B894', '🔬', 4],
                    ['Lịch sử & Địa lí', '#E17055', '🏛️', 5], ['Tin học', '#00CEC9', '💻', 6],
                    ['Kỹ năng sống', '#FDCB6E', '🌱', 7], ['Bồi dưỡng giáo viên', '#A55EEA', '🎓', 8],
                ];
                foreach ($cats as $c) {
                    DB::insert('categories', ['name' => $c[0], 'color' => $c[1], 'icon' => $c[2], 'position' => $c[3]]);
                }
            }

            // Ghi tệp cấu hình
            $cfg = "<?php\n"
                . "/**\n * Tệp cấu hình hệ thống LMS – được tạo tự động bởi install.php\n"
                . " * Ngày tạo: " . date('d/m/Y H:i') . " (giờ Việt Nam)\n */\n\n"
                . "return [\n"
                . "    'db' => [\n"
                . "        'host'   => " . var_export($form['db_host'], true) . ",\n"
                . "        'port'   => " . var_export((int)$form['db_port'], true) . ",\n"
                . "        'name'   => " . var_export($form['db_name'], true) . ",\n"
                . "        'user'   => " . var_export($form['db_user'], true) . ",\n"
                . "        'pass'   => " . var_export($form['db_pass'], true) . ",\n"
                . "        'prefix' => " . var_export($form['db_prefix'], true) . ",\n"
                . "    ],\n"
                . "    // Đặt true khi cần xem chi tiết lỗi trong lúc phát triển\n"
                . "    'debug' => false,\n"
                . "    'installed_at' => " . var_export(date('Y-m-d H:i:s'), true) . ",\n"
                . "];\n";

            if (@file_put_contents($configFile, $cfg) === false) {
                $errors[] = 'Không ghi được tệp config.php. Hãy cấp quyền ghi (chmod 755) cho thư mục cài đặt, '
                    . 'hoặc tự tạo tệp config.php với nội dung hiển thị bên dưới.';
                $manualConfig = $cfg;
            } else {
                @chmod($configFile, 0644);
                $done = true;
            }
        } catch (Exception $e) {
            $errors[] = 'Lỗi khi tạo bảng dữ liệu: ' . $e->getMessage();
        }
    }
    if ($errors) $step = 2;
}

$checks = req_checks();
$blocking = false;
foreach ($checks as $c) if ($c[3] && !$c[1]) $blocking = true;
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cài đặt hệ thống LMS</title>
<link rel="icon" href="assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<style>
    body { background: linear-gradient(160deg,#EEF0FB,#E6E9FA 60%,#F7F2FF); min-height: 100vh; }
    .wrap { max-width: 820px; margin: 0 auto; padding: 34px 18px 70px; }
    .install-head { text-align: center; margin-bottom: 26px; }
    .install-head .logo { width: 74px; height: 74px; border-radius: 22px; margin: 0 auto 14px;
        background: linear-gradient(135deg,#6C5CE7,#a29bfe); display: grid; place-items: center; font-size: 38px;
        box-shadow: 0 14px 30px rgba(108,92,231,.32); }
    .steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 24px; flex-wrap: wrap; }
    .stepbox { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 999px;
        background: #fff; border: 1px solid var(--border); font-size: .85rem; font-weight: 600; color: var(--text-mute); }
    .stepbox.active { background: linear-gradient(135deg,#6C5CE7,#a29bfe); color: #fff; border-color: transparent; }
    .stepbox.done { background: #E3F9F1; color: #05986F; border-color: #B4EDDA; }
    .check-list { list-style: none; padding: 0; margin: 0; }
    .check-list li { display: flex; gap: 12px; align-items: flex-start; padding: 11px 0; border-bottom: 1px solid var(--border); }
    .check-list li:last-child { border-bottom: 0; }
    .ck { width: 26px; height: 26px; border-radius: 9px; display: grid; place-items: center; font-size: .9rem; flex-shrink: 0; }
    .ck.ok { background: #E3F9F1; color: #05986F; }
    .ck.bad { background: #FDECEA; color: #D63031; }
    .ck.warn { background: #FEF7DE; color: #A57B04; }
</style>
</head>
<body>
<div class="wrap">
    <div class="install-head">
        <div class="logo">🎓</div>
        <h1>Cài đặt hệ thống LMS</h1>
        <p class="muted">Chỉ 3 bước là lớp học trực tuyến của bạn sẵn sàng hoạt động.</p>
    </div>

    <?php if ($alreadyInstalled && !$done): ?>
        <div class="card center">
            <div style="font-size:54px">✅</div>
            <h2>Hệ thống đã được cài đặt</h2>
            <p class="muted">Tệp <code>config.php</code> đã tồn tại. Vì lý do an toàn, trình cài đặt sẽ không chạy lại.</p>
            <p class="small muted">Muốn cài lại từ đầu? Hãy xoá tệp <code>config.php</code> trên hosting rồi mở lại trang này.</p>
            <div class="btn-group mt-2" style="justify-content:center">
                <a class="btn btn-primary" href="index.php">Vào hệ thống →</a>
            </div>
        </div>

    <?php elseif ($done): ?>
        <div class="card center pop-in">
            <div style="font-size:60px">🎉</div>
            <h2>Cài đặt thành công!</h2>
            <p class="muted">Hệ thống <b><?= e($form['site_name']) ?></b> đã sẵn sàng.</p>
            <div class="alert alert-warning" style="text-align:left">
                <span>🔐</span>
                <div><b>Việc cần làm ngay:</b> hãy xoá (hoặc đổi tên) tệp <code>install.php</code> trên hosting
                    để không ai cài lại hệ thống của bạn.</div>
            </div>
            <div class="card" style="background:var(--bg-soft);text-align:left">
                <b>Thông tin đăng nhập quản trị</b>
                <div class="small mt-1">Tài khoản: <b><?= e($form['admin_user']) ?></b></div>
                <div class="small">Email: <b><?= e($form['admin_email']) ?></b></div>
                <div class="small">Mật khẩu: mật khẩu bạn vừa đặt</div>
            </div>
            <div class="btn-group mt-3" style="justify-content:center">
                <a class="btn btn-primary btn-lg" href="index.php?r=auth/login">Đăng nhập ngay →</a>
                <a class="btn btn-ghost" href="index.php">Xem trang chủ</a>
            </div>
        </div>

    <?php else: ?>
        <div class="steps">
            <div class="stepbox <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>"><span>1</span> Kiểm tra máy chủ</div>
            <div class="stepbox <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>"><span>2</span> Cơ sở dữ liệu &amp; quản trị</div>
            <div class="stepbox <?= $step >= 3 ? 'active' : '' ?>"><span>3</span> Hoàn tất</div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><b>Vui lòng kiểm tra lại:</b>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php if (!empty($manualConfig)): ?>
                <div class="card mb-3">
                    <b>Nội dung tệp <code>config.php</code> cần tạo thủ công:</b>
                    <pre style="background:#1E2140;color:#EAECFB;padding:14px;border-radius:12px;overflow:auto;font-size:.8rem"><?= e($manualConfig) ?></pre>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="card">
                <div class="card-title"><span class="emoji">🩺</span> Kiểm tra môi trường máy chủ</div>
                <ul class="check-list">
                    <?php foreach ($checks as $c): ?>
                        <li>
                            <span class="ck <?= $c[1] ? 'ok' : ($c[3] ? 'bad' : 'warn') ?>"><?= $c[1] ? '✓' : ($c[3] ? '✕' : '!') ?></span>
                            <div>
                                <b><?= e($c[0]) ?></b><?= $c[3] ? '' : ' <span class="chip chip-gray">Tuỳ chọn</span>' ?>
                                <div class="small muted"><?= e($c[2]) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($blocking): ?>
                    <div class="alert alert-danger mt-3"><span>⚠️</span><div>
                        Một số yêu cầu bắt buộc chưa đạt. Hãy liên hệ nhà cung cấp hosting để bật các phần mở rộng còn thiếu
                        rồi tải lại trang này.</div></div>
                    <a class="btn btn-ghost" href="install.php">🔄 Kiểm tra lại</a>
                <?php else: ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="step" value="2">
                        <button class="btn btn-primary btn-lg" type="submit">Tiếp tục →</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card mt-3">
                <div class="card-title"><span class="emoji">📋</span> Thông tin hữu ích</div>
                <ul class="small muted" style="padding-left:20px;margin:0">
                    <li>Giới hạn tải tệp hiện tại của máy chủ: <b><?= human_size(server_upload_limit()) ?></b>
                        (chỉnh trong tệp <code>.user.ini</code> hoặc mục PHP Selector của cPanel nếu cần lớn hơn).</li>
                    <li>Toàn bộ dữ liệu và tệp tin sẽ được lưu trong MySQL — hãy chọn gói hosting có dung lượng CSDL phù hợp.</li>
                    <li>Múi giờ hệ thống: <b>Asia/Ho_Chi_Minh (GMT+7)</b> — hiện tại <?= date('H:i d/m/Y') ?>.</li>
                </ul>
            </div>

        <?php else: ?>
            <form method="post">
                <input type="hidden" name="step" value="3">

                <div class="card mb-3">
                    <div class="card-title"><span class="emoji">🗄️</span> Kết nối cơ sở dữ liệu MySQL</div>
                    <p class="small muted">Thông tin này lấy từ mục <b>MySQL Databases</b> trong cPanel của bạn.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Máy chủ CSDL <span class="req">*</span></label>
                            <input type="text" name="db_host" value="<?= e($form['db_host']) ?>" required>
                            <div class="form-hint">Đa số hosting dùng <code>localhost</code>.</div>
                        </div>
                        <div class="form-group">
                            <label>Cổng</label>
                            <input type="text" name="db_port" value="<?= e($form['db_port']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tên cơ sở dữ liệu <span class="req">*</span></label>
                        <input type="text" name="db_name" value="<?= e($form['db_name']) ?>" required placeholder="vd: cpaneluser_lms">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Người dùng CSDL <span class="req">*</span></label>
                            <input type="text" name="db_user" value="<?= e($form['db_user']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu CSDL</label>
                            <input type="text" name="db_pass" value="<?= e($form['db_pass']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tiền tố bảng</label>
                        <input type="text" name="db_prefix" value="<?= e($form['db_prefix']) ?>">
                        <div class="form-hint">Giữ nguyên <code>lms_</code> nếu bạn không dùng chung CSDL với ứng dụng khác.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-title"><span class="emoji">🏫</span> Thông tin đơn vị</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tên website <span class="req">*</span></label>
                            <input type="text" name="site_name" value="<?= e($form['site_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tên đơn vị / nhà trường</label>
                            <input type="text" name="org_name" value="<?= e($form['org_name']) ?>">
                        </div>
                    </div>
                    <div class="form-hint">Có thể đổi lại bất cứ lúc nào trong mục Quản trị → Cấu hình chung (kèm logo, màu sắc, dòng bản quyền).</div>
                </div>

                <div class="card mb-3">
                    <div class="card-title"><span class="emoji">👑</span> Tài khoản quản trị viên</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Họ và tên <span class="req">*</span></label>
                            <input type="text" name="admin_name" value="<?= e($form['admin_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tên đăng nhập <span class="req">*</span></label>
                            <input type="text" name="admin_user" value="<?= e($form['admin_user']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="admin_email" value="<?= e($form['admin_email']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mật khẩu <span class="req">*</span></label>
                            <input type="password" name="admin_pass" required placeholder="Ít nhất 6 ký tự">
                        </div>
                        <div class="form-group">
                            <label>Nhập lại mật khẩu <span class="req">*</span></label>
                            <input type="password" name="admin_pass2" required>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-lg btn-block" type="submit">⚙️ Bắt đầu cài đặt</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <p class="center small muted mt-4">Hệ thống LMS · Giờ Việt Nam (GMT+7) · Phiên bản 1.2.0</p>
</div>
</body>
</html>
