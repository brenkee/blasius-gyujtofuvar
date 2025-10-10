<?php
require __DIR__ . '/common.php';

$sourceFile = __DIR__ . '/data/data.json';
$logFile = __DIR__ . '/migrate_errors.log';

date_default_timezone_set('UTC');

if (!is_file($sourceFile)) {
    fwrite(STDERR, "Forrásfájl nem található: {$sourceFile}\n");
    exit(1);
}

$raw = file_get_contents($sourceFile);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Hibás JSON a forrásban.\n");
    exit(1);
}

$items = [];
$roundMeta = [];
if (isset($data['items']) && is_array($data['items'])) {
    $items = $data['items'];
} elseif (is_list_array($data)) {
    $items = $data;
}
if (isset($data['round_meta']) && is_array($data['round_meta'])) {
    $roundMeta = $data['round_meta'];
}

$pdo = db();
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA busy_timeout = 5000');

$now = now_iso();

try {
    $pdo->beginTransaction();

    $pdo->exec('DELETE FROM stops');
    $pdo->exec('DELETE FROM rounds');

    $insertStop = $pdo->prepare('INSERT INTO stops (id, round_id, position, collapsed, city, lat, lon, label, address, note, deadline, weight, volume, extra_json, version, created_at, updated_at, deleted_at)
        VALUES (:id, :round_id, :position, :collapsed, :city, :lat, :lon, :label, :address, :note, :deadline, :weight, :volume, :extra_json, :version, :created_at, :updated_at, NULL)');
    $insertRound = $pdo->prepare('INSERT INTO rounds (id, planned_date, meta_json, version, created_at, updated_at, deleted_at)
        VALUES (:id, :planned_date, :meta_json, :version, :created_at, :updated_at, NULL)');
    $insertAudit = $pdo->prepare('INSERT INTO audits (event, context, payload, created_at) VALUES (:event, :context, :payload, :created_at)');
    $setVersion = $pdo->prepare("INSERT INTO metadata(key, value) VALUES('dataset_version', :value)\n        ON CONFLICT(key) DO UPDATE SET value=excluded.value");

    $position = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Érvénytelen tétel formátum.');
        }
        $item['position'] = $position++;
        $normalized = normalize_stop_payload($item);
        if ($normalized['id'] === '') {
            throw new RuntimeException('Hiányzó azonosító egy tételnél.');
        }
        $insertStop->execute([
            ':id' => $normalized['id'],
            ':round_id' => $normalized['round'],
            ':position' => $normalized['position'],
            ':collapsed' => $normalized['collapsed'],
            ':city' => $normalized['city'],
            ':lat' => $normalized['lat'],
            ':lon' => $normalized['lon'],
            ':label' => $normalized['label'],
            ':address' => $normalized['address'],
            ':note' => $normalized['note'],
            ':deadline' => $normalized['deadline'],
            ':weight' => $normalized['weight'],
            ':volume' => $normalized['volume'],
            ':extra_json' => $normalized['extra_json'],
            ':version' => max(1, $normalized['version']),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    foreach ($roundMeta as $rid => $entry) {
        $norm = normalize_round_meta_entry($rid, $entry);
        $planned = $norm['planned_date'];
        $metaJson = $norm['meta_json'];
        if ($planned === '' && ($metaJson === null || $metaJson === '')) {
            continue;
        }
        $insertRound->execute([
            ':id' => $norm['id'],
            ':planned_date' => $planned !== '' ? $planned : null,
            ':meta_json' => $metaJson,
            ':version' => max(1, (int)$norm['version']),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    $auditPayload = json_encode([
        'items_imported' => count($items),
        'round_meta_imported' => count($roundMeta),
        'source' => basename($sourceFile),
    ], JSON_UNESCAPED_UNICODE);
    $insertAudit->execute([
        ':event' => 'migration',
        ':context' => 'data_json_import',
        ':payload' => $auditPayload,
        ':created_at' => $now,
    ]);

    $setVersion->execute([':value' => '1']);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $message = '[' . date('c') . '] ' . $e->getMessage() . "\n";
    file_put_contents($logFile, $message, FILE_APPEND);
    fwrite(STDERR, "Migráció sikertelen: " . $e->getMessage() . "\n");
    exit(1);
}

$counts = $pdo->query('SELECT COUNT(*) AS c FROM stops WHERE deleted_at IS NULL')->fetch();
$totalStops = (int)($counts['c'] ?? 0);
$roundTotals = $pdo->query('SELECT round_id, COUNT(*) AS c, SUM(weight) AS total_weight, SUM(volume) AS total_volume FROM stops WHERE deleted_at IS NULL GROUP BY round_id ORDER BY round_id')->fetchAll();
$duplicates = $pdo->query('SELECT label, address, COUNT(*) AS c FROM stops WHERE deleted_at IS NULL GROUP BY label, address HAVING COUNT(*) > 1 ORDER BY c DESC, label')->fetchAll();

echo "=== Migráció sikeres ===\n";
echo "Forrás: {$sourceFile}\n";

echo "\n-- Rekord összesítés --\n";
printf("Aktív címek: %d\n", $totalStops);

if ($roundTotals) {
    echo "\n-- Körönkénti össztömeg/össztérfogat --\n";
    foreach ($roundTotals as $row) {
        $rid = (int)$row['round_id'];
        $count = (int)$row['c'];
        $weight = $row['total_weight'] !== null ? (float)$row['total_weight'] : 0.0;
        $volume = $row['total_volume'] !== null ? (float)$row['total_volume'] : 0.0;
        printf("Kör %d: darab=%d, össztömeg=%.2f, össztérfogat=%.2f\n", $rid, $count, $weight, $volume);
    }
}

echo "\n-- Duplikációk (azonos címke + cím) --\n";
if ($duplicates) {
    foreach ($duplicates as $dup) {
        printf("%s | %s (%d darab)\n", $dup['label'] ?? '', $dup['address'] ?? '', (int)$dup['c']);
    }
} else {
    echo "Nincs duplikáció.\n";
}

echo "\n-- Futtatási lépések --\n";
echo "1) PRAGMA foreign_keys=ON, PRAGMA journal_mode=WAL, PRAGMA busy_timeout=5000\n";
echo "2) Schema inicializálás: schema.sql\n";
echo "3) Táblák ürítése (stops, rounds)\n";
echo "4) Adatbetöltés JSON-ból prepared statementekkel\n";
echo "5) Audit napló írása és dataset_version frissítése\n";
echo "6) Validációs riport generálása\n";
