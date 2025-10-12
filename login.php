<?php
require_once __DIR__ . '/auth_lib.php';

$currentUser = auth_current_user();
$redirectInput = $_REQUEST['redirect'] ?? '';
$redirectTarget = auth_normalize_redirect(is_string($redirectInput) ? $redirectInput : '');
$errors = [];
$statusMessage = null;

if ($currentUser && !auth_user_must_change_password($currentUser)) {
    header('Location: ' . ($redirectTarget !== '/' ? $redirectTarget : '/'), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_require_csrf_from_request();
    $username = isset($_POST['username']) ? (string)$_POST['username'] : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    if (trim($username) === '' || trim($password) === '') {
        $errors[] = 'Adj meg felhasználónevet és jelszót!';
    } else {
        try {
            $user = auth_login($username, $password);
            $redirectFinal = auth_normalize_redirect($_POST['redirect'] ?? $redirectTarget);
            if (auth_user_must_change_password($user)) {
                header('Location: /admin.php?force=profile', true, 302);
                exit;
            }
            header('Location: ' . ($redirectFinal !== '/' ? $redirectFinal : '/'), true, 302);
            exit;
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'too_many_attempts') {
                $errors[] = 'Túl sok sikertelen próbálkozás. Próbáld újra néhány perc múlva!';
            } else {
                $errors[] = 'Hibás felhasználónév vagy jelszó.';
            }
        }
    }
}

$csrfToken = auth_generate_csrf_token();
$loginCfg = isset($CFG['auth']['login']) && is_array($CFG['auth']['login']) ? $CFG['auth']['login'] : [];
$brandTitle = (string)($loginCfg['title'] ?? ($CFG['app']['title'] ?? 'Belépés'));
$brandSubtitle = (string)($loginCfg['subtitle'] ?? 'Jelentkezz be a fiókodba');
$brandFooter = isset($loginCfg['footer']) ? (string)$loginCfg['footer'] : '';
$logoPath = isset($loginCfg['logo']) ? trim((string)$loginCfg['logo']) : '';
$backgroundColor = (string)($loginCfg['background'] ?? '#0f172a');
$backgroundImage = isset($loginCfg['background_image']) ? trim((string)$loginCfg['background_image']) : '';
$cardColor = (string)($loginCfg['card_background'] ?? '#ffffff');
$accentColor = (string)($loginCfg['accent'] ?? '#2563eb');
$textColor = (string)($loginCfg['text'] ?? '#111827');
$mutedColor = (string)($loginCfg['muted'] ?? '#6b7280');

$bodyStyles = [
    '--auth-bg:' . htmlspecialchars($backgroundColor, ENT_QUOTES),
    '--auth-card-bg:' . htmlspecialchars($cardColor, ENT_QUOTES),
    '--auth-accent:' . htmlspecialchars($accentColor, ENT_QUOTES),
    '--auth-text:' . htmlspecialchars($textColor, ENT_QUOTES),
    '--auth-muted:' . htmlspecialchars($mutedColor, ENT_QUOTES),
];
if ($backgroundImage !== '') {
    $bodyStyles[] = '--auth-bg-image:url(' . htmlspecialchars($backgroundImage, ENT_QUOTES) . ')';
}
$bodyStyleAttr = implode(';', $bodyStyles);
?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($brandTitle) ?></title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="stylesheet" href="public/auth.css" />
</head>
<body class="auth-body" style="<?= $bodyStyleAttr ?>">
    <div class="auth-card">
        <div class="auth-header">
            <?php if ($logoPath !== ''): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logó" class="auth-logo" />
            <?php endif; ?>
            <h1><?= htmlspecialchars($brandTitle) ?></h1>
            <p><?= htmlspecialchars($brandSubtitle) ?></p>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="auth-alert auth-alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php elseif ($statusMessage): ?>
            <div class="auth-alert auth-alert-info">
                <p><?= htmlspecialchars($statusMessage) ?></p>
            </div>
        <?php endif; ?>
        <form method="post" class="auth-form" autocomplete="on">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES) ?>" />
            <label class="auth-field">
                <span>Felhasználónév</span>
                <input type="text" name="username" autocomplete="username" required autofocus value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES) ?>" />
            </label>
            <label class="auth-field">
                <span>Jelszó</span>
                <input type="password" name="password" autocomplete="current-password" required />
            </label>
            <button type="submit" class="auth-submit">Belépés</button>
        </form>
        <div class="auth-footer">
            <a href="/">Vissza a főoldalra</a>
            <?php if ($brandFooter !== ''): ?>
                <span><?= htmlspecialchars($brandFooter) ?></span>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
