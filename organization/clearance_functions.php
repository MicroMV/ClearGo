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

function organizationStatusSummary(PDO $database, int $organizationId): array
{
    $statement = $database->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN organization_record.status='APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN organization_record.status='RETURNED' THEN 1 ELSE 0 END) AS returned,
                SUM(CASE WHEN organization_record.status NOT IN ('APPROVED','RETURNED')
                    AND clearance.id IS NOT NULL
                    AND (organization.code='CBE' OR dean_record.status='APPROVED')
                    AND NOT EXISTS (
                        SELECT 1 FROM events event
                        LEFT JOIN event_attendance attendance ON attendance.event_id=event.id
                            AND attendance.student_id=student.id AND attendance.status='PRESENT'
                        INNER JOIN stations event_station ON event_station.id=event.station_id AND event_station.code='ORGANIZATION'
                        WHERE event.organization_id=student.organization_id AND event.academic_period_id=period.id
                          AND event.is_required=1 AND event.status='ACTIVE' AND attendance.id IS NULL
                    ) THEN 1 ELSE 0 END) AS eligible
         FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
            AND student.organization_id=:organization_id
         INNER JOIN organizations organization ON organization.id=student.organization_id
         LEFT JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         LEFT JOIN stations organization_station ON organization_station.code='ORGANIZATION'
         LEFT JOIN clearance_stations organization_record ON organization_record.clearance_id=clearance.id
            AND organization_record.station_id=organization_station.id
         LEFT JOIN stations dean_station ON dean_station.code='DEAN'
         LEFT JOIN clearance_stations dean_record ON dean_record.clearance_id=clearance.id
            AND dean_record.station_id=dean_station.id
         WHERE period.status='ACTIVE'"
    );
    $statement->execute(['organization_id' => $organizationId]);
    $row = $statement->fetch() ?: [];
    $total = (int) ($row['total'] ?? 0);
    $approved = (int) ($row['approved'] ?? 0);
    $returned = (int) ($row['returned'] ?? 0);
    $eligible = (int) ($row['eligible'] ?? 0);
    return [
        'TOTAL' => $total,
        'ELIGIBLE' => $eligible,
        'PENDING' => max(0, $total - $approved - $returned - $eligible),
        'APPROVED' => $approved,
        'RETURNED' => $returned,
    ];
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
    $missing = array_values(array_filter($events, static fn(array $event): bool => (int) $event['attended'] !== 1));

    if ($events === []) {
        return ['eligible' => true, 'events' => [], 'reason' => 'No event attendance is required; direct organization sign-off is available.'];
    }

    if ($missing !== []) {
        return ['eligible' => false, 'events' => $events, 'reason' => 'Required attendance is incomplete.'];
    }

    return ['eligible' => true, 'events' => $events, 'reason' => null];
}

function organizationStudent(PDO $database, int $organizationId, int $studentId, bool $lock = false): array|false
{
    $statement = $database->prepare(
        "SELECT students.id, students.student_number, students.first_name, students.last_name,
                students.year_level, users.email, organizations.code AS organization_code,
                clearances.id AS clearance_id, clearance_stations.id AS clearance_station_id,
                clearance_stations.status AS clearance_status, clearance_stations.returned_reason,
                dean_clearance.status AS dean_status,
                academic_periods.clearance_status AS period_status, academic_periods.id AS academic_period_id
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
         LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['student_id' => $studentId, 'organization_id' => $organizationId]);

    return $statement->fetch();
}

