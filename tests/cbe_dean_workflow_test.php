<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

$database = getDatabaseConnection();
$token = strtoupper(bin2hex(random_bytes(4)));
$userId = null;
$studentId = null;
$eventId = null;
$periodId = null;
$originalPeriodStatus = null;
$failures = [];

try {
    $cbeId = (int) $database->query("SELECT id FROM organizations WHERE code='CBE'")->fetchColumn();
    $orgStaff = $database->prepare(
        "SELECT assignment.user_id FROM staff_assignments assignment
         INNER JOIN users user ON user.id=assignment.user_id AND user.role='ORG_STAFF' AND user.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='ORGANIZATION'
         WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE' LIMIT 1"
    );
    $orgStaff->execute(['organization_id' => $cbeId]);
    $orgStaffId = (int) $orgStaff->fetchColumn();
    $dean = $database->prepare(
        "SELECT assignment.user_id FROM staff_assignments assignment
         INNER JOIN users user ON user.id=assignment.user_id AND user.role='DEAN' AND user.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN'
         WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE' LIMIT 1"
    );
    $dean->execute(['organization_id' => $cbeId]);
    $deanId = (int) $dean->fetchColumn();
    if ($cbeId < 1 || $orgStaffId < 1 || $deanId < 1) {
        throw new RuntimeException('CBE YBC or Dean assignments are unavailable.');
    }

    $period = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $periodId = (int) $period['id'];
    $originalPeriodStatus = (string) $period['clearance_status'];
    $database->prepare("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=:id")->execute(['id' => $periodId]);

    $user = $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,'STUDENT','ACTIVE')");
    $user->execute([
        'email' => 'cbe-flow-' . strtolower($token) . '@test.invalid',
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $userId = (int) $database->lastInsertId();
    $student = $database->prepare(
        "INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status)
         VALUES (:user_id,:number,'CBE Workflow','Test Student','BSBA',1,'TEST',:organization_id,'ACTIVE')"
    );
    $student->execute(['user_id' => $userId, 'number' => 'CBE-' . $token, 'organization_id' => $cbeId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status) VALUES (:period_id,:student_id,'ENROLLED')")
        ->execute(['period_id' => $periodId, 'student_id' => $studentId]);
    $clearanceId = (int) startStudentClearance($database, $userId)['clearance']['id'];

    $directSignOff = organizationEligibility($database, $cbeId, $studentId);
    if (!$directSignOff['eligible'] || $directSignOff['events'] !== []) {
        $failures[] = 'CBE students were not eligible for direct YBC sign-off when no events were configured.';
    }

    $organizationStation = (int) $database->query("SELECT id FROM stations WHERE code='ORGANIZATION'")->fetchColumn();
    $event = $database->prepare(
        "INSERT INTO events (station_id,organization_id,name,event_date,is_required,status,created_by)
         VALUES (:station_id,:organization_id,:name,CURDATE(),1,'ACTIVE',:created_by)"
    );
    $event->execute([
        'station_id' => $organizationStation,
        'organization_id' => $cbeId,
        'name' => 'CBE YBC workflow test ' . $token,
        'created_by' => $orgStaffId,
    ]);
    $eventId = (int) $database->lastInsertId();
    $attendance = $database->prepare(
        "INSERT INTO event_attendance (event_id,student_id,status,recorded_by)
         SELECT id,:student_id,'PRESENT',:recorded_by FROM events
         WHERE organization_id=:organization_id AND station_id=:station_id
           AND is_required=1 AND status='ACTIVE'"
    );
    $attendance->execute([
        'student_id' => $studentId,
        'recorded_by' => $orgStaffId,
        'organization_id' => $cbeId,
        'station_id' => $organizationStation,
    ]);

    approveOrganizationStudent($database, $orgStaffId, $cbeId, $studentId);
    $ybcStatus = $database->prepare(
        "SELECT record.status FROM requirement_records record
         INNER JOIN requirements requirement ON requirement.id=record.requirement_id
         INNER JOIN clearance_stations station_record ON station_record.id=record.clearance_station_id
         WHERE station_record.clearance_id=:clearance_id AND requirement.requirement_code='CBE_YBC_APPROVAL'"
    );
    $ybcStatus->execute(['clearance_id' => $clearanceId]);
    if ($ybcStatus->fetchColumn() !== 'VERIFIED') {
        $failures[] = 'YBC approval did not verify the CBE Dean sign-off.';
    }
    $deanStatus = $database->prepare(
        "SELECT station_record.status FROM clearance_stations station_record
         INNER JOIN stations station ON station.id=station_record.station_id AND station.code='DEAN'
         WHERE station_record.clearance_id=:clearance_id"
    );
    $deanStatus->execute(['clearance_id' => $clearanceId]);
    if ($deanStatus->fetchColumn() === 'APPROVED') {
        $failures[] = 'YBC approval incorrectly replaced final CBE Dean approval.';
    }

    approveDeanClearance($database, $deanId, $clearanceId);
    $deanStatus->execute(['clearance_id' => $clearanceId]);
    if ($deanStatus->fetchColumn() !== 'APPROVED') {
        $failures[] = 'The CBE Dean could not approve after YBC sign-off.';
    }
    $sasoChildren = $database->prepare(
        "SELECT COUNT(*) FROM clearance_stations station_record
         INNER JOIN stations child ON child.id=station_record.station_id
         INNER JOIN stations parent ON parent.id=child.parent_station_id AND parent.code='SASO'
         WHERE station_record.clearance_id=:clearance_id AND station_record.status='PENDING'"
    );
    $sasoChildren->execute(['clearance_id' => $clearanceId]);
    if ((int) $sasoChildren->fetchColumn() < 1) {
        $failures[] = 'Dean approval did not release the next offices after prior YBC approval.';
    }
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
} finally {
    if ($studentId !== null) {
        $database->prepare('DELETE FROM event_attendance WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM audit_logs WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM notifications WHERE user_id=:user_id OR message LIKE :token')
            ->execute(['user_id' => $userId, 'token' => '%' . $token . '%']);
        $database->prepare('DELETE FROM clearances WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM semester_enrollments WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        foreach (['library_cards','medical_requirements','student_bookstore_fees','student_documents'] as $table) {
            $database->prepare("DELETE FROM {$table} WHERE student_id=:student_id")->execute(['student_id' => $studentId]);
        }
        $database->prepare('DELETE FROM students WHERE id=:student_id')->execute(['student_id' => $studentId]);
    }
    if ($eventId !== null) {
        $database->prepare('DELETE FROM events WHERE id=:id')->execute(['id' => $eventId]);
    }
    if ($userId !== null) {
        $database->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
    }
    if ($periodId !== null && $originalPeriodStatus !== null) {
        $database->prepare('UPDATE academic_periods SET clearance_status=:status WHERE id=:id')
            ->execute(['status' => $originalPeriodStatus, 'id' => $periodId]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "CBE Dean workflow checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "CBE YBC-to-Dean workflow checks passed.\n";
