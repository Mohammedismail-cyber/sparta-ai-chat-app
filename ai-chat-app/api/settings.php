<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/theme.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'ok' => true,
        'name' => $user['name'],
        'email' => $user['email'],
        'theme' => normalize_theme($user['theme'] ?? 'paper'),
        'themes' => available_themes(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$in = json_input();
if (!verify_csrf($in['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
}

$name = trim((string) ($in['name'] ?? $user['name']));
if ($name === '' || strlen($name) > 80) {
    json_response(['ok' => false, 'error' => 'Enter a name between 1 and 80 characters.'], 422);
}
$theme = normalize_theme($in['theme'] ?? ($user['theme'] ?? 'paper'));

$stmt = db()->prepare('UPDATE users SET name = ?, theme = ? WHERE id = ?');
$stmt->execute([$name, $theme, $user['id']]);

json_response(['ok' => true, 'name' => $name, 'theme' => $theme]);
