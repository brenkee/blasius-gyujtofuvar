<?php
require __DIR__ . '/common.php';

$user = auth_require_login(['allow_password_change' => true]);
$redirectParam = auth_normalize_redirect($_GET['redirect'] ?? null);
$targetUrl = auth_resolve_redirect($redirectParam, base_url('index.php'));

$pdo = auth_get_pdo();
if (!$pdo instanceof PDO) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(503);
    echo "Az adatbázis nem érhető el. Próbáld meg később.";
    exit;
}

auth_session_start();
if (empty($_SESSION['change_pw_csrf'])) {
    $_SESSION['change_pw_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['change_pw_csrf'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || $postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Érvénytelen űrlap. Frissítsd az oldalt, majd próbáld meg újra.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
            $errors[] = 'Minden mező kitöltése kötelező.';
        }
        if ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Az új jelszó és a megerősítés nem egyezik.';
        }
        if (strlen($newPassword) < 8) {
            $errors[] = 'Az új jelszónak legalább 8 karakter hosszúnak kell lennie.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $user['id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || !password_verify($currentPassword, (string)$row['password_hash'])) {
                    $errors[] = 'A jelenlegi jelszó nem megfelelő.';
                } elseif (password_verify($newPassword, (string)$row['password_hash'])) {
                    $errors[] = 'Az új jelszó nem egyezhet meg a jelenlegivel.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated WHERE id = :id');
                    $update->execute([
                        ':hash' => $newHash,
                        ':updated' => gmdate('c'),
                        ':id' => $row['id'],
                    ]);
                    session_regenerate_id(true);
                    auth_store_session_user($row, false);
                    unset($_SESSION['change_pw_csrf']);
                    header('Location: ' . $targetUrl);
                    exit;
                }
            } catch (Throwable $e) {
                error_log('Jelszócsere hiba: ' . $e->getMessage());
                $errors[] = 'Nem sikerült módosítani a jelszót. Próbáld meg később.';
            }
        }
    }
}

$logoutUrl = base_url('logout.php');
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Jelszó módosítása – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <style>
    :root { color-scheme: light dark; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(160deg, #eef2ff 0%, #e0f2fe 100%);
    }
    .card {
      background: rgba(255,255,255,0.92);
      border-radius: 16px;
      padding: clamp(20px, 4vw, 36px);
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
      width: min(480px, 95vw);
      backdrop-filter: blur(6px);
    }
    h1 {
      margin: 0 0 18px;
      font-size: clamp(1.5rem, 2vw + 1rem, 2.2rem);
      color: #0f172a;
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    label {
      display: flex;
      flex-direction: column;
      font-weight: 600;
      color: #1f2937;
      gap: 6px;
    }
    input[type="password"] {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(148, 163, 184, 0.7);
      font-size: 1rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    input[type="password"]:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }
    button[type="submit"] {
      background: linear-gradient(120deg, #16a34a, #22c55e);
      color: #fff;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      border-radius: 999px;
      padding: 12px 18px;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-shadow: 0 12px 24px rgba(34, 197, 94, 0.35);
    }
    button[type="submit"]:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 32px rgba(34, 197, 94, 0.45);
    }
    .errors {
      background: rgba(220, 38, 38, 0.08);
      border: 1px solid rgba(220, 38, 38, 0.2);
      color: #991b1b;
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 14px;
    }
    .hint {
      color: #475569;
      font-size: 0.9rem;
      margin-top: 14px;
      line-height: 1.5;
    }
    .logout-link {
      display: inline-block;
      margin-top: 18px;
      font-size: 0.9rem;
      color: #1d4ed8;
      text-decoration: none;
    }
    .logout-link:hover {
      text-decoration: underline;
    }
    @media (prefers-color-scheme: dark) {
      body { background: linear-gradient(160deg, #0f172a, #020617); }
      .card { background: rgba(15, 23, 42, 0.88); color: #e2e8f0; }
      h1 { color: #e2e8f0; }
      label { color: #e2e8f0; }
      input[type="password"] { background: rgba(15, 23, 42, 0.92); color: #e2e8f0; border-color: rgba(71, 85, 105, 0.7); }
      .hint { color: #cbd5f5; }
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Jelszó módosítása</h1>
    <?php if (!empty($errors)): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(base_url('change-password.php' . ($redirectParam ? '?redirect=' . rawurlencode($redirectParam) : '')), ENT_QUOTES) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
      <label>
        Jelenlegi jelszó
        <input type="password" name="current_password" autocomplete="current-password" required />
      </label>
      <label>
        Új jelszó
        <input type="password" name="new_password" autocomplete="new-password" required />
      </label>
      <label>
        Új jelszó megerősítése
        <input type="password" name="new_password_confirm" autocomplete="new-password" required />
      </label>
      <button type="submit">Jelszó frissítése</button>
    </form>
    <p class="hint">Javasolt legalább 8 karakteres, kis- és nagybetűt, számot és speciális jelet tartalmazó jelszót választani.</p>
    <a class="logout-link" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>">Kilépés</a>
  </div>
</body>
</html>
