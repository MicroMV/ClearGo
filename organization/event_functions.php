<?php
declare(strict_types=1);

function activeEventPeriod(PDO $database): array
{
    $period = $database->query(
        "SELECT id,school_year,semester,clearance_status
         FROM academic_periods WHERE status='ACTIVE' LIMIT 1"
    )->fetch();
    if ($period === false || !in_array($period['clearance_status'], ['PREPARING', 'OPEN'], true)) {
        throw new RuntimeException('There is no active semester available for event attendance.');
    }
    return $period;
}

function organizationEvents(PDO $database, int $organizationId, int $periodId): array
{
    $statement = $database->prepare(
        "SELECT event.id,event.name,event.description,event.event_date,event.start_time,event.end_time,
                event.is_required,event.status,event.check_in_opens_at,event.check_in_closes_at,
                CASE WHEN event.check_in_token_hash IS NOT NULL AND event.check_in_opens_at<=NOW()
                     AND event.check_in_closes_at>=NOW() THEN 1 ELSE 0 END AS qr_is_open,
                COUNT(CASE WHEN attendance.status='PRESENT' THEN 1 END) AS present_count,
                (SELECT COUNT(*) FROM semester_enrollments enrollment
                 INNER JOIN students student ON student.id=enrollment.student_id
                 WHERE enrollment.academic_period_id=event.academic_period_id
                   AND enrollment.status='ENROLLED' AND student.status='ACTIVE'
                   AND student.organization_id=event.organization_id) AS enrolled_count
         FROM events event
         INNER JOIN stations station ON station.id=event.station_id AND station.code='ORGANIZATION'
         LEFT JOIN event_attendance attendance ON attendance.event_id=event.id
         WHERE event.organization_id=:organization_id AND event.academic_period_id=:period_id
         GROUP BY event.id
         ORDER BY event.event_date DESC,event.start_time DESC,event.id DESC"
    );
    $statement->execute(['organization_id' => $organizationId, 'period_id' => $periodId]);
    return $statement->fetchAll();
}

function organizationEvent(PDO $database, int $organizationId, int $periodId, int $eventId): array|false
{
    $statement = $database->prepare(
        "SELECT event.*,organization.name AS organization_name,organization.code AS organization_code,
                period.school_year,period.semester,period.clearance_status
         FROM events event
         INNER JOIN organizations organization ON organization.id=event.organization_id
         INNER JOIN academic_periods period ON period.id=event.academic_period_id
         INNER JOIN stations station ON station.id=event.station_id AND station.code='ORGANIZATION'
         WHERE event.id=:event_id AND event.organization_id=:organization_id
           AND event.academic_period_id=:period_id LIMIT 1"
    );
    $statement->execute([
        'event_id' => $eventId,
        'organization_id' => $organizationId,
        'period_id' => $periodId,
    ]);
    return $statement->fetch();
}

