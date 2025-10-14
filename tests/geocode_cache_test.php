#!/usr/bin/env php
<?php
require __DIR__ . '/../common.php';
require_once __DIR__ . '/../common-geocode.php';

function assert_true($condition, $message) {
  if (!$condition) {
    fwrite(STDERR, "Assertion failed: {$message}\n");
    exit(1);
  }
}

function assert_equals($expected, $actual, $message) {
  if ($expected !== $actual) {
    fwrite(STDERR, "Assertion failed: {$message}. Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n");
    exit(1);
  }
}

$tempDb = sys_get_temp_dir() . '/geocode_cache_test.sqlite';
$tempLock = sys_get_temp_dir() . '/geocode_cache_test.lock';
@unlink($tempDb);
$GLOBALS['DATA_FILE'] = $tempDb;
$GLOBALS['STATE_LOCK_FILE'] = $tempLock;
[$pdo] = data_store_sqlite_open($tempDb);
$schemaSql = file_get_contents(__DIR__ . '/../db/schema.sql');
$pdo->exec($schemaSql);

$CFG['geocode']['cache_ttl_hours'] = 1;

$callCount = 0;
$GLOBALS['__GEOCODE_HTTP_STUB'] = function($url) use (&$callCount) {
  $callCount += 1;
  return json_encode([
    [
      'lat' => '47.4979',
      'lon' => '19.0402',
      'address' => ['city' => 'Budapest'],
    ],
  ], JSON_UNESCAPED_UNICODE);
};

$result = geocode_lookup_or_fetch('Budapest, Example utca 1, 1052');
assert_equals('success', $result['status'], 'first lookup succeeds');
assert_true(empty($result['cached']), 'first lookup is not cached');
assert_equals(1, $callCount, 'HTTP stub called once for first lookup');

$resultCached = geocode_lookup_or_fetch('Budapest, Example utca 1, 1052');
assert_equals('success', $resultCached['status'], 'second lookup also succeeds');
assert_true(!empty($resultCached['cached']), 'second lookup served from cache');
assert_equals(1, $callCount, 'cached lookup does not trigger HTTP call');

$callCount = 0;
$GLOBALS['__GEOCODE_HTTP_STUB'] = function($url) use (&$callCount) {
  $callCount += 1;
  return json_encode([], JSON_UNESCAPED_UNICODE);
};

$errorResult = geocode_lookup_or_fetch('Nincs találat utca 9, 9999');
assert_equals('error', $errorResult['status'], 'missing address yields error');
assert_equals('noresult', $errorResult['error'], 'error code propagated');
assert_true(empty($errorResult['cached']), 'first error response not cached');
assert_equals(1, $callCount, 'error triggers single HTTP call');

$errorCached = geocode_lookup_or_fetch('Nincs találat utca 9, 9999');
assert_equals('error', $errorCached['status'], 'cached error still reported');
assert_true(!empty($errorCached['cached']), 'error response cached for TTL');
assert_equals(1, $callCount, 'cached error avoids extra HTTP call');

$callCount = 0;
$GLOBALS['__GEOCODE_HTTP_STUB'] = function($url) use (&$callCount) {
  $callCount += 1;
  if (strpos($url, 'Fo utca 5') !== false) {
    return json_encode([
      [
        'lat' => '46.253',
        'lon' => '20.141',
        'address' => ['city' => 'Szeged'],
      ],
    ], JSON_UNESCAPED_UNICODE);
  }
  return json_encode([
    [
      'lat' => '47.162',
      'lon' => '19.503',
      'address' => ['city' => 'Kecskemét'],
    ],
  ], JSON_UNESCAPED_UNICODE);
};

$batch = geocode_batch_lookup([
  'Fo utca 5, Szeged 6720',
  'Fo utca 5, Szeged 6720',
  'Kossuth ter 2, Kecskemet 6000',
]);
assert_equals(3, count($batch), 'batch returns entry for each query');
$uniqueQueries = array_filter($batch, function($entry) { return ($entry['status'] ?? '') === 'success'; });
assert_equals(2, $callCount, 'batch reuses HTTP response for duplicate queries');

$batchCached = geocode_batch_lookup([
  'Fo utca 5, Szeged 6720',
  'Kossuth ter 2, Kecskemet 6000',
]);
assert_equals(2, count($batchCached), 'cached batch still returns results');
assert_equals(2, $callCount, 'cached batch performs no additional HTTP calls');

unset($GLOBALS['__GEOCODE_HTTP_STUB']);
@unlink($tempDb);
@unlink($tempLock);

echo "All geocode cache tests passed.\n";
