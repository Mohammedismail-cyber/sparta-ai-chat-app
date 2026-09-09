<?php
/**
 * Central configuration.
 * Everything here is local — no external services are contacted except
 * your own local model runtime (Ollama / LM Studio / etc.) on localhost.
 */

// ---- App ----
define('APP_NAME', 'Sparta');
define('APP_ENV', 'local'); // 'local' or 'production'

// ---- Database (SQLite — zero setup, single file on disk) ----
define('DB_PATH', __DIR__ . '/../data/app.sqlite');

// ---- Session / security ----
define('SESSION_NAME', 'sparta_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7); // 7 days
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 15 * 60); // 15 minutes

// ---- Local model runtime ----
// Auto-discovers every model Ollama has pulled. LLM_MODEL is only the
// fallback if discovery fails; the UI lets you switch between all of them.
define('LLM_PROVIDER', 'ollama');           // 'ollama' | 'openai_compatible'
define('LLM_BASE_URL', 'http://127.0.0.1:11434');
define('LLM_ENDPOINT', LLM_BASE_URL . '/api/chat');
define('LLM_MODEL', 'llama3.2:3b');         // fast default; routing may pick another
define('LLM_AUTO_DISCOVER', true);
define('LLM_TIMEOUT_SECONDS', 55);
define('LLM_MAKE_TIMEOUT_SECONDS', 300);
define('LLM_THINK_TIMEOUT_SECONDS', 180);
define('LLM_SPECIALIST_TIMEOUT_SECONDS', 300);
define('LLM_KEEP_ALIVE', '0');
define('LLM_FAST_KEEP_ALIVE', '60m');
define('A1111_BASE_URL', 'http://127.0.0.1:7860');
define('LLM_IMAGE_MODEL', 'x/flux2-klein');
define('LLM_TEMPERATURE', 0.65);
define('LLM_SYSTEM_PROMPT', 'You never dump a first-guess answer. Use the notes you were given. Write a complete, specific answer and finish every list. Do not invent that you are a hosted model. Do not mention Sparta as a brand unless the user asked for that name.');
define('LLM_VISION_CAPABLE', false); // overridden per-model when the name looks vision-capable

// Extra OpenAI-compatible endpoints to probe (LM Studio, llama.cpp, etc.)
define('LLM_EXTRA_ENDPOINTS', [
    'http://127.0.0.1:1234/v1/models',
    'http://127.0.0.1:8080/v1/models',
]);

// ---- Uploads (files, images, screenshots) ----
define('ARTIFACT_DIR', __DIR__ . '/../data/artifacts');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_MAX_BYTES', 20 * 1024 * 1024); // 20MB per file
define('UPLOAD_ALLOWED_MIME', [
    'image/png'       => 'png',
    'image/jpeg'      => 'jpg',
    'image/webp'      => 'webp',
    'image/gif'       => 'gif',
    'application/pdf' => 'pdf',
    'text/plain'      => 'txt',
    'text/markdown'   => 'md',
    'text/csv'        => 'csv',
    'application/json'=> 'json',
]);

// ---- Error visibility ----
if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

@ini_set('max_execution_time', '360');
if (function_exists('set_time_limit')) {
    @set_time_limit(360);
}
