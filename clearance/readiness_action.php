<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/readiness_functions.php';
$user = requireLogin();
if (!isset(READINESS_ROLE_STATIONS[$user['role']])) {
    http_response_code(403);
    exit('Access denied.');
}
$paths = [
    'DEAN' => '/dean/dashboard.php',
    'ORG_STAFF' => '/organization/dashboard.php',
    'SASO_STAFF' => '/saso/dashboard.php',
    'STUDENT_COUNCIL_STAFF' => '/student-council/dashboard.php',
    'LIBRARY_STAFF' => '/library/dashboard.php',
    'BOOKSTORE_STAFF' => '/bookstore/dashboard.php',
    'CLINIC_STAFF' => '/clinic/dashboard.php',
    'FACILITIES_STAFF' => '/facilities/dashboard.php',
    'COMLAB_STAFF' => '/comlab/dashboard.php',
    'ACCOUNTING_STAFF' => '/accounting/dashboard.php',
];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo($paths[$user['role']]);
}
try {
    $action = (string) ($_POST['action'] ?? 'ready');
    if ($action === 'unready') {
        markOfficeNotReady(getDatabaseConnection(), $user['id'], READINESS_ROLE_STATIONS[$user['role']]);
        setFlashMessage('Your office is marked not ready. Finish the remaining preparation before confirming again.');
    } elseif ($action === 'ready') {
        markOfficeReady(getDatabaseConnection(), $user['id'], READINESS_ROLE_STATIONS[$user['role']], trim((string) ($_POST['notes'] ?? '')));
        setFlashMessage('Your office is marked ready. The Registrar can see this confirmation.');
    } else {
        throw new RuntimeException('Invalid readiness action.');
    }
} catch (Throwable $exception) {
    error_log('Office readiness failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to update office readiness.');
}
redirectTo($paths[$user['role']]);
