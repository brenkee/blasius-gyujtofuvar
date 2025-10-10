<?php

const AUTH_ROLE_FULL_ADMIN = 'full-admin';
const AUTH_ROLE_EDITOR = 'editor';
const AUTH_ROLE_VIEWER = 'viewer';

$AUTH_CFG = [];
$AUTH_USERS_FILE = __DIR__ . '/users.json';
$AUTH_SESSION_INFO = null;

function auth_bootstrap(array $cfg) {
  global $AUTH_CFG, $AUTH_USERS_FILE, $AUTH_SESSION_INFO;

  $AUTH_CFG = isset($cfg['auth']) && is_array($cfg['auth']) ? $cfg['auth'] : [];
  $usersFile = __DIR__ . '/' . ($AUTH_CFG['users_file'] ?? 'users.json');
  $AUTH_USERS_FILE = $usersFile;

  $sessionName = isset($AUTH_CFG['session_name']) && is_string($AUTH_CFG['session_name']) && $AUTH_CFG['session_name'] !== ''
    ? $AUTH_CFG['session_name']
    : 'fuvar_session';

  $lifetimeDays = isset($AUTH_CFG['session_lifetime_days']) && is_numeric($AUTH_CFG['session_lifetime_days'])
    ? max(1, (int)$AUTH_CFG['session_lifetime_days'])
    : 30;
  $cookieLifetime = $lifetimeDays * 86400;

  if (!headers_sent()) {
    session_name($sessionName);
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookieParams = [
      'lifetime' => $cookieLifetime,
      'path' => '/',
      'domain' => '',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ];
    session_set_cookie_params($cookieParams);
    if (!ini_get('session.cookie_lifetime') || (int)ini_get('session.cookie_lifetime') < $cookieLifetime) {
      ini_set('session.cookie_lifetime', (string)$cookieLifetime);
    }
    if ((int)ini_get('session.gc_maxlifetime') < $cookieLifetime) {
      ini_set('session.gc_maxlifetime', (string)$cookieLifetime);
    }
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  if (!is_file($usersFile)) {
    auth_write_users([
      auth_build_user_record([
        'username' => 'admin',
        'password' => 'admin',
        'role' => AUTH_ROLE_FULL_ADMIN,
        'email' => ''], true)
    ]);
  }

  $users = auth_read_users();
  if (!is_array($users)) {
    $users = [];
  }
  if (empty($users)) {
    $users[] = auth_build_user_record([
      'username' => 'admin',
      'password' => 'admin',
      'role' => AUTH_ROLE_FULL_ADMIN,
      'email' => ''
    ], true);
    auth_write_users($users);
  } else {
    $hasAdmin = false;
    foreach ($users as $user) {
      if (($user['role'] ?? '') === AUTH_ROLE_FULL_ADMIN) {
        $hasAdmin = true;
        break;
      }
    }
    if (!$hasAdmin) {
      $users[] = auth_build_user_record([
        'username' => 'admin',
        'password' => 'admin',
        'role' => AUTH_ROLE_FULL_ADMIN,
        'email' => ''
      ], true);
      auth_write_users($users);
    }
  }

  $AUTH_SESSION_INFO = auth_build_session_info();
}

function auth_read_users() {
  global $AUTH_USERS_FILE;
  if (!is_file($AUTH_USERS_FILE)) {
    return [];
  }
  $raw = file_get_contents($AUTH_USERS_FILE);
  if ($raw === false || $raw === '') {
    return [];
  }
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return [];
  }
  return array_values(array_filter($decoded, function ($u) {
    return is_array($u) && isset($u['username']) && isset($u['password_hash']);
  }));
}

function auth_write_users(array $users) {
  global $AUTH_USERS_FILE;
  $payload = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $tmp = $AUTH_USERS_FILE . '.tmp';
  if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
    throw new RuntimeException('Nem sikerült menteni a felhasználói adatokat.');
  }
  if (!rename($tmp, $AUTH_USERS_FILE)) {
    throw new RuntimeException('Nem sikerült frissíteni a felhasználói adatokat.');
  }
}

function auth_user_lock(callable $callback) {
  global $AUTH_USERS_FILE;
  $lockFile = $AUTH_USERS_FILE . '.lock';
  $fh = fopen($lockFile, 'c+');
  if (!$fh) {
    throw new RuntimeException('Nem sikerült zárolni a felhasználói adatokat.');
  }
  try {
    if (!flock($fh, LOCK_EX)) {
      throw new RuntimeException('Nem sikerült zárolni a felhasználói adatokat.');
    }
    $result = $callback();
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
  } catch (Throwable $e) {
    flock($fh, LOCK_UN);
    fclose($fh);
    throw $e;
  }
}

