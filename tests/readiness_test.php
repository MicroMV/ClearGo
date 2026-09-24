<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$database = getDatabaseConnection();
$failures = [];
$auditStart = (int) $database->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
$notificationStart = (int) $database->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();
$candidate = false;

try {
    $candidate = $database->query(
        "SELECT readiness.id AS readiness_id, station.code AS station_code, user.id AS user_id
         FROM academic_periods period
         INNER JOIN office_period_readiness readiness ON readiness.academic_period_id = period.id
         INNER JOIN stations station ON station.id = readiness.station_id
         INNER JOIN users user
             ON user.status = 'ACTIVE'
            AND user.role = CASE station.code
                WHEN 'DEAN' THEN 'DEAN'
                WHEN 'ORGANIZATION' THEN 'ORG_STAFF'
                WHEN 'SASO' THEN 'SASO_STAFF'
                WHEN 'STUDENT_COUNCIL' THEN 'STUDENT_COUNCIL_STAFF'
                WHEN 'LIBRARY' THEN 'LIBRARY_STAFF'
                WHEN 'BOOKSTORE' THEN 'BOOKSTORE_STAFF'
                WHEN 'CLINIC' THEN 'CLINIC_STAFF'
                WHEN 'FACILITIES' THEN 'FACILITIES_STAFF'
                WHEN 'ACCOUNTING' THEN 'ACCOUNTING_STAFF'
            END
         WHERE period.status = 'ACTIVE'
           AND period.clearance_status = 'PREPARING'
           AND readiness.status = 'NOT_READY'
           AND station.code IN ('DEAN','ORGANIZATION','SASO','STUDENT_COUNCIL','LIBRARY','BOOKSTORE','CLINIC','FACILITIES','ACCOUNTING')
         ORDER BY station.sort_order
         LIMIT 1"
    )->fetch();

    if ($candidate === false) {
        throw new RuntimeException('No not-ready office is available in the preparing semester.');
    }

    markOfficeReady($database, (int) $candidate['user_id'], (string) $candidate['station_code'], 'Automated readiness test');
    $ready = officeReadiness($database, (string) $candidate['station_code']);
    if ($ready['readiness_status'] !== 'READY' || $ready['ready_at'] === null) {
        $failures[] = 'The office was not marked ready.';
    }

    markOfficeNotReady($database, (int) $candidate['user_id'], (string) $candidate['station_code']);
    $notReady = officeReadiness($database, (string) $candidate['station_code']);
    if ($notReady['readiness_status'] !== 'NOT_READY'
        || $notReady['ready_at'] !== null
        || $notReady['ready_by_email'] !== null
        || $notReady['notes'] !== null) {
        $failures[] = 'Undo Ready did not completely restore the not-ready state.';
    }
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
} finally {
    if (is_array($candidate)) {
        try {
            $current = officeReadiness($database, (string) $candidate['station_code']);
            if ($current['readiness_status'] === 'READY') {
                markOfficeNotReady($database, (int) $candidate['user_id'], (string) $candidate['station_code']);
            }
        } catch (Throwable $cleanupException) {
            $failures[] = 'Unable to restore readiness after the test: ' . $cleanupException->getMessage();
        }

        $stationId = (int) $database->query(
            "SELECT id FROM stations WHERE code = " . $database->quote((string) $candidate['station_code'])
        )->fetchColumn();
        $deleteAudit = $database->prepare(
            "DELETE FROM audit_logs
             WHERE id > :starting_id AND user_id = :user_id AND station_id = :station_id
               AND action IN ('OFFICE_MARKED_READY','OFFICE_MARKED_NOT_READY')"
        );
        $deleteAudit->execute([
            'starting_id' => $auditStart,
            'user_id' => $candidate['user_id'],
            'station_id' => $stationId,
        ]);
        $deleteNotifications = $database->prepare(
            "DELETE FROM notifications
             WHERE id > :starting_id
               AND title IN ('Office ready for semester','Office readiness withdrawn')"
        );
        $deleteNotifications->execute(['starting_id' => $notificationStart]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Readiness checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Office Ready and Undo Ready checks passed.\n";
