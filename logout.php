<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
    }

    session_destroy();
}

redirectTo('/login.php');
