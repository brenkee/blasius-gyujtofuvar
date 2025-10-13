<?php
require __DIR__ . '/common.php';

$redirectParam = auth_normalize_redirect($_GET['redirect'] ?? null);
$resolvedRedirect = auth_resolve_redirect($redirectParam, base_url('index.php'));
$currentUser = auth_session_user();
if ($currentUser !== null) {
    if (!empty($currentUser['force_password_change'])) {
        header('Location: ' . auth_build_password_change_url($redirectParam));
        exit;
    }
    header('Location: ' . $resolvedRedirect);
    exit;
}

$errors = [];
$pdo = auth_get_pdo();
if (!$pdo instanceof PDO) {
    $errors[] = 'Az adatbázis jelenleg nem érhető el. Próbáld meg később.';
}

auth_session_start();
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['login_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || $postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Érvénytelen űrlap. Frissítsd az oldalt, majd próbáld újra.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $errors[] = 'Add meg a felhasználónevet és a jelszót is.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $username]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$userRow || !password_verify($password, (string)$userRow['password_hash'])) {
                    $errors[] = 'A megadott felhasználónév vagy jelszó érvénytelen.';
                } else {
                    $needsRehash = password_needs_rehash((string)$userRow['password_hash'], PASSWORD_DEFAULT);
                    if ($needsRehash) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $update = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated WHERE id = :id');
                        $update->execute([
                            ':hash' => $newHash,
                            ':updated' => gmdate('c'),
                            ':id' => $userRow['id'],
                        ]);
                        $userRow['password_hash'] = $newHash;
                    }
                    $forceChange = ($userRow['username'] === 'admin' && password_verify('admin', (string)$userRow['password_hash']));
                    auth_login_user($userRow, $forceChange);
                    unset($_SESSION['login_csrf']);
                    if ($forceChange) {
                        header('Location: ' . auth_build_password_change_url($redirectParam));
                    } else {
                        header('Location: ' . $resolvedRedirect);
                    }
                    exit;
                }
            } catch (Throwable $e) {
                error_log('Login hiba: ' . $e->getMessage());
                $errors[] = 'Bejelentkezési hiba történt. Próbáld meg később.';
            }
        }
    }
}

$loginAction = auth_build_login_url($redirectParam);
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bejelentkezés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <style>
    :root {
      color-scheme: light dark;
    }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle at top, #eff6ff, #e2e8f0);
    }
    .login-card {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(6px);
      border-radius: 16px;
      padding: clamp(20px, 4vw, 32px);
      box-shadow: 0 15px 35px rgba(15, 23, 42, 0.2);
      width: min(420px, 92vw);
    }
    .login-card h1 {
      margin: 0 0 16px;
      font-size: clamp(1.4rem, 2vw + 1rem, 2rem);
      color: #1f2937;
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    label {
      font-weight: 600;
      font-size: 0.95rem;
      color: #1f2937;
    }
    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid rgba(148, 163, 184, 0.7);
      border-radius: 10px;
      font-size: 1rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: rgba(255,255,255,0.95);
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }
    button[type="submit"] {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: #fff;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      border-radius: 999px;
      padding: 12px 18px;
      cursor: pointer;
      box-shadow: 0 10px 18px rgba(37, 99, 235, 0.35);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(37, 99, 235, 0.4);
    }
    .error-box {
      background: rgba(220, 38, 38, 0.08);
      border: 1px solid rgba(220, 38, 38, 0.2);
      color: #991b1b;
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 12px;
    }
    .helper-text {
      font-size: 0.85rem;
      color: #475569;
      margin-top: 12px;
      line-height: 1.5;
    }
    @media (prefers-color-scheme: dark) {
      body {
        background: radial-gradient(circle at top, #0f172a, #020617);
      }
      .login-card {
        background: rgba(15, 23, 42, 0.85);
        color: #e2e8f0;
      }
      .login-card h1 { color: #e2e8f0; }
      label { color: #e2e8f0; }
      input[type="text"], input[type="password"] {
        background: rgba(15, 23, 42, 0.9);
        border-color: rgba(71, 85, 105, 0.7);
        color: #e2e8f0;
      }
      .helper-text { color: #cbd5f5; }
    }
  </style>
</head>
<body>
  <div class="login-card">
    <h1>Bejelentkezés</h1>
    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($loginAction, ENT_QUOTES) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
      <label>
        Felhasználónév
        <input type="text" name="username" autocomplete="username" required />
      </label>
      <label>
        Jelszó
        <input type="password" name="password" autocomplete="current-password" required />
      </label>
      <button type="submit">Belépés</button>
    </form>
    <p class="helper-text">
      Az első belépés után az <strong>admin</strong> felhasználónak kötelező jelszót módosítani.
    </p>
  </div>
</body>
</html>
