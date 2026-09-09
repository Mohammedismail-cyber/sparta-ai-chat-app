<?php
/**
 * Turn a user prompt into a pictured scene (not a leftover brand pack).
 */

if (defined('SPARTA_SCENES_LOADED')) {
    return;
}
define('SPARTA_SCENES_LOADED', true);

function scene_strip_boilerplate(string $prompt): string
{
    $s = trim($prompt);
    $s = preg_replace(
        '/^(please\s+)?((can you|could you|i want you to)\s+)?((create|make|generate|draw|design|paint|build|compose)\s+)+(for me\s+)?(an?\s+)?(image|picture|photo|poster|illustration|artwork|video|motion piece|title sequence)\s+(of\s+|for\s+|from\s+)?/i',
        '',
        $s
    );
    return trim($s, " \t\"'.,!");
}

function scene_from_prompt(string $prompt): array
{
    $raw = trim($prompt);
    $t = strtolower($raw);

    $motifs = [
        'lion'     => ['simba', 'nala', 'mufasa', 'scar', 'kiara', 'kion', 'lion king', 'pride rock', 'pride lands', 'lion'],
        'coffee'   => ['coffee', 'cafe', 'café', 'espresso', 'latte', 'roast', 'barista'],
        'ocean'    => ['ocean', 'sea', 'beach', 'wave', 'whale', 'surf'],
        'forest'   => ['forest', 'jungle', 'tree', 'woods', 'pine'],
        'city'     => ['city', 'skyline', 'night city', 'tokyo', 'new york'],
        'space'    => ['space', 'galaxy', 'planet', 'star', 'moon', 'nasa'],
        'mountain' => ['mountain', 'alps', 'peak', 'hike'],
        'flower'   => ['flower', 'rose', 'garden', 'bloom'],
    ];

    $motif = 'poster';
    foreach ($motifs as $key => $hints) {
        foreach ($hints as $h) {
            if (str_contains($t, $h)) {
                $motif = $key;
                break 2;
            }
        }
    }

    $title = scene_title_from_prompt($raw, $motif);
    $meta = [
        'lion'     => ['subtitle' => 'The Lion King', 'hero' => 'Remember who you are.', 'palette' => ['#1a0902', '#f6d365', '#e85d04']],
        'coffee'   => ['subtitle' => 'Roasted in-house', 'hero' => 'A quiet cup.', 'palette' => ['#1c1410', '#f6efe6', '#c45c26']],
        'ocean'    => ['subtitle' => 'Open water', 'hero' => 'Where the light hits the swell.', 'palette' => ['#06283d', '#dff6ff', '#47b5ff']],
        'forest'   => ['subtitle' => 'Still woods', 'hero' => 'Green, deep, and quiet.', 'palette' => ['#102116', '#e8f0e4', '#3d7a4a']],
        'city'     => ['subtitle' => 'After dark', 'hero' => 'Lights on the river.', 'palette' => ['#070b16', '#e8eefc', '#5b8def']],
        'space'    => ['subtitle' => 'Above the weather', 'hero' => 'A quiet orbit.', 'palette' => ['#050510', '#e8e4ff', '#7c5cbf']],
        'mountain' => ['subtitle' => 'High country', 'hero' => 'Air thin, light sharp.', 'palette' => ['#151a22', '#eef2f4', '#8aa0b4']],
        'flower'   => ['subtitle' => 'In bloom', 'hero' => 'Color, held still.', 'palette' => ['#2a1420', '#fff4f6', '#d94b6b']],
        'poster'   => ['subtitle' => 'A still from the brief', 'hero' => $title, 'palette' => ['#111111', '#f4f4f0', '#c9a227']],
    ];
    $m = $meta[$motif] ?? $meta['poster'];

    return [
        'motif'    => $motif,
        'title'    => $title,
        'subtitle' => $m['subtitle'],
        'hero'     => $m['hero'],
        'palette'  => $m['palette'],
        'prompt'   => $raw,
    ];
}

