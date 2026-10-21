<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../auth/signature_functions.php';
require_once __DIR__ . '/dean_evaluation_policy.php';

function activeStudentForUser(PDO $database, int $userId, bool $lock = false): array
{
    $sql = "SELECT students.id, students.student_number, students.first_name, students.last_name,
                   students.organization_id, organizations.code AS organization_code
            FROM students
            INNER JOIN organizations ON organizations.id = students.organization_id
            WHERE students.user_id = :user_id AND students.status = 'ACTIVE'
            LIMIT 1";
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $statement = $database->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    $student = $statement->fetch();

    if ($student === false) {
        throw new RuntimeException('No active student profile was found.');
    }

    return $student;
}

function activeAcademicPeriod(PDO $database): array
{
    $statement = $database->prepare(
        "SELECT id, school_year, semester
         FROM academic_periods
         WHERE status = 'ACTIVE'
         ORDER BY id
         LIMIT 2"
    );
    $statement->execute();
    $periods = $statement->fetchAll();

    if (count($periods) !== 1) {
        throw new RuntimeException('Clearance applications require exactly one active academic period.');
    }

    return $periods[0];
}

function createClearanceReference(array $student, array $period): string
{
    $studentNumber = preg_replace('/[^A-Z0-9]+/', '', strtoupper((string) $student['student_number']));
    return sprintf('CLR-%d-%s-%s', (int) $period['id'], substr($studentNumber, -16), strtoupper(bin2hex(random_bytes(3))));
}

