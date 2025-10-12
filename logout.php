<?php
require_once __DIR__ . '/auth_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo 'A kijelentkezéshez POST kérés szükséges.';
    exit;
}

auth_require_csrf_from_request();
auth_logout();
header('Location: /login.php', true, 302);
exit;
