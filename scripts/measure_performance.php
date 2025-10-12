<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../common.php';

/**
 * Compute a filesystem path relative to a base directory.
 */
function relative_path(string $from, string $to): string
{
    $from = str_replace('\\', '/', rtrim($from, '/'));
    $to = str_replace('\\', '/', rtrim($to, '/'));
    $fromParts = $from === '' ? [] : explode('/', $from);
    $toParts = $to === '' ? [] : explode('/', $to);

    while ($fromParts && $toParts && $fromParts[0] === $toParts[0]) {
        array_shift($fromParts);
        array_shift($toParts);
    }

    $relative = array_fill(0, count($fromParts), '..');
    $relative = array_merge($relative, $toParts);
    $relPath = implode('/', $relative);
    return $relPath === '' ? '.' : $relPath;
}

/**
 * Run the supplied callback against an isolated copy of the datastore files so
 * that measurements do not mutate the active environment.
 *
 * @template T
 * @param callable():T $callback
 * @return T
 */
function with_isolated_store(callable $callback)
{
    global $DATA_FILE, $STATE_LOCK_FILE, $REVISION_FILE, $CHANGE_LOG_FILE, $ARCHIVE_FILE, $CFG, $DATA_BOOTSTRAP_INFO, $DATA_INIT_ERROR;

    $originals = [
        'DATA_FILE' => $DATA_FILE,
        'STATE_LOCK_FILE' => $STATE_LOCK_FILE,
        'REVISION_FILE' => $REVISION_FILE,
        'CHANGE_LOG_FILE' => $CHANGE_LOG_FILE,
        'ARCHIVE_FILE' => $ARCHIVE_FILE,
        'CFG' => $CFG,
        'DATA_BOOTSTRAP_INFO' => $DATA_BOOTSTRAP_INFO,
        'DATA_INIT_ERROR' => $DATA_INIT_ERROR,
    ];

    $tmpBase = sys_get_temp_dir() . '/fuvar_perf_' . bin2hex(random_bytes(6));
    if (!mkdir($tmpBase, 0775, true) && !is_dir($tmpBase)) {
        throw new RuntimeException('Nem sikerült ideiglenes könyvtárat létrehozni.');
    }

    $cleanupPaths = [];
    $copyIfExists = function (string $source, string $target) use (&$cleanupPaths): void {
        if (is_file($source)) {
            if (!copy($source, $target)) {
                throw new RuntimeException('Nem sikerült fájlt másolni: ' . $source);
            }
        } else {
            touch($target);
        }
        $cleanupPaths[] = $target;
    };

    $DATA_BOOTSTRAP_INFO = [];
    $DATA_INIT_ERROR = null;

    $dataCopy = $tmpBase . '/app.db';
    if (!array_key_exists('DATA_FILE', $GLOBALS)) {
        throw new RuntimeException('DATA_FILE global missing.');
    }
    $originalDataFile = $GLOBALS['DATA_FILE'] ?? null;
    if (!is_string($originalDataFile) || $originalDataFile === '') {
        throw new RuntimeException('DATA_FILE is not initialised.');
    }
    $copyIfExists($originalDataFile, $dataCopy);
    foreach (['-wal', '-shm'] as $suffix) {
        $src = $DATA_FILE . $suffix;
        if (is_file($src)) {
            $copyIfExists($src, $dataCopy . $suffix);
        }
    }

    $STATE_LOCK_FILE = $tmpBase . '/state.lock';
    $REVISION_FILE = $tmpBase . '/revision.json';
    $CHANGE_LOG_FILE = $tmpBase . '/changes.log';
    $ARCHIVE_FILE = $tmpBase . '/archive.log';
    $copyIfExists($originals['STATE_LOCK_FILE'], $STATE_LOCK_FILE);
    $copyIfExists($originals['REVISION_FILE'], $REVISION_FILE);
    $copyIfExists($originals['CHANGE_LOG_FILE'], $CHANGE_LOG_FILE);
    $copyIfExists($originals['ARCHIVE_FILE'], $ARCHIVE_FILE);
    $DATA_FILE = $dataCopy;

    $backupDir = sys_get_temp_dir() . '/fuvar_perf_backups_' . bin2hex(random_bytes(5));
    if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Nem sikerült ideiglenes backup könyvtárat létrehozni.');
    }
    $cleanupPaths[] = $backupDir;

    $CFG['backup']['dir'] = relative_path(__DIR__, $backupDir);
    $CFG['backup']['enabled'] = true;

    try {
        $result = $callback();
    } finally {
        // Restore globals.
        $DATA_FILE = $originals['DATA_FILE'];
        $STATE_LOCK_FILE = $originals['STATE_LOCK_FILE'];
        $REVISION_FILE = $originals['REVISION_FILE'];
        $CHANGE_LOG_FILE = $originals['CHANGE_LOG_FILE'];
        $ARCHIVE_FILE = $originals['ARCHIVE_FILE'];
        $CFG = $originals['CFG'];
        $DATA_BOOTSTRAP_INFO = $originals['DATA_BOOTSTRAP_INFO'];
        $DATA_INIT_ERROR = $originals['DATA_INIT_ERROR'];

        foreach (array_reverse($cleanupPaths) as $path) {
            if (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        @rmdir($file->getRealPath());
                    } else {
                        @unlink($file->getRealPath());
                    }
                }
                @rmdir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    return $result;
}

