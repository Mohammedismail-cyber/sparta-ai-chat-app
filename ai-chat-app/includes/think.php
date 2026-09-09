<?php
/**
 * Think first, then answer or build.
 * Notes stay internal — the user only sees the finished reply.
 */

if (defined('SPARTA_THINK_LOADED')) {
    return;
}
define('SPARTA_THINK_LOADED', true);

require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/studio_packs.php';
require_once __DIR__ . '/scenes.php';
require_once __DIR__ . '/specialists.php';

function public_reply(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $text) ?? $text;
    $text = preg_replace('/<think>[\s\S]*$/i', '', $text) ?? $text;
    if (preg_match('/<<<ANSWER>>>\s*([\s\S]*)$/', $text, $m)) {
        $text = trim($m[1]);
    }
    $text = preg_replace('/<<<THINK>>>[\s\S]*?(?=<<<ANSWER>>>|$)/', '', $text);
    $text = preg_replace('/^THINKING\s*\n[\s\S]*?(?=\n\n)/i', '', $text);
    return trim($text);
}

function compose_thoughtful_reply(?string $notes, string $answer): string
{
    return public_reply($answer);
}

function strip_thought_markers(string $text): string
{
    return public_reply($text);
}

function chat_think_prompt(string $prompt): string
{
    $brief = mb_substr(trim($prompt), 0, 1200);
    return <<<TXT
Think only. Do not give the final answer yet.

The user said:
"""
{$brief}
"""

Write short private notes:
Want: (one sentence)
Points:
- point one
- point two
- point three

No greeting. Stay under 100 words.
TXT;
}

function think_prompt(string $brief, string $kind): string
{
    $want = [
        'website'  => 'a finished website with nav, hero, service cards, story, and footer',
        'mobile'   => 'a finished mobile app with home, search, saved, and profile screens',
        'image'    => 'a finished illustrated image of the subject — not a brand poster unless they asked for a brand',
        'video'    => 'a finished motion title of the subject',
        'document' => 'a finished written brief',
    ][$kind] ?? 'a finished piece';

    $cut = mb_substr(trim($brief), 0, 900);

    return <<<TXT
The user said:
"""
{$cut}
"""

They want {$want}. Use their subject. If they named a character or place, that is the title.

Then write a line that is exactly ---JSON---
Then JSON only:
{"name":"","tagline":"","hero":"","story":"","cta":"","nav":["Home","Services","Story","Visit"],"palette":["#1c1410","#f6efe6","#c45c26"],"menu":[{"name":"","desc":"","price":""},{"name":"","desc":"","price":""},{"name":"","desc":"","price":""},{"name":"","desc":"","price":""}],"features":["","",""],"hours":[""],"address":""}

Finish every field. Do not use Sparta or Field Notes as a name.
TXT;
}

function parse_think_output(string $raw): array
{
    $plan = trim($raw);
    $json = null;

    if (preg_match('/---JSON---\s*(\{.*\})\s*$/s', $raw, $m)) {
        $plan = trim(preg_replace('/---JSON---.*$/s', '', $raw));
        $json = json_decode($m[1], true);
    } elseif (preg_match('/\{.*\}/s', $raw, $m)) {
        $plan = trim(str_replace($m[0], '', $raw));
        $json = json_decode($m[0], true);
    }

    $plan = trim($plan);
    if ($plan === '') {
        $plan = null;
    }
    return [$plan, is_array($json) ? $json : null];
}

function extract_name_from_plan(?string $plan): ?string
{
    if (!$plan) {
        return null;
    }
    if (preg_match('/^\s*Name:\s*(.+)$/mi', $plan, $m)) {
        $name = trim($m[1], " \t\"'.,");
        if ($name !== '' && mb_strlen($name) <= 40 && !preg_match('/\b(create|html|css|javascript|website|sparta|field notes)\b/i', $name)) {
            return $name;
        }
    }
    return null;
}

