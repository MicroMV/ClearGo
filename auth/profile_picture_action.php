<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_picture_functions.php';
require_once __DIR__ . '/../config/database.php';

$user = requireLogin();
$returnPath = $user['role'] === 'STUDENT' ? '/student/dashboard.php#account' : '/auth/profile.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo($returnPath);
}
try {
    $file = $_FILES['profile_picture'] ?? [];
    if (!is_array($file)) {
        throw new RuntimeException('Choose a valid picture to upload.');
    }
    saveProfilePicture(getDatabaseConnection(), $user, $file);
    setFlashMessage('Your profile picture has been updated.');
} catch (Throwable $exception) {
    error_log('Profile picture update failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Unable to update your profile picture. Please try again.');
}
redirectTo($returnPath);
