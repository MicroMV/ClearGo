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
    $studentId = (int) $context['student_id'];
    $periodId = (int) $context['academic_period_id'];
    ensureOfficeSourceRecords($database, $studentId, $periodId);

    if ($action === 'approve') {
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
    } elseif ($action === 'library_card' && $config['code'] === 'LIBRARY') {
        $status = strtoupper((string) ($_POST['status'] ?? ''));
        if (!in_array($status, ['VERIFIED', 'INVALID'], true)) { throw new RuntimeException('Invalid library-card status.'); }
        $statement = $database->prepare("UPDATE library_cards SET status=:status,verified_by=:user_id,verified_at=CASE WHEN :verified='VERIFIED' THEN NOW() ELSE NULL END WHERE student_id=:student_id");
        $statement->execute(['status' => $status, 'user_id' => $user['id'], 'verified' => $status, 'student_id' => $studentId]);
        officeMutationNotice($database, $context, $user['id'], 'Library updated the library-card status to ' . $status . '.');
        setFlashMessage('Library card status updated.');
    } elseif ($action === 'add_book' && $config['code'] === 'LIBRARY') {
        $title = trim((string) ($_POST['book_title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) { throw new RuntimeException('Enter a valid book title.'); }
        $statement = $database->prepare("INSERT INTO borrowed_books (student_id,book_title,borrowed_at,due_at,status,recorded_by) VALUES (:student_id,:title,CURDATE(),:due_at,'BORROWED',:user_id)");
        $dueAt = trim((string) ($_POST['due_at'] ?? ''));
        $statement->execute(['student_id' => $studentId, 'title' => $title, 'due_at' => $dueAt !== '' ? $dueAt : null, 'user_id' => $user['id']]);
        officeMutationNotice($database, $context, $user['id'], 'Library recorded an unreturned book: ' . $title . '.');
        setFlashMessage('Borrowed book recorded.');
    } elseif ($action === 'return_book' && $config['code'] === 'LIBRARY') {
        $bookId = positivePostId('book_id');
        $statement = $database->prepare("UPDATE borrowed_books SET status='RETURNED',returned_at=CURDATE(),returned_verified_by=:user_id WHERE id=:id AND student_id=:student_id AND status='BORROWED'");
        $statement->execute(['user_id' => $user['id'], 'id' => $bookId, 'student_id' => $studentId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The borrowed book could not be updated.'); }
        officeMutationNotice($database, $context, $user['id'], 'Library confirmed a borrowed book was returned.');
        setFlashMessage('Book marked as returned.');
    } elseif ($action === 'bookstore_payment' && $config['code'] === 'BOOKSTORE') {
        $feeId = positivePostId('fee_id');
        $status = strtoupper((string) ($_POST['status'] ?? ''));
        if (!in_array($status, ['PAID', 'UNPAID'], true)) { throw new RuntimeException('Invalid payment status.'); }
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        if ($status === 'PAID' && $reference === '') { throw new RuntimeException('Enter the receipt or payment reference.'); }
        $statement = $database->prepare("UPDATE student_bookstore_fees sf INNER JOIN bookstore_fees fee ON fee.id=sf.fee_id SET sf.status=:status,sf.paid_at=CASE WHEN :paid='PAID' THEN NOW() ELSE NULL END,sf.payment_reference=:reference,sf.remarks=:remarks,sf.verified_by=:user_id,sf.verified_at=NOW() WHERE sf.student_id=:student_id AND sf.fee_id=:fee_id AND fee.academic_period_id=:period_id");
        $statement->execute(['status' => $status, 'paid' => $status, 'reference' => $reference ?: null, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null, 'user_id' => $user['id'], 'student_id' => $studentId, 'fee_id' => $feeId, 'period_id' => $periodId]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The bookstore fee could not be updated.'); }
        officeMutationNotice($database, $context, $user['id'], 'Bookstore updated a required fee to ' . $status . '.');
        setFlashMessage('Bookstore payment updated.');
    } elseif ($action === 'add_violation' && in_array($config['code'], ['FACILITIES', 'SASO'], true)) {
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 2000) { throw new RuntimeException('Enter a valid incident description.'); }
        $station = officeStation($database, $config['code']);
        $statement = $database->prepare("INSERT INTO violations (student_id,station_id,description,status,recorded_by) VALUES (:student_id,:station_id,:description,'OPEN',:user_id)");
        $statement->execute(['student_id' => $studentId, 'station_id' => $station['id'], 'description' => $description, 'user_id' => $user['id']]);
        officeMutationNotice($database, $context, $user['id'], $context['station_name'] . ' recorded an open case: ' . $description);
        setFlashMessage('Open case recorded.');
    } elseif ($action === 'resolve_violation' && in_array($config['code'], ['FACILITIES', 'SASO'], true)) {
        $violationId = positivePostId('violation_id');
        $station = officeStation($database, $config['code']);
        $statement = $database->prepare("UPDATE violations SET status='RESOLVED',resolved_by=:user_id,resolved_at=NOW() WHERE id=:id AND student_id=:student_id AND station_id=:station_id AND status='OPEN'");
        $statement->execute(['user_id' => $user['id'], 'id' => $violationId, 'student_id' => $studentId, 'station_id' => $station['id']]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The case could not be resolved.'); }
        officeMutationNotice($database, $context, $user['id'], $context['station_name'] . ' resolved an open case.');
        setFlashMessage('Case marked as settled.');
    } elseif ($action === 'add_obligation' && $config['code'] === 'ACCOUNTING') {
        $description = trim((string) ($_POST['description'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($description === '' || $amount === false || $amount < 0) { throw new RuntimeException('Enter a valid obligation and amount.'); }
        $statement = $database->prepare("INSERT INTO accounting_obligations (student_id,academic_period_id,description,amount,status,remarks) VALUES (:student_id,:period_id,:description,:amount,'UNPAID',:remarks)");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId, 'description' => $description, 'amount' => $amount, 'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null]);
        officeMutationNotice($database, $context, $user['id'], 'Accounting recorded a financial obligation: ' . $description . '.');
        setFlashMessage('Financial obligation added.');
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

