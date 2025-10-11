<?php
require __DIR__ . '/common.php';

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
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#111;margin:20px}
    h1{margin:0 0 10px;font-size:20px}
    .meta{color:#555;margin-bottom:16px}
    .round{margin:16px 0 10px;font-weight:800;border-bottom:2px solid #eee;padding-bottom:4px;display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}
    .planned{color:#1f2937;font-weight:700;font-size:12px}
    .sum{color:#6b7280;font-weight:700;font-size:12px}
    .item{padding:6px 0;border-bottom:1px dashed #e5e7eb}
    .lbl{font-weight:700}.addr{color:#374151}.note{color:#6b7280;font-size:12px;margin-top:2px}
    .extra{color:#334155;font-size:12px;margin-left:6px}
    @media print{body{margin:0.8cm} a[href]:after{content:""}}
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
