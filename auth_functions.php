<?php
declare(strict_types=1);

/**
 * Return a strong password hash for storage.
 */
function auth_secure_hash(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $hash = password_hash($password, $algo);
    if ($hash === false) {
        throw new RuntimeException('password_hash_failed');
    }
    return $hash;
}

/**
 * Ensure that the users table exists with the expected schema.
 */
function auth_ensure_users_table(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL COLLATE NOCASE UNIQUE,
        email TEXT,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime("now")),
        updated_at TEXT NOT NULL DEFAULT (datetime("now"))
    )');
}

/**
 * Create the default admin user if the users table is empty.
 */
function auth_bootstrap_default_admin(PDO $pdo): void
{
    auth_ensure_users_table($pdo);

    try {
        $pdo->beginTransaction();
        $countStmt = $pdo->query('SELECT COUNT(*) AS c FROM users');
        $count = (int)($countStmt ? $countStmt->fetchColumn() : 0);
        if ($count > 0) {
            $pdo->commit();
            return;
        }

        $now = gmdate('c');
        $hash = auth_secure_hash('admin');
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at, updated_at)
            VALUES (:username, :email, :password_hash, :created_at, :updated_at)');
        $stmt->execute([
            ':username' => 'admin',
            ':email' => '',
            ':password_hash' => $hash,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
