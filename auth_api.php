<?php
require_once __DIR__ . '/auth_lib.php';

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($action) {
    case 'me':
        $user = auth_require_login(false);
        echo json_encode([
            'ok' => true,
            'user' => $user,
            'must_change_password' => auth_user_must_change_password($user),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'logout':
        require_method(['POST']);
        auth_require_csrf_from_request();
        auth_logout();
        echo json_encode(['ok' => true, 'redirect' => '/login.php'], JSON_UNESCAPED_UNICODE);
        break;

    case 'users':
        $currentUser = auth_require_admin();
        if ($method === 'GET') {
            $users = auth_fetch_all_users();
            echo json_encode(['ok' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
            break;
        }
        if ($method === 'POST') {
            auth_require_csrf_from_request();
            $payload = read_json_body();
            $username = auth_sanitize_username((string)($payload['username'] ?? ''));
            if ($username === '' || strlen($username) < 3) {
                json_error('Érvénytelen felhasználónév. Legalább 3 karakter hosszú legyen.', 422);
            }
            $email = auth_sanitize_email((string)($payload['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Érvénytelen e-mail cím.', 422);
            }
            $password = (string)($payload['password'] ?? '');
            if (!auth_validate_password_strength($password)) {
                json_error('A jelszónak legalább 8 karakterből kell állnia, tartalmazzon betűt és számot.', 422);
            }
            $role = isset($payload['role']) ? (string)$payload['role'] : AUTH_ROLE_VIEWER;
            if (!auth_is_valid_role($role)) {
                json_error('Érvénytelen szerepkör.', 422);
            }
            ensure_unique('username', $username);
            ensure_unique('email', $email);

            $id = auth_create_user([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'must_change_password' => !empty($payload['must_change_password']),
                'is_active' => !empty($payload['is_active']),
            ]);
            $user = auth_fetch_user_by_id($id);
            echo json_encode(['ok' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
            break;
        }
        method_not_allowed(['GET', 'POST']);
        break;

    case 'user':
        $currentUser = auth_require_admin();
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($userId <= 0) {
            json_error('Hiányzó felhasználó azonosító.', 400);
        }
        if ($method === 'GET') {
            $user = auth_fetch_user_by_id($userId);
            if (!$user) {
                json_error('A felhasználó nem található.', 404);
            }
            echo json_encode(['ok' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
            break;
        }
        if ($method === 'DELETE') {
            auth_require_csrf_from_request();
            if ($userId === (int)$currentUser['id']) {
                json_error('Saját fiók nem törölhető.', 409);
            }
            auth_delete_user($userId);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;
        }
        if ($method === 'PATCH' || $method === 'PUT' || $method === 'POST') {
            auth_require_csrf_from_request();
            $payload = read_json_body();
            $fields = [];

            if (array_key_exists('username', $payload)) {
                $username = auth_sanitize_username((string)$payload['username']);
                if ($username === '' || strlen($username) < 3) {
                    json_error('Érvénytelen felhasználónév.', 422);
                }
                ensure_unique('username', $username, $userId);
                $fields['username'] = $username;
            }
            if (array_key_exists('email', $payload)) {
                $email = auth_sanitize_email((string)$payload['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    json_error('Érvénytelen e-mail cím.', 422);
                }
                ensure_unique('email', $email, $userId);
                $fields['email'] = $email;
            }
            if (!empty($payload['password'])) {
                $password = (string)$payload['password'];
                if (!auth_validate_password_strength($password)) {
                    json_error('Gyenge jelszó. Minimum 8 karakter, tartalmazzon betűt és számot.', 422);
                }
                $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            if (array_key_exists('role', $payload)) {
                $role = (string)$payload['role'];
                if (!auth_is_valid_role($role)) {
                    json_error('Érvénytelen szerepkör.', 422);
                }
                if ($userId === (int)$currentUser['id'] && $role !== AUTH_ROLE_ADMIN) {
                    json_error('Saját fiókot nem fokozhatod le.', 409);
                }
                $fields['role'] = $role;
            }
            if (array_key_exists('is_active', $payload)) {
                $isActive = !empty($payload['is_active']);
                if ($userId === (int)$currentUser['id'] && !$isActive) {
                    json_error('Saját fiókot nem tilthatod le.', 409);
                }
                $fields['is_active'] = $isActive ? 1 : 0;
            }
            if (array_key_exists('must_change_password', $payload)) {
                $fields['must_change_password'] = !empty($payload['must_change_password']) ? 1 : 0;
            }

            if (!$fields) {
                json_error('Nincs módosítható mező.', 400);
            }

            auth_update_user($userId, $fields);
            if ($userId === (int)$currentUser['id']) {
                auth_clear_user_cache();
                $currentUser = auth_current_user(true) ?? $currentUser;
            }
            $user = auth_fetch_user_by_id($userId);
            echo json_encode(['ok' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
            break;
        }
        method_not_allowed(['GET', 'DELETE', 'PATCH']);
        break;

    case 'profile':
        $currentUser = auth_require_login(false);
        if ($method !== 'POST' && $method !== 'PATCH') {
            method_not_allowed(['POST', 'PATCH']);
        }
        auth_require_csrf_from_request();
        $payload = read_json_body();
        $fields = [];
        if (array_key_exists('username', $payload)) {
            $username = auth_sanitize_username((string)$payload['username']);
            if ($username === '' || strlen($username) < 3) {
                json_error('Érvénytelen felhasználónév.', 422);
            }
            ensure_unique('username', $username, (int)$currentUser['id']);
            $fields['username'] = $username;
        }
        if (array_key_exists('email', $payload)) {
            $email = auth_sanitize_email((string)$payload['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_error('Érvénytelen e-mail cím.', 422);
            }
            ensure_unique('email', $email, (int)$currentUser['id']);
            $fields['email'] = $email;
        }
        if (!empty($payload['password'])) {
            $password = (string)$payload['password'];
            if (!auth_validate_password_strength($password)) {
                json_error('Gyenge jelszó. Minimum 8 karakter, tartalmazzon betűt és számot.', 422);
            }
            if (isset($payload['password_confirm']) && $payload['password_confirm'] !== $payload['password']) {
                json_error('A jelszó megerősítése nem egyezik.', 422);
            }
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $fields['must_change_password'] = 0;
        }
        if (!$fields) {
            json_error('Nincs módosítható mező.', 400);
        }
        auth_update_user((int)$currentUser['id'], $fields);
        auth_clear_user_cache();
        $updated = auth_current_user(true);
        echo json_encode(['ok' => true, 'user' => $updated], JSON_UNESCAPED_UNICODE);
        break;

    default:
        json_error('Ismeretlen művelet.', 404);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_error('Érvénytelen JSON törzs.', 400);
    }
    return $decoded;
}

function ensure_unique(string $field, string $value, ?int $excludeId = null): void
{
    $pdo = auth_get_pdo();
    $sql = 'SELECT COUNT(*) FROM users WHERE ' . $field . ' = :value';
    $params = [':value' => $value];
    if ($excludeId !== null) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int)$stmt->fetchColumn() > 0) {
        $labels = ['username' => 'felhasználónév', 'email' => 'e-mail cím'];
        $label = $labels[$field] ?? $field;
        json_error('A megadott ' . $label . ' már használatban van.', 409);
    }
}

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function method_not_allowed(array $allowed): void
{
    header('Allow: ' . implode(', ', $allowed));
    json_error('Nem támogatott metódus.', 405);
}

function require_method(array $allowed): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        method_not_allowed($allowed);
    }
}
