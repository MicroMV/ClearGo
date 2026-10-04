<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
function validateAccountingReceiptUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Select a portal screenshot to upload.');
    }
    $size = filter_var($file['size'] ?? null, FILTER_VALIDATE_INT);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('The portal screenshot must be no larger than 8 MB.');
    }
    $temporaryName = (string) ($file['tmp_name'] ?? '');
    if ($temporaryName === '' || !is_uploaded_file($temporaryName)) {
        throw new RuntimeException('The receipt upload could not be validated.');
    }
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryName);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType) || !isset($extensions[$mimeType]) || @getimagesize($temporaryName) === false) {
        throw new RuntimeException('Upload a valid JPG, PNG, or WebP portal screenshot.');
    }
    return ['temporary_name' => $temporaryName, 'mime_type' => $mimeType, 'extension' => $extensions[$mimeType]];
}

function processAccountingReceiptUpload(PDO $database, int $userId, array $file): array
{
    $validated = validateAccountingReceiptUpload($file);
    $directory = __DIR__ . '/../storage/private/accounting-receipts';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('Secure screenshot storage is unavailable.'); }
    $code = 'PORTAL-' . bin2hex(random_bytes(24));
    $path = $directory . '/' . $code . '.image';
    $database->beginTransaction();
    try {
        $query = $database->prepare("SELECT student.id AS student_id,student.student_number,period.id AS period_id,cs.status FROM students student INNER JOIN clearances clearance ON clearance.student_id=student.id INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' AND period.clearance_status='OPEN' INNER JOIN clearance_stations cs ON cs.clearance_id=clearance.id INNER JOIN stations station ON station.id=cs.station_id AND station.code='ACCOUNTING' WHERE student.user_id=:user_id AND student.status='ACTIVE' LIMIT 1 FOR UPDATE");
        $query->execute(['user_id' => $userId]);
        $context = $query->fetch();
        if (!$context || in_array($context['status'], ['LOCKED','APPROVED'], true)) { throw new RuntimeException('Accounting screenshot submission is unavailable for this clearance.'); }
        $pendingQuery = $database->prepare("SELECT id FROM accounting_receipts WHERE student_id=:student_id AND academic_period_id=:period_id AND status='SCANNED' AND receipt_code LIKE 'PORTAL-%' LIMIT 1");
        $pendingQuery->execute(['student_id' => $context['student_id'], 'period_id' => $context['period_id']]);
        if ($pendingQuery->fetchColumn() !== false) { throw new RuntimeException('Your Accounting screenshot has already been submitted and is awaiting review.'); }
        if (!move_uploaded_file($validated['temporary_name'], $path)) { throw new RuntimeException('The screenshot could not be stored.'); }
        // Existing SCANNED value represents a submission awaiting manual review.
        $insert = $database->prepare("INSERT INTO accounting_receipts (student_id,academic_period_id,receipt_code,status,scanned_at) VALUES (:student_id,:period_id,:code,'SCANNED',NOW())");
        $insert->execute(['student_id' => $context['student_id'], 'period_id' => $context['period_id'], 'code' => $code]);
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,new_status) SELECT :user_id,'ACCOUNTING_SCREENSHOT_SUBMITTED',id,:student_id,'TLC portal screenshot submitted for manual Accounting review.','SUBMITTED' FROM stations WHERE code='ACCOUNTING'");
        $audit->execute(['user_id' => $userId, 'student_id' => $context['student_id']]);
        notifyActiveRoles($database, ['ACCOUNTING_STAFF'], 'Accounting proof awaiting review', $context['student_number'] . ' submitted a TLC portal screenshot. Open Submitted Requirements to review it.');
        $database->commit();
        return ['status' => 'SUBMITTED'];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        if (is_file($path)) { unlink($path); }
        throw $exception;
    }
}
