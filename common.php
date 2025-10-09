<?php
// Közös betöltések: konfiguráció, körök, fájlok, segédfüggvények

$CONFIG_FILE = __DIR__ . '/config.json';
$CFG_DEFAULT = [
  "app" => [
    "title" => "Gyűjtőfuvar – címkezelő",
    "export_button_label" => "Export",
    "auto_sort_by_round" => true,
    "round_zero_at_bottom" => true,
    "default_collapsed" => false
  ],
  "files" => [
    "data_file" => "fuvar_data.json",
    "export_file" => "fuvar_export.txt",
    "export_download_name" => "fuvar_export.txt",
    "archive_file" => "fuvar_archive.log"
  ],
  "map" => [
    "tiles" => [
      "url" => "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
      "attribution" => "&copy; OSM közreműködők"
    ],
    "fit_bounds" => [[45.737,16.113],[48.585,22.897]],
    "max_bounds_pad" => 0.6
  ],
  "geocode" => [
    "countrycodes" => "hu",
    "language" => "hu",
    "user_agent" => "fuvarszervezo-internal/1.5 (+contact@example.com)"
  ],
  "ui" => [
    "panel_min_px" => 330,
    "panel_pref_vw" => 36,
    "panel_max_px" => 520,
    "show_note_field" => true,
    "marker" => [
      "icon_size" => 38,
      "font_size" => 14,
      "auto_contrast" => true
    ]
  ],
  "rounds" => [],
  "export" => [
    "include_label" => true,
    "include_address" => true,
    "include_note" => true,
    "group_header_template" => "=== Kör {id} – {label} ==="
  ],
  "backup" => [
    "enabled" => true,
    "dir" => "backups",
    "keep_latest" => 50,
    "keep_days" => 14,
    "strategy" => "on_every_save"
  ]
];

$CFG = $CFG_DEFAULT;
if (is_file($CONFIG_FILE)) {
  $raw = file_get_contents($CONFIG_FILE);
  $json = json_decode($raw, true);
  if (is_array($json)) $CFG = array_replace_recursive($CFG_DEFAULT, $json);
}
if (empty($CFG['rounds'])) {
  $CFG['rounds'] = [
    ["id"=>0,"label"=>"Alap (0)","color"=>"#9aa0a6"],
    ["id"=>1,"label"=>"1. kör","color"=>"#e11d48"],
    ["id"=>2,"label"=>"2. kör","color"=>"#f59e0b"],
    ["id"=>3,"label"=>"3. kör","color"=>"#16a34a"],
    ["id"=>4,"label"=>"4. kör","color"=>"#06b6d4"],
    ["id"=>5,"label"=>"5. kör","color"=>"#2563eb"],
    ["id"=>6,"label"=>"6. kör","color"=>"#8b5cf6"],
    ["id"=>7,"label"=>"7. kör","color"=>"#db2777"],
    ["id"=>8,"label"=>"8. kör","color"=>"#10b981"],
    ["id"=>9,"label"=>"9. kör","color"=>"#0ea5e9"],
    ["id"=>10,"label"=>"10. kör","color"=>"#7c3aed"]
  ];
}

$ROUND_MAP = []; $ROUND_IDS = [];
foreach ($CFG['rounds'] as $r) {
  $rid = (int)($r['id'] ?? 0);
  $ROUND_MAP[$rid] = [
    "id"=>$rid,
    "label" => (string)($r['label'] ?? (string)$rid),
    "color" => (string)($r['color'] ?? "#374151")
  ];
  $ROUND_IDS[] = $rid;
}
sort($ROUND_IDS);

$DATA_FILE    = __DIR__ . '/' . ($CFG['files']['data_file'] ?? 'fuvar_data.json');
$EXPORT_FILE  = __DIR__ . '/' . ($CFG['files']['export_file'] ?? 'fuvar_export.txt');
$EXPORT_NAME  = (string)($CFG['files']['export_download_name'] ?? 'fuvar_export.txt');
$ARCHIVE_FILE = __DIR__ . '/' . ($CFG['files']['archive_file'] ?? 'fuvar_archive.log');

/**
 * Biztonságos backup: létezés-ellenőrzés és mtime használat védetten.
 * Elkerüli a "filemtime(): stat failed" warningokat versenyhelyzet esetén.
 */
function backup_now($cfg, $dataFile) {
  if (empty($cfg['backup']['enabled'])) return;

  $dir = __DIR__ . '/' . ($cfg['backup']['dir'] ?? 'backups');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir)) return;

  // Ha nincs mit menteni, lépjünk ki
  if (!is_file($dataFile)) return;

  $ts = date('Ymd_His');
  $target = $dir . '/fuvar_data_' . $ts . '.json';
  // Másolás védetten
  @copy($dataFile, $target);

  $keepN = (int)($cfg['backup']['keep_latest'] ?? 50);
  $keepDays = (int)($cfg['backup']['keep_days'] ?? 14);

  $files = glob($dir . '/fuvar_data_*.json');
  if (!$files) return;

  // Biztonságos mtime lekérdezés
  $mtime = function($path){
    if (!is_file($path)) return 0;
    $t = @filemtime($path);
    return $t ? (int)$t : 0;
  };

  // Rendezés mtime szerint (legújabb elöl)
  usort($files, function($a,$b) use($mtime){
    return $mtime($b) <=> $mtime($a);
  });

  // Limit felettiek törlése
  if ($keepN > 0 && count($files) > $keepN) {
    foreach (array_slice($files, $keepN) as $f) {
      if (is_file($f)) @unlink($f);
    }
  }

  // Időalapú törlés
  $now = time();
  foreach ($files as $f) {
    if (!is_file($f)) continue;
    $mt = $mtime($f);
    if ($mt === 0) continue; // ha nem elérhető az mtime, inkább hagyjuk
    $ageDays = ($now - $mt) / 86400;
    if ($ageDays > $keepDays) {
      @unlink($f);
    }
  }
}

function stream_file_download($path, $downloadName, $contentType='text/plain; charset=utf-8') {
  if (!is_file($path)) {
    header('Content-Type: '.$contentType);
    header('Content-Disposition: attachment; filename="'. basename($downloadName) .'"');
    echo ""; exit;
  }
  header('Content-Type: '.$contentType);
  header('Content-Length: '. filesize($path));
  header('Content-Disposition: attachment; filename="'. basename($downloadName) .'"');
  readfile($path); exit;
}
