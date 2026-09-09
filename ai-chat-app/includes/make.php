<?php
/**
 * Build complete, branded previews from a user brief + chat context.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/studio_packs.php';
require_once __DIR__ . '/scenes.php';
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/think.php';

function classify_make_intent(string $text): ?string
{
    $t = strtolower($text);
    $asksToMake = (bool) preg_match(
        '/\b(create|make|build|design|generate|draft|compose|code|spin up|put together|write|draw|paint)\b/i',
        $text
    );

    $kinds = [
        'video'    => ['title sequence', 'film title', 'motion piece'],
        'image'    => ['poster', 'logo', 'thumbnail', 'illustration'],
        'slides'   => ['powerpoint', 'power point', 'pptx', 'slide deck', 'slideshow'],
        'document' => ['word doc', 'word document', '.docx', 'full document', 'write a report', 'proposal', 'briefing'],
        'mobile'   => ['mobile app', 'ios app', 'android app', 'iphone app', 'phone app', 'mobile ui'],
        'website'  => ['website', 'landing page', 'web page', 'webpage', 'web app', 'homepage', 'microsite'],
    ];

    if ($asksToMake) {
        $kinds['video'][] = 'video';
        $kinds['video'][] = 'animation';
        $kinds['video'][] = 'cinematic';
        $kinds['image'][] = 'image';
        $kinds['image'][] = 'picture';
        $kinds['image'][] = 'photo';
        $kinds['slides'][] = 'slides';
        $kinds['slides'][] = 'presentation';
        $kinds['document'][] = 'pdf';
        $kinds['document'][] = 'document';
        $kinds['document'][] = 'report';
        $kinds['document'][] = 'essay';
        $kinds['document'][] = 'memo';
        $kinds['website'][] = 'html';
        $kinds['website'][] = 'css';
    }

    foreach ($kinds as $kind => $hints) {
        foreach ($hints as $hint) {
            if (str_contains($t, $hint)) {
                return $kind;
            }
        }
    }

    if ($asksToMake && preg_match('/\bapp\b/i', $text)) {
        return 'mobile';
    }
    if ($asksToMake && preg_match('/\b(shop|store|cafe|café|restaurant|brand|hotel|gym|salon|studio)\b/i', $text)) {
        return 'website';
    }

    return null;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function collect_brief(string $prompt, array $history = []): string
{
    $prompt = trim($prompt);
    if (mb_strlen($prompt) >= 24) {
        return $prompt;
    }
    $prev = '';
    foreach (array_reverse($history) as $m) {
        if (($m['role'] ?? '') !== 'user') {
            continue;
        }
        $text = trim((string) $m['content']);
        if ($text !== '' && $text !== $prompt) {
            $prev = $text;
            break;
        }
    }
    return trim($prev . "\n" . $prompt);
}

function artifact_dir(int $userId): string
{
    $dir = ARTIFACT_DIR . '/' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function save_artifact(int $userId, int $conversationId, int $messageId, string $kind, string $title, string $html): array
{
    $stored = bin2hex(random_bytes(16)) . '.html';
    file_put_contents(artifact_dir($userId) . '/' . $stored, $html);

    $stmt = db()->prepare(
        'INSERT INTO artifacts (user_id, conversation_id, message_id, kind, title, stored_name) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$userId, $conversationId, $messageId, $kind, $title, $stored]);
    $id = (int) db()->lastInsertId();

    return [
        'id'    => $id,
        'kind'  => $kind,
        'title' => $title,
        'url'   => 'api/artifact.php?id=' . $id,
    ];
}

function make_artifact(string $prompt, string $kind, int $userId, int $conversationId, int $messageId, array $history = [], string $mode = 'think'): array
{
    return think_then_make($prompt, $kind, $userId, $conversationId, $messageId, $history, $mode);
}

function reply_for_make(string $kind, string $title): string
{
    $labels = [
        'website'  => 'website',
        'mobile'   => 'mobile app',
        'image'    => 'image',
        'video'    => 'motion piece',
        'document' => 'document',
        'slides'   => 'slide deck',
    ];
    $label = $labels[$kind] ?? 'piece';
    return 'Here’s the ' . $label . ' for ' . $title . '. Open the preview.';
}
