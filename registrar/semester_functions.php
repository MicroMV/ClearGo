<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

function registrarActivePeriod(PDO $database, bool $lock = false): array
{
    $sql = "SELECT id,school_year,semester,status,clearance_status,opened_at,closed_at
            FROM academic_periods WHERE status='ACTIVE' ORDER BY id LIMIT 2" . ($lock ? ' FOR UPDATE' : '');
    $periods = $database->query($sql)->fetchAll();
    if (count($periods) !== 1) {
        throw new RuntimeException('Exactly one active semester must be configured.');
    }
    return $periods[0];
}

function semesterLabel(array $period): string
{
    $labels = ['1ST' => 'First Semester', '2ND' => 'Second Semester', 'SUMMER' => 'Summer'];
    return ($labels[$period['semester']] ?? $period['semester']) . ' A.Y. ' . $period['school_year'];
}

function semesterReadiness(PDO $database, int $periodId): array
{
    $statement = $database->prepare(
        "SELECT readiness.id,station.id AS station_id,station.code,station.name,readiness.status,
                readiness.ready_at,readiness.notes,user.email AS ready_by_email
         FROM office_period_readiness readiness
         INNER JOIN stations station ON station.id=readiness.station_id
         LEFT JOIN users user ON user.id=readiness.ready_by
         WHERE readiness.academic_period_id=:period_id
         ORDER BY COALESCE(station.parent_station_id,station.id),station.sort_order"
    );
    $statement->execute(['period_id' => $periodId]);
    return $statement->fetchAll();
}

/** Initialize each college's first office without replacing existing station decisions. */
function initializeSemesterClearanceStations(PDO $database, int $periodId): void
{
    $stationRecords = $database->prepare(
        "INSERT IGNORE INTO clearance_stations (clearance_id,station_id,status)
         SELECT clearance.id,station.id,
                CASE WHEN station.code=CASE WHEN organization.code='CBE' THEN 'ORGANIZATION' ELSE 'DEAN' END
                     THEN 'PENDING' ELSE 'LOCKED' END
         FROM clearances clearance
         INNER JOIN students student ON student.id=clearance.student_id
         INNER JOIN organizations organization ON organization.id=student.organization_id
         CROSS JOIN stations station
         WHERE clearance.academic_period_id=:period_id AND station.status='ACTIVE'"
    );
    $stationRecords->execute(['period_id' => $periodId]);
}

