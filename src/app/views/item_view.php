<?php if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); } ?>
<?php $meta = item_type_meta($item['type']); ?>
<?= breadcrumbs([
    ['label' => 'Khoá học', 'url' => url('course/mine')],
    ['label' => $course['title'], 'url' => url('course/view', ['id' => $course['id']])],
    ['label' => $item['title']],
]) ?>

<div class="page-head flex-between flex-wrap">
    <div class="flex-center">
        <span class="item-ico" style="width:52px;height:52px;font-size:24px;background:<?= e($meta[2]) ?>1a;color:<?= e($meta[2]) ?>"><?= $meta[0] ?></span>
        <div>
            <h1 style="margin-bottom:2px"><?= e($item['title']) ?></h1>
            <div class="sub"><?= e($meta[1]) ?>
                <?php if ($item['graded']): ?> · Thang điểm <?= score_fmt($item['max_points']) ?><?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($canManage): ?>
        <div class="page-actions">
            <a class="btn btn-ghost" href="<?= e(url('teach/item/edit', ['id' => $item['id']])) ?>">✏️ Chỉnh sửa</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($item['summary']): ?>
    <div class="alert alert-info"><span>💡</span><div><?= nl2html($item['summary']) ?></div></div>
<?php endif; ?>

<div class="card mb-3">
    <?php if ($item['type'] === 'page'): ?>
        <div class="rich-content"><?= safe_html($item['content']) ?></div>

    <?php elseif ($item['type'] === 'link'): ?>
        <div class="center" style="padding:22px 0">
            <div style="font-size:52px">🔗</div>
            <p class="muted">Nội dung này nằm ở một trang web bên ngoài.</p>
            <a class="btn btn-primary btn-lg" href="<?= e($item['url']) ?>" target="_blank" rel="noopener">Mở liên kết →</a>
            <div class="small muted mt-2"><?= e($item['url']) ?></div>
        </div>
        <?php if ($item['content']): ?><div class="rich-content mt-3"><?= safe_html($item['content']) ?></div><?php endif; ?>

    <?php elseif ($item['type'] === 'video'): ?>
        <?php
        $url = (string)$item['url'];
        $embed = '';
        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_\-]{6,})~', $url, $m)) {
            $embed = 'https://www.youtube.com/embed/' . $m[1];
        } elseif (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            $embed = 'https://player.vimeo.com/video/' . $m[1];
        }
        ?>
        <?php if ($embed): ?>
            <div class="video-wrap"><iframe src="<?= e($embed) ?>" allowfullscreen loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"></iframe></div>
        <?php elseif ($item['file_id']): ?>
            <video class="video-player" controls preload="metadata" style="width:100%;border-radius:var(--radius-sm);background:#000">
                <source src="<?= e(media_url($item['file_id'])) ?>">
                Trình duyệt của bạn không phát được video này.
            </video>
        <?php elseif ($url): ?>
            <div class="video-wrap"><iframe src="<?= e($url) ?>" allowfullscreen loading="lazy"></iframe></div>
        <?php else: ?>
            <p class="muted center">Chưa có nguồn video.</p>
        <?php endif; ?>
        <?php if ($item['content']): ?><div class="rich-content mt-3"><?= safe_html($item['content']) ?></div><?php endif; ?>

    <?php else: /* file */ ?>
        <?php if ($item['content']): ?><div class="rich-content mb-3"><?= safe_html($item['content']) ?></div><?php endif; ?>

        <?php
        $main = $item['file_id'] ? Storage::meta($item['file_id']) : null;
        if ($main):
            $ext = strtolower((string)$main['ext']); ?>
            <?php if ($ext === 'pdf'): ?>
                <iframe src="<?= e(media_url($main['id'])) ?>" style="width:100%;height:78vh;border:1px solid var(--border);border-radius:var(--radius-sm)"></iframe>
            <?php elseif (is_image_ext($ext)): ?>
                <div class="center"><img src="<?= e(media_url($main['id'])) ?>" alt="<?= e($main['name']) ?>" style="max-height:78vh;border-radius:var(--radius-sm)"></div>
            <?php elseif (is_video_ext($ext)): ?>
                <video controls preload="metadata" style="width:100%;border-radius:var(--radius-sm);background:#000">
                    <source src="<?= e(media_url($main['id'])) ?>" type="<?= e($main['mime']) ?>"></video>
            <?php elseif (is_audio_ext($ext)): ?>
                <audio controls style="width:100%"><source src="<?= e(media_url($main['id'])) ?>" type="<?= e($main['mime']) ?>"></audio>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($files): ?>
    <div class="card mb-3">
        <div class="card-title"><span class="emoji">📎</span> Tài liệu đính kèm (<?= count($files) ?>)</div>
        <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">
            <?php foreach ($files as $f):
                list($ic, $col) = file_icon($f['ext']); ?>
                <div class="file-item">
                    <span class="file-ico" style="background:<?= e($col) ?>1a"><?= $ic ?></span>
                    <div class="flex-1">
                        <div class="file-name"><?= e($f['name']) ?></div>
                        <div class="file-meta"><?= e(strtoupper($f['ext'])) ?> · <?= human_size($f['size']) ?></div>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(media_url($f['id'], true)) ?>" title="Tải về">⬇️</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="flex-between flex-wrap gap-1">
    <?php if ($prev): ?>
        <a class="btn btn-ghost" href="<?= e(url('item/view', ['id' => $prev['id']])) ?>">← <?= e(str_limit($prev['title'], 30)) ?></a>
    <?php else: ?><span></span><?php endif; ?>

    <a class="btn btn-ghost" href="<?= e(url('course/view', ['id' => $course['id']])) ?>">🗂️ Mục lục khoá học</a>

    <?php if ($next): ?>
        <a class="btn btn-primary" href="<?= e(url('item/view', ['id' => $next['id']])) ?>"><?= e(str_limit($next['title'], 30)) ?> →</a>
    <?php else: ?><span></span><?php endif; ?>
</div>
