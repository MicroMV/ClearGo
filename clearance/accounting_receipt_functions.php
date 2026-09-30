<?php

declare(strict_types=1);

require_once __DIR__ . '/../verification/accounting_receipt_verifier.php';

function accountingReceiptUploadContext(PDO $database, int $userId): array
{
    $statement = $database->prepare("SELECT student.id AS student_id, student.user_id, clearance.id AS clearance_id, period.id AS academic_period_id, receipt.id AS receipt_id, receipt.receipt_code, receipt.status AS receipt_status, station_clearance.status AS accounting_status
        FROM students student
        INNER JOIN clearances clearance ON clearance.student_id=student.id
        INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' AND period.clearance_status='OPEN'
        INNER JOIN stations station ON station.code='ACCOUNTING'
        INNER JOIN clearance_stations station_clearance ON station_clearance.clearance_id=clearance.id AND station_clearance.station_id=station.id
        INNER JOIN accounting_receipts receipt ON receipt.student_id=student.id AND receipt.academic_period_id=period.id AND receipt.status='ISSUED'
        WHERE student.user_id=:user_id AND student.status='ACTIVE' AND station_clearance.status NOT IN ('LOCKED','APPROVED')
        ORDER BY receipt.id DESC LIMIT 1");
    $statement->execute(['user_id' => $userId]);
    $context = $statement->fetch();
    if ($context === false) {
        throw new RuntimeException('There is no Accounting-issued receipt available to verify.');
    }
    return $context;
}

function validateAccountingReceiptUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Select a receipt photo to upload.');
    }
    $size = filter_var($file['size'] ?? null, FILTER_VALIDATE_INT);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('The receipt photo must be no larger than 8 MB.');
    }
    $temporaryName = (string) ($file['tmp_name'] ?? '');
    if ($temporaryName === '' || !is_uploaded_file($temporaryName)) {
        throw new RuntimeException('The receipt upload could not be validated.');
    }
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryName);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType) || !isset($extensions[$mimeType]) || @getimagesize($temporaryName) === false) {
        throw new RuntimeException('Upload a valid JPG, PNG, or WebP receipt photo.');
    }
    return ['temporary_name' => $temporaryName, 'mime_type' => $mimeType, 'extension' => $extensions[$mimeType]];
}

function saveAccountingReceiptResult(PDO $database, array $context, array $result): void
{
    $database->beginTransaction();
    try {
        $lock = $database->prepare("SELECT status FROM accounting_receipts WHERE id=:id FOR UPDATE");
        $lock->execute(['id' => $context['receipt_id']]);
        if ($lock->fetchColumn() !== 'ISSUED') {
            throw new RuntimeException('This receipt is no longer available for verification.');
        }
        $verification = $database->prepare("INSERT INTO accounting_receipt_verifications (accounting_receipt_id,status,verification_method,model_name,confidence,attempt_count,last_submitted_at,verified_at) VALUES (:receipt_id,:status,'OPENAI_VISION',:model_name,:confidence,1,NOW(),CASE WHEN :verified_status='VERIFIED' THEN NOW() ELSE NULL END) ON DUPLICATE KEY UPDATE status=VALUES(status),model_name=VALUES(model_name),confidence=VALUES(confidence),attempt_count=attempt_count+1,last_submitted_at=NOW(),verified_at=CASE WHEN VALUES(status)='VERIFIED' THEN NOW() ELSE NULL END");
        $verification->execute(['receipt_id' => $context['receipt_id'], 'status' => $result['status'], 'verified_status' => $result['status'], 'model_name' => $result['model_name'], 'confidence' => $result['confidence']]);
        if ($result['status'] === 'VERIFIED') {
            $scan = $database->prepare("UPDATE accounting_receipts SET status='SCANNED',scanned_at=NOW() WHERE id=:id AND status='ISSUED'");
            $scan->execute(['id' => $context['receipt_id']]);
        }
        $notice = $database->prepare('INSERT INTO notifications (user_id,title,message) VALUES (:user_id,:title,:message)');
        $messages = match ($result['status']) {
            'VERIFIED' => ['Accounting receipt verified', 'Your receipt image matched the Accounting-issued receipt code. It is ready for Accounting review.'],
            'ERROR' => ['Accounting receipt verification unavailable', 'Your receipt image was not retained. Please try again when verification is available.'],
            default => ['Accounting receipt requires action', 'Your receipt image could not be matched to the Accounting-issued receipt. Upload a clearer valid receipt photo.'],
        };
        $notice->execute(['user_id' => $context['user_id'], 'title' => $messages[0], 'message' => $messages[1]]);
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status) SELECT :user_id,'ACCOUNTING_RECEIPT_CHECKED',id,:student_id,'Accounting receipt image was checked without retaining the uploaded image.',NULL,:status FROM stations WHERE code='ACCOUNTING' LIMIT 1");
        $audit->execute(['user_id' => $context['user_id'], 'student_id' => $context['student_id'], 'status' => $result['status']]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function processAccountingReceiptUpload(PDO $database, int $userId, array $file): array
{
    $context = accountingReceiptUploadContext($database, $userId);
    $validated = validateAccountingReceiptUpload($file);
    $directory = __DIR__ . '/../storage/private/accounting-receipts';
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Secure temporary receipt storage is unavailable.');
    }
    $path = $directory . '/' . bin2hex(random_bytes(24)) . '.' . $validated['extension'];
    if (!move_uploaded_file($validated['temporary_name'], $path)) {
        throw new RuntimeException('The receipt photo could not be stored securely for verification.');
    }
    try {
        try {
            $result = verifyAccountingReceiptPhoto($path, $validated['mime_type'], $userId, $context['receipt_code']);
        } catch (Throwable $exception) {
            error_log('Accounting receipt verification failed: ' . $exception->getMessage());
            $result = ['status' => 'ERROR', 'confidence' => null, 'model_name' => evaluationVisionModel()];
        }
        saveAccountingReceiptResult($database, $context, $result);
        return $result;
    } finally {
        if (is_file($path) && !unlink($path)) {
            error_log('Unable to delete a temporary Accounting receipt photo.');
        }
    }
}
