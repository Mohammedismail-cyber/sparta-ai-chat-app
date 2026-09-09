<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit;
}

$stmt = db()->prepare('SELECT * FROM attachments WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    exit;
}

$path = attachment_disk_path($attachment);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$disposition = $attachment['kind'] === 'image' ? 'inline' : 'attachment';
$safeName = preg_replace('/[^\w.\-]/', '_', $attachment['original_name']);

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

readfile($path);
