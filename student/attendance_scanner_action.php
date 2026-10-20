<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../organization/event_functions.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to read the attendance QR code. Please scan it again.');
    redirectTo('/student/attendance_scanner.php');
}

$token = is_string($_POST['token'] ?? null) ? strtolower(trim($_POST['token'])) : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    setFlashMessage('This is not a valid ClearGo attendance QR code.');
    redirectTo('/student/attendance_scanner.php');
}

try {
    $result = recordQrAttendance(getDatabaseConnection(), $token, (int) $user['id']);
    startSecureSession();
    unset($_SESSION['pending_attendance_token']);
    $_SESSION['attendance_confirmation'] = ['event_name'=>$result['context']['name'],'already_recorded'=>$result['already_recorded']];
} catch (Throwable $exception) {
    error_log('Automatic QR attendance failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Unable to record attendance. Please try again.');
    redirectTo('/student/dashboard.php#attendance');
}
redirectTo('/student/attendance_checkin.php');
