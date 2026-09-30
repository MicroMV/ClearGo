<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/login.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process the sign-in request. Please try again.');
    redirectTo('/login.php');
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (
    $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !str_ends_with(strtolower($email), '@thelewiscollege.edu.ph')
) {
    setFlashMessage('Invalid email or password.');
    redirectTo('/login.php');
}

try {
    $database = getDatabaseConnection();
    $statement = $database->prepare(
        'SELECT id, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Authentication query failed: ' . $exception->getMessage());
    setFlashMessage('Unable to sign in right now. Please try again later.');
    redirectTo('/login.php');
}

if ($user === false || !password_verify($password, $user['password_hash'])) {
    setFlashMessage('Invalid email or password.');
    redirectTo('/login.php');
}

if ($user['status'] !== 'ACTIVE') {
    setFlashMessage('This account is currently inactive. Please contact the administrator.');
    redirectTo('/login.php');
}

if (!in_array($user['role'], APPLICATION_ROLES, true)) {
    error_log('Authentication rejected an account with an invalid role.');
    setFlashMessage('Unable to sign in right now. Please try again later.');
    redirectTo('/login.php');
}

session_regenerate_id(true);
$lastLogin = $database->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
$lastLogin->execute(['id' => $user['id']]);
$_SESSION['authenticated_user'] = [
    'id' => (int) $user['id'],
    'email' => $user['email'],
    'role' => $user['role'],
];

if (
    $user['role'] === 'STUDENT'
    && preg_match('/^[a-f0-9]{64}$/', (string) ($_SESSION['pending_attendance_token'] ?? ''))
) {
    redirectTo('/student/attendance_checkin.php');
}

redirectToDashboard($user['role']);
