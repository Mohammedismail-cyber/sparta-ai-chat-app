<?php
/**
 * Connector for a locally-running model server.
 * Picks a model automatically from the user's prompt — nothing
 * about the runtime is shown in the UI.
 */

require_once __DIR__ . '/config.php';

class LlmException extends Exception {}

function llm_candidate_bases(): array
{
    $bases = [rtrim(LLM_BASE_URL, '/')];
    if (str_contains(LLM_BASE_URL, '127.0.0.1')) {
        $bases[] = str_replace('127.0.0.1', 'localhost', rtrim(LLM_BASE_URL, '/'));
    } elseif (str_contains(LLM_BASE_URL, 'localhost')) {
        $bases[] = str_replace('localhost', '127.0.0.1', rtrim(LLM_BASE_URL, '/'));
    }
    return array_values(array_unique($bases));
}

/**
 * @return array{ok:bool, code:int, body:string, json:?array, error:?string}
 */
function llm_http(string $url, ?array $payload = null, int $timeout = 8): array
{
        $timeout = max(2, min($timeout, 360));
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_NOSIGNAL       => true,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = is_string($body) ? json_decode($body, true) : null;

    return [
        'ok'    => $errno === 0 && $code > 0 && $code < 400,
        'code'  => $code,
        'body'  => is_string($body) ? $body : '',
        'json'  => is_array($json) ? $json : null,
        'error' => $errno ? $error : null,
        'errno' => $errno,
    ];
}

function llm_http_retry(string $path, ?array $payload, int $timeout): array
{
    $bases = llm_candidate_bases();
    $last = ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => 'No endpoint tried', 'errno' => 0];
    foreach ($bases as $i => $base) {
        $last = llm_http($base . $path, $payload, $timeout);
        if ($last['ok'] || ($last['code'] > 0 && $last['code'] < 500)) {
            return $last;
        }
        // Timeouts must not be retried — a second wait blows past PHP's limit.
        if (!empty($last['errno']) && (int) $last['errno'] === CURLE_OPERATION_TIMEDOUT) {
            return $last;
        }
        if ($i === 0 && $last['code'] === 0) {
            continue;
        }
        return $last;
    }
    return $last;
}

function parse_param_billions(?string $size): float
{
    if (!$size) {
        return 0.0;
    }
    if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*([kmb])?/i', $size, $m)) {
        $n = (float) $m[1];
        $unit = strtolower($m[2] ?? 'b');
        if ($unit === 'k' || $unit === 'm') {
            return $n / 1000;
        }
        return $n;
    }
    return 0.0;
}

function model_is_vision(string $name): bool
{
    $n = strtolower($name);
    foreach (['vision', 'llava', 'minicpm-v', 'moondream', 'bakllava', 'qwen2-vl', 'qwen2.5vl', 'pixtral'] as $hint) {
        if (str_contains($n, $hint)) {
            return true;
        }
    }
    return false;
}

function model_is_coder(string $name): bool
{
    $n = strtolower($name);
    return str_contains($n, 'coder') || str_contains($n, 'code');
}

function model_is_image_gen(string $name): bool
{
    $n = strtolower($name);
    foreach (['flux', 'z-image', 'zimage', 'sdxl', 'stable-diffusion', 'stablediffusion', 'imagegen', 'x/z-image', 'x/flux'] as $hint) {
        if (str_contains($n, $hint)) {
            return true;
        }
    }
    return false;
}

function first_installed_strict(array $models, array $wanted): ?string
{
    $names = array_column($models, 'name');
    foreach ($wanted as $needle) {
        foreach ($names as $name) {
            if ($name === $needle || str_starts_with($name, $needle)) {
                return $name;
            }
            if (!str_contains($needle, ':') && str_contains(strtolower($name), strtolower($needle))) {
                return $name;
            }
        }
    }
    return null;
}

