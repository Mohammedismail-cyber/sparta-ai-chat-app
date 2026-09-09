<?php
/**
 * Database connection + schema bootstrap.
 * Uses SQLite via PDO — no server, no setup, fully local.
 *
 * Schema changes go through migrate_schema() so upgrading an existing
 * app.sqlite (from an earlier version of this app) never silently
 * breaks — every migration is additive and idempotent.
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dataDir = dirname(DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    bootstrap_schema($pdo);
    migrate_schema($pdo);

    return $pdo;
}

function bootstrap_schema(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        last_login_at TEXT,
        last_login_ip TEXT,
        preferred_model TEXT,
        theme TEXT NOT NULL DEFAULT 'paper'
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        success INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS login_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        project_id INTEGER,
        title TEXT NOT NULL DEFAULT 'New chat',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id INTEGER NOT NULL,
        role TEXT NOT NULL CHECK (role IN ('user','assistant','system')),
        content TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    );
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        conversation_id INTEGER,
        message_id INTEGER,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        kind TEXT NOT NULL DEFAULT 'file',
        size_bytes INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    );
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_conv ON messages(conversation_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conversations_project ON conversations(project_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts(email, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attachments_message ON attachments(message_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attachments_conv ON attachments(conversation_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attachments_user_pending ON attachments(user_id, message_id)');

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS artifacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        conversation_id INTEGER,
        message_id INTEGER,
        kind TEXT NOT NULL,
        title TEXT NOT NULL DEFAULT '',
        stored_name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
    );
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_artifacts_message ON artifacts(message_id)');
}

/**
 * Additive, idempotent migrations for databases created by earlier
 * versions of this app (e.g. before projects/attachments existed).
 * Safe to run on every request — each step checks before acting.
 */
function migrate_schema(PDO $pdo): void
{
    $columns = fn(string $table) => array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');

    $convCols = $columns('conversations');
    if (!in_array('project_id', $convCols, true)) {
        $pdo->exec('ALTER TABLE conversations ADD COLUMN project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL');
    }

    $userCols = $columns('users');
    if (!in_array('preferred_model', $userCols, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN preferred_model TEXT');
    }
    if (!in_array('theme', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'paper'");
    }
}
