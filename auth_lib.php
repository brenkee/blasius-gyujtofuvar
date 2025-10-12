<?php
require_once __DIR__ . '/common.php';

if (!defined('AUTH_ROLE_ADMIN')) {
    define('AUTH_ROLE_ADMIN', 'admin');
}
if (!defined('AUTH_ROLE_EDITOR')) {
    define('AUTH_ROLE_EDITOR', 'editor');
}
if (!defined('AUTH_ROLE_VIEWER')) {
    define('AUTH_ROLE_VIEWER', 'viewer');
}

const AUTH_ROLE_PRIORITY = [
    AUTH_ROLE_VIEWER => 1,
    AUTH_ROLE_EDITOR => 2,
    AUTH_ROLE_ADMIN => 3,
];

if (!defined('AUTH_DUMMY_HASH')) {
    define('AUTH_DUMMY_HASH', '$2y$10$Qeqq7wa9u0edPFsKnz03muV64UI5rwo1clu7ut8fDKQg9AK4Fxd9y');
}

/** @var array<string, mixed> $CFG */

auth_bootstrap_session();

/**
 * Start the secure PHP session if needed.
 */
function auth_bootstrap_session(): void
{
    static $started = false;
    if ($started) {
        return;
    }

    global $CFG;
    $sessionCfg = $CFG['auth']['session'] ?? [];
    $sessionName = isset($sessionCfg['name']) && is_string($sessionCfg['name']) && $sessionCfg['name'] !== ''
        ? $sessionCfg['name']
        : 'GFSESSID';
    $cookieLifetime = isset($sessionCfg['lifetime']) ? (int)$sessionCfg['lifetime'] : 3600 * 12;
    $cookiePath = isset($sessionCfg['path']) && is_string($sessionCfg['path']) ? $sessionCfg['path'] : '/';
    $cookieDomain = isset($sessionCfg['domain']) && is_string($sessionCfg['domain']) ? $sessionCfg['domain'] : '';
    $cookieSecure = !empty($sessionCfg['secure']);
    $cookieHttpOnly = array_key_exists('httponly', $sessionCfg) ? (bool)$sessionCfg['httponly'] : true;
    $cookieSameSite = $sessionCfg['samesite'] ?? 'Lax';
    if (!in_array($cookieSameSite, ['Lax', 'Strict', 'None'], true)) {
        $cookieSameSite = 'Lax';
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $started = true;
        return;
    }

    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
        'path' => $cookiePath,
        'domain' => $cookieDomain,
        'secure' => $cookieSecure,
        'httponly' => $cookieHttpOnly,
        'samesite' => $cookieSecure && $cookieSameSite === 'None' ? 'None' : $cookieSameSite,
    ]);

    session_start([
        'use_strict_mode' => true,
        'cookie_samesite' => $cookieSecure && $cookieSameSite === 'None' ? 'None' : $cookieSameSite,
    ]);
    $started = true;

    if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
        $_SESSION['auth'] = [];
    }
    if (!isset($_SESSION['auth']['csrf_tokens']) || !is_array($_SESSION['auth']['csrf_tokens'])) {
        $_SESSION['auth']['csrf_tokens'] = [];
    }
}

/**
 * Return the shared PDO connection for authentication tables.
 */
function auth_get_pdo(): PDO
{
    static $pdo = null;
    global $DATA_FILE;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . $DATA_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    return $pdo;
}

/**
 * Return the currently authenticated user or null.
 *
 * @return array{id:int,username:string,email:string,role:string,must_change_password:bool,last_login_at:?string,created_at:?string,updated_at:?string}|null
 */