function specialist_wanted(string $job): array
{
    return match ($job) {
        'web', 'make' => ['qwen2.5-coder:7b', 'qwen2.5-coder:3b', 'qwen2.5-coder'],
        'mobile'      => ['llama3.1:8b', 'llama3.1', 'hermes3:8b', 'hermes3'],
        'write'       => ['deepseek-r1:8b', 'deepseek-r1', 'hermes3:8b'],
        'slides'      => ['qwen2.5:7b', 'qwen2.5:7b-instruct', 'qwen2.5:3b'],
        'code'        => ['qwen2.5-coder:7b', 'qwen2.5-coder:3b', 'qwen2.5-coder'],
        'image'       => ['x/flux2-klein', 'flux2-klein', 'x/z-image-turbo', 'z-image', 'flux', 'sdxl', 'stable-diffusion'],
        'think', 'strong' => ['deepseek-r1:8b', 'deepseek-r1', 'hermes3:8b'],
        default       => ['llama3.2:3b', 'llama3.2:1b'],
    };
}

/**
 * @return array{name:string, vision:bool, coder:bool, online:bool, models:array, billions:float, kind:string, image:bool}
 */
function route_specialist(string $job): array
{
    $models = LLM_AUTO_DISCOVER ? discover_local_models() : [];
    $online = $models !== [];
    $job = $job === 'make' ? 'web' : $job;

    $chosen = null;
    if ($job === 'image') {
        $chosen = first_installed_strict($models, specialist_wanted('image'));
        if (!$chosen) {
            foreach ($models as $m) {
                if (model_is_image_gen($m['name'])) {
                    $chosen = $m['name'];
                    break;
                }
            }
        }
    } else {
        $chosen = first_installed_strict($models, specialist_wanted($job));
        if (!$chosen && in_array($job, ['fast'], true)) {
            $chosen = LLM_MODEL ?: 'llama3.2:3b';
        }
    }

    $meta = [
        'name'     => $chosen ?: '',
        'vision'   => $chosen ? model_is_vision($chosen) : false,
        'coder'    => $job === 'web' || $job === 'code' || $job === 'mobile' || ($chosen && model_is_coder($chosen)),
        'online'   => $online,
        'models'   => $models,
        'billions' => 0.0,
        'kind'     => $job,
        'image'    => $chosen ? model_is_image_gen($chosen) : false,
    ];
    foreach ($models as $m) {
        if ($m['name'] === $chosen) {
            $meta['vision'] = $m['vision'];
            $meta['billions'] = $m['billions'];
            $meta['coder'] = $meta['coder'] || $m['coder'];
            break;
        }
    }
    return $meta;
}

/**
 * @return array<int, array{name:string, size:int, parameter_size:string, billions:float, family:string, vision:bool, coder:bool, provider:string}>
 */
