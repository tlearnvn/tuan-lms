/* ============================================================
   Thanh công cụ soạn thảo: định dạng, chèn ảnh minh hoạ,
   chèn công thức LaTeX và xem trước có render công thức.
   Gắn vào mọi <textarea data-editor>.
   ============================================================ */
(function () {
    'use strict';

    var TOOLS = [
        { t: 'B', title: 'In đậm', act: wrap('<b>', '</b>'), style: 'font-weight:800' },
        { t: 'I', title: 'In nghiêng', act: wrap('<i>', '</i>'), style: 'font-style:italic' },
        { t: 'U', title: 'Gạch chân', act: wrap('<u>', '</u>'), style: 'text-decoration:underline' },
        { t: 'H', title: 'Tiêu đề mục', act: wrap('<h3>', '</h3>') },
        { t: '•', title: 'Danh sách', act: insert('<ul>\n  <li>Ý thứ nhất</li>\n  <li>Ý thứ hai</li>\n</ul>\n') },
        { t: '1.', title: 'Danh sách đánh số', act: insert('<ol>\n  <li>Bước 1</li>\n  <li>Bước 2</li>\n</ol>\n') },
        { t: '❝', title: 'Trích dẫn', act: wrap('<blockquote>', '</blockquote>') },
        { t: '🔗', title: 'Chèn liên kết', act: link },
        { t: '🖼️', title: 'Chèn ảnh minh hoạ (tải lên)', act: image, cls: 'ed-primary' },
        { t: '🎞️', title: 'Chèn video / nhúng iframe', act: embed },
        { t: '▦', title: 'Chèn bảng', act: table },
        { t: '∑', title: 'Công thức trong dòng', act: mathInline, cls: 'ed-primary' },
        { t: '∫', title: 'Công thức riêng dòng', act: mathBlock, cls: 'ed-primary' },
        { t: 'ƒ', title: 'Mẫu công thức thường dùng', act: mathTemplates },
        { t: '</>', title: 'Khối mã', act: wrap('<pre><code>', '</code></pre>') },
        { t: '👁️', title: 'Xem trước', act: preview, cls: 'ed-preview' }
    ];

    function wrap(open, close) {
        return function (ta) {
            var s = ta.selectionStart, e = ta.selectionEnd;
            var sel = ta.value.substring(s, e) || 'nội dung';
            setValue(ta, ta.value.substring(0, s) + open + sel + close + ta.value.substring(e),
                     s + open.length, s + open.length + sel.length);
        };
    }

    function insert(text) {
        return function (ta) { insertAt(ta, text); };
    }

    function insertAt(ta, text) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        setValue(ta, ta.value.substring(0, s) + text + ta.value.substring(e), s + text.length, s + text.length);
    }

    function setValue(ta, val, selStart, selEnd) {
        ta.value = val;
        ta.focus();
        ta.setSelectionRange(selStart, selEnd === undefined ? selStart : selEnd);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function link(ta) {
        var url = window.prompt('Nhập địa chỉ liên kết:', 'https://');
        if (!url) return;
        var s = ta.selectionStart, e = ta.selectionEnd;
        var text = ta.value.substring(s, e) || window.prompt('Chữ hiển thị:', url) || url;
        insertAt(ta, '<a href="' + url + '" target="_blank" rel="noopener">' + text + '</a>');
    }

    function embed(ta) {
        var url = window.prompt('Dán liên kết YouTube / Vimeo hoặc mã nhúng iframe:', 'https://www.youtube.com/watch?v=');
        if (!url) return;
        var m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_\-]{6,})/);
        if (m) {
            insertAt(ta, '<div class="video-wrap"><iframe src="https://www.youtube.com/embed/' + m[1]
                + '" allowfullscreen loading="lazy"></iframe></div>\n');
            return;
        }
        var v = url.match(/vimeo\.com\/(\d+)/);
        if (v) {
            insertAt(ta, '<div class="video-wrap"><iframe src="https://player.vimeo.com/video/' + v[1]
                + '" allowfullscreen loading="lazy"></iframe></div>\n');
            return;
        }
        insertAt(ta, url.indexOf('<iframe') === 0 ? url + '\n'
            : '<div class="video-wrap"><iframe src="' + url + '" allowfullscreen loading="lazy"></iframe></div>\n');
    }

    function table(ta) {
        var cols = parseInt(window.prompt('Số cột:', '3'), 10) || 3;
        var rows = parseInt(window.prompt('Số dòng (không tính dòng tiêu đề):', '3'), 10) || 3;
        var h = '<table>\n  <thead>\n    <tr>';
        for (var c = 0; c < cols; c++) h += '<th>Cột ' + (c + 1) + '</th>';
        h += '</tr>\n  </thead>\n  <tbody>\n';
        for (var r = 0; r < rows; r++) {
            h += '    <tr>';
            for (var c2 = 0; c2 < cols; c2++) h += '<td>&nbsp;</td>';
            h += '</tr>\n';
        }
        h += '  </tbody>\n</table>\n';
        insertAt(ta, h);
    }

    function mathInline(ta) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.substring(s, e) || 'x^2 + y^2 = z^2';
        insertAt(ta, '\\(' + sel + '\\)');
    }

    function mathBlock(ta) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.substring(s, e) || '\\int_{a}^{b} f(x)\\,dx = F(b) - F(a)';
        insertAt(ta, '\n\\[\n' + sel + '\n\\]\n');
    }

    var TEMPLATES = [
        ['Phân số', '\\frac{a}{b}'],
        ['Căn bậc hai', '\\sqrt{x}'], ['Căn bậc n', '\\sqrt[n]{x}'],
        ['Luỹ thừa', 'x^{n}'], ['Chỉ số dưới', 'x_{i}'],
        ['Phương trình bậc hai', 'ax^2 + bx + c = 0'],
        ['Nghiệm bậc hai', 'x = \\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}'],
        ['Tổng sigma', '\\sum_{i=1}^{n} a_i'],
        ['Tích phân', '\\int_{a}^{b} f(x)\\,dx'],
        ['Giới hạn', '\\lim_{x \\to 0} \\frac{\\sin x}{x} = 1'],
        ['Đạo hàm', "f'(x) = \\frac{df}{dx}"],
        ['Ma trận 2×2', '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}'],
        ['Hệ phương trình', '\\begin{cases} x + y = 5 \\\\ 2x - y = 1 \\end{cases}'],
        ['Véc-tơ', '\\vec{AB}'],
        ['Góc & độ', '\\angle ABC = 60^\\circ'],
        ['Tam giác', '\\triangle ABC'],
        ['Thuộc / tập hợp', 'x \\in \\mathbb{R}, \\; A \\subset B'],
        ['Bất phương trình', 'a \\le x \\le b'],
        ['Hoá học – phản ứng', '\\mathrm{2H_2 + O_2 \\rightarrow 2H_2O}'],
        ['Vật lí – định luật II', 'F = m \\cdot a'],
        ['Xác suất', 'P(A \\cup B) = P(A) + P(B) - P(A \\cap B)']
    ];

    function mathTemplates(ta) {
        var html = '<p class="small muted">Bấm vào mẫu để chèn công thức vào vị trí con trỏ.</p>'
            + '<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px">';
        TEMPLATES.forEach(function (t, i) {
            html += '<button type="button" class="btn btn-ghost btn-sm ed-tpl" data-i="' + i + '" style="justify-content:flex-start">'
                + t[0] + '</button>';
        });
        html += '</div><div class="mt-3"><b class="small">Xem trước:</b><div id="ed-tpl-prev" class="card mt-1" '
            + 'style="min-height:70px;background:var(--bg-soft);box-shadow:none">Chọn một mẫu…</div></div>';
        var modal = LMS.modal('∑ Mẫu công thức LaTeX', html,
            '<button class="btn btn-ghost" type="button" onclick="this.closest(\'.modal-backdrop\').remove()">Đóng</button>');
        modal.querySelectorAll('.ed-tpl').forEach(function (btn) {
            btn.onclick = function () {
                var tpl = TEMPLATES[parseInt(btn.getAttribute('data-i'), 10)];
                var prev = modal.querySelector('#ed-tpl-prev');
                prev.innerHTML = '\\[' + tpl[1] + '\\]';
                if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([prev]);
                insertAt(ta, '\\(' + tpl[1] + '\\)');
                LMS.toast('success', 'Đã chèn công thức <b>' + tpl[0] + '</b>', 2500);
            };
        });
    }

    function image(ta) {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function () {
            if (!input.files || !input.files[0]) return;
            var fd = new FormData();
            fd.append('image', input.files[0]);
            LMS.toast('info', 'Đang tải ảnh lên…', 2000);
            LMS.post(LMS.urls.uploadImage, fd, function (res) {
                if (!res.ok) { LMS.toast('danger', res.error || 'Tải ảnh thất bại.'); return; }
                var alt = input.files[0].name.replace(/\.[^.]+$/, '').replace(/"/g, '');
                var width = window.prompt('Chiều rộng ảnh (px, để trống = tự động):', '');
                var style = width ? ' style="max-width:' + parseInt(width, 10) + 'px"' : '';
                insertAt(ta, '\n<figure><img src="' + res.url + '" alt="' + alt + '"' + style + ' loading="lazy">'
                    + '<figcaption>' + alt + '</figcaption></figure>\n');
                LMS.toast('success', 'Đã chèn ảnh vào nội dung 🎉');
            });
        };
        input.click();
    }

    function preview(ta) {
        var modal = LMS.modal('👁️ Xem trước nội dung',
            '<div class="rich-content" id="ed-preview-box">' + ta.value + '</div>',
            '<button class="btn btn-primary" type="button" onclick="this.closest(\'.modal-backdrop\').remove()">Đóng</button>');
        var box = modal.querySelector('#ed-preview-box');
        if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([box]);
    }

    function build(ta) {
        if (ta.dataset.edReady) return;
        ta.dataset.edReady = '1';

        var bar = document.createElement('div');
        bar.className = 'editor-bar';
        TOOLS.forEach(function (tool) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'editor-btn' + (tool.cls ? ' ' + tool.cls : '');
            b.title = tool.title;
            b.textContent = tool.t;
            if (tool.style) b.setAttribute('style', tool.style);
            b.onclick = function () { tool.act(ta); };
            bar.appendChild(b);
        });

        var hint = document.createElement('span');
        hint.className = 'editor-hint';
        hint.innerHTML = 'Hỗ trợ HTML cơ bản &amp; công thức LaTeX: <code>\\(a^2+b^2\\)</code> hoặc <code>$$...$$</code>';
        bar.appendChild(hint);

        ta.parentNode.insertBefore(bar, ta);
        ta.classList.add('editor-area');

        // Dán ảnh trực tiếp từ clipboard (ảnh chụp màn hình)
        ta.addEventListener('paste', function (ev) {
            if (!ev.clipboardData || !ev.clipboardData.items) return;
            for (var i = 0; i < ev.clipboardData.items.length; i++) {
                var it = ev.clipboardData.items[i];
                if (it.type && it.type.indexOf('image/') === 0) {
                    var file = it.getAsFile();
                    if (!file) continue;
                    ev.preventDefault();
                    var fd = new FormData();
                    fd.append('image', file, 'anh-dan-' + Date.now() + '.png');
                    LMS.toast('info', 'Đang tải ảnh vừa dán…', 2000);
                    LMS.post(LMS.urls.uploadImage, fd, function (res) {
                        if (!res.ok) { LMS.toast('danger', res.error || 'Tải ảnh thất bại.'); return; }
                        insertAt(ta, '\n<img src="' + res.url + '" alt="Hình minh hoạ" loading="lazy">\n');
                        LMS.toast('success', 'Đã chèn ảnh từ clipboard 🎉');
                    });
                    return;
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        LMS.$$('textarea[data-editor]').forEach(build);
    });
})();
