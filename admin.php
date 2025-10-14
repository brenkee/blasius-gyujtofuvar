<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();
if (!auth_user_can($CURRENT_USER, 'manage_users')) {
    http_response_code(403);
    echo 'Hozzáférés megtagadva.';
    exit;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_token_from_request('html');
    $mode = $_POST['mode'] ?? '';
    if ($mode === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = auth_normalize_role($_POST['role'] ?? 'editor');
        $password = (string)($_POST['password'] ?? '');
        $mustChange = isset($_POST['must_change_password']) ? !empty($_POST['must_change_password']) : true;
        if ($username === '' || $password === '') {
            $errors[] = 'Az új felhasználóhoz kötelező megadni a felhasználónevet és a jelszót.';
        } else {
            $createOk = auth_create_user([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'password' => $password,
                'must_change_password' => $mustChange,
            ], $err);
            if ($createOk) {
                $success = 'Új felhasználó létrehozva.';
            } else {
                switch ($err) {
                    case 'username_taken':
                        $errors[] = 'A megadott felhasználónév már létezik.';
                        break;
                    case 'empty_username':
                        $errors[] = 'A felhasználónév megadása kötelező.';
                        break;
                    case 'empty_password':
                        $errors[] = 'Az új felhasználóhoz jelszót kell megadni.';
                        break;
                    default:
                        $errors[] = 'Az új felhasználó mentése nem sikerült.';
                        break;
                }
            }
        }
    } elseif ($mode === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $errors[] = 'Érvénytelen felhasználó azonosító.';
        } else {
            $existing = auth_find_user_by_id($userId);
            if (!$existing) {
                $errors[] = 'A felhasználó nem található.';
            } else {
                $username = trim((string)($_POST['username'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $role = auth_normalize_role($_POST['role'] ?? $existing['role']);
                $password = (string)($_POST['password'] ?? '');
                $mustChange = isset($_POST['must_change_password']) && $_POST['must_change_password'] === '1';

                if ($username === '') {
                    $errors[] = 'A felhasználónév nem lehet üres.';
                } else {
                    if ($existing['role'] === 'full-admin' && $role !== 'full-admin') {
                        $adminCount = auth_count_users_with_role('full-admin');
                        if ($adminCount <= 1) {
                            $errors[] = 'Legalább egy teljes admin felhasználóra szükség van.';
                        }
                    }
                    if (!$errors) {
                        $updateData = [
                            'username' => $username,
                            'email' => $email,
                            'role' => $role,
                            'must_change_password' => $mustChange,
                        ];
                        if ($password !== '') {
                            $updateData['password'] = $password;
                        }
                        $updateOk = auth_update_user($userId, $updateData, $err);
                        if ($updateOk) {
                            $success = 'Felhasználó frissítve.';
                        } else {
                            switch ($err) {
                                case 'username_taken':
                                    $errors[] = 'A megadott felhasználónév már használatban van.';
                                    break;
                                case 'db_error':
                                    $errors[] = 'Az adatok mentése nem sikerült.';
                                    break;
                                default:
                                    $errors[] = 'A felhasználó frissítése nem sikerült.';
                                    break;
                            }
                        }
                    }
                }
            }
        }
    } elseif ($mode === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $errors[] = 'Érvénytelen felhasználó azonosító.';
        } elseif ($userId === (int)$CURRENT_USER['id']) {
            $errors[] = 'A saját felhasználói fiókodat nem törölheted.';
        } else {
            $existing = auth_find_user_by_id($userId);
            if (!$existing) {
                $errors[] = 'A felhasználó nem található.';
            } else {
                if ($existing['role'] === 'full-admin') {
                    $adminCount = auth_count_users_with_role('full-admin');
                    if ($adminCount <= 1) {
                        $errors[] = 'Legalább egy teljes admin felhasználóra szükség van.';
                    }
                }
                if (!$errors) {
                    $deleteErr = null;
                    $deleteOk = auth_delete_user($userId, $deleteErr);
                    if ($deleteOk) {
                        $success = 'Felhasználó törölve.';
                    } else {
                        $errors[] = 'A felhasználó törlése nem sikerült.';
                    }
                }
            }
        }
    }
}

$users = auth_list_users();
$roles = auth_valid_roles();
$csrfToken = csrf_get_token();
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin – felhasználók kezelése</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/halfmoon@2.0.1/css/halfmoon.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/halfmoon@2.0.1/css/halfmoon-modern.min.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
</head>
<body>
  <main class="admin-page">
    <div class="admin-header">
      <div>
        <h1>Felhasználók kezelése</h1>
        <p class="admin-subtitle">Új felhasználók létrehozása és meglévők módosítása.</p>
      </div>
      <a class="admin-back-link btn-link" href="<?= htmlspecialchars(app_url_path('index.php'), ENT_QUOTES) ?>">&larr; Vissza az alkalmazásba</a>
    </div>

    <?php if ($success): ?>
      <div class="notice notice-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <?php foreach ($errors as $err): ?>
        <div class="notice notice-error"><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <section class="create-card">
      <h2>Új felhasználó hozzáadása</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="mode" value="create" />
        <div class="user-fields">
          <label>
            <span>Felhasználónév</span>
            <input type="text" name="username" required />
          </label>
          <label>
            <span>Email</span>
            <input type="email" name="email" />
          </label>
          <label>
            <span>Szerepkör</span>
            <select name="role">
              <?php foreach ($roles as $role): ?>
                <option value="<?= htmlspecialchars($role, ENT_QUOTES) ?>"><?= htmlspecialchars($role) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span>Jelszó</span>
            <input type="password" name="password" required />
          </label>
        </div>
        <div class="user-actions">
          <label class="checkbox">
            <input type="checkbox" name="must_change_password" value="1" checked /> Kötelező jelszócsere az első belépéskor
          </label>
          <button class="btn btn-primary" type="submit">Felhasználó létrehozása</button>
        </div>
      </form>
    </section>

    <section class="admin-users">
      <?php foreach ($users as $user): ?>
        <article class="user-card">
          <div>
            <h2><?= htmlspecialchars($user['username']) ?></h2>
            <small>Azonosító: <?= (int)$user['id'] ?> &bull; Utolsó frissítés: <?= htmlspecialchars($user['updated_at'] ?? '-') ?></small>
          </div>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
            <input type="hidden" name="mode" value="update" />
            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>" />
            <div class="user-fields">
              <label>
                <span>Felhasználónév</span>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required />
              </label>
              <label>
                <span>Email</span>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" />
              </label>
              <label>
                <span>Szerepkör</span>
                <select name="role">
                  <?php foreach ($roles as $role): ?>
                    <option value="<?= htmlspecialchars($role, ENT_QUOTES) ?>" <?= $role === $user['role'] ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>Új jelszó (opcionális)</span>
                <input type="password" name="password" placeholder="&nbsp;" />
              </label>
            </div>
            <div class="user-actions">
              <label class="checkbox">
                <input type="checkbox" name="must_change_password" value="1" <?= !empty($user['must_change_password']) ? 'checked' : '' ?> /> Kötelező jelszócsere
              </label>
              <button class="btn btn-primary" type="submit">Mentés</button>
            </div>
          </form>
          <?php if ((int)$user['id'] !== (int)$CURRENT_USER['id']): ?>
            <form method="post" onsubmit="return confirm('Biztosan törlöd ezt a felhasználót?');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="mode" value="delete" />
              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>" />
              <button class="btn btn-danger" type="submit">Felhasználó törlése</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
