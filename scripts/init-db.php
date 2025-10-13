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
 *   fresh?: bool
 * } $options
 *
 * @return array{created: bool, migrations: array<int, string>, seeded: bool, db_path: string, logs: array<int, string>}
 */
function init_app_database(array $options = []): array {
    $baseDir = isset($options['base_dir']) ? (string)$options['base_dir'] : dirname(__DIR__);
    $dbPath = isset($options['db_path']) ? (string)$options['db_path'] : $baseDir . '/data/app.db';
    $schemaFile = isset($options['schema_file']) ? (string)$options['schema_file'] : $baseDir . '/db/schema.sql';
    $migrationsDir = isset($options['migrations_dir']) ? (string)$options['migrations_dir'] : $baseDir . '/db/migrations';
    $seedFile = isset($options['seed_file']) ? (string)$options['seed_file'] : $baseDir . '/db/seed.sql';
    $seedPreference = $options['seed'] ?? null;
    $fresh = !empty($options['fresh']);

    $logs = [];
    $logger = function (string $message) use (&$logs): void {
        $logs[] = $message;
    };

    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
            throw new RuntimeException('Nem sikerült létrehozni a(z) "' . $dataDir . '" könyvtárat.');
        }
    }

    if ($fresh && is_file($dbPath)) {
        $logger('meglévő adatbázis törlése (--fresh): ' . $dbPath);
        if (!unlink($dbPath)) {
            throw new RuntimeException('Nem sikerült törölni a meglévő adatbázist: ' . $dbPath);
        }
        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $dbPath . $suffix;
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
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
        execute_sql_file($pdo, $schemaFile, $logger);
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
                if ($name === '') {
                    continue;
                }
                if (isset($applied[$name])) {
                    $logger('migráció kihagyva – már rögzítve: ' . $name);
                    continue;
                }
                $pdo->beginTransaction();
                try {
                    execute_sql_file($pdo, (string)$file, $logger);
                    $ins = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                    $ins->execute([':migration' => $name]);
                    $pdo->commit();
                    $executedMigrations[] = $name;
                    $logger('migráció alkalmazva: ' . $name);
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
            $logger('Minta adatok betöltése megkezdve.');
            execute_sql_file($pdo, $seedFile, $logger);
            $pdo->commit();
            $seeded = true;
            $logger('Minta adatok betöltve.');
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
        'logs' => $logs,
    ];
}

/**
 * Execute the SQL commands contained in a file.
 */
function execute_sql_file(PDO $pdo, string $file, ?callable $logger = null): array {
    if (!is_file($file)) {
        throw new RuntimeException('A(z) "' . $file . '" SQL fájl nem található.');
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Nem sikerült beolvasni az SQL fájlt: ' . $file);
    }
    if (trim($sql) === '') {
        return ['executed' => [], 'skipped' => []];
    }
    $statements = split_sql_statements($sql);
    $executed = [];
    $skipped = [];
    foreach ($statements as $statement) {
        $normalized = trim($statement);
        if ($normalized === '') {
            continue;
        }

        $skipMessage = maybe_skip_alter_table_add_column($pdo, $normalized);
        if ($skipMessage !== null) {
            $skipped[] = $skipMessage;
            if ($logger !== null) {
                $logger($skipMessage);
            }
            continue;
        }

        $pdo->exec($normalized);
        $executed[] = $normalized;
    }

    return ['executed' => $executed, 'skipped' => $skipped];
}

/**
 * Determine if an ALTER TABLE ... ADD COLUMN statement should be skipped.
 *
 * @return string|null A log message describing the skip, or null to execute.
 */
function maybe_skip_alter_table_add_column(PDO $pdo, string $statement): ?string {
    if (!preg_match('/^ALTER\s+TABLE\s+([^\s]+)\s+ADD\s+COLUMN\s+([^\s]+)\b/i', $statement, $matches)) {
        return null;
    }

    $table = normalize_sqlite_identifier($matches[1]);
    $column = normalize_sqlite_identifier($matches[2]);

    if ($table === null || $column === null) {
        return null;
    }

    if (sqlite_table_has_column($pdo, $table, $column)) {
        return 'migráció kihagyva – oszlop már létezik: ' . $table . '.' . $column;
    }

    return null;
}

/**
 * Normalise a SQLite identifier by removing quoting if applicable.
 */
function normalize_sqlite_identifier(string $identifier): ?string {
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }

    if (($identifier[0] === '"' && str_ends_with($identifier, '"')) ||
        ($identifier[0] === '`' && str_ends_with($identifier, '`')) ||
        ($identifier[0] === '[' && str_ends_with($identifier, ']'))
    ) {
        $identifier = substr($identifier, 1, -1);
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        return null;
    }

    return $identifier;
}

/**
 * Check if a table contains a specific column.
 */
function sqlite_table_has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    if ($stmt === false) {
        return false;
    }
    foreach ($stmt as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string)($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Split raw SQL into individual statements.
 *
 * @return list<string>
 */
function split_sql_statements(string $sql): array {
    $length = strlen($sql);
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $inBlockComment = false;
            }
            continue;
        }

        if ($char === '-' && $next === '-' && !$inSingle && !$inDouble) {
            $buffer .= $char;
            $buffer .= $next;
            $i++;
            $inLineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*' && !$inSingle && !$inDouble) {
            $buffer .= $char;
            $buffer .= $next;
            $i++;
            $inBlockComment = true;
            continue;
        }

        if ($char === "'" && !$inDouble) {
            $inSingle = !$inSingle;
            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingle) {
            $inDouble = !$inDouble;
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
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
        $fresh = in_array('--fresh', $argv ?? [], true);
        $result = init_app_database(['fresh' => $fresh]);
        $messages = $result['logs'] ?? [];
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
