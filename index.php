<?php
require __DIR__ . '/common.php';

$loginError = null;
$loginUsername = '';

if (!$CURRENT_USER && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
    $loginUsername = trim((string)($_POST['login_username'] ?? ''));
    $passwordInput = (string)($_POST['login_password'] ?? '');
    try {
        auth_attempt_login($loginUsername, $passwordInput);
        header('Location: index.php');
        exit;
    } catch (RuntimeException $e) {
        $code = $e->getMessage();
        if ($code === 'missing_credentials') {
            $loginError = 'Kérlek add meg a felhasználónevet és jelszót.';
        } elseif ($code === 'invalid_credentials') {
            $loginError = 'Hibás felhasználónév vagy jelszó.';
        } else {
            $loginError = 'A bejelentkezés nem sikerült. Próbáld újra.';
        }
    } catch (Throwable $e) {
        $loginError = 'A bejelentkezés során hiba történt. Részletek: ' . $e->getMessage();
    }
}

if (!auth_user_can_view($CURRENT_USER)) {
    $loginCfg = is_array($CFG['auth']['login_ui'] ?? null) ? $CFG['auth']['login_ui'] : [];
    $loginTitle = $loginCfg['title'] ?? ($CFG['app']['title'] . ' – Belépés');
    $loginSubtitle = $loginCfg['subtitle'] ?? '';
    $loginLogo = $loginCfg['logo'] ?? null;
    $bgColor = htmlspecialchars($loginCfg['background_color'] ?? '#f8fafc', ENT_QUOTES);
    $cardColor = htmlspecialchars($loginCfg['card_background'] ?? '#ffffff', ENT_QUOTES);
    $textColor = htmlspecialchars($loginCfg['text_color'] ?? '#0f172a', ENT_QUOTES);
    $accentColor = htmlspecialchars($loginCfg['accent_color'] ?? '#2563eb', ENT_QUOTES);
    $footerHtml = $loginCfg['footer_html'] ?? '';
    ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($loginTitle) ?></title>
  <link rel="icon" type="image/png" href="favicon.png" />
  <style>
    :root {
      color-scheme: light dark;
    }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
      background: <?= $bgColor ?>;
      color: <?= $textColor ?>;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .login-wrapper {
      width: min(420px, 100%);
    }
    .login-card {
      background: <?= $cardColor ?>;
      border-radius: 20px;
      box-shadow: 0 30px 60px rgba(15,23,42,0.18);
      padding: 32px;
      display: grid;
      gap: 18px;
    }
    .login-card header {
      display: grid;
      gap: 10px;
      text-align: center;
    }
    .login-card header img {
      max-width: 160px;
      max-height: 80px;
      margin: 0 auto;
      object-fit: contain;
    }
    .login-card h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 700;
    }
    .login-card p.subtitle {
      margin: 0;
      color: rgba(15,23,42,0.72);
      font-size: 15px;
    }
    .login-card form {
      display: grid;
      gap: 14px;
    }
    .login-card label {
      font-size: 14px;
      font-weight: 600;
      color: rgba(15,23,42,0.74);
    }
    .login-card input[type="text"],
    .login-card input[type="password"] {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.35);
      font: inherit;
      background: rgba(255,255,255,0.9);
      color: inherit;
    }
    .login-card input[type="text"]:focus,
    .login-card input[type="password"]:focus {
      outline: 2px solid <?= $accentColor ?>;
      outline-offset: 2px;
      border-color: transparent;
    }
    .login-card button[type="submit"] {
      margin-top: 8px;
      padding: 12px;
      border-radius: 12px;
      border: none;
      font: inherit;
      font-weight: 700;
      background: <?= $accentColor ?>;
      color: #fff;
      cursor: pointer;
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .login-card button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(37,99,235,0.25);
    }
    .login-card .error {
      background: rgba(220,38,38,0.1);
      color: #b91c1c;
      border-radius: 12px;
      padding: 10px 14px;
      font-size: 14px;
    }
    .login-footer {
      margin-top: 18px;
      text-align: center;
      font-size: 13px;
      color: rgba(15,23,42,0.6);
    }
    .init-error-banner {
      margin-top: 18px;
    }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card" role="dialog" aria-labelledby="loginTitle">
      <header>
        <?php if ($loginLogo): ?>
          <img src="<?= htmlspecialchars($loginLogo) ?>" alt="Logó" />
        <?php endif; ?>
        <h1 id="loginTitle"><?= htmlspecialchars($loginTitle) ?></h1>
        <?php if ($loginSubtitle): ?>
          <p class="subtitle"><?= htmlspecialchars($loginSubtitle) ?></p>
        <?php endif; ?>
      </header>
      <?php if ($loginError): ?>
        <div class="error" role="alert"><?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>
      <?php if (!empty($DATA_INIT_ERROR)): ?>
        <div class="error" role="alert">
          <strong>Adatbázis inicializációs hiba:</strong><br>
          <?= htmlspecialchars($DATA_INIT_ERROR) ?><br>
          Futtasd a <code>php scripts/init-db.php</code> parancsot a létrehozáshoz.
        </div>
      <?php endif; ?>
      <form method="post" autocomplete="on">
        <div>
          <label for="login_username">Felhasználónév</label>
          <input id="login_username" name="login_username" type="text" value="<?= htmlspecialchars($loginUsername) ?>" autocomplete="username" required />
        </div>
        <div>
          <label for="login_password">Jelszó</label>
          <input id="login_password" name="login_password" type="password" autocomplete="current-password" required />
        </div>
        <button type="submit">Belépés</button>
      </form>
    </div>
    <?php if ($footerHtml): ?>
      <div class="login-footer"><?= $footerHtml ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
