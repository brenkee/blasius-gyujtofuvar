<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();
$CURRENT_URI = $_SERVER['REQUEST_URI'] ?? null;
$CURRENT_REDIRECT = auth_normalize_redirect($CURRENT_URI);
$CHANGE_PASSWORD_URL = auth_build_password_change_url($CURRENT_REDIRECT);
$LOGOUT_URL = base_url('logout.php');
?>
<!doctype html>
<html lang="hu">
<head>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($CFG['app']['title']) ?></title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('favicon.png'), ENT_QUOTES) ?>" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>">
<?php
  $endpointPaths = [
    'cfg' => 'api.php?action=cfg',
    'load' => 'api.php?action=load',
    'save' => 'api.php?action=save',
    'session' => 'api.php?action=session',
    'revision' => 'api.php?action=revision',
    'changes' => 'api.php?action=changes',
    'geocode' => 'api.php?action=geocode',
    'importCsv' => 'api.php?action=import_csv',
    'exportAll' => 'api.php?action=export',
    'deleteRound' => 'api.php?action=delete_round',
    'downloadArchive' => 'api.php?action=download_archive',
    'printAll' => 'print.php',
  ];
  $bootstrapEndpoints = [];
  foreach ($endpointPaths as $key => $path) {
    $bootstrapEndpoints[$key] = base_url($path);
  }
  $printRoundBase = base_url('print.php');
