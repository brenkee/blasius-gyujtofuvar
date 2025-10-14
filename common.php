<?php
// Közös betöltések: konfiguráció, körök, fájlok, segédfüggvények

if (!isset($GLOBALS['APP_PERF'])) {
  $GLOBALS['APP_PERF'] = [
    'start' => microtime(true),
    'db' => 0.0,
  ];
}

if (!function_exists('app_perf_track_db')) {
  function app_perf_track_db($start, $end = null) {
    $endTime = $end ?? microtime(true);
    if (!is_float($start)) {
      return;
    }
    $delta = $endTime - $start;
    if (!is_float($delta) || $delta < 0) {
      return;
    }
    $GLOBALS['APP_PERF']['db'] = ($GLOBALS['APP_PERF']['db'] ?? 0.0) + $delta;
  }
}

if (!function_exists('app_perf_register_shutdown')) {
  function app_perf_register_shutdown() {
    static $registered = false;
    if ($registered) {
      return;
    }
    $registered = true;
    register_shutdown_function(function () {
      if (headers_sent()) {
        return;
      }
      $perf = $GLOBALS['APP_PERF'] ?? [];
      $start = isset($perf['start']) && is_float($perf['start']) ? $perf['start'] : microtime(true);
      $total = microtime(true) - $start;
      $db = isset($perf['db']) && is_float($perf['db']) ? $perf['db'] : 0.0;
      header(sprintf('X-App-Perf-Total: %.5f', max(0, $total)));
      header(sprintf('X-App-Perf-DB: %.5f', max(0, $db)));
    });
  }
}

app_perf_register_shutdown();

if (!defined('APP_SESSION_NAME')) {
  define('APP_SESSION_NAME', 'GFSESSID');
}

if (!defined('APP_CSRF_COOKIE')) {
  define('APP_CSRF_COOKIE', 'GF-CSRF');
}