function auth_current_user(bool $refresh = false): ?array
{
    auth_bootstrap_session();
    $cache = $_SESSION['auth']['user_cache'] ?? null;
    if (!$refresh && is_array($cache) && isset($cache['data'], $cache['loaded_at']) && (time() - (int)$cache['loaded_at'] < 120)) {
        return $cache['data'];
    }

    $userId = $_SESSION['auth']['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, role, must_change_password, is_active, last_login_at, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$userId]);
    $row = $stmt->fetch();
    if (!$row || !(int)($row['is_active'] ?? 0)) {
        auth_logout();
        return null;
    }

    $user = [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'email' => (string)$row['email'],
        'role' => (string)$row['role'],
        'must_change_password' => !empty($row['must_change_password']),
        'last_login_at' => $row['last_login_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];

    $_SESSION['auth']['user_cache'] = ['data' => $user, 'loaded_at' => time()];
    return $user;
}

/**
 * Regenerate the session identifier safely.
 */
function auth_regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Attempt to authenticate using username + password.
 *
 * @throws RuntimeException on failure
 */
function auth_login(string $username, string $password): array
{
    auth_bootstrap_session();
    $username = trim($username);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (auth_rate_limited('login:' . $ip, 10, 300)) {
        throw new RuntimeException('too_many_attempts');
    }

    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, role, password_hash, must_change_password, is_active, last_login_at, created_at, updated_at FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    $hash = $row['password_hash'] ?? AUTH_DUMMY_HASH;
    $passwordOk = password_verify($password, (string)$hash);

    if (!$row || !(int)$row['is_active'] || !$passwordOk) {
        auth_register_rate_limit_failure('login:' . $ip);
        throw new RuntimeException('invalid_credentials');
    }

    auth_rate_limit_reset('login:' . $ip);

    auth_regenerate_session();
    $_SESSION['auth']['user_id'] = (int)$row['id'];
    $_SESSION['auth']['user_cache'] = [
        'data' => [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'email' => (string)$row['email'],
            'role' => (string)$row['role'],
            'must_change_password' => !empty($row['must_change_password']),
            'last_login_at' => $row['last_login_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ],
        'loaded_at' => time(),
    ];

    $update = $pdo->prepare('UPDATE users SET last_login_at = datetime("now"), updated_at = datetime("now") WHERE id = :id');
    $update->execute([':id' => (int)$row['id']]);

    return $_SESSION['auth']['user_cache']['data'];
}

/**
 * Destroy the active session.
 */
function auth_logout(): void
{
    auth_bootstrap_session();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        session_regenerate_id(true);
        session_destroy();
    }
}

/**
 * Ensure the user is authenticated. Redirects by default.
 */
function auth_require_login(bool $redirect = true): ?array
{
    $user = auth_current_user();
    if ($user) {
        return $user;
    }

    if ($redirect) {
        $target = '/login.php';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($uri !== '' && strpos($uri, 'login.php') === false) {
            $target .= '?redirect=' . urlencode($uri);
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ensure the logged in user has at least editor permissions.
 */
function auth_require_editor(): array
{
    $user = auth_require_login(false);
    if (!auth_user_has_role($user, AUTH_ROLE_EDITOR)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}

/**
 * Ensure the logged in user is admin.
 */
function auth_require_admin(): array
{
    $user = auth_require_login(false);
    if (!auth_user_has_role($user, AUTH_ROLE_ADMIN)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'admin_only'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}

/**
 * Determine if the user role is at least the required one.
 */
function auth_user_has_role(?array $user, string $requiredRole): bool
{
    if (!$user) {
        return false;
    }
    $currentPriority = AUTH_ROLE_PRIORITY[$user['role'] ?? ''] ?? 0;
    $requiredPriority = AUTH_ROLE_PRIORITY[$requiredRole] ?? PHP_INT_MAX;
    return $currentPriority >= $requiredPriority;
}

function auth_is_valid_role(string $role): bool
{
    return isset(AUTH_ROLE_PRIORITY[$role]);
}

/**
 * Whether the user must change the password.
 */
function auth_user_must_change_password(array $user): bool
{
    return !empty($user['must_change_password']);
}

/**
 * Generate a CSRF token and persist it in the session.
 */
function auth_generate_csrf_token(): string
{
    auth_bootstrap_session();
    $token = bin2hex(random_bytes(32));
    $_SESSION['auth']['csrf_tokens'][] = ['token' => $token, 'generated' => time()];
    if (count($_SESSION['auth']['csrf_tokens']) > 30) {
        $_SESSION['auth']['csrf_tokens'] = array_slice($_SESSION['auth']['csrf_tokens'], -30);
    }
    return $token;
}

/**
 * Validate a CSRF token from the session storage.
 */
function auth_validate_csrf_token(?string $token, bool $consume = true): bool
{
    if (!$token) {
        return false;
    }
    auth_bootstrap_session();
    $tokens = $_SESSION['auth']['csrf_tokens'] ?? [];
    foreach ($tokens as $idx => $row) {
        if (!is_array($row) || empty($row['token'])) {
            continue;
        }
        if (hash_equals($row['token'], $token)) {
            if ($consume) {
                unset($_SESSION['auth']['csrf_tokens'][$idx]);
            }
            return true;
        }
    }
    return false;
}

/**
 * Require a CSRF token from the HTTP request.
 */
function auth_require_csrf_from_request(): void
{
    $token = $_POST['_csrf'] ?? null;
    if (!$token && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if (!auth_validate_csrf_token(is_string($token) ? $token : null)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'invalid_csrf'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Rate limit helper using a local file store.
 */
function auth_rate_limited(string $key, int $maxAttempts, int $perSeconds): bool
{
    $entries = auth_rate_limit_read($key);
    $now = time();
    $entries = array_filter($entries, static fn($ts) => ($now - $ts) < $perSeconds);
    if (count($entries) >= $maxAttempts) {
        return true;
    }
    $entries[] = $now;
    auth_rate_limit_write($key, $entries);
    return false;
}

function auth_register_rate_limit_failure(string $key): void
{
    // Already handled in auth_rate_limited when called prior to login attempt.
    // This function exists for API-kompatibilitás miatt.
    if ($key === '') {
        return;
    }
}

function auth_rate_limit_reset(string $key): void
{
    $file = auth_rate_limit_file($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

function auth_rate_limit_read(string $key): array
{
    $file = auth_rate_limit_file($key);
    if (!is_file($file)) {
        return [];
    }
    $fh = @fopen($file, 'r');
    if (!$fh) {
        return [];
    }
    if (flock($fh, LOCK_SH)) {
        $data = stream_get_contents($fh);
        flock($fh, LOCK_UN);
    } else {
        $data = '';
    }
    fclose($fh);
    $decoded = json_decode($data, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_filter(array_map('intval', $decoded), static fn($ts) => $ts > 0));
}

function auth_rate_limit_write(string $key, array $entries): void
{
    $file = auth_rate_limit_file($key);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $fh = fopen($file, 'c+');
    if (!$fh) {
        return;
    }
    if (flock($fh, LOCK_EX)) {
        ftruncate($fh, 0);
        fwrite($fh, json_encode(array_values($entries)) ?: '[]');
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

function auth_rate_limit_file(string $key): string
{
    return __DIR__ . '/data/security/' . md5($key) . '.json';
}

/**
 * Sanitize and validate email addresses.
 */
function auth_sanitize_email(string $email): string
{
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    return strtolower($email);
}

/**
 * Sanitize usernames.
 */
function auth_sanitize_username(string $username): string
{
    $username = trim($username);
    return preg_replace('/[^a-z0-9._-]/i', '', $username) ?: '';
}

/**
 * Validate password complexity.
 */
function auth_validate_password_strength(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    $hasLetter = (bool)preg_match('/[A-Za-z]/', $password);
    $hasDigit = (bool)preg_match('/\d/', $password);
    return $hasLetter && $hasDigit;
}

/**
 * Update a user record.
 */
function auth_update_user(int $id, array $fields): void
{
    $allowed = ['username', 'email', 'password_hash', 'role', 'must_change_password', 'is_active'];
    $columns = [];
    $params = [':id' => $id];
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $columns[] = $key . ' = :' . $key;
        $params[':' . $key] = $value;
    }
    if (!$columns) {
        return;
    }
    $columns[] = 'updated_at = datetime("now")';
    $sql = 'UPDATE users SET ' . implode(', ', $columns) . ' WHERE id = :id';
    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Create a new user.
 */
function auth_create_user(array $data): int
{
    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, must_change_password, is_active) VALUES (:username, :email, :password_hash, :role, :must_change_password, :is_active)');
    $stmt->execute([
        ':username' => $data['username'],
        ':email' => $data['email'],
        ':password_hash' => $data['password_hash'],
        ':role' => $data['role'],
        ':must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
        ':is_active' => !empty($data['is_active']) ? 1 : 0,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Delete a user by id.
 */
function auth_delete_user(int $id): void
{
    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function auth_fetch_user_by_id(int $id): ?array
{
    $pdo = auth_get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, role, must_change_password, is_active, created_at, updated_at, last_login_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return auth_normalize_user_row($row);
}

function auth_fetch_all_users(): array
{
    $pdo = auth_get_pdo();
    $stmt = $pdo->query('SELECT id, username, email, role, must_change_password, is_active, created_at, updated_at, last_login_at FROM users ORDER BY username ASC');
    $users = [];
    if ($stmt !== false) {
        foreach ($stmt as $row) {
            if (is_array($row)) {
                $users[] = auth_normalize_user_row($row);
            }
        }
    }
    return $users;
}

function auth_normalize_user_row(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'username' => (string)($row['username'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'role' => (string)($row['role'] ?? AUTH_ROLE_VIEWER),
        'must_change_password' => !empty($row['must_change_password']),
        'is_active' => !empty($row['is_active']),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'last_login_at' => $row['last_login_at'] ?? null,
    ];
}

/**
 * Normalise redirect target to prevent open redirect.
 */
function auth_normalize_redirect(?string $target): string
{
    if (!$target) {
        return '/';
    }
    $target = trim($target);
    if ($target === '') {
        return '/';
    }
    if (preg_match('/^https?:/i', $target)) {
        return '/';
    }
    if ($target[0] !== '/') {
        return '/' . $target;
    }
    return $target;
}

/**
 * Remove cached user data (for example after updates).
 */
function auth_clear_user_cache(): void
{
    if (isset($_SESSION['auth']['user_cache'])) {
        unset($_SESSION['auth']['user_cache']);
    }
}