function scene_title_from_prompt(string $prompt, string $motif): string
{
    if (preg_match('/\b(simba|nala|mufasa|scar|kiara|kion)\b/i', $prompt, $m)) {
        return ucfirst(strtolower($m[1]));
    }
    $s = scene_strip_boilerplate($prompt);
    $s = preg_replace('/\s+in\s+(the\s+)?lion king.*$/i', '', $s);
    $s = preg_replace('/\b(html|css|javascript|website|mobile app|for me)\b/i', '', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if ($s === '' || mb_strlen($s) > 36) {
        return match ($motif) {
            'lion'     => 'Simba',
            'coffee'   => 'Morning Cup',
            'ocean'    => 'Open Water',
            'forest'   => 'The Woods',
            'city'     => 'Night City',
            'space'    => 'Orbit',
            'mountain' => 'The Peak',
            'flower'   => 'Bloom',
            default    => 'Untitled',
        };
    }
    return mb_convert_case($s, MB_CASE_TITLE);
}

function scene_svg(array $scene): string
{
    return match ($scene['motif']) {
        'lion'   => scene_svg_lion($scene),
        'coffee' => scene_svg_coffee($scene),
        'ocean'  => scene_svg_sky($scene, '#06283d', '#47b5ff', true),
        'city'   => scene_svg_city($scene),
        'space'  => scene_svg_space($scene),
        'forest' => scene_svg_forest($scene),
        default  => scene_svg_poster($scene),
    };
}

function scene_svg_lion(array $scene): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    return <<<SVG
<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#f6b26b"/>
      <stop offset="38%" stop-color="#e07a3d"/>
      <stop offset="72%" stop-color="#7a2e12"/>
      <stop offset="100%" stop-color="#1a0902"/>
    </linearGradient>
    <radialGradient id="sun" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#ffe9a3"/>
      <stop offset="55%" stop-color="#f4c430"/>
      <stop offset="100%" stop-color="#e07a3d" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1600" height="900" fill="url(#sky)"/>
  <circle cx="1180" cy="210" r="220" fill="url(#sun)"/>
  <circle cx="1180" cy="210" r="78" fill="#ffe08a"/>
  <path d="M0 620 C 180 560, 320 640, 480 600 C 700 540, 820 680, 1040 620 C 1220 570, 1400 650, 1600 600 L 1600 900 L 0 900 Z" fill="#3a1c0a"/>
  <path d="M0 700 C 240 660, 420 740, 680 690 C 940 640, 1200 760, 1600 700 L 1600 900 L 0 900 Z" fill="#241208"/>
  <path d="M430 900 L 560 430 L 720 250 L 900 430 L 1040 900 Z" fill="#4a2610"/>
  <path d="M560 430 L 720 250 L 820 430 L 700 470 Z" fill="#6b3a16"/>
  <ellipse cx="250" cy="640" rx="70" ry="18" fill="#1a0c04" opacity=".35"/>
  <path d="M210 640 C 210 500, 310 470, 310 560 C 360 470, 430 520, 400 640 Z" fill="#2a1608"/>
  <circle cx="248" cy="500" r="22" fill="#2a1608"/>
  <g transform="translate(780 470)">
    <ellipse cx="0" cy="80" rx="92" ry="70" fill="#c45c1c"/>
    <circle cx="8" cy="8" r="78" fill="#e07a3d"/>
    <circle cx="-48" cy="-8" r="34" fill="#c45c1c"/>
    <circle cx="52" cy="-4" r="34" fill="#c45c1c"/>
    <circle cx="-10" cy="-46" r="30" fill="#d97830"/>
    <circle cx="36" cy="-40" r="28" fill="#d97830"/>
    <circle cx="4" cy="6" r="42" fill="#f0b27a"/>
    <ellipse cx="-12" cy="-2" rx="7" ry="9" fill="#1a0902"/>
    <ellipse cx="18" cy="-2" rx="7" ry="9" fill="#1a0902"/>
    <ellipse cx="4" cy="14" rx="10" ry="7" fill="#6b2e12"/>
    <path d="M-8 22 Q 4 30 16 22" fill="none" stroke="#6b2e12" stroke-width="3"/>
    <path d="M-70 70 Q -10 130 80 78 Q 40 150 -20 148 Q -90 120 -70 70" fill="#a84a18"/>
  </g>
  <text x="90" y="160" fill="#fff4d6" font-family="Georgia, serif" font-size="92" font-weight="700" letter-spacing="6">{$title}</text>
  <text x="94" y="210" fill="#fff4d6" opacity=".8" font-family="Inter, system-ui, sans-serif" font-size="22" letter-spacing="8">{$sub}</text>
</svg>
SVG;
}

function scene_svg_coffee(array $scene): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    return <<<SVG
<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
  <rect width="1600" height="900" fill="#1c1410"/>
  <circle cx="1280" cy="180" r="240" fill="#c45c26" opacity=".25"/>
  <rect x="980" y="340" width="280" height="220" rx="24" fill="#f6efe6"/>
  <rect x="1010" y="370" width="220" height="160" rx="12" fill="#3d2314"/>
  <path d="M1260 430 h70 a40 40 0 0 1 0 80 h-70" fill="none" stroke="#f6efe6" stroke-width="18"/>
  <path d="M1080 340 C 1100 280, 1160 280, 1180 340" fill="none" stroke="#f6efe6" stroke-width="8" opacity=".6"/>
  <path d="M1140 330 C 1160 270, 1220 270, 1240 330" fill="none" stroke="#f6efe6" stroke-width="8" opacity=".4"/>
  <text x="90" y="400" fill="#f6efe6" font-family="Georgia, serif" font-size="84">{$title}</text>
  <text x="94" y="460" fill="#c45c26" font-family="Inter, system-ui, sans-serif" font-size="24">{$sub}</text>
</svg>
SVG;
}

