<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Diễn đàn thảo luận theo khoá học */

function forum_index()
{
    Auth::requireLogin();
    $courseId = inp_int('course');
    $course = DB::find('courses', $courseId);
    if (!$course) { render_404(); return; }
    if (!Auth::canViewCourse($course)) { render_denied('Bạn cần ghi danh khoá học để tham gia thảo luận.'); return; }

    if (is_post()) {
        csrf_verify();
        $title = inp('title');
        if ($title === '') { flash_err('Vui lòng nhập tiêu đề chủ đề.'); }
        else {
            $tid = DB::insert('threads', [
                'course_id' => $courseId,
                'item_id'   => inp_int('item') ?: null,
                'user_id'   => Auth::id(),
                'title'     => $title,
                'content'   => safe_html(inp('content')) ?: null,
                'pinned'    => Auth::canManageCourse($course) ? inp_bool('pinned') : 0,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);
            Log::write('forum_thread', 'course', $courseId);
            flash_ok('Đã đăng chủ đề thảo luận. 💬');
            redirect(url('forum/thread', ['id' => $tid]));
        }
    }

    $threads = DB::all('SELECT t.*, u.full_name, u.avatar_id,
                            (SELECT MAX(p.created_at) FROM {P}posts p WHERE p.thread_id = t.id) AS last_post
                        FROM {P}threads t JOIN {P}users u ON u.id = t.user_id
                        WHERE t.course_id = :c ORDER BY t.pinned DESC, COALESCE((SELECT MAX(p.created_at) FROM {P}posts p WHERE p.thread_id = t.id), t.created_at) DESC',
                       ['c' => $courseId]);

    view('forum_index', ['title' => 'Thảo luận: ' . $course['title'], 'course' => $course, 'threads' => $threads]);
}

function forum_thread()
{
    Auth::requireLogin();
    $thread = DB::row('SELECT t.*, u.full_name, u.avatar_id, u.role FROM {P}threads t
                       JOIN {P}users u ON u.id = t.user_id WHERE t.id = :id', ['id' => inp_int('id')]);
    if (!$thread) { render_404(); return; }
    $course = DB::find('courses', $thread['course_id']);
    if (!Auth::canViewCourse($course)) { render_denied(); return; }

    $canManage = Auth::canManageCourse($course);

    if (is_post()) {
        csrf_verify();
        $action = inp('action');
        if ($action === 'reply') {
            if ($thread['locked'] && !$canManage) { flash_err('Chủ đề đã bị khoá.'); back(); }
            $content = safe_html(inp('content'));
            if (trim(strip_tags($content)) === '') { flash_err('Nội dung trả lời không được để trống.'); back(); }
            DB::insert('posts', ['thread_id' => $thread['id'], 'user_id' => Auth::id(),
                                 'content' => $content, 'created_at' => now()]);
            DB::q('UPDATE {P}threads SET reply_count = reply_count + 1, updated_at = :t WHERE id = :id',
                  ['t' => now(), 'id' => $thread['id']]);
            if ((int)$thread['user_id'] !== Auth::id()) {
                Notify::push($thread['user_id'], 'Có phản hồi mới trong chủ đề của bạn',
                    Auth::name() . ' đã trả lời: ' . $thread['title'],
                    url('forum/thread', ['id' => $thread['id']]), '💬');
            }
            flash_ok('Đã gửi phản hồi.');
        } elseif ($action === 'delete_thread' && ($canManage || (int)$thread['user_id'] === Auth::id())) {
            DB::delete('posts', 'thread_id = :t', ['t' => $thread['id']]);
            DB::delete('threads', 'id = :t', ['t' => $thread['id']]);
            flash_ok('Đã xoá chủ đề.');
            redirect(url('forum/index', ['course' => $course['id']]));
        } elseif ($action === 'delete_post') {
            $post = DB::find('posts', inp_int('post'));
            if ($post && ($canManage || (int)$post['user_id'] === Auth::id())) {
                DB::delete('posts', 'id = :p', ['p' => $post['id']]);
                DB::q('UPDATE {P}threads SET reply_count = GREATEST(reply_count - 1, 0) WHERE id = :id', ['id' => $thread['id']]);
                flash_ok('Đã xoá phản hồi.');
            }
        } elseif ($action === 'toggle_lock' && $canManage) {
            DB::update('threads', ['locked' => $thread['locked'] ? 0 : 1], 'id = :id', ['id' => $thread['id']]);
            flash_ok($thread['locked'] ? 'Đã mở khoá chủ đề.' : 'Đã khoá chủ đề.');
        } elseif ($action === 'toggle_pin' && $canManage) {
            DB::update('threads', ['pinned' => $thread['pinned'] ? 0 : 1], 'id = :id', ['id' => $thread['id']]);
            flash_ok('Đã cập nhật ghim chủ đề.');
        }
        redirect(url('forum/thread', ['id' => $thread['id']]));
    }

    DB::q('UPDATE {P}threads SET views = views + 1 WHERE id = :id', ['id' => $thread['id']]);
    $posts = DB::all('SELECT p.*, u.full_name, u.avatar_id, u.role FROM {P}posts p
                      JOIN {P}users u ON u.id = p.user_id WHERE p.thread_id = :t ORDER BY p.id',
                     ['t' => $thread['id']]);

    view('forum_thread', ['title' => $thread['title'], 'thread' => $thread, 'course' => $course,
                          'posts' => $posts, 'canManage' => $canManage]);
}
