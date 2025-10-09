<?php require __DIR__ . '/common.php'; ?>
<!doctype html>
<html lang="hu">
<head>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= htmlspecialchars($CFG['app']['title']) ?></title>
<link rel="icon" type="image/png" href="favicon.png" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="public/styles.css">
<script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin></script>
<script>
  window.APP_BOOTSTRAP = {
    endpoints: {
      cfg: 'api.php?action=cfg',
      load: 'api.php?action=load',
      save: 'api.php?action=save',
      geocode: 'api.php?action=geocode',
      exportAll: 'api.php?action=export',
      exportRound: (rid)=>'api.php?action=export&round='+encodeURIComponent(rid),
      deleteRound: 'api.php?action=delete_round',
      downloadArchive: 'api.php?action=download_archive',
      printAll: 'print.php',
      printRound: (rid)=>'print.php?round='+encodeURIComponent(rid)
    }
  };
</script>
<script defer src="public/app.js"></script>
</head>
<body>
<div class="app">
  <aside class="panel">
    <h1><?= htmlspecialchars($CFG['app']['title']) ?></h1>
    <div class="toolbar">
      <button id="expandAll">Összes kinyit</button>
      <button id="collapseAll">Összes összezár</button>
      <button id="exportBtn" title="Export TXT"><?= htmlspecialchars($CFG['app']['export_button_label']) ?></button>
      <button id="printBtn" title="Nyomtatás">Nyomtatás</button>
      <button id="downloadArchiveBtn" title="Archívum letöltése (TXT)">Archívum letöltése</button>
      <button id="themeToggle" title="Téma váltása">🌙 / ☀️</button>
      <button id="undoBtn" title="Visszavonás" disabled>Visszavonás</button> <!-- ÚJ GOMB: UNDO -->
      <span>Pin-ek: <span id="pinCount" class="badge">0</span></span>
    </div>
    <div id="groups" class="groups"></div>
  </aside>
  <main id="map"></main>
</div>
</body>
</html>