/**
 * Identify a deletable round within the provided dataset.
 */
function find_round_with_items(array $items): ?int
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $rid = (int)($item['round'] ?? 0);
        if ($rid !== 0) {
            return $rid;
        }
    }
    return null;
}

/**
 * Measure the cost of deleting a round with and without the legacy extra save call.
 *
 * @return array{round:int, removed:int, baseline:float, optimized:float}
 */
function append_change_events_measure($rev, string $actorId, string $requestId, ?string $batchId, array $events, array $meta = []): void
{
    foreach ($events as $event) {
        $log = [
            'rev' => $rev,
            'entity' => $event['entity'] ?? 'dataset',
            'entity_id' => $event['entity_id'] ?? null,
            'action' => $event['action'] ?? 'updated',
            'ts' => gmdate('c'),
            'actor_id' => $actorId,
            'request_id' => $requestId,
        ];
        if ($batchId) {
            $log['batch_id'] = $batchId;
        }
        $metaPayload = $event['meta'] ?? [];
        if (!empty($meta)) {
            $metaPayload = array_merge($metaPayload, $meta);
        }
        if (!empty($metaPayload)) {
            $log['meta'] = $metaPayload;
        }
        append_change_log_locked($log);
    }
}

function append_change_log_legacy_measure(array $entry, string $path): void
{
    if (!isset($entry['ts'])) {
        $entry['ts'] = gmdate('c');
    }

    $maxAgeSeconds = 86400;
    $cutoff = time() - $maxAgeSeconds;
    $newLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $fh = @fopen($path, 'c+');
    if (!$fh) {
        file_put_contents($path, $newLine . "\n", FILE_APPEND | LOCK_EX);
        return;
    }

    $retained = [];
    if (flock($fh, LOCK_EX)) {
        rewind($fh);
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $keep = true;
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['ts'])) {
                $ts = strtotime((string)$decoded['ts']);
                if ($ts !== false && $ts < $cutoff) {
                    $keep = false;
                }
            }

            if ($keep) {
                $retained[] = $line;
            }
        }

        $retained[] = $newLine;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, implode("\n", $retained) . "\n");
        fflush($fh);
        flock($fh, LOCK_UN);
    } else {
        file_put_contents($path, $newLine . "\n", FILE_APPEND | LOCK_EX);
    }

    fclose($fh);
}

