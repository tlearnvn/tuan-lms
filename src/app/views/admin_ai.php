<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/** Ưu tiên giá trị vừa nhập (khi bấm Kiểm tra kết nối), nếu không lấy từ cấu hình đã lưu */
$cfgv = function ($k, $d = null) use ($form) {
    if (isset($form[$k]) && $form[$k] !== '') return $form[$k];
    return Settings::get($k, $d);
};
$cfgb = function ($k, $d = false) use ($form) {
    if ($form) return !empty($form[$k]);
    return Settings::bool($k, $d);
};
?>
<div class="page-head flex-between flex-wrap">
    <div>
        <h1>🤖 Trợ lý AI chấm bài</h1>
        <div class="sub">Kết nối tới bất kỳ dịch vụ AI nào: OpenAI, Anthropic, Google Gemini hoặc API tự dựng.</div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/settings')) ?>">⚙️ Cấu hình chung</a>
    </div>
</div>

<?php if ($testResult !== null): ?>
    <?php if ($testResult['ok']): ?>
        <div class="alert alert-success"><span>✅</span><div>
            <b>Kết nối thành công!</b> (<?= (int)$testResult['duration'] ?> ms)<br>
            Mô hình trả lời: <i><?= e(str_limit($testResult['text'], 300)) ?></i>
        </div></div>
    <?php else: ?>
        <div class="alert alert-danger"><span>⚠️</span><div>
            <b>Kết nối thất bại.</b><br><?= e($testResult['error']) ?>
        </div></div>
    <?php endif; ?>
<?php endif; ?>

