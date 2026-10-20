<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../dean/completion_workbook_functions.php';

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
    $database->prepare("INSERT INTO semester_enrollments(student_id,academic_period_id,status) VALUES (?,?,'ENROLLED')")->execute([$studentId, $activePeriodId]);

    $started = startStudentClearance($database, $studentUserId);
    $clearanceId = (int) $started['clearance']['id'];

    $blocked = false;
    try { approveDeanClearance($database, $deanUserId, $clearanceId); }
    catch (RuntimeException $exception) { $blocked = str_contains($exception->getMessage(), 'Excel completion list'); }
    phaseTwoCheck($blocked, 'Dean approval accepted missing completion confirmation.');
    $period = $database->query('SELECT school_year,semester FROM academic_periods WHERE id=' . $activePeriodId)->fetch();
    $import = importCcsCompletionWorkbook($database, $deanUserId, $activePeriodId, [['student_number' => 'PHASE2-' . $token, 'completed' => 'YES', 'school_year' => $period['school_year'], 'semester' => $period['semester']]]);
    phaseTwoCheck($import['completed'] === 1, 'Completion list import failed.');
    phaseTwoCheck(approveDeanClearance($database, $deanUserId, $clearanceId), 'Confirmed CCS Dean approval failed.');
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
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ccs_evaluation_completions'"
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
        $database->prepare('DELETE FROM semester_enrollments WHERE student_id=?')->execute([$studentId]);
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
