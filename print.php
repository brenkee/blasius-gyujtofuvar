<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();

$initError = $DATA_INIT_ERROR ?? null;
if (!empty($initError)) {
  header('Content-Type: text/plain; charset=utf-8');
  http_response_code(503);
  echo "Adatbázis inicializációs hiba: " . $initError . "\n";
  echo "Futtasd a 'php scripts/init-db.php' parancsot a létrehozáshoz.";
  exit;
}

$roundFilter = isset($_GET['round']) ? (int)$_GET['round'] : null;

$itemsCfg = $CFG['items'] ?? [];
$fieldDefs = array_values(array_filter($itemsCfg['fields'] ?? [], function($f){ return ($f['enabled'] ?? true) !== false; }));
$metricsCfg = array_values(array_filter($itemsCfg['metrics'] ?? [], function($m){ return ($m['enabled'] ?? true) !== false; }));
$labelFieldId = $itemsCfg['label_field_id'] ?? 'label';
$addressFieldId = $itemsCfg['address_field_id'] ?? 'address';
$noteFieldId = $itemsCfg['note_field_id'] ?? 'note';
$groupText = $CFG['text']['group'] ?? [];
$sumTemplate = $groupText['sum_template'] ?? 'Összesen: {parts}';
$sumSeparator = $groupText['sum_separator'] ?? ' · ';

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

function format_metric_value($metric, $value, $context='row') {
  $precision = isset($metric['precision']) ? (int)$metric['precision'] : 0;
  $num = number_format((float)$value, $precision, '.', '');
  $unit = $metric['unit'] ?? '';
  $label = $metric['label'] ?? '';
  $tplKey = $context === 'row' ? 'row_format' : 'group_format';
  if (!empty($metric[$tplKey])) {
    return str_replace(['{value}','{sum}','{unit}','{label}'], [$num,$num,$unit,$label], $metric[$tplKey]);
  }
  return trim($num . ($unit ? ' '.$unit : ''));
}

$deadlineCfg = is_array($itemsCfg['deadline_indicator'] ?? null) ? $itemsCfg['deadline_indicator'] : [];
$deadlineFieldId = is_string($deadlineCfg['field_id'] ?? null) && trim($deadlineCfg['field_id']) !== ''
  ? trim($deadlineCfg['field_id'])
  : 'deadline';
$deadlineFieldLabel = $deadlineFieldId;
$deadlineFieldEnabled = false;
foreach ($fieldDefs as $field) {
  $fid = $field['id'] ?? null;
  if ($fid === $deadlineFieldId) {
    $deadlineFieldEnabled = true;
    if (!empty($field['label'])) {
      $deadlineFieldLabel = $field['label'];
    }
    break;
  }
}
$deadlineFeatureEnabled = $deadlineFieldEnabled && (($deadlineCfg['enabled'] ?? true) !== false);
$deadlineLabelText = $deadlineFeatureEnabled
  ? ($CFG['text']['items']['deadline_label'] ?? $deadlineFieldLabel ?? 'Határidő')
  : '';