$CONFIG_FILE = __DIR__ . '/config/config.json';
$CFG_DEFAULT = [
  "base_url" => "/",
  "app" => [
    "title" => "Gyűjtőfuvar – címkezelő",
    "logo" => null,
    "auto_sort_by_round" => true,
    "round_zero_at_bottom" => true,
    "default_collapsed" => false
  ],
  "history" => [
    "undo_enabled" => true,
    "max_steps" => 3
  ],
  "change_log" => [
    "retention_days" => 7,
  ],
  "change_watcher" => [
    "enabled" => true,
    "pause_when_hidden" => true,
    "error_retry_delay_ms" => 1200,
    "revision" => [
      "enabled" => true,
      "interval_ms" => 12000,
    ],
    "long_poll" => [
      "timeout_seconds" => 25,
      "sleep_microseconds" => 300000,
    ],
  ],
  "features" => [
    "toolbar" => [
      "expand_all" => true,
      "collapse_all" => true,
      "import_all" => true,
      "export_all" => true,
      "print_all" => true,
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
    "export_file" => "backups/fuvar_export.csv",
    "export_download_name" => "fuvar_export.csv",
    "revision_file" => "temp/fuvar_revision.json",
    "change_log_file" => "temp/fuvar_changes.log",
    "lock_file" => "temp/fuvar_state.lock"
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
  "smtp" => [
    "host" => "",
    "port" => 587,
    "username" => "",
    "password" => "",
    "encryption" => "tls",
    "from_email" => "",
    "from_name" => "Gyűjtőfuvar",
    "timeout" => 15
  ],
  "ui" => [
    "panel_min_px" => 330,
    "panel_pref_vw" => 36,
    "panel_max_px" => 520,
    "toolbar" => [
      "order" => ['expand_all', 'collapse_all', 'pin_counter', 'undo', 'menu'],
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
        "geocode_failed_detailed" => "Geokódolás sikertelen. Írd át a címet más formátumban, majd próbáld újra.",
        "geocode_missing" => "A cím nincs geokódolva. Írd át más formátumban, majd mentsd újra.",
        "undo_unavailable" => "Nincs visszavonható művelet.",
        "import_success" => "Import kész.",
        "import_error" => "Az importálás nem sikerült.",
        "import_in_progress" => "Import folyamatban…",
        "import_mode_prompt" => "Felülírjuk a jelenlegi adatokat az importált CSV-vel, vagy hozzáadjuk az új sorokat?",
        "import_mode_replace" => "Felülírás",
        "import_mode_append" => "Hozzáadás",
        "import_mode_confirm_replace" => "Biztosan felülírjuk a jelenlegi adatokat a CSV tartalmával?",
        "import_mode_confirm_append" => "Biztosan hozzáadjuk az új sorokat a meglévő listához?",
        "import_geocode_partial" => "Figyelem: {count} címet nem sikerült geokódolni. Válassz a lehetőségek közül.",
        "import_geocode_partial_detail" => "Nem sikerült geokódolni:\n{list}",
        "import_geocode_partial_list_title" => "Nem sikerült geokódolni:",
        "import_geocode_keep" => "Címek hozzáadása geokódolás nélkül",
        "import_geocode_keep_result" => "A hibás címek geokódolás nélkül kerültek be. Keresd a piros térkép ikont.",
        "import_geocode_keep_error" => "A hibás címek jelölése nem mentődött el teljesen.",
        "import_geocode_keep_none" => "Nem történt módosítás.",
        "import_geocode_skip_addresses" => "Hibás címek kihagyása",
        "import_geocode_skip_city" => "Bezárás"
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

if (!function_exists('normalize_base_url')) {
  function normalize_base_url($value) {
    if (!is_string($value)) {
      $value = '';
    }
    $value = trim($value);
    if ($value === '') {
      return '/';
    }
    if (preg_match('~^https?://~i', $value)) {
      return rtrim($value, '/') . '/';
    }
    if ($value[0] !== '/') {
      $value = '/' . $value;
    }
    return rtrim($value, '/') . '/';
  }
}

if (!function_exists('base_url')) {
  function base_url($path = '') {
    global $CFG;
    $base = isset($CFG['base_url']) && is_string($CFG['base_url'])
      ? $CFG['base_url']
      : '/';
    if ($path === '' || $path === null) {
      return $base;
    }
    $pathStr = (string)$path;
    if ($pathStr === '') {
      return $base;
    }
    if (preg_match('~^https?://~i', $pathStr)) {
      return $pathStr;
    }
    if ($pathStr !== '' && $pathStr[0] === '/') {
      $pathStr = ltrim($pathStr, '/');
    }
    return $base . $pathStr;
  }
}

if (!function_exists('app_is_https')) {
  function app_is_https() {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
      return true;
    }
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($proto === 'https') {
      return true;
    }
    return false;
  }
}

if (!function_exists('app_base_path')) {
  function app_base_path() {
    global $APP_BASE_PATH;
    return $APP_BASE_PATH ?? '/';
  }
}

if (!function_exists('app_url_path')) {
  function app_url_path($relative = '') {
    $base = app_base_path();
    if (!is_string($relative) || $relative === '' || $relative === '/') {
      return $base;
    }
    return $base . ltrim((string)$relative, '/');
  }
}

if (!function_exists('app_cookie_path')) {
  function app_cookie_path() {
    $base = app_base_path();
    return $base !== '' ? rtrim($base, '/') . '/' : '/';
  }
}

$CFG = $CFG_DEFAULT;
if (is_file($CONFIG_FILE)) {
  $raw = file_get_contents($CONFIG_FILE);
  $json = json_decode($raw, true);
  if (is_array($json)) $CFG = array_replace_recursive($CFG_DEFAULT, $json);
}

$BASE_URL_FILES = [
  __DIR__ . '/config/base_url.local.json',
  __DIR__ . '/config/base_url.json',
];
foreach ($BASE_URL_FILES as $baseUrlFile) {
  if (!is_file($baseUrlFile)) {
    continue;
  }
  $raw = file_get_contents($baseUrlFile);
  if ($raw === false) {
    continue;
  }
  $json = json_decode($raw, true);
  if (!is_array($json) || !isset($json['base_url'])) {
    continue;
  }
  $CFG['base_url'] = $json['base_url'];
  break;
}

$CFG['base_url'] = normalize_base_url($CFG['base_url'] ?? '/');
$APP_BASE_PATH = parse_url($CFG['base_url'], PHP_URL_PATH);
if (!is_string($APP_BASE_PATH) || $APP_BASE_PATH === '') {
  $APP_BASE_PATH = '/';
}
if (substr($APP_BASE_PATH, -1) !== '/') {
  $APP_BASE_PATH .= '/';
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
$EXPORT_FILE     = __DIR__ . '/' . ($CFG['files']['export_file'] ?? 'backups/fuvar_export.txt');
$EXPORT_NAME     = (string)($CFG['files']['export_download_name'] ?? 'fuvar_export.txt');
$REVISION_FILE   = __DIR__ . '/' . ($CFG['files']['revision_file'] ?? 'temp/fuvar_revision.json');
$CHANGE_LOG_FILE = __DIR__ . '/' . ($CFG['files']['change_log_file'] ?? 'temp/fuvar_changes.log');
$STATE_LOCK_FILE = __DIR__ . '/' . ($CFG['files']['lock_file'] ?? 'temp/fuvar_state.lock');

$changeLogCfg = isset($CFG['change_log']) && is_array($CFG['change_log']) ? $CFG['change_log'] : [];
$retentionSeconds = null;
if (array_key_exists('retention_seconds', $changeLogCfg) && is_numeric($changeLogCfg['retention_seconds'])) {
  $retentionSeconds = (int)$changeLogCfg['retention_seconds'];
} elseif (array_key_exists('retention_hours', $changeLogCfg) && is_numeric($changeLogCfg['retention_hours'])) {
  $hours = (float)$changeLogCfg['retention_hours'];
  $retentionSeconds = (int)round($hours * 3600);
} elseif (array_key_exists('retention_days', $changeLogCfg) && is_numeric($changeLogCfg['retention_days'])) {
  $days = (float)$changeLogCfg['retention_days'];
  $retentionSeconds = (int)round($days * 86400);
}
if ($retentionSeconds === null || $retentionSeconds <= 0) {
  $retentionSeconds = 7 * 86400;
}
$CHANGE_LOG_RETENTION_SECONDS = max(0, (int)$retentionSeconds);

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
  $dbStart = microtime(true);
  $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM items')->fetchColumn();
  app_perf_track_db($dbStart);
  if ($count > 0) {
    return false;
  }
  $dbStart = microtime(true);
  $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM round_meta')->fetchColumn();
  app_perf_track_db($dbStart);
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
  $dbStart = microtime(true);
  $pdo = new PDO('sqlite:' . $file);
  app_perf_track_db($dbStart);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
  $dbStart = microtime(true);
  $pdo->exec('PRAGMA foreign_keys = ON');
  app_perf_track_db($dbStart);
  $dbStart = microtime(true);
  $pdo->exec('PRAGMA journal_mode = WAL');
  app_perf_track_db($dbStart);
  $dbStart = microtime(true);
  $pdo->exec('PRAGMA synchronous = NORMAL');
  app_perf_track_db($dbStart);
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
    $dbStart = microtime(true);
    $stmt = $pdo->query('SELECT data FROM items ORDER BY position ASC, id ASC');
    app_perf_track_db($dbStart);
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
    $dbStart = microtime(true);
    $stmt = $pdo->query('SELECT round_id, data FROM round_meta');
    app_perf_track_db($dbStart);
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

  $dbStart = microtime(true);
  $pdo->beginTransaction();
  app_perf_track_db($dbStart);
  $dbStart = microtime(true);
  $pdo->exec('DELETE FROM items');
  app_perf_track_db($dbStart);
  $dbStart = microtime(true);
  $pdo->exec('DELETE FROM round_meta');
  app_perf_track_db($dbStart);

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
      $dbStart = microtime(true);
      $insertItem->execute([
        ':id' => $id,
        ':position' => (int)$position,
        ':data' => $json
      ]);
      app_perf_track_db($dbStart);
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
      $dbStart = microtime(true);
      $insertMeta->execute([
        ':round_id' => $roundKey,
        ':data' => $json
      ]);
      app_perf_track_db($dbStart);
    }
  }

  $dbStart = microtime(true);
  $pdo->commit();
  app_perf_track_db($dbStart);
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
  global $DATA_FILE, $CHANGE_LOG_FILE, $CHANGE_LOG_RETENTION_SECONDS;

  if (!isset($entry['ts']) || !is_string($entry['ts']) || trim($entry['ts']) === '') {
    $entry['ts'] = gmdate('c');
  }

  $tsIso = (string)$entry['ts'];
  $rev = isset($entry['rev']) && is_numeric($entry['rev']) ? (int)$entry['rev'] : null;
  $entity = isset($entry['entity']) ? (string)$entry['entity'] : 'dataset';
  $entityId = isset($entry['entity_id']) ? (string)$entry['entity_id'] : null;
  $action = isset($entry['action']) ? (string)$entry['action'] : 'updated';
  $actorId = isset($entry['actor_id']) ? (string)$entry['actor_id'] : null;
  $requestId = isset($entry['request_id']) ? (string)$entry['request_id'] : null;
  $batchId = isset($entry['batch_id']) ? (string)$entry['batch_id'] : null;
  $userId = isset($entry['user_id']) && is_numeric($entry['user_id']) ? (int)$entry['user_id'] : null;
  $username = isset($entry['username']) ? (string)$entry['username'] : null;
  $metaPayload = isset($entry['meta']) ? $entry['meta'] : null;
  $metaJson = $metaPayload !== null
    ? json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : null;

  if (data_store_is_sqlite($DATA_FILE)) {
    [$pdo] = data_store_sqlite_open($DATA_FILE);
    $dbStart = microtime(true);
    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare('INSERT INTO change_log (
        rev, entity, entity_id, action, actor_id, request_id, batch_id,
        user_id, username, meta, ts, created_at
      ) VALUES (
        :rev, :entity, :entity_id, :action, :actor_id, :request_id, :batch_id,
        :user_id, :username, :meta, :ts, :created_at
      )');
      $stmt->execute([
        ':rev' => $rev,
        ':entity' => $entity,
        ':entity_id' => $entityId,
        ':action' => $action,
        ':actor_id' => $actorId,
        ':request_id' => $requestId,
        ':batch_id' => $batchId,
        ':user_id' => $userId,
        ':username' => $username,
        ':meta' => $metaJson,
        ':ts' => $tsIso,
        ':created_at' => $tsIso,
      ]);
      if ($CHANGE_LOG_RETENTION_SECONDS > 0) {
        $cutoffIso = gmdate('c', time() - $CHANGE_LOG_RETENTION_SECONDS);
        $cleanup = $pdo->prepare('DELETE FROM change_log WHERE ts < :cutoff');
        $cleanup->execute([':cutoff' => $cutoffIso]);
      }
      $pdo->commit();
      app_perf_track_db($dbStart);
    } catch (Throwable $e) {
      $pdo->rollBack();
      app_perf_track_db($dbStart);
      throw $e;
    }
    return;
  }

  // Fallback to file-based logging if SQLite is not used.
  $maxAgeSeconds = max(0, (int)$CHANGE_LOG_RETENTION_SECONDS);
  $cutoff = $maxAgeSeconds > 0 ? time() - $maxAgeSeconds : null;
  $newLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
      if ($line === '') {
        continue;
      }
      $keep = true;
      if ($cutoff !== null) {
        $decoded = json_decode($line, true);
        if (is_array($decoded) && isset($decoded['ts'])) {
          $ts = strtotime((string)$decoded['ts']);
          if ($ts !== false && $ts < $cutoff) {
            $keep = false;
          }
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
  global $DATA_FILE, $CHANGE_LOG_FILE;

  if (data_store_is_sqlite($DATA_FILE)) {
    [$pdo] = data_store_sqlite_open($DATA_FILE);
    $dbStart = microtime(true);
    $stmt = $pdo->query('SELECT id, rev, entity, entity_id, action, actor_id, request_id, batch_id, user_id, username, meta, ts FROM change_log ORDER BY id ASC');
    app_perf_track_db($dbStart);
    if (!$stmt) {
      return [];
    }
    $entries = [];
    foreach ($stmt as $row) {
      if (!is_array($row)) {
        continue;
      }
      $metaRaw = $row['meta'] ?? null;
      $meta = null;
      if (is_string($metaRaw) && $metaRaw !== '') {
        $decodedMeta = json_decode($metaRaw, true);
        if (is_array($decodedMeta)) {
          $meta = $decodedMeta;
        }
      } elseif (is_array($metaRaw)) {
        $meta = $metaRaw;
      }
      $entry = [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'rev' => isset($row['rev']) ? (int)$row['rev'] : null,
        'entity' => isset($row['entity']) ? (string)$row['entity'] : null,
        'entity_id' => isset($row['entity_id']) ? (string)$row['entity_id'] : null,
        'action' => isset($row['action']) ? (string)$row['action'] : null,
        'actor_id' => isset($row['actor_id']) ? (string)$row['actor_id'] : null,
        'request_id' => isset($row['request_id']) ? (string)$row['request_id'] : null,
        'batch_id' => isset($row['batch_id']) ? (string)$row['batch_id'] : null,
        'user_id' => isset($row['user_id']) && $row['user_id'] !== null ? (int)$row['user_id'] : null,
        'username' => isset($row['username']) ? (string)$row['username'] : null,
        'ts' => isset($row['ts']) ? (string)$row['ts'] : null,
      ];
      if ($meta !== null) {
        $entry['meta'] = $meta;
      }
      $entries[] = $entry;
    }
    return $entries;
  }

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

if (!function_exists('app_session_start')) {
  function app_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
      return;
    }
    session_name(APP_SESSION_NAME);
    session_cache_limiter('');
    $cookieParams = [
      'lifetime' => 0,
      'path' => app_cookie_path(),
      'secure' => app_is_https(),
      'httponly' => true,
      'samesite' => 'Strict',
    ];
    session_set_cookie_params($cookieParams);
    session_start();
  }
}

if (!function_exists('app_session_close')) {
  function app_session_close() {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }
  }
}

if (!function_exists('auth_session_snapshot')) {
  function auth_session_snapshot() {
    app_session_start();
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $mustChange = !empty($_SESSION['must_change_password']);
    app_session_close();
    return [
      'user_id' => $userId > 0 ? $userId : null,
      'must_change' => (bool)$mustChange,
    ];
  }
}

if (!function_exists('auth_store_session')) {
  function auth_store_session($userId, $mustChangePassword) {
    app_session_start();
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['must_change_password'] = $mustChangePassword ? 1 : 0;
    app_session_close();
  }
}

if (!function_exists('auth_clear_session')) {
  function auth_clear_session() {
    app_session_start();
    $_SESSION = [];
    if (session_id() !== '') {
      setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => app_cookie_path(),
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
      ]);
    }
    session_destroy();
    app_session_close();
  }
}

