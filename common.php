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
    "round_planned_date" => true,
    "round_planned_time" => true
  ],
    "files" => [
    "data_file" => "data/app.db",
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
    "toolbar" => [
      "menu_icon" => [
        "width" => "20px",
        "height" => "14px",
        "bar_height" => "2px",
        "color" => "currentColor",
        "bar_radius" => "2px"
      ]
    ],
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
      "undo" => ["label" => "Visszavonás", "title" => "Visszavonás"],
      "more_actions" => ["label" => "Menü", "title" => "További műveletek"]
    ],
    "badges" => [
      "pin_counter_label" => "Pin-ek:",
      "pin_counter_title" => "Aktív pin jelölők száma"
    ],
    "round" => [
      "planned_date_label" => "Tervezett dátum",
      "planned_date_hint" => "Válaszd ki a kör tervezett dátumát",
      "planned_time_label" => "Tervezett idő",
      "planned_time_hint" => "Add meg a kör tervezett idejét",
      "sort_mode_label" => "Rendezési mód",
      "sort_mode_default" => "Alapértelmezett (távolság)",
      "sort_mode_custom" => "Egyéni (drag & drop)",
      "sort_mode_custom_hint" => "Fogd és vidd a címeket a sorrend módosításához",
      "custom_sort_handle_hint" => "Fogd meg és húzd a cím átrendezéséhez"
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
        "import_in_progress" => "Import folyamatban…",
        "import_mode_prompt" => "Felülírjuk a jelenlegi adatokat az importált CSV-vel, vagy hozzáadjuk az új sorokat?",
        "import_mode_replace" => "Felülírás",
        "import_mode_append" => "Hozzáadás",
        "import_mode_confirm_replace" => "Biztosan felülírjuk a jelenlegi adatokat a CSV tartalmával?",
        "import_mode_confirm_append" => "Biztosan hozzáadjuk az új sorokat a meglévő listához?",
        "import_geocode_partial" => "Figyelem: {count} címet nem sikerült automatikusan térképre tenni.",
        "import_geocode_partial_detail" => "Nem sikerült geokódolni:\n{list}",
        "import_geocode_partial_list_title" => "Nem sikerült geokódolni:",
        "import_geocode_use_city" => "Település alapján helyezze el",
        "import_geocode_skip_city" => "Bezárás",
        "import_city_fallback_progress" => "Települések geokódolása…",
        "import_city_fallback_result" => "Település-alapú geokódolás – sikeres: {success}, sikertelen: {failed}."
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
    "min_interval_minutes" => 10,
    "retention_policy" => [
      ["min_age_hours" => 12, "period_hours" => 1],
      ["min_age_hours" => 24, "period_hours" => 3],
      ["min_age_hours" => 72, "period_hours" => 24],
      ["min_age_hours" => 168, "period_hours" => 72],
      ["min_age_hours" => 720, "period_hours" => 168]
    ],
    "strategy" => "interval_csv"
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

$DATA_FILE       = __DIR__ . '/' . ($CFG['files']['data_file'] ?? 'data/app.db');
$EXPORT_FILE     = __DIR__ . '/' . ($CFG['files']['export_file'] ?? 'fuvar_export.txt');
$EXPORT_NAME     = (string)($CFG['files']['export_download_name'] ?? 'fuvar_export.txt');
$ARCHIVE_FILE    = __DIR__ . '/' . ($CFG['files']['archive_file'] ?? 'fuvar_archive.log');
$REVISION_FILE   = __DIR__ . '/' . ($CFG['files']['revision_file'] ?? 'fuvar_revision.json');
$CHANGE_LOG_FILE = __DIR__ . '/' . ($CFG['files']['change_log_file'] ?? 'fuvar_changes.log');
$STATE_LOCK_FILE = __DIR__ . '/' . ($CFG['files']['lock_file'] ?? 'fuvar_state.lock');

$DATA_BOOTSTRAP_INFO = [];
$DATA_INIT_ERROR = null;

/**
 * Biztonságos backup: létezés-ellenőrzés és mtime használat védetten.
 * Elkerüli a "filemtime(): stat failed" warningokat versenyhelyzet esetén.
 */
function generate_export_csv($cfg, $dataFile, $roundFilter = null, &$error = null) {
  $error = null;

  [$items, $roundMeta] = data_store_read($dataFile);
  $items = array_values(array_filter(is_array($items) ? $items : []));
  $roundMeta = is_array($roundMeta) ? $roundMeta : [];
  if (!empty($roundMeta)) {
    ksort($roundMeta, SORT_STRING);
  }

  $autoSort = (bool)($cfg['app']['auto_sort_by_round'] ?? true);
  $zeroBottom = (bool)($cfg['app']['round_zero_at_bottom'] ?? true);
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

  $itemsCfg = $cfg['items'] ?? [];
  $fieldsCfg = array_values(array_filter($itemsCfg['fields'] ?? [], function($f){ return ($f['enabled'] ?? true) !== false; }));
  $metricsCfg = array_values(array_filter($itemsCfg['metrics'] ?? [], function($m){ return ($m['enabled'] ?? true) !== false; }));
  $fieldIds = array_values(array_filter(array_map(function($f){ return $f['id'] ?? null; }, $fieldsCfg)));
  $metricIds = array_values(array_filter(array_map(function($m){ return $m['id'] ?? null; }, $metricsCfg)));

  $normalizeColumnId = function($value) {
    if ($value === null) return null;
    $key = (string)$value;
    if ($key === '') return null;
    $key = str_replace("\xEF\xBB\xBF", '', $key);
    $key = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $key);
    $key = str_replace("\xC2\xA0", ' ', $key);
    $key = trim($key);
    if ($key === '') return null;
    return $key;
  };

  $normalizeRecordKeys = function(array $record) use ($normalizeColumnId) {
    $clean = [];
    foreach ($record as $rawKey => $value) {
      $key = $normalizeColumnId($rawKey);
      if ($key === null) continue;
      if ($key === 'type') continue;
      if (strpos($key, '_') === 0) continue;
      if (!array_key_exists($key, $clean)) {
        $clean[$key] = $value;
      }
    }
    return $clean;
  };

  $columns = [];
  $addColumn = function($id) use (&$columns, $normalizeColumnId) {
    $key = $normalizeColumnId($id);
    if ($key === null) return;
    if ($key === 'type') {
      if (!in_array('type', $columns, true)) {
        $columns[] = 'type';
      }
      return;
    }
    if (!in_array($key, $columns, true)) {
      $columns[] = $key;
    }
  };
  $addColumn('type');
  $addColumn('id');
  $addColumn('round');
  foreach ($fieldIds as $fid) { $addColumn($fid); }
  foreach ($metricIds as $mid) { $addColumn($mid); }
  foreach (['city','lat','lon'] as $extra) { $addColumn($extra); }
  foreach ($roundMeta as $meta) {
    if (!is_array($meta)) continue;
    $normalizedMeta = $normalizeRecordKeys($meta);
    foreach ($normalizedMeta as $metaKey => $_value) {
      $addColumn($metaKey);
    }
  }

  foreach ($items as $it) {
    if (!is_array($it)) continue;
    if ($roundFilter !== null && (int)($it['round'] ?? 0) !== $roundFilter) {
      continue;
    }
    $normalizedItem = $normalizeRecordKeys($it);
    foreach ($normalizedItem as $key => $_value) {
      $addColumn($key);
    }
  }
  if (empty($columns)) {
    $columns = ['type','id','round'];
  }

  $delimiter = ';';
  $fh = fopen('php://temp', 'r+');
  if (!$fh) {
    $error = 'Export hiba';
    return null;
  }

  $metricIdSet = [];
  foreach ($metricIds as $mid) {
    $metricIdSet[$mid] = true;
  }

  $writeRow = function(array $record, $type) use ($columns, $delimiter, $fh, $metricIdSet) {
    $row = [];
    foreach ($columns as $col) {
      if ($col === null || $col === '') {
        $row[] = '';
        continue;
      }
      if ($col === 'type') {
        $row[] = $type;
        continue;
      }
      if (!array_key_exists($col, $record) || $record[$col] === null) {
        $row[] = '';
        continue;
      }
      $value = $record[$col];
      if ($col === 'round') {
        $row[] = (string)((int)$value);
      } elseif ($col === 'lat' || $col === 'lon' || isset($metricIdSet[$col])) {
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
  };

  fputcsv($fh, $columns, $delimiter);
  foreach ($items as $it) {
    if (!is_array($it)) continue;
    if ($roundFilter !== null && (int)($it['round'] ?? 0) !== $roundFilter) {
      continue;
    }
    $record = $normalizeRecordKeys($it);
    if (!array_key_exists('id', $record) && isset($it['id'])) {
      $record['id'] = $it['id'];
    }
    if (!array_key_exists('round', $record) && isset($it['round'])) {
      $record['round'] = $it['round'];
    }
    if (!array_key_exists('city', $record) && array_key_exists('city', $it)) {
      $record['city'] = $it['city'];
    }
    if (!array_key_exists('lat', $record) && array_key_exists('lat', $it)) {
      $record['lat'] = $it['lat'];
    }
    if (!array_key_exists('lon', $record) && array_key_exists('lon', $it)) {
      $record['lon'] = $it['lon'];
    }
    $writeRow($record, 'address');
  }

  foreach ($roundMeta as $roundId => $meta) {
    if (!is_array($meta) || empty($meta)) continue;
    $round = (int)$roundId;
    if ($roundFilter !== null && $round !== $roundFilter) {
      continue;
    }
    $record = $normalizeRecordKeys($meta);
    $record['id'] = 'route_' . $roundId;
    $record['round'] = $round;
    $record['type'] = 'route';
    $writeRow($record, 'route');
  }

  rewind($fh);
  $csvBody = stream_get_contents($fh);
  fclose($fh);
  if ($csvBody === false) {
    $error = 'Export hiba';
    return null;
  }

  return "\xEF\xBB\xBF" . $csvBody;
}

function backup_now($cfg, $dataFile) {
  if (empty($cfg['backup']['enabled'])) return;

  $dir = __DIR__ . '/' . ($cfg['backup']['dir'] ?? 'backups');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir)) return;

  if (!is_file($dataFile)) return;

  $intervalMinutes = (int)($cfg['backup']['min_interval_minutes'] ?? 10);
  if ($intervalMinutes < 0) { $intervalMinutes = 0; }
  $stateFile = $dir . '/backup_state.json';
  $lastBackupTs = 0;
  if (is_file($stateFile)) {
    $rawState = @file_get_contents($stateFile);
    if ($rawState !== false) {
      $decoded = json_decode($rawState, true);
      if (is_array($decoded) && isset($decoded['last_backup_ts'])) {
        $lastBackupTs = (int)$decoded['last_backup_ts'];
      }
    }
  }

  $now = time();
  if ($intervalMinutes > 0 && $lastBackupTs > 0 && ($now - $lastBackupTs) < ($intervalMinutes * 60)) {
    return;
  }

  $error = null;
  $csv = generate_export_csv($cfg, $dataFile, null, $error);
  if ($csv === null) {
    return;
  }

  $ts = date('Ymd_His', $now);
  $target = $dir . '/fuvar_data_' . $ts . '.csv';
  if (@file_put_contents($target, $csv) === false) {
    return;
  }

  $statePayload = json_encode(['last_backup_ts' => $now], JSON_UNESCAPED_UNICODE);
  if ($statePayload !== false) {
    @file_put_contents($stateFile, $statePayload, LOCK_EX);
  }

  $collectFiles = function($pattern) {
    $list = glob($pattern);
    if (!is_array($list)) { return []; }
    return $list;
  };
  $files = array_merge(
    $collectFiles($dir . '/fuvar_data_*.csv'),
    $collectFiles($dir . '/fuvar_data_*.json')
  );
  if (!$files) {
    return;
  }

  $mtime = function($path) {
    if (!is_file($path)) return 0;
    $t = @filemtime($path);
    return $t ? (int)$t : 0;
  };

  usort($files, function($a, $b) use ($mtime) {
    return $mtime($b) <=> $mtime($a);
  });

  $defaultPolicy = [
    ['min_age_hours' => 720, 'period_hours' => 168],
    ['min_age_hours' => 168, 'period_hours' => 72],
    ['min_age_hours' => 72, 'period_hours' => 24],
    ['min_age_hours' => 24, 'period_hours' => 3],
    ['min_age_hours' => 12, 'period_hours' => 1],
  ];

  $policy = [];
  $rawPolicy = $cfg['backup']['retention_policy'] ?? $defaultPolicy;
  if (!is_array($rawPolicy) || empty($rawPolicy)) {
    $rawPolicy = $defaultPolicy;
  }
  foreach ($rawPolicy as $rule) {
    if (!is_array($rule)) continue;
    $minAgeH = isset($rule['min_age_hours']) ? (float)$rule['min_age_hours'] : null;
    $periodH = isset($rule['period_hours']) ? (float)$rule['period_hours'] : null;
    if ($minAgeH === null || $periodH === null) continue;
    $minAge = (int)round($minAgeH * 3600);
    $period = (int)round($periodH * 3600);
    if ($minAge <= 0 || $period <= 0) continue;
    $policy[] = ['min_age' => $minAge, 'period' => $period];
  }
  if (empty($policy)) {
    $policy = array_map(function($r){
      return [
        'min_age' => (int)round($r['min_age_hours'] * 3600),
        'period' => (int)round($r['period_hours'] * 3600)
      ];
    }, $defaultPolicy);
  }

  usort($policy, function($a, $b) {
    return $b['min_age'] <=> $a['min_age'];
  });

  $buckets = [];
  foreach ($policy as $idx => $rule) {
    $key = $idx . ':' . $rule['min_age'] . ':' . $rule['period'];
    $buckets[$key] = [];
  }

  $toDelete = [];
  foreach ($files as $path) {
    if (!is_file($path)) continue;
    if ($path === $target) continue;
    $mt = $mtime($path);
    if ($mt === 0) continue;
    $age = $now - $mt;
    $handled = false;
    foreach ($policy as $idx => $rule) {
      if ($age < $rule['min_age']) {
        continue;
      }
      $handled = true;
      $bucketSize = max(1, $rule['period']);
      $bucketId = (int)floor($mt / $bucketSize);
      $bucketKey = $idx . ':' . $rule['min_age'] . ':' . $rule['period'];
      if (!isset($buckets[$bucketKey])) {
        $buckets[$bucketKey] = [];
      }
      if (isset($buckets[$bucketKey][$bucketId])) {
        $toDelete[] = $path;
      } else {
        $buckets[$bucketKey][$bucketId] = true;
      }
      break;
    }
    if (!$handled) {
      continue;
    }
  }

  foreach ($toDelete as $path) {
    if (is_file($path)) {
      @unlink($path);
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
    if ($key === '') continue;
    $entry = [];
    foreach ($meta as $metaKey => $value) {
      if ($metaKey === null) continue;
      $metaKeyStr = (string)$metaKey;
      if ($metaKeyStr === '') continue;
      if ($metaKeyStr === 'planned_date') {
        $val = trim((string)$value);
        if ($val === '') continue;
        if (function_exists('mb_substr')) {
          $val = mb_substr($val, 0, 120);
        } else {
          $val = substr($val, 0, 120);
        }
        $entry[$metaKeyStr] = $val;
        continue;
      }
      if ($metaKeyStr === 'planned_time') {
        $val = trim((string)$value);
        if ($val === '') continue;
        if (function_exists('mb_substr')) {
          $val = mb_substr($val, 0, 40);
        } else {
          $val = substr($val, 0, 40);
        }
        $entry[$metaKeyStr] = $val;
        continue;
      }
      if ($metaKeyStr === 'sort_mode') {
        $val = strtolower(trim((string)$value));
        $entry[$metaKeyStr] = ($val === 'custom') ? 'custom' : 'default';
        continue;
      }
      if ($metaKeyStr === 'custom_order') {
        $source = $value;
        if (!is_array($source) && is_string($source) && trim($source) !== '') {
          $decoded = json_decode($source, true);
          if (is_array($decoded)) {
            $source = $decoded;
          }
        }
        if (is_array($source)) {
          $list = [];
          $seen = [];
          foreach ($source as $itemVal) {
            if ($itemVal === null) continue;
            $itemStr = trim((string)$itemVal);
            if ($itemStr === '') continue;
            if (isset($seen[$itemStr])) continue;
            $seen[$itemStr] = true;
            $list[] = $itemStr;
          }
          if (!empty($list)) {
            $entry[$metaKeyStr] = $list;
          }
        }
        continue;
      }
      if (is_scalar($value)) {
        $val = trim((string)$value);
        if ($val === '') continue;
        if (function_exists('mb_substr')) {
          $val = mb_substr($val, 0, 200);
        } else {
          $val = substr($val, 0, 200);
        }
        $entry[$metaKeyStr] = $val;
      }
    }
    if (!isset($entry['sort_mode'])) {
      $entry['sort_mode'] = (!empty($entry['custom_order'])) ? 'custom' : 'default';
    }
    if (!empty($entry)) {
      ksort($entry);
      $out[$key] = $entry;
    }
  }
  if (!empty($out)) {
    ksort($out, SORT_STRING);
  }
  return $out;
}

function normalize_items(array $items) {
  $normalized = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $clean = [];
    foreach ($item as $key => $value) {
      if ($key === null) continue;
      $keyStr = is_string($key) ? $key : (string)$key;
      $keyStr = preg_replace('/^\xEF\xBB\xBF/u', '', $keyStr);
      if ($keyStr === '') continue;
      if ($keyStr === 'collapsed' || $keyStr === 'type') continue;
      $clean[$keyStr] = $value;
    }
    if (!empty($clean)) {
      $normalized[] = $clean;
    }
  }
  return $normalized;
}