function discover_local_models(bool $fresh = false): array
{
    static $memo = null;
    if ($fresh) {
        $memo = null;
        $cacheFile = dirname(DB_PATH) . '/models.cache.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }
    if (is_array($memo)) {
        return $memo;
    }

    $cacheFile = dirname(DB_PATH) . '/models.cache.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 120) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $memo = $cached;
            return $memo;
        }
    }

    $found = [];
    $tags = llm_http_retry('/api/tags', null, 4);
    if ($tags['ok'] && isset($tags['json']['models']) && is_array($tags['json']['models'])) {
        foreach ($tags['json']['models'] as $m) {
            $name = (string) ($m['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $details = is_array($m['details'] ?? null) ? $m['details'] : [];
            $param = (string) ($details['parameter_size'] ?? '');
            $found[$name] = [
                'name'           => $name,
                'size'           => (int) ($m['size'] ?? 0),
                'parameter_size' => $param,
                'billions'       => parse_param_billions($param),
                'family'         => (string) ($details['family'] ?? ''),
                'vision'         => model_is_vision($name),
                'coder'          => model_is_coder($name),
                'provider'       => 'ollama',
            ];
        }
    }

    $models = array_values($found);
    usort($models, function ($a, $b) {
        if ($a['billions'] !== $b['billions']) {
            return $a['billions'] <=> $b['billions'];
        }
        return $a['size'] <=> $b['size'];
    });

    @file_put_contents($cacheFile, json_encode($models));
    $memo = $models;
    return $models;
}

function invalidate_model_cache(): void
{
    discover_local_models(true);
}

function ollama_image_model_name(): string
{
    return defined('LLM_IMAGE_MODEL') ? LLM_IMAGE_MODEL : 'x/flux2-klein';
}

function ollama_image_blocked_file(): string
{
    return dirname(DB_PATH) . '/image-runtime.fail';
}

function ollama_pull_now(string $model, int $timeout = 25): bool
{
    $model = preg_replace('/[^A-Za-z0-9_.:\/-]/', '', $model) ?? '';
    if ($model === '') {
        return false;
    }
    $res = llm_http_retry('/api/pull', ['name' => $model, 'stream' => false], $timeout);
    $blob = ($res['body'] ?? '') . ' ' . ($res['error'] ?? '');
    if (stripos($blob, 'MLX') !== false) {
        @file_put_contents(ollama_image_blocked_file(), 'mlx');
    }
    return $res['ok'];
}

function ollama_pull_in_background(string $model): void
{
    $model = preg_replace('/[^A-Za-z0-9_.:\/-]/', '', $model) ?? '';
    if ($model === '') {
        return;
    }
    $flag = dirname(DB_PATH) . '/pull-' . md5($model) . '.flag';
    if (is_file($flag) && (time() - filemtime($flag)) < 7200) {
        return;
    }
    @file_put_contents($flag, $model . "\n" . date('c'));
    $log = dirname(DB_PATH) . '/pull-' . md5($model) . '.log';
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'start /MIN /B ollama pull ' . $model;
        pclose(popen($cmd, 'r'));
        return;
    }
    exec('nohup ollama pull ' . escapeshellarg($model) . ' > ' . escapeshellarg($log) . ' 2>&1 &');
}

function ollama_supports_image_gen(): bool
{
    $ver = llm_http_retry('/api/version', null, 3);
    $v = (string) ($ver['json']['version'] ?? '');
    if ($v === '') {
        return false;
    }
    // Experimental image gen was removed in 0.32.6; Windows never had a working MLX runner.
    if (version_compare($v, '0.32.6', '>=')) {
        return false;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        return false;
    }
    return version_compare($v, '0.14.0', '>=');
}

function ensure_image_model(): string
{
    $meta = route_specialist('image');
    if ($meta['name'] !== '' && !empty($meta['image'])) {
        return $meta['name'];
    }
    // Chat models must never be used for pictures. If Ollama cannot generate
    // images, the Python image backend in tools/image_gen.py is used instead.
    if (!ollama_supports_image_gen()) {
        return '';
    }
    if (is_file(ollama_image_blocked_file()) && (time() - filemtime(ollama_image_blocked_file())) < 86400) {
        return '';
    }
    $want = ollama_image_model_name();
    if (ollama_pull_now($want, 30)) {
        invalidate_model_cache();
        $meta = route_specialist('image');
        if ($meta['name'] !== '' && !empty($meta['image'])) {
            return $meta['name'];
        }
    }
    ollama_pull_in_background($want);
    return '';
}

function first_installed(array $models, array $wanted): ?string
{
    $names = array_column($models, 'name');
    foreach ($wanted as $needle) {
        foreach ($names as $name) {
            if ($name === $needle || str_starts_with($name, $needle) || str_contains(strtolower($name), strtolower($needle))) {
                return $name;
            }
        }
    }
    return $names[0] ?? null;
}