if (!function_exists('auth_set_session_must_change')) {
  function auth_set_session_must_change($mustChange) {
    app_session_start();
    if (isset($_SESSION['user_id'])) {
      $_SESSION['must_change_password'] = $mustChange ? 1 : 0;
    }
    app_session_close();
  }
}

if (!function_exists('csrf_token_from_request')) {
  function csrf_token_from_request() {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (is_string($header) && $header !== '') {
      return $header;
    }
    $postToken = $_POST['csrf_token'] ?? null;
    if (is_array($postToken)) {
      $postToken = reset($postToken);
    }
    if (is_string($postToken) && $postToken !== '') {
      return $postToken;
    }
    return null;
  }
}

if (!function_exists('csrf_get_token')) {
  function csrf_get_token() {
    $name = APP_CSRF_COOKIE;
    $token = isset($_COOKIE[$name]) ? (string)$_COOKIE[$name] : '';
    if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{32,}$/', $token)) {
      return $token;
    }
    $bytes = random_bytes(32);
    $token = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    setcookie($name, $token, [
      'expires' => time() + 86400 * 30,
      'path' => app_cookie_path(),
      'secure' => app_is_https(),
      'httponly' => false,
      'samesite' => 'Strict',
    ]);
    $_COOKIE[$name] = $token;
    return $token;
  }
}

