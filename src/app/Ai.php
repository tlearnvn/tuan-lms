<?php
if (!defined('LMS_ENTRY')) { http_response_code(403); exit('Forbidden'); }
/**
 * Trợ lý AI chấm bài.
 * Toàn bộ thông số (nhà cung cấp, Endpoint URL, model, token, timeout...) do quản trị viên
 * cấu hình trong Quản trị → Cấu hình AI.
 */
class Ai
{
    public static function enabled() { return Settings::bool('ai_enabled'); }

    public static function config($override = [])
    {
        $c = [
            'provider'    => Settings::get('ai_provider', 'openai'),
            'endpoint'    => trim((string)Settings::get('ai_endpoint', '')),
            'api_key'     => trim((string)Settings::get('ai_api_key', '')),
            'model'       => trim((string)Settings::get('ai_model', '')),
            'max_tokens'  => Settings::int('ai_max_tokens', 64000),
            'timeout'     => Settings::int('ai_timeout', 300),
            'temperature' => (float)Settings::get('ai_temperature', '0.2'),
            'headers'     => (string)Settings::get('ai_extra_headers', ''),
            'system'      => (string)Settings::get('ai_system_prompt', ''),
            'vision'      => Settings::bool('ai_vision', true),
        ];
        foreach ($override as $k => $v) {
            if ($v !== null && $v !== '') $c[$k] = $v;
        }
        return $c;
    }

    /** Endpoint mặc định gợi ý theo nhà cung cấp */
    public static function defaultEndpoint($provider)
    {
        $m = [
            'openai'    => 'https://api.openai.com/v1/chat/completions',
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            'gemini'    => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            'custom'    => '',
        ];
        return isset($m[$provider]) ? $m[$provider] : '';
    }