function classify_prompt(string $text, bool $hasImages): string
{
    if ($hasImages) {
        return 'vision';
    }
    $t = strtolower($text);

    $codeHints = [
        '```', 'function ', 'def ', 'class ', 'debug', 'stack trace', 'compile error',
        'typescript', 'javascript', 'python', 'refactor', 'stacktrace',
        'npm ', 'git ', 'dockerfile', 'sql ', 'regex', 'traceback', 'undefined',
        'syntax error', 'write a script', 'write code',
    ];
    foreach ($codeHints as $h) {
        if (str_contains($t, $h)) {
            return 'code';
        }
    }

    $strongHints = [
        'explain in detail', 'analyze', 'compare and', 'step by step',
        'architecture', 'reason about', 'prove', 'critique', 'deep dive',
    ];
    foreach ($strongHints as $h) {
        if (str_contains($t, $h)) {
            return 'strong';
        }
    }

    if (mb_strlen($text) > 40 || preg_match('/\b(why|how|what|explain|plan|write|list|compare|because)\b/i', $text)) {
        return 'strong';
    }
    return 'fast';
}

/**
 * @return array{name:string, vision:bool, coder:bool, online:bool, models:array, billions:float, kind:string}
 */
function route_model_for_prompt(string $text, bool $hasImages = false): array
{
    $models = LLM_AUTO_DISCOVER ? discover_local_models() : [];
    $online = $models !== [];
    $kind = classify_prompt($text, $hasImages);

    $wanted = [
        'vision' => ['llava', 'vision', 'moondream'],
        'code'   => specialist_wanted('code'),
        'web'    => specialist_wanted('web'),
        'write'  => specialist_wanted('write'),
        'make'   => specialist_wanted('web'),
        'image'  => specialist_wanted('image'),
        'think'  => specialist_wanted('think'),
        'strong' => specialist_wanted('strong'),
        'fast'   => ['llama3.2:3b', 'qwen2.5:3b', 'llama3.2:1b'],
    ];

    $list = $wanted[$kind] ?? $wanted['fast'];
    $chosen = first_installed_strict($models, $list);
    if (!$chosen && $kind === 'fast') {
        $chosen = first_installed($models, $list) ?: (LLM_MODEL ?: 'llama3.2:3b');
    }
    if (!$chosen) {
        $chosen = $kind === 'fast' ? (LLM_MODEL ?: 'llama3.2:3b') : '';
    }

    $meta = [
        'name'     => $chosen,
        'vision'   => model_is_vision($chosen),
        'coder'    => $kind === 'code' || model_is_coder($chosen),
        'online'   => $online,
        'models'   => $models,
        'billions' => 0.0,
        'kind'     => $kind,
    ];
    foreach ($models as $m) {
        if ($m['name'] === $chosen) {
            $meta['vision'] = $m['vision'];
            $meta['billions'] = $m['billions'];
            break;
        }
    }
    return $meta;
}

function speed_options(array $meta): array
{
    $kind = $meta['kind'] ?? 'fast';
    if ($kind === 'web' || $kind === 'make' || $kind === 'code' || $kind === 'mobile') {
        return [
            'temperature' => 0.35,
            'top_p'       => 0.9,
            'num_ctx'     => 4096,
            'num_predict' => 3200,
        ];
    }
    if ($kind === 'write' || $kind === 'slides') {
        return [
            'temperature' => 0.55,
            'top_p'       => 0.9,
            'num_ctx'     => 4096,
            'num_predict' => $kind === 'slides' ? 2800 : 2500,
        ];
    }
    if ($kind === 'think') {
        return [
            'temperature' => 0.5,
            'top_p'       => 0.9,
            'num_ctx'     => 4096,
            'num_predict' => 400,
        ];
    }
    if ($kind === 'strong') {
        return [
            'temperature' => 0.65,
            'top_p'       => 0.9,
            'num_ctx'     => 4096,
            'num_predict' => 700,
        ];
    }
    return [
        'temperature' => 0.65,
        'top_p'       => 0.9,
        'num_ctx'     => 2048,
        'num_predict' => 450,
    ];
}

function system_prompt_for_model(array $meta): string
{
    if (!empty($meta['coder']) || ($meta['kind'] ?? '') === 'code') {
        return 'Think first. Restate the coding task, list the files or steps, then write complete working code. Finish every block.';
    }
    return LLM_SYSTEM_PROMPT;
}

