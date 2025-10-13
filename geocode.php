<?php

if (!function_exists('geocode_config')) {
  function geocode_config(): array {
    global $CFG;
    $defaults = [
      'endpoint' => 'https://nominatim.openstreetmap.org/search',
      'countrycodes' => 'hu',
      'language' => 'hu',
      'user_agent' => 'gyujtofuvar-geocode/1.0 (+contact@example.com)',
      'timeout' => 12,
      'max_attempts' => 3,
      'retry_wait_ms' => 350,
      'failure_retry_minutes' => 1440,
    ];
    $cfg = isset($CFG['geocode']) && is_array($CFG['geocode']) ? $CFG['geocode'] : [];
    return array_merge($defaults, $cfg);
  }
}

if (!function_exists('geocode_db')) {
  function geocode_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
      return $pdo;
    }
    global $DATA_FILE;
    if (data_store_is_sqlite($DATA_FILE)) {
      [$pdo] = data_store_sqlite_open($DATA_FILE);
    } else {
      $dir = __DIR__ . '/data';
      if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
      }
      $path = $dir . '/geocode-cache.db';
      $pdo = new PDO('sqlite:' . $path);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    }
    geocode_ensure_schema($pdo);
    return $pdo;
  }
}

if (!function_exists('geocode_ensure_schema')) {
  function geocode_ensure_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
      return;
    }
    $dbStart = microtime(true);
    $pdo->exec('CREATE TABLE IF NOT EXISTS geocode_cache (
      address_hash TEXT PRIMARY KEY,
      address TEXT NOT NULL,
      normalized TEXT NOT NULL,
      lat REAL,
      lon REAL,
      city TEXT,
      accuracy REAL,
      source TEXT,
      raw JSON,
      status TEXT NOT NULL,
      failure_reason TEXT,
      attempts INTEGER NOT NULL DEFAULT 0,
      updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_geocode_cache_status ON geocode_cache(status, updated_at DESC)');
    app_perf_track_db($dbStart);
    $ensured = true;
  }
}

if (!function_exists('geocode_clean_string')) {
  function geocode_clean_string(string $value): string {
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return $value === '' ? '' : $value;
  }
}

if (!function_exists('geocode_normalize_key')) {
  function geocode_normalize_key(string $value): string {
    $clean = geocode_clean_string($value);
    $lower = mb_strtolower($clean, 'UTF-8');
    return $lower;
  }
}

if (!function_exists('geocode_cache_fetch')) {
  function geocode_cache_fetch(string $normalized): ?array {
    $pdo = geocode_db();
    $stmt = $pdo->prepare('SELECT * FROM geocode_cache WHERE address_hash = :hash LIMIT 1');
    $dbStart = microtime(true);
    $stmt->execute([':hash' => hash('sha256', $normalized)]);
    app_perf_track_db($dbStart);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }
}

if (!function_exists('geocode_cache_store')) {
  function geocode_cache_store(string $normalized, string $address, array $data): void {
    $pdo = geocode_db();
    $payload = [
      ':hash' => hash('sha256', $normalized),
      ':address' => $address,
      ':normalized' => $normalized,
      ':lat' => $data['lat'] ?? null,
      ':lon' => $data['lon'] ?? null,
      ':city' => $data['city'] ?? null,
      ':accuracy' => $data['accuracy'] ?? null,
      ':source' => $data['source'] ?? null,
      ':raw' => isset($data['raw']) ? json_encode($data['raw'], JSON_UNESCAPED_UNICODE) : null,
      ':status' => $data['status'] ?? 'success',
      ':failure_reason' => $data['failure_reason'] ?? null,
      ':attempts' => $data['attempts'] ?? 1,
      ':updated_at' => gmdate('c'),
    ];
    $sql = 'INSERT INTO geocode_cache (
      address_hash, address, normalized, lat, lon, city, accuracy, source, raw, status, failure_reason, attempts, updated_at
    ) VALUES (
      :hash, :address, :normalized, :lat, :lon, :city, :accuracy, :source, :raw, :status, :failure_reason, :attempts, :updated_at
    )
    ON CONFLICT(address_hash) DO UPDATE SET
      address = excluded.address,
      normalized = excluded.normalized,
      lat = excluded.lat,
      lon = excluded.lon,
      city = excluded.city,
      accuracy = excluded.accuracy,
      source = excluded.source,
      raw = excluded.raw,
      status = excluded.status,
      failure_reason = excluded.failure_reason,
      attempts = excluded.attempts,
      updated_at = excluded.updated_at';
    $stmt = $pdo->prepare($sql);
    $dbStart = microtime(true);
    $stmt->execute($payload);
    app_perf_track_db($dbStart);
  }
}

