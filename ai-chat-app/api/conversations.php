<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $projectFilter = $_GET['project_id'] ?? null;

    $sql = 'SELECT id, title, project_id, created_at, updated_at FROM conversations WHERE user_id = ?';
    $params = [$user['id']];

    if ($projectFilter === 'none') {
        $sql .= ' AND project_id IS NULL';
    } elseif ($projectFilter !== null && $projectFilter !== '') {
        $sql .= ' AND project_id = ?';
        $params[] = (int) $projectFilter;
    }

    $sql .= ' ORDER BY updated_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(['ok' => true, 'conversations' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $in = json_input();
    if (!verify_csrf($in['csrf'] ?? null)) {
        json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
    }

    // Reassign an existing conversation's project (used by the topbar "Move to project" menu).
    if (($in['action'] ?? '') === 'move') {
        $convId = (int)($in['id'] ?? 0);
        $projectId = !empty($in['project_id']) ? (int) $in['project_id'] : null;

        $stmt = db()->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
        $stmt->execute([$convId, $user['id']]);
        if (!$stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Conversation not found.'], 404);
        }
        if ($projectId) {
            $stmt = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
            $stmt->execute([$projectId, $user['id']]);
            if (!$stmt->fetch()) {
                json_response(['ok' => false, 'error' => 'Project not found.'], 404);
            }
        }

        $stmt = db()->prepare('UPDATE conversations SET project_id = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$projectId, $convId, $user['id']]);
        json_response(['ok' => true, 'project_id' => $projectId]);
    }

    $title = trim((string)($in['title'] ?? 'New chat')) ?: 'New chat';
    $title = mb_substr($title, 0, 80);
    $projectId = !empty($in['project_id']) ? (int) $in['project_id'] : null;

    if ($projectId) {
        $stmt = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
        $stmt->execute([$projectId, $user['id']]);
        if (!$stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Project not found.'], 404);
        }
    }

    $stmt = db()->prepare('INSERT INTO conversations (user_id, project_id, title) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $projectId, $title]);
    $id = (int) db()->lastInsertId();

    json_response(['ok' => true, 'conversation' => [
        'id' => $id,
        'title' => $title,
        'project_id' => $projectId,
    ]]);
}

if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $params);
    $id = (int)($params['id'] ?? ($_GET['id'] ?? 0));
    $csrf = $params['csrf'] ?? ($_GET['csrf'] ?? null);

    if (!verify_csrf($csrf)) {
        json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
    }
    if (!$id) {
        json_response(['ok' => false, 'error' => 'Missing conversation id.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);

    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
