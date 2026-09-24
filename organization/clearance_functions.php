<?php
declare(strict_types=1);

function assignedOrganization(PDO $database, int $userId): array
{
    $statement = $database->prepare(
        "SELECT organizations.id, organizations.name, organizations.code
         FROM staff_assignments
         INNER JOIN organizations ON organizations.id = staff_assignments.organization_id
         INNER JOIN stations ON stations.id = staff_assignments.station_id
         WHERE staff_assignments.user_id = :user_id
           AND staff_assignments.status = 'ACTIVE'
           AND stations.code = 'ORGANIZATION'
         LIMIT 2"
    );
    $statement->execute(['user_id' => $userId]);
    $organizations = $statement->fetchAll();

    if (count($organizations) !== 1) {
        throw new RuntimeException('Organization staff assignment is unavailable.');
    }

    return $organizations[0];
}

function requiredOrganizationEvents(PDO $database, int $organizationId, int $studentId): array
{
    $statement = $database->prepare(
        "SELECT events.id, events.name,
                CASE WHEN event_attendance.status = 'PRESENT' THEN 1 ELSE 0 END AS attended
         FROM events
         INNER JOIN academic_periods ON academic_periods.id=events.academic_period_id
             AND academic_periods.status='ACTIVE'
         LEFT JOIN event_attendance ON event_attendance.event_id = events.id
             AND event_attendance.student_id = :student_id
         INNER JOIN stations ON stations.id = events.station_id
         WHERE events.organization_id = :organization_id
           AND events.is_required = 1
           AND events.status = 'ACTIVE'
           AND academic_periods.clearance_status IN ('PREPARING','OPEN')
           AND stations.code = 'ORGANIZATION'
         ORDER BY events.event_date, events.id"
    );
    $statement->execute(['student_id' => $studentId, 'organization_id' => $organizationId]);

    return $statement->fetchAll();
}

function organizationEligibility(PDO $database, int $organizationId, int $studentId): array
{
    $events = requiredOrganizationEvents($database, $organizationId, $studentId);
    $missing = array_values(array_filter($events, static fn (array $event): bool => (int) $event['attended'] !== 1));

    if ($events === []) {
        return ['eligible' => true, 'events' => [], 'reason' => 'No event attendance is required; direct organization sign-off is available.'];
    }

    if ($missing !== []) {
        return ['eligible' => false, 'events' => $events, 'reason' => 'Required attendance is incomplete.'];
    }

    return ['eligible' => true, 'events' => $events, 'reason' => null];
}

function organizationStudent(PDO $database, int $organizationId, int $studentId): array|false
{
    $statement = $database->prepare(
        "SELECT students.id, students.student_number, students.first_name, students.last_name,
                students.year_level, users.email, organizations.code AS organization_code,
                clearances.id AS clearance_id, clearance_stations.id AS clearance_station_id,
                clearance_stations.status AS clearance_status, clearance_stations.returned_reason,
                dean_clearance.status AS dean_status,
                academic_periods.clearance_status AS period_status
         FROM students
         INNER JOIN users ON users.id = students.user_id
         INNER JOIN organizations ON organizations.id=students.organization_id
         INNER JOIN academic_periods ON academic_periods.status = 'ACTIVE'
            AND academic_periods.clearance_status IN ('PREPARING','OPEN')
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=academic_periods.id AND enrollment.student_id=students.id AND enrollment.status='ENROLLED'
         LEFT JOIN clearances ON clearances.student_id = students.id AND clearances.academic_period_id = academic_periods.id
         LEFT JOIN stations ON stations.code = 'ORGANIZATION'
         LEFT JOIN clearance_stations ON clearance_stations.clearance_id = clearances.id AND clearance_stations.station_id = stations.id
         LEFT JOIN stations AS dean_station ON dean_station.code = 'DEAN'
         LEFT JOIN clearance_stations AS dean_clearance
            ON dean_clearance.clearance_id = clearances.id AND dean_clearance.station_id = dean_station.id
         WHERE students.id = :student_id AND students.organization_id = :organization_id AND students.status = 'ACTIVE'
         LIMIT 1"
    );
    $statement->execute(['student_id' => $studentId, 'organization_id' => $organizationId]);

    return $statement->fetch();
}

