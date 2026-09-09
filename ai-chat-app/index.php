<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

header('Location: ' . (current_user() ? 'chat.php' : 'login.php'));
exit;
