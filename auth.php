<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/auth_functions.php';

/**
 * Lazy cached PDO connection for authentication related queries.
 */
function auth_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $DATA_FILE;
    [$conn] = data_store_sqlite_open($DATA_FILE);
    auth_bootstrap_default_admin($conn);
    $pdo = $conn;
    return $pdo;
}

function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(parse_url(base_url(), PHP_URL_PATH) ?: '/', '/') ?: '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('gf_session');
    session_start();
}

function auth_current_user(): ?array
{
    auth_session_start();
    $user = $_SESSION['auth']['user'] ?? null;
    return is_array($user) ? $user : null;
}

function auth_store_session_user(array $user, bool $mustChangePassword): void
{
    auth_session_start();
    $_SESSION['auth'] = [
        'user' => $user,
        'must_change_password' => $mustChangePassword,
    ];
}

function auth_user_must_change_password(?array $user = null): bool
{
    auth_session_start();
    if ($user === null) {
        $user = auth_current_user();
    }
    if (!is_array($user)) {
        return false;
    }
    if (isset($_SESSION['auth']['must_change_password'])) {
        return (bool)$_SESSION['auth']['must_change_password'];
    }
    return ($user['username'] ?? null) === 'admin'
        && isset($user['created_at'], $user['updated_at'])
        && (string)$user['created_at'] === (string)$user['updated_at'];
}

function auth_fetch_user_by_username(string $username): ?array
{
    $pdo = auth_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, created_at, updated_at FROM users WHERE username = :username COLLATE NOCASE LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function auth_fetch_user_by_id(int $userId): ?array
{
    $pdo = auth_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function auth_attempt_login(string $username, string $password): array
{
    $username = trim($username);
    $user = $username !== '' ? auth_fetch_user_by_username($username) : null;
    $error = null;

    if (!$user || !password_verify($password, (string)($user['password_hash'] ?? ''))) {
        $error = 'invalid_credentials';
        return ['ok' => false, 'error' => $error];
    }

    if (password_needs_rehash((string)$user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        $newHash = auth_secure_hash($password);
        $pdo = auth_pdo();
        $now = gmdate('c');
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':hash' => $newHash,
            ':updated_at' => $now,
            ':id' => (int)$user['id'],
        ]);
        $user = auth_fetch_user_by_id((int)$user['id']) ?? $user;
    }

    $mustChange = auth_user_should_force_change($user);
    $sessionUser = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)($user['email'] ?? ''),
        'created_at' => (string)$user['created_at'],
        'updated_at' => (string)$user['updated_at'],
    ];

    auth_session_start();
    session_regenerate_id(true);
    auth_store_session_user($sessionUser, $mustChange);

    return ['ok' => true, 'user' => $sessionUser, 'must_change_password' => $mustChange];
}

function auth_user_should_force_change(array $user): bool
{
    $username = (string)($user['username'] ?? '');
    if ($username !== 'admin') {
        return false;
    }
    $created = (string)($user['created_at'] ?? '');
    $updated = (string)($user['updated_at'] ?? '');
    return $created !== '' && $created === $updated;
}

function auth_update_password(int $userId, string $newPassword): array
{
    $pdo = auth_pdo();
    $hash = auth_secure_hash($newPassword);
    $now = gmdate('c');
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':hash' => $hash,
        ':updated_at' => $now,
        ':id' => $userId,
    ]);
    $updatedUser = auth_fetch_user_by_id($userId);
    if (!$updatedUser) {
        throw new RuntimeException('user_not_found_after_update');
    }
    $sessionUser = [
        'id' => (int)$updatedUser['id'],
        'username' => (string)$updatedUser['username'],
        'email' => (string)($updatedUser['email'] ?? ''),
        'created_at' => (string)$updatedUser['created_at'],
        'updated_at' => (string)$updatedUser['updated_at'],
    ];
    auth_store_session_user($sessionUser, false);
    return $sessionUser;
}

function auth_logout(): void
{
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auth_require_login(array $options = []): array
{
    $redirect = $options['redirect'] ?? true;
    $respondJson = $options['json'] ?? false;

    auth_session_start();
    $user = auth_current_user();
    if ($user) {
        return $user;
    }

    if ($respondJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'unauthorized',
            'login_url' => base_url('login.php'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($redirect) {
        $target = base_url('login.php');
        $current = auth_sanitize_redirect($_SERVER['REQUEST_URI'] ?? null);
        if ($current !== null) {
            $target .= '?redirect=' . rawurlencode($current);
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    http_response_code(401);
    exit;
}

function auth_redirect_if_password_change_needed(): void
{
    $user = auth_current_user();
    if ($user && auth_user_must_change_password($user)) {
        $current = auth_sanitize_redirect($_SERVER['REQUEST_URI'] ?? null) ?? '/';
        $target = base_url('password.php');
        if (strpos($current, 'password.php') === false) {
            $targetWithRedirect = $target;
            if ($current !== '') {
                $targetWithRedirect .= '?redirect=' . rawurlencode($current);
            }
            header('Location: ' . $targetWithRedirect, true, 302);
            exit;
        }
    }
}

function auth_sanitize_redirect(?string $redirect): ?string
{
    if (!is_string($redirect)) {
        return null;
    }
    $redirect = trim($redirect);
    if ($redirect === '') {
        return null;
    }
    if (preg_match('~^https?://~i', $redirect)) {
        return null;
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }
    return $redirect;
}