if (!function_exists('csrf_validate')) {
  function csrf_validate($token) {
    $cookie = isset($_COOKIE[APP_CSRF_COOKIE]) ? (string)$_COOKIE[APP_CSRF_COOKIE] : '';
    if ($cookie === '' || !is_string($token) || $token === '') {
      return false;
    }
    return hash_equals($cookie, $token);
  }
}

if (!function_exists('csrf_require_token_from_request')) {
  function csrf_require_token_from_request($responseType = 'html') {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
      return true;
    }
    $token = csrf_token_from_request();
    if ($token !== null && csrf_validate($token)) {
      return true;
    }
    if ($responseType === 'json') {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(419);
      echo json_encode(['ok' => false, 'error' => 'invalid_csrf'], JSON_UNESCAPED_UNICODE);
    } else {
      header('Content-Type: text/html; charset=utf-8');
      http_response_code(400);
      echo '<h1>Érvénytelen kérés</h1><p>Biztonsági ellenőrzés sikertelen (CSRF).</p>';
    }
    exit;
  }
}

if (!function_exists('auth_db')) {
  function auth_db() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
      return $pdo;
    }
    global $DATA_FILE;
    [$pdo] = data_store_sqlite_open($DATA_FILE);
    return $pdo;
  }
}

if (!function_exists('auth_password_hash')) {
  function auth_password_hash($password) {
    if (defined('PASSWORD_ARGON2ID')) {
      return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_DEFAULT);
  }
}

if (!function_exists('auth_verify_password')) {
  function auth_verify_password($password, $hash) {
    if (!is_string($hash) || $hash === '') {
      return false;
    }
    return password_verify($password, $hash);
  }
}

if (!function_exists('auth_find_user_by_id')) {
  function auth_find_user_by_id($id) {
    $userId = (int)$id;
    if ($userId <= 0) {
      return null;
    }
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('SELECT id, username, email, role, password_hash, must_change_password, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
      $dbStart = microtime(true);
      $stmt->execute([':id' => $userId]);
      app_perf_track_db($dbStart);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!is_array($row)) {
        return null;
      }
      $row['must_change_password'] = !empty($row['must_change_password']);
      $row['role'] = isset($row['role']) && is_string($row['role']) && $row['role'] !== '' ? $row['role'] : 'editor';
      return $row;
    } catch (Throwable $e) {
      error_log('Felhasználó lekérdezési hiba: ' . $e->getMessage());
      return null;
    }
  }
}

