<?php

declare(strict_types=1);

require_once __DIR__ . '/security_functions.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/login.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process the sign-in request. Please try again.');
    redirectTo('/login.php');
}

$email = normalizedSchoolEmail($_POST['email'] ?? null);
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if (
    $email === '' || $password === '' || strlen($password) > 1024 || str_contains($password, "\0")
) {
    setFlashMessage('Invalid email or password.');
    redirectTo('/login.php');
}

try {
    $database = getDatabaseConnection();
    // Many students can share the campus NAT address. Account/browser limits remain
    // strict while the configurable aggregate IP allowance accommodates shared networks.
    $retry = consumeAuthLimits($database, [['login-account', $email, 5, 900],
        ['login-browser', session_id(), 20, 900], ['login-ip', authClientAddress(), authLimitSetting('AUTH_LOGIN_IP_LIMIT', 10000), 900]]);
    if ($retry > 0) { displayAuthRateLimit('/login.php', $retry); }
    $statement = $database->prepare(
        'SELECT id, email, password_hash, role, status, auth_version FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Authentication query failed: ' . $exception->getMessage());
    setFlashMessage('Unable to sign in right now. Please try again later.');
    redirectTo('/login.php');
}

$verified = password_verify($password, $user === false
    ? '$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq'
    : $user['password_hash']);
if ($user === false || !$verified) {
    setFlashMessage('Invalid email or password.');
    redirectTo('/login.php');
}

if ($user['status'] !== 'ACTIVE') {
    setFlashMessage('This account is currently inactive. Please visit the Registrar\'s Office for assistance.');
    redirectTo('/login.php');
}

if (!in_array($user['role'], APPLICATION_ROLES, true)) {
    error_log('Authentication rejected an account with an invalid role.');
    setFlashMessage('Unable to sign in right now. Please try again later.');
    redirectTo('/login.php');
}

try {
    clearLoginAccountLimit($database, $email);
    revokeCurrentRememberToken($database);
    if (($_POST['remember_me'] ?? null) === '1') { issueRememberToken($database, $user); }
    $database->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
    establishAuthenticatedSession($user);
} catch (Throwable $error) {
    error_log('Sign-in completion failed (' . get_class($error) . ').');
    setRememberCookie(null);
    setFlashMessage('Unable to sign in right now. Please try again later.'); redirectTo('/login.php');
}

if (
    $user['role'] === 'STUDENT'
    && preg_match('/^[a-f0-9]{64}$/', (string) ($_SESSION['pending_attendance_token'] ?? ''))
) {
    redirectTo('/student/attendance_checkin.php');
}

redirectToDashboard($user['role']);
