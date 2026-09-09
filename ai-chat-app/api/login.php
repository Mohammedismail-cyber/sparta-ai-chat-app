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

$email = strtolower(trim((string)($in['email'] ?? '')));
$password = (string)($in['password'] ?? '');

if (!valid_email($email) || $password === '') {
    json_response(['ok' => false, 'error' => 'Enter your email and password.'], 422);
}

if (is_rate_limited($email)) {
    json_response(['ok' => false, 'error' => 'Too many failed attempts. Try again in a few minutes.'], 429);
}

$stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    record_login_attempt($email, false);
    json_response(['ok' => false, 'error' => 'Incorrect email or password.'], 401);
}

record_login_attempt($email, true);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

$previousLoginAt = $user['last_login_at'];
$_SESSION['prev_login_at'] = $previousLoginAt;

$now = date('Y-m-d H:i:s');
$stmt = db()->prepare('UPDATE users SET last_login_at = ?, last_login_ip = ? WHERE id = ?');
$stmt->execute([$now, client_ip(), $user['id']]);
record_login_history((int) $user['id']);

json_response([
    'ok' => true,
    'redirect' => 'chat.php',
    'previous_login_at' => $previousLoginAt,
]);
