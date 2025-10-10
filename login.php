<?php
require __DIR__ . '/common.php';

if (auth_current_user()) {
  header('Location: index.php');
  exit;
}

$loginCfg = is_array($CFG['auth']['login'] ?? null) ? $CFG['auth']['login'] : [];
$title = (string)($loginCfg['title'] ?? 'Belépés');
$subtitle = (string)($loginCfg['subtitle'] ?? 'Lépj be a rendszerbe.');
$logo = $loginCfg['logo'] ?? null;
$bgColor = (string)($loginCfg['background_color'] ?? '#0f172a');
$bgImage = $loginCfg['background_image'] ?? null;
$panelColor = (string)($loginCfg['panel_color'] ?? '#ffffff');
$panelShadow = (string)($loginCfg['panel_shadow'] ?? '0 24px 48px rgba(15,23,42,0.35)');
$panelRadius = (string)($loginCfg['panel_radius'] ?? '18px');
$textColor = (string)($loginCfg['text_color'] ?? '#0f172a');
$mutedColor = (string)($loginCfg['muted_text_color'] ?? 'rgba(15,23,42,0.65)');
$inputBg = (string)($loginCfg['input_background_color'] ?? 'rgba(255,255,255,0.9)');
$buttonColor = (string)($loginCfg['button_color'] ?? '#2563eb');
$buttonTextColor = (string)($loginCfg['button_text_color'] ?? '#ffffff');
$footerHtml = $loginCfg['footer_html'] ?? null;

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf'] ?? '';
  if (!auth_validate_csrf_token($token, 'login')) {
    $error = 'Érvénytelen kérés, próbáld újra.';
  } else {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
      $error = 'Add meg a felhasználónevet és a jelszót is!';
    } elseif (auth_attempt_login($username, $password)) {
      header('Location: index.php');
      exit;
    } else {
      $error = 'Hibás felhasználónév vagy jelszó.';
    }
  }
}
$csrf = auth_generate_csrf_token('login');
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($CFG['app']['title']) ?> – Belépés</title>
  <style>
    :root {
      color-scheme: light;
    }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: <?= htmlspecialchars($bgColor) ?>;
      <?php if ($bgImage): ?>
      background-image: url('<?= htmlspecialchars($bgImage) ?>');
      background-size: cover;
      background-position: center;
      <?php endif; ?>
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      padding: 24px;
    }
    .login-card {
      width: min(420px, 100%);
      background: <?= htmlspecialchars($panelColor) ?>;
      color: <?= htmlspecialchars($textColor) ?>;
      border-radius: <?= htmlspecialchars($panelRadius) ?>;
      padding: 36px;
      box-shadow: <?= htmlspecialchars($panelShadow) ?>;
      display: grid;
      gap: 18px;
    }
    .login-card h1 {
      margin: 0;
      font-size: 26px;
    }
    .login-card p.subtitle {
      margin: 0;
      color: <?= htmlspecialchars($mutedColor) ?>;
      font-size: 15px;
    }
    .login-card form {
      display: grid;
      gap: 16px;
    }
    .field {
      display: grid;
      gap: 6px;
    }
    label {
      font-size: 13px;
      font-weight: 600;
      color: <?= htmlspecialchars($mutedColor) ?>;
    }
    input[type="text"],
    input[type="password"] {
      padding: 12px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.35);
      font-size: 15px;
      background: <?= htmlspecialchars($inputBg) ?>;
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
      outline: 2px solid rgba(59,130,246,0.4);
      border-color: rgba(59,130,246,0.4);
    }
    button {
      padding: 12px;
      border-radius: 12px;
      border: none;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      background: <?= htmlspecialchars($buttonColor) ?>;
      color: <?= htmlspecialchars($buttonTextColor) ?>;
      transition: transform .15s ease, box-shadow .15s ease;
    }
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 24px rgba(37, 99, 235, 0.25);
    }
    .logo {
      display: flex;
      justify-content: center;
    }
    .logo img {
      max-width: 160px;
      max-height: 80px;
    }
    .error {
      background: rgba(220,38,38,0.12);
      color: #b91c1c;
      border-radius: 10px;
      padding: 12px;
      font-weight: 600;
      font-size: 14px;
    }
    .footer {
      margin-top: 12px;
      font-size: 12px;
      color: rgba(15,23,42,0.55);
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <?php if ($logo): ?>
      <div class="logo"><img src="<?= htmlspecialchars($logo) ?>" alt=""></div>
    <?php endif; ?>
    <div>
      <h1><?= htmlspecialchars($title) ?></h1>
      <p class="subtitle"><?= htmlspecialchars($subtitle) ?></p>
    </div>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <div class="field">
        <label for="username">Felhasználónév</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus />
      </div>
      <div class="field">
        <label for="password">Jelszó</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required />
      </div>
      <button type="submit">Belépés</button>
    </form>
    <?php if ($footerHtml): ?>
      <div class="footer"><?= $footerHtml ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