function createOrganizationEvent(
    PDO $database,
    int $userId,
    int $organizationId,
    string $name,
    string $description,
    string $eventDate,
    ?string $startTime,
    ?string $endTime
): int {
    $name = trim($name);
    $description = trim($description);
    if ($name === '' || mb_strlen($name) > 200) {
        throw new RuntimeException('Event name is required and cannot exceed 200 characters.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
    if ($date === false || $date->format('Y-m-d') !== $eventDate) {
        throw new RuntimeException('Provide a valid event date.');
    }
    foreach ([$startTime, $endTime] as $time) {
        if ($time !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new RuntimeException('Provide a valid event time.');
        }
    }
    if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
        throw new RuntimeException('The event end time must be later than its start time.');
    }

    $period = activeEventPeriod($database);
    $stationId = $database->query("SELECT id FROM stations WHERE code='ORGANIZATION' AND status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($stationId === false) { throw new RuntimeException('Organization station is unavailable.'); }

    $database->beginTransaction();
    try {
        $insert = $database->prepare(
            "INSERT INTO events
                (station_id,organization_id,academic_period_id,name,description,event_date,start_time,end_time,is_required,status,created_by)
             VALUES (:station_id,:organization_id,:period_id,:name,:description,:event_date,:start_time,:end_time,1,'ACTIVE',:created_by)"
        );
        $insert->execute([
            'station_id' => $stationId,
            'organization_id' => $organizationId,
            'period_id' => $period['id'],
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'created_by' => $userId,
        ]);
        $eventId = (int) $database->lastInsertId();
        $audit = $database->prepare(
            "INSERT INTO audit_logs (user_id,action,station_id,description,new_status)
             VALUES (:user_id,'ORGANIZATION_EVENT_CREATED',:station_id,:description,'ACTIVE')"
        );
        $audit->execute([
            'user_id' => $userId,
            'station_id' => $stationId,
            'description' => 'Created required organization event: ' . $name . '.',
        ]);
        $database->commit();
        return $eventId;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function openEventQr(PDO $database, int $userId, int $organizationId, int $eventId, int $minutes): array
{
    $period = activeEventPeriod($database);
    $event = organizationEvent($database, $organizationId, (int) $period['id'], $eventId);
    if ($event === false || $event['status'] !== 'ACTIVE') {
        throw new RuntimeException('The selected event is unavailable.');
    }
    $minutes = max(1, min(720, $minutes));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $update = $database->prepare(
        "UPDATE events SET check_in_token_hash=:token_hash,check_in_opens_at=NOW(),
                check_in_closes_at=DATE_ADD(NOW(),INTERVAL :minutes MINUTE)
         WHERE id=:event_id AND organization_id=:organization_id AND academic_period_id=:period_id"
    );
    $update->bindValue(':token_hash', $tokenHash);
    $update->bindValue(':minutes', $minutes, PDO::PARAM_INT);
    $update->bindValue(':event_id', $eventId, PDO::PARAM_INT);
    $update->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
    $update->bindValue(':period_id', (int) $period['id'], PDO::PARAM_INT);
    $update->execute();
    if ($update->rowCount() !== 1) { throw new RuntimeException('Unable to open QR check-in.'); }

    $stationId = (int) $event['station_id'];
    $audit = $database->prepare(
        "INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status)
         VALUES (:user_id,'EVENT_QR_OPENED',:station_id,:description,'CLOSED','OPEN')"
    );
    $audit->execute([
        'user_id' => $userId,
        'station_id' => $stationId,
        'description' => 'Opened QR attendance for ' . $event['name'] . " for {$minutes} minute(s).",
    ]);
    return ['token' => $token, 'event_id' => $eventId];
}

function closeEventQr(PDO $database, int $userId, int $organizationId, int $eventId): void
{
    $period = activeEventPeriod($database);
    $event = organizationEvent($database, $organizationId, (int) $period['id'], $eventId);
    if ($event === false) { throw new RuntimeException('The selected event is unavailable.'); }
    $update = $database->prepare(
        "UPDATE events SET check_in_token_hash=NULL,check_in_closes_at=NOW()
         WHERE id=:event_id AND organization_id=:organization_id AND academic_period_id=:period_id"
    );
    $update->execute(['event_id' => $eventId, 'organization_id' => $organizationId, 'period_id' => $period['id']]);
    $audit = $database->prepare(
        "INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status)
         VALUES (:user_id,'EVENT_QR_CLOSED',:station_id,:description,'OPEN','CLOSED')"
    );
    $audit->execute([
        'user_id' => $userId,
        'station_id' => $event['station_id'],
        'description' => 'Closed QR attendance for ' . $event['name'] . '.',
    ]);
}

function deleteOrganizationEvent(PDO $database, int $userId, int $organizationId, int $eventId): void
{
    $period = activeEventPeriod($database);
    $database->beginTransaction();
    try {
        $event = organizationEvent($database, $organizationId, (int) $period['id'], $eventId);
        if ($event === false) { throw new RuntimeException('The selected event is unavailable.'); }
        $removeAttendance = $database->prepare('DELETE FROM event_attendance WHERE event_id=:event_id');
        $removeAttendance->execute(['event_id' => $eventId]);
        $removeEvent = $database->prepare('DELETE FROM events WHERE id=:event_id AND organization_id=:organization_id AND academic_period_id=:period_id');
        $removeEvent->execute(['event_id' => $eventId, 'organization_id' => $organizationId, 'period_id' => $period['id']]);
        if ($removeEvent->rowCount() !== 1) { throw new RuntimeException('The event could not be deleted.'); }
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status) VALUES (:user_id,'ORGANIZATION_EVENT_DELETED',:station_id,:description,'ACTIVE','DELETED')");
        $audit->execute(['user_id' => $userId, 'station_id' => $event['station_id'], 'description' => 'Deleted organization event: ' . $event['name'] . '.']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function qrAttendanceContext(PDO $database, string $token, int $userId, bool $lock = false): array|false
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return false; }
    $statement = $database->prepare(
        "SELECT event.id AS event_id,event.name,event.description,event.event_date,event.start_time,event.end_time,
                event.check_in_closes_at,event.station_id,event.organization_id,organization.name AS organization_name,
                organization.code AS organization_code,student.id AS student_id,student.student_number,
                student.first_name,student.last_name,attendance.status AS attendance_status
         FROM events event
         INNER JOIN academic_periods period ON period.id=event.academic_period_id AND period.status='ACTIVE'
            AND period.clearance_status IN ('PREPARING','OPEN')
         LEFT JOIN organizations organization ON organization.id=event.organization_id
         INNER JOIN students student ON student.user_id=:user_id AND student.status='ACTIVE'
            AND (event.organization_id IS NULL OR student.organization_id=event.organization_id)
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id
            AND enrollment.student_id=student.id AND enrollment.status='ENROLLED'
         LEFT JOIN event_attendance attendance ON attendance.event_id=event.id AND attendance.student_id=student.id
         WHERE event.check_in_token_hash=:token_hash AND event.status='ACTIVE'
           AND event.check_in_opens_at<=NOW() AND event.check_in_closes_at>=NOW()
         LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
    return $statement->fetch();
}

function recordQrAttendance(PDO $database, string $token, int $userId): array
{
    $database->beginTransaction();
    try {
        $context = qrAttendanceContext($database, $token, $userId, true);
        if ($context === false) {
            throw new RuntimeException('This QR code is expired, closed, or not valid for this event.');
        }
        if ($context['attendance_status'] === 'PRESENT') {
            $database->commit();
            return ['context' => $context, 'already_recorded' => true];
        }
        $attendance = $database->prepare(
            "INSERT INTO event_attendance (event_id,student_id,status,check_in_method,recorded_by,recorded_at)
             VALUES (:event_id,:student_id,'PRESENT','QR',:user_id,NOW())
             ON DUPLICATE KEY UPDATE status='PRESENT',check_in_method='QR',recorded_by=VALUES(recorded_by),recorded_at=NOW()"
        );
        $attendance->execute([
            'event_id' => $context['event_id'],
            'student_id' => $context['student_id'],
            'user_id' => $userId,
        ]);
        $audit = $database->prepare(
            "INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status)
             VALUES (:user_id,'EVENT_QR_ATTENDANCE_RECORDED',:station_id,:student_id,:description,:old_status,'PRESENT')"
        );
        $audit->execute([
            'user_id' => $userId,
            'station_id' => $context['station_id'],
            'student_id' => $context['student_id'],
            'description' => 'Student confirmed QR attendance for ' . $context['name'] . '.',
            'old_status' => $context['attendance_status'],
        ]);
        $database->commit();
        return ['context' => $context, 'already_recorded' => false];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}
