<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/event_functions.php';

$user = requireRole('STUDENT_COUNCIL_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that event request. Please try again.');
    redirectTo('/student-council/attendance.php');
}
try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        createStudentCouncilEvent($database, (int) $user['id'], (string) ($_POST['name'] ?? ''), (string) ($_POST['event_date'] ?? ''));
        setFlashMessage('Required institutional event created. Open its QR code when attendance begins.');
    } else {
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false) {
            throw new RuntimeException('Select a valid event.');
        }
        if ($action === 'open_qr') {
            $minutes = filter_var($_POST['minutes'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 720]]);
            if ($minutes === false) {
                throw new RuntimeException('Select a valid QR duration.');
            }
            $qr = openStudentCouncilEventQr($database, (int) $user['id'], (int) $eventId, (int) $minutes);
            startSecureSession();
            $_SESSION['student_council_qr_display'] = $qr;
            redirectTo('/student-council/event_qr.php?event=' . (int) $eventId);
        }
        if ($action === 'close_qr') {
            closeStudentCouncilEventQr($database, (int) $eventId);
            startSecureSession();
            unset($_SESSION['student_council_qr_display']);
            setFlashMessage('QR attendance is closed.');
        } elseif ($action === 'delete') {
            deleteStudentCouncilEvent($database, (int) $user['id'], (int) $eventId);
            startSecureSession();
            unset($_SESSION['student_council_qr_display']);
            setFlashMessage('Event and its attendance records were deleted.');
        } else {
            throw new RuntimeException('Unknown event action.');
        }
    }
} catch (Throwable $exception) {
    error_log('Student Council event action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to process the event request.');
}
redirectTo('/student-council/attendance.php');
