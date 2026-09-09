<?php
/**
 * Specialist builders: coder HTML, writer documents, local image gen.
 */

if (defined('SPARTA_SPECIALISTS_LOADED')) {
    return;
}
define('SPARTA_SPECIALISTS_LOADED', true);

require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/config.php';

function extract_generated_html(string $raw): ?string
{
    $raw = trim($raw);
    if (preg_match('/```html\s*([\s\S]*?)```/i', $raw, $m)) {
        $raw = trim($m[1]);
    } elseif (preg_match('/```(?:html|xml)?\s*([\s\S]*?)```/i', $raw, $m) && str_contains(strtolower($m[1]), '<html')) {
        $raw = trim($m[1]);
    }
    if (preg_match('/<!DOCTYPE html[\s\S]*<\/html>/i', $raw, $m)) {
        return trim($m[0]);
    }
    if (preg_match('/<html[\s\S]*<\/html>/i', $raw, $m)) {
        return trim($m[0]);
    }
    return null;
}

function sanitize_generated_html(string $html): string
{
    $html = preg_replace('/<\?(?:php|=)[\s\S]*?\?>/i', '', $html) ?? $html;
    $html = preg_replace('/<script[^>]+src=["\'][^"\']+["\'][^>]*>\s*<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', '', $html) ?? $html;
    return $html;
}

function inline_css_text(string $html): string
{
    $css = '';
    if (preg_match_all('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $html, $m)) {
        $css = implode("\n", $m[1]);
    }
    return $css;
}

function html_has_css(string $html): bool
{
    $css = inline_css_text($html);
    if (strlen($css) < 180) {
        return false;
    }
    return substr_count($css, '{') >= 4 && str_contains($css, ':');
}

function html_has_js(string $html): bool
{
    if (!preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/i', $html, $m)) {
        return false;
    }
    $js = implode("\n", $m[1]);
    return strlen($js) >= 40 && (bool) preg_match('/addEventListener|function\s*\(|querySelector/i', $js);
}

function replace_file_images(string $html): string
{
    return preg_replace_callback(
        '/<img\b([^>]*?)src=["\'](?!data:|https?:\/\/)([^"\']+)["\']([^>]*)>/i',
        function ($m) {
            $attrs = $m[1] . $m[3];
            $alt = 'image';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $a)) {
                $alt = $a[1];
            }
            $safe = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            return '<div class="ph" role="img" aria-label="' . $safe . '"></div>';
        },
        $html
    ) ?? $html;
}

