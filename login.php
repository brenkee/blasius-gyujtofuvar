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
$defaultLoginTexts = [
    'page_title' => 'Bejelentkezés',
    'title' => 'Bejelentkezés',
    'subtitle' => 'Kérjük, jelentkezz be a Gyűjtőfuvar használatához.',
    'username_label' => 'Felhasználónév',
    'password_label' => 'Jelszó',
    'login_button' => 'Belépés',
    'forgot_link' => 'Elfelejtett jelszó?',
    'forgot_title' => 'Új jelszó igénylése',
    'forgot_subtitle' => 'Add meg a felhasználónevedet vagy email címedet.',
    'forgot_identifier_label' => 'Felhasználónév vagy email cím',
    'forgot_button' => 'Új jelszó kérése',
    'reset_success' => 'Ha a megadott adatok helyesek, elküldtük az új jelszót az email címedre.',
    'reset_error_missing_identifier' => 'Kérjük, add meg a felhasználónevedet vagy az email címedet.',
    'reset_error_no_email' => 'A felhasználóhoz nem tartozik email cím, kérjük, vedd fel a kapcsolatot az adminisztrátorral.',
    'reset_error_update' => 'Az új jelszó beállítása nem sikerült.',
    'reset_error_mail' => 'Az email küldése nem sikerült. Kérjük, vedd fel a kapcsolatot az adminisztrátorral.',
    'login_error_missing_fields' => 'Kérjük, töltsd ki a felhasználónevet és a jelszót is.',
    'login_error_invalid_credentials' => 'Hibás felhasználónév vagy jelszó.',
];
$customLoginTexts = [];
if (isset($CFG['auth']['login']) && is_array($CFG['auth']['login'])) {
    $customLoginTexts = $CFG['auth']['login'];
}
$loginTexts = array_merge($defaultLoginTexts, $customLoginTexts);
$loginText = static function (string $key) use ($loginTexts, $defaultLoginTexts): string {
    if (array_key_exists($key, $loginTexts) && is_string($loginTexts[$key])) {
        return $loginTexts[$key];
    }
    return $defaultLoginTexts[$key] ?? '';
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $mode = $_POST['mode'] ?? 'login';
    if ($mode === 'request_reset') {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $lastResetIdentifier = $identifier;
        if ($identifier === '') {
            $resetError = $loginText('reset_error_missing_identifier');
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
                        $resetMessage = $loginText('reset_success');
                        $lastResetIdentifier = '';
                    } else {
                        $resetError = $loginText('reset_error_mail');
                        if ($mailError) {
                            error_log('Jelszó reset email küldési hiba: ' . $mailError);
                        }
                    }
                } else {
                    $resetError = $loginText('reset_error_update');
                }
            } elseif ($user && empty($user['email'])) {
                $resetError = $loginText('reset_error_no_email');
            } else {
                $resetMessage = $loginText('reset_success');
            }
        }
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $lastLoginUsername = $username;
        if ($username === '' || $password === '') {
            $error = $loginText('login_error_missing_fields');
        } else {
            $user = auth_find_user_by_username($username);
            if (!$user || !auth_verify_password($password, (string)($user['password_hash'] ?? ''))) {
                $error = $loginText('login_error_invalid_credentials');
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
$showResetForm = ($resetMessage !== null) || ($resetError !== null) || (($_POST['mode'] ?? '') === 'request_reset');
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($loginText('page_title')) ?> – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@halfmoon/ui@2.0.1/dist/css/halfmoon-variables.min.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <h1><?= htmlspecialchars($loginText('title')) ?></h1>
      <p class="auth-subtitle"><?= htmlspecialchars($loginText('subtitle')) ?></p>
      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>" />
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo ?? '', ENT_QUOTES) ?>" />
        <input type="hidden" name="mode" value="login" />
        <label>
          <span><?= htmlspecialchars($loginText('username_label')) ?></span>
          <input type="text" name="username" required autofocus autocomplete="username" value="<?= htmlspecialchars($lastLoginUsername) ?>" />
        </label>
        <label>
          <span><?= htmlspecialchars($loginText('password_label')) ?></span>
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button type="submit" class="btn btn-primary"><?= htmlspecialchars($loginText('login_button')) ?></button>
      </form>
      <div class="auth-forgot">
        <button type="button" class="auth-link auth-forgot-toggle" id="auth-forgot-toggle"><?= htmlspecialchars($loginText('forgot_link')) ?></button>
        <section class="auth-forgot-section" id="auth-forgot-section" <?= $showResetForm ? '' : 'hidden' ?>>
          <hr aria-hidden="true" />
          <h2><?= htmlspecialchars($loginText('forgot_title')) ?></h2>
          <p class="auth-subtitle"><?= htmlspecialchars($loginText('forgot_subtitle')) ?></p>
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
              <span><?= htmlspecialchars($loginText('forgot_identifier_label')) ?></span>
              <input type="text" name="identifier" required value="<?= htmlspecialchars($lastResetIdentifier) ?>" />
            </label>
            <div class="form-actions">
              <button type="submit" class="btn btn-secondary"><?= htmlspecialchars($loginText('forgot_button')) ?></button>
            </div>
          </form>
        </section>
      </div>
    </section>
  </main>
  <script defer src="https://cdn.jsdelivr.net/npm/@halfmoon/ui@2.0.1/dist/js/halfmoon.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('auth-forgot-toggle');
    var section = document.getElementById('auth-forgot-section');
    if (!toggle || !section) {
      return;
    }
    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      var isHidden = section.hasAttribute('hidden');
      if (isHidden) {
        section.removeAttribute('hidden');
        var input = section.querySelector('input[name="identifier"]');
        if (input) {
          input.focus();
        }
      } else {
        section.setAttribute('hidden', 'hidden');
      }
    });
  });
  </script>
</body>
</html>
