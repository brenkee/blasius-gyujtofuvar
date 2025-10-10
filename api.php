<?php
require __DIR__ . '/common.php';

header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? null;
if (!$action) { http_response_code(400); echo 'Missing action'; exit; }

$jsonHeader = function(){ header('Content-Type: application/json; charset=utf-8'); };
$sendJsonError = function($message, $code = 400) use ($jsonHeader){
  $jsonHeader();
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
  exit;
};

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
  $saveStatusCfg = $CFG['ui']['save_status'] ?? [];
  if (!is_array($saveStatusCfg)) {
    $saveStatusCfg = [];
  }
  $JS_CFG = [
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
    "features" => $CFG['features'] ?? [],
    "ui" => [
      "panel_min_px" => (int)$CFG['ui']['panel_min_px'],
      "panel_pref_vw" => (int)$CFG['ui']['panel_pref_vw'],
      "panel_max_px" => (int)$CFG['ui']['panel_max_px'],
      "colors" => $CFG['ui']['colors'] ?? [],
      "marker" => [
        "icon_size" => (int)$CFG['ui']['marker']['icon_size'],
        "font_size" => (int)$CFG['ui']['marker']['font_size'],
        "auto_contrast" => (bool)$CFG['ui']['marker']['auto_contrast'],
        "focus_ring_radius" => isset($CFG['ui']['marker']['focus_ring_radius']) ? (float)$CFG['ui']['marker']['focus_ring_radius'] : 80.0,
        "focus_ring_color" => $CFG['ui']['marker']['focus_ring_color'] ?? 'auto'
      ],
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
  echo json_encode($JS_CFG, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'load') {
  $jsonHeader();
  [$items, $roundMeta] = data_store_read($DATA_FILE);
  if (!$roundMeta) { $roundMeta = (object)[]; }
  echo json_encode(["items"=>$items, "round_meta"=>$roundMeta, "rounds"=>$CFG["rounds"]], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'save') {
  $jsonHeader();
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
    $result = data_store_write($DATA_FILE, $items, $roundMeta);
    backup_now($CFG, $DATA_FILE);
    $payload = ['ok' => true];
    if (is_array($result)) {
      if (isset($result['items'])) { $payload['items'] = $result['items']; }
      if (isset($result['round_meta'])) { $payload['round_meta'] = $result['round_meta']; }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  } catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'version_conflict:') === 0 || strpos($msg, 'round_version_conflict:') === 0) {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'version_conflict'], JSON_UNESCAPED_UNICODE);
    } else {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE);
    }
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if ($action === 'geocode') {
  $jsonHeader();
  $q = trim($_GET['q'] ?? '');
  if ($q === '') { http_response_code(400); echo json_encode(['error'=>'empty']); exit; }
  $qNorm = preg_replace('/^\s*([^,]+)\s*,\s*(.+?)\s*,\s*(\d{4})\s*$/u', '$3 $1, $2', $q);
  if (!$qNorm) $qNorm = $q;

  $params = http_build_query([
    'q'=>$qNorm,'format'=>'jsonv2','limit'=>1,'addressdetails'=>1,
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
  echo json_encode(['lat'=>(float)$best['lat'], 'lon'=>(float)$best['lon'], 'city'=>$city, 'normalized'=>$qNorm], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'export') {
  $roundFilter = isset($_GET['round']) ? (int)$_GET['round'] : null;

  [$items] = data_store_read($DATA_FILE);
  $items = array_values(array_filter(is_array($items) ? $items : []));

  $autoSort = (bool)($CFG['app']['auto_sort_by_round'] ?? true);
  $zeroBottom = (bool)($CFG['app']['round_zero_at_bottom'] ?? true);
  if ($autoSort) {
    usort($items, function($a, $b) use ($zeroBottom) {
      $ra = (int)($a['round'] ?? 0);
      $rb = (int)($b['round'] ?? 0);
      if ($zeroBottom) {
        $az = $ra === 0 ? 1 : 0;
        $bz = $rb === 0 ? 1 : 0;
        if ($az !== $bz) return $az - $bz;
      }
      if ($ra === $rb) return 0;
      return $ra <=> $rb;
    });
  }

  $itemsCfg = $CFG['items'] ?? [];
  $fieldsCfg = array_values(array_filter($itemsCfg['fields'] ?? [], function($f){ return ($f['enabled'] ?? true) !== false; }));
  $metricsCfg = array_values(array_filter($itemsCfg['metrics'] ?? [], function($m){ return ($m['enabled'] ?? true) !== false; }));
  $fieldIds = array_values(array_filter(array_map(function($f){ return $f['id'] ?? null; }, $fieldsCfg)));
  $metricIds = array_values(array_filter(array_map(function($m){ return $m['id'] ?? null; }, $metricsCfg)));

  $columns = [];
  $addColumn = function($id) use (&$columns){
    $key = (string)$id;
    if ($key === '') return;
    if (!in_array($key, $columns, true)) {
      $columns[] = $key;
    }
  };
  $addColumn('id');
  $addColumn('round');
  foreach ($fieldIds as $fid) { $addColumn($fid); }
  foreach ($metricIds as $mid) { $addColumn($mid); }
  foreach (['city','lat','lon','collapsed'] as $extra) { $addColumn($extra); }

  foreach ($items as $it) {
    if (!is_array($it)) continue;
    if ($roundFilter !== null && (int)($it['round'] ?? 0) !== $roundFilter) {
      continue;
    }
    foreach ($it as $key => $value) {
      if ($key === null) continue;
      $keyStr = (string)$key;
      if ($keyStr === '' || strpos($keyStr, '_') === 0) continue;
      $addColumn($keyStr);
    }
  }
  if (empty($columns)) {
    $columns = ['id','round'];
  }

  $delimiter = ';';
  $fh = fopen('php://temp', 'r+');
  if (!$fh) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Export hiba';
    exit;
  }

  fputcsv($fh, $columns, $delimiter);
  foreach ($items as $it) {
    if (!is_array($it)) continue;
    if ($roundFilter !== null && (int)($it['round'] ?? 0) !== $roundFilter) {
      continue;
    }
    $row = [];
    foreach ($columns as $col) {
      if ($col === null || $col === '') { $row[] = ''; continue; }
      if (!array_key_exists($col, $it) || $it[$col] === null) {
        $row[] = '';
        continue;
      }
      $value = $it[$col];
      if ($col === 'round') {
        $row[] = (string)((int)$value);
      } elseif ($col === 'collapsed') {
        $row[] = (!empty($value) && $value !== '0' && $value !== 0 && $value !== 'false') ? '1' : '0';
      } elseif ($col === 'lat' || $col === 'lon' || in_array($col, $metricIds, true)) {
        if ($value === '') {
          $row[] = '';
        } elseif (is_numeric($value)) {
          $row[] = rtrim(rtrim(number_format((float)$value, 8, '.', ''), '0'), '.');
        } else {
          $row[] = (string)$value;
        }
      } else {
        $row[] = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
      }
    }
    fputcsv($fh, $row, $delimiter);
  }

  rewind($fh);
  $csvBody = stream_get_contents($fh);
  fclose($fh);
  if ($csvBody === false) { $csvBody = ''; }
  $csv = "\xEF\xBB\xBF" . $csvBody;
  @file_put_contents($EXPORT_FILE, $csv);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $EXPORT_NAME .'"');
  echo $csv; exit;
}

if ($action === 'import_csv') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sendJsonError('Hibás HTTP metódus.', 405);
  }
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
  foreach ($header as $idx => $col) {
    $name = trim((string)$col);
    $headers[$idx] = $name !== '' ? $name : null;
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

    $idRaw = isset($assoc['id']) ? trim((string)$assoc['id']) : '';
    unset($assoc['id']);
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

    $item = ['id' => $id, 'round' => $round];

    if (array_key_exists('collapsed', $assoc)) {
      $collapsedRaw = strtolower(trim((string)$assoc['collapsed']));
      $item['collapsed'] = !in_array($collapsedRaw, ['0','false','no','nem',''], true);
      unset($assoc['collapsed']);
    } else {
      $item['collapsed'] = true;
    }

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
      $item[$keyStr] = is_scalar($value) ? trim((string)$value) : $value;
    }

    $items[] = $item;
  }
  fclose($fh);

  $roundMeta = $existingRoundMeta;
  if (!is_array($roundMeta)) { $roundMeta = []; }

  $finalItems = $importMode === 'append' ? array_merge($existingItems, $items) : $items;
  try {
    $result = data_store_write($DATA_FILE, $finalItems, $roundMeta);
    backup_now($CFG, $DATA_FILE);
  } catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'version_conflict:') === 0 || strpos($msg, 'round_version_conflict:') === 0) {
      $jsonHeader();
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'version_conflict'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    throw $e;
  }

  $jsonHeader();
  $importedIds = [];
  foreach ($items as $item) {
    if (!is_array($item)) { continue; }
    $iid = isset($item['id']) ? trim((string)$item['id']) : '';
    if ($iid !== '') { $importedIds[] = $iid; }
  }
  $response = [
    'ok' => true,
    'items' => isset($result['items']) ? $result['items'] : $finalItems,
    'round_meta' => isset($result['round_meta']) ? $result['round_meta'] : $roundMeta,
    'imported_ids' => $importedIds,
    'mode' => $importMode
  ];
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'delete_round') {
  $jsonHeader();
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
    $lines = [$headerLine];
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
      $lines[] = "- " . (count($parts)? implode(' | ', $parts) : '—');
    }
    $lines[] = "";
    @file_put_contents($ARCHIVE_FILE, implode(PHP_EOL,$lines).PHP_EOL, FILE_APPEND|LOCK_EX);
  }
  if (isset($roundMeta[(string)$rid])) {
    unset($roundMeta[(string)$rid]);
  }
  try {
    data_store_write($DATA_FILE, $kept, $roundMeta);
    backup_now($CFG, $DATA_FILE);
  } catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'version_conflict:') === 0 || strpos($msg, 'round_version_conflict:') === 0) {
      http_response_code(409);
      echo json_encode(['ok'=>false,'error'=>'version_conflict']);
      exit;
    }
    throw $e;
  }
  echo json_encode(['ok'=>true,'deleted'=>count($removed)]);
  exit;
}

if ($action === 'download_archive') {
  stream_file_download($ARCHIVE_FILE, 'fuvar_archive.txt', 'text/plain; charset=utf-8');
}

http_response_code(404);
echo 'Unknown action';
