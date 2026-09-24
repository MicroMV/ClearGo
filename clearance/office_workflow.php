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

function officeListEligibilitySql(string $stationCode): string
{
    $requirement = match ($stationCode) {
        'STUDENT_COUNCIL' => "EXISTS (SELECT 1 FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=cs.id AND r.status='ACTIVE') AND NOT EXISTS (SELECT 1 FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=cs.id AND r.status='ACTIVE' AND rr.status NOT IN ('VERIFIED','RESOLVED'))",
        'LIBRARY' => "EXISTS (SELECT 1 FROM library_cards card WHERE card.student_id=student.id AND card.status='VERIFIED')",
        'BOOKSTORE' => "NOT EXISTS (SELECT 1 FROM bookstore_obligations obligation WHERE obligation.student_id=student.id AND obligation.academic_period_id=period.id AND obligation.status='UNPAID')",
        'CLINIC' => "EXISTS (SELECT 1 FROM medical_requirements medical WHERE medical.student_id=student.id AND medical.academic_period_id=period.id AND medical.status='VERIFIED')",
        'SASO' => "EXISTS (SELECT 1 FROM stations child INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO' WHERE child.status='ACTIVE') AND NOT EXISTS (SELECT 1 FROM stations child INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO' INNER JOIN clearance_stations child_record ON child_record.station_id=child.id AND child_record.clearance_id=c.id WHERE child.status='ACTIVE' AND child_record.status<>'APPROVED') AND NOT EXISTS (SELECT 1 FROM violations violation INNER JOIN stations violation_station ON violation_station.id=violation.station_id AND violation_station.code='SASO' WHERE violation.student_id=student.id AND violation.status='OPEN')",
        'FACILITIES' => "NOT EXISTS (SELECT 1 FROM violations violation INNER JOIN stations violation_station ON violation_station.id=violation.station_id AND violation_station.code='FACILITIES' WHERE violation.student_id=student.id AND violation.status='OPEN')",
        'ACCOUNTING' => "NOT EXISTS (SELECT 1 FROM accounting_obligations obligation WHERE obligation.student_id=student.id AND obligation.academic_period_id=period.id AND obligation.status='UNPAID')",
        'REGISTRAR' => "EXISTS (SELECT 1 FROM document_requirements requirement WHERE requirement.academic_period_id=period.id AND requirement.status='ACTIVE' AND requirement.is_required=1) AND NOT EXISTS (SELECT 1 FROM document_requirements requirement LEFT JOIN student_documents document ON document.document_requirement_id=requirement.id AND document.student_id=student.id WHERE requirement.academic_period_id=period.id AND requirement.status='ACTIVE' AND requirement.is_required=1 AND COALESCE(document.status,'MISSING')<>'VERIFIED') AND NOT EXISTS (SELECT 1 FROM clearance_stations prior_record INNER JOIN stations prior_station ON prior_station.id=prior_record.station_id WHERE prior_record.clearance_id=c.id AND prior_station.code<>'REGISTRAR' AND prior_station.parent_station_id IS NULL AND prior_record.status<>'APPROVED')",
        default => '0=1',
    };
    $exception = "EXISTS (SELECT 1 FROM clearance_exceptions exception_record WHERE exception_record.clearance_station_id=cs.id AND exception_record.status='APPROVED' AND (exception_record.expires_at IS NULL OR exception_record.expires_at>=NOW()))";
    return "(({$requirement}) OR ({$exception}))";
}

function officeListFilterSql(string $stationCode, string $filter): string
{
    $eligible = officeListEligibilitySql($stationCode);
    $ready = "(cs.status NOT IN ('APPROVED','RETURNED') AND {$eligible} AND (period.clearance_status='PREPARING' OR cs.status='PENDING'))";
    if (in_array($stationCode, ['CLINIC', 'LIBRARY', 'BOOKSTORE'], true) && $filter === 'PENDING') {
        return "cs.status<>'APPROVED'";
    }
    return match ($filter) {
        'APPROVED' => "cs.status='APPROVED'",
        'RETURNED' => "cs.status='RETURNED'",
        'READY' => $ready,
        'PENDING' => "(cs.status NOT IN ('APPROVED','RETURNED') AND NOT {$ready})",
        default => '1=1',
    };
}

