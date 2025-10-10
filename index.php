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
    }
  };
</script>
<script defer src="public/app.js"></script>
</head>
<body>
<div class="app">
  <aside class="panel">
    <div id="panelTop" class="panel-top">
      <h1><?= htmlspecialchars($CFG['app']['title']) ?></h1>
      <?php
        $toolbarFeatures = $CFG['features']['toolbar'] ?? [];
        $toolbarText = $CFG['text']['toolbar'] ?? [];
        $badgeText = $CFG['text']['badges']['pin_counter_label'] ?? 'Pin-ek:';
        $badgeTitle = $CFG['text']['badges']['pin_counter_title'] ?? '';
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
        <?php if (!empty($toolbarFeatures['import_all'])): ?>
          <button id="importBtn" title="<?= htmlspecialchars($toolbarText['import_all']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['import_all']['label'] ?? 'Import (CSV)') ?>
          </button>
          <input type="file" id="importFileInput" accept=".csv,text/csv" style="display:none" />
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['export_all'])): ?>
          <button id="exportBtn" title="<?= htmlspecialchars($toolbarText['export_all']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['export_all']['label'] ?? 'Export') ?>
          </button>
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['print_all'])): ?>
          <button id="printBtn" title="<?= htmlspecialchars($toolbarText['print_all']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['print_all']['label'] ?? 'Nyomtatás') ?>
          </button>
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['download_archive'])): ?>
          <button id="downloadArchiveBtn" title="<?= htmlspecialchars($toolbarText['download_archive']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['download_archive']['label'] ?? 'Archívum letöltése') ?>
          </button>
        <?php endif; ?>
        <?php if (!empty($toolbarFeatures['theme_toggle'])): ?>
          <button id="themeToggle" title="<?= htmlspecialchars($toolbarText['theme_toggle']['title'] ?? '') ?>">
            <?= htmlspecialchars($toolbarText['theme_toggle']['label'] ?? '🌙 / ☀️') ?>
          </button>
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
    <div id="groups" class="groups"></div>
  </aside>
  <main id="map"></main>
</div>
</body>
</html>
