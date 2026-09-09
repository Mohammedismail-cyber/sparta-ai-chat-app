<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/llm.php';

require_login();

$models = discover_local_models();
json_response([
    'ok'     => true,
    'online' => $models !== [],
]);