function specialist_fallback_css(string $kind): string
{
    if ($kind === 'document') {
        return '@page{size:Letter;margin:18mm}body{margin:0;background:#e7e5e4;font-family:Georgia,serif;color:#1c1917}.page,article,main{width:min(760px,92vw);margin:28px auto;background:#fff;padding:56px 60px 64px;box-shadow:0 10px 40px rgba(0,0,0,.08)}h1{font-size:36px;margin:0 0 8px}h2{font-size:20px;margin:28px 0 10px}p,li,td{line-height:1.55}table{width:100%;border-collapse:collapse}td,th{padding:8px 0;border-bottom:1px solid #e7e5e4;text-align:left}';
    }
    if ($kind === 'slides') {
        return '*{box-sizing:border-box}html,body{margin:0;height:100%;background:#111;color:#f4f1ea;font-family:Inter,system-ui,sans-serif}.deck{height:100%;position:relative}.slide{display:none;height:100%;padding:7% 8%;flex-direction:column;justify-content:center;background:linear-gradient(160deg,#1c1410,#2a2218 55%,#111)}.slide.on{display:flex}h1{font-family:Georgia,serif;font-size:clamp(36px,6vw,64px);margin:0 0 16px}h2{font-family:Georgia,serif;font-size:32px;margin:0 0 14px}p,li{font-size:20px;line-height:1.5;max-width:40ch;opacity:.86}.kicker{letter-spacing:.16em;text-transform:uppercase;font-size:12px;opacity:.55;margin-bottom:12px}.navs{position:fixed;bottom:22px;right:22px;display:flex;gap:8px}button{border:0;background:#f4f1ea;color:#111;border-radius:999px;padding:10px 16px;font-weight:700;cursor:pointer}';
    }
    if ($kind === 'mobile') {
        return '*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:#f6f4f0;font-family:Inter,system-ui,sans-serif;color:#111}body{display:flex;flex-direction:column}.phone,main,#app{width:100%;min-height:100vh;display:flex;flex-direction:column;background:#f6f4f0}nav,.logo,.status{display:flex;align-items:center;justify-content:space-between;padding:18px 18px 8px}h1{margin:0 16px 6px;font-size:26px}p{margin:0 16px 12px;line-height:1.45;opacity:.72}header,.hero,#hero{margin:8px 16px 12px;padding:22px 18px;border-radius:22px;background:linear-gradient(135deg,#1c1410,#c45c26);color:#fff;min-height:140px}.cards,.grid,#services,.columns{display:grid;grid-template-columns:1fr;gap:10px;padding:0 16px 16px}article,.card,.row{display:flex;gap:12px;align-items:center;background:#fff;border-radius:16px;padding:12px;border:0}.ph,img,svg{width:56px;height:56px;border-radius:12px;object-fit:cover;background:linear-gradient(135deg,#1c1410,#e07a3d);flex-shrink:0}nav,.tab,footer{margin-top:auto;display:flex;border-top:1px solid rgba(0,0,0,.08);background:#fff}nav a,.tab button,footer a,nav button{flex:1;padding:14px 8px;text-align:center;text-decoration:none;color:#666;background:transparent;border:0;font-weight:700;font-size:11px}.on,nav a:hover{color:#111}.screen,.panel{display:none;flex:1;overflow:auto}.screen.on,.panel.on,.screen:first-of-type{display:block}button,.cta{border:0;background:#111;color:#fff;border-radius:999px;padding:10px 14px}';
    }
    return '*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,system-ui,sans-serif;color:#111;background:#f7f4ef}a{color:inherit;text-decoration:none}img{max-width:100%;display:block}nav{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:14px 7%;background:rgba(247,244,239,.94);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.06)}.logo,.brand{font-weight:800;letter-spacing:.02em;display:flex;align-items:center;gap:10px}.logo .ph{width:36px;height:36px;border-radius:10px}.nav-links,.links{display:flex;gap:18px;align-items:center}nav a{font-size:13px;opacity:.75}nav a:hover{opacity:1}#hero,header,.hero{display:grid;grid-template-columns:1.1fr .9fr;gap:32px;padding:72px 7%;min-height:64vh;align-items:center;background:linear-gradient(180deg,#f7f4ef,#efe7d8)}h1{font-family:Georgia,serif;font-size:clamp(40px,6vw,72px);line-height:.95;margin:0 0 16px}p{line-height:1.55;max-width:46ch}.buttons,.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}button,.btn,.cta{display:inline-block;background:#111;color:#fff;border:0;border-radius:999px;padding:12px 18px;cursor:pointer}section{padding:72px 7%}h2{font-family:Georgia,serif;font-size:32px;margin:0 0 22px}.cards,.grid,#services .cards,.columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}article,.card{background:#fff;border-radius:18px;overflow:hidden;border:1px solid rgba(0,0,0,.06);box-shadow:0 10px 30px rgba(0,0,0,.04);padding:0 0 18px}.ph,article img,.card img,#hero img,.hero img{min-height:140px;width:100%;background:linear-gradient(135deg,#1c1410,#c45c26);border-radius:0}#hero .ph,.hero .ph{min-height:280px;border-radius:24px}article h3,.card h3{margin:14px 16px 6px}article p,.card p{margin:0 16px;font-size:14px;opacity:.7}footer{padding:36px 7% 48px;border-top:1px solid rgba(0,0,0,.08);display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}@media(max-width:800px){#hero,header,.hero,.columns{grid-template-columns:1fr}.nav-links a{display:none}}';
}