function scene_svg_city(array $scene): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    $towers = '';
    $x = 620;
    foreach ([220, 340, 180, 400, 260, 310, 150, 280] as $h) {
        $towers .= '<rect x="' . $x . '" y="' . (780 - $h) . '" width="70" height="' . $h . '" fill="#12182a"/>';
        $towers .= '<rect x="' . ($x + 12) . '" y="' . (800 - $h) . '" width="8" height="10" fill="#f6d365"/>';
        $x += 90;
    }
    return '<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">'
        . '<rect width="1600" height="900" fill="#070b16"/>'
        . '<circle cx="1280" cy="140" r="40" fill="#e8eefc" opacity=".8"/>'
        . $towers
        . '<rect y="780" width="1600" height="120" fill="#050814"/>'
        . '<text x="90" y="200" fill="#e8eefc" font-family="Georgia, serif" font-size="80">' . $title . '</text>'
        . '<text x="94" y="250" fill="#5b8def" font-family="Inter, system-ui, sans-serif" font-size="22">' . $sub . '</text>'
        . '</svg>';
}

function scene_svg_space(array $scene): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    $stars = '';
    for ($i = 0; $i < 40; $i++) {
        $sx = (int) (40 + ($i * 97) % 1520);
        $sy = (int) (30 + ($i * 53) % 500);
        $stars .= '<circle cx="' . $sx . '" cy="' . $sy . '" r="1.6" fill="#fff"/>';
    }
    return '<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">'
        . '<rect width="1600" height="900" fill="#050510"/>' . $stars
        . '<circle cx="1180" cy="420" r="140" fill="#7c5cbf"/>'
        . '<circle cx="1120" cy="380" r="40" fill="#050510" opacity=".35"/>'
        . '<text x="90" y="200" fill="#e8e4ff" font-family="Georgia, serif" font-size="80">' . $title . '</text>'
        . '<text x="94" y="250" fill="#a78bfa" font-family="Inter, system-ui, sans-serif" font-size="22">' . $sub . '</text>'
        . '</svg>';
}

