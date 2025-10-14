<?php
require_once __DIR__ . '/common.php';

function geocode_normalize_query($query) {
  if (!is_string($query)) {
    return '';
  }
  $text = trim($query);
  if ($text === '') {
    return '';
  }
  $text = preg_replace('/\s+/u', ' ', $text);
  $text = preg_replace('/\s*,\s*/u', ', ', $text);
  $text = preg_replace('/^\s*([^,]+)\s*,\s*(.+?)\s*,\s*(\d{4})\s*$/u', '$3 $1, $2', $text);
  return trim($text);
}

function geocode_cache_get_connection() {
  static $cached = null;
  global $DATA_FILE;
  if ($cached instanceof PDO) {
    return $cached;
  }
  [$pdo] = data_store_sqlite_open($DATA_FILE);
  geocode_cache_ensure_schema($pdo);
  $cached = $pdo;
  return $cached;
}

function geocode_cache_ensure_schema(PDO $pdo) {
  $dbStart = microtime(true);
  $pdo->exec('CREATE TABLE IF NOT EXISTS geocode_cache (
    query_normalized TEXT PRIMARY KEY,
    result_json TEXT,
    status TEXT NOT NULL DEFAULT "pending",
    fetched_at TEXT,
    expires_at TEXT,
    attempts INTEGER NOT NULL DEFAULT 0
  )');
  $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_geocode_cache_query_normalized ON geocode_cache(query_normalized)');
  app_perf_track_db($dbStart);
}

