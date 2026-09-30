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
    $messages = ['VERIFIED' => 'Receipt photo verified and sent to Accounting for review.', 'REJECTED' => 'The receipt photo could not be verified. Upload a clear photo of the valid Accounting receipt.', 'ERROR' => 'Receipt verification is temporarily unavailable. Your photo was deleted; please try again later.'];
    setFlashMessage($messages[$result['status']]);
} catch (Throwable $exception) {
    error_log('Accounting receipt upload failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to verify the receipt photo right now.');
}
redirectTo('/student/dashboard.php?station=ACCOUNTING');