    // ------------------------------------------------------------------ gọi API
    /**
     * Gửi yêu cầu tới mô hình.
     * @param string $prompt    nội dung người dùng
     * @param array  $images    [['mime'=>..,'data'=>binary], ...] ảnh bài làm (nếu mô hình hỗ trợ)
     * @return array ['ok'=>bool, 'text'=>string, 'error'=>string, 'raw'=>array, 'duration'=>int, 'request'=>array]
     */
    public static function chat($prompt, $images = [], $override = [])
    {
        $c = self::config($override);
        if ($c['endpoint'] === '') return ['ok' => false, 'error' => 'Chưa cấu hình Endpoint URL cho AI.', 'text' => ''];
        if ($c['model'] === '')    return ['ok' => false, 'error' => 'Chưa cấu hình tên model cho AI.', 'text' => ''];

        $endpoint = str_replace('{model}', rawurlencode($c['model']), $c['endpoint']);
        $headers = ['Content-Type: application/json'];
        $body = [];

        switch ($c['provider']) {
            case 'anthropic':
                $headers[] = 'x-api-key: ' . $c['api_key'];
                $headers[] = 'anthropic-version: 2023-06-01';
                $content = [];
                foreach ($images as $img) {
                    $content[] = ['type' => 'image', 'source' => [
                        'type' => 'base64', 'media_type' => $img['mime'], 'data' => base64_encode($img['data'])]];
                }
                $content[] = ['type' => 'text', 'text' => $prompt];
                $body = [
                    'model'      => $c['model'],
                    'max_tokens' => (int)$c['max_tokens'],
                    'temperature'=> (float)$c['temperature'],
                    'system'     => $c['system'],
                    'messages'   => [['role' => 'user', 'content' => $content]],
                ];
                break;

            case 'gemini':
                if ($c['api_key'] !== '') {
                    $endpoint .= (strpos($endpoint, '?') === false ? '?' : '&') . 'key=' . rawurlencode($c['api_key']);
                }
                $parts = [];
                foreach ($images as $img) {
                    $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => base64_encode($img['data'])]];
                }
                $parts[] = ['text' => $prompt];
                $body = [
                    'system_instruction' => ['parts' => [['text' => $c['system']]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => [
                        'maxOutputTokens' => (int)$c['max_tokens'],
                        'temperature'     => (float)$c['temperature'],
                    ],
                ];
                break;

            default: // openai và mọi API tương thích OpenAI
                if ($c['api_key'] !== '') $headers[] = 'Authorization: Bearer ' . $c['api_key'];
                $userContent = $prompt;
                if ($images) {
                    $userContent = [['type' => 'text', 'text' => $prompt]];
                    foreach ($images as $img) {
                        $userContent[] = ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:' . $img['mime'] . ';base64,' . base64_encode($img['data'])]];
                    }
                }
                $body = [
                    'model' => $c['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $c['system']],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'max_tokens'  => (int)$c['max_tokens'],
                    'temperature' => (float)$c['temperature'],
                ];
        }

        foreach (preg_split('/\r\n|\n/', trim($c['headers'])) as $h) {
            $h = trim($h);
            if ($h !== '' && strpos($h, ':') !== false) $headers[] = $h;
        }

        $res = self::request($endpoint, $headers, $body, $c['timeout']);

        // Một số model OpenAI mới yêu cầu max_completion_tokens thay cho max_tokens
        if (!$res['ok'] && $c['provider'] !== 'anthropic' && $c['provider'] !== 'gemini'
            && stripos($res['error'] . $res['body'], 'max_completion_tokens') !== false) {
            unset($body['max_tokens']);
            $body['max_completion_tokens'] = (int)$c['max_tokens'];
            unset($body['temperature']);
            $res = self::request($endpoint, $headers, $body, $c['timeout']);
        }

        $out = [
            'ok' => $res['ok'], 'error' => $res['error'], 'text' => '',
            'raw' => $res['json'], 'duration' => $res['duration'],
            'request' => ['endpoint' => $endpoint, 'model' => $c['model'], 'provider' => $c['provider'],
                          'max_tokens' => (int)$c['max_tokens'], 'timeout' => (int)$c['timeout']],
            'body' => $res['body'],
        ];
        if ($res['ok']) {
            $out['text'] = self::extractText($res['json'], $c['provider']);
            if ($out['text'] === '') {
                $out['ok'] = false;
                $out['error'] = 'Mô hình không trả về nội dung. Kiểm tra lại model hoặc giới hạn token.';
            }
        }
        return $out;
    }

    private static function request($url, $headers, $body, $timeout)
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $start = microtime(true);
        @set_time_limit($timeout + 60);

        if (!function_exists('curl_init')) {
            return self::requestFallback($url, $headers, $json, $timeout, $start);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => (int)$timeout,
            CURLOPT_CONNECTTIMEOUT => min(30, (int)$timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'LMS-PHP/1.0',
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $duration = (int)round((microtime(true) - $start) * 1000);

        if ($errno) {
            $msg = 'Lỗi kết nối (' . $errno . '): ' . $err;
            if ($errno === CURLE_OPERATION_TIMEDOUT) $msg = 'Quá thời gian chờ ' . $timeout . 's. Hãy tăng Timeout trong cấu hình AI.';
            return ['ok' => false, 'error' => $msg, 'json' => null, 'body' => '', 'duration' => $duration, 'code' => 0];
        }

        $data = json_decode((string)$raw, true);
        if ($code < 200 || $code >= 300) {
            $emsg = 'Máy chủ AI trả về mã ' . $code;
            if (is_array($data)) {
                if (isset($data['error']['message'])) $emsg .= ': ' . $data['error']['message'];
                elseif (isset($data['error']) && is_string($data['error'])) $emsg .= ': ' . $data['error'];
                elseif (isset($data['message'])) $emsg .= ': ' . $data['message'];
            } elseif (is_string($raw) && $raw !== '') {
                $emsg .= ': ' . mb_substr(strip_tags($raw), 0, 300, 'UTF-8');
            }
            return ['ok' => false, 'error' => $emsg, 'json' => $data, 'body' => (string)$raw, 'duration' => $duration, 'code' => $code];
        }
        return ['ok' => true, 'error' => '', 'json' => $data, 'body' => (string)$raw, 'duration' => $duration, 'code' => $code];
    }

    /** Dự phòng khi hosting không bật cURL */
    private static function requestFallback($url, $headers, $json, $timeout, $start)
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $json,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $duration = (int)round((microtime(true) - $start) * 1000);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'Không gửi được yêu cầu (hosting chặn kết nối ra ngoài?).',
                    'json' => null, 'body' => '', 'duration' => $duration, 'code' => 0];
        }
        $code = 200;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) $code = (int)$m[1];
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $emsg = 'Máy chủ AI trả về mã ' . $code;
            if (isset($data['error']['message'])) $emsg .= ': ' . $data['error']['message'];
            return ['ok' => false, 'error' => $emsg, 'json' => $data, 'body' => $raw, 'duration' => $duration, 'code' => $code];
        }
        return ['ok' => true, 'error' => '', 'json' => $data, 'body' => $raw, 'duration' => $duration, 'code' => $code];
    }

    private static function extractText($data, $provider)
    {
        if (!is_array($data)) return '';
        if ($provider === 'anthropic') {
            $t = '';
            foreach (arr_get($data, 'content', []) as $c) {
                if (isset($c['text'])) $t .= $c['text'];
            }
            return $t;
        }
        if ($provider === 'gemini') {
            $t = '';
            foreach (arr_get($data, 'candidates', []) as $cand) {
                foreach (arr_get(arr_get($cand, 'content', []), 'parts', []) as $p) {
                    if (isset($p['text'])) $t .= $p['text'];
                }
            }
            return $t;
        }
        // OpenAI & tương thích
        if (isset($data['choices'][0]['message']['content'])) {
            $c = $data['choices'][0]['message']['content'];
            if (is_array($c)) {
                $t = '';
                foreach ($c as $p) { if (isset($p['text'])) $t .= $p['text']; }
                return $t;
            }
            return (string)$c;
        }
        if (isset($data['choices'][0]['text'])) return (string)$data['choices'][0]['text'];
        // Một số API tự xây dựng
        foreach (['output_text', 'text', 'result', 'answer'] as $k) {
            if (isset($data[$k]) && is_string($data[$k])) return $data[$k];
        }
        if (isset($data['content']) && is_string($data['content'])) return $data['content'];
        return '';
    }

    // ------------------------------------------------------------------ chấm bài
    /** Kiểm tra kết nối từ trang cấu hình */
    public static function testConnection($override = [])
    {
        $r = self::chat("Bạn hãy trả lời đúng một dòng: \"Kết nối thành công\". Không thêm gì khác.", [], $override);
        return $r;
    }

    /**
     * Chấm một bài nộp.
     * @return array ['ok','score','feedback','raw','error']
     */
    public static function gradeSubmission($submissionId)
    {
        $sub = DB::row('SELECT * FROM {P}submissions WHERE id = :id', ['id' => (int)$submissionId]);
        if (!$sub) return ['ok' => false, 'error' => 'Không tìm thấy bài nộp.'];
        $item = DB::row('SELECT * FROM {P}items WHERE id = :id', ['id' => $sub['item_id']]);
        $asg  = DB::row('SELECT * FROM {P}assignments WHERE item_id = :id', ['id' => $sub['item_id']]);
        $stu  = DB::row('SELECT full_name FROM {P}users WHERE id = :id', ['id' => $sub['user_id']]);
        if (!$item) return ['ok' => false, 'error' => 'Không tìm thấy bài tập.'];

        $maxPoints = (float)$item['max_points'];
        $jobId = DB::insert('ai_jobs', [
            'submission_id' => $sub['id'],
            'user_id'       => $sub['user_id'],
            'status'        => 'running',
            'provider'      => Settings::get('ai_provider'),
            'model'         => Settings::get('ai_model'),
            'created_at'    => now(),
        ]);
        DB::update('submissions', ['ai_status' => 'running'], 'id = :id', ['id' => $sub['id']]);

        // Nội dung bài làm
        $parts = [];
        if (trim((string)$sub['content']) !== '') {
            $parts[] = "### Bài làm dạng văn bản của học sinh\n" . trim(strip_tags($sub['content'], '<br><p>'));
        }
        $images = [];
        $files = DB::all('SELECT f.* FROM {P}submission_files sf JOIN {P}files f ON f.id = sf.file_id
                          WHERE sf.submission_id = :s', ['s' => $sub['id']]);
        $useVision = Settings::bool('ai_vision', true);
        foreach ($files as $f) {
            if (is_image_ext($f['ext']) && $useVision && count($images) < 6 && (int)$f['size'] < 8 * 1024 * 1024) {
                $images[] = ['mime' => (strpos($f['mime'], 'image/') === 0 ? $f['mime'] : 'image/jpeg'),
                             'data' => Storage::read($f['id'])];
                $parts[] = "### Tệp ảnh bài làm: " . $f['name'] . " (đính kèm bên dưới)";
                continue;
            }
            $text = Storage::extractText($f);
            if (trim($text) !== '') {
                $parts[] = "### Nội dung tệp \"" . $f['name'] . "\"\n" . $text;
            } else {
                $parts[] = "### Tệp đính kèm không đọc được nội dung: " . $f['name'] . " (" . human_size($f['size']) . ")";
            }
        }
        if (!$parts) return self::finishJob($jobId, $sub['id'], false, 'Bài nộp không có nội dung để chấm.', null, null);

        $rubric = $asg && trim((string)$asg['ai_rubric']) !== ''
            ? trim($asg['ai_rubric'])
            : "Chấm theo mức độ hoàn thành yêu cầu, tính chính xác, cách lập luận/trình bày và sự sáng tạo.";
        $instructions = $asg ? trim(strip_tags((string)$asg['instructions'])) : '';

        $prompt = "Bạn hãy chấm bài tập sau của học sinh.\n\n"
            . "## Thông tin bài tập\n"
            . "- Tên bài: " . $item['title'] . "\n"
            . "- Thang điểm tối đa: " . score_fmt($maxPoints) . " điểm\n"
            . ($instructions !== '' ? "- Yêu cầu của giáo viên: " . mb_substr($instructions, 0, 4000, 'UTF-8') . "\n" : '')
            . "- Tiêu chí chấm (rubric):\n" . mb_substr($rubric, 0, 6000, 'UTF-8') . "\n\n"
            . "## Bài làm của học sinh" . ($stu ? " (" . $stu['full_name'] . ")" : '') . "\n"
            . implode("\n\n", $parts) . "\n\n"
            . "## Yêu cầu đầu ra\n"
            . "Chỉ trả về DUY NHẤT một đối tượng JSON hợp lệ (không kèm giải thích, không rào ```), theo đúng cấu trúc:\n"
            . "{\n"
            . "  \"score\": <số điểm, từ 0 đến " . score_fmt($maxPoints) . ">,\n"
            . "  \"max_score\": " . score_fmt($maxPoints) . ",\n"
            . "  \"summary\": \"<nhận xét tổng quan 2-3 câu, tiếng Việt>\",\n"
            . "  \"criteria\": [ { \"name\": \"<tiêu chí>\", \"score\": <điểm>, \"max\": <điểm tối đa>, \"comment\": \"<nhận xét>\" } ],\n"
            . "  \"strengths\": [\"<điểm mạnh>\"],\n"
            . "  \"improvements\": [\"<điều cần cải thiện, có hướng dẫn cụ thể>\"]\n"
            . "}";

        $res = self::chat($prompt, $images);
        if (!$res['ok']) {
            return self::finishJob($jobId, $sub['id'], false, $res['error'], null, null, $res);
        }

        $parsed = self::parseJson($res['text']);
        $score = null;
        if (is_array($parsed) && isset($parsed['score']) && is_numeric($parsed['score'])) {
            $score = max(0, min($maxPoints, (float)$parsed['score']));
        }
        $feedback = self::formatFeedback($parsed, $res['text'], $maxPoints);

        return self::finishJob($jobId, $sub['id'], true, '', $score, $feedback, $res);
    }

    private static function finishJob($jobId, $subId, $ok, $error, $score, $feedback, $res = null)
    {
        DB::update('ai_jobs', [
            'status'      => $ok ? 'done' : 'error',
            'response'    => $res ? mb_substr((string)$res['text'], 0, 200000, 'UTF-8') : null,
            'request'     => $res ? json_encode($res['request'], JSON_UNESCAPED_UNICODE) : null,
            'error'       => $error ?: null,
            'duration'    => $res ? (int)$res['duration'] : 0,
            'finished_at' => now(),
        ], 'id = :id', ['id' => $jobId]);

        DB::update('submissions', [
            'ai_status'   => $ok ? 'done' : 'error',
            'ai_score'    => $score,
            'ai_feedback' => $feedback,
            'ai_raw'      => $res ? mb_substr((string)$res['text'], 0, 200000, 'UTF-8') : $error,
            'ai_at'       => now(),
        ], 'id = :id', ['id' => $subId]);

        return ['ok' => $ok, 'error' => $error, 'score' => $score, 'feedback' => $feedback,
                'raw' => $res ? $res['text'] : ''];
    }

    /** Tách JSON khỏi câu trả lời (kể cả khi bị bọc trong ```json ... ```) */
    public static function parseJson($text)
    {
        $t = trim((string)$text);
        if ($t === '') return null;
        if (preg_match('/```(?:json)?\s*(.+?)```/s', $t, $m)) $t = trim($m[1]);
        $d = json_decode($t, true);
        if (is_array($d)) return $d;
        $start = strpos($t, '{');
        $end = strrpos($t, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $d = json_decode(substr($t, $start, $end - $start + 1), true);
            if (is_array($d)) return $d;
        }
        return null;
    }

    /** Dựng phần nhận xét dạng HTML gọn gàng cho giáo viên và học sinh */
    public static function formatFeedback($parsed, $rawText, $maxPoints)
    {
        if (!is_array($parsed)) {
            return '<p>' . nl2br(e(mb_substr(trim((string)$rawText), 0, 20000, 'UTF-8'))) . '</p>';
        }
        $h = '';
        if (!empty($parsed['summary'])) {
            $h .= '<p class="ai-summary">' . nl2br(e($parsed['summary'])) . '</p>';
        }
        if (!empty($parsed['criteria']) && is_array($parsed['criteria'])) {
            $h .= '<table class="ai-criteria"><thead><tr><th>Tiêu chí</th><th>Điểm</th><th>Nhận xét</th></tr></thead><tbody>';
            foreach ($parsed['criteria'] as $c) {
                if (!is_array($c)) continue;
                $h .= '<tr><td><b>' . e(arr_get($c, 'name', '')) . '</b></td>'
                    . '<td class="center">' . e(score_fmt(arr_get($c, 'score', ''))) . '/' . e(score_fmt(arr_get($c, 'max', ''))) . '</td>'
                    . '<td>' . nl2br(e(arr_get($c, 'comment', ''))) . '</td></tr>';
            }
            $h .= '</tbody></table>';
        }
        if (!empty($parsed['strengths']) && is_array($parsed['strengths'])) {
            $h .= '<div class="ai-block ai-good"><b>👍 Điểm mạnh</b><ul>';
            foreach ($parsed['strengths'] as $s) $h .= '<li>' . e($s) . '</li>';
            $h .= '</ul></div>';
        }
        if (!empty($parsed['improvements']) && is_array($parsed['improvements'])) {
            $h .= '<div class="ai-block ai-improve"><b>🎯 Cần cải thiện</b><ul>';
            foreach ($parsed['improvements'] as $s) $h .= '<li>' . e($s) . '</li>';
            $h .= '</ul></div>';
        }
        if ($h === '') $h = '<p>' . nl2br(e(mb_substr(trim((string)$rawText), 0, 20000, 'UTF-8'))) . '</p>';
        return $h;
    }

    /** Chấm một câu tự luận trong bài trắc nghiệm */
    public static function gradeEssayAnswer($question, $answerText, $maxPoints)
    {
        $prompt = "Chấm câu trả lời tự luận sau của học sinh.\n\n"
            . "## Câu hỏi\n" . strip_tags((string)$question['content']) . "\n\n"
            . ($question['answer_key'] ? "## Đáp án/gợi ý của giáo viên\n" . $question['answer_key'] . "\n\n" : '')
            . "## Bài làm của học sinh\n" . mb_substr(strip_tags((string)$answerText), 0, 20000, 'UTF-8') . "\n\n"
            . "Điểm tối đa của câu này: " . score_fmt($maxPoints) . ".\n"
            . "Chỉ trả về JSON: {\"score\": <số>, \"comment\": \"<nhận xét ngắn bằng tiếng Việt>\"}";
        $res = self::chat($prompt);
        if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];
        $p = self::parseJson($res['text']);
        $score = (is_array($p) && isset($p['score']) && is_numeric($p['score']))
            ? max(0, min((float)$maxPoints, (float)$p['score'])) : null;
        return ['ok' => true, 'score' => $score,
                'comment' => is_array($p) ? arr_get($p, 'comment', '') : mb_substr($res['text'], 0, 2000, 'UTF-8')];
    }
}
