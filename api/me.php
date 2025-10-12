<?php
declare(strict_types=1);

require __DIR__ . '/../common.php';
require __DIR__ . '/../src/auth/session_guard.php';

use function App\Auth\auth_db;
use function App\Auth\hash_password;
use function App\Auth\password_change_url;
use function App\Auth\require_login;
use function App\Auth\resolve_redirect;
use function App\Auth\user_payload;
use function App\Auth\wants_json;

auth_db(); // biztosítsuk, hogy a séma elérhető legyen

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$isJson = wants_json();

if ($method === 'GET') {
    $user = require_login(['allow_password_change' => true, 'api' => true]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'user' => user_payload($user),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo 'Method Not Allowed';
    exit;
}

$user = require_login(['allow_password_change' => true, 'api' => $isJson]);
$action = $_POST['action'] ?? '';
if ($action !== 'change_password') {
    http_response_code(400);
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'unknown_action'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Unknown action';
    }
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordAgain = (string) ($_POST['new_password_confirmation'] ?? '');
$redirectTarget = resolve_redirect($_POST['redirect'] ?? null);

$errors = [];
if ($currentPassword === '' || $newPassword === '' || $newPasswordAgain === '') {
    $errors[] = 'missing_fields';
}
if ($newPassword !== '' && strlen($newPassword) < 8) {
    $errors[] = 'password_too_short';
}
if ($newPassword !== $newPasswordAgain) {
    $errors[] = 'password_mismatch';
}
if (!password_verify($currentPassword, $user['password_hash'])) {
    $errors[] = 'invalid_current_password';
}

if (!empty($errors)) {
    if ($isJson) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'validation_failed',
            'details' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $params = [
        'error' => implode(',', $errors),
        'redirect' => $redirectTarget,
    ];
    header('Location: ' . password_change_url($params));
    exit;
}

$hash = hash_password($newPassword);
$timestamp = (new DateTimeImmutable('now'))->format(DATE_ATOM);
$pdo = auth_db();
$statement = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated WHERE id = :id');
$statement->execute([
    ':hash' => $hash,
    ':updated' => $timestamp,
    ':id' => (int) $user['id'],
]);

$updatedUserStmt = $pdo->prepare('SELECT id, username, email, password_hash, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
$updatedUserStmt->execute([':id' => (int) $user['id']]);
$updatedUser = $updatedUserStmt->fetch() ?: $user;

$_SESSION['user'] = $updatedUser;

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'redirect' => $redirectTarget,
        'user' => user_payload($updatedUser),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$separator = strpos($redirectTarget, '?') !== false ? '&' : '?';
header('Location: ' . $redirectTarget . $separator . 'password_changed=1');
exit;