if (!function_exists('geocode_cache_increment_failure')) {
  function geocode_cache_increment_failure(string $normalized, string $address, string $reason, array $context = []): void {
    $existing = geocode_cache_fetch($normalized);
    $attempts = isset($existing['attempts']) ? (int)$existing['attempts'] + 1 : 1;
    geocode_cache_store($normalized, $address, [
      'status' => 'failed',
      'failure_reason' => $reason,
      'attempts' => $attempts,
      'raw' => ['context' => $context],
      'source' => 'nominatim',
      'city' => $context['city'] ?? null,
    ]);
  }
}

if (!function_exists('geocode_cache_store_success')) {
  function geocode_cache_store_success(string $normalized, string $address, array $result, array $context = []): void {
    $raw = $result['raw'] ?? null;
    if (is_array($raw)) {
      $raw['context'] = $context;
    } else {
      $raw = ['context' => $context];
    }
    geocode_cache_store($normalized, $address, [
      'status' => 'success',
      'lat' => $result['lat'] ?? null,
      'lon' => $result['lon'] ?? null,
      'city' => $result['city'] ?? null,
      'accuracy' => $result['accuracy'] ?? null,
      'source' => $result['source'] ?? 'nominatim',
      'raw' => $raw,
      'attempts' => isset($result['attempts']) ? (int)$result['attempts'] : 1,
    ]);
  }
}

if (!function_exists('geocode_generate_candidates')) {
  function geocode_generate_candidates(string $address, string $city = ''): array {
    $base = geocode_clean_string($address);
    if ($base === '') {
      return [];
    }
    $variants = [];
    $add = function (string $value) use (&$variants) {
      $clean = geocode_clean_string($value);
      if ($clean === '') {
        return;
      }
      $norm = geocode_normalize_key($clean);
      foreach ($variants as $entry) {
        if ($entry['normalized'] === $norm) {
          return;
        }
      }
      $variants[] = ['value' => $clean, 'normalized' => $norm];
    };

    $add($base);

    $cityClean = geocode_clean_string($city);
    if ($cityClean !== '') {
      if (stripos($base, $cityClean) === false) {
        $add($cityClean . ', ' . $base);
        $add($base . ', ' . $cityClean);
      }
    }

    if (preg_match('/^\s*([^,]+)\s*,\s*(.+?)\s*,\s*(\d{4})\s*$/u', $base, $m)) {
      $add($m[3] . ' ' . $m[1] . ', ' . $m[2]);
    }

    $simplified = preg_replace('/\b(u\.|ut\.?|utc\.?|utca)\b/iu', 'utca', $base);
    $simplified = preg_replace('/\b(út\.|u?t\.?|ut)\b/iu', 'út', $simplified);
    $simplified = preg_replace('/\b(ter\.?|tér\.)\b/iu', 'tér', $simplified);
    $simplified = preg_replace('/\b(krt\.?|körút)\b/iu', 'körút', $simplified);
    if ($simplified !== $base) {
      $add($simplified);
      if ($cityClean !== '' && stripos($simplified, $cityClean) === false) {
        $add($cityClean . ', ' . $simplified);
      }
    }

    $withoutDistrict = preg_replace('/,?\s*(?:[IVXLCDM]+\.?|\d{1,2}\.?)(?:\s*-?\s*)ker(?:\.|ület)?/iu', '', $base);
    if ($withoutDistrict !== $base) {
      $add($withoutDistrict);
      if ($cityClean !== '' && stripos($withoutDistrict, $cityClean) === false) {
        $add($cityClean . ', ' . $withoutDistrict);
      }
    }

    return array_map(function ($entry) {
      return $entry['value'];
    }, $variants);
  }
}