function data_store_is_sqlite($file) {
  $extension = strtolower((string)pathinfo((string)$file, PATHINFO_EXTENSION));
  return in_array($extension, ['sqlite', 'sqlite3', 'db'], true);
}

function data_store_sqlite_is_empty(PDO $pdo) {
  $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM items')->fetchColumn();
  if ($count > 0) {
    return false;
  }
  $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM round_meta')->fetchColumn();
  return $count === 0;
}

function data_store_sqlite_open($file) {
  $dir = dirname($file);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  global $DATA_BOOTSTRAP_INFO, $DATA_INIT_ERROR;
  if (!empty($DATA_INIT_ERROR)) {
    throw new RuntimeException($DATA_INIT_ERROR);
  }
  $bootstrap = $DATA_BOOTSTRAP_INFO[$file] ?? null;
  $isNew = is_array($bootstrap) ? !empty($bootstrap['created']) : !is_file($file);
  $pdo = new PDO('sqlite:' . $file);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
  $pdo->exec('PRAGMA foreign_keys = ON');
  $pdo->exec('PRAGMA journal_mode = WAL');
  $pdo->exec('PRAGMA synchronous = NORMAL');
  return [$pdo, $isNew];
}

function data_store_sqlite_guess_legacy_json($file) {
  $candidates = [];
  $ext = pathinfo($file, PATHINFO_EXTENSION);
  if ($ext) {
    $candidates[] = preg_replace('/\.(sqlite3?|db)$/i', '.json', $file);
  }
  $candidates[] = dirname($file) . '/fuvar_data.json';
  foreach ($candidates as $candidate) {
    if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
      return $candidate;
    }
  }
  return null;
}

