<?php
declare(strict_types=1);

require __DIR__ . '/../common.php';
require __DIR__ . '/../src/auth/session_guard.php';

use function App\Auth\auth_session_start;
use function App\Auth\login_url;
use function App\Auth\logout_user;
use function App\Auth\resolve_redirect;
use function App\Auth\wants_json;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo 'Method Not Allowed';
    exit;
}

auth_session_start();
logout_user();

$redirectParam = null;
if ($method === 'POST') {
    $redirectParam = isset($_POST['redirect']) ? (string) $_POST['redirect'] : null;
} elseif (isset($_GET['redirect'])) {
    $redirectParam = (string) $_GET['redirect'];
}

$redirectTarget = resolve_redirect($redirectParam);
$loginUrl = login_url([
    'redirect' => $redirectTarget,
    'logged_out' => 1,
]);

if (wants_json()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'redirect' => $loginUrl,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(303);
header('Location: ' . $loginUrl);
exit;
