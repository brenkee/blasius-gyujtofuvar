<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

auth_session_start();
$redirectParam = auth_sanitize_redirect($_REQUEST['redirect'] ?? null);
$currentUser = auth_current_user();
if ($currentUser) {
    if (auth_user_must_change_password($currentUser)) {
        $target = base_url('password.php');
        if ($redirectParam) {
            $target .= '?redirect=' . rawurlencode($redirectParam);
        }
        header('Location: ' . $target, true, 302);
        exit;
    }
    $target = $redirectParam ?? base_url('index.php');
    header('Location: ' . $target, true, 302);
    exit;
}

$errorMessage = '';
$usernameValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameValue = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $result = auth_attempt_login($usernameValue, $password);
    if ($result['ok']) {
        $mustChange = !empty($result['must_change_password']);
        $target = null;
        if ($mustChange) {
            $target = base_url('password.php');
            $redirectFinal = $redirectParam ?: '/';
            if ($redirectFinal) {
                $target .= '?redirect=' . rawurlencode($redirectFinal);
            }
        } else {
            $target = $redirectParam ?? base_url('index.php');
        }
        header('Location: ' . $target, true, 302);
        exit;
    }
    $errorMessage = 'Helytelen felhasználónév vagy jelszó. Kérlek, próbáld újra!';
}

?><!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bejelentkezés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <style>
    :root {
      color-scheme: light dark;
    }
    body {
      margin: 0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      background: radial-gradient(circle at top, rgba(37,99,235,0.08), transparent 55%), #f5f5f5;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(6px);
      border-radius: 16px;
      box-shadow: 0 12px 35px rgba(15,23,42,0.12);
      padding: 32px clamp(24px, 4vw, 48px);
      width: min(420px, 100%);
    }
    h1 {
      margin: 0 0 16px;
      font-size: clamp(26px, 5vw, 32px);
      color: #1f2937;
    }
    p.subtitle {
      margin: 0 0 28px;
      color: #4b5563;
      line-height: 1.5;
    }
    form {
      display: grid;
      gap: 18px;
    }
    label {
      display: grid;
      gap: 8px;
      font-weight: 600;
      color: #1f2937;
    }
    input[type="text"], input[type="password"] {
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,0.6);
      background: rgba(255,255,255,0.9);
      font-size: 16px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input[type="text"]:focus, input[type="password"]:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
    }
    button {
      appearance: none;
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-size: 16px;
      font-weight: 600;
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: #fff;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-shadow: 0 10px 20px rgba(37,99,235,0.25);
    }
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 22px rgba(37,99,235,0.28);
    }
    .error {
      background: rgba(248,113,113,0.12);
      color: #b91c1c;
      padding: 12px 14px;
      border-radius: 12px;
      font-weight: 600;
    }
    footer {
      margin-top: 26px;
      font-size: 13px;
      color: #6b7280;
      text-align: center;
    }
    @media (max-width: 480px) {
      .card {
        padding: 28px 20px;
        border-radius: 12px;
      }
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Belépés</h1>
    <p class="subtitle">Kérlek, jelentkezz be a <?= htmlspecialchars($CFG['app']['title']) ?> használatához.</p>
    <?php if ($errorMessage !== ''): ?>
      <div class="error" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <form method="post" novalidate>
      <label>
        Felhasználónév
        <input type="text" name="username" autocomplete="username" required value="<?= htmlspecialchars($usernameValue) ?>" />
      </label>
      <label>
        Jelszó
        <input type="password" name="password" autocomplete="current-password" required />
      </label>
      <?php if ($redirectParam): ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectParam) ?>" />
      <?php endif; ?>
      <button type="submit">Bejelentkezés</button>
    </form>
    <footer>Első bejelentkezéskor az „admin” felhasználónévvel és jelszóval lépj be, majd módosítsd a jelszót.</footer>
  </div>
</body>
</html>
