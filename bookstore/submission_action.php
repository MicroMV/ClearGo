<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('BOOKSTORE_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that review.');
    redirectTo('/bookstore/submissions.php');
}

try {
    $submissionId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($submissionId === false || !in_array($action, ['verify', 'return'], true)) {
        throw new RuntimeException('Invalid receipt review request.');
    }
    if ($action === 'return' && ($reason === '' || mb_strlen($reason) > 1000)) {
        throw new RuntimeException('Provide a reason for returning the receipt submission.');
    }

    $database = getDatabaseConnection();
    $database->beginTransaction();
    $query = $database->prepare(
        "SELECT submission.student_fee_id,submission.status,fee.name,student.user_id
         FROM bookstore_receipt_submissions submission
         INNER JOIN student_bookstore_fees student_fee ON student_fee.id=submission.student_fee_id
         INNER JOIN bookstore_fees fee ON fee.id=student_fee.fee_id
         INNER JOIN academic_periods period ON period.id=fee.academic_period_id AND period.status='ACTIVE'
         INNER JOIN students student ON student.id=student_fee.student_id
         WHERE submission.id=:id AND submission.status IN ('SUBMITTED','LOST_REPORTED') FOR UPDATE"
    );
    $query->execute(['id' => $submissionId]);
    $submission = $query->fetch();
    if ($submission === false) {
        throw new RuntimeException('This submission is no longer awaiting review.');
    }

    $status = $action === 'verify' ? 'VERIFIED' : 'RETURNED';
    $update = $database->prepare('UPDATE bookstore_receipt_submissions SET status=:status,reviewed_at=NOW(),reviewed_by=:user_id,return_reason=:reason WHERE id=:id');
    $update->execute(['status' => $status, 'user_id' => $user['id'], 'reason' => $action === 'return' ? $reason : null, 'id' => $submissionId]);
    if ($action === 'verify') {
        $paymentReference = $submission['status'] === 'LOST_REPORTED' ? 'LOST RECEIPT CONFIRMED' : 'RECEIPT IMAGE VERIFIED';
        $paid = $database->prepare("UPDATE student_bookstore_fees SET status='PAID',paid_at=NOW(),payment_reference=:reference,remarks=NULL,verified_by=:user_id,verified_at=NOW() WHERE id=:id");
        $paid->execute(['reference' => $paymentReference, 'user_id' => $user['id'], 'id' => $submission['student_fee_id']]);
    } else {
        $unpaid = $database->prepare("UPDATE student_bookstore_fees SET status='UNPAID',paid_at=NULL,payment_reference=NULL,remarks=:reason,verified_by=NULL,verified_at=NULL WHERE id=:id");
        $unpaid->execute(['reason' => $reason, 'id' => $submission['student_fee_id']]);
    }
    $notification = $database->prepare('INSERT INTO notifications (user_id,title,message) VALUES (:user_id,:title,:message)');
    $notification->execute([
        'user_id' => $submission['user_id'],
        'title' => $action === 'verify' ? 'Bookstore receipt confirmed' : 'Bookstore receipt returned',
        'message' => $action === 'verify' ? 'Your receipt requirement for ' . $submission['name'] . ' was confirmed.' : $reason,
    ]);
    $database->commit();
    setFlashMessage($action === 'verify' ? 'Receipt submission confirmed.' : 'Receipt submission returned to the student.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Bookstore receipt review failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review the receipt submission.');
}

redirectTo('/bookstore/submissions.php');
