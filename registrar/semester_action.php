<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/semester_functions.php';
$user = requireRole('REGISTRAR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) { setFlashMessage('Unable to process that request.'); redirectTo('/registrar/dashboard.php'); }
try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_semester') {
        createRegistrarSemester($database, $user['id'], (string) ($_POST['school_year'] ?? ''), (string) ($_POST['semester'] ?? ''));
        setFlashMessage('The new semester is ready for enrollment import and office readiness confirmation.');
    } elseif ($action === 'open_clearance') {
        $count = openRegistrarClearance($database, $user['id']);
        setFlashMessage("Semester clearance opened for {$count} enrolled students.");
    } else { throw new RuntimeException('Invalid semester action.'); }
} catch (Throwable $exception) {
    error_log('Registrar semester action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to update the semester.');
}
redirectTo('/registrar/dashboard.php');
