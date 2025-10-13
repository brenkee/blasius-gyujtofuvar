<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

auth_session_start();
$user = auth_require_login();
$redirectParam = auth_sanitize_redirect($_REQUEST['redirect'] ?? null);
$mustChange = auth_user_must_change_password($user);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (mb_strlen($password) < 8) {
        $errors[] = 'A jelszónak legalább 8 karakter hosszúnak kell lennie.';
    }
    if ($password !== $confirm) {
        $errors[] = 'A két jelszó nem egyezik.';
    }
    if (strtolower($password) === 'admin') {
        $errors[] = 'Biztonsági okokból ne használj „admin” jelszót.';
    }

    if (empty($errors)) {
        auth_update_password((int)$user['id'], $password);
        $mustChange = false;
        $target = $redirectParam ?? base_url('index.php');
        header('Location: ' . $target, true, 302);
        exit;
    }
}

?><!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Jelszó módosítása – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <style>
    :root { color-scheme: light dark; }
    body {
      margin: 0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      background: linear-gradient(160deg, rgba(16,185,129,0.15), rgba(37,99,235,0.08));
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: rgba(255,255,255,0.94);
      backdrop-filter: blur(6px);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.16);
      padding: clamp(28px, 5vw, 48px);
      width: min(480px, 100%);
    }
    h1 {
      margin: 0 0 12px;
      font-size: clamp(26px, 5vw, 32px);
      color: #0f172a;
    }
    p.lead {
      margin: 0 0 24px;
      color: #334155;
      line-height: 1.6;
    }
    form {
      display: grid;
      gap: 18px;
    }
    label {
      display: grid;
      gap: 8px;
      font-weight: 600;
      color: #0f172a;
    }
    input[type="password"] {
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,0.65);
      font-size: 16px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input[type="password"]:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14,165,233,0.18);
    }
    button {
      appearance: none;
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-size: 16px;
      font-weight: 600;
      background: linear-gradient(135deg, #0ea5e9, #2563eb);
      color: #fff;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-shadow: 0 12px 25px rgba(37,99,235,0.25);
    }
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 28px rgba(37,99,235,0.28);
    }
    .messages {
      display: grid;
      gap: 12px;
      margin-bottom: 16px;
    }
    .alert {
      padding: 12px 14px;
      border-radius: 12px;
      font-weight: 600;
    }
    .alert--error {
      background: rgba(248,113,113,0.12);
      color: #b91c1c;
    }
    .alert--info {
      background: rgba(14,165,233,0.12);
      color: #0369a1;
    }
    a.back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 20px;
      color: #0f172a;
      font-weight: 600;
      text-decoration: none;
    }
    a.back-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Jelszó módosítása</h1>
    <p class="lead"><?= htmlspecialchars($user['username']) ?>, itt tudod megadni az új, biztonságos jelszavadat.</p>
    <div class="messages">
      <?php if ($mustChange): ?>
        <div class="alert alert--info">Első belépéskor kötelező a jelszó módosítása.</div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
    <form method="post" novalidate>
      <label>
        Új jelszó
        <input type="password" name="password" autocomplete="new-password" required />
      </label>
      <label>
        Új jelszó mégegyszer
        <input type="password" name="password_confirm" autocomplete="new-password" required />
      </label>
      <?php if ($redirectParam): ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectParam) ?>" />
      <?php endif; ?>
      <button type="submit">Jelszó mentése</button>
    </form>
    <a class="back-link" href="<?= htmlspecialchars(base_url('index.php')) ?>">&larr; Vissza az alkalmazáshoz</a>
  </div>
</body>
</html>
