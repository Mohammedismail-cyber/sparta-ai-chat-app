<?php
/**
 * Authentication + session security helpers.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);
    session_start();
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, created_at, last_login_at, last_login_ip, preferred_model, theme FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Basic brute-force throttling, keyed by email + IP. */
function is_rate_limited(string $email): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) as c FROM login_attempts
         WHERE email = ? AND success = 0
         AND created_at > datetime('now', ?)"
    );
    $stmt->execute([$email, '-' . LOGIN_LOCKOUT_SECONDS . ' seconds']);
    $row = $stmt->fetch();
    return (int)($row['c'] ?? 0) >= MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(string $email, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)');
    $stmt->execute([$email, client_ip(), $success ? 1 : 0]);
}

function record_login_history(int $userId): void
{
    $stmt = db()->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)');
    $stmt->execute([$userId, client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

/** Very small validation helpers — keep dependencies at zero. */
function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8;
}
