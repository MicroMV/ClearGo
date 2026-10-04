<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/accounting_receipt_functions.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/student/dashboard.php?station=ACCOUNTING');
}
try {
    $result = processAccountingReceiptUpload(getDatabaseConnection(), $user['id'], $_FILES['accounting_receipt'] ?? []);
    setFlashMessage('TLC portal screenshot submitted. Please wait for Accounting review.');
} catch (Throwable $exception) {
    error_log('Accounting receipt upload failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to submit the portal screenshot right now.');
}
redirectTo('/student/dashboard.php?station=ACCOUNTING');