<form method="post" data-warn-unsaved>
    <?= csrf_field() ?>
    <div class="grid" style="grid-template-columns:1.4fr 1fr;align-items:start">
        <div>
            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🔌</span> Kết nối API</div>

                <label class="switch mb-3">
                    <input type="checkbox" name="ai_enabled" value="1" <?= $cfgb('ai_enabled') ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Bật trợ lý AI chấm bài</b>
                        <span>Giáo viên sẽ thấy nút "Nhờ AI chấm" ở màn hình chấm bài</span></span>
                </label>

                <div class="form-group">
                    <label>Nhà cung cấp</label>
                    <select name="ai_provider" id="ai_provider" onchange="fillEndpoint()">
                        <?php foreach (['openai' => 'OpenAI / API tương thích OpenAI (khuyên dùng)',
                                        'anthropic' => 'Anthropic (Claude)',
                                        'gemini' => 'Google Gemini',
                                        'custom' => 'Tuỳ chỉnh (định dạng OpenAI)'] as $k => $lb): ?>
                            <option value="<?= $k ?>" <?= $cfgv('ai_provider') === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Chọn "API tương thích OpenAI" nếu dùng OpenRouter, Groq, DeepSeek, Together, LM Studio, Ollama…</div>
                </div>

                <div class="form-group">
                    <label>Endpoint URL <span class="req">*</span></label>
                    <input type="url" name="ai_endpoint" id="ai_endpoint" value="<?= e($cfgv('ai_endpoint')) ?>"
                           placeholder="https://api.openai.com/v1/chat/completions">
                    <div class="form-hint">Với Gemini có thể dùng <code>{model}</code> trong URL, hệ thống sẽ tự thay bằng tên model.</div>
                </div>

                <div class="form-group">
                    <label>API Key / Token</label>
                    <input type="password" name="ai_api_key" autocomplete="new-password"
                           placeholder="<?= Settings::get('ai_api_key') ? '•••••••••• (đã lưu — để trống nếu không đổi)' : 'sk-...' ?>">
                    <?php if (Settings::get('ai_api_key')): ?>
                        <div class="check-row mt-1">
                            <input type="checkbox" id="clear_key" name="clear_key" value="1">
                            <label for="clear_key">Xoá API key đã lưu</label>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Tên model <span class="req">*</span></label>
                    <input type="text" name="ai_model" id="ai_model" value="<?= e($cfgv('ai_model')) ?>"
                           placeholder="gpt-4o-mini">
                    <div class="form-hint">Ví dụ: <code>gpt-4o-mini</code>, <code>claude-sonnet-4-5</code>,
                        <code>gemini-2.0-flash</code>, <code>deepseek-chat</code>…</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Token tối đa (max_tokens)</label>
                        <input type="number" name="ai_max_tokens" min="256" step="256" value="<?= (int)$cfgv('ai_max_tokens', 64000) ?>">
                        <div class="form-hint">Mặc định 64000.</div>
                    </div>
                    <div class="form-group">
                        <label>Timeout (giây)</label>
                        <input type="number" name="ai_timeout" min="10" max="3600" value="<?= (int)$cfgv('ai_timeout', 300) ?>">
                        <div class="form-hint">Mặc định 300 giây.</div>
                    </div>
                    <div class="form-group">
                        <label>Temperature</label>
                        <input type="number" name="ai_temperature" min="0" max="2" step="0.1" value="<?= e($cfgv('ai_temperature', '0.2')) ?>">
                        <div class="form-hint">Thấp = chấm ổn định.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Header HTTP bổ sung</label>
                    <textarea name="ai_extra_headers" rows="3" placeholder="Mỗi dòng một header, ví dụ:&#10;HTTP-Referer: https://truong.edu.vn&#10;X-Title: LMS"><?= e($cfgv('ai_extra_headers')) ?></textarea>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-title"><span class="emoji">🧭</span> Hướng dẫn chấm cho AI</div>
                <div class="form-group">
                    <label>Lời nhắc hệ thống (system prompt)</label>
                    <textarea name="ai_system_prompt" rows="6" data-autogrow><?= e($cfgv('ai_system_prompt')) ?></textarea>
                    <div class="form-hint">Đây là "tính cách" của trợ lý chấm bài. Tiêu chí riêng từng bài tập được cấu hình trong phần rubric của bài đó.</div>
                </div>
                <label class="switch mb-2">
                    <input type="checkbox" name="ai_vision" value="1" <?= $cfgb('ai_vision', true) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Gửi kèm ảnh bài làm cho AI</b>
                        <span>Dùng khi học sinh chụp ảnh bài viết tay (cần model hỗ trợ hình ảnh)</span></span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="ai_auto_default" value="1" <?= $cfgb('ai_auto_default') ? 'checked' : '' ?>>
                    <span class="track"></span>
                    <span class="switch-label"><b>Mặc định bật chấm tự động cho bài tập mới</b></span>
                </label>
            </div>

            <div class="card">
                <div class="btn-group">
                    <button class="btn btn-primary btn-lg" type="submit">💾 Lưu cấu hình</button>
                    <button class="btn btn-info btn-lg" type="submit" name="action" value="test">🔍 Kiểm tra kết nối</button>
                </div>
                <p class="small muted mt-2">Nút kiểm tra gửi một câu hỏi ngắn tới API bằng thông số đang nhập trên biểu mẫu.</p>
            </div>
        </div>

        <div>
            <div class="card mb-3 center">
                <img src="<?= e(asset('img/ai-robot.svg')) ?>" alt="" style="max-width:170px">
                <h3>AI chấm bài hoạt động thế nào?</h3>
                <ol class="small muted" style="text-align:left;padding-left:18px">
                    <li>Học sinh nộp bài (văn bản, tệp Word/PDF hoặc ảnh chụp).</li>
                    <li>Hệ thống trích nội dung và gửi kèm tiêu chí chấm tới API.</li>
                    <li>AI trả về JSON gồm điểm, nhận xét theo từng tiêu chí, điểm mạnh và điều cần cải thiện.</li>
                    <li>Giáo viên xem, điều chỉnh và quyết định điểm cuối cùng.</li>
                </ol>
            </div>

            <div class="card">
                <div class="card-title"><span class="emoji">📜</span> Lần chấm AI gần đây</div>
                <?php if (!$jobs): ?>
                    <p class="small muted">Chưa có lượt chấm nào.</p>
                <?php else: foreach ($jobs as $j): ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border)">
                        <div class="flex-between">
                            <b class="small"><?= e($j['full_name'] ?: 'Không rõ') ?></b>
                            <?= $j['status'] === 'done' ? chip('Thành công', 'chip-green') : ($j['status'] === 'error' ? chip('Lỗi', 'chip-red') : chip($j['status'], 'chip-yellow')) ?>
                        </div>
                        <div class="tiny muted"><?= e($j['model']) ?> · <?= (int)$j['duration'] ?> ms · <?= e(time_ago($j['created_at'])) ?></div>
                        <?php if ($j['error']): ?><div class="tiny text-danger"><?= e(str_limit($j['error'], 110)) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
var endpoints = {
    openai: 'https://api.openai.com/v1/chat/completions',
    anthropic: 'https://api.anthropic.com/v1/messages',
    gemini: 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
    custom: ''
};
var models = { openai: 'gpt-4o-mini', anthropic: 'claude-sonnet-4-5', gemini: 'gemini-2.0-flash', custom: '' };
function fillEndpoint() {
    var p = document.getElementById('ai_provider').value;
    var ep = document.getElementById('ai_endpoint');
    var md = document.getElementById('ai_model');
    var known = Object.keys(endpoints).map(function (k) { return endpoints[k]; });
    if (ep.value === '' || known.indexOf(ep.value) !== -1) ep.value = endpoints[p];
    var knownM = Object.keys(models).map(function (k) { return models[k]; });
    if (md.value === '' || knownM.indexOf(md.value) !== -1) md.value = models[p];
}
</script>