function officeRowCount(PDO $database, string $stationCode, string $search = '', string $filter = 'ALL'): int
{
    $filterSql = officeListFilterSql($stationCode, $filter);
    $statement = $database->prepare(
        "SELECT COUNT(*)
         FROM clearance_stations cs
         INNER JOIN stations station ON station.id=cs.station_id AND station.code=:station_code
         INNER JOIN clearances c ON c.id=cs.clearance_id
         INNER JOIN academic_periods period ON period.id=c.academic_period_id AND period.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.student_id=c.student_id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=c.student_id AND student.status='ACTIVE'
         WHERE (student.student_number LIKE :number_search OR student.first_name LIKE :first_search
            OR student.last_name LIKE :last_search OR student.program LIKE :program_search)
           AND {$filterSql}"
    );
    $term = '%' . $search . '%';
    $statement->execute(['station_code' => $stationCode, 'number_search' => $term, 'first_search' => $term, 'last_search' => $term, 'program_search' => $term]);
    return (int) $statement->fetchColumn();
}

function officeStatusSummary(PDO $database, string $stationCode): array
{
    $eligibilitySql = match ($stationCode) {
        'STUDENT_COUNCIL' => "EXISTS (SELECT 1 FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=cs.id AND r.status='ACTIVE') AND NOT EXISTS (SELECT 1 FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=cs.id AND r.status='ACTIVE' AND rr.status NOT IN ('VERIFIED','RESOLVED'))",
        'LIBRARY' => "EXISTS (SELECT 1 FROM library_cards card WHERE card.student_id=student.id AND card.status='VERIFIED')",
        'BOOKSTORE' => "NOT EXISTS (SELECT 1 FROM bookstore_obligations obligation WHERE obligation.student_id=student.id AND obligation.academic_period_id=period.id AND obligation.status='UNPAID')",
        'CLINIC' => "EXISTS (SELECT 1 FROM medical_requirements medical WHERE medical.student_id=student.id AND medical.academic_period_id=period.id AND medical.status='VERIFIED')",
        'SASO' => "EXISTS (SELECT 1 FROM stations child INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO' WHERE child.status='ACTIVE') AND NOT EXISTS (SELECT 1 FROM stations child INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO' INNER JOIN clearance_stations child_record ON child_record.station_id=child.id AND child_record.clearance_id=clearance.id WHERE child.status='ACTIVE' AND child_record.status<>'APPROVED') AND NOT EXISTS (SELECT 1 FROM violations violation INNER JOIN stations violation_station ON violation_station.id=violation.station_id AND violation_station.code='SASO' WHERE violation.student_id=student.id AND violation.status='OPEN')",
        'FACILITIES' => "NOT EXISTS (SELECT 1 FROM violations violation INNER JOIN stations violation_station ON violation_station.id=violation.station_id AND violation_station.code='FACILITIES' WHERE violation.student_id=student.id AND violation.status='OPEN')",
        'ACCOUNTING' => "NOT EXISTS (SELECT 1 FROM accounting_obligations obligation WHERE obligation.student_id=student.id AND obligation.academic_period_id=period.id AND obligation.status='UNPAID')",
        'REGISTRAR' => "EXISTS (SELECT 1 FROM document_requirements requirement WHERE requirement.academic_period_id=period.id AND requirement.status='ACTIVE' AND requirement.is_required=1) AND NOT EXISTS (SELECT 1 FROM document_requirements requirement LEFT JOIN student_documents document ON document.document_requirement_id=requirement.id AND document.student_id=student.id WHERE requirement.academic_period_id=period.id AND requirement.status='ACTIVE' AND requirement.is_required=1 AND COALESCE(document.status,'MISSING')<>'VERIFIED') AND NOT EXISTS (SELECT 1 FROM clearance_stations prior_record INNER JOIN stations prior_station ON prior_station.id=prior_record.station_id WHERE prior_record.clearance_id=clearance.id AND prior_station.code<>'REGISTRAR' AND prior_station.parent_station_id IS NULL AND prior_record.status<>'APPROVED')",
        default => '0=1',
    };
    $exceptionSql = "EXISTS (SELECT 1 FROM clearance_exceptions exception_record WHERE exception_record.clearance_station_id=cs.id AND exception_record.status='APPROVED' AND (exception_record.expires_at IS NULL OR exception_record.expires_at>=NOW()))";
    $eligible = "(({$eligibilitySql}) OR ({$exceptionSql}))";
    $statement = $database->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN cs.status='APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN cs.status='RETURNED' THEN 1 ELSE 0 END) AS returned,
                SUM(CASE WHEN cs.status NOT IN ('APPROVED','RETURNED') AND {$eligible}
                    AND (period.clearance_status='PREPARING' OR cs.status='PENDING') THEN 1 ELSE 0 END) AS ready,
                SUM(CASE WHEN cs.status NOT IN ('APPROVED','RETURNED') AND NOT ({$eligible}
                    AND (period.clearance_status='PREPARING' OR cs.status='PENDING')) THEN 1 ELSE 0 END) AS pending
         FROM clearance_stations cs
         INNER JOIN stations station ON station.id=cs.station_id AND station.code=:station_code
         INNER JOIN clearances clearance ON clearance.id=cs.clearance_id
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.student_id=clearance.student_id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'"
    );
    $statement->execute(['station_code' => $stationCode]);
    $row = $statement->fetch() ?: [];
    if (in_array($stationCode, ['CLINIC', 'LIBRARY', 'BOOKSTORE'], true)) {
        $total = (int) ($row['total'] ?? 0);
        $approved = (int) ($row['approved'] ?? 0);
        return [
            'TOTAL' => $total,
            'READY' => 0,
            'PENDING' => $total - $approved,
            'RETURNED' => 0,
            'APPROVED' => $approved,
        ];
    }
    return [
        'TOTAL' => (int) ($row['total'] ?? 0),
        'READY' => (int) ($row['ready'] ?? 0),
        'PENDING' => (int) ($row['pending'] ?? 0),
        'RETURNED' => (int) ($row['returned'] ?? 0),
        'APPROVED' => (int) ($row['approved'] ?? 0),
    ];
}

