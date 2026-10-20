<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
$user = requireRole('ACCOUNTING_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/accounting/submissions.php');
}
try {
    $id = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new RuntimeException('Invalid submission.');
    }
    $database = getDatabaseConnection();
    $query = $database->prepare("SELECT c.id FROM accounting_receipts receipt INNER JOIN clearances c ON c.student_id=receipt.student_id AND c.academic_period_id=receipt.academic_period_id INNER JOIN academic_periods period ON period.id=c.academic_period_id AND period.status='ACTIVE' WHERE receipt.id=:id AND receipt.receipt_code LIKE 'PORTAL-%'");
    $query->execute(['id' => $id]);
    $clearanceId = (int) $query->fetchColumn();
    if (!$clearanceId) {
        throw new RuntimeException('Submission not found in the active semester.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'approve') {
        approveOfficeClearance($database, 'ACCOUNTING', $clearanceId, (int) $user['id'], (int) $id);
        setFlashMessage('Screenshot approved and Accounting clearance completed.');
    } elseif ($action === 'reject') {
        returnOfficeClearance($database, 'ACCOUNTING', $clearanceId, (int) $user['id'], trim((string) ($_POST['reason'] ?? '')), (int) $id);
        setFlashMessage('Screenshot rejected. The student has been notified of the reason.');
    } else {
        throw new RuntimeException('Invalid review action.');
    }
} catch (Throwable $exception) {
    error_log('Accounting screenshot review failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review this submission.');
}
redirectTo('/accounting/submissions.php');
