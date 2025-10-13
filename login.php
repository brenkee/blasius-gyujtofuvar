<?php
require __DIR__ . '/common.php';

$returnToRaw = $_GET['return_to'] ?? $_POST['return_to'] ?? null;
$returnTo = ($returnToRaw !== null && $returnToRaw !== '') ? auth_normalize_return_to($returnToRaw) : null;
$currentUser = auth_get_current_user();
if ($currentUser) {
    if (!empty($currentUser['must_change_password'])) {
        auth_redirect_to_password_change($returnTo);
    }
    header('Location: ' . ($returnTo ?: app_url_path('')));
    exit;
}

$error = null;
$resetMessage = null;
$resetError = null;
$lastLoginUsername = '';
$lastResetIdentifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $mode = $_POST['mode'] ?? 'login';
    if ($mode === 'request_reset') {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $lastResetIdentifier = $identifier;
        if ($identifier === '') {
            $resetError = 'Kérjük, add meg a felhasználónevedet vagy az email címedet.';
        } else {
            $user = auth_find_user_by_identifier($identifier);
            if ($user && !empty($user['email'])) {
                $newPassword = auth_generate_random_password();
                $hash = auth_password_hash($newPassword);
                if (auth_update_user_password((int)$user['id'], $hash, true)) {
                    $smtpCfg = $CFG['smtp'] ?? [];
                    $mailError = null;
                    $mailOk = app_smtp_send_mail($smtpCfg, [
                        'from_email' => $smtpCfg['from_email'] ?? '',
                        'from_name' => $smtpCfg['from_name'] ?? ($CFG['app']['title'] ?? 'Gyűjtőfuvar'),
                        'to_email' => $user['email'],
                        'to_name' => $user['username'],
                        'subject' => 'Új belépési jelszó',
                        'body' => "Kedves {$user['username']}!\n\nÚj jelszót generáltunk a Gyűjtőfuvar rendszerhez tartozó fiókodhoz.\n\nÚj jelszó: {$newPassword}\n\nKérjük, az első belépés után változtasd meg a jelszót a saját biztonságod érdekében.\n\nÜdvözlettel,\nGyűjtőfuvar" ,
                    ], $mailError);
                    if ($mailOk) {
                        $resetMessage = 'Ha a megadott adatok helyesek, elküldtük az új jelszót az email címedre.';
                        $lastResetIdentifier = '';
                    } else {
                        $resetError = 'Az email küldése nem sikerült. Kérjük, vedd fel a kapcsolatot az adminisztrátorral.';
                        if ($mailError) {
                            error_log('Jelszó reset email küldési hiba: ' . $mailError);
                        }
                    }
                } else {
                    $resetError = 'Az új jelszó beállítása nem sikerült.';
                }
            } elseif ($user && empty($user['email'])) {
                $resetError = 'A felhasználóhoz nem tartozik email cím, kérjük, vedd fel a kapcsolatot az adminisztrátorral.';
            } else {
                $resetMessage = 'Ha a megadott adatok helyesek, elküldtük az új jelszót az email címedre.';
            }
        }
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $lastLoginUsername = $username;
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
}

$token = csrf_get_token();
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bejelentkezés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
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
        <input type="hidden" name="mode" value="login" />
        <label>
          <span>Felhasználónév</span>
          <input type="text" name="username" required autofocus autocomplete="username" value="<?= htmlspecialchars($lastLoginUsername) ?>" />
        </label>
        <label>
          <span>Jelszó</span>
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button type="submit" class="auth-submit">Belépés</button>
      </form>
      <hr style="border:none;border-top:1px solid var(--border);margin:12px 0" />
      <h2 style="margin:0;font-size:16px;text-align:center;color:var(--muted)">Elfelejtett jelszó</h2>
      <?php if ($resetMessage): ?>
        <div class="auth-info" role="status"><?= htmlspecialchars($resetMessage) ?></div>
      <?php endif; ?>
      <?php if ($resetError): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($resetError) ?></div>
      <?php endif; ?>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>" />
        <input type="hidden" name="mode" value="request_reset" />
        <label>
          <span>Felhasználónév vagy email cím</span>
          <input type="text" name="identifier" required value="<?= htmlspecialchars($lastResetIdentifier) ?>" />
        </label>
        <button type="submit" class="auth-submit" style="background:var(--border);color:var(--text)">Új jelszó kérése</button>
      </form>
    </section>
  </main>
</body>
</html>
