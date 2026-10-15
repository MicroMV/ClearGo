<?php

declare(strict_types=1);

const READINESS_ROLE_STATIONS = [
    'DEAN' => 'DEAN',
    'ORG_STAFF' => 'ORGANIZATION',
    'SASO_STAFF' => 'SASO',
    'STUDENT_COUNCIL_STAFF' => 'STUDENT_COUNCIL',
    'LIBRARY_STAFF' => 'LIBRARY',
    'BOOKSTORE_STAFF' => 'BOOKSTORE',
    'CLINIC_STAFF' => 'CLINIC',
    'FACILITIES_STAFF' => 'FACILITIES',
    'COMLAB_STAFF' => 'COMLAB',
    'ACCOUNTING_STAFF' => 'ACCOUNTING',
];

function officeReadiness(PDO $database, string $stationCode): array
{
    $statement = $database->prepare(
        "SELECT period.id AS academic_period_id,period.school_year,period.semester,period.clearance_status,
                readiness.id AS readiness_id,readiness.status AS readiness_status,readiness.ready_at,
                readiness.notes,user.email AS ready_by_email,station.id AS station_id,station.name AS station_name
         FROM academic_periods period
         INNER JOIN stations station ON station.code=:station_code AND station.status='ACTIVE'
         INNER JOIN office_period_readiness readiness ON readiness.academic_period_id=period.id AND readiness.station_id=station.id
         LEFT JOIN users user ON user.id=readiness.ready_by
         WHERE period.status='ACTIVE' LIMIT 1"
    );
    $statement->execute(['station_code' => $stationCode]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Semester office readiness is not configured.');
    }
    return $row;
}

function markOfficeReady(PDO $database, int $userId, string $stationCode, string $notes): void
{
    if (mb_strlen($notes) > 500) {
        throw new RuntimeException('Readiness notes cannot exceed 500 characters.');
    }
    $database->beginTransaction();
    try {
        $context = officeReadiness($database, $stationCode);
        if ($context['clearance_status'] !== 'PREPARING') {
            throw new RuntimeException('Office readiness can only be confirmed before clearance opens.');
        }
        if ($context['readiness_status'] === 'READY') {
            throw new RuntimeException('This office is already marked ready.');
        }
        $update = $database->prepare("UPDATE office_period_readiness SET status='READY',ready_by=:user_id,ready_at=NOW(),notes=:notes WHERE id=:id AND status='NOT_READY'");
        $update->execute(['user_id' => $userId, 'notes' => $notes ?: null, 'id' => $context['readiness_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Office readiness could not be updated.');
        }
        $description = $context['station_name'] . ' confirmed readiness for ' . $context['semester'] . ' Semester A.Y. ' . $context['school_year'] . '.';
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status) VALUES (:user_id,'OFFICE_MARKED_READY',:station_id,:description,'NOT_READY','READY')");
        $audit->execute(['user_id' => $userId, 'station_id' => $context['station_id'], 'description' => $description]);
        $notification = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) SELECT id,:period_id,'Office ready for semester',:message FROM users WHERE role='REGISTRAR' AND status='ACTIVE'");
        $notification->execute(['period_id' => $context['academic_period_id'], 'message' => $description]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function markOfficeNotReady(PDO $database, int $userId, string $stationCode): void
{
    $database->beginTransaction();
    try {
        $context = officeReadiness($database, $stationCode);
        if ($context['clearance_status'] !== 'PREPARING') {
            throw new RuntimeException('Ready status cannot be changed after clearance opens.');
        }
        if ($context['readiness_status'] !== 'READY') {
            throw new RuntimeException('This office is already marked not ready.');
        }
        $update = $database->prepare("UPDATE office_period_readiness SET status='NOT_READY',ready_by=NULL,ready_at=NULL,notes=NULL WHERE id=:id AND status='READY'");
        $update->execute(['id' => $context['readiness_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Office readiness could not be updated.');
        }
        $description = $context['station_name'] . ' withdrew readiness for ' . $context['semester'] . ' Semester A.Y. ' . $context['school_year'] . '.';
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,description,old_status,new_status) VALUES (:user_id,'OFFICE_MARKED_NOT_READY',:station_id,:description,'READY','NOT_READY')");
        $audit->execute(['user_id' => $userId, 'station_id' => $context['station_id'], 'description' => $description]);
        $notification = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) SELECT id,:period_id,'Office readiness withdrawn',:message FROM users WHERE role='REGISTRAR' AND status='ACTIVE'");
        $notification->execute(['period_id' => $context['academic_period_id'], 'message' => $description]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
