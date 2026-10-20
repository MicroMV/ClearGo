<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/office_workflow.php';
require_once __DIR__ . '/bulk_clearance.php';
require_once __DIR__ . '/../bookstore/obligation_functions.php';

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
    if ($value === false) {
        throw new RuntimeException('The selected record is invalid.');
    }
    return (int) $value;
}

function officeMutationNotice(PDO $database, array $context, int $userId, string $message): void
{
    notifyAndAuditOffice($database, $context, $userId, $context['station_name'] . '_RECORD_UPDATED', $message, $context['station_status'], $context['station_status']);
}

try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'approve_all_ready') {
        $audience = strtoupper((string) ($_POST['audience'] ?? 'ALL'));
        $department = strtoupper((string) ($_POST['department'] ?? 'ALL'));
        $result = approveAllReadyClearances($database, $user, '', $audience, $department);
        setFlashMessage('Cleared: ' . $result['approved'] . '. Skipped: ' . $result['skipped'] . '.');
        $returnQuery = $config['code'] === 'FACILITIES' ? '?' . http_build_query(['audience' => $audience, 'department' => $department]) : '';
        redirectTo($config['path'] . $returnQuery);
    }
    $clearanceId = positivePostId('clearance_id');
    $context = officeClearanceContext($database, $config['code'], $clearanceId);
    $canRecordComlabViolation = $config['code'] === 'COMLAB' && in_array($action, ['add_comlab_incident', 'resolve_comlab_incident'], true);
    $canManageFacilitiesItems = $config['code'] === 'FACILITIES' && in_array($action, ['add_facilities_item', 'return_facilities_item'], true);
    $canRecordLibraryReturn = $config['code'] === 'LIBRARY' && $action === 'return_library_book';
    if ($context['station_status'] === 'LOCKED' && !$canRecordComlabViolation && !$canManageFacilitiesItems && !$canRecordLibraryReturn) {
        throw new RuntimeException('The required preceding offices have not cleared this student yet.');
    }
    $studentId = (int) $context['student_id'];
    $periodId = (int) $context['academic_period_id'];
    ensureOfficeSourceRecords($database, $studentId, $periodId);

    if ($action === 'manual_accounting_clear' && $config['code'] === 'ACCOUNTING') {
        manuallyClearAccounting($database, $clearanceId, $user['id']);
        setFlashMessage('Accounting manually cleared ' . $context['student_name'] . '.');
    } elseif ($action === 'registrar_clear' && $config['code'] === 'REGISTRAR') {
        finalizeRegistrarClearance($database, $clearanceId, $user['id']);
        setFlashMessage('Registrar completed clearance for ' . $context['student_name'] . '.');
    } elseif ($action === 'clinic_clear' && $config['code'] === 'CLINIC') {
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Clinic remarks cannot exceed 1,000 characters.');
        }
        $cleared = clearClinicClearance($database, $clearanceId, $user['id'], $remarks ?: 'Cleared directly by the Clinic.');
        setFlashMessage($cleared ? 'Clinic clearance approved for ' . $context['student_name'] . '.' : 'This student is already cleared by the Clinic.');
    } elseif ($action === 'return_library_book' && $config['code'] === 'LIBRARY') {
        returnLibraryBook($database, $clearanceId, positivePostId('book_id'), (int) $user['id']);
        setFlashMessage('Book return recorded.');
    } elseif ($action === 'library_clear' && $config['code'] === 'LIBRARY') {
        $cleared = clearLibraryClearance($database, $clearanceId, $user['id']);
        setFlashMessage($cleared ? 'Library clearance completed for ' . $context['student_name'] . '.' : 'This student is already cleared by the Library.');
    } elseif ($action === 'approve' && $config['code'] !== 'REGISTRAR') {
        approveOfficeClearance($database, $config['code'], $clearanceId, $user['id']);
        setFlashMessage($config['title'] . ' approved for ' . $context['student_name'] . '.');
    } elseif ($action === 'return') {
        returnOfficeClearance($database, $config['code'], $clearanceId, $user['id'], trim((string) ($_POST['reason'] ?? '')));
        setFlashMessage($config['code'] === 'REGISTRAR' ? 'Student marked for compliance.' : 'Clearance returned with instructions for the student.');
    } elseif ($action === 'verify_requirement' && $config['code'] === 'STUDENT_COUNCIL') {
        $requirementRecordId = positivePostId('requirement_record_id');
        $statement = $database->prepare("UPDATE requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id INNER JOIN clearance_stations cs ON cs.id=rr.clearance_station_id SET rr.status='VERIFIED',rr.verified_by=:user_id,rr.verified_at=NOW(),rr.remarks=:remarks WHERE rr.id=:record_id AND cs.clearance_id=:clearance_id AND r.station_id=:station_id");
        $station = officeStation($database, $config['code']);
        $statement->execute(['user_id' => $user['id'], 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null, 'record_id' => $requirementRecordId, 'clearance_id' => $clearanceId, 'station_id' => $station['id']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The requirement could not be verified.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Student Council verified a clearance requirement.');
        setFlashMessage('Student Council requirement verified.');
    } elseif ($action === 'medical_status' && $config['code'] === 'CLINIC') {
        $status = strtoupper((string) ($_POST['status'] ?? ''));
        if (!in_array($status, ['VERIFIED', 'RETURNED'], true)) {
            throw new RuntimeException('Invalid medical-record status.');
        }
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($status === 'RETURNED' && $remarks === '') {
            throw new RuntimeException('Add remarks explaining the missing medical requirement.');
        }
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
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
        }
        addBookstoreObligation($database, $clearanceId, (int) $user['id'], $description, (float) $amount, $remarks);
        setFlashMessage('Bookstore obligation recorded.');
    } elseif ($action === 'clear_bookstore_obligation' && $config['code'] === 'BOOKSTORE') {
        $obligationId = positivePostId('obligation_id');
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($reference === '' || mb_strlen($reference) > 255) {
            throw new RuntimeException('Enter the payment receipt or reference.');
        }
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
        }
        $statement = $database->prepare("UPDATE bookstore_obligations SET status='CLEARED',payment_reference=:reference,remarks=:remarks,cleared_by=:user_id,cleared_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'");
        $statement->execute(['reference' => $reference, 'remarks' => $remarks ?: null, 'user_id' => $user['id'], 'id' => $obligationId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The Bookstore obligation could not be updated.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Bookstore verified payment and settled an obligation.');
        setFlashMessage('Bookstore obligation settled.');
    } elseif ($action === 'add_facilities_item' && $config['code'] === 'FACILITIES') {
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Clearance processing is closed for this semester.');
        }
        if ($context['station_status'] === 'APPROVED') {
            throw new RuntimeException('This student is already cleared by Facilities.');
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255) {
            throw new RuntimeException('Enter a valid borrowed item description.');
        }
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
        }
        $borrowedInput = (string) ($_POST['borrowed_at'] ?? '');
        $borrowingTimezone = new DateTimeZone('Asia/Manila');
        $borrowedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $borrowedInput, $borrowingTimezone);
        if ($borrowedAt === false || $borrowedAt->format('Y-m-d\TH:i') !== $borrowedInput || $borrowedAt > new DateTimeImmutable('now', $borrowingTimezone)) {
            throw new RuntimeException('Enter a valid borrowing date and time that is not in the future.');
        }
        $statement = $database->prepare("INSERT INTO facilities_obligations (student_id,academic_period_id,item_type,description,status,remarks,recorded_by,created_at) VALUES (:student_id,:period_id,'BORROWED_ITEM',:description,'OUTSTANDING',:remarks,:user_id,:borrowed_at)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'remarks' => $remarks ?: null, 'user_id' => $user['id'], 'borrowed_at' => $borrowedAt->format('Y-m-d H:i:s')]);
        officeMutationNotice($database, $context, $user['id'], 'Facilities recorded a borrowed item that must be returned: ' . $description . '.');
        setFlashMessage('Borrowed item recorded.');
    } elseif ($action === 'return_facilities_item' && $config['code'] === 'FACILITIES') {
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true) || $context['station_status'] === 'APPROVED') {
            throw new RuntimeException('Facilities items cannot be changed for this clearance.');
        }
        $itemId = positivePostId('item_id');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if (mb_strlen($remarks) > 1000) {
            throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
        }
        $statement = $database->prepare("UPDATE facilities_obligations SET status='RETURNED',remarks=COALESCE(NULLIF(:remarks,''),remarks),returned_by=:user_id,returned_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='OUTSTANDING'");
        $statement->execute(['remarks' => $remarks, 'user_id' => $user['id'], 'id' => $itemId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The Facilities item could not be marked returned.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Facilities confirmed that a borrowed item or toga was returned.');
        setFlashMessage('Item marked as returned.');
    } elseif ($action === 'add_comlab_incident' && $config['code'] === 'COMLAB') {
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Clearance processing is closed for this semester.');
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 2000) {
            throw new RuntimeException('Enter an incident description of up to 2,000 characters.');
        }
        if ($context['station_status'] === 'APPROVED') {
            throw new RuntimeException('This student is already cleared by Computer Laboratory.');
        }
        $station = officeStation($database, 'COMLAB');
        $violationInput = (string) ($_POST['violation_at'] ?? '');
        $violationTimezone = new DateTimeZone('Asia/Manila');
        $violationAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $violationInput, $violationTimezone);
        if ($violationAt === false || $violationAt->format('Y-m-d\TH:i') !== $violationInput || $violationAt > new DateTimeImmutable('now', $violationTimezone)) {
            throw new RuntimeException('Enter a valid violation date and time that is not in the future.');
        }
        $statement = $database->prepare("INSERT INTO violations (student_id,station_id,description,status,recorded_by,created_at) VALUES (:student_id,:station_id,:description,'OPEN',:user_id,:violation_at)");
        $statement->execute(['student_id' => $studentId, 'station_id' => $station['id'], 'description' => $description, 'user_id' => $user['id'], 'violation_at' => $violationAt->format('Y-m-d H:i:s')]);
        officeMutationNotice($database, $context, $user['id'], 'Comlab violation: ' . $description . '. Go to the Comlab room to resolve this violation before clearance.');
        setFlashMessage('Comlab violation recorded. The student must visit the Comlab room to resolve it.');
    } elseif ($action === 'resolve_comlab_incident' && $config['code'] === 'COMLAB') {
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true) || $context['station_status'] === 'APPROVED') {
            throw new RuntimeException('Violations cannot be changed for this clearance in its current state.');
        }
        $incidentId = positivePostId('incident_id');
        $station = officeStation($database, 'COMLAB');
        $statement = $database->prepare("UPDATE violations SET status='RESOLVED',resolved_by=:user_id,resolved_at=NOW() WHERE id=:id AND student_id=:student_id AND station_id=:station_id AND status='OPEN'");
        $statement->execute(['user_id' => $user['id'], 'id' => $incidentId, 'student_id' => $studentId, 'station_id' => $station['id']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The Computer Laboratory incident could not be marked settled.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Computer Laboratory marked an incident report as settled.');
        setFlashMessage('Computer Laboratory incident report settled.');
    } elseif ($action === 'add_obligation' && $config['code'] === 'ACCOUNTING') {
        $description = trim((string) ($_POST['description'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($description === '' || $amount === false || $amount < 0) {
            throw new RuntimeException('Enter a valid obligation and amount.');
        }
        $statement = $database->prepare("INSERT INTO accounting_obligations (student_id,academic_period_id,description,amount,status,remarks) VALUES (:student_id,:period_id,:description,:amount,'UNPAID',:remarks)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'amount' => $amount, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null]);
        officeMutationNotice($database, $context, $user['id'], 'Accounting recorded a financial obligation: ' . $description . '.');
        setFlashMessage('Financial obligation added.');
    } elseif ($action === 'register_accounting_receipt' && $config['code'] === 'ACCOUNTING') {
        $receiptCode = strtoupper(trim((string) ($_POST['receipt_code'] ?? '')));
        if (str_starts_with($receiptCode, 'CLEARGO-ACCOUNTING:')) {
            $receiptCode = substr($receiptCode, strlen('CLEARGO-ACCOUNTING:'));
        }
        if ($receiptCode === '' || mb_strlen($receiptCode) > 255) {
            throw new RuntimeException('Enter the valid receipt number or QR code from Accounting.');
        }
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
        if ($reference === '') {
            throw new RuntimeException('Enter the payment reference.');
        }
        $statement = $database->prepare("UPDATE accounting_obligations SET status='CLEARED',payment_reference=:reference,remarks=:remarks,cleared_by=:user_id,cleared_at=NOW() WHERE id=:id AND student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'");
        $statement->execute(['reference' => $reference, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null, 'user_id' => $user['id'], 'id' => $obligationId, 'student_id' => $studentId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The obligation could not be cleared.');
        }
        officeMutationNotice($database, $context, $user['id'], 'Accounting verified a settled financial obligation.');
        setFlashMessage('Financial obligation cleared.');
    } elseif ($action === 'approve_exception' && $config['code'] === 'ACCOUNTING') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $proof = trim((string) ($_POST['proof_reference'] ?? ''));
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        if ($reason === '' || $proof === '') {
            throw new RuntimeException('Enter the approved reason and proof or promissory-note reference.');
        }
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
if (($_POST['return_history'] ?? '') === '1') {
    if ($config['code'] === 'FACILITIES' && ($action ?? '') === 'return_facilities_item') {
        redirectTo('/facilities/borrowed-items.php');
    }
    if ($config['code'] === 'COMLAB' && ($action ?? '') === 'resolve_comlab_incident') {
        redirectTo('/comlab/violations.php');
    }
}
redirectTo($config['path'] . $studentParameter);
