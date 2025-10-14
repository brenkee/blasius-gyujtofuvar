<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login(['response' => 'json']);
$PERMISSIONS = auth_build_permissions($CURRENT_USER);
$FEATURES = app_features_for_user($CFG['features'] ?? [], $PERMISSIONS);
csrf_require_token_from_request('json');

header('X-Content-Type-Options: nosniff');

$changeWatcherRaw = isset($CFG['change_watcher']) && is_array($CFG['change_watcher']) ? $CFG['change_watcher'] : [];
$changeWatcherRevisionRaw = isset($changeWatcherRaw['revision']) && is_array($changeWatcherRaw['revision'])
  ? $changeWatcherRaw['revision']
  : [];
$changeWatcherLongPollRaw = isset($changeWatcherRaw['long_poll']) && is_array($changeWatcherRaw['long_poll'])
  ? $changeWatcherRaw['long_poll']
  : [];
$CHANGE_WATCHER_CFG = [
  'enabled' => array_key_exists('enabled', $changeWatcherRaw) ? (bool)$changeWatcherRaw['enabled'] : true,
  'pause_when_hidden' => array_key_exists('pause_when_hidden', $changeWatcherRaw)
    ? (bool)$changeWatcherRaw['pause_when_hidden']
    : true,
  'error_retry_delay_ms' => isset($changeWatcherRaw['error_retry_delay_ms'])
    ? max(0, (int)$changeWatcherRaw['error_retry_delay_ms'])
    : 1200,
  'revision' => [
    'enabled' => array_key_exists('enabled', $changeWatcherRevisionRaw)
      ? (bool)$changeWatcherRevisionRaw['enabled']
      : true,
    'interval_ms' => isset($changeWatcherRevisionRaw['interval_ms'])
      ? max(0, (int)$changeWatcherRevisionRaw['interval_ms'])
      : 12000,
  ],
  'long_poll' => [
    'timeout_seconds' => isset($changeWatcherLongPollRaw['timeout_seconds'])
      ? max(0, (float)$changeWatcherLongPollRaw['timeout_seconds'])
      : 25.0,
    'sleep_microseconds' => isset($changeWatcherLongPollRaw['sleep_microseconds'])
      ? max(0, (int)$changeWatcherLongPollRaw['sleep_microseconds'])
      : 300000,
  ],
];