function approveOrganizationStudent(PDO $database, int $staffUserId, int $organizationId, int $studentId, bool $readyOnly = false): string
{
    $database->beginTransaction();
    try {
        $student = organizationStudent($database, $organizationId, $studentId, true);
        if ($student === false || empty($student['clearance_id']) || empty($student['clearance_station_id'])) {
            throw new RuntimeException('This student does not have an Organization clearance record.');
        }
        if ($readyOnly && $student['clearance_status'] !== 'PENDING') {
            throw new RuntimeException('This student is no longer ready for bulk clearance.');
        }
        if ($student['clearance_status'] === 'APPROVED') {
            throw new RuntimeException('This student is already approved.');
        }
        $isCbe = $student['organization_code'] === 'CBE';
        $isYbcSignOff = $isCbe && (int) $student['year_level'] <= 3;
        if ((!$isCbe && $student['dean_status'] !== 'APPROVED') || $student['clearance_status'] === 'LOCKED') {
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

        if ($isCbe) {
            $unlockDean = $database->prepare(
                "UPDATE clearance_stations cs
                 INNER JOIN stations ON stations.id = cs.station_id AND stations.code = 'DEAN'
                 SET cs.status = 'PENDING'
                 WHERE cs.clearance_id = :clearance_id AND cs.status = 'LOCKED'"
            );
            $unlockDean->execute(['clearance_id' => $student['clearance_id']]);
        } else {
            $unlockSasoChecks = $database->prepare(
                "UPDATE clearance_stations cs
                 INNER JOIN stations ON stations.id = cs.station_id
                 INNER JOIN stations parent ON parent.id = stations.parent_station_id AND parent.code = 'SASO'
                 SET cs.status = 'PENDING'
                 WHERE cs.clearance_id = :clearance_id AND cs.status = 'LOCKED'"
            );
            $unlockSasoChecks->execute(['clearance_id' => $student['clearance_id']]);
        }

        $studentUser = $database->prepare('SELECT user_id FROM students WHERE id = :student_id');
        $studentUser->execute(['student_id' => $studentId]);
        $studentUserId = $studentUser->fetchColumn();
        if ($studentUserId !== false) {
            $notification = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) VALUES (:user_id,:period_id,:title,:message)");
            $notification->execute([
                'user_id' => $studentUserId,
                'period_id' => $student['academic_period_id'],
                'title' => $isYbcSignOff ? 'YBC sign-off approved' : 'Organization clearance approved',
                'message' => $isCbe
                    ? 'YBC approved your CBE organization clearance. Dean clearance is now available.'
                    : 'Your organization clearance is approved. Student Council, Library, Bookstore, and Clinic checks are now available.',
            ]);
        }

        if (!$isCbe) {
            $staffNotification = $database->prepare(
                "INSERT INTO notifications (user_id,academic_period_id,title,message)
                 SELECT id,:period_id,'Student ready for office review',:message
                 FROM users
                 WHERE role IN ('STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF')
                   AND status='ACTIVE'"
            );
            $staffNotification->execute(['period_id' => $student['academic_period_id'], 'message' => $student['student_number'] . ' completed Organization clearance and is ready for the SASO sub-checks.']);
        }

        $stationId = $database->query("SELECT id FROM stations WHERE code='ORGANIZATION' LIMIT 1")->fetchColumn();
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status) VALUES (:user_id,'ORGANIZATION_CLEARANCE_APPROVED',:station_id,:student_id,'Organization approved the student after required attendance was completed.',:old_status,'APPROVED')");
        $audit->execute(['user_id' => $staffUserId, 'station_id' => $stationId, 'student_id' => $studentId, 'old_status' => $student['clearance_status']]);

        $startClearance = $database->prepare("UPDATE clearances SET status = 'IN_PROGRESS', started_at = COALESCE(started_at, NOW()) WHERE id = :clearance_id AND status = 'NOT_STARTED'");
        $startClearance->execute(['clearance_id' => $student['clearance_id']]);
        $database->commit();
        return 'approved';
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
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
        $notification = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) VALUES (:user_id,:period_id,'Organization clearance returned',:message)");
        $notification->execute(['user_id' => $studentUserId, 'period_id' => $student['academic_period_id'], 'message' => 'Organization returned your clearance: ' . $reason]);
    }
}
