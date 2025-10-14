<?php
require __DIR__ . '/common.php';

$CURRENT_USER = auth_require_login();
if (!auth_user_can($CURRENT_USER, 'manage_users')) {
    http_response_code(403);
    echo 'Hozzáférés megtagadva.';
    exit;
}

$entries = read_change_log_entries();
$entries = array_reverse($entries);
$limit = 250;
$displayEntries = array_slice($entries, 0, $limit);
$retentionSeconds = isset($CHANGE_LOG_RETENTION_SECONDS) ? (int)$CHANGE_LOG_RETENTION_SECONDS : 0;

function human_readable_duration(int $seconds): string {
    if ($seconds <= 0) {
        return '0 perc';
    }
    if ($seconds % 86400 === 0) {
        $days = (int)($seconds / 86400);
        return $days === 1 ? '1 nap' : $days . ' nap';
    }
    if ($seconds % 3600 === 0) {
        $hours = (int)($seconds / 3600);
        return $hours === 1 ? '1 óra' : $hours . ' óra';
    }
    if ($seconds % 60 === 0) {
        $minutes = (int)($seconds / 60);
        return $minutes === 1 ? '1 perc' : $minutes . ' perc';
    }
    return $seconds . ' másodperc';
}

$timezone = new DateTimeZone(date_default_timezone_get());
function format_log_timestamp(?string $timestamp, DateTimeZone $tz): string {
    if ($timestamp === null || trim($timestamp) === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($timestamp);
        return $dt->setTimezone($tz)->format('Y.m.d. H:i:s');
    } catch (Throwable $e) {
        return $timestamp;
    }
}

$backUrl = app_url_path('index.php');
$retentionText = $retentionSeconds > 0 ? human_readable_duration($retentionSeconds) : null;
$totalEntries = count($entries);
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Napló – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
  <style>
    .log-page{max-width:960px;margin:24px auto;padding:0 16px;display:grid;gap:20px}
    .log-header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px}
    .log-header h1{margin:0;font-size:28px}
    .log-subtitle{margin:4px 0 0;font-size:14px;color:var(--muted)}
    .back-link{color:var(--accent);text-decoration:none;font-weight:600}
    .back-link:hover{text-decoration:underline}
    .log-empty{padding:18px;border-radius:12px;border:1px dashed var(--border);background:var(--panel);color:var(--muted);font-size:15px}
    .log-entries{display:grid;gap:16px}
    .log-entry{border:1px solid var(--border);background:var(--panel);border-radius:12px;padding:18px;display:grid;gap:10px}
    .log-entry header{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}
    .log-entry__time{font-weight:600;font-size:15px}
    .log-entry__actor{font-size:14px;color:var(--muted)}
    .log-entry__message{margin:0;font-size:15px;line-height:1.5}
    .log-entry__meta{margin:0;padding:0;font-size:12px;color:var(--muted);list-style:none;display:flex;flex-wrap:wrap;gap:12px}
    .log-entry__meta li{margin:0}
  </style>
</head>
<body>
  <main class="log-page">
    <div class="log-header">
      <div>
        <h1>Napló</h1>
        <p class="log-subtitle">
          <?= htmlspecialchars($totalEntries === 0 ? 'Jelenleg nincs elérhető naplóbejegyzés.' : 'A legutóbbi ' . min($totalEntries, $limit) . ' bejegyzés látható.') ?>
          <?php if ($retentionText): ?>
            <br />A bejegyzések <?= htmlspecialchars($retentionText) ?> után automatikusan törlődnek.
          <?php endif; ?>
        </p>
      </div>
      <a class="back-link" href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">&larr; Vissza az alkalmazásba</a>
    </div>

    <?php if (empty($displayEntries)): ?>
      <div class="log-empty">Nincs megjeleníthető naplóbejegyzés.</div>
    <?php else: ?>
      <section class="log-entries">
        <?php foreach ($displayEntries as $entry): ?>
          <?php
            $actorName = isset($entry['username']) && $entry['username'] !== ''
              ? (string)$entry['username']
              : 'Ismeretlen felhasználó';
            $actorId = isset($entry['user_id']) && $entry['user_id'] !== null ? (int)$entry['user_id'] : null;
            $displayActor = $actorId ? $actorName . ' (#' . $actorId . ')' : $actorName;
            $rev = isset($entry['rev']) && $entry['rev'] !== null ? (int)$entry['rev'] : null;
            $actorClient = isset($entry['actor_id']) && $entry['actor_id'] !== null ? (string)$entry['actor_id'] : null;
            $requestId = isset($entry['request_id']) && $entry['request_id'] !== null ? (string)$entry['request_id'] : null;
            $batchId = isset($entry['batch_id']) && $entry['batch_id'] !== null ? (string)$entry['batch_id'] : null;
            $message = isset($entry['message']) ? trim((string)$entry['message']) : '';
          ?>
          <article class="log-entry">
            <header>
              <span class="log-entry__time"><?= htmlspecialchars(format_log_timestamp($entry['ts'] ?? null, $timezone)) ?></span>
              <span class="log-entry__actor"><?= htmlspecialchars($displayActor) ?></span>
            </header>
            <p class="log-entry__message"><?= htmlspecialchars($message !== '' ? $message : 'Történt egy módosítás.') ?></p>
            <?php if ($rev !== null || $actorClient || $requestId || $batchId): ?>
              <ul class="log-entry__meta">
                <?php if ($rev !== null): ?>
                  <li>rev <?= htmlspecialchars((string)$rev) ?></li>
                <?php endif; ?>
                <?php if ($actorClient): ?>
                  <li>kliens: <?= htmlspecialchars($actorClient) ?></li>
                <?php endif; ?>
                <?php if ($requestId): ?>
                  <li>kérés: <?= htmlspecialchars($requestId) ?></li>
                <?php endif; ?>
                <?php if ($batchId): ?>
                  <li>batch: <?= htmlspecialchars($batchId) ?></li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