function officeRows(PDO $database, string $stationCode, string $search = '', int $limit = 100, int $offset = 0, string $filter = 'ALL'): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $filterSql = officeListFilterSql($stationCode, $filter);
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
         WHERE (student.student_number LIKE :number_search
            OR student.first_name LIKE :first_search
            OR student.last_name LIKE :last_search
            OR student.program LIKE :program_search)
           AND {$filterSql}
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
        $details[] = 'Library card: ' . str_replace('_', ' ', (string) $cardStatus);
        $eligible = $cardStatus === 'VERIFIED';
    } elseif ($stationCode === 'BOOKSTORE') {
        $open = countQuery($database, "SELECT COUNT(*) FROM bookstore_obligations WHERE student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'", ['student_id' => $studentId, 'period_id' => $periodId]);
        $details[] = $open === 0 ? 'No pending Bookstore obligations' : "Pending Bookstore obligations: {$open}";
        $eligible = $open === 0;
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

function clearClinicClearance(PDO $database, int $clearanceId, int $staffUserId, string $remarks = ''): bool
{
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, 'CLINIC', $clearanceId, true);
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Clinic clearance decisions are not available for this semester.');
        }
        if ($context['clearance_status'] === 'OPEN' && $context['station_status'] === 'LOCKED') {
            throw new RuntimeException('The preceding clearance requirements are not complete.');
        }
        $medical = $database->prepare(
            "INSERT INTO medical_requirements (student_id,academic_period_id,status,remarks,verified_by,verified_at)
             VALUES (:student_id,:period_id,'VERIFIED',:remarks,:user_id,NOW())
             ON DUPLICATE KEY UPDATE status='VERIFIED',remarks=VALUES(remarks),verified_by=VALUES(verified_by),verified_at=NOW()"
        );
        $medical->execute([
            'student_id' => $context['student_id'],
            'period_id' => $context['academic_period_id'],
            'remarks' => trim($remarks) !== '' ? trim($remarks) : null,
            'user_id' => $staffUserId,
        ]);
        if ($context['station_status'] === 'APPROVED') {
            $database->commit();
            return false;
        }
        $approve = $database->prepare("UPDATE clearance_stations SET status='APPROVED',approved_by=:user_id,approved_at=NOW(),returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=:id");
        $approve->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        $requirements = $database->prepare("UPDATE requirement_records SET status='VERIFIED',verified_by=:user_id,verified_at=NOW(),remarks=NULL WHERE clearance_station_id=:id AND status NOT IN ('VERIFIED','RESOLVED')");
        $requirements->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        unlockAfterOfficeApproval($database, 'CLINIC', $clearanceId);
        notifyAndAuditOffice($database, $context, $staffUserId, 'CLINIC_CLEARANCE_APPROVED', 'Clinic verified the medical record and approved your clearance.', $context['station_status'], 'APPROVED');
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function clearLibraryClearance(PDO $database, int $clearanceId, int $staffUserId): bool
{
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, 'LIBRARY', $clearanceId, true);
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Library clearance decisions are not available for this semester.');
        }
        if ($context['clearance_status'] === 'OPEN' && $context['station_status'] === 'LOCKED') {
            throw new RuntimeException('The preceding clearance requirements are not complete.');
        }
        $card = $database->prepare(
            "INSERT INTO library_cards (student_id,status,verified_by,verified_at)
             VALUES (:student_id,'VERIFIED',:user_id,NOW())
             ON DUPLICATE KEY UPDATE status='VERIFIED',verified_by=VALUES(verified_by),verified_at=NOW()"
        );
        $card->execute(['student_id' => $context['student_id'], 'user_id' => $staffUserId]);
        if ($context['station_status'] === 'APPROVED') {
            $database->commit();
            return false;
        }
        $approve = $database->prepare("UPDATE clearance_stations SET status='APPROVED',approved_by=:user_id,approved_at=NOW(),returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=:id");
        $approve->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        $requirements = $database->prepare("UPDATE requirement_records SET status='VERIFIED',verified_by=:user_id,verified_at=NOW(),remarks=NULL WHERE clearance_station_id=:id AND status NOT IN ('VERIFIED','RESOLVED')");
        $requirements->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        unlockAfterOfficeApproval($database, 'LIBRARY', $clearanceId);
        notifyAndAuditOffice($database, $context, $staffUserId, 'LIBRARY_CLEARANCE_APPROVED', 'The Library verified your library card and cleared your Library requirement.', $context['station_status'], 'APPROVED');
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function clearBookstoreClearance(PDO $database, int $clearanceId, int $staffUserId, string $paymentReference = '', string $remarks = ''): bool
{
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, 'BOOKSTORE', $clearanceId, true);
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Bookstore clearance decisions are not available for this semester.');
        }
        if ($context['clearance_status'] === 'OPEN' && $context['station_status'] === 'LOCKED') {
            throw new RuntimeException('The preceding clearance requirements are not complete.');
        }
        $unpaid = countQuery($database, "SELECT COUNT(*) FROM bookstore_obligations WHERE student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'", ['student_id' => $context['student_id'], 'period_id' => $context['academic_period_id']]);
        if ($unpaid > 0 && trim($paymentReference) === '') {
            throw new RuntimeException('Enter the payment receipt or reference for the pending Bookstore obligation.');
        }
        if ($unpaid > 0) {
            $settle = $database->prepare("UPDATE bookstore_obligations SET status='CLEARED',payment_reference=:reference,remarks=CASE WHEN :remarks='' THEN remarks ELSE :remarks END,cleared_by=:user_id,cleared_at=NOW() WHERE student_id=:student_id AND academic_period_id=:period_id AND status='UNPAID'");
            $settle->execute(['reference' => trim($paymentReference), 'remarks' => trim($remarks), 'user_id' => $staffUserId, 'student_id' => $context['student_id'], 'period_id' => $context['academic_period_id']]);
        }
        if ($context['station_status'] === 'APPROVED') {
            $database->commit();
            return false;
        }
        $approve = $database->prepare("UPDATE clearance_stations SET status='APPROVED',approved_by=:user_id,approved_at=NOW(),returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=:id");
        $approve->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        $requirements = $database->prepare("UPDATE requirement_records SET status='VERIFIED',verified_by=:user_id,verified_at=NOW(),remarks=NULL WHERE clearance_station_id=:id AND status NOT IN ('VERIFIED','RESOLVED')");
        $requirements->execute(['user_id' => $staffUserId, 'id' => $context['clearance_station_id']]);
        unlockAfterOfficeApproval($database, 'BOOKSTORE', $clearanceId);
        notifyAndAuditOffice($database, $context, $staffUserId, 'BOOKSTORE_CLEARANCE_APPROVED', 'The Bookstore verified that all obligations were settled and cleared your Bookstore requirement.', $context['station_status'], 'APPROVED');
        $database->commit();
        return true;
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