function specialist_fallback_js(string $kind): string
{
    if ($kind === 'mobile') {
        return '(function(){function show(id){document.querySelectorAll(".screen,.panel,[data-screen]").forEach(function(s){var on=s.id===id||s.getAttribute("data-screen")===id;s.style.display=on?"block":"none";s.classList.toggle("on",on);});}document.querySelectorAll(".tab button, nav a, nav button, [data-tab]").forEach(function(b){b.addEventListener("click",function(e){var id=b.getAttribute("data-tab")||(b.getAttribute("href")||"").replace("#","");if(!id){var t=(b.textContent||"").trim().toLowerCase();id={home:"home",search:"search",saved:"saved",you:"profile",profile:"profile"}[t]||t;}if(!id)return;e.preventDefault();show(id);document.querySelectorAll(".tab button, nav button").forEach(function(x){x.classList.toggle("on",x===b);});});});})();';
    }
    if ($kind === 'slides') {
        return '(function(){var s=document.querySelectorAll(".slide");var i=0;function go(n){i=(n+s.length)%s.length;s.forEach(function(x,k){x.classList.toggle("on",k===i);});}go(0);document.querySelectorAll("[data-next]").forEach(function(b){b.onclick=function(){go(i+1)};});document.querySelectorAll("[data-prev]").forEach(function(b){b.onclick=function(){go(i-1)};});document.addEventListener("keydown",function(e){if(e.key==="ArrowRight"||e.key===" ")go(i+1);if(e.key==="ArrowLeft")go(i-1);});})();';
    }
    return 'document.querySelectorAll(\'nav a[href^="#"], a[href^="#"]\').forEach(function(a){a.addEventListener("click",function(e){var t=document.querySelector(a.getAttribute("href"));if(!t)return;e.preventDefault();t.scrollIntoView({behavior:"smooth"});});});';
}

function inject_head_css(string $html, string $css): string
{
    $style = '<style>' . $css . '</style>';
    if (preg_match('/<\/head>/i', $html)) {
        return preg_replace('/<\/head>/i', $style . '</head>', $html, 1) ?? ($style . $html);
    }
    if (preg_match('/<body/i', $html)) {
        return preg_replace('/<body/i', '<head>' . $style . '</head><body', $html, 1) ?? ($style . $html);
    }
    return $style . $html;
}

function inject_body_js(string $html, string $js): string
{
    $script = '<script>' . $js . '</script>';
    if (preg_match('/<\/body>/i', $html)) {
        return preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? ($html . $script);
    }
    return $html . $script;
}

function polish_generated_html(string $html, string $kind): string
{
    $html = replace_file_images($html);
    if (!html_has_css($html)) {
        $html = inject_head_css($html, specialist_fallback_css($kind));
    }
    if (in_array($kind, ['website', 'mobile', 'slides'], true) && !html_has_js($html)) {
        $html = inject_body_js($html, specialist_fallback_js($kind));
    }
    return $html;
}

function html_looks_complete(string $html, string $kind): bool
{
    $low = strtolower($html);
    if (!str_contains($low, '</html>') || !str_contains($low, '<body')) {
        return false;
    }
    if (preg_match('/<\?php|<\?=/i', $html)) {
        return false;
    }
    if (in_array($kind, ['website', 'mobile'], true)) {
        $structure = str_contains($low, '<nav')
            || str_contains($low, '<header')
            || str_contains($low, '<section')
            || str_contains($low, 'class="phone"')
            || str_contains($low, 'class="hero"');
        return $structure && html_has_css($html);
    }
    if ($kind === 'slides') {
        return (str_contains($low, 'class="slide"') || str_contains($low, 'class="deck"')) && html_has_css($html);
    }
    return html_has_css($html) || mb_strlen($html) > 800;
}

