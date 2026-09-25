<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/office_workflow.php';

$user = requireLogin();
try {
    $config = officeConfigForRole($user['role']);
} catch (RuntimeException $exception) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo($config['path']);
}

function positivePostId(string $name): int
{
    $value = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) { throw new RuntimeException('The selected record is invalid.'); }
    return (int) $value;
}

function officeMutationNotice(PDO $database, array $context, int $userId, string $message): void
{
    notifyAndAuditOffice($database, $context, $userId, $context['station_name'] . '_RECORD_UPDATED', $message, $context['station_status'], $context['station_status']);
}

try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    $clearanceId = positivePostId('clearance_id');
    $context = officeClearanceContext($database, $config['code'], $clearanceId);
    if ($context['station_status'] === 'LOCKED') {
        throw new RuntimeException('The required preceding offices have not cleared this student yet.');
    }
    $studentId = (int) $context['student_id'];
    $periodId = (int) $context['academic_period_id'];
    ensureOfficeSourceRecords($database, $studentId, $periodId);

    if ($action === 'manual_accounting_clear' && $config['code'] === 'ACCOUNTING') {
        manuallyClearAccounting($database, $clearanceId, $user['id']);
        setFlashMessage('Accounting manually cleared ' . $context['student_name'] . '.');
    } elseif ($action === 'manual_registrar_clear' && $config['code'] === 'REGISTRAR') {
        manuallyClearRegistrar($database, $clearanceId, $user['id']);
        setFlashMessage('Registrar manually completed clearance for ' . $context['student_name'] . '.');
    } elseif ($action === 'clinic_clear' && $config['code'] === 'CLINIC') {
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if (mb_strlen($remarks) > 1000) { throw new RuntimeException('Clinic remarks cannot exceed 1,000 characters.'); }
        $cleared = clearClinicClearance($database, $clearanceId, $user['id'], $remarks ?: 'Cleared directly by the Clinic.');
        setFlashMessage($cleared ? 'Clinic clearance approved for ' . $context['student_name'] . '.' : 'This student is already cleared by the Clinic.');
    } elseif ($action === 'library_clear' && $config['code'] === 'LIBRARY') {
        $cleared = clearLibraryClearance($database, $clearanceId, $user['id']);
        setFlashMessage($cleared ? 'Library clearance completed for ' . $context['student_name'] . '.' : 'This student is already cleared by the Library.');
    } elseif ($action === 'approve') {
        approveOfficeClearance($database, $config['code'], $clearanceId, $user['id']);
        setFlashMessage($config['title'] . ' approved for ' . $context['student_name'] . '.');
    } elseif ($action === 'return') {
        returnOfficeClearance($database, $config['code'], $clearanceId, $user['id'], trim((string) ($_POST['reason'] ?? '')));
        setFlashMessage('Clearance returned with instructions for the student.');
    } elseif ($action === 'verify_requirement' && $config['code'] === 'STUDENT_COUNCIL') {
        $requirementRecordId = positivePostId('requirement_record_id');
        $statement = $database->prepare("UPDATE requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id INNER JOIN clearance_stations cs ON cs.id=rr.clearance_station_id SET rr.status='VERIFIED',rr.verified_by=:user_id,rr.verified_at=NOW(),rr.remarks=:remarks WHERE rr.id=:record_id AND cs.clearance_id=:clearance_id AND r.station_id=:station_id");
        $station = officeStation($database, $config['code']);
        $statement->execute(['user_id' => $user['id'], 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null, 'record_id' => $requirementRecordId, 'clearance_id' => $clearanceId, 'station_id' => $station['id']]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The requirement could not be verified.'); }
        officeMutationNotice($database, $context, $user['id'], 'Student Council verified a clearance requirement.');
        setFlashMessage('Student Council requirement verified.');
    } elseif ($action === 'medical_status' && $config['code'] === 'CLINIC') {
        $status = strtoupper((string) ($_POST['status'] ?? ''));
        if (!in_array($status, ['VERIFIED', 'RETURNED'], true)) { throw new RuntimeException('Invalid medical-record status.'); }
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($status === 'RETURNED' && $remarks === '') { throw new RuntimeException('Add remarks explaining the missing medical requirement.'); }
        $statement = $database->prepare("UPDATE medical_requirements SET status=:status,remarks=:remarks,verified_by=:user_id,verified_at=CASE WHEN :verified='VERIFIED' THEN NOW() ELSE NULL END WHERE student_id=:student_id AND academic_period_id=:period_id");
        $statement->execute(['status' => $status, 'remarks' => $remarks ?: null, 'user_id' => $user['id'], 'verified' => $status, 'student_id' => $studentId, 'period_id' => $periodId]);
        officeMutationNotice($database, $context, $user['id'], 'Clinic updated the medical-record review to ' . $status . '.');
        setFlashMessage('Medical record updated.');
    } elseif ($action === 'add_bookstore_obligation' && $config['code'] === 'BOOKSTORE') {
        $description = trim((string) ($_POST['description'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255 || $amount === false || $amount < 0) {
            throw new RuntimeException('Enter a valid Bookstore obligation and amount.');
        }
        if (mb_strlen($remarks) > 1000) { throw new RuntimeException('Remarks cannot exceed 1,000 characters.'); }
        $statement = $database->prepare("INSERT INTO bookstore_obligations (student_id,academic_period_id,description,amount,status,remarks,recorded_by) VALUES (:student_id,:period_id,:description,:amount,'UNPAID',:remarks,:user_id)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'amount' => $amount, 'remarks' => $remarks ?: null, 'user_id' => $user['id']]);
        $newStatus = $context['station_status'];
        if ($context['station_status'] === 'APPROVED') {
            $newStatus = 'RETURNED';
            $reopen = $database->prepare("UPDATE clearance_stations SET status='RETURNED',approved_by=NULL,approved_at=NULL,returned_by=:user_id,returned_at=NOW(),returned_reason=:reason WHERE id=:id");
            $reopen->execute(['user_id' => $user['id'], 'reason' => 'Bookstore obligation: ' . $description, 'id' => $context['clearance_station_id']]);
        }
        notifyAndAuditOffice($database, $context, $user['id'], 'BOOKSTORE_OBLIGATION_ADDED', 'Bookstore recorded a pending obligation: ' . $description . '.', $context['station_status'], $newStatus);
        setFlashMessage('Bookstore obligation recorded.');
    } elseif ($action === 'clear_bookstore_obligation' && $config['code'] === 'BOOKSTORE') {
        $obligationId = positivePostId('obligation_id');
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($reference === '' || mb_strlen($reference) > 255) { throw new RuntimeException('Enter the payment receipt or reference.'); }
        if (mb_strlen($remarks) > 1000) { throw new RuntimeException('Remarks cannot exceed 1,000 characters.'); }
        $statement = $database->prepare("UPDATE bookstore_obligations SET status='CLEARED',payment_reference=:reference,remarks=:remarks,cleared_by=:user_id,cleared_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'");
        $statement->execute(['reference' => $reference, 'remarks' => $remarks ?: null, 'user_id' => $user['id'], 'id' => $obligationId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The Bookstore obligation could not be updated.'); }
        officeMutationNotice($database, $context, $user['id'], 'Bookstore verified payment and settled an obligation.');
        setFlashMessage('Bookstore obligation settled.');
    } elseif ($action === 'add_facilities_item' && $config['code'] === 'FACILITIES') {
        $description = trim((string) ($_POST['description'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255) { throw new RuntimeException('Enter a valid borrowed item description.'); }
        if (mb_strlen($remarks) > 1000) { throw new RuntimeException('Remarks cannot exceed 1,000 characters.'); }
        $statement = $database->prepare("INSERT INTO facilities_obligations (student_id,academic_period_id,item_type,description,status,remarks,recorded_by) VALUES (:student_id,:period_id,'BORROWED_ITEM',:description,'OUTSTANDING',:remarks,:user_id)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'remarks' => $remarks ?: null, 'user_id' => $user['id']]);
        officeMutationNotice($database, $context, $user['id'], 'Facilities recorded a borrowed item that must be returned: ' . $description . '.');
        setFlashMessage('Borrowed item recorded.');
    } elseif ($action === 'return_facilities_item' && $config['code'] === 'FACILITIES') {
        $itemId = positivePostId('item_id');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if (mb_strlen($remarks) > 1000) { throw new RuntimeException('Remarks cannot exceed 1,000 characters.'); }
        $statement = $database->prepare("UPDATE facilities_obligations SET status='RETURNED',remarks=CASE WHEN :remarks='' THEN remarks ELSE :remarks END,returned_by=:user_id,returned_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='OUTSTANDING'");
        $statement->execute(['remarks' => $remarks, 'user_id' => $user['id'], 'id' => $itemId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The Facilities item could not be marked returned.'); }
        officeMutationNotice($database, $context, $user['id'], 'Facilities confirmed that a borrowed item or toga was returned.');
        setFlashMessage('Item marked as returned.');
    } elseif ($action === 'add_comlab_incident' && $config['code'] === 'COMLAB') {
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 2000) { throw new RuntimeException('Enter an incident description of up to 2,000 characters.'); }
        if ($context['station_status'] === 'APPROVED') { throw new RuntimeException('This student is already cleared by Computer Laboratory.'); }
        $station = officeStation($database, 'COMLAB');
        $statement = $database->prepare("INSERT INTO violations (student_id,station_id,description,status,recorded_by) VALUES (:student_id,:station_id,:description,'OPEN',:user_id)");
        $statement->execute(['student_id' => $studentId, 'station_id' => $station['id'], 'description' => $description, 'user_id' => $user['id']]);
        officeMutationNotice($database, $context, $user['id'], 'Computer Laboratory recorded an incident report.');
        setFlashMessage('Computer Laboratory incident report recorded.');
    } elseif ($action === 'resolve_comlab_incident' && $config['code'] === 'COMLAB') {
        $incidentId = positivePostId('incident_id');
        $station = officeStation($database, 'COMLAB');
        $statement = $database->prepare("UPDATE violations SET status='RESOLVED',resolved_by=:user_id,resolved_at=NOW() WHERE id=:id AND student_id=:student_id AND station_id=:station_id AND status='OPEN'");
        $statement->execute(['user_id' => $user['id'], 'id' => $incidentId, 'student_id' => $studentId, 'station_id' => $station['id']]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The Computer Laboratory incident could not be marked settled.'); }
        officeMutationNotice($database, $context, $user['id'], 'Computer Laboratory marked an incident report as settled.');
        setFlashMessage('Computer Laboratory incident report settled.');
    } elseif ($action === 'add_obligation' && $config['code'] === 'ACCOUNTING') {
        $description = trim((string) ($_POST['description'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($description === '' || $amount === false || $amount < 0) { throw new RuntimeException('Enter a valid obligation and amount.'); }
        $statement = $database->prepare("INSERT INTO accounting_obligations (student_id,academic_period_id,description,amount,status,remarks) VALUES (:student_id,:period_id,:description,:amount,'UNPAID',:remarks)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'amount' => $amount, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null]);
        officeMutationNotice($database, $context, $user['id'], 'Accounting recorded a financial obligation: ' . $description . '.');
        setFlashMessage('Financial obligation added.');
    } elseif ($action === 'register_accounting_receipt' && $config['code'] === 'ACCOUNTING') {
        $receiptCode = strtoupper(trim((string) ($_POST['receipt_code'] ?? '')));
        if (str_starts_with($receiptCode, 'CLEARGO-ACCOUNTING:')) { $receiptCode = substr($receiptCode, strlen('CLEARGO-ACCOUNTING:')); }
        if ($receiptCode === '' || mb_strlen($receiptCode) > 255) { throw new RuntimeException('Enter the valid receipt number or QR code from Accounting.'); }
        try {
            $statement = $database->prepare("INSERT INTO accounting_receipts (student_id,academic_period_id,receipt_code,status,issued_by) VALUES (:student_id,:period_id,:receipt_code,'ISSUED',:user_id)");
            $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'receipt_code' => $receiptCode, 'user_id' => $user['id']]);
        } catch (PDOException $exception) {
            throw new RuntimeException('That Accounting receipt code is already registered.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Accounting registered a receipt for student scanning.');
        setFlashMessage('Valid Accounting receipt registered. Ask the student to scan the QR or barcode.');
    } elseif ($action === 'clear_obligation' && $config['code'] === 'ACCOUNTING') {
        $obligationId = positivePostId('obligation_id');
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        if ($reference === '') { throw new RuntimeException('Enter the payment reference.'); }
        $statement = $database->prepare("UPDATE accounting_obligations SET status='CLEARED',payment_reference=:reference,remarks=:remarks,cleared_by=:user_id,cleared_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'");
        $statement->execute(['reference' => $reference, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null, 'user_id' => $user['id'], 'id' => $obligationId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The obligation could not be cleared.'); }
        officeMutationNotice($database, $context, $user['id'], 'Accounting verified a settled financial obligation.');
        setFlashMessage('Financial obligation cleared.');
    } elseif ($action === 'document_status' && $config['code'] === 'REGISTRAR') {
        $documentId = positivePostId('document_id');
        $status = strtoupper((string) ($_POST['status'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (!in_array($status, ['VERIFIED', 'RETURNED'], true) || ($status === 'RETURNED' && $reason === '')) { throw new RuntimeException('Select a valid document status and return reason.'); }
        $statement = $database->prepare("UPDATE student_documents document INNER JOIN document_requirements requirement ON requirement.id=document.document_requirement_id SET document.status=:status,document.reviewed_by=:user_id,document.reviewed_at=NOW(),document.return_reason=:reason WHERE document.id=:id AND document.student_id=:student_id AND requirement.academic_period_id=:period_id");
        $statement->execute(['status' => $status, 'user_id' => $user['id'], 'reason' => $reason ?: null, 'id' => $documentId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The document could not be updated.'); }
        officeMutationNotice($database, $context, $user['id'], 'Registrar updated a required document to ' . $status . '.');
        setFlashMessage('Registrar document updated.');
    } elseif ($action === 'approve_exception' && in_array($config['code'], ['ACCOUNTING', 'REGISTRAR'], true)) {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $proof = trim((string) ($_POST['proof_reference'] ?? ''));
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        if ($reason === '' || $proof === '') { throw new RuntimeException('Enter the approved reason and proof or promissory-note reference.'); }
        $statement = $database->prepare("INSERT INTO clearance_exceptions (clearance_station_id,reason,proof_reference,status,requested_by,decided_by,decided_at,expires_at) VALUES (:station_id,:reason,:proof,'APPROVED',:user_id,:user_id,NOW(),:expires_at)");
        $statement->execute(['station_id' => $context['clearance_station_id'], 'reason' => $reason, 'proof' => $proof, 'user_id' => $user['id'], 'expires_at' => $expires !== '' ? $expires . ' 23:59:59' : null]);
        officeMutationNotice($database, $context, $user['id'], $context['station_name'] . ' approved a temporary exception: ' . $reason);
        setFlashMessage('Temporary exception approved and audited.');
    } else {
        throw new RuntimeException('That action is not available for this office.');
    }
} catch (Throwable $exception) {
    error_log('Office workflow action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to process that request.');
}

$studentParameter = isset($clearanceId) ? '?clearance=' . urlencode((string) $clearanceId) : '';
redirectTo($config['path'] . $studentParameter);