function merge_thought_pack(array $base, ?array $thought, ?string $plan = null): array
{
    if ($thought) {
        foreach (['name', 'tagline', 'hero', 'story', 'cta', 'address'] as $k) {
            if (!empty($thought[$k]) && is_string($thought[$k])) {
                $val = trim($thought[$k]);
                if ($val === '' || preg_match('/\b(sparta|field notes)\b/i', $val)) {
                    continue;
                }
                if (!preg_match('/\b(create|html|css|for me|website)\b/i', $val)) {
                    $base[$k] = mb_substr($val, 0, $k === 'story' || $k === 'hero' ? 400 : 160);
                } elseif (in_array($k, ['story', 'hero', 'tagline', 'cta', 'address'], true)) {
                    $base[$k] = mb_substr($val, 0, 400);
                }
            }
        }
        if (!empty($thought['name']) && is_string($thought['name'])) {
            $name = trim($thought['name']);
            if ($name !== '' && mb_strlen($name) <= 40 && !preg_match('/\b(create|html|css|javascript|sparta|field notes)\b/i', $name)) {
                $base['name'] = $name;
                $base['title'] = $name;
            }
        }
        if (!empty($thought['nav']) && is_array($thought['nav'])) {
            $nav = array_values(array_filter(array_map('strval', $thought['nav'])));
            if ($nav) {
                $base['nav'] = array_slice($nav, 0, 6);
            }
        }
        if (!empty($thought['features']) && is_array($thought['features'])) {
            $feats = [];
            foreach ($thought['features'] as $f) {
                if (is_string($f) && trim($f) !== '') {
                    $feats[] = mb_substr(trim($f), 0, 80);
                }
            }
            if ($feats) {
                $base['features'] = array_slice($feats, 0, 8);
            }
        }
        if (!empty($thought['hours']) && is_array($thought['hours'])) {
            $base['hours'] = array_slice(array_map('strval', $thought['hours']), 0, 4);
        }
        if (!empty($thought['palette']) && is_array($thought['palette'])) {
            $colors = [];
            foreach ($thought['palette'] as $c) {
                if (is_string($c) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) {
                    $colors[] = $c;
                }
            }
            if (count($colors) >= 2) {
                $base['palette'] = array_pad(array_slice($colors, 0, 3), 3, $colors[0]);
            }
        }
        if (!empty($thought['menu']) && is_array($thought['menu'])) {
            $menu = [];
            foreach ($thought['menu'] as $row) {
                if (is_array($row)) {
                    $menu[] = [
                        mb_substr((string) ($row['name'] ?? $row[0] ?? 'Item'), 0, 40),
                        mb_substr((string) ($row['desc'] ?? $row[1] ?? ''), 0, 80),
                        mb_substr((string) ($row['price'] ?? $row[2] ?? ''), 0, 20),
                    ];
                }
            }
            if ($menu) {
                $base['menu'] = array_slice($menu, 0, 10);
            }
        }
    }

    $fromPlan = extract_name_from_plan($plan);
    if ($fromPlan) {
        $base['name'] = $fromPlan;
        $base['title'] = $fromPlan;
    }

    return $base;
}

function think_then_make(string $prompt, string $kind, int $userId, int $conversationId, int $messageId, array $history = [], string $mode = 'think'): array
{
    $brief = collect_brief($prompt, $history);
    $scene = scene_from_prompt($brief);
    $domain = studio_domain_from_text($brief);
    $pack = studio_pack($domain, $brief, $conversationId);
    $pack['scene'] = $scene;
    $pack['nav'] = ['Home', 'Services', 'Story', 'Visit'];

    if (in_array($kind, ['image', 'video'], true) || ($domain === 'studio' && $scene['motif'] !== 'poster')) {
        $pack['name'] = $scene['title'];
        $pack['title'] = $scene['title'];
        $pack['tagline'] = $scene['subtitle'];
        $pack['hero'] = $scene['hero'];
        $pack['palette'] = $scene['palette'];
        $pack['story'] = $scene['hero'] . ' ' . $scene['subtitle'];
    }

    $notes = null;

    $html = null;
    $replyExtra = '';

    if (in_array($kind, ['website', 'mobile', 'document', 'slides'], true)) {
        $html = generate_specialist_html($brief, $kind, (string) ($pack['name'] ?? $scene['title']));
        if ($html) {
            $pack['name'] = title_from_html($html, $pack['name']);
            $pack['title'] = $pack['name'];
        }
    }

    if (in_array($kind, ['image', 'video'], true)) {
        $imgPrompt = scene_strip_boilerplate($brief);
        if (mb_strlen($imgPrompt) < 12) {
            $imgPrompt = $brief;
        }
        $gen = generate_local_image($imgPrompt);
        if ($gen['ok'] && $gen['png']) {
            $html = wrap_image_html($scene['title'], $gen['png'], $kind === 'video');
            $pack['name'] = $scene['title'];
        }
    }

    if (!$html && in_array($kind, ['website', 'mobile', 'document', 'slides'], true)) {
        $html = polish_generated_html(specialist_skeleton_html($brief, $kind, (string) ($pack['name'] ?? $scene['title'])), $kind);
        $pack['name'] = title_from_html($html, $pack['name']);
        $pack['title'] = $pack['name'];
    }

    if (!$html) {
        $html = render_artifact_html($pack, $kind);
    }

    $artifact = save_artifact($userId, $conversationId, $messageId, $kind, $pack['name'], $html);

    $labels = [
        'website'  => 'website',
        'mobile'   => 'mobile app',
        'image'    => 'image',
        'video'    => 'motion piece',
        'document' => 'document',
        'slides'   => 'slide deck',
    ];
    $label = $labels[$kind] ?? 'piece';
    $reply = 'Here’s the ' . $label . ' for ' . $pack['name'] . '.' . $replyExtra . ' Open the preview.';

    return ['artifact' => $artifact, 'reply' => public_reply($reply)];
}

function ask_with_thinking(array $history, string $prompt, bool $hasImages = false, string $mode = 'think'): string
{
    $notes = null;
    $shouldThink = $mode !== 'fast' && !$hasImages && mb_strlen(trim($prompt)) >= 6;

    if ($shouldThink) {
        try {
            $notes = generate_plain(chat_think_prompt($prompt), [
                'temperature' => 0.4,
                'num_ctx'     => 2048,
                'num_predict' => 360,
            ], 'think');
            $notes = trim(preg_replace('/^(sure|okay|here(?:\'s| are).*?:)\s*/i', '', (string) $notes));
            if (mb_strlen($notes) < 20) {
                $notes = null;
            }
        } catch (LlmException $e) {
            $notes = null;
        }
    }

    $answer = ask_local_model($history, $prompt, $hasImages, $notes, $mode === 'fast');
    return public_reply($answer);
}
