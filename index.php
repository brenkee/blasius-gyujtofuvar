<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();
$PERMISSIONS = auth_build_permissions($CURRENT_USER);
$FEATURES = app_features_for_user($CFG['features'] ?? [], $PERMISSIONS);
$LOGOUT_TOKEN = csrf_get_token();
$appTitle = $CFG['app']['title'] ?? '';
$resolveImageConfig = static function ($value, string $defaultAlt = '') {
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = ['src' => $value];
    }
    if (!is_array($value)) {
        return null;
    }
    $rawSrc = trim((string)($value['src'] ?? ''));
    if ($rawSrc === '') {
        return null;
    }
    $formatCssLength = static function ($input) {
        if ($input === null || $input === '') {
            return null;
        }
        return is_numeric($input) ? ((string)$input . 'px') : (string)$input;
    };
    $attrs = '';
    foreach (['width', 'height'] as $dimension) {
        $dimVal = $value[$dimension] ?? null;
        if ($dimVal !== null && $dimVal !== '') {
            $attrs .= ' ' . $dimension . '="' . htmlspecialchars((string)$dimVal, ENT_QUOTES) . '"';
        }
    }
    $styleParts = [];
    foreach ([
        'max_width' => 'max-width',
        'max_height' => 'max-height',
    ] as $configKey => $cssProperty) {
        $cssValue = $formatCssLength($value[$configKey] ?? null);
        if ($cssValue !== null && $cssValue !== '') {
            $styleParts[] = $cssProperty . ':' . $cssValue;
        }
    }
    $styleAttr = $styleParts ? ' style="' . htmlspecialchars(implode(';', $styleParts), ENT_QUOTES) . '"' : '';
    return [
        'src' => htmlspecialchars(base_url($rawSrc), ENT_QUOTES),
        'alt' => htmlspecialchars((string)($value['alt'] ?? $defaultAlt), ENT_QUOTES),
        'attrs' => $attrs,
        'style' => $styleAttr,
    ];
};
$panelTitleImage = $resolveImageConfig($CFG['app']['panel_title']['image'] ?? null, $appTitle);
$logoImage = $resolveImageConfig($CFG['app']['logo'] ?? null, $appTitle);
?>
<!doctype html>
<html lang="hu">
<head>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<title><?= htmlspecialchars($CFG['app']['title']) ?></title>
<link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
<link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
<link rel="shortcut icon" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
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
    'auditLog' => 'log.php',
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
    'permissions' => $PERMISSIONS,
    'features' => $FEATURES,
    'version' => APP_VERSION,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  window.APP_BOOTSTRAP.endpoints.printRound = function(rid){
    const base = <?= json_encode($printRoundBase, JSON_UNESCAPED_SLASHES) ?>;
    const separator = base.indexOf('?') === -1 ? '?' : '&';
    return base + separator + 'round=' + encodeURIComponent(rid);
  };