if (!function_exists('auth_find_user_by_username')) {
  function auth_find_user_by_username($username) {
    $name = trim((string)$username);
    if ($name === '') {
      return null;
    }
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('SELECT id, username, email, role, password_hash, must_change_password, created_at, updated_at FROM users WHERE username = :username LIMIT 1');
      $dbStart = microtime(true);
      $stmt->execute([':username' => $name]);
      app_perf_track_db($dbStart);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!is_array($row)) {
        return null;
      }
      $row['must_change_password'] = !empty($row['must_change_password']);
      $row['role'] = isset($row['role']) && is_string($row['role']) && $row['role'] !== '' ? $row['role'] : 'editor';
      return $row;
    } catch (Throwable $e) {
      error_log('Felhasználó keresési hiba: ' . $e->getMessage());
      return null;
    }
  }
}

if (!function_exists('auth_update_user_password')) {
  function auth_update_user_password($userId, $passwordHash, $forceChange = false) {
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = :must_change, updated_at = :updated_at WHERE id = :id');
      $dbStart = microtime(true);
      $stmt->execute([
        ':hash' => $passwordHash,
        ':must_change' => $forceChange ? 1 : 0,
        ':updated_at' => gmdate('c'),
        ':id' => (int)$userId,
      ]);
      app_perf_track_db($dbStart);
      return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
      error_log('Jelszó frissítési hiba: ' . $e->getMessage());
      return false;
    }
  }
}

if (!function_exists('auth_valid_roles')) {
  function auth_valid_roles() {
    return ['full-admin', 'editor', 'viewer'];
  }
}

if (!function_exists('auth_normalize_role')) {
  function auth_normalize_role($value) {
    $role = is_string($value) ? strtolower(trim($value)) : '';
    $valid = auth_valid_roles();
    foreach ($valid as $candidate) {
      if ($role === strtolower($candidate)) {
        return $candidate;
      }
    }
    return 'editor';
  }
}

if (!function_exists('auth_user_role')) {
  function auth_user_role($user = null) {
    if (!is_array($user)) {
      return 'editor';
    }
    $role = $user['role'] ?? 'editor';
    return auth_normalize_role($role);
  }
}

if (!function_exists('auth_user_is_admin')) {
  function auth_user_is_admin($user = null) {
    return auth_user_role($user) === 'full-admin';
  }
}

if (!function_exists('auth_user_can')) {
  function auth_user_can($user, $capability) {
    $role = auth_user_role($user);
    $cap = is_string($capability) ? strtolower(trim($capability)) : '';
    $map = [
      'view' => ['full-admin', 'editor', 'viewer'],
      'export' => ['full-admin', 'editor', 'viewer'],
      'print' => ['full-admin', 'editor', 'viewer'],
      'edit' => ['full-admin', 'editor'],
      'save' => ['full-admin', 'editor'],
      'sort' => ['full-admin', 'editor'],
      'round_meta' => ['full-admin', 'editor'],
      'delete' => ['full-admin', 'editor'],
      'import' => ['full-admin', 'editor'],
      'manage_users' => ['full-admin'],
    ];
    if (!isset($map[$cap])) {
      return false;
    }
    return in_array($role, $map[$cap], true);
  }
}

if (!function_exists('auth_build_permissions')) {
  function auth_build_permissions($user = null) {
    $role = auth_user_role($user);
    return [
      'role' => $role,
      'readOnly' => $role === 'viewer',
      'canEdit' => auth_user_can($user, 'edit'),
      'canSave' => auth_user_can($user, 'save'),
      'canSort' => auth_user_can($user, 'sort'),
      'canChangeRoundMeta' => auth_user_can($user, 'round_meta'),
      'canDelete' => auth_user_can($user, 'delete'),
      'canImport' => auth_user_can($user, 'import'),
      'canExport' => auth_user_can($user, 'export'),
      'canPrint' => auth_user_can($user, 'print'),
      'canManageUsers' => auth_user_can($user, 'manage_users'),
    ];
  }
}

if (!function_exists('app_features_for_user')) {
  function app_features_for_user($features, array $permissions) {
    $result = is_array($features) ? $features : [];
    $toolbar = isset($result['toolbar']) && is_array($result['toolbar']) ? $result['toolbar'] : [];
    $groupActions = isset($result['group_actions']) && is_array($result['group_actions']) ? $result['group_actions'] : [];

    if (empty($permissions['canImport'])) {
      $toolbar['import_all'] = false;
    }
    if (empty($permissions['canExport'])) {
      $toolbar['export_all'] = false;
      $groupActions['export'] = false;
    }
    if (empty($permissions['canPrint'])) {
      $toolbar['print_all'] = false;
      $groupActions['print'] = false;
    }
    if (empty($permissions['canEdit'])) {
      $toolbar['undo'] = false;
      $groupActions['delete'] = false;
      $toolbar['expand_all'] = $toolbar['expand_all'] ?? true;
      $toolbar['collapse_all'] = $toolbar['collapse_all'] ?? true;
    }
    if (!empty($permissions['readOnly'])) {
      $toolbar['import_all'] = false;
    }

    $result['toolbar'] = $toolbar;
    $result['group_actions'] = $groupActions;
    return $result;
  }
}

