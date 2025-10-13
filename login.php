<?php
require __DIR__ . '/common.php';

$returnToRaw = $_GET['return_to'] ?? $_POST['return_to'] ?? null;
$returnTo = auth_normalize_return_to($returnToRaw);
$currentUser = auth_get_current_user();
if ($currentUser) {
    if (!empty($currentUser['must_change_password'])) {
        auth_redirect_to_password_change($returnTo);
    }
    header('Location: ' . ($returnTo ?: app_url_path('')));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Kérjük, töltsd ki a felhasználónevet és a jelszót is.';
    } else {
        $user = auth_find_user_by_username($username);
        if (!$user || !auth_verify_password($password, (string)($user['password_hash'] ?? ''))) {
            $error = 'Hibás felhasználónév vagy jelszó.';
        } else {
            auth_store_session((int)$user['id'], !empty($user['must_change_password']));
            if (!empty($user['must_change_password'])) {
                auth_redirect_to_password_change($returnTo);
            }
            header('Location: ' . ($returnTo ?: app_url_path('')));
            exit;
        }
    }
}

$token = csrf_get_token();
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bejelentkezés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('favicon.png'), ENT_QUOTES) ?>" />
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <h1>Bejelentkezés</h1>
      <p class="auth-subtitle">Kérjük, jelentkezz be a Gyűjtőfuvar használatához.</p>
      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>" />
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo ?? '', ENT_QUOTES) ?>" />
        <label>
          <span>Felhasználónév</span>
          <input type="text" name="username" required autofocus autocomplete="username" />
        </label>
        <label>
          <span>Jelszó</span>
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button type="submit" class="auth-submit">Belépés</button>
      </form>
    </section>
  </main>
</body>
</html>
