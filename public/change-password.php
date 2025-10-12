<?php
declare(strict_types=1);

require __DIR__ . '/../common.php';
require __DIR__ . '/../src/auth/session_guard.php';

use function App\Auth\path_with_base;
use function App\Auth\require_login;
use function App\Auth\resolve_redirect;

$user = require_login(['allow_password_change' => true]);
$redirectParam = isset($_GET['redirect']) ? (string) $_GET['redirect'] : null;
$redirectTarget = resolve_redirect($redirectParam);
$formAction = path_with_base('api/me.php');

$errorCodes = isset($_GET['error']) ? explode(',', (string) $_GET['error']) : [];
$errorMessages = [
    'missing_fields' => 'Tölts ki minden mezőt.',
    'password_too_short' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.',
    'password_mismatch' => 'A megadott jelszavak nem egyeznek.',
    'invalid_current_password' => 'A jelenlegi jelszó nem megfelelő.',
];
$errors = array_values(array_filter(array_map(function ($code) use ($errorMessages) {
    $key = trim((string) $code);
    return $errorMessages[$key] ?? null;
}, $errorCodes)));
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jelszó módosítása</title>
  <style>
    :root {
      color-scheme: light dark;
      --accent: #2563eb;
      --bg: #0f172a;
      --panel: rgba(255, 255, 255, 0.94);
      --panel-dark: rgba(15, 23, 42, 0.88);
      --error: #dc2626;
    }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
      color: #0f172a;
    }
    @media (prefers-color-scheme: dark) {
      body { color: #e2e8f0; }
    }
    main {
      width: min(460px, 92vw);
      background: var(--panel);
      border-radius: 18px;
      padding: 36px;
      box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
      backdrop-filter: blur(10px);
    }
    @media (prefers-color-scheme: dark) {
      main { background: var(--panel-dark); }
    }
    h1 {
      margin: 0 0 10px;
      font-size: clamp(1.6rem, 3vw, 2.1rem);
    }
    p.sub {
      margin: 0 0 28px;
      color: rgba(15, 23, 42, 0.65);
      font-size: 0.95rem;
    }
    @media (prefers-color-scheme: dark) {
      p.sub { color: rgba(226, 232, 240, 0.75); }
    }
    form {
      display: grid;
      gap: 18px;
    }
    label {
      display: flex;
      flex-direction: column;
      gap: 8px;
      font-weight: 600;
      font-size: 0.95rem;
    }
    input[type="password"] {
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px solid rgba(148, 163, 184, 0.45);
      background: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
    }
    @media (prefers-color-scheme: dark) {
      input[type="password"] {
        background: rgba(15, 23, 42, 0.75);
        border-color: rgba(148, 163, 184, 0.3);
        color: #e2e8f0;
      }
    }
    input[type="password"]:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
    }
    button[type="submit"] {
      margin-top: 6px;
      padding: 12px 18px;
      border-radius: 12px;
      border: none;
      background: var(--accent);
      color: #fff;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.2s ease;
    }
    button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 22px rgba(37, 99, 235, 0.35);
    }
    button[type="submit"]:active {
      transform: translateY(0);
    }
    ul.errors {
      list-style: none;
      padding: 0 0 16px;
      margin: 0;
      border-radius: 12px;
      background: rgba(220, 38, 38, 0.12);
      color: var(--error);
    }
    ul.errors li {
      padding: 10px 14px;
      font-size: 0.92rem;
    }
  </style>
</head>
<body>
  <main>
    <h1>Jelszó módosítása</h1>
    <p class="sub">Személyre szabott jelszót kell beállítanod a folytatáshoz, kedves <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>.</p>
    <?php if (!empty($errors)): ?>
      <ul class="errors">
        <?php foreach ($errors as $message): ?>
          <li><?= htmlspecialchars($message, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES) ?>">
      <label>
        Jelenlegi jelszó
        <input type="password" name="current_password" autocomplete="current-password" required>
      </label>
      <label>
        Új jelszó
        <input type="password" name="new_password" autocomplete="new-password" required minlength="8">
      </label>
      <label>
        Új jelszó megerősítése
        <input type="password" name="new_password_confirmation" autocomplete="new-password" required minlength="8">
      </label>
      <button type="submit">Jelszó mentése</button>
    </form>
  </main>
</body>
</html>
