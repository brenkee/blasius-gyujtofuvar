<?php
require __DIR__ . '/common.php';

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

?><!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($CFG['app']['title']) ?> – Nyomtatás</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#111;margin:20px}
    h1{margin:0 0 10px;font-size:20px}
    .meta{color:#555;margin-bottom:16px}
    .round{margin:16px 0 10px;font-weight:800;border-bottom:2px solid #eee;padding-bottom:4px;display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}
    .sum{color:#6b7280;font-weight:700;font-size:12px}
    .item{padding:6px 0;border-bottom:1px dashed #e5e7eb}
    .lbl{font-weight:700}.addr{color:#374151}.note{color:#6b7280;font-size:12px;margin-top:2px}
    .extra{color:#334155;font-size:12px;margin-left:6px}
    @media print{body{margin:0.8cm} a[href]:after{content:""}}
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($CFG['app']['title']) ?> – Szállítási lista</h1>
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

      // totals
      $sumW=0.0; $sumV=0.0;
      foreach ($arr as $t){
        if (isset($t['weight']) && is_numeric($t['weight'])) $sumW += (float)$t['weight'];
        if (isset($t['volume']) && is_numeric($t['volume'])) $sumV += (float)$t['volume'];
      }
      echo '<div class="round"><div>'.htmlspecialchars($rlabel).'</div><div class="sum">Összesen: '.number_format($sumW,1,'.','').' kg · '.number_format($sumV,1,'.','').' m³</div></div>';

      $n=0;
      foreach ($arr as $it){
        $n++; $parts=[];
        if (trim((string)($it['label']??''))!=='') $parts[] = '<span class="lbl">'.htmlspecialchars($it['label']).'</span>';
        if (trim((string)($it['address']??''))!=='') $parts[] = '<span class="addr">'.htmlspecialchars($it['address']).'</span>';
        $extras=[];
        if (isset($it['weight']) && $it['weight']!=='') $extras[] = number_format((float)$it['weight'],1,'.','').' kg';
        if (isset($it['volume']) && $it['volume']!=='') $extras[] = number_format((float)$it['volume'],2,'.','').' m³';

        echo '<div class="item"><span class="num">'.sprintf('%02d. ', $n).'</span>'.implode(' – ', $parts);
        if ($extras) echo '<span class="extra">('.implode(' · ', $extras).')</span>';
        if (trim((string)($it['note']??''))!=='') echo '<div class="note">'.htmlspecialchars($it['note']).'</div>';
        echo '</div>';
      }
    }
  ?>
  <script>window.print()</script>
</body>
</html>
