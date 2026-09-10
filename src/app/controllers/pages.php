<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Trang công khai: trang chủ, danh mục khoá học, giới thiệu */

function home_index()
{
    $stats = [
        'courses'  => (int)DB::val('SELECT COUNT(*) FROM {P}courses WHERE status = "published"', [], 0),
        'students' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "student" AND status = "active"', [], 0),
        'teachers' => (int)DB::val('SELECT COUNT(*) FROM {P}users WHERE role = "teacher" AND status = "active"', [], 0),
        'items'    => (int)DB::val('SELECT COUNT(*) FROM {P}items', [], 0),
    ];

    $courses = DB::all('SELECT c.*, u.full_name AS teacher_name, cat.name AS category_name, cat.color AS category_color,
                        (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = "active") AS student_count,
                        (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id) AS item_count
                        FROM {P}courses c
                        LEFT JOIN {P}users u ON u.id = c.owner_id
                        LEFT JOIN {P}categories cat ON cat.id = c.category_id
                        WHERE c.status = "published" AND c.visibility = "public"
                        ORDER BY student_count DESC, c.id DESC LIMIT 6');

    $categories = DB::all('SELECT cat.*, (SELECT COUNT(*) FROM {P}courses c WHERE c.category_id = cat.id AND c.status = "published") AS n
                           FROM {P}categories cat ORDER BY cat.position, cat.name');

    view('home', [
        'title' => 'Trang chủ',
        'stats' => $stats,
        'courses' => $courses,
        'categories' => $categories,
    ], 'public');
}

function catalog_index()
{
    $q = inp('q');
    $cat = inp_int('cat');
    $page = max(1, inp_int('page', 1));
    $per = 12;

    $where = 'c.status = "published"';
    $params = [];
    if (!Auth::isAdmin()) $where .= ' AND c.visibility = "public"';
    if ($q !== '') {
        $where .= ' AND (c.title LIKE :q OR c.code LIKE :q OR c.summary LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    if ($cat) { $where .= ' AND c.category_id = :cat'; $params['cat'] = $cat; }

    $total = (int)DB::val("SELECT COUNT(*) FROM {P}courses c WHERE $where", $params, 0);
    $courses = DB::all("SELECT c.*, u.full_name AS teacher_name, cat.name AS category_name, cat.color AS category_color,
                        (SELECT COUNT(*) FROM {P}enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
                        (SELECT COUNT(*) FROM {P}items i WHERE i.course_id = c.id) AS item_count
                        FROM {P}courses c
                        LEFT JOIN {P}users u ON u.id = c.owner_id
                        LEFT JOIN {P}categories cat ON cat.id = c.category_id
                        WHERE $where ORDER BY c.id DESC LIMIT " . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);

    $categories = DB::all('SELECT cat.*, (SELECT COUNT(*) FROM {P}courses c WHERE c.category_id = cat.id AND c.status = "published") AS n
                           FROM {P}categories cat ORDER BY cat.position, cat.name');

    view('catalog', [
        'title' => 'Khám phá khoá học',
        'courses' => $courses, 'categories' => $categories,
        'total' => $total, 'page' => $page, 'per' => $per, 'q' => $q, 'cat' => $cat,
    ], Auth::check() ? 'app' : 'public');
}

function about_index()
{
    view('about', ['title' => 'Giới thiệu'], Auth::check() ? 'app' : 'public');
}