function specialist_html_prompt(string $brief, string $kind, string $titleHint = ''): string
{
    $cut = mb_substr(trim($brief), 0, 900);
    $hint = $titleHint !== '' ? "Preferred title if it fits: {$titleHint}\n" : '';

    if ($kind === 'mobile') {
        return <<<TXT
Write one complete HTML5 mobile app. Output HTML only. One file.

User brief:
"""
{$cut}
"""
{$hint}
Required:
- A real product name (not Sparta, not Field Notes)
- ALL CSS in a <style> tag in <head>. Never link to styles.css or any other file.
- ALL JS in a <script> tag before </body>. Never use src= files.
- No <img src="file.png">. Use <div class="ph"> color blocks instead.
- Phone layout: status bar, hero, 4 cards, tab bar with data-tab="home|search|saved|profile"
- Screens use class="screen" and matching ids so tabs switch
- CSS and JS are mandatory. Do not output a bare HTML skeleton.
- End with </html>
TXT;
    }

    if ($kind === 'document') {
        return <<<TXT
Write one complete print-ready HTML document. Output HTML only.

User brief:
"""
{$cut}
"""
{$hint}
Required:
- Real title (not Sparta, not Field Notes)
- ALL CSS in a <style> tag in <head>. Never link to an external stylesheet.
- Letter page: type, margins, table, headings
- 4+ sections, a table, a closing
- End with </html>
TXT;
    }

    if ($kind === 'slides') {
        return <<<TXT
Write one complete HTML slide deck. Output HTML only. One file.

User brief:
"""
{$cut}
"""
{$hint}
Required:
- A real talk title (not Sparta, not Field Notes)
- ALL CSS in a <style> tag in <head>
- ALL JS in a <script> before </body> so Next / Prev and arrow keys change slides
- A wrapping .deck with at least 5 .slide sections; the first has class="slide on"
- Buttons with data-next and data-prev
- No external CSS, JS, or image files
- End with </html>
TXT;
    }

    return <<<TXT
Write one complete HTML website. Output starts with <!DOCTYPE html>. No markdown.

User brief:
"""
{$cut}
"""
{$hint}
Required:
- Invent a real brand name (not the prompt, not Sparta, not Field Notes)
- ALL CSS in a <style> tag in <head>. Never link to styles.css or any other file.
- ALL JS in a <script> tag before </body>. Never use src= files.
- No <img src="logo.png"> or other files. Use <div class="ph"> color blocks instead.
- Sticky nav, hero with two buttons, 3+ service cards, story, footer
- CSS and JS are mandatory. Do not output a bare HTML skeleton.
- Finish every tag. End with </html>
TXT;
}

function generate_specialist_html(string $brief, string $kind, string $titleHint = ''): ?string
{
    $job = match ($kind) {
        'document' => 'write',
        'mobile'   => 'mobile',
        'slides'   => 'slides',
        default    => 'web',
    };
    try {
        $raw = generate_plain(specialist_html_prompt($brief, $kind, $titleHint), [
            'temperature' => in_array($kind, ['document', 'slides'], true) ? 0.55 : 0.3,
            'num_ctx'     => 4096,
            'num_predict' => $kind === 'document' ? 2500 : 3200,
        ], $job);
    } catch (LlmException $e) {
        return null;
    }
    $html = extract_generated_html($raw);
    if (!$html) {
        return null;
    }
    return polish_generated_html(sanitize_generated_html($html), $kind);
}

function specialist_skeleton_html(string $brief, string $kind, string $title): string
{
    $t = htmlspecialchars($title !== '' ? $title : 'Untitled', ENT_QUOTES, 'UTF-8');
    $body = htmlspecialchars(mb_substr(trim($brief), 0, 600), ENT_QUOTES, 'UTF-8');
    if ($kind === 'slides') {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $t . '</title></head><body>'
            . '<div class="deck">'
            . '<section class="slide on"><div class="kicker">Deck</div><h1>' . $t . '</h1><p>' . $body . '</p></section>'
            . '<section class="slide"><h2>What this is</h2><p>' . $body . '</p></section>'
            . '<section class="slide"><h2>Next</h2><p>Use the arrows to move through the talk.</p></section>'
            . '</div><div class="navs"><button data-prev type="button">Back</button><button data-next type="button">Next</button></div>'
            . '</body></html>';
    }
    if ($kind === 'mobile') {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $t . '</title></head><body>'
            . '<div class="phone"><div class="status"><span>9:41</span></div>'
            . '<header class="hero"><h1>' . $t . '</h1><p>' . $body . '</p></header>'
            . '<div class="screen on" id="home"><article class="card"><h3>Home</h3><p>' . $body . '</p></article></div>'
            . '<div class="screen" id="search"><p>Search</p></div>'
            . '<div class="screen" id="saved"><p>Saved</p></div>'
            . '<div class="screen" id="profile"><p>' . $t . '</p></div>'
            . '<div class="tab"><button class="on" data-tab="home">Home</button><button data-tab="search">Search</button>'
            . '<button data-tab="saved">Saved</button><button data-tab="profile">You</button></div></div></body></html>';
    }
    if ($kind === 'document') {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $t . '</title></head><body>'
            . '<article class="page"><h1>' . $t . '</h1><p>' . $body . '</p></article></body></html>';
    }
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $t . '</title></head><body>'
        . '<nav><div class="logo">' . $t . '</div><div class="nav-links"><a href="#hero">Home</a><a href="#story">Story</a></div></nav>'
        . '<section id="hero"><h1>' . $t . '</h1><p>' . $body . '</p></section>'
        . '<section id="story"><h2>Story</h2><p>' . $body . '</p></section>'
        . '</body></html>';
}

