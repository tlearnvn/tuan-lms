<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Lược đồ cơ sở dữ liệu MySQL cho hệ thống LMS.
 * Tất cả dữ liệu (kể cả tệp tin) đều nằm trong MySQL.
 * {P} sẽ được thay bằng tiền tố bảng (mặc định lms_).
 */

function lms_schema_tables()
{
    return [

        // ---------------------------------------------------------------- người dùng
        'users' => "CREATE TABLE IF NOT EXISTS `{P}users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(64) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(190) NOT NULL,
            `role` ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
            `status` ENUM('active','pending','locked') NOT NULL DEFAULT 'active',
            `avatar_id` INT UNSIGNED NULL,
            `phone` VARCHAR(32) NULL,
            `birthday` DATE NULL,
            `gender` ENUM('male','female','other') NULL,
            `org_unit` VARCHAR(190) NULL,
            `bio` TEXT NULL,
            `theme` VARCHAR(16) NOT NULL DEFAULT 'light',
            `remember_token` VARCHAR(64) NULL,
            `remember_expires` DATETIME NULL,
            `last_login` DATETIME NULL,
            `login_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_username` (`username`),
            UNIQUE KEY `uq_email` (`email`),
            KEY `idx_role` (`role`),
            KEY `idx_status` (`status`),
            KEY `idx_remember` (`remember_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- tệp tin (BLOB trong MySQL)
        'files' => "CREATE TABLE IF NOT EXISTS `{P}files` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `ext` VARCHAR(16) NULL,
            `mime` VARCHAR(150) NOT NULL DEFAULT 'application/octet-stream',
            `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `sha1` CHAR(40) NULL,
            `visibility` ENUM('public','auth','private') NOT NULL DEFAULT 'auth',
            `owner_id` INT UNSIGNED NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_owner` (`owner_id`),
            KEY `idx_sha1` (`sha1`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'file_chunks' => "CREATE TABLE IF NOT EXISTS `{P}file_chunks` (
            `file_id` INT UNSIGNED NOT NULL,
            `seq` INT UNSIGNED NOT NULL,
            `data` LONGBLOB NOT NULL,
            PRIMARY KEY (`file_id`,`seq`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- danh mục & khoá học
        'categories' => "CREATE TABLE IF NOT EXISTS `{P}categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(190) NOT NULL,
            `color` VARCHAR(16) NOT NULL DEFAULT '#6C5CE7',
            `icon` VARCHAR(16) NOT NULL DEFAULT '📚',
            `position` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'courses' => "CREATE TABLE IF NOT EXISTS `{P}courses` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `summary` TEXT NULL,
            `description` LONGTEXT NULL,
            `cover_id` INT UNSIGNED NULL,
            `category_id` INT UNSIGNED NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            `visibility` ENUM('public','private') NOT NULL DEFAULT 'public',
            `enroll_mode` ENUM('open','key','manual','approval') NOT NULL DEFAULT 'open',
            `enroll_key` VARCHAR(64) NULL,
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `max_students` INT NOT NULL DEFAULT 0,
            `color` VARCHAR(16) NOT NULL DEFAULT '#6C5CE7',
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_code` (`code`),
            KEY `idx_owner` (`owner_id`),
            KEY `idx_status` (`status`),
            KEY `idx_cat` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'course_teachers' => "CREATE TABLE IF NOT EXISTS `{P}course_teachers` (
            `course_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`course_id`,`user_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'enrollments' => "CREATE TABLE IF NOT EXISTS `{P}enrollments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `status` ENUM('active','pending','completed','removed') NOT NULL DEFAULT 'active',
            `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `final_score` DECIMAL(8,2) NULL,
            `note` VARCHAR(255) NULL,
            `enrolled_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_enroll` (`course_id`,`user_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- nội dung
        'sections' => "CREATE TABLE IF NOT EXISTS `{P}sections` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `summary` TEXT NULL,
            `position` INT NOT NULL DEFAULT 0,
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `idx_course` (`course_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'items' => "CREATE TABLE IF NOT EXISTS `{P}items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NOT NULL,
            `section_id` INT UNSIGNED NULL,
            `type` ENUM('page','file','video','link','scorm','assignment','quiz','forum') NOT NULL DEFAULT 'page',
            `title` VARCHAR(255) NOT NULL,
            `summary` TEXT NULL,
            `content` LONGTEXT NULL,
            `file_id` INT UNSIGNED NULL,
            `url` VARCHAR(500) NULL,
            `position` INT NOT NULL DEFAULT 0,
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            `open_at` DATETIME NULL,
            `close_at` DATETIME NULL,
            `graded` TINYINT(1) NOT NULL DEFAULT 0,
            `max_points` DECIMAL(8,2) NOT NULL DEFAULT 10.00,
            `weight` DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            `settings` TEXT NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_course` (`course_id`),
            KEY `idx_section` (`section_id`),
            KEY `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'item_files' => "CREATE TABLE IF NOT EXISTS `{P}item_files` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_id` INT UNSIGNED NOT NULL,
            `file_id` INT UNSIGNED NOT NULL,
            `position` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- bài tập
        'assignments' => "CREATE TABLE IF NOT EXISTS `{P}assignments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_id` INT UNSIGNED NOT NULL,
            `instructions` LONGTEXT NULL,
            `due_at` DATETIME NULL,
            `cutoff_at` DATETIME NULL,
            `allow_late` TINYINT(1) NOT NULL DEFAULT 1,
            `submission_type` VARCHAR(40) NOT NULL DEFAULT 'file,text',
            `max_files` INT NOT NULL DEFAULT 5,
            `allowed_ext` VARCHAR(255) NULL,
            `max_attempts` INT NOT NULL DEFAULT 1,
            `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `ai_auto` TINYINT(1) NOT NULL DEFAULT 0,
            `ai_apply` TINYINT(1) NOT NULL DEFAULT 0,
            `ai_rubric` LONGTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'submissions' => "CREATE TABLE IF NOT EXISTS `{P}submissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `attempt` INT NOT NULL DEFAULT 1,
            `content` LONGTEXT NULL,
            `status` ENUM('draft','submitted','graded','returned') NOT NULL DEFAULT 'draft',
            `submitted_at` DATETIME NULL,
            `is_late` TINYINT(1) NOT NULL DEFAULT 0,
            `score` DECIMAL(8,2) NULL,
            `feedback` LONGTEXT NULL,
            `graded_by` INT UNSIGNED NULL,
            `graded_at` DATETIME NULL,
            `ai_score` DECIMAL(8,2) NULL,
            `ai_feedback` LONGTEXT NULL,
            `ai_raw` LONGTEXT NULL,
            `ai_status` VARCHAR(20) NULL,
            `ai_at` DATETIME NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_item_user` (`item_id`,`user_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'submission_files' => "CREATE TABLE IF NOT EXISTS `{P}submission_files` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `submission_id` INT UNSIGNED NOT NULL,
            `file_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sub` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- trắc nghiệm
        'quizzes' => "CREATE TABLE IF NOT EXISTS `{P}quizzes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_id` INT UNSIGNED NOT NULL,
            `intro` LONGTEXT NULL,
            `time_limit` INT NOT NULL DEFAULT 0,
            `max_attempts` INT NOT NULL DEFAULT 1,
            `shuffle_q` TINYINT(1) NOT NULL DEFAULT 0,
            `shuffle_a` TINYINT(1) NOT NULL DEFAULT 0,
            `show_result` ENUM('immediate','after_close','never') NOT NULL DEFAULT 'immediate',
            `pass_score` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
            `grade_method` ENUM('highest','last','average','first') NOT NULL DEFAULT 'highest',
            `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'questions' => "CREATE TABLE IF NOT EXISTS `{P}questions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `quiz_id` INT UNSIGNED NOT NULL,
            `type` ENUM('single','multi','truefalse','short','essay') NOT NULL DEFAULT 'single',
            `content` LONGTEXT NOT NULL,
            `points` DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            `position` INT NOT NULL DEFAULT 0,
            `feedback` TEXT NULL,
            `image_id` INT UNSIGNED NULL,
            `answer_key` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_quiz` (`quiz_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'options' => "CREATE TABLE IF NOT EXISTS `{P}options` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `question_id` INT UNSIGNED NOT NULL,
            `content` TEXT NOT NULL,
            `correct` TINYINT(1) NOT NULL DEFAULT 0,
            `position` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_q` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'attempts' => "CREATE TABLE IF NOT EXISTS `{P}attempts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `quiz_id` INT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `number` INT NOT NULL DEFAULT 1,
            `started_at` DATETIME NULL,
            `finished_at` DATETIME NULL,
            `score` DECIMAL(8,2) NULL,
            `max_score` DECIMAL(8,2) NULL,
            `status` ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
            `order_data` LONGTEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_quiz_user` (`quiz_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'answers' => "CREATE TABLE IF NOT EXISTS `{P}answers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `attempt_id` INT UNSIGNED NOT NULL,
            `question_id` INT UNSIGNED NOT NULL,
            `response` LONGTEXT NULL,
            `score` DECIMAL(6,2) NULL,
            `feedback` TEXT NULL,
            `graded` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_ans` (`attempt_id`,`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- SCORM
        'scorm_packages' => "CREATE TABLE IF NOT EXISTS `{P}scorm_packages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `item_id` INT UNSIGNED NOT NULL,
            `file_id` INT UNSIGNED NULL,
            `identifier` VARCHAR(255) NULL,
            `title` VARCHAR(255) NULL,
            `version` VARCHAR(32) NOT NULL DEFAULT '1.2',
            `launch_url` VARCHAR(500) NULL,
            `entry_count` INT NOT NULL DEFAULT 0,
            `manifest` LONGTEXT NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'scorm_files' => "CREATE TABLE IF NOT EXISTS `{P}scorm_files` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `package_id` INT UNSIGNED NOT NULL,
            `path` VARCHAR(500) NOT NULL,
            `file_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_pkg_path` (`package_id`,`path`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'scorm_tracks' => "CREATE TABLE IF NOT EXISTS `{P}scorm_tracks` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `package_id` INT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `sco` VARCHAR(100) NOT NULL DEFAULT 'default',
            `element` VARCHAR(190) NOT NULL,
            `value` LONGTEXT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_track` (`package_id`,`user_id`,`sco`,`element`),
            KEY `idx_user_item` (`user_id`,`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- theo dõi tiến độ
        'completions' => "CREATE TABLE IF NOT EXISTS `{P}completions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `status` ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
            `score` DECIMAL(8,2) NULL,
            `time_spent` INT NOT NULL DEFAULT 0,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_comp` (`item_id`,`user_id`),
            KEY `idx_course_user` (`course_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- trao đổi
        'announcements' => "CREATE TABLE IF NOT EXISTS `{P}announcements` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NULL,
            `pinned` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_course` (`course_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'threads' => "CREATE TABLE IF NOT EXISTS `{P}threads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `course_id` INT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NULL,
            `pinned` TINYINT(1) NOT NULL DEFAULT 0,
            `locked` TINYINT(1) NOT NULL DEFAULT 0,
            `views` INT UNSIGNED NOT NULL DEFAULT 0,
            `reply_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_course` (`course_id`),
            KEY `idx_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'posts' => "CREATE TABLE IF NOT EXISTS `{P}posts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `thread_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `content` LONGTEXT NOT NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_thread` (`thread_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'notifications' => "CREATE TABLE IF NOT EXISTS `{P}notifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `body` TEXT NULL,
            `url` VARCHAR(500) NULL,
            `icon` VARCHAR(16) NOT NULL DEFAULT '🔔',
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`,`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---------------------------------------------------------------- hệ thống
        'settings' => "CREATE TABLE IF NOT EXISTS `{P}settings` (
            `k` VARCHAR(64) NOT NULL,
            `v` LONGTEXT NULL,
            PRIMARY KEY (`k`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'sessions' => "CREATE TABLE IF NOT EXISTS `{P}sessions` (
            `id` VARCHAR(128) NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `ip` VARCHAR(45) NULL,
            `ua` VARCHAR(255) NULL,
            `payload` LONGTEXT NULL,
            `last_activity` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_last` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'logs' => "CREATE TABLE IF NOT EXISTS `{P}logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(64) NOT NULL,
            `target` VARCHAR(64) NULL,
            `target_id` INT UNSIGNED NULL,
            `detail` TEXT NULL,
            `ip` VARCHAR(45) NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'ai_jobs' => "CREATE TABLE IF NOT EXISTS `{P}ai_jobs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `submission_id` INT UNSIGNED NULL,
            `attempt_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NULL,
            `status` ENUM('queued','running','done','error') NOT NULL DEFAULT 'queued',
            `provider` VARCHAR(32) NULL,
            `model` VARCHAR(120) NULL,
            `request` LONGTEXT NULL,
            `response` LONGTEXT NULL,
            `error` TEXT NULL,
            `duration` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sub` (`submission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/** Giá trị mặc định của bảng cấu hình */
function lms_default_settings()
{
    return [
        // Thương hiệu
        'site_name'            => 'Trường học số',
        'site_tagline'         => 'Học vui – Học giỏi – Học mọi lúc mọi nơi',
        'org_name'             => 'Trung tâm Giáo dục & Đào tạo',
        'copyright'            => '© ' . date('Y') . ' Trường học số. Bảo lưu mọi quyền.',
        'logo_id'              => '',
        'favicon_id'           => '',
        'primary_color'        => '#6C5CE7',
        'accent_color'         => '#FF7675',
        'hero_image_id'        => '',
        'contact_email'        => '',
        'contact_phone'        => '',
        'contact_address'      => '',
        'footer_note'          => 'Được xây dựng cho cộng đồng giáo dục Việt Nam.',

        // Đăng ký tài khoản
        'allow_student_register' => '1',
        'allow_teacher_register' => '1',   // admin bật/tắt đăng ký giáo viên
        'teacher_need_approval'  => '1',   // giáo viên đăng ký phải chờ duyệt
        'student_need_approval'  => '0',
        'register_note'          => '',

        // Phiên làm việc
        'session_lifetime'     => '43200',  // 12 giờ – học sinh làm bài không bị out
        'session_keepalive'    => '1',      // JS tự động giữ phiên
        'remember_days'        => '30',

        // Tệp tin
        'max_upload_mb'        => '64',
        'allowed_ext'          => 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,odt,ods,odp,zip,rar,7z,jpg,jpeg,png,gif,webp,svg,bmp,mp3,wav,ogg,m4a,mp4,webm,mkv,avi,mov,html,htm,csv,json,epub',
        'chunk_size_kb'        => '512',

        // AI chấm bài
        'ai_enabled'           => '0',
        'ai_provider'          => 'openai',           // openai | anthropic | gemini | custom
        'ai_endpoint'          => 'https://api.openai.com/v1/chat/completions',
        'ai_api_key'           => '',
        'ai_model'             => 'gpt-4o-mini',
        'ai_max_tokens'        => '64000',
        'ai_timeout'           => '300',
        'ai_temperature'       => '0.2',
        'ai_extra_headers'     => '',
        'ai_system_prompt'     => "Bạn là trợ giảng chấm bài tận tâm, công tâm và giàu kinh nghiệm sư phạm tại Việt Nam. Hãy chấm bài của học sinh theo thang điểm và tiêu chí được cung cấp. Luôn nhận xét bằng tiếng Việt, nêu rõ điểm mạnh, điểm cần cải thiện và gợi ý cụ thể.",
        'ai_vision'            => '1',                 // gửi kèm ảnh bài làm nếu có
        'ai_auto_default'      => '0',

        // Công thức toán & trình soạn thảo
        'enable_math'          => '1',      // bật LaTeX/MathJax trong bài giảng, học liệu, bài tập
        'math_dollar'          => '1',      // cho phép dùng $...$ ngoài \\( ... \\)
        'mathjax_url'          => 'assets/js/mathjax/tex-mml-chtml.js',  // bản cài kèm, không cần Internet
        'editor_enabled'       => '1',      // thanh công cụ soạn thảo (chèn ảnh, công thức, bảng...)

        // Xem trước tệp học liệu
        'preview_enabled'      => '1',      // mở tệp đính kèm ngay trên web (Word, Excel, PowerPoint, PDF…)
        'preview_max_mb'       => '25',     // tệp lớn hơn mức này chỉ cho tải về

        // Khác
        'timezone'             => 'Asia/Ho_Chi_Minh',
        'date_format'          => 'd/m/Y',
        'datetime_format'      => 'H:i d/m/Y',
        'items_per_page'       => '20',
        'grade_scale'          => '10',
        'maintenance'          => '0',
        'maintenance_message'  => 'Hệ thống đang bảo trì, vui lòng quay lại sau ít phút.',
        'landing_show_courses' => '1',
        'landing_show_stats'   => '1',
        'schema_version'       => '1',
    ];
}