function geocode_cache_fetch_row(PDO $pdo, $normalized) {
  $dbStart = microtime(true);
  $stmt = $pdo->prepare('SELECT query_normalized, result_json, status, fetched_at, expires_at, attempts FROM geocode_cache WHERE query_normalized = :q LIMIT 1');
  $stmt->execute([':q' => $normalized]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  app_perf_track_db($dbStart);
  return $row;
}

function geocode_cache_store(PDO $pdo, array $data) {
  $dbStart = microtime(true);
  $stmt = $pdo->prepare('INSERT INTO geocode_cache (query_normalized, result_json, status, fetched_at, expires_at, attempts)
    VALUES (:query_normalized, :result_json, :status, :fetched_at, :expires_at, :attempts)
    ON CONFLICT(query_normalized) DO UPDATE SET
      result_json = excluded.result_json,
      status = excluded.status,
      fetched_at = excluded.fetched_at,
      expires_at = excluded.expires_at,
      attempts = excluded.attempts');
  $stmt->execute([
    ':query_normalized' => $data['query_normalized'],
    ':result_json' => $data['result_json'],
    ':status' => $data['status'],
    ':fetched_at' => $data['fetched_at'],
    ':expires_at' => $data['expires_at'],
    ':attempts' => $data['attempts'],
  ]);
  app_perf_track_db($dbStart);
}

function geocode_cache_row_to_payload(array $row) {
  $decoded = [];
  if (!empty($row['result_json'])) {
    $decoded = json_decode($row['result_json'], true);
    if (!is_array($decoded)) {
      $decoded = [];
    }
  }
  $status = $row['status'] ?? ($decoded['status'] ?? 'error');
  $payload = [
    'status' => $status,
    'cached' => true,
    'attempts' => isset($row['attempts']) ? (int)$row['attempts'] : 0,
    'fetched_at' => $row['fetched_at'] ?? null,
    'expires_at' => $row['expires_at'] ?? null,
  ];
  if ($status === 'success') {
    $payload['result'] = $decoded['result'] ?? null;
  } else {
    $payload['error'] = $decoded['error'] ?? ($status === 'error' ? 'geocode_error' : $status);
    if (isset($decoded['message']) && $decoded['message'] !== '') {
      $payload['message'] = $decoded['message'];
    }
  }
  return $payload;
}

function geocode_http_fetch($url, $context) {
  if (isset($GLOBALS['__GEOCODE_HTTP_STUB']) && is_callable($GLOBALS['__GEOCODE_HTTP_STUB'])) {
    return call_user_func($GLOBALS['__GEOCODE_HTTP_STUB'], $url, $context);
  }
  return @file_get_contents($url, false, $context);
}

function geocode_rate_limit_throttle(array $settings) {
  $rate = isset($settings['rate_limit_per_minute']) ? (int)$settings['rate_limit_per_minute'] : 45;
  if ($rate < 1) {
    $rate = 1;
  }
  $intervalUs = (int)ceil(60000000 / $rate);
  $lockPath = $settings['lock_file'] ?? (__DIR__ . '/temp/geocode_api.lock');
  $dir = dirname($lockPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $fh = @fopen($lockPath, 'c+');
  if (!$fh) {
    if ($intervalUs > 0) {
      usleep(min($intervalUs, 200000));
    }
    return;
  }
  try {
    if (!flock($fh, LOCK_EX)) {
      fclose($fh);
      if ($intervalUs > 0) {
        usleep(min($intervalUs, 200000));
      }
      return;
    }
    $raw = stream_get_contents($fh);
    $last = is_string($raw) ? (float)trim($raw) : 0.0;
    $now = microtime(true);
    if ($last > 0) {
      $elapsedUs = (int)round(($now - $last) * 1000000);
      if ($elapsedUs < $intervalUs) {
        $sleep = $intervalUs - $elapsedUs;
        if ($sleep > 0) {
          usleep($sleep);
        }
        $now = microtime(true);
      }
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, sprintf('%.6F', $now));
    fflush($fh);
    flock($fh, LOCK_UN);
  } finally {
    fclose($fh);
  }
}

function geocode_get_settings() {
  global $CFG;
  $cfg = is_array($CFG['geocode'] ?? null) ? $CFG['geocode'] : [];
  $ttlHours = isset($cfg['cache_ttl_hours']) ? (float)$cfg['cache_ttl_hours'] : 24.0;
  if (!is_finite($ttlHours) || $ttlHours <= 0) {
    $ttlHours = 24.0;
  }
  $ttlSeconds = max(60, (int)round($ttlHours * 3600));
  $errorTtlSeconds = isset($cfg['error_cache_ttl_minutes']) ? max(60, (int)round($cfg['error_cache_ttl_minutes'] * 60)) : min($ttlSeconds, 900);
  if ($errorTtlSeconds <= 0) {
    $errorTtlSeconds = min($ttlSeconds, 900);
    if ($errorTtlSeconds <= 0) {
      $errorTtlSeconds = 900;
    }
  }
  $rate = isset($cfg['rate_limit_per_minute']) ? (int)$cfg['rate_limit_per_minute'] : 45;
  if ($rate < 1) {
    $rate = 1;
  }
  $lockFile = $cfg['rate_limit_lock_file'] ?? (__DIR__ . '/temp/geocode_api.lock');
  return [
    'service_url' => trim($cfg['service_url'] ?? 'https://nominatim.openstreetmap.org/search'),
    'user_agent' => $cfg['user_agent'] ?? 'fuvarszervezo-internal/1.5 (+contact@example.com)',
    'countrycodes' => $cfg['countrycodes'] ?? 'hu',
    'language' => $cfg['language'] ?? 'hu',
    'ttl_seconds' => $ttlSeconds,
    'error_ttl_seconds' => $errorTtlSeconds,
    'rate_limit_per_minute' => $rate,
    'lock_file' => is_string($lockFile) && $lockFile !== '' ? (strpos($lockFile, '/') === 0 ? $lockFile : (__DIR__ . '/' . $lockFile)) : (__DIR__ . '/temp/geocode_api.lock'),
  ];
}

function geocode_fetch_remote($normalized, array $settings) {
  $now = time();
  $nowIso = gmdate('c', $now);
  $ttlSeconds = (int)($settings['ttl_seconds'] ?? 86400);
  $errorTtlSeconds = (int)($settings['error_ttl_seconds'] ?? 900);
  $expiresOk = gmdate('c', $now + max(60, $ttlSeconds));
  $expiresErr = gmdate('c', $now + max(60, $errorTtlSeconds));

  $params = http_build_query([
    'q' => $normalized,
    'format' => 'jsonv2',
    'limit' => 1,
    'addressdetails' => 1,
    'countrycodes' => $settings['countrycodes'] ?? 'hu',
    'accept-language' => $settings['language'] ?? 'hu',
  ], '', '&', PHP_QUERY_RFC3986);

  $serviceUrl = $settings['service_url'] ?? 'https://nominatim.openstreetmap.org/search';
  $separator = strpos($serviceUrl, '?') === false ? '?' : '&';
  $url = $serviceUrl . $separator . $params;

  $headers = [
    'User-Agent: ' . ($settings['user_agent'] ?? 'fuvarszervezo-internal/1.5 (+contact@example.com)'),
    'Accept: application/json',
  ];
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => $headers,
      'timeout' => 10,
    ],
  ]);

  geocode_rate_limit_throttle($settings);
  $resp = geocode_http_fetch($url, $context);
  if ($resp === false || $resp === null) {
    return [
      'status' => 'error',
      'error' => 'fetch',
      'message' => 'Geocode lekérdezés sikertelen.',
      'fetched_at' => $nowIso,
      'expires_at' => $expiresErr,
    ];
  }

  $decoded = json_decode($resp, true);
  if (!is_array($decoded)) {
    return [
      'status' => 'error',
      'error' => 'invalid_response',
      'message' => 'Nem sikerült értelmezni a geokódolási választ.',
      'fetched_at' => $nowIso,
      'expires_at' => $expiresErr,
    ];
  }

  if (empty($decoded)) {
    return [
      'status' => 'error',
      'error' => 'noresult',
      'message' => 'Nincs találat a megadott címre.',
      'fetched_at' => $nowIso,
      'expires_at' => $expiresErr,
    ];
  }

  $best = $decoded[0];
  if (!is_array($best)) {
    return [
      'status' => 'error',
      'error' => 'invalid_response',
      'message' => 'Nem sikerült értelmezni a geokódolási választ.',
      'fetched_at' => $nowIso,
      'expires_at' => $expiresErr,
    ];
  }
  if (!isset($best['lat']) || !isset($best['lon'])) {
    return [
      'status' => 'error',
      'error' => 'invalid_result',
      'message' => 'A geokódolási válasz nem tartalmaz koordinátákat.',
      'fetched_at' => $nowIso,
      'expires_at' => $expiresErr,
    ];
  }
  $addr = isset($best['address']) && is_array($best['address']) ? $best['address'] : [];
  $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? '';

  $result = [
    'lat' => (float)$best['lat'],
    'lon' => (float)$best['lon'],
    'city' => $city,
    'normalized' => $normalized,
    'raw' => $best,
  ];

  return [
    'status' => 'success',
    'result' => $result,
    'fetched_at' => $nowIso,
    'expires_at' => $expiresOk,
  ];
}

