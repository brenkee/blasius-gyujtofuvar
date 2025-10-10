<?php
// Közös betöltések: konfiguráció, körök, fájlok, segédfüggvények

$CONFIG_FILE = __DIR__ . '/config.json';
$CFG_DEFAULT = [
  "app" => [
    "title" => "Gyűjtőfuvar – címkezelő",
    "auto_sort_by_round" => true,
    "round_zero_at_bottom" => true,
    "default_collapsed" => false
  ],
  "history" => [
    "undo_enabled" => true,
    "max_steps" => 3
  ],
  "features" => [
    "toolbar" => [
      "expand_all" => true,
      "collapse_all" => true,
      "import_all" => true,
      "export_all" => true,
      "print_all" => true,
      "download_archive" => true,
      "theme_toggle" => true,
      "undo" => true
    ],
    "quick_search" => true,
    "marker_popup_on_click" => true,
    "marker_popup_on_focus" => true,
    "marker_focus_feedback" => true,
    "group_actions" => [
      "open" => true,
      "close" => true,
      "print" => true,
      "export" => true,
      "navigate" => true,
      "delete" => true
    ],
    "group_totals" => true,
    "round_planned_date" => true
  ],
  "files" => [
    "data_file" => "data/data.json",
    "export_file" => "fuvar_export.csv",
    "export_download_name" => "fuvar_export.csv",
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
    "colors" => [
      "light" => [
        "bg" => "#fafafa",
        "panel" => "#ffffff",
        "border" => "#e5e7eb",
        "text" => "#111827",
        "muted" => "#6b7280",
        "accent" => "#2563eb",
        "ok" => "#16a34a",
        "err" => "#dc2626",
        "highlight" => "#e0f2fe"
      ],
      "dark" => [
        "bg" => "#0f172a",
        "panel" => "#0b1220",
        "border" => "#1f2937",
        "text" => "#e5e7eb",
        "muted" => "#94a3b8",
        "accent" => "#60a5fa",
        "ok" => "#34d399",
        "err" => "#f87171",
        "highlight" => "#1e293b"
      ]
    ],
    "marker" => [
      "icon_size" => 38,
      "font_size" => 14,
      "auto_contrast" => true,
      "focus_ring_radius" => 80,
      "focus_ring_color" => "auto"
    ],
    "panel" => [
      "sticky_top" => [
        "enabled" => false
      ]
    ],
    "save_status" => [
      "position" => [
        "top" => '10px',
        "right" => '12px'
      ],
      "style" => [
        "padding" => '6px 10px',
        "borderRadius" => '999px',
        "fontSize" => '12px',
        "fontWeight" => '600',
        "boxShadow" => '0 2px 6px rgba(0,0,0,.15)',
        "zIndex" => 9999,
        "transition" => 'opacity .25s ease',
        "pointerEvents" => 'none'
      ],
      "colors" => [
        "success" => [
          "color" => '#065f46',
          "background" => '#d1fae5',
          "border" => '1px solid #a7f3d0'
        ],
        "error" => [
          "color" => '#7f1d1d',
          "background" => '#fee2e2',
          "border" => '1px solid #fecaca'
        ]
      ],
      "hide_after_ms" => 1600
    ]
  ],
  "routing" => [
    "origin" => "Maglód",
    "origin_coordinates" => [
      "lat" => 47.45,
      "lon" => 19.35
    ],
    "max_waypoints" => 10,
    "geocode_origin_on_start" => true
  ],
  "text" => [
    "toolbar" => [
      "expand_all" => ["label" => "Összes kinyit", "title" => "Összes kör kinyitása"],
      "collapse_all" => ["label" => "Összes összezár", "title" => "Összes kör összezárása"],
      "import_all" => ["label" => "Import (CSV)", "title" => "Címlista importálása CSV-ből"],
      "export_all" => ["label" => "Export (CSV)", "title" => "Címlista exportálása CSV-be"],
      "print_all" => ["label" => "Nyomtatás", "title" => "Nyomtatás"],
      "download_archive" => ["label" => "Archívum letöltése", "title" => "Archívum letöltése (TXT)"],
      "theme_toggle" => ["label" => "🌙 / ☀️", "title" => "Téma váltása"],
      "undo" => ["label" => "Visszavonás", "title" => "Visszavonás"]
    ],
    "badges" => [
      "pin_counter_label" => "Pin-ek:",
      "pin_counter_title" => "Aktív pin jelölők száma"
    ],
    "round" => [
      "planned_date_label" => "Tervezett dátum",
      "planned_date_hint" => "Válaszd ki a kör tervezett dátumát"
    ],
    "group" => [
      "sum_template" => "Összesen: {parts}",
      "sum_separator" => " · ",
      "actions" => [
        "open" => "Kinyit",
        "close" => "Összezár",
        "print" => "Nyomtatás (kör)",
        "export" => "Export (kör)",
        "navigate" => "Navigáció (GMaps)",
        "delete" => "Kör törlése"
      ]
    ],
    "quick_search" => [
      "placeholder" => "Keresés: címke, város, cím…",
      "clear_label" => "✕",
      "clear_title" => "Szűrés törlése"
    ],
    "actions" => [
      "ok" => "OK",
      "delete" => "Törlés",
      "delete_disabled_hint" => "Nem törölhető az alap sor"
    ],
    "items" => [
      "label_missing" => "Címke nélkül",
      "deadline_label" => "Határidő",
      "deadline_missing" => "Nincs határidő",
      "deadline_relative_future" => "hátra: {days} nap",
      "deadline_relative_today" => "ma esedékes",
      "deadline_relative_past" => "lejárt: {days} napja"
    ],
      "messages" => [
        "address_required" => "Adj meg teljes címet!",
        "load_error" => "Betöltési hiba: kérlek frissítsd az oldalt.",
        "delete_round_confirm" => "Biztosan törlöd a(z) \"{name}\" kör összes címét?",
        "delete_round_success" => "Kör törölve. Tételek: {count}.",
        "delete_round_error" => "A kör törlése nem sikerült.",
        "navigation_empty" => "Nincs navigálható cím ebben a körben.",
        "navigation_skip" => "Figyelem: {count} cím nem került bele (nincs geolokáció).",
        "geocode_failed" => "Geokódolás sikertelen.",
        "geocode_failed_detailed" => "Geokódolás sikertelen. Próbáld pontosítani a címet.",
        "undo_unavailable" => "Nincs visszavonható művelet.",
        "import_success" => "Import kész.",
        "import_error" => "Az importálás nem sikerült.",
        "import_mode_prompt" => "Felülírjuk a jelenlegi adatokat az importált CSV-vel, vagy hozzáadjuk az új sorokat?",
        "import_mode_replace" => "Felülírás",
        "import_mode_append" => "Hozzáadás",
        "import_mode_confirm_replace" => "Biztosan felülírjuk a jelenlegi adatokat a CSV tartalmával?",
        "import_mode_confirm_append" => "Biztosan hozzáadjuk az új sorokat a meglévő listához?",
        "import_geocode_partial" => "Figyelem: {count} címet nem sikerült automatikusan térképre tenni.",
        "import_geocode_partial_detail" => "Nem sikerült geokódolni:\n{list}"
      ]
    ],
  "items" => [
    "address_field_id" => "address",
    "label_field_id" => "label",
    "note_field_id" => "note",
    "fields" => [
      [
        "id" => "label",
        "type" => "text",
        "label" => "Címke",
        "placeholder" => "pl. Ügyfél neve / kód",
        "default" => ""
      ],
      [
        "id" => "address",
        "type" => "text",
        "label" => "Teljes cím",
        "placeholder" => "pl. 2234 Maglód, Fő utca 1.",
        "default" => "",
        "required" => true
      ],
      [
        "id" => "note",
        "type" => "text",
        "label" => "Megjegyzés",
        "placeholder" => "időablak, kapucsengő, stb.",
        "default" => ""
      ],
      [
        "id" => "deadline",
        "type" => "date",
        "label" => "Kiszállítás határideje",
        "placeholder" => "",
        "default" => ""
      ]
    ],
    "metrics" => [
      [
        "id" => "weight",
        "type" => "number",
        "label" => "Súly (kg)",
        "placeholder" => "pl. 12.5",
        "step" => 0.1,
        "min" => 0,
        "precision" => 1,
        "unit" => "kg",
        "row_format" => "{value} kg",
        "group_format" => "{sum} kg"
      ],
      [
        "id" => "volume",
        "type" => "number",
        "label" => "Térfogat (m³)",
        "placeholder" => "pl. 0.80",
        "step" => 0.01,
        "min" => 0,
        "precision" => 2,
        "unit" => "m³",
        "row_format" => "{value} m³",
        "group_format" => "{sum} m³"
      ]
    ],
    "round_field" => [
      "label" => "Kör",
      "placeholder" => ""
    ],
    "meta_display" => [
      "separator" => " · ",
      "missing_warning" => [
        "enabled" => true,
        "text" => "!",
        "title" => "Hiányzó súly és térfogat",
        "class" => "warn"
      ]
    ],
    "deadline_indicator" => [
      "enabled" => true,
      "field_id" => "deadline",
      "icon_size" => 16,
      "steps" => [
        ["min_days" => 7, "color" => "#16a34a"],
        ["min_days" => 3, "color" => "#f97316"],
        ["color" => "#dc2626"]
      ]
    ]
  ],
  "rounds" => [],
  "export" => [
    "include_label" => true,
    "include_address" => true,
    "include_note" => true,
    "group_header_template" => "=== Kör {id} – {label} ==="
  ],
  "print" => [
    "title_suffix" => " – Nyomtatás",
    "list_title" => "Szállítási lista"
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

$DATA_DIR     = __DIR__ . '/data';
$DB_FILE      = $DATA_DIR . '/app.db';
$DATA_FILE    = __DIR__ . '/' . ($CFG['files']['data_file'] ?? 'data/data.json');
$EXPORT_FILE  = __DIR__ . '/' . ($CFG['files']['export_file'] ?? 'fuvar_export.txt');
$EXPORT_NAME  = (string)($CFG['files']['export_download_name'] ?? 'fuvar_export.txt');
$ARCHIVE_FILE = __DIR__ . '/' . ($CFG['files']['archive_file'] ?? 'fuvar_archive.log');

if (!is_dir($DATA_DIR)) {
  @mkdir($DATA_DIR, 0775, true);
}

function db_path() {
  global $DB_FILE;
  return $DB_FILE;
}

function db() {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $dbFile = db_path();
  $dir = dirname($dbFile);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  $pdo = new PDO('sqlite:' . $dbFile);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec('PRAGMA journal_mode = WAL');
  $pdo->exec('PRAGMA foreign_keys = ON');
  $pdo->exec('PRAGMA busy_timeout = 5000');

  initialize_database_schema($pdo);

  return $pdo;
}

function initialize_database_schema(PDO $pdo) {
  $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stops' LIMIT 1")->fetchColumn();
  if ($exists) {
    return;
  }

  $schemaFile = __DIR__ . '/schema.sql';
  if (!is_file($schemaFile)) {
    throw new RuntimeException('schema.sql hiányzik, az adatbázis nem inicializálható.');
  }

  $sql = file_get_contents($schemaFile);
  if ($sql === false) {
    throw new RuntimeException('schema.sql nem olvasható.');
  }

  $pdo->exec($sql);
}

function now_iso() {
  return gmdate('Y-m-d\TH:i:s\Z');
}

function stop_base_keys() {
  return [
    'id','round','collapsed','city','lat','lon','label','address','note','deadline','weight','volume','version','updated_at','created_at','deleted_at','position'
  ];
}

function hydrate_stop_row(array $row) {
  $item = [
    'id' => (string)$row['id'],
    'round' => (int)$row['round_id'],
    'collapsed' => (bool)$row['collapsed'],
    'city' => (string)($row['city'] ?? ''),
    'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
    'lon' => $row['lon'] !== null ? (float)$row['lon'] : null,
    'label' => (string)($row['label'] ?? ''),
    'address' => (string)($row['address'] ?? ''),
    'note' => (string)($row['note'] ?? ''),
    'deadline' => (string)($row['deadline'] ?? ''),
    'weight' => $row['weight'] !== null ? (float)$row['weight'] : null,
    'volume' => $row['volume'] !== null ? (float)$row['volume'] : null,
    'version' => (int)$row['version'],
    'updated_at' => (string)($row['updated_at'] ?? ''),
  ];

  $extra = [];
  if (isset($row['extra_json']) && $row['extra_json'] !== null && $row['extra_json'] !== '') {
    $decoded = json_decode($row['extra_json'], true);
    if (is_array($decoded)) {
      $extra = $decoded;
    }
  }
  foreach ($extra as $key => $value) {
    if (!array_key_exists($key, $item)) {
      $item[$key] = $value;
    }
  }

  return $item;
}

function fetch_all_stops($includeDeleted = false) {
  $pdo = db();
  $where = $includeDeleted ? '1=1' : 'deleted_at IS NULL';
  $stmt = $pdo->query("SELECT id, round_id, position, collapsed, city, lat, lon, label, address, note, deadline, weight, volume, extra_json, version, updated_at FROM stops WHERE {$where} ORDER BY position ASC, created_at ASC, id ASC");
  $items = [];
  while ($row = $stmt->fetch()) {
    $items[] = hydrate_stop_row($row);
  }
  return $items;
}

function fetch_round_meta($includeDeleted = false) {
  $pdo = db();
  $where = $includeDeleted ? '1=1' : 'deleted_at IS NULL';
  $stmt = $pdo->query("SELECT id, planned_date, meta_json, version, updated_at FROM rounds WHERE {$where}");
  $meta = [];
  while ($row = $stmt->fetch()) {
    $entry = [];
    $planned = isset($row['planned_date']) ? trim((string)$row['planned_date']) : '';
    if ($planned !== '') {
      $entry['planned_date'] = $planned;
    }
    if (isset($row['meta_json']) && $row['meta_json'] !== '') {
      $decoded = json_decode($row['meta_json'], true);
      if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
          if (!array_key_exists($k, $entry)) {
            $entry[$k] = $v;
          }
        }
      }
    }
    $entry['version'] = (int)$row['version'];
    $entry['updated_at'] = (string)($row['updated_at'] ?? '');
    $meta[(string)$row['id']] = $entry;
  }
  return $meta;
}

