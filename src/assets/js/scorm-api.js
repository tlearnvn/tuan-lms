/* ============================================================
   Bộ điều hợp SCORM API (hỗ trợ SCORM 1.2 và SCORM 2004)
   Nội dung SCORM chạy trong iframe sẽ tìm window.parent.API / API_1484_11
   ============================================================ */
(function () {
    'use strict';

    var cfg = window.SCORM_CONFIG || {};
    var data = cfg.data || {};
    var dirty = {};
    var initialized = false;
    var terminated = false;
    var lastError = '0';
    var startTime = Date.now();

    function log(msg) { if (cfg.debug) console.log('[SCORM]', msg); }

    function setStatus(text, cls) {
        var el = document.getElementById('scorm-status');
        if (el) { el.textContent = text; el.className = 'chip ' + (cls || 'chip-blue'); }
    }

    function defaults() {
        var d = {
            'cmi.core.student_id': String(cfg.userId || ''),
            'cmi.core.student_name': cfg.userName || '',
            'cmi.core.lesson_status': 'not attempted',
            'cmi.core.lesson_location': '',
            'cmi.core.credit': 'credit',
            'cmi.core.entry': 'ab-initio',
            'cmi.core.score.raw': '',
            'cmi.core.score.min': '0',
            'cmi.core.score.max': '100',
            'cmi.core.total_time': '0000:00:00.00',
            'cmi.core.session_time': '0000:00:00.00',
            'cmi.core.exit': '',
            'cmi.core.lesson_mode': 'normal',
            'cmi.suspend_data': '',
            'cmi.launch_data': '',
            'cmi.comments': '',
            'cmi.student_data.mastery_score': '',
            // SCORM 2004
            'cmi.learner_id': String(cfg.userId || ''),
            'cmi.learner_name': cfg.userName || '',
            'cmi.completion_status': 'not attempted',
            'cmi.success_status': 'unknown',
            'cmi.location': '',
            'cmi.entry': 'ab-initio',
            'cmi.mode': 'normal',
            'cmi.credit': 'credit',
            'cmi.score.raw': '',
            'cmi.score.min': '0',
            'cmi.score.max': '100',
            'cmi.score.scaled': '',
            'cmi.total_time': 'PT0H0M0S',
            'cmi.session_time': 'PT0H0M0S',
            'cmi.exit': '',
            'cmi.progress_measure': '',
            'cmi.max_time_allowed': '',
            'cmi.time_limit_action': 'continue,no message'
        };
        for (var k in d) if (!(k in data)) data[k] = d[k];
        // Lần vào sau được đánh dấu là học tiếp
        if (data['cmi.core.lesson_status'] && data['cmi.core.lesson_status'] !== 'not attempted') {
            data['cmi.core.entry'] = 'resume';
            data['cmi.entry'] = 'resume';
        }
    }
    defaults();

    function commit(sync) {
        var payload = {};
        var has = false;
        for (var k in dirty) { payload[k] = data[k]; has = true; }
        // Luôn gửi kèm thời lượng phiên học
        payload['cmi.core.session_time'] = secondsToCmi12((Date.now() - startTime) / 1000);
        payload['cmi.session_time'] = secondsToCmi2004((Date.now() - startTime) / 1000);

        var body = new FormData();
        body.append('_csrf', cfg.csrf);
        body.append('item', cfg.itemId);
        body.append('sco', cfg.sco || 'default');
        body.append('data', JSON.stringify(payload));

        if (sync && navigator.sendBeacon) {
            navigator.sendBeacon(cfg.trackUrl, body);
            dirty = {};
            return true;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.trackUrl, !sync);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            dirty = {};
            setStatus('Đã lưu tiến độ ' + new Date().toLocaleTimeString('vi-VN'), 'chip-green');
            if (window.SCORM_ON_SAVE) window.SCORM_ON_SAVE(data);
        };
        xhr.onerror = function () { setStatus('Không lưu được tiến độ', 'chip-red'); };
        xhr.send(body);
        return true;
    }

    function secondsToCmi12(sec) {
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        function p(n, l) { n = String(Math.floor(n)); while (n.length < l) n = '0' + n; return n; }
        return p(h, 4) + ':' + p(m, 2) + ':' + p(s, 2) + '.' + p(Math.round((s % 1) * 100), 2);
    }
    function secondsToCmi2004(sec) {
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = Math.floor(sec % 60);
        return 'PT' + h + 'H' + m + 'M' + s + 'S';
    }

    // Ánh xạ tên phần tử giữa hai phiên bản để dữ liệu dùng chung
    var alias = {
        'cmi.core.lesson_status': 'cmi.completion_status',
        'cmi.core.score.raw': 'cmi.score.raw',
        'cmi.core.score.min': 'cmi.score.min',
        'cmi.core.score.max': 'cmi.score.max',
        'cmi.core.lesson_location': 'cmi.location',
        'cmi.core.student_id': 'cmi.learner_id',
        'cmi.core.student_name': 'cmi.learner_name'
    };

    function getValue(key) {
        lastError = '0';
        if (key in data) return String(data[key]);
        // Tương thích chéo
        for (var a in alias) {
            if (a === key && alias[a] in data) return String(data[alias[a]]);
            if (alias[a] === key && a in data) return String(data[a]);
        }
        if (/_children$/.test(key) || /_count$/.test(key)) return '';
        lastError = '401';
        return '';
    }

    function setValue(key, value) {
        lastError = '0';
        data[key] = String(value);
        dirty[key] = true;
        for (var a in alias) {
            if (a === key) { data[alias[a]] = String(value); dirty[alias[a]] = true; }
            else if (alias[a] === key) { data[a] = String(value); dirty[a] = true; }
        }
        log('SetValue ' + key + ' = ' + value);
        if (key.indexOf('status') !== -1 || key.indexOf('score') !== -1) {
            setStatus('Đang cập nhật…', 'chip-yellow');
        }
        return 'true';
    }

    // ---------------------------------------------------------- SCORM 1.2
    var API = {
        LMSInitialize: function () { initialized = true; setStatus('Đang học', 'chip-blue'); log('Initialize'); return 'true'; },
        LMSFinish: function () {
            if (terminated) return 'true';
            terminated = true;
            if (!data['cmi.core.exit']) setValue('cmi.core.exit', 'suspend');
            commit(false); log('Finish'); return 'true';
        },
        LMSGetValue: function (k) { return getValue(k); },
        LMSSetValue: function (k, v) { return setValue(k, v); },
        LMSCommit: function () { commit(false); return 'true'; },
        LMSGetLastError: function () { return lastError; },
        LMSGetErrorString: function (c) {
            var m = { '0': 'No error', '101': 'General exception', '401': 'Not implemented error', '403': 'Element is read only' };
            return m[c] || 'Unknown error';
        },
        LMSGetDiagnostic: function (c) { return c || ''; }
    };

    // ---------------------------------------------------------- SCORM 2004
    var API_1484_11 = {
        Initialize: function () { return API.LMSInitialize(); },
        Terminate: function () { return API.LMSFinish(); },
        GetValue: function (k) { return getValue(k); },
        SetValue: function (k, v) { return setValue(k, v); },
        Commit: function () { return API.LMSCommit(); },
        GetLastError: function () { return lastError; },
        GetErrorString: function (c) { return API.LMSGetErrorString(c); },
        GetDiagnostic: function (c) { return c || ''; }
    };

    window.API = API;
    window.API_1484_11 = API_1484_11;

    // Tự lưu định kỳ và khi rời trang
    setInterval(function () {
        for (var k in dirty) { commit(false); break; }
    }, 30000);

    window.addEventListener('beforeunload', function () {
        if (!terminated) { if (!data['cmi.core.exit']) data['cmi.core.exit'] = 'suspend'; commit(true); }
    });

    window.SCORM_COMMIT = function () { commit(false); };
    window.SCORM_DATA = data;
})();
