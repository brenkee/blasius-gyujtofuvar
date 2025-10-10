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
    "data_file" => "fuvar_data.json",
    "export_file" => "fuvar_export.csv",
    "export_download_name" => "fuvar_export.csv",
    "archive_file" => "fuvar_archive.log",
    "revision_file" => "fuvar_revision.json",
    "change_log_file" => "fuvar_changes.log",
    "lock_file" => "fuvar_state.lock"
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

$DATA_FILE       = __DIR__ . '/' . ($CFG['files']['data_file'] ?? 'fuvar_data.json');
$EXPORT_FILE     = __DIR__ . '/' . ($CFG['files']['export_file'] ?? 'fuvar_export.txt');
$EXPORT_NAME     = (string)($CFG['files']['export_download_name'] ?? 'fuvar_export.txt');
$ARCHIVE_FILE    = __DIR__ . '/' . ($CFG['files']['archive_file'] ?? 'fuvar_archive.log');
$REVISION_FILE   = __DIR__ . '/' . ($CFG['files']['revision_file'] ?? 'fuvar_revision.json');
$CHANGE_LOG_FILE = __DIR__ . '/' . ($CFG['files']['change_log_file'] ?? 'fuvar_changes.log');
$STATE_LOCK_FILE = __DIR__ . '/' . ($CFG['files']['lock_file'] ?? 'fuvar_state.lock');

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
    if (!empty($entry)) {
      $out[$key] = $entry;
    }
  }
  return $out;
}

function data_store_read($file) {
  $items = [];
  $roundMeta = [];
  if (!is_file($file)) {
    return [$items, $roundMeta];
  }
  $raw = file_get_contents($file);
  $decoded = json_decode($raw ?: '[]', true);
  if (!is_array($decoded)) {
    return [$items, $roundMeta];
  }
  if (is_list_array($decoded)) {
    $items = array_values($decoded);
    return [$items, $roundMeta];
  }
  if (isset($decoded['items']) && is_array($decoded['items'])) {
    $items = array_values($decoded['items']);
  }
  if (isset($decoded['round_meta']) && is_array($decoded['round_meta'])) {
    $roundMeta = normalize_round_meta($decoded['round_meta']);
  }
  return [$items, $roundMeta];
}

