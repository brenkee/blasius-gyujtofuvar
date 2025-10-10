<?php
require __DIR__ . '/common.php';

auth_logout();
header('Location: login.php');
exit;
