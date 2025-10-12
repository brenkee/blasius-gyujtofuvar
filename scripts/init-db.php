<?php
declare(strict_types=1);


/**
 * Initialise the SQLite database for the Gyűjtőfuvar application.
 *
 * @param array{
 *   base_dir?: string,
 *   db_path?: string,
 *   schema_file?: string,
 *   migrations_dir?: string,
 *   seed_file?: string,
 *   seed?: bool|null,
 *   quiet?: bool
 * } $options
 *
 * @return array{created: bool, migrations: array<int, string>, seeded: bool, db_path: string}
 */
function init_app_database(array $options = []): array {
    $baseDir = isset($options['base_dir']) ? (string)$options['base_dir'] : dirname(__DIR__);
    $dbPath = isset($options['db_path']) ? (string)$options['db_path'] : $baseDir . '/data/app.db';
    $schemaFile = isset($options['schema_file']) ? (string)$options['schema_file'] : $baseDir . '/db/schema.sql';
    $migrationsDir = isset($options['migrations_dir']) ? (string)$options['migrations_dir'] : $baseDir . '/db/migrations';
    $seedFile = isset($options['seed_file']) ? (string)$options['seed_file'] : $baseDir . '/db/seed.sql';
    $seedPreference = $options['seed'] ?? null;

    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
            throw new RuntimeException('Nem sikerült létrehozni a(z) "' . $dataDir . '" könyvtárat.');
        }
    }

    $created = !is_file($dbPath);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

    // Global PRAGMA-k.
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    if (is_file($schemaFile)) {
        execute_sql_file($pdo, $schemaFile);
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        migration TEXT PRIMARY KEY,
        executed_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $applied = [];
    $stmt = $pdo->query('SELECT migration FROM schema_migrations');
    if ($stmt !== false) {
        foreach ($stmt as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string)($row['migration'] ?? '');
            if ($name !== '') {
                $applied[$name] = true;
            }
        }
    }

    $executedMigrations = [];
    if (is_dir($migrationsDir)) {
        $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql');
        if ($files !== false) {
            sort($files, SORT_NATURAL);
            foreach ($files as $file) {
                $name = basename((string)$file);
                if ($name === '' || isset($applied[$name])) {
                    continue;
                }
                $pdo->beginTransaction();
                try {
                    execute_sql_file($pdo, (string)$file);
                    $ins = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                    $ins->execute([':migration' => $name]);
                    $pdo->commit();
                    $executedMigrations[] = $name;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
    }

    $shouldSeed = false;
    if ($seedPreference === true) {
        $shouldSeed = true;
    } elseif ($seedPreference === null) {
        $shouldSeed = database_is_empty($pdo);
    }

    $seeded = false;
    if ($shouldSeed && is_file($seedFile) && filesize($seedFile) > 0) {
        $pdo->beginTransaction();
        try {
            execute_sql_file($pdo, $seedFile);
            $pdo->commit();
            $seeded = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    return [
        'created' => $created,
        'migrations' => $executedMigrations,
        'seeded' => $seeded,
        'db_path' => $dbPath,
    ];
}

/**
 * Execute the SQL commands contained in a file.
 */
function execute_sql_file(PDO $pdo, string $file): void {
    if (!is_file($file)) {
        throw new RuntimeException('A(z) "' . $file . '" SQL fájl nem található.');
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Nem sikerült beolvasni az SQL fájlt: ' . $file);
    }
    if (trim($sql) === '') {
        return;
    }
    $pdo->exec($sql);
}

/**
 * Determine whether the data tables are empty.
 */
function database_is_empty(PDO $pdo): bool {
    $tables = ['items', 'round_meta'];
    foreach ($tables as $table) {
        $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = :name");
        $existsStmt->execute([':name' => $table]);
        if ((int)$existsStmt->fetchColumn() === 0) {
            continue;
        }
        $countStmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
        if ($countStmt !== false && (int)$countStmt->fetchColumn() > 0) {
            return false;
        }
    }
    return true;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $result = init_app_database();
        $messages = [];
        $messages[] = $result['created'] ? 'Új adatbázis készült.' : 'Meglévő adatbázis frissítve.';
        if (!empty($result['migrations'])) {
            $messages[] = 'Futtatott migrációk: ' . implode(', ', $result['migrations']);
        } else {
            $messages[] = 'Nem volt új migráció.';
        }
        $messages[] = $result['seeded'] ? 'Minta adatok betöltve.' : 'Minta adatok nem kerültek betöltésre.';
        echo implode(PHP_EOL, $messages) . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Adatbázis inicializációs hiba: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
