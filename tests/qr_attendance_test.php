<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../organization/event_functions.php';

function qrCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$database = getDatabaseConnection();
$eventId = null;
try {
    $period = activeEventPeriod($database);
    $candidate = $database->query(
        "SELECT organization.id AS organization_id,assignment.user_id AS staff_user_id,
                student.id AS student_id,student.user_id AS student_user_id
         FROM organizations organization
         INNER JOIN staff_assignments assignment ON assignment.organization_id=organization.id AND assignment.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='ORGANIZATION'
         INNER JOIN students student ON student.organization_id=organization.id AND student.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
            AND enrollment.academic_period_id=" . (int) $period['id'] . " AND enrollment.status='ENROLLED'
         LIMIT 1"
    )->fetch();
    qrCheck($candidate !== false, 'No enrolled organization student/staff pair is available for the QR test.');

    $eventId = createOrganizationEvent(
        $database,
        (int) $candidate['staff_user_id'],
        (int) $candidate['organization_id'],
        'Automated QR Attendance Test ' . bin2hex(random_bytes(3)),
        '',
        date('Y-m-d'),
        null,
        null
    );
    $qr = openEventQr($database, (int) $candidate['staff_user_id'], (int) $candidate['organization_id'], $eventId, 120);
    qrCheck(strlen($qr['token']) === 64, 'A secure QR token was not generated.');
    $durationQuery = $database->prepare('SELECT TIMESTAMPDIFF(MINUTE,check_in_opens_at,check_in_closes_at) FROM events WHERE id=:id');
    $durationQuery->execute(['id' => $eventId]);
    qrCheck((int) $durationQuery->fetchColumn() === 120, 'QR attendance could not remain open beyond one hour.');

    $context = qrAttendanceContext($database, $qr['token'], (int) $candidate['student_user_id']);
    qrCheck($context !== false && (int) $context['event_id'] === $eventId, 'The enrolled student could not validate the QR.');
    $otherOrganizationUser = $database->prepare(
        "SELECT student.user_id FROM students student
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
            AND enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'
         WHERE student.organization_id<>:organization_id AND student.status='ACTIVE' LIMIT 1"
    );
    $otherOrganizationUser->execute(['period_id' => $period['id'], 'organization_id' => $candidate['organization_id']]);
    $otherUserId = $otherOrganizationUser->fetchColumn();
    if ($otherUserId !== false) {
        qrCheck(qrAttendanceContext($database, $qr['token'], (int) $otherUserId) === false, 'A student from another organization could use the QR.');
    }
    $first = recordQrAttendance($database, $qr['token'], (int) $candidate['student_user_id']);
    qrCheck($first['already_recorded'] === false, 'The first scan was incorrectly treated as a duplicate.');
    $second = recordQrAttendance($database, $qr['token'], (int) $candidate['student_user_id']);
    qrCheck($second['already_recorded'] === true, 'A duplicate scan was not handled idempotently.');

    $attendance = $database->prepare("SELECT COUNT(*) FROM event_attendance WHERE event_id=:event_id AND student_id=:student_id AND status='PRESENT' AND check_in_method='QR'");
    $attendance->execute(['event_id' => $eventId, 'student_id' => $candidate['student_id']]);
    qrCheck((int) $attendance->fetchColumn() === 1, 'QR attendance was not stored exactly once.');

    $database->prepare('UPDATE events SET check_in_closes_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=:id')->execute(['id' => $eventId]);
    qrCheck(qrAttendanceContext($database, $qr['token'], (int) $candidate['student_user_id']) === false, 'An expired QR token was accepted.');
    echo "QR attendance test passed.\n";
} finally {
    if ($eventId !== null) {
        $database->prepare("DELETE FROM audit_logs WHERE action IN ('ORGANIZATION_EVENT_CREATED','EVENT_QR_OPENED','EVENT_QR_ATTENDANCE_RECORDED','EVENT_QR_CLOSED') AND description LIKE '%Automated QR Attendance Test%'")->execute();
        $database->prepare('DELETE FROM events WHERE id=:id')->execute(['id' => $eventId]);
    }
}