function data_store_sqlite_import_legacy(PDO $pdo, $dbPath) {
  if (!data_store_sqlite_is_empty($pdo)) {
    return;
  }
  $legacy = data_store_sqlite_guess_legacy_json($dbPath);
  if (!$legacy) {
    return;
  }
  [$items, $roundMeta] = data_store_read_json($legacy);
  if (empty($items) && empty($roundMeta)) {
    return;
  }
  data_store_write_sqlite_conn($pdo, $items, $roundMeta);
}

function data_store_read_sqlite($file) {
  try {
    [$pdo, $isNew] = data_store_sqlite_open($file);
  } catch (Throwable $e) {
    error_log('SQLite megnyitási hiba: ' . $e->getMessage());
    return [[], []];
  }

  global $DATA_BOOTSTRAP_INFO;
  $bootstrap = $DATA_BOOTSTRAP_INFO[$file] ?? null;
  $shouldImportLegacy = false;
  if (is_array($bootstrap)) {
    $shouldImportLegacy = !empty($bootstrap['created']) && empty($bootstrap['seeded']);
  } else {
    $shouldImportLegacy = $isNew;
  }

  if ($shouldImportLegacy) {
    try {
      data_store_sqlite_import_legacy($pdo, $file);
    } catch (Throwable $e) {
      error_log('Legacy JSON import sikertelen: ' . $e->getMessage());
    }
  }

  try {
    $items = [];
    $stmt = $pdo->query('SELECT data FROM items ORDER BY position ASC, id ASC');
    if ($stmt) {
      foreach ($stmt as $row) {
        if (!is_array($row)) continue;
        $payload = $row['data'] ?? null;
        if (!is_string($payload)) continue;
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
          $items[] = $decoded;
        }
      }
    }
    $roundMetaRaw = [];
    $stmt = $pdo->query('SELECT round_id, data FROM round_meta');
    if ($stmt) {
      foreach ($stmt as $row) {
        if (!is_array($row)) continue;
        $rid = isset($row['round_id']) ? (string)$row['round_id'] : '';
        if ($rid === '') continue;
        $payload = $row['data'] ?? null;
        if (!is_string($payload)) continue;
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
          $roundMetaRaw[$rid] = $decoded;
        }
      }
    }
    $items = normalize_items($items);
    $roundMeta = normalize_round_meta($roundMetaRaw);
    return [$items, $roundMeta];
  } catch (Throwable $e) {
    error_log('SQLite olvasási hiba: ' . $e->getMessage());
    return [[], []];
  }
}

