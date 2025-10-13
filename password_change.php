<?php

declare(strict_types=1);

define('AUTH_ALLOW_PASSWORD_CHANGE', true);
require __DIR__ . '/common.php';

$user = auth_current_user();
if (!$user) {
    header('Location: ' . base_url('login.php'), true, 302);
    exit;
}

$mustChange = auth_user_requires_password_change($user);
$redirectTarget = auth_sanitize_redirect_target($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($password === '' || $confirm === '') {
        $errors[] = 'A jelszó és a megerősítés mező kitöltése kötelező.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'A jelszónak legalább 12 karakter hosszúnak kell lennie.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'A jelszónak tartalmaznia kell betűt és számot.';
    }
    if (strtolower($password) === 'admin') {
        $errors[] = 'Az alapértelmezett jelszó nem használható.';
    }
    if ($password !== $confirm) {
        $errors[] = 'A megadott jelszavak nem egyeznek.';
    }

    if (empty($errors)) {
        if (auth_update_user_password((int)$user['id'], $password)) {
            $refreshed = auth_fetch_user_by_id((int)$user['id']);
            if ($refreshed) {
                auth_refresh_session_user($refreshed);
            }
            session_regenerate_id(true);
            auth_add_flash('success', 'A jelszó sikeresen frissült.');
            $target = $redirectTarget !== '' ? $redirectTarget : base_url('index.php');
            header('Location: ' . $target, true, 302);
            exit;
        }
        $errors[] = 'A jelszó frissítése nem sikerült. Próbáld meg újra.';
    }
}

$authCss = base_url('public/auth.css');
$title = 'Jelszó módosítása – Gyűjtőfuvar';
$actionUrl = htmlspecialchars(base_url('password_change.php'), ENT_QUOTES);
$redirectValue = htmlspecialchars($redirectTarget, ENT_QUOTES);
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($authCss, ENT_QUOTES) ?>">
</head>
<body class="auth-body">
  <main class="auth-card" role="main">
    <h1 class="auth-title">Jelszó módosítása</h1>
    <?php if ($mustChange): ?>
      <p class="auth-subtitle">Biztonsági okokból kérjük, állíts be új jelszót az első belépés után.</p>
    <?php else: ?>
      <p class="auth-subtitle">Adj meg egy új, erős jelszót a fiókod védelméhez.</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="auth-alert auth-alert--error">
        <?php foreach ($errors as $error): ?>
          <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= $actionUrl ?>" class="auth-form" autocomplete="off">
      <div class="auth-field">
        <label for="password">Új jelszó</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="12">
        <small class="auth-hint">Legalább 12 karakter, betű és szám használatával.</small>
      </div>
      <div class="auth-field">
        <label for="password_confirm">Új jelszó megerősítése</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
      </div>
      <input type="hidden" name="redirect" value="<?= $redirectValue ?>">
      <button type="submit" class="auth-submit">Jelszó mentése</button>
    </form>

    <p class="auth-footer">
      <a href="<?= htmlspecialchars(base_url('logout.php'), ENT_QUOTES) ?>">Kilépés a fiókból</a>
    </p>
  </main>
</body>
</html>
