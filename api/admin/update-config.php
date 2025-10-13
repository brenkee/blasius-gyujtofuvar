<?php
require __DIR__ . '/../../common.php';

header('X-Content-Type-Options: nosniff');

$CURRENT_USER = auth_require_login(['response' => 'json']);
if (!auth_user_is_admin($CURRENT_USER)) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$CONFIG_PATH = realpath(__DIR__ . '/../../config');
if ($CONFIG_PATH === false) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'config_path_missing'], JSON_UNESCAPED_UNICODE);
  exit;
}
$configFile = $CONFIG_PATH . '/config.json';
$backupFile = $CONFIG_PATH . '/config_backup.json';

function read_json_file($path) {
  if (!is_file($path)) {
    return null;
  }
  $raw = file_get_contents($path);
  if ($raw === false) {
    return null;
  }
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return null;
  }
  return $decoded;
}

function canonical_json($value) {
  return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

function compute_config_hash($config) {
  $canonical = canonical_json($config);
  return $canonical !== false ? sha1($canonical) : sha1('');
}

function is_assoc_array(array $value) {
  if ($value === []) {
    return false;
  }
  return array_keys($value) !== range(0, count($value) - 1);
}

function validate_structure($newValue, $currentValue, $path = '') {
  $type = gettype($currentValue);
  switch ($type) {
    case 'boolean':
      if (!is_bool($newValue)) {
        throw new RuntimeException('invalid_type:' . $path);
      }
      return;
    case 'integer':
    case 'double':
      if (!is_int($newValue) && !is_float($newValue)) {
        throw new RuntimeException('invalid_type:' . $path);
      }
      return;
    case 'string':
      if (!is_string($newValue)) {
        throw new RuntimeException('invalid_type:' . $path);
      }
      return;
    case 'array':
      if (!is_array($newValue)) {
        throw new RuntimeException('invalid_type:' . $path);
      }
      $isAssoc = is_assoc_array($currentValue);
      if ($isAssoc) {
        $currentKeys = array_keys($currentValue);
        $newKeys = array_keys($newValue);
        foreach ($newKeys as $key) {
          if (!array_key_exists($key, $currentValue)) {
            throw new RuntimeException('unknown_key:' . ($path === '' ? $key : $path . '.' . $key));
          }
        }
        foreach ($currentKeys as $key) {
          if (!array_key_exists($key, $newValue)) {
            throw new RuntimeException('missing_key:' . ($path === '' ? $key : $path . '.' . $key));
          }
          $childPath = $path === '' ? (string)$key : $path . '.' . $key;
          validate_structure($newValue[$key], $currentValue[$key], $childPath);
        }
        return;
      }
      $template = $currentValue;
      if ($template === []) {
        return;
      }
      $templateValue = $template[0];
      foreach ($newValue as $idx => $item) {
        $childPath = $path === '' ? (string)$idx : $path . '.' . $idx;
        validate_structure($item, $templateValue, $childPath);
      }
      return;
    default:
      throw new RuntimeException('unsupported_type:' . $path);
  }
}

function write_config_file($path, array $payload) {
  $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
  if ($encoded === false) {
    throw new RuntimeException('encode_failed');
  }
  $tmpPath = $path . '.tmp';
  if (file_put_contents($tmpPath, $encoded) === false) {
    throw new RuntimeException('write_failed');
  }
  if (!rename($tmpPath, $path)) {
    @unlink($tmpPath);
    throw new RuntimeException('rename_failed');
  }
}

function respond($data, $code = 200) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
  exit;
}

function load_current_config($configFile) {
  global $CFG_DEFAULT;
  if (is_file($configFile)) {
    $raw = file_get_contents($configFile);
    if ($raw === false) {
      throw new RuntimeException('config_read_failed');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      throw new RuntimeException('config_invalid');
    }
    return $decoded;
  }
  return $CFG_DEFAULT;
}

function ensure_backup($configFile, $backupFile) {
  if (!is_file($configFile)) {
    return;
  }
  $raw = file_get_contents($configFile);
  if ($raw === false) {
    throw new RuntimeException('backup_read_failed');
  }
  if (file_put_contents($backupFile, $raw) === false) {
    throw new RuntimeException('backup_write_failed');
  }
}