?>
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin></script>
<script>
  window.APP_BOOTSTRAP = <?= json_encode([
    'baseUrl' => $CFG['base_url'],
    'endpoints' => $bootstrapEndpoints,
    'loginUrl' => base_url('login.php'),
    'passwordChangeUrl' => base_url('change-password.php'),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  window.APP_BOOTSTRAP.endpoints.printRound = function(rid){
    const base = <?= json_encode($printRoundBase, JSON_UNESCAPED_SLASHES) ?>;
    const separator = base.indexOf('?') === -1 ? '?' : '&';
    return base + separator + 'round=' + encodeURIComponent(rid);
  };
</script>
<script defer src="<?= htmlspecialchars(base_url('public/app.js'), ENT_QUOTES) ?>"></script>
</head>
<body>
<?php if (!empty($DATA_INIT_ERROR)): ?>
<div class="init-error-banner">
  <strong>Adatbázis inicializációs hiba</strong>
  <p><?= htmlspecialchars($DATA_INIT_ERROR) ?></p>
  <p><code>php scripts/init-db.php</code> futtatásával megpróbálhatod manuálisan létrehozni az adatbázist.</p>
</div>
<?php endif; ?>
<div class="app">
  <aside class="panel">
    <div id="panelTop" class="panel-top">
      <h1><?= htmlspecialchars($CFG['app']['title']) ?></h1>
      <div class="auth-controls">
        <span class="auth-user" title="Bejelentkezett felhasználó">
          👤 <?= htmlspecialchars($CURRENT_USER['username']) ?>
        </span>
        <a class="auth-link" href="<?= htmlspecialchars($CHANGE_PASSWORD_URL, ENT_QUOTES) ?>">Jelszó módosítása</a>
        <a class="auth-link" href="<?= htmlspecialchars($LOGOUT_URL, ENT_QUOTES) ?>">Kilépés</a>
      </div>
      <?php
        $toolbarFeatures = $CFG['features']['toolbar'] ?? [];
        $toolbarText = $CFG['text']['toolbar'] ?? [];
        $badgeText = $CFG['text']['badges']['pin_counter_label'] ?? 'Pin-ek:';
        $badgeTitle = $CFG['text']['badges']['pin_counter_title'] ?? '';
        $toolbarMenuIconCfg = $CFG['ui']['toolbar']['menu_icon'] ?? [];
        $cssValue = static function ($value, string $unit = 'px'): ?string {
          if ($value === null || $value === '') {
            return null;
          }
          return is_numeric($value) ? $value . $unit : $value;
        };
        $menuIconStyles = [];
        if (($width = $cssValue($toolbarMenuIconCfg['width'] ?? null)) !== null) {
          $menuIconStyles[] = '--menu-icon-width:' . $width;
        }
        if (($height = $cssValue($toolbarMenuIconCfg['height'] ?? null)) !== null) {
          $menuIconStyles[] = '--menu-icon-height:' . $height;
        }
        if (($barHeight = $cssValue($toolbarMenuIconCfg['bar_height'] ?? null)) !== null) {
          $menuIconStyles[] = '--menu-icon-bar-height:' . $barHeight;
        }
        if (!empty($toolbarMenuIconCfg['color'])) {
          $menuIconStyles[] = '--menu-icon-color:' . $toolbarMenuIconCfg['color'];
        }
        if (($barRadius = $cssValue($toolbarMenuIconCfg['bar_radius'] ?? null)) !== null) {
          $menuIconStyles[] = '--menu-icon-bar-radius:' . $barRadius;
        }
        $menuIconStyleAttr = $menuIconStyles ? htmlspecialchars(implode(';', $menuIconStyles), ENT_QUOTES) : '';
        $toolbarMenuLabel = $toolbarText['more_actions']['label'] ?? 'Menü';
        $toolbarMenuTitle = $toolbarText['more_actions']['title'] ?? $toolbarMenuLabel;
        $toolbarMenuHasItems = !empty($toolbarFeatures['import_all'])
          || !empty($toolbarFeatures['export_all'])
          || !empty($toolbarFeatures['print_all'])
          || !empty($toolbarFeatures['download_archive'])
          || !empty($toolbarFeatures['theme_toggle']);
      ?>
      <div class="toolbar">
        <?php if (!empty($toolbarFeatures['expand_all'])): ?>
          <button id="expandAll" title="<?= htmlspecialchars($toolbarText['expand_all']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['expand_all']['label'] ?? 'Összes kinyit') ?>
          </button>
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['collapse_all'])): ?>
          <button id="collapseAll" title="<?= htmlspecialchars($toolbarText['collapse_all']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['collapse_all']['label'] ?? 'Összes összezár') ?>
          </button>
        <?php endif; ?>
        <?php if ($toolbarMenuHasItems): ?>
          <div class="toolbar-menu-container">
            <button
              id="toolbarMenuToggle"
              type="button"
              class="toolbar-menu-toggle"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="toolbarMenu"
              title="<?= htmlspecialchars($toolbarMenuTitle) ?>"
              aria-label="<?= htmlspecialchars($toolbarMenuLabel) ?>"
              <?= $menuIconStyleAttr !== '' ? 'style="' . $menuIconStyleAttr . '"' : '' ?>
            >
              <span class="hamburger-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
              </span>
              <span class="visually-hidden"><?= htmlspecialchars($toolbarMenuLabel) ?></span>
            </button>
            <div id="toolbarMenu" class="toolbar-menu" hidden>
              <?php if (!empty($toolbarFeatures['import_all'])): ?>
                <button id="importBtn" type="button" title="<?= htmlspecialchars($toolbarText['import_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['import_all']['label'] ?? 'Import (CSV)') ?>
                </button>
                <input type="file" id="importFileInput" accept=".csv,text/csv" style="display:none" />
              <?php endif; ?>
              <?php if (!empty($toolbarFeatures['export_all'])): ?>
                <button id="exportBtn" type="button" title="<?= htmlspecialchars($toolbarText['export_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['export_all']['label'] ?? 'Export') ?>
                </button>
              <?php endif; ?>
              <?php if (!empty($toolbarFeatures['print_all'])): ?>
                <button id="printBtn" type="button" title="<?= htmlspecialchars($toolbarText['print_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['print_all']['label'] ?? 'Nyomtatás') ?>
                </button>
              <?php endif; ?>
              <?php if (!empty($toolbarFeatures['download_archive'])): ?>
                <button id="downloadArchiveBtn" type="button" title="<?= htmlspecialchars($toolbarText['download_archive']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['download_archive']['label'] ?? 'Archívum letöltése') ?>
                </button>
              <?php endif; ?>
              <?php if (!empty($toolbarFeatures['theme_toggle'])): ?>
                <button id="themeToggle" type="button" title="<?= htmlspecialchars($toolbarText['theme_toggle']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['theme_toggle']['label'] ?? '🌙 / ☀️') ?>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['undo']) && !empty($CFG['history']['undo_enabled'])): ?>
          <button id="undoBtn" title="<?= htmlspecialchars($toolbarText['undo']['title'] ?? '') ?>" disabled>
            <?= htmlspecialchars($toolbarText['undo']['label'] ?? 'Visszavonás') ?>
          </button>
        <?php endif; ?>
        <span title="<?= htmlspecialchars($badgeTitle) ?>">
          <?= htmlspecialchars($badgeText) ?> <span id="pinCount" class="badge">0</span>
        </span>
      </div>
    </div>
    <div id="newAddress" class="new-address-container"></div>
    <div id="groups" class="groups"></div>
  </aside>
  <main id="map"></main>
</div>
</body>
</html>
