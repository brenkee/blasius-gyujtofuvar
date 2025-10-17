<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();
if (!auth_user_can($CURRENT_USER, 'view_logs')) {
    http_response_code(403);
    echo 'Hozzáférés megtagadva.';
    exit;
}

$actionLabels = audit_log_action_labels();
$filters = [
    'user' => trim((string)($_GET['user'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$download = isset($_GET['download']) && strtolower((string)$_GET['download']) === 'csv';

if ($download) {
    $exportResult = audit_log_query($filters, 1, $perPage, true);
    $entries = $exportResult['entries'];
    $filename = 'naplo-' . gmdate('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Dátum', 'Felhasználó', 'Szerep', 'Művelet', 'Üzenet'], ';', '"', '\\');
    $tzName = @date_default_timezone_get();
    if (!is_string($tzName) || $tzName === '') {
        $tzName = 'UTC';
    }
    $tz = new DateTimeZone($tzName);
    foreach ($entries as $entry) {
        try {
            $createdAt = new DateTimeImmutable((string)($entry['created_at'] ?? ''), new DateTimeZone('UTC'));
            $local = $createdAt->setTimezone($tz);
            $displayDate = $local->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $displayDate = (string)($entry['created_at'] ?? '');
        }
        $actionKey = (string)($entry['action'] ?? '');
        $actionLabel = $actionLabels[$actionKey] ?? $actionKey;
        $row = [
            $displayDate,
            (string)($entry['actor_name'] ?? ''),
            auth_role_label($entry['actor_role'] ?? null),
            $actionLabel,
            (string)($entry['message'] ?? ''),
        ];
        fputcsv($out, $row, ';', '"', '\\');
    }
    fclose($out);
    exit;
}

$result = audit_log_query($filters, $page, $perPage, false);
$entries = $result['entries'];
$total = $result['total'];
$pages = $result['pages'];
$currentPage = $result['page'];

$tzName = @date_default_timezone_get();
if (!is_string($tzName) || $tzName === '') {
    $tzName = 'UTC';
}
$tz = new DateTimeZone($tzName);

function log_format_datetime(?string $iso, DateTimeZone $tz): array {
    if ($iso === null || trim($iso) === '') {
        return ['', ''];
    }
    try {
        $dt = new DateTimeImmutable($iso, new DateTimeZone('UTC'));
        $local = $dt->setTimezone($tz);
        return [$local->format('Y. m. d. H:i'), $dt->format(DateTimeInterface::ATOM)];
    } catch (Throwable $e) {
        return [$iso, $iso];
    }
}

function log_build_query(array $params, array $overrides = []): string {
    $merged = array_merge($params, $overrides);
    $filtered = [];
    foreach ($merged as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $filtered[$key] = $value;
    }
    return http_build_query($filtered);
}

$filterQuery = [
    'user' => $filters['user'],
    'action' => $filters['action'],
    'from' => $filters['from'],
    'to' => $filters['to'],
];
$downloadQuery = log_build_query($filterQuery, ['download' => 'csv']);
?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <title>Napló – admin műveletek</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body>
    <main class="log-page">
        <div class="log-header">
            <div>
                <h1>Admin napló</h1>
                <p class="log-subtitle">Minden lényegi adminisztrátori és szerkesztői művelet időrendben.</p>
            </div>
            <a class="log-back" href="<?= htmlspecialchars(app_url_path('index.php'), ENT_QUOTES) ?>">&larr; Vissza az alkalmazáshoz</a>
        </div>
        <form class="log-filters" method="get" action="">
            <div class="log-filter-group">
                <label>Felhasználó
                    <input type="text" name="user" value="<?= htmlspecialchars($filters['user'], ENT_QUOTES) ?>" placeholder="pl. admin" />
                </label>
            </div>
            <div class="log-filter-group">
                <label>Művelet
                    <select name="action">
                        <option value="">Összes</option>
                        <?php foreach ($actionLabels as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"<?= $filters['action'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="log-filter-group">
                <label>Dátumtól
                    <input type="date" name="from" value="<?= htmlspecialchars($filters['from'], ENT_QUOTES) ?>" />
                </label>
            </div>
            <div class="log-filter-group">
                <label>Dátumig
                    <input type="date" name="to" value="<?= htmlspecialchars($filters['to'], ENT_QUOTES) ?>" />
                </label>
            </div>
            <div class="log-filter-actions">
                <button type="submit" class="btn-primary">Szűrés</button>
                <a class="btn-secondary" href="<?= htmlspecialchars(app_url_path('log.php'), ENT_QUOTES) ?>">Szűrők törlése</a>
                <a class="btn-secondary" href="<?= htmlspecialchars(app_url_path('log.php') . ($downloadQuery !== '' ? ('?' . $downloadQuery) : '?download=csv'), ENT_QUOTES) ?>">CSV letöltése</a>
            </div>
        </form>

        <section class="log-summary">
            <span>Találatok: <?= (int)$total ?></span>
            <span>Oldal: <?= (int)$currentPage ?> / <?= (int)$pages ?></span>
        </section>

        <section class="log-entries">
            <?php if (empty($entries)): ?>
                <p class="log-empty">Nincs naplóbejegyzés a megadott szűrőkkel.</p>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <?php [$displayDate, $isoDate] = log_format_datetime($entry['created_at'] ?? null, $tz); ?>
                    <?php $actionKey = (string)($entry['action'] ?? ''); ?>
                    <?php $actionLabel = $actionLabels[$actionKey] ?? $actionKey; ?>
                    <article class="log-entry">
                        <header class="log-entry-header">
                            <time datetime="<?= htmlspecialchars($isoDate, ENT_QUOTES) ?>"><?= htmlspecialchars($displayDate) ?></time>
                            <span class="log-entry-action"><?= htmlspecialchars($actionLabel) ?></span>
                        </header>
                        <p class="log-entry-message"><?= nl2br(htmlspecialchars((string)($entry['message'] ?? ''))) ?></p>
                        <footer class="log-entry-meta">
                            <span><?= htmlspecialchars(auth_role_label($entry['actor_role'] ?? null)) ?> &middot; <?= htmlspecialchars((string)($entry['actor_name'] ?? '')) ?></span>
                            <?php if (!empty($entry['entity'])): ?>
                                <span>Objektum: <?= htmlspecialchars((string)$entry['entity']) ?><?= $entry['entity_id'] !== null ? ' #' . htmlspecialchars((string)$entry['entity_id']) : '' ?></span>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <?php if ($pages > 1): ?>
            <nav class="log-pagination" aria-label="Napló oldalak">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php $query = log_build_query($filterQuery, ['page' => $p]); ?>
                    <a class="log-page-link<?= $p === $currentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars(app_url_path('log.php') . ($query !== '' ? ('?' . $query) : ''), ENT_QUOTES) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </main>
</body>
</html>