function generate_plain(string $prompt, array $options = [], string $kind = 'fast'): string
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(360);
    }
    $specialistJobs = ['web', 'write', 'code', 'make', 'image', 'mobile', 'slides'];
    if (in_array($kind, $specialistJobs, true) || $kind === 'think' || $kind === 'strong') {
        $meta = route_specialist($kind === 'make' ? 'web' : $kind);
        if ($kind === 'image' && (($meta['name'] ?? '') === '' || empty($meta['image']))) {
            throw new LlmException('No image model is available for that job.');
        }
        if (($meta['name'] ?? '') === '') {
            if (in_array($kind, ['web', 'mobile', 'write', 'slides', 'code', 'make'], true)) {
                throw new LlmException('No specialist is available for that job.');
            }
            $meta = route_model_for_prompt($prompt, false);
            $meta['kind'] = $kind;
        }
    } else {
        $meta = route_model_for_prompt($prompt, false);
    }
    if (!$meta['name']) {
        throw new LlmException('No local model is available for that job.');
    }
    $specialistKeep = defined('LLM_KEEP_ALIVE') ? LLM_KEEP_ALIVE : '0';
    $fastKeep = defined('LLM_FAST_KEEP_ALIVE') ? LLM_FAST_KEEP_ALIVE : '60m';
    $keep = in_array($kind, $specialistJobs, true) || $kind === 'think' || $kind === 'strong'
        ? ($kind === 'think' ? '30s' : $specialistKeep)
        : $fastKeep;
    $payload = [
        'model'      => $meta['name'],
        'prompt'     => $prompt,
        'stream'     => false,
        'keep_alive' => $keep,
        'options'    => array_merge(speed_options($meta), $options),
    ];
    if (str_contains(strtolower((string) $meta['name']), 'deepseek-r1') && in_array($kind, $specialistJobs, true)) {
        $payload['think'] = false;
    }
    if (in_array($kind, $specialistJobs, true)) {
        $timeout = defined('LLM_SPECIALIST_TIMEOUT_SECONDS') ? LLM_SPECIALIST_TIMEOUT_SECONDS : LLM_MAKE_TIMEOUT_SECONDS;
    } elseif ($kind === 'think' || $kind === 'strong') {
        $timeout = defined('LLM_THINK_TIMEOUT_SECONDS') ? LLM_THINK_TIMEOUT_SECONDS : 90;
    } else {
        $timeout = LLM_TIMEOUT_SECONDS;
    }
    $res = llm_http_retry('/api/generate', $payload, $timeout);
    if (!$res['ok']) {
        throw new LlmException($res['error'] ?: 'Generate failed');
    }
    $text = trim((string) ($res['json']['response'] ?? ''));
    $thinking = trim((string) ($res['json']['thinking'] ?? ''));
    if ($text === '' && $thinking !== '') {
        if (in_array($kind, ['think', 'strong'], true) || str_contains($thinking, '<html')) {
            $text = $thinking;
        }
    }
    if ($text === '') {
        throw new LlmException('Empty generate response');
    }
    return $text;
}

function warmup_fast_model(): bool
{
    $meta = route_model_for_prompt('hi', false);
    if (!$meta['online']) {
        return false;
    }
    $fast = first_installed($meta['models'], ['llama3.2:3b', 'qwen2.5:3b', 'llama3.2:1b', 'phi3:mini']);
    if (!$fast) {
        return false;
    }
    $res = llm_http_retry('/api/generate', [
        'model'      => $fast,
        'prompt'     => 'ok',
        'stream'     => false,
        'keep_alive' => '60m',
        'options'    => ['num_predict' => 1, 'num_ctx' => 512],
    ], 45);
    return $res['ok'];
}

/**
 * @param array $history
 * @throws LlmException
 */
