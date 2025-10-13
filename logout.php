<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

$redirectTarget = auth_sanitize_redirect_target($_GET['redirect'] ?? '');

auth_logout();

if ($redirectTarget !== '') {
    header('Location: ' . $redirectTarget, true, 302);
    exit;
}

$loginUrl = base_url('login.php');
$separator = strpos($loginUrl, '?') === false ? '?' : '&';
$loginUrl .= $separator . 'logged_out=1';
header('Location: ' . $loginUrl, true, 302);
exit;