if (!function_exists('auth_find_user_by_email')) {
  function auth_find_user_by_email($email) {
    $emailStr = trim((string)$email);
    if ($emailStr === '') {
      return null;
    }
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('SELECT id, username, email, role, password_hash, must_change_password, created_at, updated_at FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
      $dbStart = microtime(true);
      $stmt->execute([':email' => $emailStr]);
      app_perf_track_db($dbStart);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        return null;
      }
      $row['must_change_password'] = !empty($row['must_change_password']);
      $row['role'] = isset($row['role']) && is_string($row['role']) && $row['role'] !== '' ? $row['role'] : 'editor';
      return $row;
    } catch (Throwable $e) {
      error_log('Felhasználó lekérdezési hiba (email): ' . $e->getMessage());
      return null;
    }
  }
}

if (!function_exists('auth_find_user_by_identifier')) {
  function auth_find_user_by_identifier($identifier) {
    $id = trim((string)$identifier);
    if ($id === '') {
      return null;
    }
    $user = auth_find_user_by_username($id);
    if ($user) {
      return $user;
    }
    return auth_find_user_by_email($id);
  }
}

if (!function_exists('auth_list_users')) {
  function auth_list_users() {
    try {
      $pdo = auth_db();
      $stmt = $pdo->query('SELECT id, username, email, role, must_change_password, created_at, updated_at FROM users ORDER BY LOWER(username) ASC');
      $dbStart = microtime(true);
      $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
      app_perf_track_db($dbStart);
      foreach ($rows as &$row) {
        $row['must_change_password'] = !empty($row['must_change_password']);
        $row['role'] = isset($row['role']) && is_string($row['role']) && $row['role'] !== '' ? $row['role'] : 'editor';
      }
      unset($row);
      return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
      error_log('Felhasználó lista hiba: ' . $e->getMessage());
      return [];
    }
  }
}

if (!function_exists('auth_count_users_with_role')) {
  function auth_count_users_with_role($role) {
    $normalized = auth_normalize_role($role);
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = :role');
      $dbStart = microtime(true);
      $stmt->execute([':role' => $normalized]);
      app_perf_track_db($dbStart);
      return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
      error_log('Szerepkör számolási hiba: ' . $e->getMessage());
      return 0;
    }
  }
}

if (!function_exists('auth_create_user')) {
  function auth_create_user(array $data, &$error = null) {
    $username = trim((string)($data['username'] ?? ''));
    if ($username === '') {
      $error = 'empty_username';
      return false;
    }
    $email = trim((string)($data['email'] ?? ''));
    $role = auth_normalize_role($data['role'] ?? 'editor');
    $password = (string)($data['password'] ?? '');
    if ($password === '') {
      $error = 'empty_password';
      return false;
    }
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
      $dbStart = microtime(true);
      $stmt->execute([':username' => $username]);
      app_perf_track_db($dbStart);
      if ((int)$stmt->fetchColumn() > 0) {
        $error = 'username_taken';
        return false;
      }
      $hash = auth_password_hash($password);
      $now = gmdate('c');
      $ins = $pdo->prepare('INSERT INTO users (username, email, role, password_hash, must_change_password, created_at, updated_at) VALUES (:username, :email, :role, :hash, :must_change, :created, :created)');
      $dbStart = microtime(true);
      $ins->execute([
        ':username' => $username,
        ':email' => $email,
        ':role' => $role,
        ':hash' => $hash,
        ':must_change' => array_key_exists('must_change_password', $data)
          ? (!empty($data['must_change_password']) ? 1 : 0)
          : 1,
        ':created' => $now,
      ]);
      app_perf_track_db($dbStart);
      return true;
    } catch (Throwable $e) {
      $error = 'db_error';
      error_log('Felhasználó létrehozási hiba: ' . $e->getMessage());
      return false;
    }
  }
}

if (!function_exists('auth_update_user')) {
  function auth_update_user($userId, array $data, &$error = null) {
    $id = (int)$userId;
    if ($id <= 0) {
      $error = 'invalid_id';
      return false;
    }
    $fields = [];
    $params = [':id' => $id];
    if (array_key_exists('username', $data)) {
      $username = trim((string)$data['username']);
      if ($username === '') {
        $error = 'empty_username';
        return false;
      }
      try {
        $pdo = auth_db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id != :id');
        $dbStart = microtime(true);
        $stmt->execute([':username' => $username, ':id' => $id]);
        app_perf_track_db($dbStart);
        if ((int)$stmt->fetchColumn() > 0) {
          $error = 'username_taken';
          return false;
        }
      } catch (Throwable $e) {
        $error = 'db_error';
        error_log('Felhasználó frissítési hiba: ' . $e->getMessage());
        return false;
      }
      $fields[] = 'username = :username';
      $params[':username'] = $username;
    }
    if (array_key_exists('email', $data)) {
      $fields[] = 'email = :email';
      $params[':email'] = trim((string)$data['email']);
    }
    if (array_key_exists('role', $data)) {
      $fields[] = 'role = :role';
      $params[':role'] = auth_normalize_role($data['role']);
    }
    $mustChangeProvided = array_key_exists('must_change_password', $data);
    if (!empty($data['password'])) {
      $hash = auth_password_hash((string)$data['password']);
      $fields[] = 'password_hash = :hash';
      $params[':hash'] = $hash;
      $fields[] = 'must_change_password = :must_change_password';
      if (!empty($data['force_change_password'])) {
        $params[':must_change_password'] = 1;
      } elseif ($mustChangeProvided) {
        $params[':must_change_password'] = !empty($data['must_change_password']) ? 1 : 0;
      } else {
        $params[':must_change_password'] = 1;
      }
    } elseif ($mustChangeProvided) {
      $fields[] = 'must_change_password = :must_change_password';
      $params[':must_change_password'] = !empty($data['must_change_password']) ? 1 : 0;
    }
    if (!$fields) {
      return true;
    }
    $fields[] = 'updated_at = :updated_at';
    $params[':updated_at'] = gmdate('c');
    try {
      $pdo = auth_db();
      $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
      $stmt = $pdo->prepare($sql);
      $dbStart = microtime(true);
      $stmt->execute($params);
      app_perf_track_db($dbStart);
      return true;
    } catch (Throwable $e) {
      $error = 'db_error';
      error_log('Felhasználó frissítési hiba: ' . $e->getMessage());
      return false;
    }
  }
}