function prepareSemesterClearanceRecords(PDO $database, int $periodId): void
{
    $clearances = $database->prepare(
        "INSERT INTO clearances (student_id,academic_period_id,reference_number,status,started_at)
         SELECT enrollment.student_id,:period_id,
                CONCAT('CLR-',:period_reference,'-',UPPER(REPLACE(REPLACE(student.student_number,'-',''),' ','')),'-',UPPER(SUBSTRING(MD5(CONCAT(UUID(),student.id)),1,6))),
                'IN_PROGRESS',NOW()
         FROM semester_enrollments enrollment
         INNER JOIN students student ON student.id=enrollment.student_id
         WHERE enrollment.academic_period_id=:enrollment_period AND enrollment.status='ENROLLED'
         ON DUPLICATE KEY UPDATE clearances.id=clearances.id"
    );
    $clearances->execute(['period_id' => $periodId, 'period_reference' => $periodId, 'enrollment_period' => $periodId]);
    initializeSemesterClearanceStations($database, $periodId);
    $requirements = $database->prepare(
        "INSERT IGNORE INTO requirement_records (clearance_station_id,requirement_id,status)
         SELECT clearance_station.id,requirement.id,'NOT_STARTED'
         FROM clearances clearance
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
         INNER JOIN students student ON student.id=clearance.student_id
         INNER JOIN clearance_stations clearance_station ON clearance_station.clearance_id=clearance.id
         INNER JOIN requirements requirement ON requirement.station_id=clearance_station.station_id
         WHERE clearance.academic_period_id=:period_id AND requirement.status='ACTIVE' AND requirement.is_required=1
           AND (requirement.applicable_organization_id IS NULL OR requirement.applicable_organization_id=student.organization_id)
           AND (requirement.minimum_year_level IS NULL OR student.year_level>=requirement.minimum_year_level)
           AND (requirement.maximum_year_level IS NULL OR student.year_level<=requirement.maximum_year_level)
           AND (requirement.applicable_semester IS NULL OR requirement.applicable_semester=period.semester)"
    );
    $requirements->execute(['period_id' => $periodId]);
    $removeNonApplicableRequirements = $database->prepare(
        "DELETE record
         FROM requirement_records record
         INNER JOIN clearance_stations clearance_station ON clearance_station.id=record.clearance_station_id
         INNER JOIN clearances clearance ON clearance.id=clearance_station.clearance_id
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
         INNER JOIN students student ON student.id=clearance.student_id
         INNER JOIN requirements requirement ON requirement.id=record.requirement_id
         WHERE clearance.academic_period_id=:period_id
           AND ((requirement.applicable_organization_id IS NOT NULL AND requirement.applicable_organization_id<>student.organization_id)
             OR (requirement.minimum_year_level IS NOT NULL AND student.year_level<requirement.minimum_year_level)
             OR (requirement.maximum_year_level IS NOT NULL AND student.year_level>requirement.maximum_year_level)
             OR (requirement.applicable_semester IS NOT NULL AND requirement.applicable_semester<>period.semester))"
    );
    $removeNonApplicableRequirements->execute(['period_id' => $periodId]);
    $medical = $database->prepare("INSERT IGNORE INTO medical_requirements (student_id,academic_period_id,status) SELECT student_id,:period_id,'PENDING' FROM semester_enrollments WHERE academic_period_id=:enrollment_period AND status='ENROLLED'");
    $medical->execute(['period_id' => $periodId, 'enrollment_period' => $periodId]);
    // Library-card rows are created on demand when Library reviews a student.
    // Avoid bulk inserting thousands of globally scoped card records during import.
    $fees = $database->prepare("INSERT IGNORE INTO student_bookstore_fees (student_id,fee_id,status) SELECT enrollment.student_id,fee.id,'UNPAID' FROM semester_enrollments enrollment INNER JOIN bookstore_fees fee ON fee.academic_period_id=enrollment.academic_period_id AND fee.status='ACTIVE' AND fee.is_required=1 WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'");
    $fees->execute(['period_id' => $periodId]);
    $togas = $database->prepare("INSERT IGNORE INTO facilities_obligations (student_id,academic_period_id,item_type,description,status) SELECT enrollment.student_id,enrollment.academic_period_id,'TOGA','Graduation toga','OUTSTANDING' FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id INNER JOIN academic_periods period ON period.id=enrollment.academic_period_id AND period.semester='2ND' WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED' AND student.status='ACTIVE' AND student.year_level>=4");
    $togas->execute(['period_id' => $periodId]);
}