function data_store_write_sqlite_conn(PDO $pdo, array $items, array $roundMeta) {
  $normalizedItems = normalize_items($items);
  $normalizedMeta = normalize_round_meta($roundMeta);

  $pdo->beginTransaction();
  $pdo->exec('DELETE FROM items');
  $pdo->exec('DELETE FROM round_meta');

  if (!empty($normalizedItems)) {
    $insertItem = $pdo->prepare('INSERT INTO items (id, position, data) VALUES (:id, :position, :data)');
    foreach (array_values($normalizedItems) as $position => $item) {
      $id = isset($item['id']) ? (string)$item['id'] : '';
      if ($id === '') {
        throw new RuntimeException('Hiányzó azonosító az egyik elemnél.');
      }
      $json = json_encode($item, JSON_UNESCAPED_UNICODE);
      if ($json === false) {
        throw new RuntimeException('JSON kódolási hiba elem írásakor.');
      }
      $insertItem->execute([
        ':id' => $id,
        ':position' => (int)$position,
        ':data' => $json
      ]);
    }
  }

  if (!empty($normalizedMeta)) {
    $insertMeta = $pdo->prepare('INSERT INTO round_meta (round_id, data) VALUES (:round_id, :data)');
    foreach ($normalizedMeta as $roundId => $meta) {
      $roundKey = (string)$roundId;
      if ($roundKey === '') {
        continue;
      }
      $json = json_encode($meta, JSON_UNESCAPED_UNICODE);
      if ($json === false) {
        throw new RuntimeException('JSON kódolási hiba kör meta írásakor.');
      }
      $insertMeta->execute([
        ':round_id' => $roundKey,
        ':data' => $json
      ]);
    }
  }

  $pdo->commit();
  return true;
}