function normalize_stop_payload(array $item) {
  $baseKeys = stop_base_keys();
  $sanitized = [];
  $sanitized['id'] = isset($item['id']) ? trim((string)$item['id']) : '';
  if ($sanitized['id'] === '') {
    throw new InvalidArgumentException('Hiányzó azonosító egy címnél.');
  }
  $sanitized['round'] = (int)($item['round'] ?? 0);
  $sanitized['position'] = isset($item['position']) ? (int)$item['position'] : 0;
  $sanitized['collapsed'] = !empty($item['collapsed']) ? 1 : 0;
  $sanitized['city'] = isset($item['city']) ? trim((string)$item['city']) : '';
  $sanitized['lat'] = isset($item['lat']) && $item['lat'] !== '' && $item['lat'] !== null && is_numeric($item['lat']) ? (float)$item['lat'] : null;
  $sanitized['lon'] = isset($item['lon']) && $item['lon'] !== '' && $item['lon'] !== null && is_numeric($item['lon']) ? (float)$item['lon'] : null;
  $sanitized['label'] = isset($item['label']) ? (string)$item['label'] : '';
  $sanitized['address'] = isset($item['address']) ? (string)$item['address'] : '';
  $sanitized['note'] = isset($item['note']) ? (string)$item['note'] : '';
  $sanitized['deadline'] = isset($item['deadline']) ? trim((string)$item['deadline']) : '';
  $sanitized['weight'] = isset($item['weight']) && $item['weight'] !== '' && $item['weight'] !== null && is_numeric($item['weight']) ? (float)$item['weight'] : null;
  $sanitized['volume'] = isset($item['volume']) && $item['volume'] !== '' && $item['volume'] !== null && is_numeric($item['volume']) ? (float)$item['volume'] : null;
  $sanitized['version'] = isset($item['version']) ? (int)$item['version'] : 0;
  $sanitized['updated_at'] = isset($item['updated_at']) ? (string)$item['updated_at'] : '';

  $extra = [];
  foreach ($item as $key => $value) {
    if (!in_array($key, $baseKeys, true)) {
      $extra[$key] = $value;
    }
  }
  $sanitized['extra_json'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

  return $sanitized;
}

function normalize_round_meta_entry($rid, $entry) {
  $id = (int)$rid;
  $planned = '';
  $meta = [];
  if (is_array($entry)) {
    if (isset($entry['planned_date'])) {
      $planned = trim((string)$entry['planned_date']);
      if (strlen($planned) > 120) {
        $planned = substr($planned, 0, 120);
      }
    }
    $tmp = $entry;
  } elseif (is_object($entry)) {
    $tmp = (array)$entry;
    if (isset($tmp['planned_date'])) {
      $planned = trim((string)$tmp['planned_date']);
      if (strlen($planned) > 120) {
        $planned = substr($planned, 0, 120);
      }
    }
  } else {
    $tmp = [];
  }
  foreach ($tmp as $k => $v) {
    if ($k === 'planned_date' || $k === 'version' || $k === 'updated_at') continue;
    $meta[$k] = $v;
  }
  return [
    'id' => $id,
    'planned_date' => $planned,
    'meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    'version' => isset($tmp['version']) ? (int)$tmp['version'] : 0,
    'updated_at' => isset($tmp['updated_at']) ? (string)$tmp['updated_at'] : ''
  ];
}

function dataset_state_version() {
  $pdo = db();
  $stmt = $pdo->query("SELECT value FROM metadata WHERE key='dataset_version' LIMIT 1");
  $val = $stmt->fetchColumn();
  return $val ? (int)$val : 0;
}

function bump_dataset_state_version(PDO $pdo) {
  $nowVersion = dataset_state_version();
  $newVersion = $nowVersion + 1;
  $stmt = $pdo->prepare('INSERT INTO metadata(key, value) VALUES("dataset_version", :val)
    ON CONFLICT(key) DO UPDATE SET value=excluded.value');
  $stmt->execute([':val' => (string)$newVersion]);
  return $newVersion;
}


/**
 * Biztonságos backup: létezés-ellenőrzés és mtime használat védetten.
 * Elkerüli a "filemtime(): stat failed" warningokat versenyhelyzet esetén.
 */
function export_dataset_snapshot($path) {
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  [$items, $roundMeta] = data_store_read($path);
  $payload = [
    'items' => $items,
    'round_meta' => !empty($roundMeta) ? $roundMeta : (object)[]
  ];
  file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function backup_now($cfg, $dataFile) {
  if (empty($cfg['backup']['enabled'])) return;

  $dir = __DIR__ . '/' . ($cfg['backup']['dir'] ?? 'backups');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir)) return;

  export_dataset_snapshot($dataFile);

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

function is_list_array($value) {
  if (!is_array($value)) return false;
  if (function_exists('array_is_list')) {
    return array_is_list($value);
  }
  $expected = 0;
  foreach ($value as $key => $_) {
    if ($key !== $expected) return false;
    $expected++;
  }
  return true;
}

function normalize_round_meta($roundMeta) {
  $out = [];
  if (!is_array($roundMeta)) return $out;
  foreach ($roundMeta as $rid => $meta) {
    if (!is_array($meta)) continue;
    $key = (string)$rid;
    $entry = [];
    if (array_key_exists('planned_date', $meta)) {
      $val = trim((string)$meta['planned_date']);
      if ($val !== '') {
        if (function_exists('mb_substr')) {
          $val = mb_substr($val, 0, 120);
        } else {
          $val = substr($val, 0, 120);
        }
        $entry['planned_date'] = $val;
      }
    }
    if (array_key_exists('version', $meta)) {
      $entry['version'] = (int)$meta['version'];
    }
    if (array_key_exists('updated_at', $meta)) {
      $entry['updated_at'] = (string)$meta['updated_at'];
    }
    foreach ($meta as $k => $v) {
      if ($k === 'planned_date' || $k === 'version' || $k === 'updated_at') continue;
      $entry[$k] = $v;
    }
    if (!empty($entry)) {
      $out[$key] = $entry;
    }
  }
  return $out;
}

function save_state_to_database($items, $roundMeta) {
  $pdo = db();
  $pdo->beginTransaction();
  $anyChange = false;

  $existingStops = [];
  $stmtStops = $pdo->query('SELECT id, round_id, position, collapsed, city, lat, lon, label, address, note, deadline, weight, volume, extra_json, version, updated_at, deleted_at FROM stops');
  while ($row = $stmtStops->fetch()) {
    $existingStops[$row['id']] = $row;
  }

  $insertStop = $pdo->prepare('INSERT INTO stops (id, round_id, position, collapsed, city, lat, lon, label, address, note, deadline, weight, volume, extra_json, version, created_at, updated_at, deleted_at)
    VALUES (:id, :round_id, :position, :collapsed, :city, :lat, :lon, :label, :address, :note, :deadline, :weight, :volume, :extra_json, :version, :created_at, :updated_at, NULL)');
  $updateStop = $pdo->prepare('UPDATE stops SET round_id=:round_id, position=:position, collapsed=:collapsed, city=:city, lat=:lat, lon=:lon, label=:label, address=:address, note=:note, deadline=:deadline, weight=:weight, volume=:volume, extra_json=:extra_json, version=:version, updated_at=:updated_at, deleted_at=NULL WHERE id=:id');
  $deleteStop = $pdo->prepare('UPDATE stops SET deleted_at=:deleted_at, updated_at=:updated_at, version=version+1 WHERE id=:id AND deleted_at IS NULL');

  $normalizeRow = function(array $row) {
    return [
      'round' => (int)$row['round_id'],
      'position' => (int)$row['position'],
      'collapsed' => (int)$row['collapsed'] ? 1 : 0,
      'city' => (string)($row['city'] ?? ''),
      'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
      'lon' => $row['lon'] !== null ? (float)$row['lon'] : null,
      'label' => (string)($row['label'] ?? ''),
      'address' => (string)($row['address'] ?? ''),
      'note' => (string)($row['note'] ?? ''),
      'deadline' => (string)($row['deadline'] ?? ''),
      'weight' => $row['weight'] !== null ? (float)$row['weight'] : null,
      'volume' => $row['volume'] !== null ? (float)$row['volume'] : null,
      'extra_json' => isset($row['extra_json']) && $row['extra_json'] !== '' ? $row['extra_json'] : null,
    ];
  };

  $incomingIds = [];
  $now = now_iso();

  $itemsArray = is_array($items) ? array_values($items) : [];
  foreach ($itemsArray as $idx => $rawItem) {
    if (!is_array($rawItem)) {
      continue;
    }
    $rawItem['position'] = $idx;
    $normalized = normalize_stop_payload($rawItem);
    $id = $normalized['id'];
    $incomingIds[$id] = true;

    $expectedVersion = (int)$normalized['version'];
    $existingRow = $existingStops[$id] ?? null;
    $targetComparable = [
      'round' => $normalized['round'],
      'position' => $normalized['position'],
      'collapsed' => $normalized['collapsed'],
      'city' => $normalized['city'],
      'lat' => $normalized['lat'],
      'lon' => $normalized['lon'],
      'label' => $normalized['label'],
      'address' => $normalized['address'],
      'note' => $normalized['note'],
      'deadline' => $normalized['deadline'],
      'weight' => $normalized['weight'],
      'volume' => $normalized['volume'],
      'extra_json' => $normalized['extra_json'],
    ];

    if ($existingRow && $existingRow['deleted_at'] === null) {
      $currentComparable = $normalizeRow($existingRow);
      $currentVersion = (int)$existingRow['version'];
      if ($expectedVersion > 0 && $expectedVersion !== $currentVersion) {
        throw new RuntimeException('version_conflict:'.$id);
      }
      $needsUpdate = $currentComparable !== $targetComparable || (int)$existingRow['position'] !== $normalized['position'];
      if ($needsUpdate) {
        $anyChange = true;
        $updateStop->execute([
          ':round_id' => $normalized['round'],
          ':position' => $normalized['position'],
          ':collapsed' => $normalized['collapsed'],
          ':city' => $normalized['city'],
          ':lat' => $normalized['lat'],
          ':lon' => $normalized['lon'],
          ':label' => $normalized['label'],
          ':address' => $normalized['address'],
          ':note' => $normalized['note'],
          ':deadline' => $normalized['deadline'],
          ':weight' => $normalized['weight'],
          ':volume' => $normalized['volume'],
          ':extra_json' => $normalized['extra_json'],
          ':version' => $currentVersion + 1,
          ':updated_at' => $now,
          ':id' => $id,
        ]);
      } elseif ((int)$existingRow['position'] !== $normalized['position']) {
        $anyChange = true;
        $updateStop->execute([
          ':round_id' => $normalized['round'],
          ':position' => $normalized['position'],
          ':collapsed' => $normalized['collapsed'],
          ':city' => $normalized['city'],
          ':lat' => $normalized['lat'],
          ':lon' => $normalized['lon'],
          ':label' => $normalized['label'],
          ':address' => $normalized['address'],
          ':note' => $normalized['note'],
          ':deadline' => $normalized['deadline'],
          ':weight' => $normalized['weight'],
          ':volume' => $normalized['volume'],
          ':extra_json' => $normalized['extra_json'],
          ':version' => $currentVersion,
          ':updated_at' => $existingRow['updated_at'] ?? $now,
          ':id' => $id,
        ]);
      }
    } elseif ($existingRow) {
      $anyChange = true;
      $updateStop->execute([
        ':round_id' => $normalized['round'],
        ':position' => $normalized['position'],
        ':collapsed' => $normalized['collapsed'],
        ':city' => $normalized['city'],
        ':lat' => $normalized['lat'],
        ':lon' => $normalized['lon'],
        ':label' => $normalized['label'],
        ':address' => $normalized['address'],
        ':note' => $normalized['note'],
        ':deadline' => $normalized['deadline'],
        ':weight' => $normalized['weight'],
        ':volume' => $normalized['volume'],
        ':extra_json' => $normalized['extra_json'],
        ':version' => (int)$existingRow['version'] + 1,
        ':updated_at' => $now,
        ':id' => $id,
      ]);
    } else {
      $anyChange = true;
      $insertStop->execute([
        ':id' => $id,
        ':round_id' => $normalized['round'],
        ':position' => $normalized['position'],
        ':collapsed' => $normalized['collapsed'],
        ':city' => $normalized['city'],
        ':lat' => $normalized['lat'],
        ':lon' => $normalized['lon'],
        ':label' => $normalized['label'],
        ':address' => $normalized['address'],
        ':note' => $normalized['note'],
        ':deadline' => $normalized['deadline'],
        ':weight' => $normalized['weight'],
        ':volume' => $normalized['volume'],
        ':extra_json' => $normalized['extra_json'],
        ':version' => max(1, $expectedVersion),
        ':created_at' => $now,
        ':updated_at' => $now,
      ]);
    }
  }

  foreach ($existingStops as $id => $row) {
    if (!isset($incomingIds[$id]) && $row['deleted_at'] === null) {
      $anyChange = true;
      $deleteStop->execute([
        ':deleted_at' => $now,
        ':updated_at' => $now,
        ':id' => $id,
      ]);
    }
  }

  $existingRounds = [];
  $stmtRounds = $pdo->query('SELECT id, planned_date, meta_json, version, updated_at, deleted_at FROM rounds');
  while ($row = $stmtRounds->fetch()) {
    $existingRounds[(int)$row['id']] = $row;
  }

  $insertRound = $pdo->prepare('INSERT INTO rounds (id, planned_date, meta_json, version, created_at, updated_at, deleted_at)
    VALUES (:id, :planned_date, :meta_json, :version, :created_at, :updated_at, NULL)');
  $updateRound = $pdo->prepare('UPDATE rounds SET planned_date=:planned_date, meta_json=:meta_json, version=:version, updated_at=:updated_at, deleted_at=NULL WHERE id=:id');
  $deleteRound = $pdo->prepare('UPDATE rounds SET deleted_at=:deleted_at, updated_at=:updated_at, version=version+1 WHERE id=:id AND deleted_at IS NULL');

  $incomingRounds = [];
  $roundMetaArray = is_array($roundMeta) ? $roundMeta : [];
  foreach ($roundMetaArray as $rid => $entry) {
    $normalized = normalize_round_meta_entry($rid, $entry);
    $roundId = (int)$normalized['id'];
    $incomingRounds[$roundId] = true;
    $expectedVersion = (int)$normalized['version'];
    $existing = $existingRounds[$roundId] ?? null;
    $targetMeta = $normalized['meta_json'] ?? null;
    $planned = $normalized['planned_date'];

    if ($existing && $existing['deleted_at'] === null) {
      $currentMeta = $existing['meta_json'] ?? null;
      $currentPlanned = (string)($existing['planned_date'] ?? '');
      $currentVersion = (int)$existing['version'];
      if ($expectedVersion > 0 && $expectedVersion !== $currentVersion) {
        throw new RuntimeException('round_version_conflict:'.$roundId);
      }
      if ($currentMeta !== $targetMeta || $currentPlanned !== $planned) {
        $anyChange = true;
        $updateRound->execute([
          ':id' => $roundId,
          ':planned_date' => $planned !== '' ? $planned : null,
          ':meta_json' => $targetMeta,
          ':version' => $currentVersion + 1,
          ':updated_at' => $now,
        ]);
      }
    } elseif ($existing) {
      $anyChange = true;
      $updateRound->execute([
        ':id' => $roundId,
        ':planned_date' => $planned !== '' ? $planned : null,
        ':meta_json' => $targetMeta,
        ':version' => (int)$existing['version'] + 1,
        ':updated_at' => $now,
      ]);
    } else {
      $anyChange = true;
      $insertRound->execute([
        ':id' => $roundId,
        ':planned_date' => $planned !== '' ? $planned : null,
        ':meta_json' => $targetMeta,
        ':version' => max(1, $expectedVersion),
        ':created_at' => $now,
        ':updated_at' => $now,
      ]);
    }
  }

  foreach ($existingRounds as $rid => $row) {
    if (!isset($incomingRounds[$rid]) && $row['deleted_at'] === null) {
      $anyChange = true;
      $deleteRound->execute([
        ':deleted_at' => $now,
        ':updated_at' => $now,
        ':id' => $rid,
      ]);
    }
  }

  if ($anyChange) {
    bump_dataset_state_version($pdo);
  }

  $pdo->commit();

  return [
    'items' => fetch_all_stops(),
    'round_meta' => fetch_round_meta(),
  ];
}

function data_store_read($file, $options = []) {
  $includeDeleted = is_array($options) && !empty($options['include_deleted']);
  $items = fetch_all_stops($includeDeleted);
  $roundMeta = fetch_round_meta($includeDeleted);
  return [$items, $roundMeta];
}

function data_store_write($file, $items, $roundMeta) {
  return save_state_to_database($items, $roundMeta);
}