function createRegistrarSemester(PDO $database, ?int $registrarUserId, string $schoolYear, string $semester): int
{
    $schoolYear = trim($schoolYear);
    $semester = strtoupper(trim($semester));
    if (preg_match('/^(\d{4})-(\d{4})$/', $schoolYear, $matches) !== 1 || (int) $matches[2] !== (int) $matches[1] + 1) {
        throw new RuntimeException('Use an academic year such as 2026-2027.');
    }
    if (!in_array($semester, ['1ST', '2ND', 'SUMMER'], true)) {
        throw new RuntimeException('Select a valid semester.');
    }

    $database->beginTransaction();
    try {
        $existing = $database->prepare('SELECT id FROM academic_periods WHERE school_year=:school_year AND semester=:semester LIMIT 1');
        $existing->execute(['school_year' => $schoolYear, 'semester' => $semester]);
        if ($existing->fetchColumn() !== false) {
            throw new RuntimeException('That academic semester already exists. Open its archive instead of creating a duplicate.');
        }

        $database->exec("UPDATE academic_periods SET status='CLOSED',clearance_status='CLOSED',closed_at=COALESCE(closed_at,NOW()) WHERE status='ACTIVE'");
        $insert = $database->prepare("INSERT INTO academic_periods (school_year,semester,status,clearance_status) VALUES (:school_year,:semester,'ACTIVE','PREPARING')");
        $insert->execute(['school_year' => $schoolYear, 'semester' => $semester]);
        $periodId = (int) $database->lastInsertId();
        $readiness = $database->prepare("INSERT INTO office_period_readiness (academic_period_id,station_id,status) SELECT :period_id,id,'NOT_READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'");
        $readiness->execute(['period_id' => $periodId]);
        $registrarStation = $database->query("SELECT id FROM stations WHERE code='REGISTRAR' LIMIT 1")->fetchColumn();
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,new_status) VALUES (:user_id,'SEMESTER_CREATED',:station_id,:description,'PREPARING')");
        $audit->execute(['user_id' => $registrarUserId, 'station_id' => $registrarStation, 'description' => ($registrarUserId === null ? 'Standalone local debug created ' : 'Registrar created ') . $semester . ' Semester A.Y. ' . $schoolYear . '.']);
        notifyActiveRoles(
            $database,
            ['DEAN','ORG_STAFF','SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','COMLAB_STAFF','ACCOUNTING_STAFF'],
            'New semester ready for preparation',
            semesterLabel(['semester' => $semester, 'school_year' => $schoolYear]) . ' was created. Review your office requirements and mark your office Ready.',
            $periodId
        );
        $database->commit();
        return $periodId;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function openRegistrarClearance(PDO $database, ?int $registrarUserId): int
{
    $database->beginTransaction();
    try {
        $period = registrarActivePeriod($database, true);
        if ($period['clearance_status'] === 'OPEN') {
            throw new RuntimeException('Clearance is already open for this semester.');
        }
        if ($period['clearance_status'] !== 'PREPARING') {
            throw new RuntimeException('This semester is not available for preparation.');
        }
        $readinessQuery = $database->prepare("SELECT COUNT(*) FROM office_period_readiness WHERE academic_period_id=:period_id AND status<>'READY'");
        $readinessQuery->execute(['period_id' => $period['id']]);
        if ((int) $readinessQuery->fetchColumn() > 0) {
            throw new RuntimeException('Every office must click Ready before clearance can be opened.');
        }
        $enrollmentQuery = $database->prepare("SELECT COUNT(*) FROM semester_enrollments WHERE academic_period_id=:period_id AND status='ENROLLED'");
        $enrollmentQuery->execute(['period_id' => $period['id']]);
        $enrollmentCount = (int) $enrollmentQuery->fetchColumn();
        if ($enrollmentCount < 1) {
            throw new RuntimeException('Import or enroll students before opening clearance.');
        }

        $open = $database->prepare("UPDATE academic_periods SET clearance_status='OPEN',opened_by=:user_id,opened_at=NOW(),closed_at=NULL WHERE id=:period_id");
        $open->execute(['user_id' => $registrarUserId, 'period_id' => $period['id']]);
        $clearances = $database->prepare(
            "INSERT INTO clearances (student_id,academic_period_id,reference_number,status,started_at)
             SELECT enrollment.student_id,:period_id,
                    CONCAT('CLR-',:period_reference,'-',UPPER(REPLACE(REPLACE(student.student_number,'-',''),' ','')),'-',UPPER(SUBSTRING(MD5(CONCAT(UUID(),student.id)),1,6))),
                    'IN_PROGRESS',NOW()
             FROM semester_enrollments enrollment
             INNER JOIN students student ON student.id=enrollment.student_id
             WHERE enrollment.academic_period_id=:enrollment_period AND enrollment.status='ENROLLED'
             ON DUPLICATE KEY UPDATE clearances.id=clearances.id"
        );
        $clearances->execute(['period_id' => $period['id'], 'period_reference' => $period['id'], 'enrollment_period' => $period['id']]);

        initializeSemesterClearanceStations($database, (int) $period['id']);
        $requirements = $database->prepare(
            "INSERT IGNORE INTO requirement_records (clearance_station_id,requirement_id,status)
             SELECT clearance_station.id,requirement.id,'NOT_STARTED'
             FROM clearances clearance
             INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
             INNER JOIN students student ON student.id=clearance.student_id
             INNER JOIN clearance_stations clearance_station ON clearance_station.clearance_id=clearance.id
             INNER JOIN requirements requirement ON requirement.station_id=clearance_station.station_id
             WHERE clearance.academic_period_id=:period_id AND requirement.status='ACTIVE' AND requirement.is_required=1
               AND (requirement.applicable_organization_id IS NULL OR requirement.applicable_organization_id=student.organization_id)
               AND (requirement.minimum_year_level IS NULL OR student.year_level>=requirement.minimum_year_level)
               AND (requirement.maximum_year_level IS NULL OR student.year_level<=requirement.maximum_year_level)
               AND (requirement.applicable_semester IS NULL OR requirement.applicable_semester=period.semester)"
        );
        $requirements->execute(['period_id' => $period['id']]);
        $removeNonApplicableRequirements = $database->prepare(
            "DELETE record
             FROM requirement_records record
             INNER JOIN clearance_stations clearance_station ON clearance_station.id=record.clearance_station_id
             INNER JOIN clearances clearance ON clearance.id=clearance_station.clearance_id
             INNER JOIN academic_periods requirement_period ON requirement_period.id=clearance.academic_period_id
             INNER JOIN students student ON student.id=clearance.student_id
             INNER JOIN requirements requirement ON requirement.id=record.requirement_id
             WHERE clearance.academic_period_id=:period_id
               AND ((requirement.applicable_organization_id IS NOT NULL AND requirement.applicable_organization_id<>student.organization_id)
                 OR (requirement.minimum_year_level IS NOT NULL AND student.year_level<requirement.minimum_year_level)
                 OR (requirement.maximum_year_level IS NOT NULL AND student.year_level>requirement.maximum_year_level)
                 OR (requirement.applicable_semester IS NOT NULL AND requirement.applicable_semester<>requirement_period.semester))"
        );
        $removeNonApplicableRequirements->execute(['period_id' => $period['id']]);
        $medical = $database->prepare("INSERT IGNORE INTO medical_requirements (student_id,academic_period_id,status) SELECT student_id,:period_id,'PENDING' FROM semester_enrollments WHERE academic_period_id=:enrollment_period AND status='ENROLLED'");
        $medical->execute(['period_id' => $period['id'], 'enrollment_period' => $period['id']]);
        $cards = $database->prepare("INSERT IGNORE INTO library_cards (student_id,status) SELECT student_id,'PENDING' FROM semester_enrollments WHERE academic_period_id=:period_id AND status='ENROLLED'");
        $cards->execute(['period_id' => $period['id']]);
        $fees = $database->prepare("INSERT IGNORE INTO student_bookstore_fees (student_id,fee_id,status) SELECT enrollment.student_id,fee.id,'UNPAID' FROM semester_enrollments enrollment INNER JOIN bookstore_fees fee ON fee.academic_period_id=enrollment.academic_period_id AND fee.status='ACTIVE' AND fee.is_required=1 WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'");
        $fees->execute(['period_id' => $period['id']]);
        $togas = $database->prepare("INSERT IGNORE INTO facilities_obligations (student_id,academic_period_id,item_type,description,status) SELECT enrollment.student_id,enrollment.academic_period_id,'TOGA','Graduation toga','OUTSTANDING' FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id INNER JOIN academic_periods period ON period.id=enrollment.academic_period_id AND period.semester='2ND' WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED' AND student.status='ACTIVE' AND student.year_level>=4");
        $togas->execute(['period_id' => $period['id']]);
        $studentNotices = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) SELECT student.user_id,enrollment.academic_period_id,'Semester clearance is open',:message FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'");
        $studentNotices->execute(['message' => semesterLabel($period) . ' clearance is now available.', 'period_id' => $period['id']]);
        notifyActiveRoles($database, ['DEAN','ORG_STAFF'], 'Semester clearance opened', semesterLabel($period) . ' is open. Review the students currently pending at your assigned office.', (int) $period['id']);
        notifyActiveRoles(
            $database,
            ['SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','COMLAB_STAFF','ACCOUNTING_STAFF'],
            'Semester clearance opened',
            semesterLabel($period) . ' is open. You will be notified as each student reaches your office for action.',
            (int) $period['id']
        );
        $registrarStation = $database->query("SELECT id FROM stations WHERE code='REGISTRAR' LIMIT 1")->fetchColumn();
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status) VALUES (:user_id,'SEMESTER_CLEARANCE_OPENED',:station_id,:description,'PREPARING','OPEN')");
        $audit->execute(['user_id' => $registrarUserId, 'station_id' => $registrarStation, 'description' => ($registrarUserId === null ? 'Standalone local debug opened ' : 'Registrar opened ') . semesterLabel($period) . ' clearance for ' . $enrollmentCount . ' enrolled students.']);
        $database->commit();
        return $enrollmentCount;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
