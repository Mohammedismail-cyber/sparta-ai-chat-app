<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(422);
    exit('Missing id');
}

$stmt = db()->prepare('SELECT * FROM artifacts WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$path = ARTIFACT_DIR . '/' . $user['id'] . '/' . $row['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Missing file');
}

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=120');
readfile($path);
exit;