function ask_local_model(array $history, ?string $promptForRoute = null, bool $hasImages = false, ?string $thinkingNotes = null, bool $forceFast = false): string
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(LLM_TIMEOUT_SECONDS + 20);
    }

    $routeText = $promptForRoute;
    if ($routeText === null || $routeText === '') {
        foreach (array_reverse($history) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $routeText = (string) $m['content'];
                break;
            }
        }
    }
    $routeText = (string) $routeText;
    if (!$hasImages) {
        foreach ($history as $m) {
            if (!empty($m['images'])) {
                $hasImages = true;
                break;
            }
        }
    }

    $meta = route_model_for_prompt($routeText, $hasImages);
    if ($forceFast) {
        $fast = route_specialist('fast');
        if (($fast['name'] ?? '') !== '') {
            $meta = $fast;
            $meta['kind'] = 'fast';
        }
    } elseif (!$hasImages && ($thinkingNotes || in_array($meta['kind'] ?? '', ['strong', 'code'], true))) {
        $strong = route_specialist(($meta['kind'] ?? '') === 'code' ? 'code' : 'strong');
        if (($strong['name'] ?? '') !== '') {
            $meta = $strong;
        }
    }
    $model = $meta['name'];
    $vision = $meta['vision'];

    $messages = [];
    $sys = system_prompt_for_model($meta);
    if ($thinkingNotes) {
        $sys = trim($sys . "\n\nYou already wrote these notes. Follow them. Do not repeat the notes. Write the finished answer only.\n" . mb_substr($thinkingNotes, 0, 800));
    } else {
        $sys = trim($sys . "\nThink silently first. Then write a complete answer. Finish every list. No generic filler.");
    }
    if ($sys !== '') {
        $messages[] = ['role' => 'system', 'content' => $sys];
    }
    $window = array_slice($history, -6);
    foreach ($window as $m) {
        $content = (string) $m['content'];
        if (function_exists('strip_thought_markers')) {
            $content = strip_thought_markers($content);
        } elseif (preg_match('/<<<ANSWER>>>\s*([\s\S]*)$/', $content, $mm)) {
            $content = trim($mm[1]);
        }
        $entry = ['role' => $m['role'], 'content' => mb_substr($content, 0, 1200)];
        if (!empty($m['images']) && $vision) {
            $entry['images'] = $m['images'];
        }
        $messages[] = $entry;
    }

    $kind = $meta['kind'] ?? 'fast';
    $keep = $kind === 'fast'
        ? (defined('LLM_FAST_KEEP_ALIVE') ? LLM_FAST_KEEP_ALIVE : '60m')
        : '30s';
    $timeout = in_array($kind, ['strong', 'think', 'code'], true)
        ? (defined('LLM_THINK_TIMEOUT_SECONDS') ? LLM_THINK_TIMEOUT_SECONDS + 40 : 120)
        : LLM_TIMEOUT_SECONDS;

    $payload = [
        'model'      => $model,
        'messages'   => $messages,
        'stream'     => false,
        'keep_alive' => $keep,
        'options'    => speed_options($meta),
    ];

    $res = llm_http_retry('/api/chat', $payload, $timeout);

    $timedOut = !empty($res['errno']) && (int) $res['errno'] === CURLE_OPERATION_TIMEDOUT;
    if ($timedOut) {
        throw new LlmException('The local model is still waking up. Send that again — the next reply will be faster.');
    }
    if (!$res['ok'] && $res['code'] === 0) {
        throw new LlmException(
            "Can't reach the local model server. Make sure Ollama is running, then try once more."
        );
    }
    if ($res['error'] && !$res['ok']) {
        throw new LlmException('Local model request failed: ' . $res['error']);
    }
    if ($res['code'] >= 400) {
        throw new LlmException('Local model server returned HTTP ' . $res['code'] . ': ' . substr($res['body'], 0, 300));
    }

    $decoded = $res['json'];
    if (!is_array($decoded)) {
        throw new LlmException('Local model server returned an unexpected response.');
    }
    $content = trim((string) ($decoded['message']['content'] ?? ''));
    if ($content === '') {
        $content = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
    }
    if ($content !== '') {
        return $content;
    }

    throw new LlmException('Could not parse a reply from the local model server.');
}
