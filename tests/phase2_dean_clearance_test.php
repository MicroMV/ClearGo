<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/evaluation_functions.php';

$database = getDatabaseConnection();
$failures = [];
$token = strtoupper(bin2hex(random_bytes(4)));
$studentUserId = null;
$deanUserId = null;
$studentId = null;
$activePeriodId = null;
$originalClearanceStatus = null;

function phaseTwoCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

try {
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $userInsert = $database->prepare(
        "INSERT INTO users (email, password_hash, role, status)
         VALUES (:email, :password_hash, :role, 'ACTIVE')"
    );
    $userInsert->execute([
        'email' => 'phase2-student-' . strtolower($token) . '@test.invalid',
        'password_hash' => $passwordHash,
        'role' => 'STUDENT',
    ]);
    $studentUserId = (int) $database->lastInsertId();
    $userInsert->execute([
        'email' => 'phase2-dean-' . strtolower($token) . '@test.invalid',
        'password_hash' => $passwordHash,
        'role' => 'DEAN',
    ]);
    $deanUserId = (int) $database->lastInsertId();

    $organizationId = $database->query(
        "SELECT id FROM organizations WHERE status = 'ACTIVE' AND code='CCS' LIMIT 1"
    )->fetchColumn();
    if ($organizationId === false) {
        throw new RuntimeException('No active organization is available for testing.');
    }
    $deanStationId = (int) $database->query("SELECT id FROM stations WHERE code='DEAN' LIMIT 1")->fetchColumn();
    $deanAssignment = $database->prepare(
        "INSERT INTO staff_assignments (user_id,station_id,organization_id,status)
         VALUES (:user_id,:station_id,:organization_id,'ACTIVE')"
    );
    $deanAssignment->execute([
        'user_id' => $deanUserId,
        'station_id' => $deanStationId,
        'organization_id' => $organizationId,
    ]);

    $studentInsert = $database->prepare(
        "INSERT INTO students
            (user_id, student_number, first_name, last_name, program, year_level, section, organization_id, status)
         VALUES
            (:user_id, :student_number, 'Phase Two', 'Test Student', 'Test Program', 1, 'TEST', :organization_id, 'ACTIVE')"
    );
    $studentInsert->execute([
        'user_id' => $studentUserId,
        'student_number' => 'PHASE2-' . $token,
        'organization_id' => $organizationId,
    ]);
    $studentId = (int) $database->lastInsertId();

    $activePeriod = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $activePeriodId = (int) $activePeriod['id'];
    $originalClearanceStatus = (string) $activePeriod['clearance_status'];
    $database->prepare("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=:id")->execute(['id' => $activePeriodId]);

    $started = startStudentClearance($database, $studentUserId);
    $clearanceId = (int) $started['clearance']['id'];

    $context = studentEvaluationContext($database, $studentUserId);
    saveEvaluationResult($database, $context, $studentUserId, [
        'status' => 'REJECTED',
        'model_name' => 'test-model',
        'confidence' => 0.2,
    ]);
    $returnedRequirement = $database->prepare(
        "SELECT record.status
         FROM requirement_records record
         INNER JOIN requirements requirement ON requirement.id = record.requirement_id
         WHERE record.clearance_station_id = :clearance_station_id
           AND requirement.requirement_code = 'CCS_FACULTY_EVALUATION'"
    );
    $returnedRequirement->execute(['clearance_station_id' => $context['clearance_station_id']]);
    phaseTwoCheck($returnedRequirement->fetchColumn() === 'RETURNED', 'Rejected proof did not return the evaluation requirement.');

    saveEvaluationResult($database, $context, $studentUserId, [
        'status' => 'VERIFIED',
        'model_name' => 'test-model',
        'confidence' => 0.99,
    ]);

    $resetEvaluation = $database->prepare(
        "UPDATE requirement_records record
         INNER JOIN requirements requirement ON requirement.id=record.requirement_id
         SET record.status='NOT_STARTED',record.verified_by=NULL,record.verified_at=NULL,record.remarks=NULL
         WHERE record.clearance_station_id=:clearance_station_id
           AND requirement.requirement_code='CCS_FACULTY_EVALUATION'"
    );
    $resetEvaluation->execute(['clearance_station_id' => $context['clearance_station_id']]);
    phaseTwoCheck(approveDeanClearance($database, $deanUserId, $clearanceId), 'Direct CCS Dean approval failed.');
    $returnedRequirement->execute(['clearance_station_id' => $context['clearance_station_id']]);
    phaseTwoCheck($returnedRequirement->fetchColumn() === 'VERIFIED', 'CCS Dean approval did not automatically verify the evaluation requirement.');
    phaseTwoCheck(!approveDeanClearance($database, $deanUserId, $clearanceId), 'Duplicate Dean approval was not prevented.');

    $statusQuery = $database->prepare(
        "SELECT stations.code, clearance_stations.status
         FROM clearance_stations
         INNER JOIN stations ON stations.id = clearance_stations.station_id
         WHERE clearance_stations.clearance_id = :clearance_id"
    );
    $statusQuery->execute(['clearance_id' => $clearanceId]);
    $statuses = [];
    foreach ($statusQuery->fetchAll() as $row) {
        $statuses[$row['code']] = $row['status'];
    }
    phaseTwoCheck(($statuses['DEAN'] ?? null) === 'APPROVED', 'Dean station was not approved.');
    phaseTwoCheck(($statuses['ORGANIZATION'] ?? null) === 'PENDING', 'Organization was not unlocked after Dean approval.');
    foreach (['SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
        phaseTwoCheck(($statuses[$code] ?? null) === 'LOCKED', $code . ' should remain locked after Dean approval.');
    }

    $columns = $database->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluation_verifications'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (['file_path', 'original_file_name', 'image', 'content', 'answers', 'comments', 'ratings'] as $forbiddenColumn) {
        phaseTwoCheck(!in_array($forbiddenColumn, $columns, true), 'Private evaluation data column exists: ' . $forbiddenColumn);
    }
} catch (Throwable $exception) {
    $failures[] = 'Phase 2 integration setup failed: ' . $exception->getMessage();
} finally {
    $deleteStaffNotifications = $database->prepare('DELETE FROM notifications WHERE message LIKE :token');
    $deleteStaffNotifications->execute(['token' => '%PHASE2-' . $token . '%']);
    if ($studentId !== null) {
        $deleteAudit = $database->prepare('DELETE FROM audit_logs WHERE student_id = :student_id');
        $deleteAudit->execute(['student_id' => $studentId]);
        $deleteClearance = $database->prepare('DELETE FROM clearances WHERE student_id = :student_id');
        $deleteClearance->execute(['student_id' => $studentId]);
        foreach (['library_cards', 'medical_requirements', 'student_bookstore_fees', 'student_documents'] as $sourceTable) {
            $deleteSource = $database->prepare("DELETE FROM {$sourceTable} WHERE student_id = :student_id");
            $deleteSource->execute(['student_id' => $studentId]);
        }
        $deleteStudent = $database->prepare('DELETE FROM students WHERE id = :student_id');
        $deleteStudent->execute(['student_id' => $studentId]);
    }
    foreach ([$studentUserId, $deanUserId] as $userId) {
        if ($userId !== null) {
            $deleteUser = $database->prepare('DELETE FROM users WHERE id = :user_id');
            $deleteUser->execute(['user_id' => $userId]);
        }
    }
    if ($activePeriodId !== null && $originalClearanceStatus !== null) {
        $restorePeriod = $database->prepare('UPDATE academic_periods SET clearance_status=:status WHERE id=:id');
        $restorePeriod->execute(['status' => $originalClearanceStatus, 'id' => $activePeriodId]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Phase 2 checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Phase 2 Dean clearance checks passed.\n";