function data_store_write($file, $items, $roundMeta) {
  $normalizedMeta = normalize_round_meta($roundMeta);
  $payload = [
    'items' => array_values(is_array($items) ? $items : []),
    'round_meta' => !empty($normalizedMeta) ? $normalizedMeta : (object)[]
  ];
  return file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function state_lock(callable $callback) {
  global $STATE_LOCK_FILE;
  $fh = fopen($STATE_LOCK_FILE, 'c+');
  if (!$fh) {
    throw new RuntimeException('Nem sikerült megnyitni a zároló fájlt.');
  }
  try {
    if (!flock($fh, LOCK_EX)) {
      throw new RuntimeException('Nem sikerült zárolni az állapotot.');
    }
    $result = $callback($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
  } catch (Throwable $e) {
    flock($fh, LOCK_UN);
    fclose($fh);
    throw $e;
  }
}

function read_current_revision() {
  global $REVISION_FILE;
  if (!is_file($REVISION_FILE)) {
    return 0;
  }
  $raw = @file_get_contents($REVISION_FILE);
  if ($raw === false) {
    return 0;
  }
  $decoded = json_decode($raw, true);
  if (is_array($decoded) && isset($decoded['rev']) && is_numeric($decoded['rev'])) {
    return (int)$decoded['rev'];
  }
  if (is_numeric($raw)) {
    return (int)$raw;
  }
  return 0;
}

function write_revision_locked($rev) {
  global $REVISION_FILE;
  $payload = json_encode(['rev' => (int)$rev], JSON_UNESCAPED_UNICODE);
  file_put_contents($REVISION_FILE, $payload, LOCK_EX);
}

function append_change_log_locked(array $entry) {
  global $CHANGE_LOG_FILE;
  $line = json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  file_put_contents($CHANGE_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function normalized_actor_id($raw) {
  $trimmed = trim((string)$raw);
  if ($trimmed === '') {
    return null;
  }
  if (!preg_match('/^[A-Za-z0-9._\-]{3,120}$/', $trimmed)) {
    return null;
  }
  return $trimmed;
}

function normalized_request_id($raw) {
  $trimmed = trim((string)$raw);
  if ($trimmed === '') {
    return null;
  }
  if (!preg_match('/^[A-Za-z0-9._\-]{6,160}$/', $trimmed)) {
    return null;
  }
  return $trimmed;
}

function normalized_batch_id($raw) {
  $trimmed = trim((string)$raw);
  if ($trimmed === '') {
    return null;
  }
  if (!preg_match('/^[A-Za-z0-9._\-]{3,160}$/', $trimmed)) {
    return null;
  }
  return $trimmed;
}

function generate_client_id() {
  $bytes = random_bytes(9);
  $base = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  return 'cli_' . $base;
}

function compute_item_changes(array $oldItems, array $newItems) {
  $oldById = [];
  foreach ($oldItems as $item) {
    if (!is_array($item)) continue;
    $id = isset($item['id']) ? (string)$item['id'] : null;
    if (!$id) continue;
    $oldById[$id] = $item;
  }
  $newById = [];
  foreach ($newItems as $item) {
    if (!is_array($item)) continue;
    $id = isset($item['id']) ? (string)$item['id'] : null;
    if (!$id) continue;
    $newById[$id] = $item;
  }

  $events = [];

  foreach ($newById as $id => $item) {
    if (!isset($oldById[$id])) {
      $events[] = ['entity' => 'item', 'entity_id' => $id, 'action' => 'created', 'meta' => ['fields' => array_keys($item)]];
      continue;
    }
    $before = $oldById[$id];
    $after = $item;
    $changed = [];
    foreach ($after as $key => $value) {
      if ($key === null) continue;
      if (is_array($value)) {
        $encodedAfter = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $encodedBefore = json_encode($before[$key] ?? null, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($encodedAfter !== $encodedBefore) {
          $changed[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
        }
        continue;
      }
      if (!array_key_exists($key, $before) || $before[$key] !== $value) {
        $changed[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
      }
    }
    foreach ($before as $key => $value) {
      if (!array_key_exists($key, $after)) {
        $changed[$key] = ['before' => $value, 'after' => null];
      }
    }
    if (!empty($changed)) {
      $events[] = ['entity' => 'item', 'entity_id' => $id, 'action' => 'updated', 'meta' => ['changes' => $changed]];
    }
  }

  foreach ($oldById as $id => $item) {
    if (!isset($newById[$id])) {
      $events[] = ['entity' => 'item', 'entity_id' => $id, 'action' => 'deleted'];
    }
  }

  return $events;
}

function compute_round_meta_changes(array $oldMeta, array $newMeta) {
  $events = [];
  $old = normalize_round_meta($oldMeta);
  $new = normalize_round_meta($newMeta);
  $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
  foreach ($allKeys as $rid) {
    $before = $old[$rid] ?? [];
    $after = $new[$rid] ?? [];
    if ($before === $after) continue;
    $events[] = [
      'entity' => 'round_meta',
      'entity_id' => $rid,
      'action' => empty($after) ? 'cleared' : 'updated',
      'meta' => ['before' => $before, 'after' => $after]
    ];
  }
  return $events;
}

function read_change_log_entries() {
  global $CHANGE_LOG_FILE;
  if (!is_file($CHANGE_LOG_FILE)) {
    return [];
  }
  $fh = fopen($CHANGE_LOG_FILE, 'r');
  if (!$fh) {
    return [];
  }
  $entries = [];
  if (flock($fh, LOCK_SH)) {
    while (($line = fgets($fh)) !== false) {
      $line = trim($line);
      if ($line === '') continue;
      $decoded = json_decode($line, true);
      if (is_array($decoded) && isset($decoded['rev'])) {
        $entries[] = $decoded;
      }
    }
    flock($fh, LOCK_UN);
  }
  fclose($fh);
  return $entries;
}