function auth_build_user_record(array $input, $forcePassword = false) {
  $username = trim((string)($input['username'] ?? ''));
  if ($username === '') {
    throw new InvalidArgumentException('Hiányzó felhasználónév.');
  }
  $role = $input['role'] ?? AUTH_ROLE_VIEWER;
  if (!in_array($role, [AUTH_ROLE_FULL_ADMIN, AUTH_ROLE_EDITOR, AUTH_ROLE_VIEWER], true)) {
    $role = AUTH_ROLE_VIEWER;
  }
  $email = trim((string)($input['email'] ?? ''));
  $now = gmdate('c');
  $id = isset($input['id']) && is_string($input['id']) && $input['id'] !== ''
    ? $input['id']
    : 'usr_' . bin2hex(random_bytes(8));

  $passwordHash = $input['password_hash'] ?? null;
  if (!is_string($passwordHash) || $passwordHash === '' || $forcePassword) {
    $password = (string)($input['password'] ?? '');
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  }

  return [
    'id' => $id,
    'username' => $username,
    'password_hash' => $passwordHash,
    'role' => $role,
    'email' => $email,
    'created_at' => $input['created_at'] ?? $now,
    'updated_at' => $now
  ];
}

function auth_current_user() {
  if (!empty($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
    return $_SESSION['auth_user'];
  }
  return null;
}

function auth_current_role() {
  $user = auth_current_user();
  if (!$user) {
    return null;
  }
  return $user['role'] ?? null;
}

function auth_require_login() {
  $user = auth_current_user();
  if ($user) {
    return $user;
  }
  if (php_sapi_name() === 'cli') {
    return null;
  }
  if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'auth_required'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  header('Location: login.php');
  exit;
}

function auth_logout() {
  if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
  }
  $GLOBALS['AUTH_SESSION_INFO'] = auth_build_session_info();
}

function auth_attempt_login($username, $password) {
  $username = trim((string)$username);
  if ($username === '' || $password === '') {
    return false;
  }
  $users = auth_read_users();
  foreach ($users as $user) {
    if (strcasecmp($user['username'] ?? '', $username) !== 0) {
      continue;
    }
    if (!password_verify($password, $user['password_hash'] ?? '')) {
      continue;
    }
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
      'id' => $user['id'],
      'username' => $user['username'],
      'role' => $user['role'],
      'email' => $user['email'] ?? ''
    ];
    $_SESSION['auth_last_login'] = time();
    $GLOBALS['AUTH_SESSION_INFO'] = auth_build_session_info();
    return true;
  }
  return false;
}

function auth_public_user(?array $user = null) {
  if (!$user) {
    return null;
  }
  return [
    'id' => $user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'email' => $user['email'] ?? ''
  ];
}

function auth_role_priority($role) {
  switch ($role) {
    case AUTH_ROLE_FULL_ADMIN:
      return 3;
    case AUTH_ROLE_EDITOR:
      return 2;
    case AUTH_ROLE_VIEWER:
      return 1;
  }
  return 0;
}

function auth_require_role($role) {
  $current = auth_current_role();
  if ($current === null || auth_role_priority($current) < auth_role_priority($role)) {
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
      http_response_code(403);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    http_response_code(403);
    echo 'Hozzáférés megtagadva.';
    exit;
  }
}

function auth_user_has_role($role) {
  $current = auth_current_role();
  return $current !== null && auth_role_priority($current) >= auth_role_priority($role);
}

function auth_all_users() {
  $users = auth_read_users();
  return array_map('auth_public_user', $users);
}

function auth_find_user($id) {
  $users = auth_read_users();
  foreach ($users as $user) {
    if (($user['id'] ?? null) === $id) {
      return $user;
    }
  }
  return null;
}

function auth_save_user_record(array $user) {
  auth_user_lock(function () use ($user) {
    $users = auth_read_users();
    $updated = false;
    for ($i = 0; $i < count($users); $i++) {
      if (($users[$i]['id'] ?? null) === $user['id']) {
        $users[$i] = $user;
        $updated = true;
        break;
      }
    }
    if (!$updated) {
      $users[] = $user;
    }
    auth_write_users($users);
  });
}

function auth_delete_user($id) {
  auth_user_lock(function () use ($id) {
    $users = auth_read_users();
    $remaining = [];
    $deleted = null;
    foreach ($users as $user) {
      if (($user['id'] ?? null) === $id) {
        $deleted = $user;
        continue;
      }
      $remaining[] = $user;
    }
    if ($deleted && ($deleted['role'] ?? null) === AUTH_ROLE_FULL_ADMIN) {
      $hasAdmin = false;
      foreach ($remaining as $user) {
        if (($user['role'] ?? '') === AUTH_ROLE_FULL_ADMIN) {
          $hasAdmin = true;
          break;
        }
      }
      if (!$hasAdmin) {
        throw new RuntimeException('Legalább egy teljes admin felhasználónak maradnia kell.');
      }
    }
    auth_write_users($remaining);
    $GLOBALS['AUTH_SESSION_INFO'] = auth_build_session_info();
  });
}

