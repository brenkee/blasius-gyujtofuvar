<?php
require __DIR__ . '/common.php';
$CURRENT_USER = auth_require_login();
if (!auth_user_can($CURRENT_USER, 'manage_users')) {
    http_response_code(403);
    echo 'Hozzáférés megtagadva.';
    exit;
}

function log_user_admin_event(array $actor, string $action, array $meta = [], $entityId = null): void {
    try {
        $entry = [
            'rev' => read_current_revision(),
            'entity' => 'user_admin',
            'action' => $action,
        ];
        if ($entityId !== null) {
            $entry['entity_id'] = (string)$entityId;
        }
        if (isset($actor['id']) && is_numeric($actor['id'])) {
            $entry['user_id'] = (int)$actor['id'];
        }
        if (!empty($actor['username']) && is_string($actor['username'])) {
            $entry['username'] = (string)$actor['username'];
        }
        if (!empty($meta)) {
            $entry['meta'] = $meta;
        }
        append_change_log_locked($entry);
    } catch (Throwable $e) {
        error_log('Audit napló írási hiba: ' . $e->getMessage());
    }
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
                $createdUser = auth_find_user_by_username($username);
                $targetId = $createdUser['id'] ?? null;
                $meta = [
                    'target' => [
                        'id' => $targetId,
                        'username' => $createdUser['username'] ?? $username,
                        'role' => $createdUser['role'] ?? $role,
                        'email' => $createdUser['email'] ?? $email,
                    ],
                    'must_change_password' => (bool)$mustChange,
                ];
                log_user_admin_event($CURRENT_USER, 'created_user', $meta, $targetId);
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
                            $updatedUser = auth_find_user_by_id($userId);
                            if (!is_array($updatedUser)) {
                                $updatedUser = [
                                    'id' => $userId,
                                    'username' => $username,
                                    'email' => $email,
                                    'role' => $role,
                                    'must_change_password' => $mustChange,
                                ];
                            }
                            $changes = [];
                            $fieldsToTrack = ['username', 'email', 'role'];
                            foreach ($fieldsToTrack as $field) {
                                $beforeVal = $existing[$field] ?? null;
                                $afterVal = $updatedUser[$field] ?? null;
                                if ($beforeVal !== $afterVal) {
                                    $changes[$field] = ['before' => $beforeVal, 'after' => $afterVal];
                                }
                            }
                            $beforeMust = !empty($existing['must_change_password']);
                            $afterMust = !empty($updatedUser['must_change_password']);
                            if ($beforeMust !== $afterMust) {
                                $changes['must_change_password'] = ['before' => $beforeMust, 'after' => $afterMust];
                            }
                            $meta = [
                                'target' => [
                                    'id' => $updatedUser['id'] ?? $userId,
                                    'username' => $updatedUser['username'] ?? $existing['username'],
                                    'role' => $updatedUser['role'] ?? $existing['role'],
                                    'email' => $updatedUser['email'] ?? $existing['email'],
                                ],
                            ];
                            if (!empty($changes)) {
                                $meta['changes'] = $changes;
                            }
                            if ($password !== '') {
                                $meta['password_changed'] = true;
                            }
                            log_user_admin_event($CURRENT_USER, 'updated_user', $meta, $updatedUser['id'] ?? $userId);
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
                        $meta = [
                            'target' => [
                                'id' => $existing['id'] ?? $userId,
                                'username' => $existing['username'] ?? '',
                                'role' => $existing['role'] ?? null,
                                'email' => $existing['email'] ?? null,
                            ],
                        ];
                        log_user_admin_event($CURRENT_USER, 'deleted_user', $meta, $existing['id'] ?? $userId);
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
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('public/styles.css'), ENT_QUOTES) ?>" />
  <style>
    .admin-page{max-width:960px;margin:24px auto;padding:0 16px;display:grid;gap:20px}
    .admin-header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
    .admin-users{display:grid;gap:16px}
    .user-card{border:1px solid var(--border);background:var(--panel);border-radius:12px;padding:16px 18px;display:grid;gap:14px}
    .user-card h2{margin:0;font-size:18px}
    .user-card small{color:var(--muted);display:block;margin-top:4px}
    .user-card form{display:grid;gap:14px}
    .user-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
    .user-fields label{display:grid;gap:6px;font-size:13px;color:var(--muted)}
    .user-fields input[type="text"],.user-fields input[type="email"],.user-fields input[type="password"],.user-fields select{padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--panel);color:var(--text)}
    .user-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .user-actions .checkbox{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)}
    .btn-primary{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:600;cursor:pointer}
    .btn-primary:hover{filter:brightness(.95)}
    .btn-danger{background:#dc2626;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:600;cursor:pointer}
    .btn-danger:hover{filter:brightness(.9)}
    .notice{padding:12px 16px;border-radius:10px;font-size:14px}
    .notice-success{background:rgba(22,163,74,0.12);border:1px solid rgba(22,163,74,0.35);color:#14532d}
    .notice-error{background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.35);color:#7f1d1d}
    .create-card{border:1px dashed var(--border);padding:18px;border-radius:12px;background:var(--panel);display:grid;gap:14px}
    .create-card h2{margin:0;font-size:18px}
    .back-link{color:var(--accent);text-decoration:none;font-weight:600}
    .back-link:hover{text-decoration:underline}
  </style>
</head>
<body>
  <main class="admin-page">
    <div class="admin-header">
      <div>
        <h1>Felhasználók kezelése</h1>
        <p class="admin-subtitle">Új felhasználók létrehozása és meglévők módosítása.</p>
      </div>
      <a class="back-link" href="<?= htmlspecialchars(app_url_path('index.php'), ENT_QUOTES) ?>">&larr; Vissza az alkalmazásba</a>
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
          <button class="btn-primary" type="submit">Felhasználó létrehozása</button>
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
              <button class="btn-primary" type="submit">Mentés</button>
            </div>
          </form>
          <?php if ((int)$user['id'] !== (int)$CURRENT_USER['id']): ?>
            <form method="post" onsubmit="return confirm('Biztosan törlöd ezt a felhasználót?');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="mode" value="delete" />
              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>" />
              <button class="btn-danger" type="submit">Felhasználó törlése</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </main>
</body>
</html>