function geocode_lookup_or_fetch($query, array $options = []) {
  $isNormalized = !empty($options['normalized']);
  $forceRefresh = !empty($options['force_refresh']);
  $original = $query;
  $normalized = $isNormalized ? (string)$query : geocode_normalize_query($query);
  if ($normalized === '') {
    return [
      'status' => 'error',
      'error' => 'empty_query',
      'message' => 'Hiányzó vagy üres geokódolási lekérdezés.',
      'cached' => false,
      'normalized' => '',
      'query' => is_string($original) ? $original : '',
      'attempts' => 0,
    ];
  }

  $pdo = geocode_cache_get_connection();
  $row = geocode_cache_fetch_row($pdo, $normalized);
  $now = time();
  $nowIso = gmdate('c', $now);
  $cacheValid = false;
  if ($row && !$forceRefresh) {
    if (!isset($row['expires_at']) || $row['expires_at'] === '') {
      $cacheValid = true;
    } else {
      $expiresAt = strtotime($row['expires_at']);
      if ($expiresAt === false || $expiresAt > $now) {
        $cacheValid = true;
      }
    }
  }
  if ($cacheValid) {
    $payload = geocode_cache_row_to_payload($row);
    $payload['normalized'] = $normalized;
    $payload['query'] = is_string($original) ? $original : $normalized;
    return $payload;
  }

  $settings = geocode_get_settings();
  $attempts = $row ? ((int)$row['attempts']) + 1 : 1;
  $fetch = geocode_fetch_remote($normalized, $settings);
  $status = $fetch['status'] ?? 'error';

  if ($status === 'success') {
    $resultJson = json_encode([
      'status' => 'success',
      'result' => $fetch['result'],
    ], JSON_UNESCAPED_UNICODE);
    geocode_cache_store($pdo, [
      'query_normalized' => $normalized,
      'result_json' => $resultJson,
      'status' => 'success',
      'fetched_at' => $fetch['fetched_at'] ?? $nowIso,
      'expires_at' => $fetch['expires_at'] ?? null,
      'attempts' => $attempts,
    ]);
    return [
      'status' => 'success',
      'result' => $fetch['result'],
      'cached' => false,
      'attempts' => $attempts,
      'normalized' => $normalized,
      'query' => is_string($original) ? $original : $normalized,
      'fetched_at' => $fetch['fetched_at'] ?? $nowIso,
      'expires_at' => $fetch['expires_at'] ?? null,
    ];
  }

  $errorCode = $fetch['error'] ?? 'geocode_error';
  $message = $fetch['message'] ?? null;
  $resultJson = json_encode([
    'status' => 'error',
    'error' => $errorCode,
    'message' => $message,
  ], JSON_UNESCAPED_UNICODE);
  geocode_cache_store($pdo, [
    'query_normalized' => $normalized,
    'result_json' => $resultJson,
    'status' => 'error',
    'fetched_at' => $fetch['fetched_at'] ?? $nowIso,
    'expires_at' => $fetch['expires_at'] ?? null,
    'attempts' => $attempts,
  ]);
  $payload = [
    'status' => 'error',
    'error' => $errorCode,
    'cached' => false,
    'attempts' => $attempts,
    'normalized' => $normalized,
    'query' => is_string($original) ? $original : $normalized,
    'fetched_at' => $fetch['fetched_at'] ?? $nowIso,
    'expires_at' => $fetch['expires_at'] ?? null,
  ];
  if ($message) {
    $payload['message'] = $message;
  }
  return $payload;
}

