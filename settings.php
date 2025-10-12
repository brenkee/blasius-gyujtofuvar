<?php require __DIR__ . '/common.php'; ?>
<!doctype html>
<html lang="hu">
<head>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars(($CFG['app']['title'] ?? 'Alkalmazás') . ' – Beállítások') ?></title>
<link rel="icon" type="image/png" href="favicon.png" />
<link rel="stylesheet" href="public/styles.css">
<script>
  window.APP_BOOTSTRAP = {
    endpoints: {
      configGet: 'api.php?action=config_get',
      configSave: 'api.php?action=config_save'
    },
    homeUrl: 'index.php'
  };
</script>
<script defer src="public/settings.js"></script>
</head>
<body class="settings-page">
<div id="settingsView" class="settings-view">
  <header class="settings-header">
    <button id="settingsBackBtn" type="button" class="settings-back" data-href="index.php">← Vissza</button>
    <h1>Beállítások</h1>
    <div class="settings-header-actions">
      <button id="settingsCancelBtn" type="button" class="settings-cancel" disabled>Mégse</button>
      <button id="settingsSaveBtn" type="button" class="settings-save" disabled>Mentés</button>
    </div>
  </header>
  <div id="settingsStatus" class="settings-status" role="status" aria-live="polite"></div>
  <div id="settingsContent" class="settings-content"></div>
</div>
</body>
</html>
