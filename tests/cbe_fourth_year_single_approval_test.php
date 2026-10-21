<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

$database = getDatabaseConnection();
$userId = null;
$studentId = null;
$periodId = null;
$originalPeriodStatus = null;
$failures = [];

try {
    $cbeId = (int) $database->query("SELECT id FROM organizations WHERE code='CBE' LIMIT 1")->fetchColumn();
    $deanQuery = $database->prepare(
        "SELECT assignment.user_id FROM staff_assignments assignment
         INNER JOIN users user ON user.id=assignment.user_id AND user.role='DEAN' AND user.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN'
         WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE' LIMIT 1"
    );
    $deanQuery->execute(['organization_id' => $cbeId]);
    $deanId = (int) $deanQuery->fetchColumn();
    $organizationStaffQuery = $database->prepare(
        "SELECT assignment.user_id FROM staff_assignments assignment
         INNER JOIN users user ON user.id=assignment.user_id AND user.role='ORG_STAFF' AND user.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='ORGANIZATION'
         WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE' LIMIT 1"
    );
    $organizationStaffQuery->execute(['organization_id' => $cbeId]);
    $organizationStaffId = (int) $organizationStaffQuery->fetchColumn();
    if ($cbeId < 1 || $deanId < 1 || $organizationStaffId < 1) {
        throw new RuntimeException('CBE Organization or Dean assignment is unavailable.');
    }

    $period = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $periodId = (int) $period['id'];
    $originalPeriodStatus = (string) $period['clearance_status'];
    $database->prepare("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=:id")->execute(['id' => $periodId]);

    $token = strtolower(bin2hex(random_bytes(5)));
    $user = $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,'STUDENT','ACTIVE')");
    $user->execute(['email' => 'cbe-fourth-' . $token . '@test.invalid', 'password' => password_hash(random_bytes(16), PASSWORD_DEFAULT)]);
    $userId = (int) $database->lastInsertId();
    $student = $database->prepare(
        "INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,organization_id,status)
         VALUES (:user_id,:number,'CBE Fourth Year','Test Student','BSBA',4,:organization_id,'ACTIVE')"
    );
    $student->execute(['user_id' => $userId, 'number' => 'CBE4-' . strtoupper($token), 'organization_id' => $cbeId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status) VALUES (:period_id,:student_id,'ENROLLED')")
        ->execute(['period_id' => $periodId, 'student_id' => $studentId]);
    $clearanceId = (int) startStudentClearance($database, $userId)['clearance']['id'];

    $ojtQuery = $database->prepare(
        "SELECT record.status FROM requirement_records record
         INNER JOIN requirements requirement ON requirement.id=record.requirement_id
         INNER JOIN clearance_stations station_record ON station_record.id=record.clearance_station_id
         WHERE station_record.clearance_id=:clearance_id AND requirement.requirement_code='CBE_OJT_REQUIREMENTS'"
    );
    $ojtQuery->execute(['clearance_id' => $clearanceId]);
    if ($ojtQuery->fetchColumn() !== 'NOT_STARTED') {
        $failures[] = 'The test OJT requirement did not begin as NOT STARTED.';
    }

    approveOrganizationStudent($database, $organizationStaffId, $cbeId, $studentId);
    if (!approveDeanClearance($database, $deanId, $clearanceId)) {
        $failures[] = 'The single CBE approval did not run.';
    }
    $ojtQuery->execute(['clearance_id' => $clearanceId]);
    if ($ojtQuery->fetchColumn() !== 'VERIFIED') {
        $failures[] = 'The Approve action did not verify the OJT requirement.';
    }
    $deanStatus = $database->prepare(
        "SELECT record.status FROM clearance_stations record
         INNER JOIN stations station ON station.id=record.station_id AND station.code='DEAN'
         WHERE record.clearance_id=:clearance_id"
    );
    $deanStatus->execute(['clearance_id' => $clearanceId]);
    if ($deanStatus->fetchColumn() !== 'APPROVED') {
        $failures[] = 'The same Approve action did not clear the Dean station.';
    }
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
} finally {
    if ($studentId !== null) {
        $database->prepare('DELETE FROM audit_logs WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM notifications WHERE user_id=:user_id')->execute(['user_id' => $userId]);
        $database->prepare('DELETE FROM clearances WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM semester_enrollments WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        foreach (['library_cards', 'medical_requirements', 'student_bookstore_fees'] as $table) {
            $database->prepare("DELETE FROM {$table} WHERE student_id=:student_id")->execute(['student_id' => $studentId]);
        }
        $database->prepare('DELETE FROM students WHERE id=:student_id')->execute(['student_id' => $studentId]);
    }
    if ($userId !== null) {
        $database->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
    }
    if ($periodId !== null && $originalPeriodStatus !== null) {
        $database->prepare('UPDATE academic_periods SET clearance_status=:status WHERE id=:id')->execute(['status' => $originalPeriodStatus, 'id' => $periodId]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "CBE fourth-year single approval test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "CBE fourth-year single approval test passed.\n";
