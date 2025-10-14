<?php
require __DIR__ . '/common.php';

$returnToRaw = $_GET['return_to'] ?? $_POST['return_to'] ?? null;
$returnTo = ($returnToRaw !== null && $returnToRaw !== '') ? auth_normalize_return_to($returnToRaw) : null;
$CURRENT_USER = auth_require_login(['allow_password_change' => true, 'return_to' => $returnTo]);
$TOKEN = csrf_get_token();

$error = null;
$info = null;
$originalEmail = trim((string)($CURRENT_USER['email'] ?? ''));
$formEmail = $originalEmail;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $formEmail = trim((string)($_POST['email'] ?? $formEmail));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $changingPassword = ($newPassword !== '' || $confirmPassword !== '');
    $emailChanged = $formEmail !== trim((string)($CURRENT_USER['email'] ?? ''));

    if (!$changingPassword && !$emailChanged) {
        $error = 'Nincs módosításra váró adat.';
    } elseif ($currentPassword === '') {
        $error = 'A módosítások mentéséhez add meg a jelenlegi jelszavad.';
    } elseif (!auth_verify_password($currentPassword, (string)($CURRENT_USER['password_hash'] ?? ''))) {
        $error = 'A jelenlegi jelszó nem megfelelő.';
    } else {
        if ($emailChanged) {
            if ($formEmail !== '' && !filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Érvénytelen email cím.';
            } else {
                $emailOwner = $formEmail === '' ? null : auth_find_user_by_email($formEmail);
                if ($emailOwner && (int)$emailOwner['id'] !== (int)$CURRENT_USER['id']) {
                    $error = 'Ez az email cím már használatban van.';
                }
            }
        }
        if (!$error && $changingPassword) {
            if ($newPassword === '' || $confirmPassword === '') {
                $error = 'Az új jelszó mezőinek kitöltése kötelező.';
            } elseif (strlen($newPassword) < 12) {
                $error = 'Az új jelszónak legalább 12 karakter hosszúnak kell lennie.';
            } elseif (!preg_match('/[A-ZÁÉÍÓÖŐÚÜŰ]/u', $newPassword) || !preg_match('/[a-záéíóöőúüű]/u', $newPassword) || !preg_match('/\d/', $newPassword)) {
                $error = 'Használj kis- és nagybetűt, valamint számot az új jelszóban.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Az új jelszó és a megerősítés nem egyezik.';
            } elseif ($newPassword === $currentPassword) {
                $error = 'Az új jelszó nem lehet azonos a jelenlegivel.';
            }
        }

        if (!$error) {
            $updateData = [];
            if ($emailChanged) {
                $updateData['email'] = $formEmail;
            }
            if ($changingPassword) {
                $updateData['password'] = $newPassword;
                $updateData['must_change_password'] = false;
            }
            if ($updateData) {
                $updateOk = auth_update_user((int)$CURRENT_USER['id'], $updateData, $updateErr);
                if ($updateOk) {
                    if ($changingPassword) {
                        auth_set_session_must_change(false);
                    }
                    $currentUserId = (int)$CURRENT_USER['id'];
                    $updatedUser = auth_find_user_by_id($currentUserId);
                    if (is_array($updatedUser)) {
                        $CURRENT_USER = $updatedUser;
                    }
                    $formEmail = trim((string)($CURRENT_USER['email'] ?? $formEmail));
                    $meta = [
                        'email_changed' => $emailChanged,
                        'password_changed' => $changingPassword,
                    ];
                    if ($emailChanged) {
                        $meta['previous_email'] = $originalEmail;
                        $meta['new_email'] = $formEmail;
                    }
                    try {
                        append_change_log_locked([
                            'rev' => read_current_revision(),
                            'entity' => 'user_profile',
                            'entity_id' => (string)$currentUserId,
                            'action' => 'updated',
                            'user_id' => $currentUserId,
                            'username' => $CURRENT_USER['username'] ?? null,
                            'meta' => $meta,
                        ]);
                    } catch (Throwable $logError) {
                        error_log('Profil naplózás hiba: ' . $logError->getMessage());
                    }
                    $info = $changingPassword ? 'A jelszó sikeresen frissült.' : 'A profil adatai sikeresen frissültek.';
                    header('Location: ' . ($returnTo ?: app_url_path('')));
                    exit;
                }
                $error = 'Nem sikerült frissíteni az adatokat. Próbáld meg később.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profilom – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('pic/favicon.png'), ENT_QUOTES) ?>" />
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card">
      <h1>Profilom</h1>
      <p class="auth-subtitle"><?= !empty($CURRENT_USER['must_change_password']) ? 'Az első bejelentkezéshez új jelszót kell beállítanod.' : 'Itt módosíthatod az email címedet és a jelszavadat.' ?></p>
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
          <span>Email cím</span>
          <input type="email" name="email" value="<?= htmlspecialchars($formEmail) ?>" />
        </label>
        <label>
          <span>Jelenlegi jelszó</span>
          <input type="password" name="current_password" required autocomplete="current-password" />
        </label>
        <label>
          <span>Új jelszó</span>
          <input type="password" name="new_password" autocomplete="new-password" minlength="12" />
        </label>
        <label>
          <span>Új jelszó megerősítése</span>
          <input type="password" name="confirm_password" autocomplete="new-password" minlength="12" />
        </label>
        <button type="submit" class="auth-submit">Változtatások mentése</button>
        <a class="auth-link" href="<?= htmlspecialchars($returnTo ?: app_url_path(''), ENT_QUOTES) ?>">Mégse, vissza az alkalmazásba</a>
      </form>
    </section>
  </main>
</body>
</html>