function data_store_write_sqlite($file, $items, $roundMeta) {
  try {
    [$pdo] = data_store_sqlite_open($file);
    return data_store_write_sqlite_conn($pdo, is_array($items) ? $items : [], $roundMeta);
  } catch (Throwable $e) {
    error_log('SQLite írási hiba: ' . $e->getMessage());
    return false;
  }
}

function data_store_read_json($file) {
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
  $items = normalize_items($items);
  if (isset($decoded['round_meta']) && is_array($decoded['round_meta'])) {
    $roundMeta = normalize_round_meta($decoded['round_meta']);
  }
  return [$items, $roundMeta];
}

function data_store_write_json($file, $items, $roundMeta) {
  $normalizedItems = normalize_items(is_array($items) ? $items : []);
  $normalizedMeta = normalize_round_meta($roundMeta);
  $payload = [
    'items' => array_values($normalizedItems),
    'round_meta' => !empty($normalizedMeta) ? $normalizedMeta : (object)[]
  ];
  return file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function data_store_read($file) {
  if (data_store_is_sqlite($file)) {
    return data_store_read_sqlite($file);
  }
  return data_store_read_json($file);
}

function data_store_write($file, $items, $roundMeta) {
  if (data_store_is_sqlite($file)) {
    return data_store_write_sqlite($file, $items, $roundMeta);
  }
  return data_store_write_json($file, $items, $roundMeta);
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

  if (!isset($entry['ts'])) {
    $entry['ts'] = gmdate('c');
  }

  $maxAgeSeconds = 86400; // 1 day
  $cutoff = time() - $maxAgeSeconds;
  $newLine = json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  $fh = @fopen($CHANGE_LOG_FILE, 'c+');
  if (!$fh) {
    file_put_contents($CHANGE_LOG_FILE, $newLine . "\n", FILE_APPEND | LOCK_EX);
    return;
  }

  $retained = [];
  if (flock($fh, LOCK_EX)) {
    rewind($fh);
    while (($line = fgets($fh)) !== false) {
      $line = trim($line);
      if ($line === '') continue;

      $keep = true;
      $decoded = json_decode($line, true);
      if (is_array($decoded) && isset($decoded['ts'])) {
        $ts = strtotime((string)$decoded['ts']);
        if ($ts !== false && $ts < $cutoff) {
          $keep = false;
        }
      }

      if ($keep) {
        $retained[] = $line;
      }
    }

    $retained[] = $newLine;

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, implode("\n", $retained) . "\n");
    fflush($fh);
    flock($fh, LOCK_UN);
  } else {
    // Fallback if we could not acquire the lock.
    file_put_contents($CHANGE_LOG_FILE, $newLine . "\n", FILE_APPEND | LOCK_EX);
  }

  fclose($fh);
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

function bootstrap_data_store_if_needed() {
  global $DATA_FILE, $DATA_BOOTSTRAP_INFO, $DATA_INIT_ERROR;

  if (!data_store_is_sqlite($DATA_FILE)) {
    return;
  }

  $script = __DIR__ . '/scripts/init-db.php';
  if (!is_file($script)) {
    $DATA_INIT_ERROR = 'Hiányzik az adatbázis inicializáló script. Futtasd a "php scripts/init-db.php" parancsot a létrehozáshoz.';
    return;
  }

  require_once $script;
  if (!function_exists('init_app_database')) {
    $DATA_INIT_ERROR = 'A scripts/init-db.php nem tartalmaz init_app_database függvényt.';
    return;
  }

  $seedPreference = null;
  $legacyJson = data_store_sqlite_guess_legacy_json($DATA_FILE);
  if ($legacyJson) {
    $seedPreference = false;
  }

  try {
    $result = init_app_database([
      'base_dir' => __DIR__,
      'db_path' => $DATA_FILE,
      'seed' => $seedPreference,
    ]);
    if (is_array($result)) {
      $DATA_BOOTSTRAP_INFO[$DATA_FILE] = $result;
    }
  } catch (Throwable $e) {
    $DATA_INIT_ERROR = 'Az adatbázis inicializálása nem sikerült. Próbáld meg futtatni a "php scripts/init-db.php" parancsot. Részletek: ' . $e->getMessage();
    error_log('Adatbázis inicializációs hiba: ' . $e->getMessage());
  }
}

bootstrap_data_store_if_needed();
