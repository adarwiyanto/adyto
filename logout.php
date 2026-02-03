<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
auth_logout();
header('Location: ' . url('/login.php'));
exit;
