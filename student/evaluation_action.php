<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/evaluation_functions.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/student/dashboard.php?station=DEAN');
}

try {
    processEvaluationSubmission(getDatabaseConnection(), $user['id'], $_FILES['evaluation_proofs'] ?? []);
    setFlashMessage('Your screenshots were submitted anonymously for CCS Dean review.');
} catch (Throwable $exception) {
    error_log('Evaluation upload action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to verify the screenshot right now.');
}

redirectTo('/student/dashboard.php?station=DEAN');
