<?php
declare(strict_types=1);

const OFFICE_ROLE_CONFIG = [
    'STUDENT_COUNCIL_STAFF' => ['code' => 'STUDENT_COUNCIL', 'title' => 'Student Council Clearance', 'path' => '/student-council/dashboard.php'],
    'LIBRARY_STAFF' => ['code' => 'LIBRARY', 'title' => 'Library Clearance', 'path' => '/library/dashboard.php'],
    'BOOKSTORE_STAFF' => ['code' => 'BOOKSTORE', 'title' => 'Bookstore Clearance', 'path' => '/bookstore/dashboard.php'],
    'CLINIC_STAFF' => ['code' => 'CLINIC', 'title' => 'Clinic Clearance', 'path' => '/clinic/dashboard.php'],
    'SASO_STAFF' => ['code' => 'SASO', 'title' => 'SASO Clearance', 'path' => '/saso/dashboard.php'],
    'FACILITIES_STAFF' => ['code' => 'FACILITIES', 'title' => 'Facilities Clearance', 'path' => '/facilities/dashboard.php'],
    'ACCOUNTING_STAFF' => ['code' => 'ACCOUNTING', 'title' => 'Accounting Clearance', 'path' => '/accounting/dashboard.php'],
    'REGISTRAR' => ['code' => 'REGISTRAR', 'title' => 'Registrar Clearance', 'path' => '/registrar/clearance.php'],
];

function officeConfigForRole(string $role): array
{
    if (!isset(OFFICE_ROLE_CONFIG[$role])) {
        throw new RuntimeException('This account is not assigned to an office workflow.');
    }
    return OFFICE_ROLE_CONFIG[$role];
}

function officeStation(PDO $database, string $code): array
{
    $statement = $database->prepare("SELECT id, code, name FROM stations WHERE code = :code AND status = 'ACTIVE' LIMIT 1");
    $statement->execute(['code' => $code]);
    $station = $statement->fetch();
    if ($station === false) {
        throw new RuntimeException('The clearance office is unavailable.');
    }
    return $station;
}

function officeRowCount(PDO $database, string $stationCode, string $search = ''): int
{
    $statement = $database->prepare(
        "SELECT COUNT(*)
         FROM clearance_stations cs
         INNER JOIN stations station ON station.id=cs.station_id AND station.code=:station_code
         INNER JOIN clearances clearance ON clearance.id=cs.clearance_id
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.student_id=clearance.student_id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
         WHERE (student.student_number LIKE :number_search OR student.first_name LIKE :first_search
            OR student.last_name LIKE :last_search OR student.program LIKE :program_search)"
    );
    $term = '%' . $search . '%';
    $statement->execute(['station_code' => $stationCode, 'number_search' => $term, 'first_search' => $term, 'last_search' => $term, 'program_search' => $term]);
    return (int) $statement->fetchColumn();
}

function officeRows(PDO $database, string $stationCode, string $search = '', int $limit = 100, int $offset = 0): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $statement = $database->prepare(
        "SELECT c.id AS clearance_id, c.status AS overall_status, c.academic_period_id,
                period.clearance_status,
                cs.id AS clearance_station_id, cs.status AS station_status, cs.returned_reason,
                student.id AS student_id, student.user_id AS student_user_id,
                student.student_number, student.first_name, student.last_name,
                student.program, student.year_level, student.section,
                organization.code AS organization_code, organization.name AS organization_name
         FROM clearance_stations cs
         INNER JOIN stations station ON station.id = cs.station_id AND station.code = :station_code
         INNER JOIN clearances c ON c.id = cs.clearance_id
         INNER JOIN academic_periods period ON period.id = c.academic_period_id AND period.status = 'ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.student_id=c.student_id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id = c.student_id AND student.status = 'ACTIVE'
         INNER JOIN organizations organization ON organization.id = student.organization_id
         WHERE student.student_number LIKE :number_search
            OR student.first_name LIKE :first_search
            OR student.last_name LIKE :last_search
            OR student.program LIKE :program_search
         ORDER BY student.last_name, student.first_name
         LIMIT {$limit} OFFSET {$offset}"
    );
    $term = '%' . $search . '%';
    $statement->execute([
        'station_code' => $stationCode,
        'number_search' => $term,
        'first_search' => $term,
        'last_search' => $term,
        'program_search' => $term,
    ]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row['eligibility'] = officeEligibility($database, $stationCode, $row);
    }
    unset($row);
    return $rows;
}

