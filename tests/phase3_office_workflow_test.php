<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

$database = getDatabaseConnection();
$failures = [];
$token = strtoupper(bin2hex(random_bytes(4)));
$studentUserId = null;
$studentId = null;
$eventId = null;
$activePeriodId = null;
$originalClearanceStatus = null;
$testFeeId = null;
$testDocumentRequirementId = null;

function phaseThreeCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

try {
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $user = $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,'STUDENT','ACTIVE')");
    $user->execute(['email' => 'phase3-' . strtolower($token) . '@test.invalid', 'password' => $passwordHash]);
    $studentUserId = (int) $database->lastInsertId();

    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CCS' LIMIT 1")->fetchColumn();
    $staff = $database->query("SELECT id,role FROM users WHERE status='ACTIVE' AND role IN ('ORG_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','SASO_STAFF','FACILITIES_STAFF','ACCOUNTING_STAFF','REGISTRAR') ORDER BY id")->fetchAll();
    $staffByRole = [];
    foreach ($staff as $member) {
        $staffByRole[$member['role']] ??= (int) $member['id'];
    }
    foreach (['STUDENT_COUNCIL_STAFF', 'LIBRARY_STAFF', 'BOOKSTORE_STAFF', 'CLINIC_STAFF', 'SASO_STAFF', 'FACILITIES_STAFF', 'ACCOUNTING_STAFF', 'REGISTRAR'] as $role) {
        if (!isset($staffByRole[$role])) {
            throw new RuntimeException('Missing test staff role: ' . $role);
        }
    }
    // The test invokes workflow functions directly; use the Facilities test account
    // until a dedicated Computer Laboratory account is configured by the Registrar.
    $staffByRole['COMLAB_STAFF'] = $staffByRole['FACILITIES_STAFF'];
    $orgStaffQuery = $database->prepare("SELECT user_id FROM staff_assignments WHERE organization_id=:organization_id AND status='ACTIVE' ORDER BY id LIMIT 1");
    $orgStaffQuery->execute(['organization_id' => $organizationId]);
    $orgStaffId = (int) $orgStaffQuery->fetchColumn();
    $deanQuery = $database->prepare(
        "SELECT assignment.user_id FROM staff_assignments assignment
         INNER JOIN users dean ON dean.id=assignment.user_id AND dean.role='DEAN' AND dean.status='ACTIVE'
         INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN'
         WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE' LIMIT 1"
    );
    $deanQuery->execute(['organization_id' => $organizationId]);
    $staffByRole['DEAN'] = (int) $deanQuery->fetchColumn();
    if ($staffByRole['DEAN'] < 1) {
        throw new RuntimeException('Missing Dean assigned to the test organization.');
    }

    $student = $database->prepare("INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (:user_id,:number,'Phase Three','Workflow Student','BS Computer Science',1,'TEST',:organization_id,'ACTIVE')");
    $student->execute(['user_id' => $studentUserId, 'number' => 'PHASE3-' . $token, 'organization_id' => $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $activePeriod = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $activePeriodId = (int) $activePeriod['id'];
    $originalClearanceStatus = (string) $activePeriod['clearance_status'];
    $database->prepare("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=:id")->execute(['id' => $activePeriodId]);
    $fee = $database->prepare("INSERT INTO bookstore_fees (academic_period_id,name,amount,is_required,status) VALUES (:period_id,:name,0,1,'ACTIVE')");
    $fee->execute(['period_id' => $activePeriodId, 'name' => 'Phase 3 Test Fee ' . $token]);
    $testFeeId = (int) $database->lastInsertId();
    $documentRequirement = $database->prepare("INSERT INTO document_requirements (academic_period_id,name,description,is_required,status) VALUES (:period_id,:name,'Test only',1,'ACTIVE')");
    $documentRequirement->execute(['period_id' => $activePeriodId, 'name' => 'Phase 3 Test Document ' . $token]);
    $testDocumentRequirementId = (int) $database->lastInsertId();
    $enrollment = $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status) VALUES (:period_id,:student_id,'ENROLLED')");
    $enrollment->execute(['period_id' => $activePeriodId, 'student_id' => $studentId]);
    $started = startStudentClearance($database, $studentUserId);
    $clearanceId = (int) $started['clearance']['id'];

    try {
        returnOfficeClearance($database, 'FACILITIES', $clearanceId, $staffByRole['FACILITIES_STAFF'], 'Preparation-mode test requirement.');
        phaseThreeCheck(false, 'A locked office incorrectly took action during semester preparation.');
    } catch (RuntimeException $exception) {
        phaseThreeCheck(str_contains($exception->getMessage(), 'current state'), 'Locked office rejection returned an unexpected message.');
    }
    $preparationStatus = $database->prepare(
        "SELECT cs.status FROM clearance_stations cs
         INNER JOIN stations station ON station.id=cs.station_id AND station.code='FACILITIES'
         WHERE cs.clearance_id=:clearance_id"
    );
    $preparationStatus->execute(['clearance_id' => $clearanceId]);
    phaseThreeCheck($preparationStatus->fetchColumn() === 'LOCKED', 'A locked office status changed during semester preparation.');
    $database->prepare("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=:id")->execute(['id' => $activePeriodId]);

    $verifyDean = $database->prepare("UPDATE requirement_records rr INNER JOIN clearance_stations cs ON cs.id=rr.clearance_station_id INNER JOIN requirements r ON r.id=rr.requirement_id SET rr.status='VERIFIED',rr.verified_at=NOW() WHERE cs.clearance_id=:clearance_id AND r.requirement_code='CCS_FACULTY_EVALUATION'");
    $verifyDean->execute(['clearance_id' => $clearanceId]);
    approveDeanClearance($database, $staffByRole['DEAN'], $clearanceId);

    $orgStationId = (int) $database->query("SELECT id FROM stations WHERE code='ORGANIZATION'")->fetchColumn();
    $event = $database->prepare("INSERT INTO events (station_id,organization_id,academic_period_id,name,event_date,is_required,status,created_by) VALUES (:station_id,:organization_id,:period_id,:name,CURDATE(),1,'ACTIVE',:created_by)");
    $event->execute(['station_id' => $orgStationId, 'organization_id' => $organizationId, 'period_id' => $activePeriodId, 'name' => 'Phase 3 Test Event ' . $token, 'created_by' => $orgStaffId]);
    $eventId = (int) $database->lastInsertId();
    $attendance = $database->prepare("INSERT INTO event_attendance (event_id,student_id,status,recorded_by) VALUES (:event_id,:student_id,'PRESENT',:recorded_by)");
    $attendance->execute(['event_id' => $eventId, 'student_id' => $studentId, 'recorded_by' => $orgStaffId]);
    $otherAttendance = $database->prepare(
        "INSERT INTO event_attendance (event_id,student_id,status,recorded_by)
         SELECT event.id,:student_id,'PRESENT',:recorded_by FROM events event
         WHERE event.organization_id=:organization_id AND event.academic_period_id=:period_id
           AND event.is_required=1 AND event.status='ACTIVE'
         ON DUPLICATE KEY UPDATE status='PRESENT',recorded_by=VALUES(recorded_by)"
    );
    $otherAttendance->execute([
        'student_id' => $studentId,
        'recorded_by' => $orgStaffId,
        'organization_id' => $organizationId,
        'period_id' => $activePeriodId,
    ]);
    approveOrganizationStudent($database, $orgStaffId, $organizationId, $studentId);

    $statusQuery = $database->prepare("SELECT station.code,cs.status FROM clearance_stations cs INNER JOIN stations station ON station.id=cs.station_id WHERE cs.clearance_id=:clearance_id");
    $readStatuses = static function () use ($statusQuery, $clearanceId): array {
        $statusQuery->execute(['clearance_id' => $clearanceId]);
        $result = [];
        foreach ($statusQuery->fetchAll() as $row) {
            $result[$row['code']] = $row['status'];
        }
        return $result;
    };
    $statuses = $readStatuses();
    foreach (['STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC'] as $code) {
        phaseThreeCheck(($statuses[$code] ?? null) === 'PENDING', $code . ' did not unlock after Organization approval.');
    }
    phaseThreeCheck(($statuses['SASO'] ?? null) === 'LOCKED', 'SASO opened before all sub-checks were approved.');

    $verifyCouncil = $database->prepare("UPDATE requirement_records rr INNER JOIN clearance_stations cs ON cs.id=rr.clearance_station_id INNER JOIN stations station ON station.id=cs.station_id SET rr.status='VERIFIED' WHERE cs.clearance_id=:clearance_id AND station.code='STUDENT_COUNCIL'");
    $verifyCouncil->execute(['clearance_id' => $clearanceId]);
    $database->prepare(
        "INSERT INTO event_attendance (event_id,student_id,status,recorded_by)
         SELECT event.id,:student_id,'PRESENT',:recorded_by
         FROM events event
         INNER JOIN stations station ON station.id=event.station_id AND station.code='STUDENT_COUNCIL'
         WHERE event.academic_period_id=:period_id AND event.is_required=1 AND event.status='ACTIVE'
         ON DUPLICATE KEY UPDATE status='PRESENT',recorded_by=VALUES(recorded_by)"
    )->execute([
        'student_id' => $studentId,
        'recorded_by' => $staffByRole['STUDENT_COUNCIL_STAFF'],
        'period_id' => $activePeriodId,
    ]);
    approveOfficeClearance($database, 'STUDENT_COUNCIL', $clearanceId, $staffByRole['STUDENT_COUNCIL_STAFF']);

    $card = $database->prepare("UPDATE library_cards SET status='VERIFIED',verified_by=:user_id,verified_at=NOW() WHERE student_id=:student_id");
    $card->execute(['user_id' => $staffByRole['LIBRARY_STAFF'], 'student_id' => $studentId]);
    approveOfficeClearance($database, 'LIBRARY', $clearanceId, $staffByRole['LIBRARY_STAFF']);

    $fees = $database->prepare("UPDATE student_bookstore_fees SET status='PAID',payment_reference='PHASE3-TEST',paid_at=NOW(),verified_by=:user_id,verified_at=NOW() WHERE student_id=:student_id");
    $fees->execute(['user_id' => $staffByRole['BOOKSTORE_STAFF'], 'student_id' => $studentId]);
    approveOfficeClearance($database, 'BOOKSTORE', $clearanceId, $staffByRole['BOOKSTORE_STAFF']);

    $medical = $database->prepare("UPDATE medical_requirements SET status='VERIFIED',verified_by=:user_id,verified_at=NOW() WHERE student_id=:student_id");
    $medical->execute(['user_id' => $staffByRole['CLINIC_STAFF'], 'student_id' => $studentId]);
    approveOfficeClearance($database, 'CLINIC', $clearanceId, $staffByRole['CLINIC_STAFF']);
    $statuses = $readStatuses();
    phaseThreeCheck(($statuses['SASO'] ?? null) === 'PENDING', 'SASO did not unlock after all sub-checks were approved.');

    approveOfficeClearance($database, 'SASO', $clearanceId, $staffByRole['SASO_STAFF']);
    approveOfficeClearance($database, 'FACILITIES', $clearanceId, $staffByRole['FACILITIES_STAFF']);
    approveOfficeClearance($database, 'COMLAB', $clearanceId, $staffByRole['COMLAB_STAFF']);
    approveOfficeClearance($database, 'ACCOUNTING', $clearanceId, $staffByRole['ACCOUNTING_STAFF']);

    $documents = $database->prepare("UPDATE student_documents SET status='VERIFIED',reviewed_by=:user_id,reviewed_at=NOW() WHERE student_id=:student_id");
    $documents->execute(['user_id' => $staffByRole['REGISTRAR'], 'student_id' => $studentId]);
    approveOfficeClearance($database, 'REGISTRAR', $clearanceId, $staffByRole['REGISTRAR']);

    $overall = $database->prepare('SELECT status,completed_at FROM clearances WHERE id=:id');
    $overall->execute(['id' => $clearanceId]);
    $completion = $overall->fetch();
    phaseThreeCheck($completion['status'] === 'COMPLETED' && $completion['completed_at'] !== null, 'Registrar approval did not complete the clearance.');
    $approvalAudits = countQuery($database, "SELECT COUNT(*) FROM audit_logs WHERE student_id=:student_id AND action LIKE '%_CLEARANCE_APPROVED'", ['student_id' => $studentId]);
    phaseThreeCheck($approvalAudits >= 9, 'Office approvals were not fully audited.');
} catch (Throwable $exception) {
    $failures[] = 'Phase 3 integration setup failed: ' . $exception->getMessage();
} finally {
    $deleteStaffNotifications = $database->prepare('DELETE FROM notifications WHERE message LIKE :token');
    $deleteStaffNotifications->execute(['token' => '%' . $token . '%']);
    if ($eventId !== null) {
        $deleteAttendance = $database->prepare('DELETE FROM event_attendance WHERE student_id=:student_id');
        $deleteAttendance->execute(['student_id' => $studentId]);
        $deleteEvent = $database->prepare('DELETE FROM events WHERE id=:id');
        $deleteEvent->execute(['id' => $eventId]);
    }
    if ($studentId !== null) {
        foreach (['audit_logs', 'violations', 'borrowed_books', 'accounting_obligations'] as $table) {
            $delete = $database->prepare("DELETE FROM {$table} WHERE student_id=:student_id");
            $delete->execute(['student_id' => $studentId]);
        }
        $deleteClearance = $database->prepare('DELETE FROM clearances WHERE student_id=:student_id');
        $deleteClearance->execute(['student_id' => $studentId]);
        $deleteEnrollment = $database->prepare('DELETE FROM semester_enrollments WHERE student_id=:student_id');
        $deleteEnrollment->execute(['student_id' => $studentId]);
        foreach (['library_cards', 'medical_requirements', 'student_bookstore_fees', 'student_documents'] as $table) {
            $delete = $database->prepare("DELETE FROM {$table} WHERE student_id=:student_id");
            $delete->execute(['student_id' => $studentId]);
        }
        $deleteStudent = $database->prepare('DELETE FROM students WHERE id=:id');
        $deleteStudent->execute(['id' => $studentId]);
    }
    if ($studentUserId !== null) {
        $deleteUser = $database->prepare('DELETE FROM users WHERE id=:id');
        $deleteUser->execute(['id' => $studentUserId]);
    }
    if ($activePeriodId !== null && $originalClearanceStatus !== null) {
        $restorePeriod = $database->prepare('UPDATE academic_periods SET clearance_status=:status WHERE id=:id');
        $restorePeriod->execute(['status' => $originalClearanceStatus, 'id' => $activePeriodId]);
    }
    if ($testFeeId !== null) {
        $database->prepare('DELETE FROM bookstore_fees WHERE id=:id')->execute(['id' => $testFeeId]);
    }
    if ($testDocumentRequirementId !== null) {
        $database->prepare('DELETE FROM document_requirements WHERE id=:id')->execute(['id' => $testDocumentRequirementId]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Phase 3 checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Phase 3 office workflow checks passed.\n";