function seed_change_log_file(string $path, int $count, int $startRev = 1): void
{
    $fh = @fopen($path, 'w');
    if (!$fh) {
        return;
    }
    $baseTs = time() - 3600;
    for ($i = 0; $i < $count; $i++) {
        $entry = [
            'rev' => $startRev + $i,
            'entity' => 'seed',
            'entity_id' => 'seed_' . $i,
            'action' => 'updated',
            'ts' => gmdate('c', $baseTs + $i),
            'actor_id' => 'seed',
            'request_id' => 'seed_' . $i,
        ];
        fwrite($fh, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
    fclose($fh);
}

function commit_dataset_update_measure(array $newItems, array $newRoundMeta, string $actorId, string $requestId, ?string $batchId, string $action, array $actionMeta = []): array
{
    global $DATA_FILE;
    $dataFile = $DATA_FILE;
    return state_lock(function () use ($newItems, $newRoundMeta, $actorId, $requestId, $batchId, $action, $actionMeta, $dataFile) {
        [$oldItems, $oldRoundMeta] = data_store_read($dataFile);
        $writeOk = data_store_write($dataFile, $newItems, $newRoundMeta);
        if ($writeOk === false) {
            throw new RuntimeException('write_failed');
        }
        $currentRev = read_current_revision();
        $newRev = $currentRev + 1;
        write_revision_locked($newRev);
        $events = array_merge(
            compute_item_changes($oldItems, $newItems),
            compute_round_meta_changes($oldRoundMeta, $newRoundMeta)
        );
        if (empty($events)) {
            $events[] = ['entity' => 'dataset', 'entity_id' => null, 'action' => $action, 'meta' => $actionMeta];
        } else {
            $events = array_map(static function ($ev) use ($action) {
                if (!isset($ev['meta']) || !is_array($ev['meta'])) {
                    $ev['meta'] = [];
                }
                if (!isset($ev['meta']['source_action'])) {
                    $ev['meta']['source_action'] = $action;
                }
                return $ev;
            }, $events);
        }
        append_change_events_measure($newRev, $actorId, $requestId, $batchId, $events, $actionMeta);
        return ['rev' => $newRev, 'events' => $events];
    });
}

function measure_delete_round_flows(): array
{
    return with_isolated_store(function (): array {
        global $DATA_FILE, $CFG;

        [$items, $roundMeta] = data_store_read($DATA_FILE);
        if (!is_array($items) || count($items) === 0) {
            throw new RuntimeException('Nincsenek mérhető tételek az adatbázisban.');
        }

        $round = find_round_with_items($items);
        if ($round === null) {
            throw new RuntimeException('Nem található törölhető kör a mintában.');
        }

        $kept = array_values(array_filter($items, fn($it) => (int)($it['round'] ?? 0) !== $round));
        $metaCopy = $roundMeta;
        unset($metaCopy[(string)$round]);

        $actor = 'cli_profiler';
        $baselineStart = microtime(true);
        commit_dataset_update_measure($kept, $metaCopy, $actor, 'req_del_base_' . uniqid(), null, 'delete_round', [
            'round' => $round,
            'deleted_count' => count($items) - count($kept),
        ]);
        schedule_backup($CFG, $DATA_FILE);
        commit_dataset_update_measure($kept, $metaCopy, $actor, 'req_save_base_' . uniqid(), null, 'save', ['scope' => 'full_save']);
        schedule_backup($CFG, $DATA_FILE);
        $baseline = microtime(true) - $baselineStart;

        commit_dataset_update_measure($items, $roundMeta, $actor, 'req_restore_' . uniqid(), null, 'restore', []);
        schedule_backup($CFG, $DATA_FILE);

        $optimizedStart = microtime(true);
        $optimizedResult = delete_round_from_store($round, $actor, 'req_del_opt_' . uniqid(), null);
        schedule_backup($CFG, $DATA_FILE);
        $optimized = microtime(true) - $optimizedStart;

        commit_dataset_update_measure($items, $roundMeta, $actor, 'req_restore_opt_' . uniqid(), null, 'restore', []);
        schedule_backup($CFG, $DATA_FILE);

        return [
            'round' => $round,
            'removed' => count($items) - count($kept),
            'baseline' => $baseline,
            'optimized' => $optimized,
            'optimized_deleted' => (int)($optimizedResult['deleted_count'] ?? 0),
        ];
    });
}

function measure_change_log_append_cost(): array
{
    $suffix = function (): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(5));
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $parts[] = bin2hex(pack('N', mt_rand(0, PHP_INT_MAX)));
        }
        return implode('', $parts);
    };

    $tmpDir = sys_get_temp_dir() . '/gf_change_log_bench_' . $suffix();
    if (!is_dir($tmpDir)) {
        if (!@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Nem sikerült létrehozni a temporális mérési könyvtárat.');
        }
    }

    $legacyLog = $tmpDir . '/legacy.log';
    $optimizedLog = $tmpDir . '/optimized.log';
    $optimizedState = $tmpDir . '/optimized.state';
    $seedCount = 800;
    seed_change_log_file($legacyLog, $seedCount);
    seed_change_log_file($optimizedLog, $seedCount);

    $iterations = 150;
    $baselineStart = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $entry = [
            'rev' => $seedCount + $i + 1,
            'entity' => 'benchmark',
            'entity_id' => 'legacy_' . $i,
            'action' => 'updated',
            'actor_id' => 'bench',
            'request_id' => 'legacy_' . $i,
        ];
        append_change_log_legacy_measure($entry, $legacyLog);
    }
    $baseline = microtime(true) - $baselineStart;

    global $CHANGE_LOG_FILE, $CHANGE_LOG_STATE_FILE;
    $originalLog = $CHANGE_LOG_FILE;
    $originalState = $CHANGE_LOG_STATE_FILE;
    $CHANGE_LOG_FILE = $optimizedLog;
    $CHANGE_LOG_STATE_FILE = $optimizedState;
    if (is_file($optimizedState)) {
        @unlink($optimizedState);
    }

    $optimizedStart = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $entry = [
            'rev' => $seedCount + $iterations + $i + 1,
            'entity' => 'benchmark',
            'entity_id' => 'optimized_' . $i,
            'action' => 'updated',
            'actor_id' => 'bench',
            'request_id' => 'optimized_' . $i,
        ];
        append_change_log_locked($entry);
    }
    $optimized = microtime(true) - $optimizedStart;

    $CHANGE_LOG_FILE = $originalLog;
    $CHANGE_LOG_STATE_FILE = $originalState;

    foreach ([$legacyLog, $optimizedLog, $optimizedState] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($tmpDir);

    return [
        'iterations' => $iterations,
        'seed_count' => $seedCount,
        'legacy' => $baseline,
        'optimized' => $optimized,
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $result = measure_delete_round_flows();
        printf(
            "baseline_delete_plus_save=%.4f\noptimized_delete=%.4f\nround=%d\nremoved_items=%d\noptimized_deleted=%d\n",
            $result['baseline'],
            $result['optimized'],
            $result['round'],
            $result['removed'],
            $result['optimized_deleted']
        );

        $logBenchmark = measure_change_log_append_cost();
        printf(
            "change_log_legacy=%.4f\nchange_log_optimized=%.4f\nchange_log_iterations=%d\nchange_log_seed=%d\n",
            $logBenchmark['legacy'],
            $logBenchmark['optimized'],
            $logBenchmark['iterations'],
            $logBenchmark['seed_count']
        );
    } catch (Throwable $e) {
        fwrite(STDERR, 'Mérés nem sikerült: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

