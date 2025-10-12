<?php
declare(strict_types=1);

require __DIR__ . '/../common.php';
require __DIR__ . '/../src/auth/session_guard.php';

use function App\Auth\auth_session_start;
use function App\Auth\find_user_by_username;
use function App\Auth\login_url;
use function App\Auth\login_user;
use function App\Auth\password_change_url;
use function App\Auth\requires_password_change;
use function App\Auth\resolve_redirect;
use function App\Auth\wants_json;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}

auth_session_start();

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$redirectParam = isset($_POST['redirect']) ? (string) $_POST['redirect'] : null;
$redirectTarget = resolve_redirect($redirectParam);

if ($username === '' || $password === '') {
    $error = 'missing_credentials';
    if (wants_json()) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . login_url([
        'error' => $error,
        'redirect' => $redirectTarget,
        'username' => $username,
    ]));
    exit;
}

$user = find_user_by_username($username);
if (!$user || !password_verify($password, $user['password_hash'])) {
    if (wants_json()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid_credentials'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . login_url([
        'error' => 'invalid_credentials',
        'redirect' => $redirectTarget,
        'username' => $username,
    ]));
    exit;
}

login_user($user);

$target = $redirectTarget;
if (requires_password_change($user)) {
    $target = password_change_url(['redirect' => $redirectTarget]);
}

http_response_code(303);
header('Location: ' . $target);
exit;