function countQuery(PDO $database, string $sql, array $parameters): int
{
    $statement = $database->prepare($sql);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
}

function officeEligibility(PDO $database, string $stationCode, array $row): array
{
    $studentId = (int) $row['student_id'];
    $periodId = (int) $row['academic_period_id'];
    $clearanceId = (int) $row['clearance_id'];
    $clearanceStationId = (int) $row['clearance_station_id'];
    $details = [];
    $eligible = false;

    if ($stationCode === 'STUDENT_COUNCIL') {
        $total = countQuery($database, "SELECT COUNT(*) FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=:id AND r.status='ACTIVE'", ['id' => $clearanceStationId]);
        $verified = countQuery($database, "SELECT COUNT(*) FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=:id AND r.status='ACTIVE' AND rr.status IN ('VERIFIED','RESOLVED')", ['id' => $clearanceStationId]);
        $details[] = "Verified council requirements: {$verified}/{$total}";
        $eligible = $total > 0 && $verified === $total;
    } elseif ($stationCode === 'LIBRARY') {
        $card = $database->prepare('SELECT status FROM library_cards WHERE student_id=:student_id LIMIT 1');
        $card->execute(['student_id' => $studentId]);
        $cardStatus = $card->fetchColumn() ?: 'PENDING';
        $borrowed = countQuery($database, "SELECT COUNT(*) FROM borrowed_books WHERE student_id=:student_id AND status='BORROWED'", ['student_id' => $studentId]);
        $details[] = 'Library card: ' . str_replace('_', ' ', (string) $cardStatus);
        $details[] = "Unreturned books: {$borrowed}";
        $eligible = $cardStatus === 'VERIFIED' && $borrowed === 0;
    } elseif ($stationCode === 'BOOKSTORE') {
        $fees = $database->prepare(
            "SELECT fee.name, fee.amount, COALESCE(student_fee.status,'UNPAID') AS payment_status,
                    student_fee.id, student_fee.payment_reference, student_fee.remarks
             FROM bookstore_fees fee
             LEFT JOIN student_bookstore_fees student_fee ON student_fee.fee_id=fee.id AND student_fee.student_id=:student_id
             WHERE fee.academic_period_id=:period_id AND fee.status='ACTIVE' AND fee.is_required=1
             ORDER BY fee.id"
        );
        $fees->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $feeRows = $fees->fetchAll();
        $unpaid = count(array_filter($feeRows, static fn(array $fee): bool => $fee['payment_status'] !== 'PAID'));
        $details[] = 'Required fees paid: ' . (count($feeRows) - $unpaid) . '/' . count($feeRows);
        $eligible = $feeRows !== [] && $unpaid === 0;
    } elseif ($stationCode === 'CLINIC') {
        $medical = $database->prepare('SELECT status, remarks FROM medical_requirements WHERE student_id=:student_id AND academic_period_id=:period_id LIMIT 1');
        $medical->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $medicalRow = $medical->fetch() ?: ['status' => 'PENDING', 'remarks' => null];
        $details[] = 'Medical record: ' . str_replace('_', ' ', $medicalRow['status']);
        if (!empty($medicalRow['remarks'])) { $details[] = 'Clinic remarks: ' . $medicalRow['remarks']; }
        $eligible = $medicalRow['status'] === 'VERIFIED';
    } elseif ($stationCode === 'SASO') {
        $children = $database->prepare("SELECT s.name, cs.status FROM stations s INNER JOIN stations parent ON parent.id=s.parent_station_id AND parent.code='SASO' INNER JOIN clearance_stations cs ON cs.station_id=s.id AND cs.clearance_id=:clearance_id WHERE s.status='ACTIVE' ORDER BY s.sort_order");
        $children->execute(['clearance_id' => $clearanceId]);
        $childRows = $children->fetchAll();
        foreach ($childRows as $child) { $details[] = $child['name'] . ': ' . str_replace('_', ' ', $child['status']); }
        $openCases = countQuery($database, "SELECT COUNT(*) FROM violations v INNER JOIN stations s ON s.id=v.station_id WHERE v.student_id=:student_id AND s.code='SASO' AND v.status='OPEN'", ['student_id' => $studentId]);
        if ($openCases > 0) { $details[] = "Open SASO cases: {$openCases}"; }
        $eligible = count($childRows) > 0
            && count(array_filter($childRows, static fn(array $child): bool => $child['status'] === 'APPROVED')) === count($childRows)
            && $openCases === 0;
    } elseif ($stationCode === 'FACILITIES') {
        $open = countQuery($database, "SELECT COUNT(*) FROM violations v INNER JOIN stations s ON s.id=v.station_id WHERE v.student_id=:student_id AND s.code='FACILITIES' AND v.status='OPEN'", ['student_id' => $studentId]);
        $details[] = "Open incident or equipment reports: {$open}";
        $eligible = $open === 0;
    } elseif ($stationCode === 'ACCOUNTING') {
        $obligations = $database->prepare("SELECT id, description, amount, status, payment_reference, remarks FROM accounting_obligations WHERE student_id=:student_id AND academic_period_id=:period_id ORDER BY id");
        $obligations->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $obligationRows = $obligations->fetchAll();
        $unpaid = array_values(array_filter($obligationRows, static fn(array $item): bool => $item['status'] === 'UNPAID'));
        $balance = array_sum(array_map(static fn(array $item): float => (float) $item['amount'], $unpaid));
        $details[] = 'Outstanding balance: PHP ' . number_format($balance, 2);
        $eligible = $unpaid === [];
    } elseif ($stationCode === 'REGISTRAR') {
        $documents = $database->prepare(
            "SELECT requirement.name, COALESCE(document.status,'MISSING') AS document_status, document.id, document.return_reason
             FROM document_requirements requirement
             LEFT JOIN student_documents document ON document.document_requirement_id=requirement.id AND document.student_id=:student_id
             WHERE requirement.academic_period_id=:period_id AND requirement.status='ACTIVE' AND requirement.is_required=1 ORDER BY requirement.id"
        );
        $documents->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $documentRows = $documents->fetchAll();
        $verified = count(array_filter($documentRows, static fn(array $document): bool => $document['document_status'] === 'VERIFIED'));
        $details[] = "Required documents verified: {$verified}/" . count($documentRows);
        $priorOpen = countQuery($database, "SELECT COUNT(*) FROM clearance_stations cs INNER JOIN stations s ON s.id=cs.station_id WHERE cs.clearance_id=:clearance_id AND s.code<>'REGISTRAR' AND s.parent_station_id IS NULL AND cs.status<>'APPROVED'", ['clearance_id' => $clearanceId]);
        if ($priorOpen > 0) { $details[] = "Prior main offices not cleared: {$priorOpen}"; }
        $eligible = $documentRows !== [] && $verified === count($documentRows) && $priorOpen === 0;
    }

    $exception = $database->prepare("SELECT id, reason, proof_reference, expires_at FROM clearance_exceptions WHERE clearance_station_id=:station_id AND status='APPROVED' AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY id DESC LIMIT 1");
    $exception->execute(['station_id' => $clearanceStationId]);
    $activeException = $exception->fetch();
    if ($activeException !== false) {
        $details[] = 'Approved temporary exception: ' . $activeException['reason'];
        $eligible = true;
    }

    return ['eligible' => $eligible, 'details' => $details, 'exception' => $activeException ?: null];
}

