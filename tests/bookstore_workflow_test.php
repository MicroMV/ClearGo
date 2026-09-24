<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';

$database = getDatabaseConnection();
$candidate = $database->query(
    "SELECT clearance.id AS clearance_id
     FROM academic_periods period
     INNER JOIN clearances clearance ON clearance.academic_period_id=period.id
     INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.student_id=clearance.student_id AND enrollment.status='ENROLLED'
     INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
     INNER JOIN stations station ON station.code='BOOKSTORE'
     INNER JOIN clearance_stations cs ON cs.clearance_id=clearance.id AND cs.station_id=station.id
     WHERE period.status='ACTIVE' LIMIT 1"
)->fetchColumn();
if ($candidate === false) { throw new RuntimeException('No current student is available for the Bookstore test.'); }
$staffId = $database->query("SELECT id FROM users WHERE role='BOOKSTORE_STAFF' AND status='ACTIVE' ORDER BY id LIMIT 1")->fetchColumn();
if ($staffId === false) { throw new RuntimeException('No active Bookstore staff account is available.'); }
$context = officeClearanceContext($database, 'BOOKSTORE', (int) $candidate);
$obligationId = null;
try {
    $insert = $database->prepare("INSERT INTO bookstore_obligations (student_id,academic_period_id,description,amount,status,recorded_by) VALUES (:student_id,:period_id,'Automated Bookstore test',10,'UNPAID',:user_id)");
    $insert->execute(['student_id' => $context['student_id'], 'period_id' => $context['academic_period_id'], 'user_id' => $staffId]);
    $obligationId = (int) $database->lastInsertId();
    if (officeEligibility($database, 'BOOKSTORE', $context)['eligible']) {
        throw new RuntimeException('An unpaid Bookstore obligation did not block clearance.');
    }
    $database->prepare("UPDATE bookstore_obligations SET status='CLEARED',payment_reference='TEST-RECEIPT',cleared_by=:user_id,cleared_at=NOW() WHERE id=:id")
        ->execute(['user_id' => $staffId, 'id' => $obligationId]);
    if (!officeEligibility($database, 'BOOKSTORE', $context)['eligible']) {
        throw new RuntimeException('A settled Bookstore obligation still blocked clearance.');
    }
    echo "Bookstore obligation workflow test passed.\n";
} finally {
    if ($obligationId !== null) { $database->prepare('DELETE FROM bookstore_obligations WHERE id=:id')->execute(['id' => $obligationId]); }
}