function scene_svg_forest(array $scene): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    return <<<SVG
<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
  <rect width="1600" height="900" fill="#102116"/>
  <circle cx="1200" cy="160" r="90" fill="#e8f0e4" opacity=".2"/>
  <polygon points="900,900 1040,320 1180,900" fill="#1d3a24"/>
  <polygon points="1080,900 1240,240 1400,900" fill="#16301d"/>
  <polygon points="1280,900 1440,380 1600,900" fill="#1d3a24"/>
  <text x="90" y="200" fill="#e8f0e4" font-family="Georgia, serif" font-size="80">{$title}</text>
  <text x="94" y="250" fill="#7dba86" font-family="Inter, system-ui, sans-serif" font-size="22">{$sub}</text>
</svg>
SVG;
}

function scene_svg_sky(array $scene, string $a, string $b, bool $wave): string
{
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    $w = $wave
        ? '<path d="M0 620 C 200 560, 400 680, 600 620 C 800 560, 1000 680, 1200 620 C 1400 560, 1600 640, 1600 640 L 1600 900 L 0 900 Z" fill="' . $b . '" opacity=".85"/>'
        : '';
    return '<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">'
        . '<rect width="1600" height="900" fill="' . htmlspecialchars($a, ENT_QUOTES, 'UTF-8') . '"/>'
        . '<circle cx="1200" cy="180" r="90" fill="#fff" opacity=".85"/>'
        . $w
        . '<text x="90" y="200" fill="#fff" font-family="Georgia, serif" font-size="80">' . $title . '</text>'
        . '<text x="94" y="250" fill="#fff" opacity=".7" font-family="Inter, system-ui, sans-serif" font-size="22">' . $sub . '</text>'
        . '</svg>';
}

function scene_svg_poster(array $scene): string
{
    [$a, $b, $c] = $scene['palette'];
    $title = htmlspecialchars($scene['title'], ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars($scene['subtitle'], ENT_QUOTES, 'UTF-8');
    $a = htmlspecialchars($a, ENT_QUOTES, 'UTF-8');
    $b = htmlspecialchars($b, ENT_QUOTES, 'UTF-8');
    $c = htmlspecialchars($c, ENT_QUOTES, 'UTF-8');
    return <<<SVG
<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
  <rect width="1600" height="900" fill="{$a}"/>
  <circle cx="1280" cy="160" r="260" fill="{$c}" opacity=".35"/>
  <circle cx="300" cy="780" r="280" fill="{$b}" opacity=".08"/>
  <rect x="90" y="300" width="8" height="220" fill="{$c}"/>
  <text x="120" y="380" fill="{$b}" font-family="Georgia, serif" font-size="72">{$title}</text>
  <text x="124" y="440" fill="{$b}" opacity=".7" font-family="Inter, system-ui, sans-serif" font-size="22">{$sub}</text>
</svg>
SVG;
}

function scene_card_art(string $motif, int $i, array $palette): string
{
    [$a, $b, $c] = array_pad($palette, 3, '#888');
    $a = htmlspecialchars($a, ENT_QUOTES, 'UTF-8');
    $b = htmlspecialchars($b, ENT_QUOTES, 'UTF-8');
    $c = htmlspecialchars($c, ENT_QUOTES, 'UTF-8');
    $shift = 20 * ($i % 4);
    return '<svg viewBox="0 0 640 400" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">'
        . '<rect width="640" height="400" fill="' . $a . '"/>'
        . '<circle cx="' . (480 + $shift) . '" cy="' . (80 + $shift) . '" r="140" fill="' . $c . '" opacity=".45"/>'
        . '<rect x="40" y="240" width="220" height="12" rx="6" fill="' . $b . '" opacity=".35"/>'
        . '<rect x="40" y="268" width="140" height="8" rx="4" fill="' . $b . '" opacity=".2"/>'
        . '</svg>';
}