function ensureOfficeSourceRecords(PDO $database, int $studentId, int $periodId): void
{
    $medical = $database->prepare("INSERT IGNORE INTO medical_requirements (student_id,academic_period_id,status) VALUES (:student_id,:period_id,'PENDING')");
    $medical->execute(['student_id' => $studentId, 'period_id' => $periodId]);
    $card = $database->prepare("INSERT IGNORE INTO library_cards (student_id,status) VALUES (:student_id,'PENDING')");
    $card->execute(['student_id' => $studentId]);
    $fees = $database->prepare("INSERT IGNORE INTO student_bookstore_fees (student_id,fee_id,status) SELECT :student_id,id,'UNPAID' FROM bookstore_fees WHERE academic_period_id=:period_id AND status='ACTIVE' AND is_required=1");
    $fees->execute(['student_id' => $studentId, 'period_id' => $periodId]);
    $documents = $database->prepare("INSERT IGNORE INTO student_documents (student_id,document_requirement_id,status) SELECT :student_id,id,'MISSING' FROM document_requirements WHERE academic_period_id=:period_id AND status='ACTIVE' AND is_required=1");
    $documents->execute(['student_id' => $studentId, 'period_id' => $periodId]);
}

function officeClearanceContext(PDO $database, string $stationCode, int $clearanceId, bool $lock = false): array
{
    $sql = "SELECT c.id AS clearance_id, c.academic_period_id, period.clearance_status,
                   student.id AS student_id,
                   student.user_id AS student_user_id, student.student_number,
                   CONCAT(student.first_name,' ',student.last_name) AS student_name,
                   cs.id AS clearance_station_id, cs.status AS station_status,
                   station.id AS station_id, station.name AS station_name
            FROM clearances c
            INNER JOIN academic_periods period ON period.id=c.academic_period_id AND period.status='ACTIVE'
            INNER JOIN students student ON student.id=c.student_id
            INNER JOIN stations station ON station.code=:station_code
            INNER JOIN clearance_stations cs ON cs.clearance_id=c.id AND cs.station_id=station.id
            WHERE c.id=:clearance_id LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    $statement = $database->prepare($sql);
    $statement->execute(['station_code' => $stationCode, 'clearance_id' => $clearanceId]);
    $context = $statement->fetch();
    if ($context === false) { throw new RuntimeException('The clearance record was not found.'); }
    return $context;
}

