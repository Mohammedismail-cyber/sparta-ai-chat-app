<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$in = json_input();
$csrf = $in['csrf'] ?? ($_POST['csrf'] ?? null);
if (!verify_csrf($csrf)) {
    json_response(['ok' => false, 'error' => 'Invalid form token.'], 419);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
}
session_destroy();

json_response(['ok' => true, 'redirect' => 'login.php']);
