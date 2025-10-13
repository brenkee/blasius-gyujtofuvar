<?php

declare(strict_types=1);

define('AUTH_OPTIONAL', true);
require __DIR__ . '/common.php';

$redirectTarget = auth_sanitize_redirect_target($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$errors = [];
$infoMessage = null;
$username = '';

if (!empty($_GET['logged_out'])) {
    $infoMessage = 'Sikeresen kijelentkeztél.';
}

if (auth_is_logged_in()) {
    $currentUser = auth_current_user();
    if ($currentUser && auth_user_requires_password_change($currentUser)) {
        auth_redirect_to_password_change($redirectTarget !== '' ? $redirectTarget : null);
    }
    if ($redirectTarget === '') {
        $redirectTarget = base_url('index.php');
    }
    header('Location: ' . $redirectTarget, true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Add meg a felhasználónevet és a jelszót.';
    } else {
        $user = auth_fetch_user_by_username($username);
        if (!$user || !auth_password_verify($password, (string)($user['password_hash'] ?? ''))) {
            $errors[] = 'Hibás felhasználónév vagy jelszó.';
        } else {
            auth_rehash_password_if_needed($user, $password);
            auth_login_user($user);

            if (auth_user_requires_password_change($user)) {
                auth_redirect_to_password_change($redirectTarget !== '' ? $redirectTarget : null);
            }

            $target = $redirectTarget !== '' ? $redirectTarget : base_url('index.php');
            header('Location: ' . $target, true, 302);
            exit;
        }
    }
}

$authCss = base_url('public/auth.css');
$loginAction = htmlspecialchars(base_url('login.php'), ENT_QUOTES);
$redirectValue = htmlspecialchars($redirectTarget, ENT_QUOTES);
$title = 'Bejelentkezés – Gyűjtőfuvar';
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
    <h1 class="auth-title">Gyűjtőfuvar</h1>
    <p class="auth-subtitle">Jelentkezz be a folytatáshoz.</p>

    <?php if ($infoMessage): ?>
      <div class="auth-alert auth-alert--info"><?= htmlspecialchars($infoMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="auth-alert auth-alert--error">
        <?php foreach ($errors as $error): ?>
          <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= $loginAction ?>" class="auth-form" autocomplete="on">
      <div class="auth-field">
        <label for="username">Felhasználónév</label>
        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" autocomplete="username">
      </div>
      <div class="auth-field">
        <label for="password">Jelszó</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <input type="hidden" name="redirect" value="<?= $redirectValue ?>">
      <button type="submit" class="auth-submit">Belépés</button>
    </form>

    <p class="auth-footer">
      Az első bejelentkezéshez használd az <strong>admin / admin</strong> párost. A rendszer azonnal kérni fogja az új jelszó beállítását.
    </p>
  </main>
</body>
</html>