function auth_create_user(array $data) {
  auth_user_lock(function () use ($data) {
    $users = auth_read_users();
    $username = trim((string)($data['username'] ?? ''));
    foreach ($users as $user) {
      if (strcasecmp($user['username'] ?? '', $username) === 0) {
        throw new RuntimeException('Már létezik ilyen felhasználónév.');
      }
    }
    $users[] = auth_build_user_record($data, true);
    auth_write_users($users);
    $GLOBALS['AUTH_SESSION_INFO'] = auth_build_session_info();
  });
}

function auth_update_user(array $data) {
  auth_user_lock(function () use ($data) {
    $users = auth_read_users();
    $id = $data['id'] ?? null;
    if (!$id) {
      throw new RuntimeException('Hiányzó felhasználói azonosító.');
    }
    $found = null;
    foreach ($users as $idx => $user) {
      if (($user['id'] ?? null) === $id) {
        $found = $users[$idx];
        break;
      }
    }
    if (!$found) {
      throw new RuntimeException('Felhasználó nem található.');
    }
    $username = trim((string)($data['username'] ?? $found['username']));
    if ($username === '') {
      throw new RuntimeException('A felhasználónév nem lehet üres.');
    }
    foreach ($users as $existing) {
      if (($existing['id'] ?? null) !== $id && strcasecmp($existing['username'] ?? '', $username) === 0) {
        throw new RuntimeException('Már létezik ilyen felhasználónév.');
      }
    }
    $role = $data['role'] ?? $found['role'];
    if (!in_array($role, [AUTH_ROLE_FULL_ADMIN, AUTH_ROLE_EDITOR, AUTH_ROLE_VIEWER], true)) {
      $role = $found['role'];
    }
    $email = trim((string)($data['email'] ?? $found['email'] ?? ''));
    $updated = $found;
    $updated['username'] = $username;
    $updated['role'] = $role;
    $updated['email'] = $email;
    $updated['updated_at'] = gmdate('c');
    if (!empty($data['password'])) {
      $updated['password_hash'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
    }

    if (($found['role'] ?? null) === AUTH_ROLE_FULL_ADMIN && $role !== AUTH_ROLE_FULL_ADMIN) {
      $hasOtherAdmin = false;
      foreach ($users as $existing) {
        if (($existing['id'] ?? null) === $id) {
          continue;
        }
        if (($existing['role'] ?? null) === AUTH_ROLE_FULL_ADMIN) {
          $hasOtherAdmin = true;
          break;
        }
      }
      if (!$hasOtherAdmin) {
        throw new RuntimeException('Legalább egy teljes admin felhasználó szükséges.');
      }
    }

    foreach ($users as $idx => $user) {
      if (($user['id'] ?? null) === $id) {
        $users[$idx] = $updated;
        break;
      }
    }

    auth_write_users($users);

    $current = auth_current_user();
    if ($current && ($current['id'] ?? null) === $id) {
      $_SESSION['auth_user'] = [
        'id' => $updated['id'],
        'username' => $updated['username'],
        'role' => $updated['role'],
        'email' => $updated['email'] ?? ''
      ];
    }
    $GLOBALS['AUTH_SESSION_INFO'] = auth_build_session_info();
  });
}

function auth_generate_csrf_token($key = 'default') {
  if (!isset($_SESSION['csrf_tokens'])) {
    $_SESSION['csrf_tokens'] = [];
  }
  $token = bin2hex(random_bytes(16));
  $_SESSION['csrf_tokens'][$key] = $token;
  return $token;
}

function auth_validate_csrf_token($token, $key = 'default') {
  if (!isset($_SESSION['csrf_tokens'][$key])) {
    return false;
  }
  $stored = $_SESSION['csrf_tokens'][$key];
  unset($_SESSION['csrf_tokens'][$key]);
  return hash_equals($stored, (string)$token);
}

function auth_build_session_info() {
  $user = auth_current_user();
  $abilities = [
    'view_data' => false,
    'edit_data' => false,
    'manage_users' => false
  ];
  if ($user) {
    $role = $user['role'] ?? AUTH_ROLE_VIEWER;
    $abilities['view_data'] = true;
    if ($role === AUTH_ROLE_FULL_ADMIN) {
      $abilities['edit_data'] = true;
      $abilities['manage_users'] = true;
    } elseif ($role === AUTH_ROLE_EDITOR) {
      $abilities['edit_data'] = true;
    }
  }
  return [
    'user' => auth_public_user($user),
    'abilities' => $abilities
  ];
}

function auth_session_info() {
  global $AUTH_SESSION_INFO;
  if ($AUTH_SESSION_INFO === null) {
    $AUTH_SESSION_INFO = auth_build_session_info();
  }
  return $AUTH_SESSION_INFO;
}

?>
