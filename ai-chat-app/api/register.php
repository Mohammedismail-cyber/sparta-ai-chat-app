<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$in = json_input();

if (!verify_csrf($in['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Invalid or expired form token. Refresh and try again.'], 419);
}

$name = trim((string)($in['name'] ?? ''));
$email = strtolower(trim((string)($in['email'] ?? '')));
$password = (string)($in['password'] ?? '');

if ($name === '' || strlen($name) > 80) {
    json_response(['ok' => false, 'error' => 'Enter a name between 1 and 80 characters.'], 422);
}
if (!valid_email($email)) {
    json_response(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}
if (!valid_password($password)) {
    json_response(['ok' => false, 'error' => 'Password must be at least 8 characters.'], 422);
}

$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_response(['ok' => false, 'error' => 'An account with that email already exists.'], 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
$stmt->execute([$name, $email, $hash]);
$userId = (int) db()->lastInsertId();

// Log the user straight in after signup.
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['prev_login_at'] = null;

$now = date('Y-m-d H:i:s');
$stmt = db()->prepare('UPDATE users SET last_login_at = ?, last_login_ip = ? WHERE id = ?');
$stmt->execute([$now, client_ip(), $userId]);
record_login_history($userId);

json_response(['ok' => true, 'redirect' => 'chat.php']);