function geocode_batch_lookup(array $queries, array $options = []) {
  $forceRefresh = !empty($options['force_refresh']);
  $results = [];
  $byNormalized = [];
  foreach ($queries as $query) {
    if (!is_string($query)) {
      $results[] = [
        'status' => 'error',
        'error' => 'invalid_query',
        'message' => 'A lekérdezés nem sztring típusú.',
        'query' => $query,
        'normalized' => '',
        'cached' => false,
        'attempts' => 0,
      ];
      continue;
    }
    $normalized = geocode_normalize_query($query);
    if ($normalized === '') {
      $results[] = [
        'status' => 'error',
        'error' => 'empty_query',
        'message' => 'Hiányzó vagy üres geokódolási lekérdezés.',
        'query' => $query,
        'normalized' => '',
        'cached' => false,
        'attempts' => 0,
      ];
      continue;
    }
    if (!isset($byNormalized[$normalized])) {
      $byNormalized[$normalized] = null;
    }
    $results[] = [
      'query' => $query,
      'normalized' => $normalized,
      '_defer' => true,
    ];
  }

  foreach ($byNormalized as $normalized => $_) {
    $lookup = geocode_lookup_or_fetch($normalized, ['normalized' => true, 'force_refresh' => $forceRefresh]);
    $lookup['normalized'] = $normalized;
    $byNormalized[$normalized] = $lookup;
  }

  $final = [];
  foreach ($results as $entry) {
    if (empty($entry['_defer'])) {
      $final[] = $entry;
      continue;
    }
    $normalized = $entry['normalized'];
    $lookup = $byNormalized[$normalized] ?? null;
    if ($lookup === null) {
      $final[] = [
        'status' => 'error',
        'error' => 'geocode_error',
        'message' => 'Ismeretlen geokódolási hiba.',
        'query' => $entry['query'],
        'normalized' => $normalized,
        'cached' => false,
        'attempts' => 0,
      ];
      continue;
    }
    $final[] = array_merge($lookup, [
      'query' => $entry['query'],
      'normalized' => $normalized,
    ]);
  }
  return $final;
}

function geocode_cache_maintain(array $options = []) {
  $pdo = geocode_cache_get_connection();
  $nowIso = gmdate('c');
  $dryRun = !empty($options['dry_run']);
  $retryErrors = array_key_exists('retry_errors', $options) ? (bool)$options['retry_errors'] : true;

  $dbStart = microtime(true);
  $countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM geocode_cache WHERE expires_at IS NOT NULL AND expires_at <= :now');
  $countStmt->execute([':now' => $nowIso]);
  $expiredCount = (int)$countStmt->fetchColumn();
  app_perf_track_db($dbStart);

  if (!$dryRun && $expiredCount > 0) {
    $dbStart = microtime(true);
    $deleteStmt = $pdo->prepare('DELETE FROM geocode_cache WHERE expires_at IS NOT NULL AND expires_at <= :now');
    $deleteStmt->execute([':now' => $nowIso]);
    app_perf_track_db($dbStart);
  }

  $retried = 0;
  $retrySuccess = 0;
  $retryError = 0;

  if ($retryErrors) {
    $dbStart = microtime(true);
    $selectStmt = $pdo->prepare('SELECT query_normalized FROM geocode_cache WHERE status != :status GROUP BY query_normalized');
    $selectStmt->execute([':status' => 'success']);
    $pending = $selectStmt->fetchAll(PDO::FETCH_COLUMN);
    app_perf_track_db($dbStart);
    foreach ($pending as $normalized) {
      if (!is_string($normalized) || $normalized === '') {
        continue;
      }
      $retried += 1;
      if ($dryRun) {
        continue;
      }
      $result = geocode_lookup_or_fetch($normalized, ['normalized' => true, 'force_refresh' => true]);
      if (($result['status'] ?? 'error') === 'success') {
        $retrySuccess += 1;
      } else {
        $retryError += 1;
      }
    }
  }

  return [
    'expired' => $expiredCount,
    'deleted' => $dryRun ? 0 : $expiredCount,
    'retried' => $retried,
    'retry_success' => $retrySuccess,
    'retry_error' => $retryError,
    'dry_run' => $dryRun,
  ];
}

