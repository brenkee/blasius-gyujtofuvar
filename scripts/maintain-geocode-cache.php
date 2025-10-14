#!/usr/bin/env php
<?php
require __DIR__ . '/../common.php';
require_once __DIR__ . '/../common-geocode.php';

$options = getopt('', ['dry-run', 'no-retry']);
$dryRun = array_key_exists('dry-run', $options);
$retryErrors = !array_key_exists('no-retry', $options);

$stats = geocode_cache_maintain([
  'dry_run' => $dryRun,
  'retry_errors' => $retryErrors,
]);

$modeLabel = $dryRun ? 'DRY RUN' : 'EXECUTED';
$retryLabel = $retryErrors ? 'enabled' : 'disabled';

fwrite(STDOUT, "Geocode cache maintenance ({$modeLabel}, retry {$retryLabel})\n");
if ($dryRun) {
  fwrite(STDOUT, sprintf("  Would delete expired entries: %d\n", $stats['expired'] ?? 0));
} else {
  fwrite(STDOUT, sprintf("  Deleted expired entries: %d\n", $stats['deleted'] ?? 0));
}

if ($retryErrors) {
  fwrite(STDOUT, sprintf("  Retried failed queries: %d (success: %d, errors: %d)\n",
    $stats['retried'] ?? 0,
    $stats['retry_success'] ?? 0,
    $stats['retry_error'] ?? 0
  ));
} else {
  fwrite(STDOUT, "  Error retry skipped.\n");
}

echo "Done.\n";
