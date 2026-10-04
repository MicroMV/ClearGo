<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that receipt submission.');
    redirectTo('/student/dashboard.php');
}

$studentFeeId = filter_var($_POST['student_fee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string) ($_POST['action'] ?? '');
if ($studentFeeId === false || !in_array($action, ['upload', 'lost'], true)) {
    setFlashMessage('Invalid Bookstore receipt request.');
    redirectTo('/student/dashboard.php');
}

$storedName = null;
$originalName = null;
$mimeType = null;
$storedPath = null;

try {
    if ($action === 'upload') {
        $file = $_FILES['receipt_image'] ?? [];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);
        $temporaryName = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $temporaryName === '' || !is_uploaded_file($temporaryName)) {
            throw new RuntimeException('Select a receipt image to upload.');
        }
        if ($size < 1 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('The receipt image must be no larger than 8 MB.');
        }
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryName) ?: '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mimeType])) {
            throw new RuntimeException('Upload a valid JPG, PNG, or WebP receipt image.');
        }
        $directory = __DIR__ . '/../storage/private/bookstore-receipts';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Secure receipt storage is unavailable.');
        }
        $storedName = bin2hex(random_bytes(20)) . '.' . $extensions[$mimeType];
        $storedPath = $directory . '/' . $storedName;
        $originalName = basename((string) ($file['name'] ?? 'receipt.' . $extensions[$mimeType]));
        if (!move_uploaded_file($temporaryName, $storedPath)) {
            throw new RuntimeException('The receipt image could not be stored.');
        }
    }

    $database = getDatabaseConnection();
    $database->beginTransaction();
    $feeQuery = $database->prepare(
        "SELECT student_fee.id,student_fee.status AS payment_status,fee.name,student.student_number,
                station_record.status AS station_status
         FROM student_bookstore_fees student_fee
         INNER JOIN students student ON student.id=student_fee.student_id AND student.user_id=:user_id
         INNER JOIN bookstore_fees fee ON fee.id=student_fee.fee_id AND fee.status='ACTIVE'
         INNER JOIN academic_periods period ON period.id=fee.academic_period_id AND period.status='ACTIVE' AND period.clearance_status='OPEN'
         INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         INNER JOIN stations station ON station.code='BOOKSTORE'
         INNER JOIN clearance_stations station_record ON station_record.clearance_id=clearance.id AND station_record.station_id=station.id
         WHERE student_fee.id=:student_fee_id FOR UPDATE"
    );
    $feeQuery->execute(['user_id' => $user['id'], 'student_fee_id' => $studentFeeId]);
    $fee = $feeQuery->fetch();
    if ($fee === false || in_array($fee['station_status'], ['LOCKED', 'APPROVED'], true)) {
        throw new RuntimeException('This Bookstore receipt requirement is not available.');
    }
    if ($fee['payment_status'] === 'PAID') {
        throw new RuntimeException('This event receipt has already been confirmed.');
    }

    $oldQuery = $database->prepare('SELECT status,stored_name FROM bookstore_receipt_submissions WHERE student_fee_id=:student_fee_id FOR UPDATE');
    $oldQuery->execute(['student_fee_id' => $studentFeeId]);
    $oldSubmission = $oldQuery->fetch();
    if ($oldSubmission !== false && in_array($oldSubmission['status'], ['SUBMITTED', 'LOST_REPORTED', 'VERIFIED'], true)) {
        throw new RuntimeException('This receipt submission is already waiting for confirmation.');
    }

    $status = $action === 'upload' ? 'SUBMITTED' : 'LOST_REPORTED';
    $save = $database->prepare(
        "INSERT INTO bookstore_receipt_submissions (student_fee_id,status,stored_name,original_name,mime_type,submitted_at)
         VALUES (:student_fee_id,:status,:stored_name,:original_name,:mime_type,NOW())
         ON DUPLICATE KEY UPDATE status=VALUES(status),stored_name=VALUES(stored_name),original_name=VALUES(original_name),mime_type=VALUES(mime_type),submitted_at=NOW(),reviewed_at=NULL,reviewed_by=NULL,return_reason=NULL"
    );
    $save->execute(['student_fee_id' => $studentFeeId, 'status' => $status, 'stored_name' => $storedName, 'original_name' => $originalName, 'mime_type' => $mimeType]);
    notifyActiveRoles(
        $database,
        ['BOOKSTORE_STAFF'],
        'Bookstore receipt awaiting review',
        $fee['student_number'] . ($action === 'upload' ? ' uploaded a receipt for ' : ' reported a lost receipt for ') . $fee['name'] . '. Open Submitted Requirements to review it.'
    );
    $database->commit();

    if ($oldSubmission !== false && !empty($oldSubmission['stored_name']) && $oldSubmission['stored_name'] !== $storedName) {
        $oldPath = __DIR__ . '/../storage/private/bookstore-receipts/' . basename((string) $oldSubmission['stored_name']);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    setFlashMessage($action === 'upload' ? 'Receipt uploaded. Please wait for Bookstore confirmation.' : 'Lost receipt reported. Please wait for Bookstore confirmation.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    if ($storedPath !== null && is_file($storedPath)) {
        @unlink($storedPath);
    }
    error_log('Bookstore receipt submission failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to submit the Bookstore receipt.');
}

redirectTo('/student/dashboard.php');
