<?php

declare(strict_types=1);

require_once __DIR__ . '/../organization/event_functions.php';

function studentCouncilEvents(PDO $database, int $periodId): array
{
    $statement = $database->prepare(
        "SELECT event.id,event.name,event.event_date,event.start_time,event.end_time,event.status,
                event.check_in_opens_at,event.check_in_closes_at,
                CASE WHEN event.check_in_token_hash IS NOT NULL AND event.check_in_opens_at<=NOW()
                          AND event.check_in_closes_at>=NOW() THEN 1 ELSE 0 END AS qr_is_open,
                COUNT(CASE WHEN attendance.status='PRESENT' THEN 1 END) AS present_count,
                (SELECT COUNT(*) FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id
                 WHERE enrollment.academic_period_id=event.academic_period_id AND enrollment.status='ENROLLED' AND student.status='ACTIVE') AS enrolled_count
         FROM events event
         INNER JOIN stations station ON station.id=event.station_id AND station.code='STUDENT_COUNCIL'
         LEFT JOIN event_attendance attendance ON attendance.event_id=event.id
         WHERE event.academic_period_id=:period_id
         GROUP BY event.id
         ORDER BY event.event_date DESC,event.start_time DESC,event.id DESC"
    );
    $statement->execute(['period_id' => $periodId]);
    return $statement->fetchAll();
}

function createStudentCouncilEvent(PDO $database, int $userId, string $name, string $eventDate): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 200) {
        throw new RuntimeException('Event name is required and cannot exceed 200 characters.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
    if ($date === false || $date->format('Y-m-d') !== $eventDate) {
        throw new RuntimeException('Provide a valid event date.');
    }
    $period = activeEventPeriod($database);
    $stationId = $database->query("SELECT id FROM stations WHERE code='STUDENT_COUNCIL' AND status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($stationId === false) {
        throw new RuntimeException('Student Council station is unavailable.');
    }
    $statement = $database->prepare("INSERT INTO events (station_id,organization_id,academic_period_id,name,event_date,is_required,status,created_by) VALUES (:station_id,NULL,:period_id,:name,:event_date,1,'ACTIVE',:user_id)");
    $statement->execute(['station_id' => $stationId, 'period_id' => $period['id'], 'name' => $name, 'event_date' => $eventDate, 'user_id' => $userId]);
    $notification = $database->prepare("INSERT INTO notifications (user_id,title,message) SELECT student.user_id,'New required institutional activity',:message FROM students student INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id AND enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED' WHERE student.status='ACTIVE'");
    $notification->execute(['period_id' => $period['id'], 'message' => 'Student Council added the required activity "' . $name . '" on ' . $eventDate . '. Check in through the event QR code when it opens.']);
    $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,new_status) VALUES (:user_id,'STUDENT_COUNCIL_EVENT_CREATED',:station_id,:description,'ACTIVE')");
    $audit->execute(['user_id' => $userId, 'station_id' => $stationId, 'description' => 'Created required institutional activity: ' . $name . '.']);
}

function studentCouncilEvent(PDO $database, int $periodId, int $eventId): array|false
{
    $statement = $database->prepare("SELECT event.* FROM events event INNER JOIN stations station ON station.id=event.station_id AND station.code='STUDENT_COUNCIL' WHERE event.id=:event_id AND event.academic_period_id=:period_id LIMIT 1");
    $statement->execute(['event_id' => $eventId, 'period_id' => $periodId]);
    return $statement->fetch();
}

function openStudentCouncilEventQr(PDO $database, int $userId, int $eventId, int $minutes): array
{
    $period = activeEventPeriod($database);
    $event = studentCouncilEvent($database, (int) $period['id'], $eventId);
    if ($event === false || $event['status'] !== 'ACTIVE') {
        throw new RuntimeException('The selected event is unavailable.');
    }
    $token = bin2hex(random_bytes(32));
    $statement = $database->prepare("UPDATE events SET check_in_token_hash=:token_hash,check_in_opens_at=NOW(),check_in_closes_at=DATE_ADD(NOW(),INTERVAL :minutes MINUTE) WHERE id=:event_id AND academic_period_id=:period_id");
    $statement->bindValue(':token_hash', hash('sha256', $token));
    $statement->bindValue(':minutes', max(1, min(720, $minutes)), PDO::PARAM_INT);
    $statement->bindValue(':event_id', $eventId, PDO::PARAM_INT);
    $statement->bindValue(':period_id', (int) $period['id'], PDO::PARAM_INT);
    $statement->execute();
    return ['token' => $token, 'event_id' => $eventId];
}

function closeStudentCouncilEventQr(PDO $database, int $eventId): void
{
    $period = activeEventPeriod($database);
    if (studentCouncilEvent($database, (int) $period['id'], $eventId) === false) {
        throw new RuntimeException('The selected event is unavailable.');
    }
    $statement = $database->prepare('UPDATE events SET check_in_token_hash=NULL,check_in_closes_at=NOW() WHERE id=:event_id AND academic_period_id=:period_id');
    $statement->execute(['event_id' => $eventId, 'period_id' => $period['id']]);
}

function deleteStudentCouncilEvent(PDO $database, int $userId, int $eventId): void
{
    $period = activeEventPeriod($database);
    $database->beginTransaction();
    try {
        $event = studentCouncilEvent($database, (int) $period['id'], $eventId);
        if ($event === false) {
            throw new RuntimeException('The selected event is unavailable.');
        }
        $database->prepare('DELETE FROM event_attendance WHERE event_id=:event_id')->execute(['event_id' => $eventId]);
        $removeEvent = $database->prepare('DELETE FROM events WHERE id=:event_id AND academic_period_id=:period_id');
        $removeEvent->execute(['event_id' => $eventId, 'period_id' => $period['id']]);
        if ($removeEvent->rowCount() !== 1) {
            throw new RuntimeException('The event could not be deleted.');
        }
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status) VALUES (:user_id,'STUDENT_COUNCIL_EVENT_DELETED',:station_id,:description,'ACTIVE','DELETED')");
        $audit->execute(['user_id' => $userId, 'station_id' => $event['station_id'], 'description' => 'Deleted institutional activity: ' . $event['name'] . '.']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
