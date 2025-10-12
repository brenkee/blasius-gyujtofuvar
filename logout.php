<?php
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_logout();
}

header('Location: index.php');
exit;