<?php
    exit;
}

$currentUserJson = json_encode($CURRENT_USER ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$authBootstrap = [
    'logout' => 'logout.php',
    'users' => [
        'list' => 'api.php?action=users_list',
        'save' => 'api.php?action=users_save',
    ],
];
$authJson = json_encode($authBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="hu">
<head>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($CFG['app']['title']) ?></title>
<link rel="icon" type="image/png" href="favicon.png" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin>
<link rel="stylesheet" href="public/styles.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin></script>
<script>
  window.APP_BOOTSTRAP = {
    endpoints: {
      cfg: 'api.php?action=cfg',
      load: 'api.php?action=load',
      save: 'api.php?action=save',
      session: 'api.php?action=session',
      revision: 'api.php?action=revision',
      changes: 'api.php?action=changes',
      geocode: 'api.php?action=geocode',
      importCsv: 'api.php?action=import_csv',
      exportAll: 'api.php?action=export',
      deleteRound: 'api.php?action=delete_round',
      downloadArchive: 'api.php?action=download_archive',
      printAll: 'print.php',
      printRound: (rid)=>'print.php?round='+encodeURIComponent(rid)
    },
    auth: <?= $authJson ?>,
    currentUser: <?= $currentUserJson ?>
  };
</script>
<script defer src="public/app.js"></script>
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
        $isAdminUser = auth_user_can_manage_users($CURRENT_USER);
        $toolbarMenuHasItems = !empty($toolbarFeatures['import_all'])
          || !empty($toolbarFeatures['export_all'])
          || !empty($toolbarFeatures['print_all'])
          || !empty($toolbarFeatures['download_archive'])
          || !empty($toolbarFeatures['theme_toggle'])
          || $isAdminUser;
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
                  <?= htmlspecialchars($toolbarText['export_all']['label'] ?? 'Export (CSV)') ?>
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
              <?php if ($isAdminUser): ?>
                <button id="adminUsersBtn" type="button" title="Felhasználók kezelése">Felhasználók</button>
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
        <div class="toolbar-spacer"></div>
        <div class="user-indicator" aria-label="Bejelentkezett felhasználó">
          <div class="user-indicator__name"><?= htmlspecialchars($CURRENT_USER['username'] ?? '') ?></div>
          <div class="user-indicator__role"><?= htmlspecialchars(ucfirst((string)($CURRENT_USER['role'] ?? ''))) ?></div>
          <form method="post" action="logout.php" class="logout-form">
            <button type="submit" class="logout-button">Kijelentkezés</button>
          </form>
        </div>
      </div>
    </div>
    <div id="newAddress" class="new-address-container"></div>
    <div id="groups" class="groups"></div>
  </aside>
  <main id="map"></main>
</div>
</body>
</html>
