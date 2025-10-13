<?php
require __DIR__ . '/common.php';

$returnToRaw = $_GET['return_to'] ?? $_POST['return_to'] ?? null;
$returnTo = auth_normalize_return_to($returnToRaw);
$CURRENT_USER = auth_require_login(['allow_password_change' => true, 'return_to' => $returnTo]);
$TOKEN = csrf_get_token();

$error = null;
$info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Minden mező kitöltése kötelező.';
    } elseif (!auth_verify_password($currentPassword, (string)($CURRENT_USER['password_hash'] ?? ''))) {
        $error = 'A jelenlegi jelszó nem megfelelő.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'Az új jelszónak legalább 12 karakter hosszúnak kell lennie.';
    } elseif (!preg_match('/[A-ZÁÉÍÓÖŐÚÜŰ]/u', $newPassword) || !preg_match('/[a-záéíóöőúüű]/u', $newPassword) || !preg_match('/\d/', $newPassword)) {
        $error = 'Használj kis- és nagybetűt, valamint számot az új jelszóban.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Az új jelszó és a megerősítés nem egyezik.';
    } elseif ($newPassword === $currentPassword) {
        $error = 'Az új jelszó nem lehet azonos a jelenlegivel.';
    } else {
        $hash = auth_password_hash($newPassword);
        if (auth_update_user_password((int)$CURRENT_USER['id'], $hash, false)) {
            auth_set_session_must_change(false);
            $info = 'A jelszó sikeresen frissült.';
            header('Location: ' . ($returnTo ?: app_url_path('')));
            exit;
        } else {
            $error = 'Nem sikerült frissíteni a jelszót. Próbáld meg később.';
        }
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Jelszó módosítása – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('favicon.png'), ENT_QUOTES) ?>" />
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <h1>Jelszó módosítása</h1>
      <p class="auth-subtitle"><?= !empty($CURRENT_USER['must_change_password']) ? 'Az első bejelentkezéshez új jelszót kell beállítanod.' : 'Adj meg egy új, erős jelszót.' ?></p>
      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($info): ?>
        <div class="auth-info" role="status"><?= htmlspecialchars($info) ?></div>
      <?php endif; ?>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($TOKEN, ENT_QUOTES) ?>" />
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo ?? '', ENT_QUOTES) ?>" />
        <label>
          <span>Jelenlegi jelszó</span>
          <input type="password" name="current_password" required autocomplete="current-password" />
        </label>
        <label>
          <span>Új jelszó</span>
          <input type="password" name="new_password" required autocomplete="new-password" minlength="12" />
        </label>
        <label>
          <span>Új jelszó megerősítése</span>
          <input type="password" name="confirm_password" required autocomplete="new-password" minlength="12" />
        </label>
        <button type="submit" class="auth-submit">Jelszó mentése</button>
        <a class="auth-link" href="<?= htmlspecialchars($returnTo ?: app_url_path(''), ENT_QUOTES) ?>">Mégse, vissza az alkalmazásba</a>
      </form>
    </section>
  </main>
</body>
</html>
