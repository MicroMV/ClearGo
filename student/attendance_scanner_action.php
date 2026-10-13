<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to read the attendance QR code. Please scan it again.');
    redirectTo('/student/attendance_scanner.php');
}

$token = strtolower(trim((string) ($_POST['token'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    setFlashMessage('This is not a valid ClearGo attendance QR code.');
    redirectTo('/student/attendance_scanner.php');
}

startSecureSession();
$_SESSION['pending_attendance_token'] = $token;
redirectTo('/student/attendance_checkin.php');
