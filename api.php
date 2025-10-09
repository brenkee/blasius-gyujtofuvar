<?php
require __DIR__ . '/common.php';

header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? null;
if (!$action) { http_response_code(400); echo 'Missing action'; exit; }

$jsonHeader = function(){ header('Content-Type: application/json; charset=utf-8'); };

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
  $ok = data_store_write($DATA_FILE, $items, $roundMeta);
  if ($ok !== false) backup_now($CFG, $DATA_FILE);
  echo json_encode(['ok' => $ok !== false]);
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

  [$items, $roundMeta] = data_store_read($DATA_FILE);
  if (!is_array($roundMeta)) { $roundMeta = []; }
  $auto = (bool)($CFG['app']['auto_sort_by_round'] ?? true);
  $zeroBottom = (bool)($CFG['app']['round_zero_at_bottom'] ?? true);
  if ($auto) {
    $items = array_values($items);
    usort($items, function($a,$b) use($zeroBottom){
      $ra = (int)($a['round'] ?? 0); $rb = (int)($b['round'] ?? 0);
      if ($zeroBottom) { $az = $ra===0 ? 1 : 0; $bz = $rb===0 ? 1 : 0; if ($az !== $bz) return $az - $bz; }
      if ($ra !== $rb) return $ra - $rb;
      return 0;
    });
  }
  if ($roundFilter !== null) {
    $items = array_values(array_filter($items, fn($x)=> (int)($x['round']??0) === $roundFilter));
  }

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

  // csoportosítás + összesítések
  $by = [];
  foreach ($items as $idx => $it) { $r=(int)($it['round']??0); $by[$r][] = ['n'=>$idx+1] + $it; }
  ksort($by);
  $tpl = (string)($CFG['export']['group_header_template'] ?? "=== Kör {id} – {label} ===");
  $lines = [];
  $plannedLabel = $CFG['text']['round']['planned_date_label'] ?? 'Tervezett dátum';
  foreach ($by as $rid => $arr) {
    $label = $ROUND_MAP[$rid]['label'] ?? (string)$rid;
    $totalsParts = [];
    foreach ($metricsCfg as $metric){
      $id = $metric['id'] ?? null; if (!$id) continue;
      $sum = 0.0;
      foreach ($arr as $t){ if (isset($t[$id]) && is_numeric($t[$id])) $sum += (float)$t[$id]; }
      $totalsParts[] = $formatMetric($metric, $sum, 'group');
    }
    $hdrBase = str_replace(['{id}','{label}'], [$rid,$label], $tpl);
    $sumText = $totalsParts ? str_replace('{parts}', implode($sumSeparator, $totalsParts), $sumTemplate) : '';
    $metaPieces = [$hdrBase];
    $plannedKey = (string)$rid;
    if (isset($roundMeta[$plannedKey]['planned_date'])) {
      $plannedValue = trim((string)$roundMeta[$plannedKey]['planned_date']);
      if ($plannedValue !== '') {
        $metaPieces[] = $plannedLabel . ': ' . $plannedValue;
      }
    }
    if ($sumText !== '') {
      $metaPieces[] = $sumText;
    }
    $lines[] = implode('  | ', $metaPieces);
    foreach ($arr as $t){
      $parts = [];
      if (!empty($CFG['export']['include_label'])   && trim((string)($t[$labelFieldId] ?? ''))!=='') $parts[] = trim((string)$t[$labelFieldId]);
      if (!empty($CFG['export']['include_address']) && trim((string)($t[$addressFieldId] ?? ''))!=='') $parts[] = trim((string)$t[$addressFieldId]);
      foreach ($metricsCfg as $metric){
        $id = $metric['id'] ?? null; if (!$id) continue;
        if (isset($t[$id]) && $t[$id] !== '') $parts[] = $formatMetric($metric, $t[$id], 'row');
      }
      if (!empty($CFG['export']['include_note'])    && trim((string)($t[$noteFieldId] ?? ''))!=='') $parts[] = trim((string)$t[$noteFieldId]);
      $lines[] = sprintf('%02d. %s', $t['n'], count($parts)? implode(' | ', $parts) : '—');
    }
    $lines[] = '';
  }
  $txt = implode(PHP_EOL, $lines);
  @file_put_contents($EXPORT_FILE, $txt);
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="'. $EXPORT_NAME .'"');
  echo $txt; exit;
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
  data_store_write($DATA_FILE, $kept, $roundMeta);
  backup_now($CFG, $DATA_FILE);
  echo json_encode(['ok'=>true,'deleted'=>count($removed)]);
  exit;
}

if ($action === 'download_archive') {
  stream_file_download($ARCHIVE_FILE, 'fuvar_archive.txt', 'text/plain; charset=utf-8');
}

http_response_code(404);
echo 'Unknown action';
