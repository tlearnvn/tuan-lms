/* ============================================================
   Xem trước tệp học liệu ngay trên trang — Word, Excel, PowerPoint,
   PDF, ảnh, video, âm thanh, văn bản, mã nguồn và tệp nén.
   ============================================================ */
(function () {
    'use strict';

    var LMS = window.LMS || {};
    window.LMS = LMS;

    var cache = {};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    /** Các nút xem trước cùng nhóm để lật qua lại bằng phím ← → */
    function siblings(el) {
        var scope = el && el.closest ? el.closest('[data-preview-group]') : null;
        var list = (scope || document).querySelectorAll('[data-preview]');
        return Array.prototype.slice.call(list);
    }

    LMS.preview = function (fileId, trigger) {
        if (!LMS.urls || !LMS.urls.preview) return false;

        var group = siblings(trigger);
        var index = -1;
        for (var i = 0; i < group.length; i++) {
            if (group[i].getAttribute('data-preview') === String(fileId)) { index = i; break; }
        }

        var modal = LMS.modal('<span class="pv-title">Đang mở tệp…</span>',
            '<div class="pv-loading"><span class="spinner"></span> Đang tải nội dung…</div>',
            '<span class="pv-nav"></span><span class="flex-1"></span>'
            + '<a class="btn btn-ghost pv-open" target="_blank" rel="noopener">↗️ Mở tab mới</a>'
            + '<a class="btn btn-primary pv-dl">⬇️ Tải về</a>',
            'modal-preview');

        var head = modal.querySelector('.pv-title');
        var body = modal.querySelector('.modal-body');
        var nav  = modal.querySelector('.pv-nav');

        function go(step) {
            var next = index + step;
            if (next < 0 || next >= group.length) return;
            index = next;
            load(group[index].getAttribute('data-preview'));
        }

        function onKey(e) {
            if (!document.body.contains(modal)) { document.removeEventListener('keydown', onKey); return; }
            if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
            if (e.key === 'ArrowLeft') go(-1);
            if (e.key === 'ArrowRight') go(1);
        }
        document.addEventListener('keydown', onKey);

        function render(res) {
            head.innerHTML = '<span class="pv-name">' + esc(res.name) + '</span>'
                + '<span class="pv-sub">' + esc(res.label) + ' · ' + esc(res.ext) + ' · ' + esc(res.size) + '</span>';
            body.innerHTML = '<div class="pv-body">' + res.html + '</div>';
            modal.querySelector('.pv-open').href = res.open;
            modal.querySelector('.pv-dl').href = res.download;
            if (group.length > 1 && index >= 0) {
                nav.innerHTML = '<button type="button" class="btn btn-ghost btn-sm pv-prev"' + (index === 0 ? ' disabled' : '') + '>←</button>'
                    + '<span class="tiny muted" style="margin:0 8px">Tệp ' + (index + 1) + '/' + group.length + '</span>'
                    + '<button type="button" class="btn btn-ghost btn-sm pv-next"' + (index === group.length - 1 ? ' disabled' : '') + '>→</button>';
                nav.querySelector('.pv-prev').onclick = function () { go(-1); };
                nav.querySelector('.pv-next').onclick = function () { go(1); };
            }
            body.scrollTop = 0;
        }

        function load(id) {
            if (cache[id]) { render(cache[id]); return; }
            body.innerHTML = '<div class="pv-loading"><span class="spinner"></span> Đang tải nội dung…</div>';
            head.textContent = 'Đang mở tệp…';
            LMS.get(LMS.urls.preview + '&f=' + encodeURIComponent(id), function (res) {
                if (!res.ok) {
                    body.innerHTML = '<div class="pv-empty"><div class="pv-empty-emoji">🚫</div><p>'
                        + esc(res.error || 'Không mở được tệp.') + '</p></div>';
                    head.textContent = 'Không mở được tệp';
                    return;
                }
                cache[id] = res;
                render(res);
            }, function (msg) {
                body.innerHTML = '<div class="pv-empty"><div class="pv-empty-emoji">😥</div><p>' + esc(msg) + '</p></div>';
            });
        }

        load(fileId);
        return true;
    };

    document.addEventListener('click', function (e) {
        // Chuyển tab trang tính Excel (dùng cả trong hộp thoại lẫn trên trang)
        var tab = e.target.closest ? e.target.closest('[data-pv-tab]') : null;
        if (tab) {
            var id = tab.getAttribute('data-pv-tab');
            var bar = tab.parentNode;
            Array.prototype.forEach.call(bar.querySelectorAll('[data-pv-tab]'), function (b) {
                b.classList.toggle('active', b === tab);
                var pane = document.getElementById(b.getAttribute('data-pv-tab'));
                if (pane) pane.classList.toggle('hidden', b !== tab);
            });
            void id;
            e.preventDefault();
            return;
        }

        var el = e.target.closest ? e.target.closest('[data-preview]') : null;
        if (!el) return;
        var fid = el.getAttribute('data-preview');
        if (!fid) return;
        // Ctrl/⌘ + click hoặc chuột giữa: giữ hành vi mở tab mới của trình duyệt
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        if (LMS.preview(fid, el)) e.preventDefault();
    });
})();