function startStudentClearance(PDO $database, int $userId): array
{
    $database->beginTransaction();

    try {
        $student = activeStudentForUser($database, $userId, true);
        $period = activeAcademicPeriod($database);

        $existingQuery = $database->prepare(
            'SELECT id, reference_number, status
             FROM clearances
             WHERE student_id = :student_id AND academic_period_id = :period_id
             LIMIT 1'
        );
        $existingQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $existing = $existingQuery->fetch();

        if ($existing !== false) {
            $database->commit();
            return ['created' => false, 'clearance' => $existing];
        }

        $reference = createClearanceReference($student, $period);
        $insertClearance = $database->prepare(
            "INSERT INTO clearances
                (student_id, academic_period_id, reference_number, status, started_at)
             VALUES
                (:student_id, :period_id, :reference_number, 'IN_PROGRESS', NOW())"
        );
        $insertClearance->execute([
            'student_id' => $student['id'],
            'period_id' => $period['id'],
            'reference_number' => $reference,
        ]);
        $clearanceId = (int) $database->lastInsertId();

        $firstStationCode = $student['organization_code'] === 'CBE' ? 'ORGANIZATION' : 'DEAN';
        $stationInsert = $database->prepare(
            "INSERT INTO clearance_stations (clearance_id, station_id, status)
             SELECT :clearance_id, id,
                    CASE WHEN code = :first_station_code THEN 'PENDING' ELSE 'LOCKED' END
             FROM stations
             WHERE status = 'ACTIVE'"
        );
        $stationInsert->execute(['clearance_id' => $clearanceId, 'first_station_code' => $firstStationCode]);

        $requiredStationCount = (int) $database->query("SELECT COUNT(*) FROM stations WHERE status = 'ACTIVE'")->fetchColumn();
        if ($requiredStationCount === 0 || $stationInsert->rowCount() !== $requiredStationCount) {
            throw new RuntimeException('The clearance stations are not configured correctly.');
        }

        $requirementInsert = $database->prepare(
            "INSERT INTO requirement_records (clearance_station_id, requirement_id, status)
             SELECT clearance_stations.id, requirements.id, 'NOT_STARTED'
             FROM clearance_stations
             INNER JOIN clearances ON clearances.id = clearance_stations.clearance_id
             INNER JOIN academic_periods ON academic_periods.id = clearances.academic_period_id
             INNER JOIN students ON students.id = clearances.student_id
             INNER JOIN requirements ON requirements.station_id = clearance_stations.station_id
             WHERE clearance_stations.clearance_id = :clearance_id
               AND requirements.status = 'ACTIVE'
               AND requirements.is_required = 1
               AND (requirements.applicable_organization_id IS NULL
                    OR requirements.applicable_organization_id = students.organization_id)
               AND (requirements.minimum_year_level IS NULL
                    OR students.year_level >= requirements.minimum_year_level)
               AND (requirements.maximum_year_level IS NULL
                    OR students.year_level <= requirements.maximum_year_level)
               AND (requirements.applicable_semester IS NULL
                    OR requirements.applicable_semester = academic_periods.semester)"
        );
        $requirementInsert->execute(['clearance_id' => $clearanceId]);

        require_once __DIR__ . '/office_workflow.php';
        ensureOfficeSourceRecords($database, (int) $student['id'], (int) $period['id']);

        $firstStationId = $database->prepare('SELECT id FROM stations WHERE code = :code LIMIT 1');
        $firstStationId->execute(['code' => $firstStationCode]);
        $firstStationId = $firstStationId->fetchColumn();
        if ($firstStationId === false) {
            throw new RuntimeException('The first clearance station is unavailable.');
        }

        $auditInsert = $database->prepare(
            "INSERT INTO audit_logs
                (user_id, action, station_id, student_id, description, old_status, new_status)
             VALUES
                (:user_id, 'CLEARANCE_STARTED', :station_id, :student_id,
                 'Student started a clearance application.', 'NOT_STARTED', 'IN_PROGRESS')"
        );
        $auditInsert->execute([
            'user_id' => $userId,
            'station_id' => $firstStationId,
            'student_id' => $student['id'],
        ]);

        createNotification(
            $database,
            $userId,
            'Clearance application started',
            $firstStationCode === 'ORGANIZATION'
                ? 'Your clearance application has started. Complete Organization clearance first.'
                : 'Your clearance application has started. Review the applicable requirements for your Dean clearance first.',
            (int) $period['id']
        );

        $staffUsers = $database->prepare(
            "SELECT staff.id
             FROM users staff
             INNER JOIN staff_assignments assignment ON assignment.user_id=staff.id AND assignment.status='ACTIVE'
             INNER JOIN stations station ON station.id=assignment.station_id AND station.code=:station_code
             WHERE staff.status='ACTIVE'
               AND assignment.organization_id=:organization_id"
        );
        $staffUsers->execute([
            'station_code' => $firstStationCode,
            'organization_id' => $student['organization_id'],
        ]);
        foreach ($staffUsers->fetchAll(PDO::FETCH_COLUMN) as $staffUserId) {
            createNotification(
                $database,
                (int) $staffUserId,
                $firstStationCode === 'ORGANIZATION' ? 'New Organization clearance application' : 'New Dean clearance application',
                $student['student_number'] . ' reached your office with PENDING status and needs review.',
                (int) $period['id']
            );
        }

        $database->commit();

        return [
            'created' => true,
            'clearance' => [
                'id' => $clearanceId,
                'reference_number' => $reference,
                'status' => 'IN_PROGRESS',
            ],
        ];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function deanAssignment(PDO $database, int $deanUserId): array
{
    $statement = $database->prepare(
        "SELECT organization.id AS organization_id, organization.code AS organization_code,
                organization.name AS organization_name
         FROM staff_assignments assignment
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN'
         INNER JOIN organizations organization ON organization.id=assignment.organization_id
         WHERE assignment.user_id=:user_id AND assignment.status='ACTIVE'
           AND organization.status='ACTIVE'
         LIMIT 1"
    );
    $statement->execute(['user_id' => $deanUserId]);
    $assignment = $statement->fetch();
    if ($assignment === false) {
        throw new RuntimeException('This Dean account is not assigned to a college. Contact the Registrar.');
    }
    return $assignment;
}

function approveDeanClearance(PDO $database, int $deanUserId, int $clearanceId, bool $readyOnly = false): bool
{
    $database->beginTransaction();

    try {
        $dean = deanAssignment($database, $deanUserId);
        $statement = $database->prepare(
            "SELECT clearances.id, clearances.academic_period_id, academic_periods.clearance_status,
                    students.id AS student_id, students.user_id AS student_user_id,
                    students.student_number, students.organization_id, students.year_level,
                    clearance_stations.id AS clearance_station_id,
                    clearance_stations.status AS dean_status
             FROM clearances
             INNER JOIN academic_periods ON academic_periods.id=clearances.academic_period_id AND academic_periods.status='ACTIVE'
             INNER JOIN students ON students.id = clearances.student_id
             INNER JOIN stations ON stations.code = 'DEAN'
             INNER JOIN clearance_stations ON clearance_stations.clearance_id = clearances.id
                AND clearance_stations.station_id = stations.id
             WHERE clearances.id = :clearance_id
             LIMIT 1
             FOR UPDATE"
        );
        $statement->execute(['clearance_id' => $clearanceId]);
        $clearance = $statement->fetch();

        if ($clearance === false) {
            throw new RuntimeException('The clearance application was not found.');
        }
        if ($readyOnly && $clearance['dean_status'] !== 'PENDING') {
            throw new RuntimeException('This student is no longer ready for bulk clearance.');
        }
        if (!in_array($clearance['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Dean clearance is not available for processing.');
        }
        if ((int) $clearance['organization_id'] !== (int) $dean['organization_id']) {
            throw new RuntimeException('This student belongs to a different college.');
        }
        if ($clearance['dean_status'] === 'APPROVED') {
            $database->commit();
            return false;
        }
        if ($clearance['dean_status'] === 'LOCKED') {
            throw new RuntimeException('Dean clearance is not available for this student.');
        }
        if ($dean['organization_code'] === 'CBE') {
            $organizationStatus = $database->prepare(
                "SELECT organization_record.status
                 FROM clearance_stations organization_record
                 INNER JOIN stations organization_station
                    ON organization_station.id=organization_record.station_id
                   AND organization_station.code='ORGANIZATION'
                 WHERE organization_record.clearance_id=:clearance_id
                 LIMIT 1 FOR UPDATE"
            );
            $organizationStatus->execute(['clearance_id' => $clearanceId]);
            if ($organizationStatus->fetchColumn() !== 'APPROVED') {
                throw new RuntimeException('CBE Organization/YBC approval is required before Dean clearance.');
            }
        }
        if ($dean['organization_code'] === 'CCS') {
            requireCcsEvaluationCompletion($database, $clearanceId);
        }
        if ($readyOnly) {
            $blocking = $database->prepare(
                "SELECT COUNT(*) FROM requirement_records record
                 INNER JOIN requirements requirement ON requirement.id=record.requirement_id
                 WHERE record.clearance_station_id=:station_id AND requirement.status='ACTIVE'
                   AND requirement.name<>'Dean Approval' AND record.status NOT IN ('VERIFIED','RESOLVED')
                   AND NOT (:allow_ojt=1 AND requirement.requirement_code='CBE_OJT_REQUIREMENTS')"
            );
            $blocking->execute([
                'station_id' => $clearance['clearance_station_id'],
                'allow_ojt' => $dean['organization_code'] === 'CBE' && (int) $clearance['year_level'] === 4 ? 1 : 0,
            ]);
            if ((int) $blocking->fetchColumn() > 0) {
                throw new RuntimeException('This student still has incomplete Dean requirements.');
            }
        }
        if ($dean['organization_code'] === 'CBE') {
            $verifyOjt = $database->prepare(
                "UPDATE requirement_records record
                 INNER JOIN requirements requirement ON requirement.id=record.requirement_id
                 SET record.status='VERIFIED',record.verified_by=:user_id,
                     record.verified_at=NOW(),record.remarks='Confirmed by the CBE Dean during approval.'
                 WHERE record.clearance_station_id=:station_id
                   AND requirement.requirement_code='CBE_OJT_REQUIREMENTS'
                   AND record.status NOT IN ('VERIFIED','RESOLVED')"
            );
            $verifyOjt->execute([
                'user_id' => $deanUserId,
                'station_id' => $clearance['clearance_station_id'],
            ]);
        }
        $incomplete = $database->prepare(
            "SELECT requirements.name
             FROM requirement_records
             INNER JOIN requirements ON requirements.id = requirement_records.requirement_id
             WHERE requirement_records.clearance_station_id = :station_id
               AND requirements.status = 'ACTIVE'
               AND requirements.name <> 'Dean Approval'
               AND requirement_records.status NOT IN ('VERIFIED','RESOLVED')
             ORDER BY requirements.id LIMIT 1"
        );
        $incomplete->execute(['station_id' => $clearance['clearance_station_id']]);
        $incompleteName = $incomplete->fetchColumn();
        if ($incompleteName !== false) {
            throw new RuntimeException((string) $incompleteName . ' must be verified before Dean approval.');
        }

        $approve = $database->prepare(
            "UPDATE clearance_stations
             SET status = 'APPROVED', approved_by = :approved_by, approved_at = NOW(),
                 returned_by = NULL, returned_at = NULL, returned_reason = NULL
             WHERE id = :clearance_station_id AND status <> 'APPROVED'"
        );
        $approve->execute([
            'approved_by' => $deanUserId,
            'clearance_station_id' => $clearance['clearance_station_id'],
        ]);
        if ($approve->rowCount() !== 1) {
            throw new RuntimeException('Dean clearance could not be approved.');
        }

        $approvalRequirement = $database->prepare(
            "UPDATE requirement_records record
             INNER JOIN requirements requirement ON requirement.id = record.requirement_id
             SET record.status = 'VERIFIED', record.verified_by = :verified_by,
                 record.verified_at = NOW(), record.remarks = NULL
             WHERE record.clearance_station_id = :clearance_station_id
               AND requirement.name = 'Dean Approval'"
        );
        $approvalRequirement->execute([
            'verified_by' => $deanUserId,
            'clearance_station_id' => $clearance['clearance_station_id'],
        ]);

        $unlockOrganization = $database->prepare(
            "UPDATE clearance_stations
             INNER JOIN stations ON stations.id = clearance_stations.station_id
             SET clearance_stations.status = 'PENDING'
             WHERE clearance_stations.clearance_id = :clearance_id
               AND stations.code = 'ORGANIZATION'
               AND clearance_stations.status = 'LOCKED'"
        );
        $unlockOrganization->execute(['clearance_id' => $clearanceId]);
        $organizationStatus = $database->prepare(
            "SELECT organization_record.status
             FROM clearance_stations organization_record
             INNER JOIN stations organization_station ON organization_station.id=organization_record.station_id
                AND organization_station.code='ORGANIZATION'
             WHERE organization_record.clearance_id=:clearance_id"
        );
        $organizationStatus->execute(['clearance_id' => $clearanceId]);
        $organizationClearanceStatus = $organizationStatus->fetchColumn();
        if ($organizationClearanceStatus === false) {
            throw new RuntimeException('Organization clearance could not be found.');
        }
        if ($organizationClearanceStatus === 'APPROVED') {
            $unlockSasoChecks = $database->prepare(
                "UPDATE clearance_stations child_record
                 INNER JOIN stations child_station ON child_station.id=child_record.station_id
                 INNER JOIN stations parent_station ON parent_station.id=child_station.parent_station_id
                    AND parent_station.code='SASO'
                 SET child_record.status='PENDING'
                 WHERE child_record.clearance_id=:clearance_id AND child_record.status='LOCKED'"
            );
            $unlockSasoChecks->execute(['clearance_id' => $clearanceId]);
        }

        $stationId = $database->query("SELECT id FROM stations WHERE code = 'DEAN' LIMIT 1")->fetchColumn();
        $isCbe = $dean['organization_code'] === 'CBE';
        $notification = $database->prepare(
            "INSERT INTO notifications (user_id, academic_period_id, title, message)
             VALUES (:user_id, :period_id, 'Dean clearance approved', :message)"
        );
        $notification->execute([
            'user_id' => $clearance['student_user_id'],
            'period_id' => $clearance['academic_period_id'],
            'message' => $isCbe
                ? 'Your Dean clearance is cleared. SASO sub-checks are now available.'
                : 'Your Dean clearance is cleared. Organization clearance is now available.',
        ]);

        $organizationStaffNotification = $database->prepare(
            "INSERT INTO notifications (user_id,academic_period_id,title,message)
             SELECT assignment.user_id,:period_id,'Student ready for organization review',:message
             FROM staff_assignments assignment
             INNER JOIN students student ON student.organization_id=assignment.organization_id
             INNER JOIN stations station ON station.id=assignment.station_id AND station.code='ORGANIZATION'
             WHERE student.id=:student_id AND assignment.status='ACTIVE'"
        );
        if (!$isCbe) {
            $organizationStaffNotification->execute([
                'period_id' => $clearance['academic_period_id'],
                'message' => $clearance['student_number'] . ' completed Dean clearance and is ready for Organization review.',
                'student_id' => $clearance['student_id'],
            ]);
        }

        $audit = $database->prepare(
            "INSERT INTO audit_logs
                (user_id, action, station_id, student_id, description, old_status, new_status)
             VALUES
                (:user_id, 'DEAN_CLEARANCE_APPROVED', :station_id, :student_id,
                 'Dean approved clearance after verifying the applicable college and year-level requirements.',
                 :old_status, 'APPROVED')"
        );
        $audit->execute([
            'user_id' => $deanUserId,
            'station_id' => $stationId,
            'student_id' => $clearance['student_id'],
            'old_status' => $clearance['dean_status'],
        ]);

        captureClearanceStationSignature($database, (int) $clearance['clearance_station_id'], $deanUserId);
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function approveAllReadyCbeDeanStudents(PDO $database, int $deanUserId, string $group): array
{
    $dean = deanAssignment($database, $deanUserId);
    if ($dean['organization_code'] !== 'CBE') {
        throw new RuntimeException('Bulk CBE approval is only available to the CBE Dean.');
    }
    if (!in_array($group, ['NON_GRADUATING', 'FOURTH_YEAR'], true)) {
        throw new RuntimeException('Select a valid CBE student group.');
    }

    $yearCondition = $group === 'FOURTH_YEAR' ? 'student.year_level = 4' : 'student.year_level < 4';
    $statement = $database->prepare(
        "SELECT clearance.id
         FROM clearances clearance
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
         INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id
            AND enrollment.student_id=student.id AND enrollment.status='ENROLLED'
         INNER JOIN stations dean_station ON dean_station.code='DEAN'
         INNER JOIN clearance_stations dean_record ON dean_record.clearance_id=clearance.id
            AND dean_record.station_id=dean_station.id
         INNER JOIN stations organization_station ON organization_station.code='ORGANIZATION'
         INNER JOIN clearance_stations organization_record ON organization_record.clearance_id=clearance.id
            AND organization_record.station_id=organization_station.id
         WHERE student.organization_id=:organization_id
           AND {$yearCondition}
           AND dean_record.status='PENDING'
           AND organization_record.status='APPROVED'
           AND NOT EXISTS (
               SELECT 1 FROM requirement_records record
               INNER JOIN requirements requirement ON requirement.id=record.requirement_id
               WHERE record.clearance_station_id=dean_record.id
                 AND requirement.status='ACTIVE'
                 AND requirement.name<>'Dean Approval'
                 AND record.status NOT IN ('VERIFIED','RESOLVED')
                 AND NOT (:group_name='FOURTH_YEAR' AND requirement.requirement_code='CBE_OJT_REQUIREMENTS')
           )
         ORDER BY student.last_name,student.first_name"
    );
    $statement->execute(['organization_id' => $dean['organization_id'], 'group_name' => $group]);

    $approved = 0;
    $skipped = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $clearanceId) {
        try {
            if (approveDeanClearance($database, $deanUserId, (int) $clearanceId)) {
                $approved++;
            } else {
                $skipped++;
            }
        } catch (RuntimeException $exception) {
            $skipped++;
        }
    }

    return ['approved' => $approved, 'skipped' => $skipped];
}
