<?php
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url_path('login.php'));
    exit;
}

csrf_require_token_from_request('html');
auth_clear_session();

header('Location: ' . app_url_path('login.php'));
exit;
