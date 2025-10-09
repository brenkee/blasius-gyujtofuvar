<?php
require __DIR__ . '/common.php';

header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? null;
if (!$action) { http_response_code(400); echo 'Missing action'; exit; }

$jsonHeader = function(){ header('Content-Type: application/json; charset=utf-8'); };

if ($action === 'cfg') {
  $jsonHeader();
  $JS_CFG = [
    "app" => [
      "title" => $CFG['app']['title'],
      "export_button_label" => $CFG['app']['export_button_label'],
      "auto_sort_by_round" => (bool)$CFG['app']['auto_sort_by_round'],
      "round_zero_at_bottom" => (bool)$CFG['app']['round_zero_at_bottom'],
      "default_collapsed" => (bool)$CFG['app']['default_collapsed']
    ],
    "ui" => [
      "panel_min_px" => (int)$CFG['ui']['panel_min_px'],
      "panel_pref_vw" => (int)$CFG['ui']['panel_pref_vw'],
      "panel_max_px" => (int)$CFG['ui']['panel_max_px'],
      "show_note_field" => (bool)$CFG['ui']['show_note_field'],
      "marker" => [
        "icon_size" => (int)$CFG['ui']['marker']['icon_size'],
        "font_size" => (int)$CFG['ui']['marker']['font_size'],
        "auto_contrast" => (bool)$CFG['ui']['marker']['auto_contrast']
      ]
    ],
    "map" => [
      "tiles" => [
        "url" => $CFG['map']['tiles']['url'],
        "attribution" => $CFG['map']['tiles']['attribution']
      ],
      "fit_bounds" => $CFG['map']['fit_bounds'] ?? null,
      "max_bounds_pad" => (float)$CFG['map']['max_bounds_pad']
    ],
    "rounds" => array_values($ROUND_MAP),
    "routing" => [
      "origin" => "Maglód",
      "max_waypoints" => 10
    ]
  ];
  echo json_encode($JS_CFG, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'load') {
  $jsonHeader();
  $items = [];
  if (file_exists($DATA_FILE)) {
    $raw = file_get_contents($DATA_FILE);
    $arr = json_decode($raw ?: '[]', true);
    if (is_array($arr)) $items = $arr;
  }
  echo json_encode(["items"=>$items, "rounds"=>$CFG["rounds"]], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($action === 'save') {
  $jsonHeader();
  $body = file_get_contents('php://input');
  $arr = json_decode($body, true);
  if (!is_array($arr)) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
  $ok = file_put_contents($DATA_FILE, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
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

  $items = [];
  if (file_exists($DATA_FILE)) {
    $raw = file_get_contents($DATA_FILE);
    $arr = json_decode($raw ?: '[]', true);
    if (is_array($arr)) $items = $arr;
  }
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

  // csoportosítás + összesítések
  $by = [];
  foreach ($items as $idx => $it) { $r=(int)($it['round']??0); $by[$r][] = ['n'=>$idx+1] + $it; }
  ksort($by);
  $tpl = (string)($CFG['export']['group_header_template'] ?? "=== Kör {id} – {label} ===");
  $lines = [];
  foreach ($by as $rid => $arr) {
    $label = $ROUND_MAP[$rid]['label'] ?? (string)$rid;
    // totals
    $sumW=0.0; $sumV=0.0;
    foreach ($arr as $t){
      if (isset($t['weight']) && is_numeric($t['weight'])) $sumW += (float)$t['weight'];
      if (isset($t['volume']) && is_numeric($t['volume'])) $sumV += (float)$t['volume'];
    }
    $hdrBase = str_replace(['{id}','{label}'], [$rid,$label], $tpl);
    $lines[] = $hdrBase . "  | Összesen: " . number_format($sumW,1,'.','') . " kg, " . number_format($sumV,1,'.','') . " m3";
    foreach ($arr as $t){
      $parts = [];
      if (!empty($CFG['export']['include_label'])   && trim((string)($t['label'] ?? ''))!=='') $parts[] = trim((string)$t['label']);
      if (!empty($CFG['export']['include_address']) && trim((string)($t['address'] ?? ''))!=='') $parts[] = trim((string)$t['address']);
      if (isset($t['weight']) && $t['weight']!=='') $parts[] = number_format((float)$t['weight'],1,'.','') . " kg";
      if (isset($t['volume']) && $t['volume']!=='') $parts[] = number_format((float)$t['volume'],2,'.','') . " m3";
      if (!empty($CFG['export']['include_note'])    && trim((string)($t['note'] ?? ''))!=='') $parts[] = trim((string)$t['note']);
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

  $items = [];
  if (file_exists($DATA_FILE)) {
    $raw = file_get_contents($DATA_FILE);
    $arr = json_decode($raw ?: '[]', true);
    if (is_array($arr)) $items = $arr;
  }
  $kept = []; $removed = [];
  foreach ($items as $it) {
    if ((int)($it['round'] ?? 0) === $rid) $removed[] = $it; else $kept[] = $it;
  }
  if (count($removed) > 0) {
    $dt = date('Y-m-d H:i:s');
    $roundLabel = $ROUND_MAP[$rid]['label'] ?? (string)$rid;

    $sumW=0.0; $sumV=0.0;
    foreach ($removed as $t){
      if (isset($t['weight']) && is_numeric($t['weight'])) $sumW += (float)$t['weight'];
      if (isset($t['volume']) && is_numeric($t['volume'])) $sumV += (float)$t['volume'];
    }

    $lines = ["[$dt] TÖRÖLT KÖR: $rid – $roundLabel  | Összesen: ".number_format($sumW,1,'.','')." kg, ".number_format($sumV,1,'.','')." m3"];
    foreach ($removed as $t) {
      $parts = [];
      foreach (['label','address','note'] as $k) { $v = trim((string)($t[$k] ?? '')); if ($v!=='') $parts[] = $v; }
      if (isset($t['weight']) && $t['weight']!=='') $parts[] = number_format((float)$t['weight'],1,'.','') . " kg";
      if (isset($t['volume']) && $t['volume']!=='') $parts[] = number_format((float)$t['volume'],2,'.','') . " m3";
      $lines[] = "- " . (count($parts)? implode(' | ', $parts) : '—');
    }
    $lines[] = "";
    @file_put_contents($ARCHIVE_FILE, implode(PHP_EOL,$lines).PHP_EOL, FILE_APPEND|LOCK_EX);
  }
  file_put_contents($DATA_FILE, json_encode($kept, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  backup_now($CFG, $DATA_FILE);
  echo json_encode(['ok'=>true,'deleted'=>count($removed)]);
  exit;
}

if ($action === 'download_archive') {
  stream_file_download($ARCHIVE_FILE, 'fuvar_archive.txt', 'text/plain; charset=utf-8');
}

http_response_code(404);
echo 'Unknown action';