function with_config_lock($configFile, callable $callback) {
  $lockPath = $configFile . '.lock';
  $fh = fopen($lockPath, 'c+');
  if (!$fh) {
    throw new RuntimeException('lock_failed');
  }
  try {
    if (!flock($fh, LOCK_EX)) {
      throw new RuntimeException('lock_failed');
    }
    $result = $callback();
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
  } catch (Throwable $e) {
    flock($fh, LOCK_UN);
    fclose($fh);
    throw $e;
  }
}

function config_updated_at($configFile) {
  clearstatcache(true, $configFile);
  $mtime = @filemtime($configFile);
  if ($mtime === false || $mtime <= 0) {
    return null;
  }
  return gmdate('c', (int)$mtime);
}

if ($method === 'GET') {
  try {
    $currentConfig = load_current_config($configFile);
  } catch (RuntimeException $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
  }
  $hash = compute_config_hash($currentConfig);
  $metaOnly = isset($_GET['meta']) && $_GET['meta'] === '1';
  $payload = [
    'ok' => true,
    'hash' => $hash,
    'updatedAt' => config_updated_at($configFile),
    'backupAvailable' => is_file($backupFile),
  ];
  if (!$metaOnly) {
    $payload['config'] = $currentConfig;
  }
  respond($payload);
}

if ($method !== 'POST') {
  respond(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

csrf_require_token_from_request('json');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
  respond(['ok' => false, 'error' => 'invalid_payload'], 400);
}

$mode = $input['mode'] ?? 'update';

if ($mode === 'restore') {
  if (!is_file($backupFile)) {
    respond(['ok' => false, 'error' => 'backup_unavailable'], 400);
  }
  $backupData = read_json_file($backupFile);
  if (!is_array($backupData)) {
    respond(['ok' => false, 'error' => 'backup_invalid'], 400);
  }
  try {
    with_config_lock($configFile, function () use ($configFile, $backupData) {
      write_config_file($configFile, $backupData);
    });
  } catch (Throwable $e) {
    respond(['ok' => false, 'error' => 'restore_failed'], 500);
  }
  $hash = compute_config_hash($backupData);
  respond([
    'ok' => true,
    'config' => $backupData,
    'hash' => $hash,
    'backupAvailable' => true,
    'updatedAt' => config_updated_at($configFile),
  ]);
}

if ($mode !== 'update') {
  respond(['ok' => false, 'error' => 'unknown_mode'], 400);
}

if (!array_key_exists('config', $input)) {
  respond(['ok' => false, 'error' => 'missing_config'], 400);
}
$newConfig = $input['config'];
if (!is_array($newConfig)) {
  respond(['ok' => false, 'error' => 'invalid_config'], 400);
}
$expectedHash = isset($input['expectedHash']) ? (string)$input['expectedHash'] : '';

$result = null;
try {
  $result = with_config_lock($configFile, function () use ($configFile, $backupFile, $newConfig, $expectedHash) {
    $currentConfig = load_current_config($configFile);
    $currentHash = compute_config_hash($currentConfig);
    if ($expectedHash !== '' && $expectedHash !== $currentHash) {
      return [
        'status' => 'conflict',
        'hash' => $currentHash,
      ];
    }
    validate_structure($newConfig, $currentConfig, '');
    ensure_backup($configFile, $backupFile);
    write_config_file($configFile, $newConfig);
    $hash = compute_config_hash($newConfig);
    return [
      'status' => 'ok',
      'hash' => $hash,
    ];
  });
} catch (RuntimeException $e) {
  $error = $e->getMessage();
  $serverErrors = ['config_read_failed', 'config_invalid', 'backup_read_failed', 'backup_write_failed', 'encode_failed', 'rename_failed', 'lock_failed'];
  $status = in_array($error, $serverErrors, true) ? 500 : 400;
  respond(['ok' => false, 'error' => $error], $status);
} catch (Throwable $e) {
  respond(['ok' => false, 'error' => 'write_failed'], 500);
}

if (!is_array($result)) {
  respond(['ok' => false, 'error' => 'unexpected_state'], 500);
}

if (($result['status'] ?? null) === 'conflict') {
  respond(['ok' => false, 'error' => 'config_conflict', 'hash' => $result['hash'] ?? ''], 409);
}

if (($result['status'] ?? null) !== 'ok') {
  respond(['ok' => false, 'error' => 'unexpected_state'], 500);
}

respond([
  'ok' => true,
  'hash' => $result['hash'],
  'config' => $newConfig,
  'backupAvailable' => is_file($backupFile),
  'updatedAt' => config_updated_at($configFile),
]);
