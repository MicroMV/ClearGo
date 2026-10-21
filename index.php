<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/auth_check.php';
$user = authenticatedUser();
if ($user !== null) { redirectToDashboard($user['role']); }
redirectTo('/login.php');
