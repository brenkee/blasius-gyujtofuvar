<?php
require __DIR__ . '/common.php';

auth_logout();
$redirectParam = auth_normalize_redirect($_GET['redirect'] ?? null);
header('Location: ' . auth_build_login_url($redirectParam));
exit;
