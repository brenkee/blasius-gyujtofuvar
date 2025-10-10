<?php
require __DIR__ . '/common.php';

auth_require_login();
auth_require_role(AUTH_ROLE_FULL_ADMIN);

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf'] ?? '';
  if (!auth_validate_csrf_token($token, 'admin_users')) {
    $errors[] = 'Érvénytelen kérés, próbáld újra.';
  } else {
    $action = $_POST['action'] ?? '';
    try {
      if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? AUTH_ROLE_VIEWER);
        if ($username === '' || $password === '') {
          throw new RuntimeException('A felhasználónév és a jelszó megadása kötelező.');
        }
        auth_create_user([
          'username' => $username,
          'email' => $email,
          'password' => $password,
          'role' => $role
        ]);
        $messages[] = 'Új felhasználó létrehozva.';
      } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = (string)($_POST['role'] ?? AUTH_ROLE_VIEWER);
        $password = (string)($_POST['password'] ?? '');
        if ($username === '') {
          throw new RuntimeException('A felhasználónév nem lehet üres.');
        }
        $payload = [
          'id' => $id,
          'username' => $username,
          'email' => $email,
          'role' => $role,
        ];
        if ($password !== '') {
          $payload['password'] = $password;
        }
        auth_update_user($payload);
        $messages[] = 'Felhasználó frissítve.';
      } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $current = auth_current_user();
        if ($current && ($current['id'] ?? '') === $id) {
          throw new RuntimeException('A saját felhasználódat nem törölheted.');
        }
        auth_delete_user($id);
        $messages[] = 'Felhasználó törölve.';
      } else {
        throw new RuntimeException('Ismeretlen művelet.');
      }
    } catch (RuntimeException $e) {
      $errors[] = $e->getMessage();
    }
  }
}

$users = auth_read_users();
$csrf = auth_generate_csrf_token('admin_users');
$roleOptions = [
  AUTH_ROLE_FULL_ADMIN => 'Teljes admin',
  AUTH_ROLE_EDITOR => 'Szerkesztő',
  AUTH_ROLE_VIEWER => 'Megtekintő',
];
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Felhasználókezelés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
  <style>
    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      margin: 0;
      padding: 32px 20px 80px;
      background: #f8fafc;
      color: #0f172a;
    }
    h1 {
      margin-top: 0;
      font-size: 28px;
    }
    .toolbar {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
    }
    .toolbar a {
      color: #2563eb;
      font-weight: 600;
      text-decoration: none;
    }
    .messages, .errors {
      margin-bottom: 18px;
      padding: 14px 16px;
      border-radius: 12px;
    }
    .messages {
      background: rgba(16,185,129,0.15);
      border: 1px solid rgba(16,185,129,0.4);
      color: #047857;
    }
    .errors {
      background: rgba(248,113,113,0.12);
      border: 1px solid rgba(248,113,113,0.4);
      color: #b91c1c;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 40px rgba(15,23,42,0.12);
      margin-bottom: 32px;
    }
    th, td {
      padding: 14px 16px;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
      font-size: 15px;
    }
    th {
      background: #f1f5f9;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.04em;
    }
    tr:last-child td {
      border-bottom: none;
    }
    form.inline {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 12px;
      align-items: end;
    }
    .inline label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #475569;
      margin-bottom: 4px;
    }
    .inline input,
    .inline select {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #cbd5f5;
      background: #fff;
      font-size: 14px;
    }
    .inline button {
      padding: 12px 18px;
      border-radius: 12px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      background: #2563eb;
      color: #fff;
      box-shadow: 0 10px 20px rgba(37,99,235,0.2);
    }
    .inline button.secondary {
      background: #64748b;
      box-shadow: none;
    }
    .inline button.danger {
      background: #dc2626;
    }
    .new-user {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 20px 40px rgba(15,23,42,0.12);
    }
    .new-user h2 {
      margin-top: 0;
      font-size: 22px;
    }
    .new-user form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      align-items: end;
    }
    .new-user label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #475569;
      margin-bottom: 4px;
    }
    .new-user input,
    .new-user select {
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      border: 1px solid #cbd5f5;
      font-size: 14px;
    }
    .new-user button {
      padding: 12px 18px;
      border-radius: 12px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      background: #16a34a;
      color: #fff;
      box-shadow: 0 12px 22px rgba(22,163,74,0.25);
    }
  </style>
</head>
<body>
  <h1>Felhasználókezelés</h1>
  <div class="toolbar">
    <a href="index.php">← Vissza az alkalmazásba</a>
    <a href="logout.php">Kijelentkezés</a>
  </div>
  <?php if ($messages): ?>
    <div class="messages">
      <?php foreach ($messages as $msg): ?>
        <div><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errors">
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>Felhasználónév</th>
        <th>E-mail</th>
        <th>Szerepkör</th>
        <th>Létrehozva</th>
        <th>Utoljára frissítve</th>
        <th>Műveletek</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <?php
          $createdAtText = '-';
          if (!empty($user['created_at'])) {
            $ts = strtotime($user['created_at']);
            if ($ts !== false) {
              $createdAtText = date('Y-m-d H:i', $ts);
            }
          }
          $updatedAtText = '-';
          if (!empty($user['updated_at'])) {
            $uts = strtotime($user['updated_at']);
            if ($uts !== false) {
              $updatedAtText = date('Y-m-d H:i', $uts);
            }
          }
        ?>
        <tr>
          <td><?= htmlspecialchars($user['username'] ?? '') ?></td>
          <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($roleOptions[$user['role']] ?? $user['role']) ?></td>
          <td><?= htmlspecialchars($createdAtText) ?></td>
          <td><?= htmlspecialchars($updatedAtText) ?></td>
          <td>
            <form class="inline" method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="id" value="<?= htmlspecialchars($user['id'] ?? '') ?>" />
              <div>
                <label>Felhasználónév</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required />
              </div>
              <div>
                <label>E-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" />
              </div>
              <div>
                <label>Szerepkör</label>
                <select name="role">
                  <?php foreach ($roleOptions as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= ($user['role'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label>Új jelszó (opcionális)</label>
                <input type="password" name="password" autocomplete="new-password" placeholder="********" />
              </div>
              <div>
                <button type="submit">Mentés</button>
              </div>
            </form>
            <form class="inline" method="post" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a felhasználót?');" style="margin-top:10px;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?= htmlspecialchars($user['id'] ?? '') ?>" />
              <button type="submit" class="danger">Törlés</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="new-user">
    <h2>Új felhasználó hozzáadása</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" name="action" value="create" />
      <div>
        <label>Felhasználónév</label>
        <input type="text" name="username" required />
      </div>
      <div>
        <label>E-mail</label>
        <input type="email" name="email" />
      </div>
      <div>
        <label>Szerepkör</label>
        <select name="role">
          <?php foreach ($roleOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Ideiglenes jelszó</label>
        <input type="password" name="password" required autocomplete="new-password" />
      </div>
      <div>
        <button type="submit">Felhasználó létrehozása</button>
      </div>
    </form>
  </div>
</body>
</html>