if (!function_exists('auth_delete_user')) {
  function auth_delete_user($userId, &$error = null) {
    $id = (int)$userId;
    if ($id <= 0) {
      $error = 'invalid_id';
      return false;
    }
    try {
      $pdo = auth_db();
      $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
      $dbStart = microtime(true);
      $stmt->execute([':id' => $id]);
      app_perf_track_db($dbStart);
      return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
      $error = 'db_error';
      error_log('Felhasználó törlési hiba: ' . $e->getMessage());
      return false;
    }
  }
}

if (!function_exists('auth_generate_random_password')) {
  function auth_generate_random_password($length = 12) {
    $length = max(8, (int)$length);
    $bytes = random_bytes($length);
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $alphabetLength = strlen($alphabet);
    $result = '';
    for ($i = 0; $i < $length; $i++) {
      $result .= $alphabet[ord($bytes[$i]) % $alphabetLength];
    }
    return $result;
  }
}

if (!function_exists('app_format_email_address')) {
  function app_format_email_address($email, $name = '') {
    $email = trim((string)$email);
    $name = trim((string)$name);
    if ($name === '') {
      return $email;
    }
    $encodedName = $name;
    if (function_exists('mb_encode_mimeheader')) {
      $encodedName = mb_encode_mimeheader($name, 'UTF-8', 'B', '\r\n');
    }
    return sprintf('%s <%s>', $encodedName, $email);
  }
}

if (!function_exists('app_smtp_send_mail')) {
  function app_smtp_send_mail(array $smtpCfg, array $message, &$error = null) {
    $host = trim((string)($smtpCfg['host'] ?? ''));
    if ($host === '') {
      $error = 'missing_host';
      return false;
    }
    $port = isset($smtpCfg['port']) ? (int)$smtpCfg['port'] : 587;
    if ($port <= 0) {
      $port = 587;
    }
    $username = (string)($smtpCfg['username'] ?? '');
    $password = (string)($smtpCfg['password'] ?? '');
    $encryption = strtolower((string)($smtpCfg['encryption'] ?? 'tls'));
    $timeout = isset($smtpCfg['timeout']) ? (int)$smtpCfg['timeout'] : 15;
    if ($timeout <= 0) {
      $timeout = 15;
    }
    $fromEmail = trim((string)($message['from_email'] ?? ($smtpCfg['from_email'] ?? $username)));
    if ($fromEmail === '') {
      $error = 'missing_from_email';
      return false;
    }
    $fromName = trim((string)($message['from_name'] ?? ($smtpCfg['from_name'] ?? '')));
    $toEmail = trim((string)($message['to_email'] ?? ''));
    if ($toEmail === '') {
      $error = 'missing_recipient';
      return false;
    }
    $toName = trim((string)($message['to_name'] ?? ''));
    $subject = (string)($message['subject'] ?? '');
    $body = (string)($message['body'] ?? '');
    $ehloDomain = $message['ehlo_domain'] ?? 'localhost';
    $transport = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
      $error = 'connect_failed: ' . $errstr;
      return false;
    }
    stream_set_timeout($socket, $timeout);

    $readResponse = function () use ($socket) {
      $response = '';
      while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
          break;
        }
      }
      return $response;
    };

    $sendCommand = function ($command) use ($socket) {
      if ($command !== null) {
        fwrite($socket, $command . "\r\n");
      }
    };

    $expect = function ($command, $expectedCode) use ($sendCommand, $readResponse, &$error) {
      if ($command !== null) {
        $sendCommand($command);
      }
      $resp = $readResponse();
      if ($resp === '' || strpos($resp, (string)$expectedCode) !== 0) {
        $error = 'smtp_error: ' . trim($resp);
        return false;
      }
      return $resp;
    };

    $greeting = $readResponse();
    if ($greeting === '' || strpos($greeting, '220') !== 0) {
      $error = 'smtp_greeting_failed';
      fclose($socket);
      return false;
    }

    if ($expect('EHLO ' . $ehloDomain, 250) === false) {
      fclose($socket);
      return false;
    }

    if ($encryption === 'tls') {
      if ($expect('STARTTLS', 220) === false) {
        fclose($socket);
        return false;
      }
      if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        $error = 'tls_negotiation_failed';
        fclose($socket);
        return false;
      }
      if ($expect('EHLO ' . $ehloDomain, 250) === false) {
        fclose($socket);
        return false;
      }
    }

    if ($username !== '' && $password !== '') {
      if ($expect('AUTH LOGIN', 334) === false) {
        fclose($socket);
        return false;
      }
      if ($expect(base64_encode($username), 334) === false) {
        fclose($socket);
        return false;
      }
      if ($expect(base64_encode($password), 235) === false) {
        fclose($socket);
        return false;
      }
    }

    if ($expect('MAIL FROM:<' . $fromEmail . '>', 250) === false) {
      fclose($socket);
      return false;
    }
    if ($expect('RCPT TO:<' . $toEmail . '>', 250) === false) {
      fclose($socket);
      return false;
    }
    if ($expect('DATA', 354) === false) {
      fclose($socket);
      return false;
    }

    $encodedSubject = $subject;
    if ($subject !== '' && function_exists('mb_encode_mimeheader')) {
      $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    $headers = [
      'Date: ' . gmdate('D, d M Y H:i:s O'),
      'From: ' . app_format_email_address($fromEmail, $fromName),
      'To: ' . app_format_email_address($toEmail, $toName),
      'Subject: ' . $encodedSubject,
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: 8bit'
    ];

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $normalizedBody);
    foreach ($lines as &$line) {
      if (isset($line[0]) && $line[0] === '.') {
        $line = '.' . $line;
      }
    }
    unset($line);
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $lines) . "\r\n.";
    $sendCommand($payload);

    $resp = $readResponse();
    if ($resp === '' || strpos($resp, '250') !== 0) {
      $error = 'smtp_data_rejected: ' . trim($resp);
      fclose($socket);
      return false;
    }

    $sendCommand('QUIT');
    fclose($socket);
    return true;
  }
}

