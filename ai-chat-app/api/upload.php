<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!verify_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
}

if (empty($_FILES['file'])) {
    json_response(['ok' => false, 'error' => 'No file received.'], 422);
}

$conversationId = (int)($_POST['conversation_id'] ?? 0) ?: null;

// If a conversation id was supplied, make sure the user actually owns it.
if ($conversationId) {
    $stmt = db()->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $user['id']]);
    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Conversation not found.'], 404);
    }
}

try {
    $attachment = store_uploaded_file($_FILES['file'], (int) $user['id'], $conversationId);
} catch (UploadException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}

json_response([
    'ok' => true,
    'attachment' => $attachment + [
        'url' => 'api/file.php?id=' . $attachment['id'],
    ],
]);
