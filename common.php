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
        "import_error" => "Az importálás nem sikerült."
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