if (!function_exists('geocode_should_retry_failure')) {
  function geocode_should_retry_failure(?array $cache, int $retryMinutes): bool {
    if (!$cache || ($cache['status'] ?? '') !== 'failed') {
      return true;
    }
    if ($retryMinutes <= 0) {
      return false;
    }
    $updatedAt = isset($cache['updated_at']) ? strtotime((string)$cache['updated_at']) : 0;
    if ($updatedAt <= 0) {
      return true;
    }
    $ageMinutes = (time() - $updatedAt) / 60;
    return $ageMinutes >= $retryMinutes;
  }
}

if (!function_exists('geocode_call_nominatim')) {
  function geocode_call_nominatim(string $query, array $cfg): array {
    $params = [
      'q' => $query,
      'format' => 'jsonv2',
      'limit' => 1,
      'addressdetails' => 1,
    ];
    if (!empty($cfg['countrycodes'])) {
      $params['countrycodes'] = $cfg['countrycodes'];
    }
    if (!empty($cfg['language'])) {
      $params['accept-language'] = $cfg['language'];
    }
    $url = rtrim((string)$cfg['endpoint'], '?') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $headers = [
      'User-Agent: ' . ($cfg['user_agent'] ?? 'gyujtofuvar-geocode/1.0 (+contact@example.com)'),
      'Accept: application/json',
    ];
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => max(1, (int)($cfg['timeout'] ?? 12)),
      ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
      return ['status' => 'error', 'reason' => 'http_error'];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
      return ['status' => 'error', 'reason' => 'bad_json'];
    }
    if (empty($decoded)) {
      return ['status' => 'empty'];
    }
    $best = $decoded[0];
    if (!is_array($best)) {
      return ['status' => 'error', 'reason' => 'bad_payload'];
    }
    return ['status' => 'ok', 'result' => $best];
  }
}

if (!function_exists('geocode_lookup')) {
  function geocode_lookup(string $address, array $context = []): array {
    $address = geocode_clean_string($address);
    if ($address === '') {
      return ['status' => 'skipped', 'reason' => 'empty_address'];
    }
    $cfg = geocode_config();
    $city = isset($context['city']) ? geocode_clean_string((string)$context['city']) : '';
    $candidates = geocode_generate_candidates($address, $city);
    if (empty($candidates)) {
      return ['status' => 'skipped', 'reason' => 'empty_candidates'];
    }
    $maxAttempts = max(1, (int)$cfg['max_attempts']);
    $waitMs = max(0, (int)$cfg['retry_wait_ms']);
    $failureRetry = max(0, (int)$cfg['failure_retry_minutes']);
    $baseNormalized = geocode_normalize_key($address);
    $attempted = 0;
    $lastFailure = null;
    $lastCandidateNormalized = null;

    foreach ($candidates as $candidate) {
      $normalized = geocode_normalize_key($candidate);
      $lastCandidateNormalized = $normalized;
      $cache = geocode_cache_fetch($normalized);
      if ($cache && ($cache['status'] ?? '') === 'success') {
        $lat = isset($cache['lat']) ? (float)$cache['lat'] : null;
        $lon = isset($cache['lon']) ? (float)$cache['lon'] : null;
        geocode_cache_store_success($baseNormalized, $address, [
          'lat' => $lat,
          'lon' => $lon,
          'city' => $cache['city'] ?? null,
          'accuracy' => isset($cache['accuracy']) ? (float)$cache['accuracy'] : null,
          'source' => $cache['source'] ?? 'cache',
          'raw' => isset($cache['raw']) ? json_decode((string)$cache['raw'], true) : null,
          'attempts' => isset($cache['attempts']) ? (int)$cache['attempts'] : 1,
        ], $context);
        return [
          'status' => 'success',
          'lat' => $lat,
          'lon' => $lon,
          'city' => $cache['city'] ?? '',
          'accuracy' => isset($cache['accuracy']) ? (float)$cache['accuracy'] : null,
          'source' => $cache['source'] ?? 'cache',
          'cache_hit' => true,
          'query' => $candidate,
          'normalized' => $normalized,
          'attempts' => $attempted,
        ];
      }
      if (!$cache) {
        // nothing cached, proceed
      } elseif (!geocode_should_retry_failure($cache, $failureRetry)) {
        $lastFailure = ['status' => 'failed', 'reason' => $cache['failure_reason'] ?? 'cached_failure'];
        continue;
      }

      if ($attempted >= $maxAttempts) {
        break;
      }

      $attempted += 1;
      $response = geocode_call_nominatim($candidate, $cfg);
      if (($response['status'] ?? '') === 'ok' && isset($response['result'])) {
        $best = $response['result'];
        $lat = isset($best['lat']) ? (float)$best['lat'] : null;
        $lon = isset($best['lon']) ? (float)$best['lon'] : null;
        $addr = isset($best['address']) && is_array($best['address']) ? $best['address'] : [];
        $cityCandidate = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? ($city ?: '');
        $accuracy = isset($best['importance']) ? (float)$best['importance'] : (isset($best['place_rank']) ? (float)$best['place_rank'] : null);
        $result = [
          'status' => 'success',
          'lat' => $lat,
          'lon' => $lon,
          'city' => $cityCandidate,
          'accuracy' => $accuracy,
          'source' => 'nominatim',
          'raw' => $best,
          'attempts' => $attempted,
          'cache_hit' => false,
          'query' => $candidate,
          'normalized' => $normalized,
        ];
        geocode_cache_store_success($normalized, $candidate, $result, $context);
        if ($normalized !== $baseNormalized) {
          geocode_cache_store_success($baseNormalized, $address, $result, $context);
        }
        return $result;
      }

      $reason = $response['reason'] ?? (($response['status'] ?? '') === 'empty' ? 'no_result' : 'unknown');
      $lastFailure = ['status' => 'failed', 'reason' => $reason];
      geocode_cache_increment_failure($normalized, $candidate, $reason, $context);
      if ($waitMs > 0) {
        usleep($waitMs * 1000);
      }
    }

    if ($lastFailure) {
      if ($baseNormalized !== $lastCandidateNormalized) {
        geocode_cache_increment_failure($baseNormalized, $address, $lastFailure['reason'], $context);
      }
    }

    return $lastFailure ?: ['status' => 'failed', 'reason' => 'unavailable'];
  }
}