function decode_image_payload($value): ?string
{
    if (is_array($value)) {
        $value = $value[0] ?? null;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    if (str_starts_with($value, 'data:image')) {
        $value = preg_replace('/^data:image\/[a-zA-Z0-9+]+;base64,/', '', $value) ?? $value;
    }
    $bin = base64_decode($value, true);
    if ($bin === false || strlen($bin) < 32) {
        return null;
    }
    if (strncmp($bin, "\x89PNG", 4) !== 0 && strncmp($bin, "\xFF\xD8\xFF", 3) !== 0 && strncmp($bin, 'RIFF', 4) !== 0) {
        return null;
    }
    return $bin;
}

function png_from_ollama_json(?array $json): ?string
{
    if (!$json) {
        return null;
    }
    foreach (['image', 'b64_json'] as $k) {
        $hit = decode_image_payload($json[$k] ?? null);
        if ($hit) {
            return $hit;
        }
    }
    if (!empty($json['images'])) {
        $hit = decode_image_payload($json['images']);
        if ($hit) {
            return $hit;
        }
    }
    if (!empty($json['data'][0]['b64_json'])) {
        $hit = decode_image_payload($json['data'][0]['b64_json']);
        if ($hit) {
            return $hit;
        }
    }
    if (!empty($json['response']) && is_string($json['response']) && strlen($json['response']) > 200) {
        $hit = decode_image_payload($json['response']);
        if ($hit) {
            return $hit;
        }
    }
    return null;
}

function image_gen_script_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'image_gen.py';
}

function python_bin(): string
{
    return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
}

function prepare_python_image_backend(): void
{
    $ready = dirname(DB_PATH) . '/image-backend.ready';
    if (is_file($ready) && filesize($ready) > 2) {
        return;
    }
    $flag = dirname(DB_PATH) . '/image-backend.pulling';
    if (is_file($flag) && (time() - filemtime($flag)) < 7200) {
        return;
    }
    @file_put_contents($flag, date('c'));
    $script = image_gen_script_path();
    $log = dirname(DB_PATH) . '/image-backend.log';
    if (!is_file($script)) {
        return;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = 'start /MIN /B ' . python_bin() . ' ' . escapeshellarg($script) . ' --prepare';
        pclose(popen($cmd, 'r'));
        return;
    }
    exec('nohup ' . escapeshellarg(python_bin()) . ' ' . escapeshellarg($script) . ' --prepare > ' . escapeshellarg($log) . ' 2>&1 &');
}

function generate_python_image(string $prompt): ?string
{
    $script = image_gen_script_path();
    if (!is_file($script)) {
        return null;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(240);
    }
    $out = dirname(DB_PATH) . '/image-' . bin2hex(random_bytes(6)) . '.png';
    $cmd = python_bin() . ' -u ' . escapeshellarg($script)
        . ' --prompt ' . escapeshellarg($prompt)
        . ' --out ' . escapeshellarg($out);
    $lines = [];
    $code = 1;
    exec($cmd . ' 2>&1', $lines, $code);
    if ($code !== 0 || !is_file($out) || filesize($out) < 64) {
        @file_put_contents(
            dirname(DB_PATH) . '/image-backend.log',
            date('c') . " generate failed code={$code}\n" . implode("\n", $lines) . "\n",
            FILE_APPEND
        );
        @unlink($out);
        return null;
    }
    $png = file_get_contents($out);
    @unlink($out);
    return is_string($png) && strncmp($png, "\x89PNG", 4) === 0 ? $png : null;
}

