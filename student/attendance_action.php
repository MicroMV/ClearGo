<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../organization/event_functions.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to confirm attendance. Please scan the QR code again.');
    redirectTo('/student/dashboard.php');
}
$token = strtolower(trim((string) ($_POST['token'] ?? '')));
try {
    $result = recordQrAttendance(getDatabaseConnection(), $token, $user['id']);
    startSecureSession();
    unset($_SESSION['pending_attendance_token']);
    setFlashMessage($result['already_recorded']
        ? 'Your attendance was already recorded for this event.'
        : 'Attendance confirmed for ' . $result['context']['name'] . '.');
} catch (Throwable $exception) {
    error_log('QR attendance recording failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to record attendance right now.');
}
redirectTo('/student/dashboard.php');
