<?php
declare(strict_types=1);

require __DIR__ . '/../common.php';
require __DIR__ . '/../src/auth/session_guard.php';

use function App\Auth\auth_db;
use function App\Auth\hash_password;

$pdo = auth_db();

$schemaFile = __DIR__ . '/../db/schema_auth.sql';
if (is_file($schemaFile)) {
    $schemaSql = file_get_contents($schemaFile);
    if ($schemaSql !== false) {
        $pdo->exec($schemaSql);
    }
}

$countStmt = $pdo->query('SELECT COUNT(*) AS cnt FROM users');
$countRow = $countStmt ? $countStmt->fetch() : null;
$userCount = $countRow['cnt'] ?? 0;

if ((int) $userCount === 0) {
    $now = (new DateTimeImmutable('now'))->format(DATE_ATOM);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at, updated_at) VALUES (:username, :email, :password_hash, :created_at, :updated_at)');
    $stmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@example.com',
        ':password_hash' => hash_password('admin'),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    echo "Alapértelmezett admin felhasználó létrehozva (admin / admin). Első belépéskor kötelező a jelszócsere.\n";
} else {
    echo "A users tábla már tartalmaz adatokat ({$userCount} sor). Nem történt módosítás.\n";
}