</script>
<script defer src="<?= htmlspecialchars(base_url('public/app.js'), ENT_QUOTES) ?>"></script>
</head>
<body<?= !empty($PERMISSIONS['readOnly']) ? ' class="app-readonly"' : '' ?>>
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
      <?php
        $toolbarFeatures = $FEATURES['toolbar'] ?? [];
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
        $hasImportAll = !empty($toolbarFeatures['import_all']);
        $hasExportAll = !empty($toolbarFeatures['export_all']);
        $hasPrintAll = !empty($toolbarFeatures['print_all']);
        $hasAuditLog = !empty($toolbarFeatures['audit_log']);
        $hasThemeToggle = !empty($toolbarFeatures['theme_toggle']);
        $hasAdminMenuItem = !empty($PERMISSIONS['canManageUsers']);
        $hasUtilityMenuItems = $hasImportAll || $hasExportAll || $hasPrintAll || $hasAuditLog || $hasThemeToggle;
        $hasUserMenuItems = true;
        $toolbarMenuHasItems = $hasUtilityMenuItems || $hasAdminMenuItem || $hasUserMenuItems;

        $toolbarItems = [];
        if (!empty($toolbarFeatures['expand_all'])) {
          $toolbarItems['expand_all'] = '<button id="expandAll" title="' . htmlspecialchars($toolbarText['expand_all']['title'] ?? '', ENT_QUOTES) . '">' . htmlspecialchars($toolbarText['expand_all']['label'] ?? 'Összes kinyit', ENT_QUOTES) . '</button>';
        }
        if (!empty($toolbarFeatures['collapse_all'])) {
          $toolbarItems['collapse_all'] = '<button id="collapseAll" title="' . htmlspecialchars($toolbarText['collapse_all']['title'] ?? '', ENT_QUOTES) . '">' . htmlspecialchars($toolbarText['collapse_all']['label'] ?? 'Összes összezár', ENT_QUOTES) . '</button>';
        }
        if ($toolbarMenuHasItems) {
          ob_start();
          ?>
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
              <?php $menuSectionRendered = false; ?>
              <?php if ($hasImportAll): ?>
                <button id="importBtn" type="button" title="<?= htmlspecialchars($toolbarText['import_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['import_all']['label'] ?? 'Import (CSV)') ?>
                </button>
                <input type="file" id="importFileInput" accept=".csv,text/csv" style="display:none" />
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasExportAll): ?>
                <button id="exportBtn" type="button" title="<?= htmlspecialchars($toolbarText['export_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['export_all']['label'] ?? 'Export') ?>
                </button>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasPrintAll): ?>
                <button id="printBtn" type="button" title="<?= htmlspecialchars($toolbarText['print_all']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['print_all']['label'] ?? 'Nyomtatás') ?>
                </button>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasAuditLog): ?>
                <button id="auditLogBtn" type="button" title="<?= htmlspecialchars($toolbarText['audit_log']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['audit_log']['label'] ?? 'Napló') ?>
                </button>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasThemeToggle): ?>
                <button id="themeToggle" type="button" title="<?= htmlspecialchars($toolbarText['theme_toggle']['title'] ?? '') ?>">
                  <?= htmlspecialchars($toolbarText['theme_toggle']['label'] ?? '🌙 / ☀️') ?>
                </button>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasAdminMenuItem): ?>
                <?php if ($menuSectionRendered): ?>
                  <hr class="toolbar-menu-separator" role="presentation" />
                <?php endif; ?>
                <a class="toolbar-menu-link" href="<?= htmlspecialchars(app_url_path('admin.php'), ENT_QUOTES) ?>">Admin</a>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
              <?php if ($hasUserMenuItems): ?>
                <?php if ($menuSectionRendered): ?>
                  <hr class="toolbar-menu-separator" role="presentation" />
                <?php endif; ?>
                <a class="toolbar-menu-link" href="<?= htmlspecialchars(app_url_path('password.php'), ENT_QUOTES) ?>">Profilom</a>
                <form method="post" action="<?= htmlspecialchars(app_url_path('logout.php'), ENT_QUOTES) ?>" class="toolbar-menu-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($LOGOUT_TOKEN, ENT_QUOTES) ?>">
                  <button type="submit">Kilépés</button>
                </form>
                <?php $menuSectionRendered = true; ?>
              <?php endif; ?>
            </div>
          </div>
          <?php
          $toolbarItems['menu'] = trim(ob_get_clean());
        }
        if (!empty($toolbarFeatures['undo']) && !empty($CFG['history']['undo_enabled'])) {
          $toolbarItems['undo'] = '<button id="undoBtn" title="' . htmlspecialchars($toolbarText['undo']['title'] ?? '', ENT_QUOTES) . '" disabled>' . htmlspecialchars($toolbarText['undo']['label'] ?? 'Visszavonás', ENT_QUOTES) . '</button>';
        }
        $toolbarItems['pin_counter'] = '<span class="toolbar-pin-counter" title="' . htmlspecialchars($badgeTitle, ENT_QUOTES) . '"><span class="toolbar-pin-counter-label">' . htmlspecialchars($badgeText, ENT_QUOTES) . '</span> <span id="pinCount" class="badge">0</span></span>';

        $validToolbarKeys = ['expand_all', 'collapse_all', 'menu', 'undo', 'pin_counter'];
        $toolbarOrderCfg = $CFG['ui']['toolbar']['order'] ?? [];
        $toolbarOrder = [];
        if (is_array($toolbarOrderCfg)) {
          foreach ($toolbarOrderCfg as $candidate) {
            $key = is_string($candidate) ? strtolower(trim($candidate)) : '';
            if ($key !== '' && in_array($key, $validToolbarKeys, true) && !in_array($key, $toolbarOrder, true)) {
              $toolbarOrder[] = $key;
            }
          }
        }
        foreach ($validToolbarKeys as $key) {
          if (!in_array($key, $toolbarOrder, true)) {
            $toolbarOrder[] = $key;
          }
        }
        $orderedToolbarItems = [];
        foreach ($toolbarOrder as $orderKey) {
          if (isset($toolbarItems[$orderKey])) {
            $orderedToolbarItems[] = $toolbarItems[$orderKey];
          }
        }
        $hasToolbarItems = !empty($orderedToolbarItems);
      ?>
      <div class="panel-top-header">
        <div class="panel-brand">
          <?php if ($panelTitleImage): ?>
            <img src="<?= $panelTitleImage['src'] ?>" alt="<?= $panelTitleImage['alt'] ?>" class="panel-title-image"<?= $panelTitleImage['attrs'] ?><?= $panelTitleImage['style'] ?>>
          <?php elseif ($logoImage): ?>
            <img src="<?= $logoImage['src'] ?>" alt="<?= $logoImage['alt'] ?>" class="panel-brand-logo"<?= $logoImage['attrs'] ?><?= $logoImage['style'] ?>>
          <?php else: ?>
            <h1 class="panel-title"><?= htmlspecialchars($appTitle, ENT_QUOTES) ?></h1>
          <?php endif; ?>
        </div>
        <?php if ($hasToolbarItems): ?>
          <div class="toolbar">
            <?php foreach ($orderedToolbarItems as $toolbarItemHtml): ?>
              <?= $toolbarItemHtml ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div id="panelContent" class="panel-content">
      <div id="newAddress" class="new-address-container"></div>
      <div id="groups" class="groups"></div>
      <img id="devlogo" src="pic/devlogo.webp">
      <div class="app-version" aria-label="Alkalmazás verziója"><?= htmlspecialchars(APP_VERSION) ?></div>
    </div>
  </aside>
  <main id="map"></main>
</div>
</body>
</html>
