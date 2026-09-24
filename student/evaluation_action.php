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
    $result = processEvaluationUpload(getDatabaseConnection(), $user['id'], $_FILES['evaluation_proof'] ?? []);
    $messages = [
        'VERIFIED' => 'Faculty Evaluation completion verified. It is ready for Dean review.',
        'REJECTED' => 'Your proof could not be verified. Please upload a valid screenshot and try again.',
        'ERROR' => 'Verification is temporarily unavailable. Your screenshot was deleted; please try again later.',
    ];
    setFlashMessage($messages[$result['status']]);
} catch (Throwable $exception) {
    error_log('Evaluation upload action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to verify the screenshot right now.');
}

redirectTo('/student/dashboard.php?station=DEAN');