if (!function_exists('auth_ensure_admin_user')) {
  function auth_ensure_admin_user() {
    static $ensured = false;
    if ($ensured) {
      return;
    }
    $ensured = true;
    try {
      $pdo = auth_db();
      $dbStart = microtime(true);
      $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'");
      app_perf_track_db($dbStart);
      $exists = $stmt ? (int)$stmt->fetchColumn() : 0;
      if ($exists === 0) {
        return;
      }
      $dbStart = microtime(true);
      $countStmt = $pdo->query('SELECT COUNT(*) FROM users');
      app_perf_track_db($dbStart);
      $count = $countStmt ? (int)$countStmt->fetchColumn() : 0;
      if ($count === 0) {
        $hash = auth_password_hash('admin');
        $now = gmdate('c');
        $stmt = $pdo->prepare('INSERT INTO users (username, email, role, password_hash, must_change_password, created_at, updated_at) VALUES (:username, :email, :role, :hash, 1, :created, :created)');
        $dbStart = microtime(true);
        $stmt->execute([
          ':username' => 'admin',
          ':email' => '',
          ':role' => 'full-admin',
          ':hash' => $hash,
          ':created' => $now,
        ]);
        app_perf_track_db($dbStart);
      }
    } catch (Throwable $e) {
      error_log('Admin felhasználó inicializálása nem sikerült: ' . $e->getMessage());
    }
  }
}

if (!function_exists('auth_normalize_return_to')) {
  function auth_normalize_return_to($value = null) {
    $candidate = is_string($value) && $value !== '' ? $value : (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($candidate === '') {
      return app_url_path('');
    }
    $candidate = trim($candidate);
    if (preg_match('~^https?://~i', $candidate)) {
      return app_url_path('');
    }
    if ($candidate[0] !== '/') {
      $candidate = '/' . ltrim($candidate, '/');
    }
    $base = app_base_path();
    if ($base !== '/' && strpos($candidate, $base) !== 0) {
      return app_url_path('');
    }
    return $candidate;
  }
}

if (!function_exists('auth_redirect_to_login')) {
  function auth_redirect_to_login($returnTo = null) {
    $target = app_url_path('login.php');
    $returnPath = $returnTo ? auth_normalize_return_to($returnTo) : null;
    if ($returnPath && $returnPath !== $target) {
      $target .= (strpos($target, '?') === false ? '?' : '&') . 'return_to=' . rawurlencode($returnPath);
    }
    header('Location: ' . $target);
    exit;
  }
}

if (!function_exists('auth_redirect_to_password_change')) {
  function auth_redirect_to_password_change($returnTo = null) {
    $target = app_url_path('password.php');
    $returnPath = $returnTo ? auth_normalize_return_to($returnTo) : null;
    if ($returnPath && $returnPath !== $target) {
      $target .= (strpos($target, '?') === false ? '?' : '&') . 'return_to=' . rawurlencode($returnPath);
    }
    header('Location: ' . $target);
    exit;
  }
}

if (!function_exists('auth_get_current_user')) {
  function auth_get_current_user() {
    static $cacheInitialized = false;
    static $cachedUser = null;
    if ($cacheInitialized) {
      return $cachedUser;
    }
    $cacheInitialized = true;
    auth_ensure_admin_user();
    $snapshot = auth_session_snapshot();
    if (empty($snapshot['user_id'])) {
      $cachedUser = null;
      return null;
    }
    $user = auth_find_user_by_id((int)$snapshot['user_id']);
    if (!$user) {
      auth_clear_session();
      $cachedUser = null;
      return null;
    }
    if (!empty($snapshot['must_change']) && empty($user['must_change_password'])) {
      auth_set_session_must_change(false);
    }
    $cachedUser = $user;
    return $user;
  }
}

if (!function_exists('auth_require_login')) {
  function auth_require_login(array $options = []) {
    $response = isset($options['response']) ? strtolower((string)$options['response']) : 'redirect';
    $allowPasswordChange = !empty($options['allow_password_change']);
    $returnTo = auth_normalize_return_to($options['return_to'] ?? null);
    $user = auth_get_current_user();
    if (!$user) {
      if ($response === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'auth_required'], JSON_UNESCAPED_UNICODE);
        exit;
      }
      auth_redirect_to_login($returnTo);
    }
    if (!empty($user['must_change_password']) && !$allowPasswordChange) {
      if ($response === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'password_change_required'], JSON_UNESCAPED_UNICODE);
        exit;
      }
      auth_redirect_to_password_change($returnTo);
    }
    csrf_get_token();
    return $user;
  }
}

if (!function_exists('auth_bootstrap_users')) {
  function auth_bootstrap_users() {
    auth_ensure_admin_user();
  }
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
auth_bootstrap_users();