if (!function_exists('geocode_extract_summary')) {
  function geocode_extract_summary(array $item, string $addressField, string $labelField): string {
    $parts = [];
    $label = isset($item[$labelField]) ? trim((string)$item[$labelField]) : '';
    $address = isset($item[$addressField]) ? trim((string)$item[$addressField]) : '';
    if ($label !== '') {
      $parts[] = $label;
    }
    if ($address !== '') {
      $parts[] = $address;
    }
    if (empty($parts) && isset($item['id'])) {
      $parts[] = '#' . $item['id'];
    }
    return implode(' – ', $parts);
  }
}

if (!function_exists('geocode_has_coordinates')) {
  function geocode_has_coordinates($item): bool {
    if (!is_array($item)) {
      return false;
    }
    if (!isset($item['lat'], $item['lon'])) {
      return false;
    }
    $lat = $item['lat'];
    $lon = $item['lon'];
    if ($lat === null || $lon === null || $lat === '' || $lon === '') {
      return false;
    }
    return is_numeric($lat) && is_numeric($lon);
  }
}

if (!function_exists('geocode_apply_to_items')) {
  function geocode_apply_to_items(array $items, array $previousItems = [], array $options = []): array {
    $addressField = $options['address_field'] ?? ($GLOBALS['CFG']['items']['address_field_id'] ?? 'address');
    $labelField = $options['label_field'] ?? ($GLOBALS['CFG']['items']['label_field_id'] ?? 'label');
    $prevMap = [];
    foreach ($previousItems as $prev) {
      if (!is_array($prev)) {
        continue;
      }
      $id = isset($prev['id']) ? (string)$prev['id'] : '';
      if ($id !== '') {
        $prevMap[$id] = $prev;
      }
    }

    $report = [
      'attempted' => 0,
      'success' => 0,
      'failed' => 0,
      'updated' => 0,
      'failures' => [],
    ];

    foreach ($items as $index => &$item) {
      if (!is_array($item)) {
        continue;
      }
      $id = isset($item['id']) ? (string)$item['id'] : '';
      $address = isset($item[$addressField]) ? geocode_clean_string((string)$item[$addressField]) : '';
      $city = isset($item['city']) ? geocode_clean_string((string)$item['city']) : '';
      $prev = $id !== '' && isset($prevMap[$id]) ? $prevMap[$id] : null;
      $addressChanged = true;
      if ($prev) {
        $prevAddress = isset($prev[$addressField]) ? geocode_clean_string((string)$prev[$addressField]) : '';
        $prevCity = isset($prev['city']) ? geocode_clean_string((string)$prev['city']) : '';
        $addressChanged = ($prevAddress !== $address) || ($prevCity !== $city);
        if (!$addressChanged && geocode_has_coordinates($prev) && !geocode_has_coordinates($item)) {
          $item['lat'] = $prev['lat'];
          $item['lon'] = $prev['lon'];
          if (isset($prev['geocode_accuracy'])) {
            $item['geocode_accuracy'] = $prev['geocode_accuracy'];
          }
          if (isset($prev['geocode_source'])) {
            $item['geocode_source'] = $prev['geocode_source'];
          }
        }
      }

      if ($address === '') {
        $item['lat'] = null;
        $item['lon'] = null;
        $item['geocode_status'] = 'missing';
        continue;
      }

      $needsGeocode = $addressChanged || !geocode_has_coordinates($item);
      if (!$needsGeocode) {
        if (isset($item['geocode_status']) && $item['geocode_status'] === 'failed') {
          unset($item['geocode_status']);
        }
        continue;
      }

      $report['attempted'] += 1;
      $result = geocode_lookup($address, [
        'city' => $city,
        'item_id' => $id,
        'label' => isset($item[$labelField]) ? (string)$item[$labelField] : null,
      ]);

      if (($result['status'] ?? '') === 'success') {
        $lat = isset($result['lat']) ? (float)$result['lat'] : null;
        $lon = isset($result['lon']) ? (float)$result['lon'] : null;
        $item['lat'] = $lat;
        $item['lon'] = $lon;
        $item['geocode_status'] = 'ok';
        $item['geocode_accuracy'] = $result['accuracy'] ?? null;
        $item['geocode_source'] = $result['source'] ?? 'nominatim';
        if (!empty($result['city'])) {
          $item['city'] = $result['city'];
        }
        $report['success'] += 1;
        if ($addressChanged || !$prev || !geocode_has_coordinates($prev)) {
          $report['updated'] += 1;
        }
      } else {
        $item['geocode_status'] = 'failed';
        $item['geocode_accuracy'] = null;
        $item['geocode_source'] = null;
        $item['lat'] = null;
        $item['lon'] = null;
        $report['failed'] += 1;
        $report['failures'][] = [
          'index' => $index,
          'id' => $id,
          'label' => isset($item[$labelField]) ? (string)$item[$labelField] : '',
          'address' => $address,
          'city' => $city,
          'fallbackCity' => $city,
          'reason' => $result['reason'] ?? 'unknown',
          'summary' => geocode_extract_summary($item, $addressField, $labelField),
        ];
      }
    }
    unset($item);

    return [$items, $report];
  }
}

if (!function_exists('geocode_list_failures')) {
  function geocode_list_failures(int $limit = 50): array {
    $pdo = geocode_db();
    $stmt = $pdo->prepare('SELECT address, city, failure_reason, updated_at, attempts, raw FROM geocode_cache WHERE status = \"failed\" ORDER BY updated_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $dbStart = microtime(true);
    $stmt->execute();
    app_perf_track_db($dbStart);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
      return [];
    }
    return array_map(function ($row) {
      $extra = [];
      if (!empty($row['raw'])) {
        $decoded = json_decode((string)$row['raw'], true);
        if (is_array($decoded) && isset($decoded['context']) && is_array($decoded['context'])) {
          $extra = $decoded['context'];
        }
      }
      return [
        'address' => $row['address'] ?? '',
        'city' => $row['city'] ?? '',
        'reason' => $row['failure_reason'] ?? 'unknown',
        'updated_at' => $row['updated_at'] ?? '',
        'attempts' => isset($row['attempts']) ? (int)$row['attempts'] : 0,
        'context' => $extra,
      ];
    }, $rows);
  }
}