function notifyAndAuditOffice(PDO $database, array $context, int $staffUserId, string $action, string $description, string $oldStatus, string $newStatus): void
{
    if (($context['clearance_status'] ?? null) === 'OPEN') {
        $notification = $database->prepare('INSERT INTO notifications (user_id,title,message) VALUES (:user_id,:title,:message)');
        $notification->execute([
            'user_id' => $context['student_user_id'],
            'title' => $context['station_name'] . ' clearance updated',
            'message' => $description,
        ]);
    }
    $audit = $database->prepare('INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status) VALUES (:user_id,:action,:station_id,:student_id,:description,:old_status,:new_status)');
    $audit->execute([
        'user_id' => $staffUserId, 'action' => $action, 'station_id' => $context['station_id'],
        'student_id' => $context['student_id'], 'description' => $description,
        'old_status' => $oldStatus, 'new_status' => $newStatus,
    ]);
}

function unlockAfterOfficeApproval(PDO $database, string $stationCode, int $clearanceId): void
{
    $codes = [];
    $roles = [];
    if ($stationCode === 'STUDENT_COUNCIL' || $stationCode === 'LIBRARY' || $stationCode === 'BOOKSTORE' || $stationCode === 'CLINIC') {
        $remaining = countQuery($database, "SELECT COUNT(*) FROM clearance_stations cs INNER JOIN stations child ON child.id=cs.station_id INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO' WHERE cs.clearance_id=:clearance_id AND child.status='ACTIVE' AND cs.status<>'APPROVED'", ['clearance_id' => $clearanceId]);
        if ($remaining === 0) { $codes = ['SASO']; $roles = ['SASO_STAFF']; }
    } elseif ($stationCode === 'SASO') {
        $codes = ['FACILITIES'];
        $roles = ['FACILITIES_STAFF'];
    } elseif ($stationCode === 'FACILITIES') {
        $codes = ['ACCOUNTING'];
        $roles = ['ACCOUNTING_STAFF'];
    } elseif ($stationCode === 'ACCOUNTING') {
        $codes = ['REGISTRAR'];
        $roles = ['REGISTRAR'];
    } elseif ($stationCode === 'REGISTRAR') {
        $complete = $database->prepare("UPDATE clearances SET status='COMPLETED',completed_at=NOW() WHERE id=:clearance_id");
        $complete->execute(['clearance_id' => $clearanceId]);
    }
    if ($codes !== []) {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $unlock = $database->prepare("UPDATE clearance_stations cs INNER JOIN stations s ON s.id=cs.station_id SET cs.status='PENDING' WHERE cs.clearance_id=? AND s.code IN ({$placeholders}) AND cs.status='LOCKED'");
        $unlock->execute(array_merge([$clearanceId], $codes));
        if ($unlock->rowCount() > 0 && $roles !== []) {
            $studentNumberQuery = $database->prepare('SELECT student.student_number FROM clearances clearance INNER JOIN students student ON student.id=clearance.student_id WHERE clearance.id=?');
            $studentNumberQuery->execute([$clearanceId]);
            $studentNumber = (string) $studentNumberQuery->fetchColumn();
            $rolePlaceholders = implode(',', array_fill(0, count($roles), '?'));
            $staffNotification = $database->prepare("INSERT INTO notifications (user_id,title,message) SELECT id,'Clearance ready for review',? FROM users WHERE role IN ({$rolePlaceholders}) AND status='ACTIVE'");
            $staffNotification->execute(array_merge([$studentNumber . ' has reached your office and is ready for review.'], $roles));
        }
    }
}