function approveOrganizationStudent(PDO $database, int $staffUserId, int $organizationId, int $studentId): string
{
    $database->beginTransaction();
    try {
        $student = organizationStudent($database, $organizationId, $studentId);
        if ($student === false || empty($student['clearance_id']) || empty($student['clearance_station_id'])) {
            throw new RuntimeException('This student does not have an Organization clearance record.');
        }
        if ($student['clearance_status'] === 'APPROVED') {
            throw new RuntimeException('This student is already approved.');
        }
        $isYbcSignOff = $student['organization_code'] === 'CBE' && (int) $student['year_level'] <= 3;
        if (!$isYbcSignOff
            && ($student['dean_status'] !== 'APPROVED' || $student['clearance_status'] === 'LOCKED')) {
            throw new RuntimeException('Dean clearance must be approved before Organization clearance.');
        }

        $eligibility = organizationEligibility($database, $organizationId, $studentId);
        if (!$eligibility['eligible']) {
            throw new RuntimeException('This student cannot be approved because some organization requirements are incomplete.');
        }

        $approve = $database->prepare(
            "UPDATE clearance_stations
             SET status = 'APPROVED', approved_by = :staff_user_id, approved_at = NOW(), returned_by = NULL, returned_at = NULL, returned_reason = NULL
             WHERE id = :clearance_station_id AND status <> 'APPROVED'"
        );
        $approve->execute(['staff_user_id' => $staffUserId, 'clearance_station_id' => $student['clearance_station_id']]);

        if ($isYbcSignOff) {
            $verifyYbcRequirement = $database->prepare(
                "UPDATE requirement_records record
                 INNER JOIN requirements requirement ON requirement.id=record.requirement_id
                    AND requirement.requirement_code='CBE_YBC_APPROVAL'
                 INNER JOIN clearance_stations station_record ON station_record.id=record.clearance_station_id
                 INNER JOIN stations station ON station.id=station_record.station_id AND station.code='DEAN'
                 SET record.status='VERIFIED',record.verified_by=:user_id,
                     record.verified_at=NOW(),record.remarks='Signed by YBC (CBE organization).'
                 WHERE station_record.clearance_id=:clearance_id"
            );
            $verifyYbcRequirement->execute(['user_id' => $staffUserId, 'clearance_id' => $student['clearance_id']]);
            if ($verifyYbcRequirement->rowCount() !== 1) {
                throw new RuntimeException('The YBC sign-off could not be recorded for the CBE Dean.');
            }
        }

        $unlockSasoChecks = $database->prepare(
            "UPDATE clearance_stations cs
             INNER JOIN stations ON stations.id = cs.station_id
             INNER JOIN stations parent ON parent.id = stations.parent_station_id AND parent.code = 'SASO'
             SET cs.status = 'PENDING'
             WHERE cs.clearance_id = :clearance_id AND cs.status = 'LOCKED'"
        );
        if (!$isYbcSignOff || $student['dean_status'] === 'APPROVED') {
            $unlockSasoChecks->execute(['clearance_id' => $student['clearance_id']]);
        }

        $studentUser = $database->prepare('SELECT user_id FROM students WHERE id = :student_id');
        $studentUser->execute(['student_id' => $studentId]);
        $studentUserId = $studentUser->fetchColumn();
        if ($studentUserId !== false) {
            $notification = $database->prepare("INSERT INTO notifications (user_id,title,message) VALUES (:user_id,:title,:message)");
            $notification->execute([
                'user_id' => $studentUserId,
                'title' => $isYbcSignOff ? 'YBC sign-off approved' : 'Organization clearance approved',
                'message' => $isYbcSignOff
                    ? 'YBC approved your CBE organization sign-off. Final CBE Dean approval is still required.'
                    : 'Your organization clearance is approved. Student Council, Library, Bookstore, and Clinic checks are now available.',
            ]);
        }

        $staffNotification = $database->prepare(
            "INSERT INTO notifications (user_id,title,message)
             SELECT id,'Student ready for office review',:message
             FROM users
             WHERE role IN ('STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF')
               AND status='ACTIVE'"
        );
        if (!$isYbcSignOff || $student['dean_status'] === 'APPROVED') {
            $staffNotification->execute(['message' => $student['student_number'] . ' completed Organization clearance and is ready for the SASO sub-checks.']);
        }

        $stationId = $database->query("SELECT id FROM stations WHERE code='ORGANIZATION' LIMIT 1")->fetchColumn();
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status) VALUES (:user_id,'ORGANIZATION_CLEARANCE_APPROVED',:station_id,:student_id,'Organization approved the student after required attendance was completed.',:old_status,'APPROVED')");
        $audit->execute(['user_id' => $staffUserId, 'station_id' => $stationId, 'student_id' => $studentId, 'old_status' => $student['clearance_status']]);

        $startClearance = $database->prepare("UPDATE clearances SET status = 'IN_PROGRESS', started_at = COALESCE(started_at, NOW()) WHERE id = :clearance_id AND status = 'NOT_STARTED'");
        $startClearance->execute(['clearance_id' => $student['clearance_id']]);
        $database->commit();
        return 'approved';
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function returnOrganizationStudent(PDO $database, int $staffUserId, int $organizationId, int $studentId, string $reason): void
{
    $student = organizationStudent($database, $organizationId, $studentId);
    if ($student === false || empty($student['clearance_station_id']) || $student['clearance_status'] === 'APPROVED') {
        throw new RuntimeException('This student cannot be returned.');
    }

    $statement = $database->prepare(
        "UPDATE clearance_stations
         SET status = 'RETURNED', returned_by = :staff_user_id, returned_at = NOW(), returned_reason = :reason
         WHERE id = :clearance_station_id"
    );
    $statement->execute(['staff_user_id' => $staffUserId, 'reason' => $reason, 'clearance_station_id' => $student['clearance_station_id']]);

    if ($student['organization_code'] === 'CBE' && (int) $student['year_level'] <= 3) {
        $returnYbcRequirement = $database->prepare(
            "UPDATE requirement_records record
             INNER JOIN requirements requirement ON requirement.id=record.requirement_id
                AND requirement.requirement_code='CBE_YBC_APPROVAL'
             INNER JOIN clearance_stations station_record ON station_record.id=record.clearance_station_id
             SET record.status='RETURNED',record.verified_by=:user_id,record.verified_at=NULL,record.remarks=:reason
             WHERE station_record.clearance_id=:clearance_id"
        );
        $returnYbcRequirement->execute([
            'user_id' => $staffUserId,
            'reason' => $reason,
            'clearance_id' => $student['clearance_id'],
        ]);
    }

    $studentUser = $database->prepare('SELECT user_id FROM students WHERE id = :student_id');
    $studentUser->execute(['student_id' => $studentId]);
    $studentUserId = $studentUser->fetchColumn();
    if ($studentUserId !== false) {
        $notification = $database->prepare("INSERT INTO notifications (user_id,title,message) VALUES (:user_id,'Organization clearance returned',:message)");
        $notification->execute(['user_id' => $studentUserId, 'message' => 'Organization returned your clearance: ' . $reason]);
    }
}
