<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/think.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$conversationId = (int)($_GET['conversation_id'] ?? 0);
if (!$conversationId) {
    json_response(['ok' => false, 'error' => 'Missing conversation_id.'], 422);
}

// Confirm ownership before returning anything.
$stmt = db()->prepare('SELECT id, title, project_id FROM conversations WHERE id = ? AND user_id = ?');
$stmt->execute([$conversationId, $user['id']]);
$conversation = $stmt->fetch();
if (!$conversation) {
    json_response(['ok' => false, 'error' => 'Conversation not found.'], 404);
}

$stmt = db()->prepare(
    'SELECT id, role, content, created_at FROM messages
     WHERE conversation_id = ? ORDER BY id ASC'
);
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

// Attach any files/images to their message in one extra query.
$msgIds = array_column($messages, 'id');
$attachmentsByMessage = [];
if ($msgIds) {
    $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
    $stmt = db()->prepare("SELECT id, message_id, original_name, mime_type, kind FROM attachments WHERE message_id IN ($placeholders)");
    $stmt->execute($msgIds);
    foreach ($stmt->fetchAll() as $att) {
        $attachmentsByMessage[$att['message_id']][] = [
            'id' => (int) $att['id'],
            'original_name' => $att['original_name'],
            'mime_type' => $att['mime_type'],
            'kind' => $att['kind'],
            'url' => 'api/file.php?id=' . $att['id'],
        ];
    }
}

$artifactsByMessage = [];
if ($msgIds) {
    $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
    $stmt = db()->prepare("SELECT id, message_id, kind, title FROM artifacts WHERE message_id IN ($placeholders)");
    $stmt->execute($msgIds);
    foreach ($stmt->fetchAll() as $art) {
        $artifactsByMessage[$art['message_id']] = [
            'id' => (int) $art['id'],
            'kind' => $art['kind'],
            'title' => $art['title'],
            'url' => 'api/artifact.php?id=' . (int) $art['id'],
        ];
    }
}

foreach ($messages as &$m) {
    if (($m['role'] ?? '') === 'assistant') {
        $m['content'] = public_reply((string) $m['content']);
    }
    $m['attachments'] = $attachmentsByMessage[$m['id']] ?? [];
    $m['artifact'] = $artifactsByMessage[$m['id']] ?? null;
}
unset($m);

json_response([
    'ok' => true,
    'conversation' => $conversation,
    'messages' => $messages,
]);
