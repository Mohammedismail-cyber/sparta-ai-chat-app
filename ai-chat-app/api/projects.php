<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.created_at,
                (SELECT COUNT(*) FROM conversations c WHERE c.project_id = p.id AND c.user_id = p.user_id) AS chat_count
         FROM projects p WHERE p.user_id = ? ORDER BY p.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    json_response(['ok' => true, 'projects' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $in = json_input();
    if (!verify_csrf($in['csrf'] ?? null)) {
        json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
    }

    $name = trim((string)($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 60) {
        json_response(['ok' => false, 'error' => 'Give the project a name (up to 60 characters).'], 422);
    }

    $stmt = db()->prepare('INSERT INTO projects (user_id, name) VALUES (?, ?)');
    $stmt->execute([$user['id'], $name]);
    $id = (int) db()->lastInsertId();

    json_response(['ok' => true, 'project' => ['id' => $id, 'name' => $name, 'chat_count' => 0]]);
}

if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $params);
    $id = (int)($params['id'] ?? ($_GET['id'] ?? 0));
    $csrf = $params['csrf'] ?? ($_GET['csrf'] ?? null);

    if (!verify_csrf($csrf)) {
        json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
    }
    if (!$id) {
        json_response(['ok' => false, 'error' => 'Missing project id.'], 422);
    }

    // Conversations in this project are kept — they just fall back to
    // "All chats" (project_id set to NULL via the FK's ON DELETE SET NULL).
    $stmt = db()->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);

    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