$printTitleSuffix = $CFG['print']['title_suffix'] ?? ' – Nyomtatás';
$printListTitle = $CFG['print']['list_title'] ?? 'Szállítási lista';
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($CFG['app']['title'] . $printTitleSuffix) ?></title>
  <style>
    :root {
      color-scheme: light;
      font-family: "Inter", "Segoe UI", "Roboto", sans-serif;
      --hm-text: #10163a;
      --hm-muted: #6c7392;
      --hm-primary: #5468ff;
      --hm-border: #d6dbeb;
      --hm-soft: #eef1fb;
    }
    body {
      margin: 20px;
      color: var(--hm-text);
      background: #fff;
      font-family: inherit;
    }
    h1 {
      margin: 0 0 12px;
      font-size: 22px;
      color: var(--hm-text);
    }
    .meta {
      color: var(--hm-muted);
      margin-bottom: 18px;
    }
    .round {
      margin: 18px 0 12px;
      font-weight: 700;
      border-bottom: 2px solid var(--hm-border);
      padding-bottom: 6px;
      display: flex;
      gap: 12px;
      align-items: baseline;
      flex-wrap: wrap;
      color: var(--hm-primary);
    }
    .planned {
      color: var(--hm-text);
      font-weight: 600;
      font-size: 12px;
      background: var(--hm-soft);
      padding: 2px 8px;
      border-radius: 12px;
    }
    .sum {
      color: var(--hm-muted);
      font-weight: 600;
      font-size: 12px;
    }
    .item {
      padding: 8px 0;
      border-bottom: 1px dashed var(--hm-border);
    }
    .item:last-child {
      border-bottom: none;
    }
    .lbl {
      font-weight: 700;
    }
    .addr {
      color: var(--hm-text);
    }
    .note {
      color: var(--hm-muted);
      font-size: 12px;
      margin-top: 2px;
    }
    .extra {
      color: var(--hm-muted);
      font-size: 12px;
      margin-left: 8px;
    }
    @media print {
      body { margin: 0.8cm; }
      a[href]:after { content: ""; }
    }
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($CFG['app']['title'] . ' – ' . $printListTitle) ?></h1>
  <div class="meta">
    Készült: <?= date('Y-m-d H:i') ?>
    <?php if ($roundFilter!==null): ?>
      &nbsp;|&nbsp; Kör: <?= htmlspecialchars($ROUND_MAP[$roundFilter]['label'] ?? $roundFilter) ?>
    <?php endif; ?>
  </div>
  <?php
    $by = [];
    foreach ($items as $it){ $r=(int)($it['round']??0); $by[$r][]=$it; }
    ksort($by);
    foreach ($by as $rid=>$arr){
      if ($roundFilter!==null && $rid!==$roundFilter) continue;
      $rlabel = $ROUND_MAP[$rid]['label'] ?? (string)$rid;
      $plannedKey = (string)$rid;
      $plannedDateValue = '';
      $plannedTimeValue = '';
      if (isset($roundMeta[$plannedKey]['planned_date'])) {
        $plannedDateValue = trim((string)$roundMeta[$plannedKey]['planned_date']);
      }
      if (isset($roundMeta[$plannedKey]['planned_time'])) {
        $plannedTimeValue = trim((string)$roundMeta[$plannedKey]['planned_time']);
      }

      $totalsParts = [];
      foreach ($metricsCfg as $metric){
        $id = $metric['id'] ?? null; if (!$id) continue;
        $sum = 0.0;
        foreach ($arr as $t){ if (isset($t[$id]) && is_numeric($t[$id])) $sum += (float)$t[$id]; }
        $totalsParts[] = format_metric_value($metric, $sum, 'group');
      }
      $sumText = $totalsParts ? str_replace('{parts}', implode($sumSeparator, $totalsParts), $sumTemplate) : '';
      echo '<div class="round"><div>'.htmlspecialchars($rlabel).'</div>';
      if ($plannedDateValue !== '') {
        $plannedLabel = $CFG['text']['round']['planned_date_label'] ?? 'Tervezett dátum';
        echo '<div class="planned">'.htmlspecialchars($plannedLabel.': '.$plannedDateValue).'</div>';
      }
      if ($plannedTimeValue !== '') {
        $plannedTimeLabel = $CFG['text']['round']['planned_time_label'] ?? 'Tervezett idő';
        echo '<div class="planned">'.htmlspecialchars($plannedTimeLabel.': '.$plannedTimeValue).'</div>';
      }
      if ($sumText !== '') echo '<div class="sum">'.htmlspecialchars($sumText).'</div>';
      echo '</div>';

      $n=0;
      foreach ($arr as $it){
        $n++; $parts=[];
        $labelVal = $it[$labelFieldId] ?? '';
        $addressVal = $it[$addressFieldId] ?? '';
        if (trim((string)$labelVal)!=='') $parts[] = '<span class="lbl">'.htmlspecialchars($labelVal).'</span>';
        if (trim((string)$addressVal)!=='') $parts[] = '<span class="addr">'.htmlspecialchars($addressVal).'</span>';
        $extras=[];
        foreach ($metricsCfg as $metric){
          $id = $metric['id'] ?? null; if (!$id) continue;
          if (isset($it[$id]) && $it[$id] !== '') {
            $extras[] = htmlspecialchars(format_metric_value($metric, $it[$id], 'row'));
          }
        }
        if ($deadlineFeatureEnabled) {
          $deadlineRaw = isset($it[$deadlineFieldId]) ? trim((string)$it[$deadlineFieldId]) : '';
          if ($deadlineRaw !== '') {
            $extras[] = htmlspecialchars($deadlineLabelText . ': ' . $deadlineRaw);
          }
        }

        echo '<div class="item"><span class="num">'.sprintf('%02d. ', $n).'</span>'.implode(' – ', $parts);
        if ($extras) echo '<span class="extra">('.implode(' · ', $extras).')</span>';
        if ($noteFieldId && trim((string)($it[$noteFieldId]??''))!=='') echo '<div class="note">'.htmlspecialchars($it[$noteFieldId]).'</div>';
        echo '</div>';
      }
    }
  ?>
  <script>window.print()</script>
</body>
</html>
