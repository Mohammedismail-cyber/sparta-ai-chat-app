<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/llm.php';
require_once __DIR__ . '/../includes/specialists.php';

require_login();

if (function_exists('set_time_limit')) {
    @set_time_limit(60);
}

if (function_exists('prepare_python_image_backend')) {
    prepare_python_image_backend();
}

$ok = warmup_fast_model();
json_response(['ok' => true, 'warm' => $ok]);