if (!empty($DATA_INIT_ERROR)) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(503);
  echo json_encode([
    'ok' => false,
    'error' => 'db_init_failed',
    'message' => $DATA_INIT_ERROR
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_GET['action'] ?? null;
if (!$action) { http_response_code(400); echo 'Missing action'; exit; }

$jsonHeader = function(){ header('Content-Type: application/json; charset=utf-8'); };
$sendJsonError = function($message, $code = 400) use ($jsonHeader){
  $jsonHeader();
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
  exit;
};

function require_actor_id() {
  $actor = normalized_actor_id($_SERVER['HTTP_X_CLIENT_ID'] ?? '');
  if (!$actor) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'error'=>'missing_client_id'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  return $actor;
}

function require_request_id() {
  $req = normalized_request_id($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
  if (!$req) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'error'=>'missing_request_id'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  return $req;
}

function optional_batch_id() {
  return normalized_batch_id($_SERVER['HTTP_X_BATCH_ID'] ?? '');
}

function append_change_events($rev, $actorId, $requestId, $batchId, array $events, array $meta = []) {
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

function commit_dataset_update(array $newItems, array $newRoundMeta, $actorId, $requestId, $batchId, $action, array $actionMeta = []) {
  global $DATA_FILE;
  return state_lock(function() use ($DATA_FILE, $newItems, $newRoundMeta, $actorId, $requestId, $batchId, $action, $actionMeta) {
    [$oldItems, $oldRoundMeta] = data_store_read($DATA_FILE);
    $writeOk = data_store_write($DATA_FILE, $newItems, $newRoundMeta);
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
      $events = array_map(function($ev) use ($action) {
        if (!isset($ev['meta']) || !is_array($ev['meta'])) {
          $ev['meta'] = [];
        }
        if (!isset($ev['meta']['source_action'])) {
          $ev['meta']['source_action'] = $action;
        }
        return $ev;
      }, $events);
    }
    append_change_events($newRev, $actorId, $requestId, $batchId, $events, $actionMeta);
    return ['rev' => $newRev, 'events' => $events];
  });
}

if ($action === 'cfg') {
  $jsonHeader();
  $panelStickyRaw = $CFG['ui']['panel']['sticky_top'] ?? false;
  if (is_array($panelStickyRaw)) {
    $panelSticky = ['enabled' => !empty($panelStickyRaw['enabled'])];
    foreach (['top','offset','shadow','background','z_index','zIndex'] as $prop) {
      if (array_key_exists($prop, $panelStickyRaw)) {
        $panelSticky[$prop] = $panelStickyRaw[$prop];
      }
    }
  } else {
    $panelSticky = ['enabled' => (bool)$panelStickyRaw];
  }
  $markerCfgRaw = isset($CFG['ui']['marker']) && is_array($CFG['ui']['marker']) ? $CFG['ui']['marker'] : [];
  $markerOverlapRaw = isset($markerCfgRaw['overlap_badge']) && is_array($markerCfgRaw['overlap_badge']) ? $markerCfgRaw['overlap_badge'] : [];
  $markerFocusRingRaw = isset($markerCfgRaw['focus_ring']) && is_array($markerCfgRaw['focus_ring']) ? $markerCfgRaw['focus_ring'] : [];
  $markerCfg = [
    'icon_size' => isset($markerCfgRaw['icon_size']) ? (int)$markerCfgRaw['icon_size'] : 38,
    'font_size' => isset($markerCfgRaw['font_size']) ? (int)$markerCfgRaw['font_size'] : 14,
    'font_family' => isset($markerCfgRaw['font_family']) ? (string)$markerCfgRaw['font_family'] : null,
    'font_weight' => isset($markerCfgRaw['font_weight']) ? (string)$markerCfgRaw['font_weight'] : null,
    'auto_contrast' => array_key_exists('auto_contrast', $markerCfgRaw) ? (bool)$markerCfgRaw['auto_contrast'] : true,
    'default_text_color' => isset($markerCfgRaw['default_text_color']) ? (string)$markerCfgRaw['default_text_color'] : null,
    'view_box_size' => isset($markerCfgRaw['view_box_size']) ? (int)$markerCfgRaw['view_box_size'] : null,
    'icon_path' => isset($markerCfgRaw['icon_path']) ? (string)$markerCfgRaw['icon_path'] : null,
    'stroke_color' => isset($markerCfgRaw['stroke_color']) ? (string)$markerCfgRaw['stroke_color'] : null,
    'stroke_opacity' => isset($markerCfgRaw['stroke_opacity']) ? (float)$markerCfgRaw['stroke_opacity'] : null,
    'stroke_width' => isset($markerCfgRaw['stroke_width']) ? (float)$markerCfgRaw['stroke_width'] : null,
    'icon_anchor_x' => array_key_exists('icon_anchor_x', $markerCfgRaw) ? $markerCfgRaw['icon_anchor_x'] : null,
    'icon_anchor_y' => array_key_exists('icon_anchor_y', $markerCfgRaw) ? $markerCfgRaw['icon_anchor_y'] : null,
    'popup_anchor_x' => array_key_exists('popup_anchor_x', $markerCfgRaw) ? $markerCfgRaw['popup_anchor_x'] : 0,
    'popup_anchor_y' => array_key_exists('popup_anchor_y', $markerCfgRaw) ? $markerCfgRaw['popup_anchor_y'] : null,
    'focus_ring_radius' => isset($markerCfgRaw['focus_ring_radius']) ? (float)$markerCfgRaw['focus_ring_radius'] : 80.0,
    'focus_ring_color' => isset($markerCfgRaw['focus_ring_color']) ? (string)$markerCfgRaw['focus_ring_color'] : 'auto',
    'overlap_badge' => [
      'size' => isset($markerOverlapRaw['size']) ? (float)$markerOverlapRaw['size'] : null,
      'margin_right' => isset($markerOverlapRaw['margin_right']) ? (float)$markerOverlapRaw['margin_right'] : null,
      'offset_y' => isset($markerOverlapRaw['offset_y']) ? (float)$markerOverlapRaw['offset_y'] : null,
      'font_scale' => isset($markerOverlapRaw['font_scale']) ? (float)$markerOverlapRaw['font_scale'] : null,
      'corner_radius' => isset($markerOverlapRaw['corner_radius']) ? (float)$markerOverlapRaw['corner_radius'] : null,
      'fill' => isset($markerOverlapRaw['fill']) ? (string)$markerOverlapRaw['fill'] : null,
      'fill_opacity' => isset($markerOverlapRaw['fill_opacity']) ? (float)$markerOverlapRaw['fill_opacity'] : null,
      'stroke' => isset($markerOverlapRaw['stroke']) ? (string)$markerOverlapRaw['stroke'] : null,
      'stroke_opacity' => isset($markerOverlapRaw['stroke_opacity']) ? (float)$markerOverlapRaw['stroke_opacity'] : null,
      'stroke_width' => isset($markerOverlapRaw['stroke_width']) ? (float)$markerOverlapRaw['stroke_width'] : null,
      'text_color' => isset($markerOverlapRaw['text_color']) ? (string)$markerOverlapRaw['text_color'] : null,
      'font_family' => isset($markerOverlapRaw['font_family']) ? (string)$markerOverlapRaw['font_family'] : null,
      'font_weight' => isset($markerOverlapRaw['font_weight']) ? (string)$markerOverlapRaw['font_weight'] : null,
      'distance_threshold_ratio' => isset($markerOverlapRaw['distance_threshold_ratio'])
        ? (float)$markerOverlapRaw['distance_threshold_ratio']
        : null,
    ],
    'focus_ring' => [
      'radius' => isset($markerFocusRingRaw['radius']) ? (float)$markerFocusRingRaw['radius'] : null,
      'weight' => isset($markerFocusRingRaw['weight']) ? (float)$markerFocusRingRaw['weight'] : null,
      'stroke_opacity' => isset($markerFocusRingRaw['stroke_opacity']) ? (float)$markerFocusRingRaw['stroke_opacity'] : null,
      'fill_opacity' => isset($markerFocusRingRaw['fill_opacity']) ? (float)$markerFocusRingRaw['fill_opacity'] : null,
      'initial_opacity' => isset($markerFocusRingRaw['initial_opacity']) ? (float)$markerFocusRingRaw['initial_opacity'] : null,
      'initial_fill_opacity' => isset($markerFocusRingRaw['initial_fill_opacity']) ? (float)$markerFocusRingRaw['initial_fill_opacity'] : null,
      'fade_step' => isset($markerFocusRingRaw['fade_step']) ? (float)$markerFocusRingRaw['fade_step'] : null,
      'fill_fade_step' => isset($markerFocusRingRaw['fill_fade_step']) ? (float)$markerFocusRingRaw['fill_fade_step'] : null,
      'fade_interval_ms' => isset($markerFocusRingRaw['fade_interval_ms']) ? (int)$markerFocusRingRaw['fade_interval_ms'] : null,
      'lifetime_ms' => isset($markerFocusRingRaw['lifetime_ms']) ? (int)$markerFocusRingRaw['lifetime_ms'] : null,
    ],
  ];
  $saveStatusCfg = $CFG['ui']['save_status'] ?? [];
  if (!is_array($saveStatusCfg)) {
    $saveStatusCfg = [];
  }
  $JS_CFG = [
    "base_url" => $CFG['base_url'],
    "app" => [
      "title" => $CFG['app']['title'],
      "auto_sort_by_round" => (bool)$CFG['app']['auto_sort_by_round'],
      "round_zero_at_bottom" => (bool)$CFG['app']['round_zero_at_bottom'],
      "default_collapsed" => (bool)$CFG['app']['default_collapsed']
    ],
    "history" => [
      "undo_enabled" => !empty($CFG['history']['undo_enabled']),
      "max_steps" => (int)($CFG['history']['max_steps'] ?? 3)
    ],
    "change_watcher" => $CHANGE_WATCHER_CFG,
    "features" => $FEATURES,
    "ui" => [
      "panel_min_px" => (int)$CFG['ui']['panel_min_px'],
      "panel_pref_vw" => (int)$CFG['ui']['panel_pref_vw'],
      "panel_max_px" => (int)$CFG['ui']['panel_max_px'],
      "colors" => $CFG['ui']['colors'] ?? [],
      "marker" => $markerCfg,
      "panel" => [
        "sticky_top" => $panelSticky
      ],
      "save_status" => $saveStatusCfg
    ],
    "map" => [
      "tiles" => [
        "url" => $CFG['map']['tiles']['url'],
        "attribution" => $CFG['map']['tiles']['attribution']
      ],
      "fit_bounds" => $CFG['map']['fit_bounds'] ?? null,
      "max_bounds_pad" => isset($CFG['map']['max_bounds_pad']) ? (float)$CFG['map']['max_bounds_pad'] : 0.6
    ],
    "rounds" => array_values($ROUND_MAP),
    "routing" => [
      "origin" => $CFG['routing']['origin'] ?? 'Maglód',
      "origin_coordinates" => [
        'lat' => isset($CFG['routing']['origin_coordinates']['lat']) ? (float)$CFG['routing']['origin_coordinates']['lat'] : null,
        'lon' => isset($CFG['routing']['origin_coordinates']['lon']) ? (float)$CFG['routing']['origin_coordinates']['lon'] : null,
      ],
      "max_waypoints" => (int)($CFG['routing']['max_waypoints'] ?? 10),
      "geocode_origin_on_start" => !empty($CFG['routing']['geocode_origin_on_start'])
    ],
    "text" => $CFG['text'] ?? [],
    "items" => $CFG['items'] ?? [],
    "export" => $CFG['export'] ?? [],
    "print" => $CFG['print'] ?? []
  ];
  $JS_CFG['permissions'] = $PERMISSIONS;
  echo json_encode($JS_CFG, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'session') {
  $jsonHeader();
  echo json_encode(['ok' => true, 'client_id' => generate_client_id()], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'revision') {
  $jsonHeader();
  echo json_encode(['rev' => read_current_revision()], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'changes') {
  if (empty($CHANGE_WATCHER_CFG['enabled'])) {
    $jsonHeader();
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'change_watcher_disabled'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
  $excludeActor = normalized_actor_id($_GET['exclude_actor'] ?? '') ?? null;
  $excludeBatchRaw = $_GET['exclude_batch'] ?? '';
  $excludeBatchList = [];
  $batchInputs = is_array($excludeBatchRaw) ? $excludeBatchRaw : explode(',', (string)$excludeBatchRaw);
  foreach ($batchInputs as $candidate) {
    $bid = normalized_batch_id($candidate);
    if ($bid) {
      $excludeBatchList[] = $bid;
    }
  }
  $excludeBatchList = array_values(array_unique($excludeBatchList));

  $timeout = isset($CHANGE_WATCHER_CFG['long_poll']['timeout_seconds'])
    ? (float)$CHANGE_WATCHER_CFG['long_poll']['timeout_seconds']
    : 25.0;
  if ($timeout <= 0) {
    http_response_code(204);
    exit;
  }
  $start = microtime(true);
  $sleepMicro = isset($CHANGE_WATCHER_CFG['long_poll']['sleep_microseconds'])
    ? (int)$CHANGE_WATCHER_CFG['long_poll']['sleep_microseconds']
    : 300000;
  if ($sleepMicro < 0) {
    $sleepMicro = 0;
  }

  while (true) {
    $entries = read_change_log_entries();
    $filtered = [];
    $maxRev = $since;
    foreach ($entries as $entry) {
      $rev = isset($entry['rev']) ? (int)$entry['rev'] : 0;
      if ($rev <= $since) {
        continue;
      }
      if ($rev > $maxRev) {
        $maxRev = $rev;
      }
      if ($excludeActor && isset($entry['actor_id']) && $entry['actor_id'] === $excludeActor) {
        continue;
      }
      if (!empty($excludeBatchList) && isset($entry['batch_id']) && in_array($entry['batch_id'], $excludeBatchList, true)) {
        continue;
      }
      $filtered[] = $entry;
    }

    if (!empty($filtered)) {
      $jsonHeader();
      echo json_encode(['events' => $filtered, 'latest_rev' => $maxRev], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      exit;
    }

    if ($maxRev > $since) {
      $jsonHeader();
      echo json_encode(['events' => [], 'latest_rev' => $maxRev], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      exit;
    }

    if ((microtime(true) - $start) >= $timeout) {
      http_response_code(204);
      exit;
    }
    usleep($sleepMicro);
  }
}

if ($action === 'load') {
  $jsonHeader();
  [$items, $roundMeta] = data_store_read($DATA_FILE);
  if (!$roundMeta) { $roundMeta = (object)[]; }
  $rev = read_current_revision();
  echo json_encode(["items"=>$items, "round_meta"=>$roundMeta, "rounds"=>$CFG["rounds"], "revision"=>$rev], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'save') {
  $jsonHeader();
  if (empty($PERMISSIONS['canSave'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $actorId = require_actor_id();
  $requestId = require_request_id();
  $batchId = optional_batch_id();
  $body = file_get_contents('php://input');
  $arr = json_decode($body, true);
  if (!is_array($arr)) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
  $items = [];
  $roundMeta = [];
  if (isset($arr['items'])) {
    $items = is_array($arr['items']) ? array_values($arr['items']) : [];
    $roundMeta = normalize_round_meta($arr['round_meta'] ?? []);
  } elseif (is_list_array($arr)) {
    $items = array_values($arr);
  } else {
    http_response_code(400); echo json_encode(['ok'=>false]); exit;
  }
  try {
    $result = commit_dataset_update($items, $roundMeta, $actorId, $requestId, $batchId, 'save', ['scope' => 'full_save']);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'write_failed'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  backup_now($CFG, $DATA_FILE);
  echo json_encode(['ok' => true, 'rev' => $result['rev'] ?? null]);
  exit;
}

if ($action === 'geocode') {
  $jsonHeader();
  $q = trim($_GET['q'] ?? '');
  if ($q === '') { http_response_code(400); echo json_encode(['error'=>'empty']); exit; }

  $params = http_build_query([
    'q'=>$q,'format'=>'jsonv2','limit'=>1,'addressdetails'=>1,
    'countrycodes'=>$CFG['geocode']['countrycodes'] ?? 'hu',
    'accept-language'=>$CFG['geocode']['language'] ?? 'hu'
  ]);
  $ctx = stream_context_create(['http'=>[
    'method'=>'GET',
    'header'=>[
      'User-Agent: '.($CFG['geocode']['user_agent'] ?? 'fuvarszervezo-internal/1.5'),
      'Accept: application/json'
    ],
    'timeout'=>10
  ]]);
  $resp = @file_get_contents("https://nominatim.openstreetmap.org/search?$params", false, $ctx);
  if ($resp === false) { http_response_code(502); echo json_encode(['error'=>'fetch']); exit; }
  $arr = json_decode($resp, true);
  if (!is_array($arr) || !count($arr)) { http_response_code(404); echo json_encode(['error'=>'noresult']); exit; }
  $best = $arr[0];
  $addr = isset($best['address']) && is_array($best['address']) ? $best['address'] : [];
  $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? '';
  echo json_encode(['lat'=>(float)$best['lat'], 'lon'=>(float)$best['lon'], 'city'=>$city], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'export') {
  $roundFilter = isset($_GET['round']) ? (int)$_GET['round'] : null;

  $error = null;
  $csv = generate_export_csv($CFG, $DATA_FILE, $roundFilter, $error);
  if ($csv === null) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $error ?: 'Export hiba';
    exit;
  }

  @file_put_contents($EXPORT_FILE, $csv);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $EXPORT_NAME .'"');
  echo $csv; exit;
}

if ($action === 'import_csv') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sendJsonError('Hibás HTTP metódus.', 405);
  }
  if (empty($PERMISSIONS['canImport'])) {
    $sendJsonError('Nincs jogosultság az importáláshoz.', 403);
  }
  $actorId = require_actor_id();
  $requestId = require_request_id();
  $batchId = optional_batch_id();
  if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
    $sendJsonError('Nem található feltöltött fájl.');
  }
  $modeRaw = isset($_POST['mode']) ? strtolower(trim((string)$_POST['mode'])) : 'replace';
  $importMode = $modeRaw === 'append' ? 'append' : 'replace';
  $uploadErr = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_OK);
  if ($uploadErr !== UPLOAD_ERR_OK) {
    $sendJsonError('A fájl feltöltése nem sikerült.');
  }
  $tmpFile = $_FILES['file']['tmp_name'] ?? '';
  if (!$tmpFile || !is_file($tmpFile)) {
    $sendJsonError('A feltöltött fájl nem érhető el.');
  }

  $raw = file_get_contents($tmpFile);
  if ($raw === false) {
    $sendJsonError('A CSV fájl nem olvasható.');
  }
  $rawTrimmed = trim($raw);
  if ($rawTrimmed === '') {
    $sendJsonError('A CSV fájl üres.');
  }

  $lines = preg_split('/\r\n|\n|\r/', $raw);
  $firstLine = '';
  foreach ($lines as $line) {
    if ($line !== '') { $firstLine = $line; break; }
  }
  if ($firstLine === '') {
    $sendJsonError('A CSV fejléce nem olvasható.');
  }
  $firstLine = preg_replace('/^\xEF\xBB\xBF/u', '', $firstLine);
  $semi = substr_count($firstLine, ';');
  $comma = substr_count($firstLine, ',');
  $delimiter = $semi >= $comma ? ';' : ',';

  $fh = fopen($tmpFile, 'r');
  if (!$fh) {
    $sendJsonError('A CSV fájl nem nyitható meg.');
  }
  $header = fgetcsv($fh, 0, $delimiter);
  if ($header === false || $header === null) {
    fclose($fh);
    $sendJsonError('A CSV fejléce nem olvasható.');
  }
  if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string)$header[0]);
  }
  $headers = [];
  $rawHeaders = [];
  $normalizeHeaderName = function($value) {
    $name = trim((string)$value);
    if ($name === '') return null;
    $name = str_replace("\xEF\xBB\xBF", '', $name);
    $name = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $name);
    $name = str_replace("\xC2\xA0", ' ', $name);
    $name = trim($name);
    return $name !== '' ? $name : null;
  };

  foreach ($header as $idx => $col) {
    $rawHeaders[$idx] = $normalizeHeaderName($col);
  }

  [$existingItems, $existingRoundMeta] = data_store_read($DATA_FILE);
  if (!is_array($existingItems)) { $existingItems = []; }
  if (!is_array($existingRoundMeta)) { $existingRoundMeta = []; }

  $itemsCfg = $CFG['items'] ?? [];
  $fieldDefs = array_values(array_filter($itemsCfg['fields'] ?? [], function($f){ return ($f['enabled'] ?? true) !== false; }));
  $metricDefs = array_values(array_filter($itemsCfg['metrics'] ?? [], function($m){ return ($m['enabled'] ?? true) !== false; }));
  $fieldMap = [];
  foreach ($fieldDefs as $def) {
    $fid = $def['id'] ?? null;
    if ($fid) { $fieldMap[$fid] = $def; }
  }
  $metricIds = [];
  foreach ($metricDefs as $def) {
    $mid = $def['id'] ?? null;
    if ($mid) { $metricIds[$mid] = true; }
  }

  $addressColumnLookup = ['city' => true, 'lat' => true, 'lon' => true, 'collapsed' => true];
  foreach (array_keys($fieldMap) as $fid) {
    $addressColumnLookup[$fid] = true;
  }
  foreach (array_keys($metricIds) as $mid) {
    $addressColumnLookup[$mid] = true;
  }

  $canonicalLookup = [];
  $registerCanonical = function($key) use (&$canonicalLookup) {
    if ($key === null) return;
    $canonicalLookup[strtolower($key)] = $key;
  };
  foreach (['type', 'id', 'round', 'city', 'lat', 'lon'] as $baseKey) {
    $registerCanonical($baseKey);
  }
  foreach (array_keys($fieldMap) as $fid) {
    $registerCanonical($fid);
  }
  foreach (array_keys($metricIds) as $mid) {
    $registerCanonical($mid);
  }

  $headers = [];
  $usedCanonicalHeaders = [];
  foreach ($rawHeaders as $idx => $name) {
    if ($name === null) {
      $headers[$idx] = null;
      continue;
    }
    $lower = strtolower($name);
    if (isset($canonicalLookup[$lower])) {
      $canonical = $canonicalLookup[$lower];
    } else {
      $canonical = $name;
    }
    $canonicalLower = strtolower($canonical);
    if (isset($usedCanonicalHeaders[$canonicalLower])) {
      $headers[$idx] = null;
      continue;
    }
    $headers[$idx] = $canonical;
    $usedCanonicalHeaders[$canonicalLower] = true;
  }

  $usedIds = [];
  if ($importMode === 'append') {
    foreach ($existingItems as $existing) {
      if (!is_array($existing)) { continue; }
      $eid = isset($existing['id']) ? trim((string)$existing['id']) : '';
      if ($eid !== '') { $usedIds[$eid] = true; }
    }
  }
  $makeId = function() use (&$usedIds) {
    do {
      try {
        $rand = bin2hex(random_bytes(6));
      } catch (Throwable $e) {
        if (function_exists('openssl_random_pseudo_bytes')) {
          $alt = openssl_random_pseudo_bytes(6);
          $rand = $alt !== false ? bin2hex($alt) : bin2hex(pack('N', mt_rand()));
        } else {
          $rand = bin2hex(pack('N', mt_rand()));
        }
      }
      $id = 'row_' . $rand;
    } while(isset($usedIds[$id]));
    $usedIds[$id] = true;
    return $id;
  };
  $parseNumber = function($value, $column, $rowNumber) use ($sendJsonError) {
    $str = trim((string)$value);
    if ($str === '') return null;
    $norm = str_replace(["\xC2\xA0", ' '], '', $str);
    $norm = str_replace(',', '.', $norm);
    if (!is_numeric($norm)) {
      $sendJsonError("Érvénytelen szám a(z) {$column} oszlopban (sor: {$rowNumber}).");
    }
    return (float)$norm;
  };

  $rowNumber = 1;
  $items = [];
  $routeMetaUpdates = [];
  while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
    $rowNumber++;
    if (!is_array($row)) { continue; }
    $allEmpty = true;
    foreach ($row as $val) {
      if (trim((string)$val) !== '') { $allEmpty = false; break; }
    }
    if ($allEmpty) { continue; }

    $assoc = [];
    foreach ($headers as $idx => $colName) {
      if ($colName === null) continue;
      $assoc[$colName] = $row[$idx] ?? '';
    }

    $typeRaw = isset($assoc['type']) ? trim((string)$assoc['type']) : '';
    unset($assoc['type']);
    $typeNormalized = $typeRaw === '' ? 'address' : strtolower($typeRaw);
    if ($typeNormalized !== 'address' && $typeNormalized !== 'route') {
      fclose($fh);
      $sendJsonError("Ismeretlen típus a(z) {$rowNumber}. sorban (érték: {$typeRaw}).");
    }

    $idRaw = isset($assoc['id']) ? trim((string)$assoc['id']) : '';
    unset($assoc['id']);

    $roundRaw = isset($assoc['round']) ? trim((string)$assoc['round']) : '';
    unset($assoc['round']);
    if ($roundRaw === '') {
      $round = 0;
    } else {
      if (filter_var($roundRaw, FILTER_VALIDATE_INT) === false) {
        fclose($fh);
        $sendJsonError("Érvénytelen kör azonosító a(z) {$rowNumber}. sorban.");
      }
      $round = (int)$roundRaw;
    }

    if ($typeNormalized === 'route') {
      $roundKey = (string)$round;
      $meta = [];
      $hasValue = false;
      foreach ($assoc as $key => $value) {
        if ($key === null) continue;
        $keyStr = (string)$key;
        if ($keyStr === '' || strpos($keyStr, '_') === 0) continue;
        if (isset($addressColumnLookup[$keyStr])) continue;
        $val = is_scalar($value) ? trim((string)$value) : '';
        if ($val === '') continue;
        $meta[$keyStr] = $val;
        $hasValue = true;
      }
      if ($hasValue) {
        $routeMetaUpdates[$roundKey] = $meta;
      } else {
        $routeMetaUpdates[$roundKey] = [];
      }
      continue;
    }

    if ($idRaw === '') {
      $id = $makeId();
    } else {
      if (isset($usedIds[$idRaw])) {
        $id = $makeId();
      } else {
        $id = $idRaw;
        $usedIds[$id] = true;
      }
    }

    $item = ['id' => $id, 'round' => $round];

    if (array_key_exists('city', $assoc)) {
      $item['city'] = trim((string)$assoc['city']);
      unset($assoc['city']);
    } else {
      $item['city'] = $item['city'] ?? '';
    }

    if (array_key_exists('lat', $assoc)) {
      $item['lat'] = $parseNumber($assoc['lat'], 'lat', $rowNumber);
      unset($assoc['lat']);
    } else {
      $item['lat'] = null;
    }
    if (array_key_exists('lon', $assoc)) {
      $item['lon'] = $parseNumber($assoc['lon'], 'lon', $rowNumber);
      unset($assoc['lon']);
    } else {
      $item['lon'] = null;
    }

    foreach ($fieldMap as $fid => $def) {
      if (array_key_exists($fid, $assoc)) {
        $val = $assoc[$fid];
        if (($def['type'] ?? '') === 'number') {
          $item[$fid] = $parseNumber($val, $fid, $rowNumber);
        } else {
          $item[$fid] = is_scalar($val) ? trim((string)$val) : '';
        }
        unset($assoc[$fid]);
      } else {
        if (array_key_exists('default', $def)) {
          $item[$fid] = $def['default'];
        } elseif (($def['type'] ?? '') === 'number') {
          $item[$fid] = null;
        } else {
          if (!array_key_exists($fid, $item)) {
            $item[$fid] = '';
          }
        }
      }
    }

    foreach (array_keys($metricIds) as $mid) {
      if (array_key_exists($mid, $assoc)) {
        $item[$mid] = $parseNumber($assoc[$mid], $mid, $rowNumber);
        unset($assoc[$mid]);
      } else {
        if (!array_key_exists($mid, $item)) {
          $item[$mid] = null;
        }
      }
    }

    if (!array_key_exists('city', $item)) { $item['city'] = ''; }
    if (!array_key_exists('lat', $item)) { $item['lat'] = null; }
    if (!array_key_exists('lon', $item)) { $item['lon'] = null; }

    foreach ($assoc as $key => $value) {
      if ($key === null) continue;
      $keyStr = (string)$key;
      if ($keyStr === '' || strpos($keyStr, '_') === 0 || array_key_exists($keyStr, $item)) continue;
      if (isset($addressColumnLookup[$keyStr])) continue;
      $item[$keyStr] = is_scalar($value) ? trim((string)$value) : $value;
    }

    $items[] = $item;
  }
  fclose($fh);

  $roundMeta = $existingRoundMeta;
  if (!is_array($roundMeta)) { $roundMeta = []; }

  $baseRoundMeta = $importMode === 'append' ? $roundMeta : [];
  if (!is_array($baseRoundMeta)) { $baseRoundMeta = []; }
  foreach ($routeMetaUpdates as $roundKey => $meta) {
    $key = (string)$roundKey;
    if ($key === '') continue;
    if (!is_array($meta) || empty($meta)) {
      unset($baseRoundMeta[$key]);
      continue;
    }
    $baseRoundMeta[$key] = $meta;
  }
  $roundMeta = normalize_round_meta($baseRoundMeta);

  $finalItems = $importMode === 'append' ? array_merge($existingItems, $items) : $items;

  try {
    $result = commit_dataset_update($finalItems, $roundMeta, $actorId, $requestId, $batchId ?: ('batch_'.$requestId), 'import', [
      'mode' => $importMode,
      'imported_count' => count($items)
    ]);
  } catch (Throwable $e) {
    $sendJsonError('Az importálás nem sikerült.', 500);
  }

  $jsonHeader();
  $importedIds = [];
  foreach ($items as $item) {
    if (!is_array($item)) { continue; }
    $iid = isset($item['id']) ? trim((string)$item['id']) : '';
    if ($iid !== '') { $importedIds[] = $iid; }
  }
  backup_now($CFG, $DATA_FILE);
  echo json_encode([
    'ok' => true,
    'items' => $finalItems,
    'round_meta' => $roundMeta,
    'imported_ids' => $importedIds,
    'mode' => $importMode,
    'rev' => $result['rev'] ?? null
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'delete_round') {
  $jsonHeader();
  if (empty($PERMISSIONS['canDelete'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $actorId = require_actor_id();
  $requestId = require_request_id();
  $batchId = optional_batch_id();
  $body = file_get_contents('php://input');
  $req = json_decode($body, true);
  $rid = isset($req['round']) ? (int)$req['round'] : (int)($_GET['round'] ?? 0);

  $itemsCfg = $CFG['items'] ?? [];
  $metricsCfg = array_values(array_filter($itemsCfg['metrics'] ?? [], function($m){ return ($m['enabled'] ?? true) !== false; }));
  $labelFieldId = $itemsCfg['label_field_id'] ?? 'label';
  $addressFieldId = $itemsCfg['address_field_id'] ?? 'address';
  $noteFieldId = $itemsCfg['note_field_id'] ?? 'note';
  $sumTemplate = $CFG['text']['group']['sum_template'] ?? 'Összesen: {parts}';
  $sumSeparator = $CFG['text']['group']['sum_separator'] ?? ' · ';
  $formatMetric = function($metric, $value, $context='row'){
    $precision = isset($metric['precision']) ? (int)$metric['precision'] : 0;
    $formatted = number_format((float)$value, $precision, '.', '');
    $unit = $metric['unit'] ?? '';
    $label = $metric['label'] ?? '';
    $tplKey = $context === 'row' ? 'row_format' : 'group_format';
    if (!empty($metric[$tplKey])) {
      return str_replace(['{value}','{sum}','{unit}','{label}'], [$formatted,$formatted,$unit,$label], $metric[$tplKey]);
    }
    return trim($formatted . ($unit ? ' '.$unit : ''));
  };

  [$items, $roundMeta] = data_store_read($DATA_FILE);
  $kept = []; $removed = [];
  foreach ($items as $it) {
    if ((int)($it['round'] ?? 0) === $rid) $removed[] = $it; else $kept[] = $it;
  }
  $archiveLines = [];
  if (count($removed) > 0) {
    $dt = date('Y-m-d H:i:s');
    $roundLabel = $ROUND_MAP[$rid]['label'] ?? (string)$rid;

    $totalParts = [];
    foreach ($metricsCfg as $metric){
      $id = $metric['id'] ?? null; if (!$id) continue;
      $sum = 0.0;
      foreach ($removed as $t){ if (isset($t[$id]) && is_numeric($t[$id])) $sum += (float)$t[$id]; }
      $totalParts[] = $formatMetric($metric, $sum, 'group');
    }
    $summary = $totalParts ? str_replace('{parts}', implode($sumSeparator, $totalParts), $sumTemplate) : '';
    $headerLine = "[$dt] TÖRÖLT KÖR: $rid – $roundLabel";
    $plannedLabel = $CFG['text']['round']['planned_date_label'] ?? 'Tervezett dátum';
    $plannedKey = (string)$rid;
    if (isset($roundMeta[$plannedKey]['planned_date'])) {
      $plannedValue = trim((string)$roundMeta[$plannedKey]['planned_date']);
      if ($plannedValue !== '') {
        $headerLine .= '  | ' . $plannedLabel . ': ' . $plannedValue;
      }
    }
    if ($summary) {
      $headerLine .= '  | ' . $summary;
    }
    $archiveLines[] = $headerLine;
    foreach ($removed as $t) {
      $parts = [];
      foreach ([$labelFieldId, $addressFieldId, $noteFieldId] as $k) {
        if (!$k) continue;
        $v = trim((string)($t[$k] ?? '')); if ($v!=='') $parts[] = $v;
      }
      foreach ($metricsCfg as $metric){
        $id = $metric['id'] ?? null; if (!$id) continue;
        if (isset($t[$id]) && $t[$id] !== '') $parts[] = $formatMetric($metric, $t[$id], 'row');
      }
      $archiveLines[] = "- " . (count($parts)? implode(' | ', $parts) : '—');
    }
    $archiveLines[] = "";
  }
  if (isset($roundMeta[(string)$rid])) {
    unset($roundMeta[(string)$rid]);
  }

  try {
    $result = commit_dataset_update($kept, $roundMeta, $actorId, $requestId, $batchId, 'delete_round', [
      'round' => $rid,
      'deleted_count' => count($removed)
    ]);
  } catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'delete_failed'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (!empty($archiveLines)) {
    @file_put_contents($ARCHIVE_FILE, implode(PHP_EOL,$archiveLines).PHP_EOL, FILE_APPEND|LOCK_EX);
  }
  backup_now($CFG, $DATA_FILE);
  echo json_encode(['ok'=>true,'deleted'=>count($removed),'rev'=>$result['rev'] ?? null]);
  exit;
}

if ($action === 'download_archive') {
  stream_file_download($ARCHIVE_FILE, 'fuvar_archive.txt', 'text/plain; charset=utf-8');
}

http_response_code(404);
echo 'Unknown action';