function approveOfficeClearance(PDO $database, string $stationCode, int $clearanceId, int $staffUserId): void
{
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, $stationCode, $clearanceId, true);
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) { throw new RuntimeException('Clearance decisions are not available for this semester.'); }
        if ($context['station_status'] === 'APPROVED') { throw new RuntimeException('This office has already approved the student.'); }
        if ($context['clearance_status'] === 'OPEN' && $context['station_status'] === 'LOCKED') { throw new RuntimeException('The preceding clearance requirements are not complete.'); }
        $row = $context + ['academic_period_id' => $context['academic_period_id']];
        $eligibility = officeEligibility($database, $stationCode, $row);
        if (!$eligibility['eligible']) { throw new RuntimeException('The student still has incomplete requirements for this office.'); }
        $update = $database->prepare("UPDATE clearance_stations SET status='APPROVED',approved_by=:user_id,approved_at=NOW(),returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=:id");
        $update->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        $requirements = $database->prepare("UPDATE requirement_records SET status='VERIFIED',verified_by=:user_id,verified_at=NOW(),remarks=NULL WHERE clearance_station_id=:station_id AND status NOT IN ('VERIFIED','RESOLVED')");
        $requirements->execute(['user_id' => $staffUserId, 'station_id' => $context['clearance_station_id']]);
        unlockAfterOfficeApproval($database, $stationCode, $clearanceId);
        $description = $context['station_name'] . ' approved your clearance.';
        notifyAndAuditOffice($database, $context, $staffUserId, $stationCode . '_CLEARANCE_APPROVED', $description, $context['station_status'], 'APPROVED');
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function returnOfficeClearance(PDO $database, string $stationCode, int $clearanceId, int $staffUserId, string $reason): void
{
    if ($reason === '' || mb_strlen($reason) > 1000) { throw new RuntimeException('Provide a return reason of no more than 1,000 characters.'); }
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, $stationCode, $clearanceId, true);
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) { throw new RuntimeException('Clearance decisions are not available for this semester.'); }
        if (($context['clearance_status'] === 'OPEN' && $context['station_status'] === 'LOCKED') || $context['station_status'] === 'APPROVED') { throw new RuntimeException('This clearance cannot be returned in its current state.'); }
        $update = $database->prepare("UPDATE clearance_stations SET status='RETURNED',returned_by=:user_id,returned_at=NOW(),returned_reason=:reason WHERE id=:id");
        $update->execute(['user_id' => $staffUserId, 'reason' => $reason, 'id' => $context['clearance_station_id']]);
        $description = $context['station_name'] . ' returned your clearance: ' . $reason;
        notifyAndAuditOffice($database, $context, $staffUserId, $stationCode . '_CLEARANCE_RETURNED', $description, $context['station_status'], 'RETURNED');
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}
