<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/llm.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/make.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$in = json_input();

if (!verify_csrf($in['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
}

$content = trim((string)($in['message'] ?? ''));
$conversationId = (int)($in['conversation_id'] ?? 0);
$attachmentIds = array_values(array_unique(array_filter(array_map('intval', $in['attachment_ids'] ?? []))));
$mode = strtolower((string)($in['mode'] ?? 'think')) === 'fast' ? 'fast' : 'think';

if ($content === '' && empty($attachmentIds)) {
    json_response(['ok' => false, 'error' => 'Message cannot be empty.'], 422);
}
if (mb_strlen($content) > 8000) {
    json_response(['ok' => false, 'error' => 'That message is too long.'], 422);
}

$pdo = db();

// Create a conversation on first message if none was supplied.
if (!$conversationId) {
    $title = $content !== '' ? mb_substr($content, 0, 60) : 'New chat';
    $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title) VALUES (?, ?)');
    $stmt->execute([$user['id'], $title]);
    $conversationId = (int) $pdo->lastInsertId();
} else {
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $user['id']]);
    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Conversation not found.'], 404);
    }
}

// Store the user's message.
$stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
$stmt->execute([$conversationId, 'user', $content]);
$messageId = (int) $pdo->lastInsertId();

// Link any pending attachments (uploaded moments ago, not yet attached to a message) to this message.
if (!empty($attachmentIds)) {
    $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
    $stmt = $pdo->prepare(
        "UPDATE attachments SET conversation_id = ?, message_id = ?
         WHERE id IN ($placeholders) AND user_id = ? AND message_id IS NULL"
    );
    $stmt->execute(array_merge([$conversationId, $messageId], $attachmentIds, [$user['id']]));
}

// Pull recent history (bounded window) to give the model context.
$stmt = $pdo->prepare(
    'SELECT id, role, content FROM messages WHERE conversation_id = ?
     ORDER BY id DESC LIMIT 8'
);
$stmt->execute([$conversationId]);
$recent = array_reverse($stmt->fetchAll());

// Pull attachments for every message in this window in one query.
$msgIds = array_column($recent, 'id');
$attachmentsByMessage = [];
if ($msgIds) {
    $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE message_id IN ($placeholders)");
    $stmt->execute($msgIds);
    foreach ($stmt->fetchAll() as $att) {
        $attachmentsByMessage[$att['message_id']][] = $att;
    }
}

// Build the payload for the model: attach base64 images, and note any
// non-image files by name so even a text-only model knows they exist.
foreach ($recent as &$m) {
    $atts = $attachmentsByMessage[$m['id']] ?? [];
    if (!$atts) {
        continue;
    }
    $images = [];
    $fileNotes = [];
    foreach ($atts as $att) {
        if ($att['kind'] === 'image') {
            $path = attachment_disk_path($att);
            if (is_file($path)) {
                $images[] = base64_encode(file_get_contents($path));
            }
        } else {
            $fileNotes[] = $att['original_name'];
        }
    }
    if ($images) {
        $m['images'] = $images;
    }
    if ($fileNotes) {
        $m['content'] = trim($m['content'] . "\n\n[Attached file(s): " . implode(', ', $fileNotes) . ']');
    }
}
unset($m);

if (function_exists('set_time_limit')) {
    @set_time_limit(360);
}

$makeKind = classify_make_intent($content);
$artifact = null;

if ($makeKind) {
    $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, 'assistant', 'Working on that…']);
    $assistantId = (int) $pdo->lastInsertId();
    try {
        $built = make_artifact($content, $makeKind, (int) $user['id'], $conversationId, $assistantId, $recent, $mode);
        $artifact = $built['artifact'] ?? null;
        $reply = public_reply($built['reply'] ?? reply_for_make($makeKind, $artifact['title'] ?? 'this brief'));
        $stmt = $pdo->prepare('UPDATE messages SET content = ? WHERE id = ?');
        $stmt->execute([$reply, $assistantId]);
    } catch (Throwable $e) {
        $reply = "Couldn't finish that preview. Try a shorter brief, then send it again.";
        $stmt = $pdo->prepare('UPDATE messages SET content = ? WHERE id = ?');
        $stmt->execute([$reply, $assistantId]);
        $artifact = null;
    }
} else {
    $hasImages = false;
    foreach ($recent as $m) {
        if (!empty($m['images'])) {
            $hasImages = true;
            break;
        }
    }

    try {
        $reply = ask_with_thinking($recent, $content, $hasImages, $mode);
    } catch (LlmException $e) {
        json_response(['ok' => false, 'error' => $e->getMessage()], 502);
    }

    $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, 'assistant', $reply]);
}

$stmt = $pdo->prepare("UPDATE conversations SET updated_at = datetime('now') WHERE id = ?");
$stmt->execute([$conversationId]);

// Return the attachments actually saved against this message, for the UI to render.
$sentAttachments = array_map(function ($a) {
    return [
        'id' => (int) $a['id'],
        'original_name' => $a['original_name'],
        'mime_type' => $a['mime_type'],
        'kind' => $a['kind'],
        'url' => 'api/file.php?id=' . $a['id'],
    ];
}, $attachmentsByMessage[$messageId] ?? []);

json_response([
    'ok' => true,
    'conversation_id' => $conversationId,
    'reply' => $reply,
    'attachments' => $sentAttachments,
    'artifact' => $artifact,
]);
