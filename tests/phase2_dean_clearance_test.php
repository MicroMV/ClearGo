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
$sourceSchema = (string) $database->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $sourceSchema)) { throw new RuntimeException('Unsafe test template.'); }
$testSchema = 'cleargo_phase2_test_' . bin2hex(random_bytes(6));
$testSchemaCreated = false;
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
    $database->exec("CREATE DATABASE `$testSchema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $testSchemaCreated = true;
    $database->exec("USE `$testSchema`"); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query("SHOW FULL TABLES FROM `$sourceSchema` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Unsafe test table.'); }
        $database->exec($database->query("SHOW CREATE TABLE `$sourceSchema`.`$table`")->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations', 'stations', 'requirements'] as $table) { $database->exec("INSERT INTO `$table` SELECT * FROM `$sourceSchema`.`$table`"); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES('2098-2099','1ST','ACTIVE','PREPARING')");
    $fixturePeriod = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'NOT_READY' FROM stations WHERE code<>'REGISTRAR'")->execute([$fixturePeriod]);
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
    saveStaffSignature($database, ['id'=>$deanUserId,'role'=>'DEAN'], 'Synthetic Test Dean', 'College Dean', json_encode([[[0.1,0.4],[0.3,0.6],[0.5,0.3]]]));

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
            (user_id, student_number, first_name, last_name, program, year_level, organization_id, status)
         VALUES
            (:user_id, :student_number, 'Phase Two', 'Test Student', 'Test Program', 1, :organization_id, 'ACTIVE')"
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
    if ($database->inTransaction()) { $database->rollBack(); }
    if ($testSchemaCreated) { $database->exec("USE `$sourceSchema`"); $database->exec("DROP DATABASE `$testSchema`"); }
}

if ($failures !== []) {
    fwrite(STDERR, "Phase 2 checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Phase 2 Dean clearance checks passed.\n";
