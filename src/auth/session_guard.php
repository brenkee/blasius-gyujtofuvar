<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;
use PDOException;

/**
 * Session guard és segédfüggvények a beléptetéshez.
 */

/** @var array $CFG */

global $CFG;

const SESSION_NAME = 'BGFSESSID';

/**
 * Visszaadja az alkalmazás alapútvonalát (pl. "" vagy "/alap/utvonal").
 */
function base_path(): string
{
    global $CFG;
    $base = isset($CFG['base_url']) ? trim((string) $CFG['base_url']) : '';
    if ($base === '' || $base === '/' || $base === '.') {
        return '';
    }
    $parts = parse_url($base);
    if ($parts !== false && isset($parts['path'])) {
        $path = '/' . trim($parts['path'], '/');
    } else {
        $path = '/' . trim($base, '/');
    }
    if ($path === '/' || $path === '') {
        return '';
    }
    return $path;
}

/**
 * Base path-hoz illesztett abszolút útvonalat ad vissza (mindig per jellel kezdve).
 */
function path_with_base(string $path): string
{
    $normalized = '/' . ltrim($path, '/');
    $base = base_path();
    if ($base === '') {
        return $normalized;
    }
    if ($normalized === '/') {
        return $base;
    }
    return rtrim($base, '/') . $normalized;
}

/**
 * Kiszámolja a login URL-t opcionális query paraméterekkel.
 */
function login_url(array $params = []): string
{
    return append_query(path_with_base('public/login.html'), $params);
}

/**
 * Kiszámolja a jelszócsere oldal URL-jét opcionális query paraméterekkel.
 */
function password_change_url(array $params = []): string
{
    return append_query(path_with_base('public/change-password.php'), $params);
}

/**
 * Query paramétereket illeszt az URL végére.
 */
function append_query(string $url, array $params): string
{
    if (empty($params)) {
        return $url;
    }
    $query = http_build_query($params);
    return $url . (strpos($url, '?') !== false ? '&' : '?') . $query;
}

/**
 * Biztonságosan elindítja a sessiont HttpOnly süti paraméterekkel.
 */
function auth_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $cookiePath = base_path();
    if ($cookiePath === '') {
        $cookiePath = '/';
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Visszaadja az authentikációs SQLite adatbázis PDO kapcsolatát.
 */
function auth_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $CFG;
    $relative = $CFG['files']['auth_db_file'] ?? 'data/auth.db';
    $baseDir = dirname(__DIR__, 2);
    $dbPath = $baseDir . '/' . ltrim($relative, '/');
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new PDOException('Nem hozható létre az auth adatbázis könyvtára: ' . $dir);
        }
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Séma biztosítása (idempotens CREATE TABLE IF NOT EXISTS).
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    return $pdo;
}

/**
 * Visszaadja a belépett felhasználót (ha van) és frissíti a cache-t.
 */
function current_user(bool $refresh = false): ?array
{
    auth_session_start();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    if (!isset($_SESSION['user']) || $refresh) {
        $user = find_user_by_id((int) $_SESSION['user_id']);
        if (!$user) {
            logout_user();
            return null;
        }
        $_SESSION['user'] = $user;
    }
    return is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Betölti a felhasználót ID alapján.
 */
function find_user_by_id(int $id): ?array
{
    $stmt = auth_db()->prepare('SELECT id, username, email, password_hash, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Betölti a felhasználót felhasználónév alapján.
 */
function find_user_by_username(string $username): ?array
{
    $stmt = auth_db()->prepare('SELECT id, username, email, password_hash, created_at, updated_at FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Visszaadja, hogy a felhasználónak kötelező-e jelszót változtatni.
 */
function requires_password_change(array $user): bool
{
    $created = $user['created_at'] ?? '';
    $updated = $user['updated_at'] ?? '';
    return $created !== '' && $created === $updated;
}

/**
 * Biztonságosan hash-eli a jelszót.
 */
function hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Bejelentkezteti a felhasználót.
 */
function login_user(array $user): void
{
    auth_session_start();
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user'] = $user;
}

/**
 * Kijelentkezteti az aktuális felhasználót.
 */
function logout_user(): void
{
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Megállapítja, hogy a kliens JSON választ vár-e.
 */
function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return strtolower($requestedWith) === 'xmlhttprequest';
}

/**
 * A jelenlegi kéréshez tartozó (base path-szal kezdődő) relatív útvonal.
 */
function current_request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $path . $query;
}

/**
 * Ellenőrzi, hogy a megadott cél URL ugyanazon az alkalmazás bázison belül van-e.
 */
function is_safe_redirect_target(string $target): bool
{
    if ($target === '') {
        return false;
    }
    $parts = parse_url($target);
    if ($parts === false) {
        return false;
    }
    if (isset($parts['scheme']) || isset($parts['host'])) {
        return false;
    }
    $path = $parts['path'] ?? '';
    if ($path === '') {
        return false;
    }
    $base = base_path();
    if ($base !== '') {
        $normalizedBase = rtrim($base, '/') . '/';
        $normalizedPath = rtrim($path, '/') . '/';
        if (strncmp($normalizedPath, $normalizedBase, strlen($normalizedBase)) !== 0) {
            return false;
        }
    }
    return true;
}

/**
 * Meghatározza az átirányítási célt (biztonságosan).
 */
function resolve_redirect(?string $preferred = null): string
{
    $fallback = base_path();
    if ($fallback === '') {
        $fallback = '/';
    }
    if ($preferred && is_safe_redirect_target($preferred)) {
        return $preferred;
    }
    return $fallback;
}

/**
 * Nem hitelesített kérés kezelése.
 */
function handle_unauthenticated(array $options = []): void
{
    $redirect = $options['redirect_to'] ?? current_request_path();
    $redirect = resolve_redirect($redirect);
    if (($options['api'] ?? false) || wants_json()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'unauthenticated',
            'login_url' => login_url(['redirect' => $redirect]),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . login_url(['redirect' => $redirect, 'error' => 'session_required']));
    exit;
}

/**
 * Jelszócsere kényszer kezelése.
 */
function handle_password_change_required(array $options = []): void
{
    $redirect = $options['redirect_to'] ?? current_request_path();
    $redirect = resolve_redirect($redirect);
    if (($options['api'] ?? false) || wants_json()) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'password_change_required',
            'change_url' => password_change_url(['redirect' => $redirect]),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . password_change_url(['redirect' => $redirect]));
    exit;
}

/**
 * Kötelező beléptetés biztosítása.
 */
function require_login(array $options = []): array
{
    $user = current_user();
    if (!$user) {
        handle_unauthenticated($options);
    }
    if (requires_password_change($user) && empty($options['allow_password_change'])) {
        handle_password_change_required($options);
    }
    return $user;
}

/**
 * Felhasználó metainformáció (API válaszhoz) összeállítása.
 */
function user_payload(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'username' => (string) ($user['username'] ?? ''),
        'email' => $user['email'] ?? null,
        'requires_password_change' => requires_password_change($user),
    ];
}
