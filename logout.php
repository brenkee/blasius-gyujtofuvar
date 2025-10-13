<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

auth_logout();
$redirectParam = auth_sanitize_redirect($_GET['redirect'] ?? null);
$target = base_url('login.php');
if ($redirectParam) {
    $target .= '?redirect=' . rawurlencode($redirectParam);
}
header('Location: ' . $target, true, 302);
exit;
