<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/event_functions.php';

$user = requireRole('ORG_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that event request. Please try again.');
    redirectTo('/organization/dashboard.php');
}

try {
    $database = getDatabaseConnection();
    $organization = assignedOrganization($database, $user['id']);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $startTime = trim((string) ($_POST['start_time'] ?? '')) ?: null;
        $endTime = trim((string) ($_POST['end_time'] ?? '')) ?: null;
        createOrganizationEvent(
            $database,
            $user['id'],
            (int) $organization['id'],
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (string) ($_POST['event_date'] ?? ''),
            $startTime,
            $endTime
        );
        setFlashMessage('Required event created. Students can now record attendance when you open its QR code.');
    } elseif ($action === 'open_qr') {
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $minutes = filter_var($_POST['minutes'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($eventId === false || $minutes === false) { throw new RuntimeException('Select a valid event and QR duration.'); }
        $qr = openEventQr($database, $user['id'], (int) $organization['id'], (int) $eventId, (int) $minutes);
        startSecureSession();
        $_SESSION['organization_qr_display'] = $qr;
        redirectTo('/organization/event_qr.php?event=' . (int) $eventId);
    } elseif ($action === 'close_qr') {
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false) { throw new RuntimeException('Select a valid event.'); }
        closeEventQr($database, $user['id'], (int) $organization['id'], (int) $eventId);
        startSecureSession();
        unset($_SESSION['organization_qr_display']);
        setFlashMessage('QR attendance is closed.');
    } else {
        throw new RuntimeException('Unknown event action.');
    }
} catch (Throwable $exception) {
    error_log('Organization event action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to process the event request.');
}

redirectTo('/organization/dashboard.php');