/**
 * @return array{ok:bool, png:?string, source:string}
 */
function generate_local_image(string $prompt): array
{
    $prompt = mb_substr(trim($prompt), 0, 400);
    $name = ensure_image_model();
    if ($name !== '' && model_is_image_gen($name)) {
        $payloads = [
            ['path' => '/api/generate-image', 'body' => [
                'model' => $name,
                'prompt' => $prompt,
                'size' => '768x768',
                'response_format' => 'b64_json',
            ]],
            ['path' => '/v1/images/generations', 'body' => [
                'model' => $name,
                'prompt' => $prompt,
                'size' => '768x768',
                'response_format' => 'b64_json',
            ]],
        ];
        foreach ($payloads as $try) {
            $res = llm_http_retry($try['path'], $try['body'], 90);
            $png = png_from_ollama_json($res['json']);
            if ($png) {
                return ['ok' => true, 'png' => $png, 'source' => 'ollama'];
            }
        }
    }

    $png = generate_python_image($prompt);
    if ($png) {
        return ['ok' => true, 'png' => $png, 'source' => 'diffusers'];
    }

    $bases = array_unique([
        rtrim(defined('A1111_BASE_URL') ? A1111_BASE_URL : 'http://127.0.0.1:7860', '/'),
        'http://127.0.0.1:7860',
        'http://127.0.0.1:7861',
    ]);
    foreach ($bases as $a1111) {
        $res = llm_http($a1111 . '/sdapi/v1/txt2img', [
            'prompt' => $prompt,
            'steps' => 18,
            'width' => 512,
            'height' => 512,
            'cfg_scale' => 6,
        ], 90);
        if ($res['ok'] && is_array($res['json'])) {
            $png = decode_image_payload($res['json']['images'] ?? null);
            if ($png) {
                return ['ok' => true, 'png' => $png, 'source' => 'a1111'];
            }
        }
    }

    return ['ok' => false, 'png' => null, 'source' => 'none'];
}

function wrap_image_html(string $title, string $pngBinary, bool $motion = false): string
{
    $mime = strncmp($pngBinary, "\xFF\xD8\xFF", 3) === 0 ? 'image/jpeg' : 'image/png';
    $b64 = base64_encode($pngBinary);
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    if ($motion) {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $t . '</title><style>'
            . 'html,body{margin:0;height:100%;overflow:hidden;background:#000;color:#fff}'
            . '.stage{position:relative;height:100%;display:flex;align-items:center;justify-content:center}'
            . '.art{position:absolute;inset:-8%;background:url(data:' . $mime . ';base64,' . $b64 . ') center/cover no-repeat;animation:ken 12s ease-in-out infinite alternate}'
            . '.copy{position:relative;text-align:center;z-index:1;text-shadow:0 8px 30px #000}'
            . 'h1{font-family:Georgia,serif;font-size:clamp(40px,7vw,84px);margin:0;animation:rise 1.4s ease}'
            . '@keyframes ken{to{transform:scale(1.12)}}@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1}}'
            . '</style></head><body><div class="stage"><div class="art"></div><div class="copy"><h1>' . $t . '</h1></div></div></body></html>';
    }
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $t . '</title>'
        . '<style>html,body{margin:0;height:100%;background:#0a0a0c}img{width:100%;height:100%;object-fit:contain;display:block}</style>'
        . '</head><body><img alt="' . $t . '" src="data:' . $mime . ';base64,' . $b64 . '"></body></html>';
}

function title_from_html(string $html, string $fallback): string
{
    if (preg_match('/<title>([^<]{2,80})<\/title>/i', $html, $m)) {
        $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if ($t !== '' && !preg_match('/\b(sparta|field notes)\b/i', $t)) {
            return $t;
        }
    }
    if (preg_match('/<h1[^>]*>([^<]{2,80})<\/h1>/i', $html, $m)) {
        $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if ($t !== '' && !preg_match('/\b(create|html|sparta|field notes)\b/i', $t)) {
            return $t;
        }
    }
    return $fallback;
}
