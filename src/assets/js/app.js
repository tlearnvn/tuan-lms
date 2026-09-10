/* ============================================================
   Kịch bản giao diện LMS – không phụ thuộc thư viện ngoài
   ============================================================ */
(function () {
    'use strict';

    var LMS = window.LMS || {};
    window.LMS = LMS;

    // ------------------------------------------------------ tiện ích
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
    LMS.$ = $; LMS.$$ = $$;

    LMS.post = function (url, data, cb, errCb) {
        var fd;
        if (data instanceof FormData) fd = data;
        else {
            fd = new FormData();
            for (var k in data) if (Object.prototype.hasOwnProperty.call(data, k)) fd.append(k, data[k]);
        }
        if (LMS.csrf && !fd.has('_csrf')) fd.append('_csrf', LMS.csrf);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (LMS.csrf) xhr.setRequestHeader('X-CSRF-Token', LMS.csrf);
        xhr.onload = function () {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { }
            if (res) { cb && cb(res); }
            else { errCb ? errCb('Phản hồi không hợp lệ từ máy chủ.') : LMS.toast('danger', 'Phản hồi không hợp lệ từ máy chủ.'); }
        };
        xhr.onerror = function () {
            errCb ? errCb('Không kết nối được máy chủ.') : LMS.toast('danger', 'Không kết nối được máy chủ.');
        };
        xhr.send(fd);
        return xhr;
    };

    // ------------------------------------------------------ thông báo nổi
    LMS.toast = function (type, msg, timeout) {
        var stack = $('.flash-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'flash-stack';
            document.body.appendChild(stack);
        }
        var icons = { success: '🎉', danger: '⚠️', warning: '💡', info: 'ℹ️' };
        var el = document.createElement('div');
        el.className = 'alert alert-' + type;
        el.innerHTML = '<span>' + (icons[type] || 'ℹ️') + '</span><div class="flex-1">' + msg + '</div>'
            + '<button type="button" class="alert-x" aria-label="Đóng">×</button>';
        el.querySelector('.alert-x').onclick = function () { el.remove(); };
        stack.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .4s, transform .4s';
            el.style.opacity = '0'; el.style.transform = 'translateX(24px)';
            setTimeout(function () { el.remove(); }, 400);
        }, timeout || 5200);
    };

    // ------------------------------------------------------ pháo giấy
    LMS.confetti = function (n) {
        var colors = ['#6C5CE7', '#00B894', '#FDCB6E', '#FF7675', '#74B9FF', '#E84393'];
        n = n || 70;
        for (var i = 0; i < n; i++) {
            (function (i) {
                setTimeout(function () {
                    var p = document.createElement('div');
                    p.className = 'confetti-piece';
                    p.style.left = Math.random() * 100 + 'vw';
                    p.style.background = colors[Math.floor(Math.random() * colors.length)];
                    p.style.animationDuration = (2.2 + Math.random() * 1.8) + 's';
                    p.style.borderRadius = Math.random() > .5 ? '50%' : '2px';
                    document.body.appendChild(p);
                    setTimeout(function () { p.remove(); }, 4200);
                }, i * 18);
            })(i);
        }
    };

    // ------------------------------------------------------ khởi tạo
    document.addEventListener('DOMContentLoaded', function () {

        // Thanh bên
        var toggle = $('.menu-toggle'), sidebar = $('.sidebar');
        if (toggle && sidebar) {
            toggle.onclick = function () {
                sidebar.classList.toggle('open');
                if (sidebar.classList.contains('open')) {
                    var bd = document.createElement('div');
                    bd.className = 'sidebar-backdrop';
                    bd.onclick = function () { sidebar.classList.remove('open'); bd.remove(); };
                    document.body.appendChild(bd);
                } else {
                    var b = $('.sidebar-backdrop'); if (b) b.remove();
                }
            };
        }

        // Menu thả xuống
        $$('.dropdown > [data-dropdown]').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var dd = btn.parentNode;
                var wasOpen = dd.classList.contains('open');
                $$('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
                if (!wasOpen) dd.classList.add('open');
            };
        });
        document.addEventListener('click', function () {
            $$('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
        });

        // Đổi giao diện sáng/tối
        $$('[data-theme-toggle]').forEach(function (btn) {
            btn.onclick = function () {
                var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                var next = cur === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('lms-theme', next); } catch (e) { }
                if (LMS.urls && LMS.urls.theme) LMS.post(LMS.urls.theme, { theme: next });
                btn.textContent = next === 'dark' ? '☀️' : '🌙';
            };
        });

        // Tự đóng thông báo
        $$('.flash-stack .alert').forEach(function (el) {
            var x = el.querySelector('.alert-x');
            if (x) x.onclick = function () { el.remove(); };
            setTimeout(function () {
                el.style.transition = 'opacity .4s, transform .4s';
                el.style.opacity = '0'; el.style.transform = 'translateX(24px)';
                setTimeout(function () { el.remove(); }, 400);
            }, 6000);
        });

        // Xác nhận trước khi xoá
        $$('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault(); e.stopPropagation();
                }
            });
        });

        // Vùng kéo thả tệp
        $$('.dropzone').forEach(function (dz) {
            var input = dz.querySelector('input[type=file]') || document.getElementById(dz.getAttribute('data-input'));
            if (!input) return;
            var preview = dz.parentNode.querySelector('.file-preview');
            dz.addEventListener('click', function (e) { if (e.target !== input) input.click(); });
            ['dragenter', 'dragover'].forEach(function (ev) {
                dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('dragover'); });
            });
            dz.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
            input.addEventListener('change', function () {
                if (!preview) return;
                preview.innerHTML = '';
                Array.prototype.forEach.call(input.files, function (f) {
                    var div = document.createElement('div');
                    div.className = 'file-item';
                    div.innerHTML = '<span class="file-ico">📎</span><div class="flex-1"><div class="file-name">'
                        + f.name.replace(/</g, '&lt;') + '</div><div class="file-meta">' + LMS.humanSize(f.size) + '</div></div>';
                    preview.appendChild(div);
                });
            });
        });

        // Ô tìm kiếm lọc bảng ngay tại chỗ
        $$('[data-filter-table]').forEach(function (input) {
            input.addEventListener('input', function () {
                var table = document.querySelector(input.getAttribute('data-filter-table'));
                if (!table) return;
                var q = LMS.noAccent(input.value.toLowerCase().trim());
                var shown = 0;
                $$('tbody tr', table).forEach(function (tr) {
                    var txt = LMS.noAccent(tr.textContent.toLowerCase());
                    var ok = q === '' || txt.indexOf(q) !== -1;
                    tr.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                var counter = document.querySelector(input.getAttribute('data-filter-count') || '');
                if (counter) counter.textContent = shown;
            });
        });

        // Textarea tự giãn
        $$('textarea[data-autogrow]').forEach(function (ta) {
            var grow = function () { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight + 4) + 'px'; };
            ta.addEventListener('input', grow); grow();
        });

        // Giữ phiên đăng nhập khi học sinh làm bài lâu
        if (LMS.keepalive && LMS.urls && LMS.urls.ping) {
            setInterval(function () {
                var img = new XMLHttpRequest();
                img.open('GET', LMS.urls.ping, true);
                img.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                img.send();
            }, 5 * 60 * 1000);
        }

        // Đánh dấu chưa lưu khi rời trang
        $$('form[data-warn-unsaved]').forEach(function (form) {
            var dirty = false;
            form.addEventListener('input', function () { dirty = true; });
            form.addEventListener('submit', function () { dirty = false; });
            window.addEventListener('beforeunload', function (e) {
                if (dirty) { e.preventDefault(); e.returnValue = ''; }
            });
        });

        // Nút sao chép
        $$('[data-copy]').forEach(function (btn) {
            btn.onclick = function () {
                var txt = btn.getAttribute('data-copy');
                if (navigator.clipboard) navigator.clipboard.writeText(txt);
                LMS.toast('success', 'Đã sao chép: <b>' + txt + '</b>');
            };
        });

        // Chống bấm nộp hai lần
        $$('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[type=submit]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner"></span> Đang xử lý...';
                    setTimeout(function () { btn.disabled = false; }, 30000);
                }
            });
        });

        if (window.location.hash === '#success') LMS.confetti();
    });

    // ------------------------------------------------------ hàm phụ trợ
    LMS.humanSize = function (b) {
        var u = ['B', 'KB', 'MB', 'GB'], i = 0;
        while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
        return (i === 0 ? b : b.toFixed(1)) + ' ' + u[i];
    };

    LMS.noAccent = function (s) {
        return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D') : s;
    };

    // ------------------------------------------------------ đồng hồ làm bài
    LMS.startTimer = function (opts) {
        var el = document.getElementById(opts.el);
        if (!el) return;
        var remain = opts.seconds;
        var box = el.closest('.quiz-timer');
        var tick = function () {
            if (remain <= 0) {
                el.textContent = '00:00';
                if (opts.onExpire) opts.onExpire();
                return;
            }
            var h = Math.floor(remain / 3600), m = Math.floor((remain % 3600) / 60), s = remain % 60;
            el.textContent = (h > 0 ? (h < 10 ? '0' : '') + h + ':' : '')
                + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            if (box && remain <= 300) box.classList.add('warning');
            remain--;
            setTimeout(tick, 1000);
        };
        tick();
    };

    // ------------------------------------------------------ chấm bài bằng AI
    LMS.aiGrade = function (btn, url, submissionId) {
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> AI đang chấm bài...';
        var box = document.getElementById('ai-result-' + submissionId);
        if (box) {
            box.classList.remove('hidden');
            box.innerHTML = '<div class="ai-panel"><div class="ai-head"><span class="ai-avatar">🤖</span>'
                + '<div>Trợ lý AI đang đọc bài làm…<div class="small muted">Việc này có thể mất vài chục giây, vui lòng đừng đóng trang.</div></div></div>'
                + '<div class="skeleton" style="height:14px;margin:10px 0"></div>'
                + '<div class="skeleton" style="height:14px;width:82%;margin:10px 0"></div>'
                + '<div class="skeleton" style="height:14px;width:64%"></div></div>';
        }
        LMS.post(url, { id: submissionId }, function (res) {
            btn.disabled = false;
            btn.innerHTML = original;
            if (res.ok) {
                if (box) box.innerHTML = res.html;
                var input = document.getElementById('score-input-' + submissionId);
                if (input && res.score !== null && res.score !== undefined && input.value === '') input.value = res.score;
                LMS.toast('success', 'AI đã chấm xong bài làm.');
            } else {
                if (box) box.innerHTML = '<div class="alert alert-danger"><span>⚠️</span><div>' + (res.error || 'Lỗi không xác định') + '</div></div>';
                LMS.toast('danger', res.error || 'Chấm bài thất bại.');
            }
        }, function (err) {
            btn.disabled = false; btn.innerHTML = original;
            if (box) box.innerHTML = '<div class="alert alert-danger"><span>⚠️</span><div>' + err + '</div></div>';
        });
    };

    // ------------------------------------------------------ hộp thoại
    LMS.modal = function (title, bodyHtml, footHtml) {
        var bd = document.createElement('div');
        bd.className = 'modal-backdrop';
        bd.innerHTML = '<div class="modal"><div class="modal-head"><h3 style="margin:0">' + title + '</h3>'
            + '<button class="icon-btn" type="button" aria-label="Đóng">✕</button></div>'
            + '<div class="modal-body">' + bodyHtml + '</div>'
            + (footHtml ? '<div class="modal-foot">' + footHtml + '</div>' : '') + '</div>';
        bd.querySelector('.icon-btn').onclick = function () { bd.remove(); };
        bd.onclick = function (e) { if (e.target === bd) bd.remove(); };
        document.body.appendChild(bd);
        return bd;
    };
})();
