<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';

$database = getDatabaseConnection();
$failures = [];

function checkPhaseOne(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$primaryStations = $database->query(
    "SELECT code FROM stations WHERE status = 'ACTIVE' AND parent_station_id IS NULL ORDER BY sort_order"
)->fetchAll(PDO::FETCH_COLUMN);
checkPhaseOne(
    $primaryStations === ['DEAN', 'ORGANIZATION', 'SASO', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'],
    'Primary station sequence is incorrect.'
);

$subchecks = $database->query(
    "SELECT child.code
     FROM stations child
     INNER JOIN stations parent ON parent.id = child.parent_station_id
     WHERE parent.code = 'SASO' AND child.status = 'ACTIVE'
     ORDER BY child.sort_order"
)->fetchAll(PDO::FETCH_COLUMN);
checkPhaseOne(
    $subchecks === ['STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC'],
    'SASO sub-check configuration is incorrect.'
);

$duplicateApplications = (int) $database->query(
    "SELECT COUNT(*) FROM (
        SELECT student_id, academic_period_id
        FROM clearances
        GROUP BY student_id, academic_period_id
        HAVING COUNT(*) > 1
     ) duplicate_clearances"
)->fetchColumn();
checkPhaseOne($duplicateApplications === 0, 'Duplicate student-period applications exist.');

$invalidStationSets = (int) $database->query(
    "SELECT COUNT(*) FROM (
        SELECT c.id
        FROM clearances c
        LEFT JOIN clearance_stations cs ON cs.clearance_id = c.id
        GROUP BY c.id
        HAVING COUNT(cs.id) <> (SELECT COUNT(*) FROM stations WHERE status = 'ACTIVE')
     ) invalid_sets"
)->fetchColumn();
checkPhaseOne($invalidStationSets === 0, 'An application does not have one record for every active station.');

$testToken = strtoupper(bin2hex(random_bytes(4)));
$testEmail = 'phase1-' . strtolower($testToken) . '@test.invalid';
$testStudentNumber = 'PHASE1-' . $testToken;
$testUserId = null;
$testStudentId = null;

try {
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $userInsert = $database->prepare(
        "INSERT INTO users (email, password_hash, role, status)
         VALUES (:email, :password_hash, 'STUDENT', 'ACTIVE')"
    );
    $userInsert->execute(['email' => $testEmail, 'password_hash' => $passwordHash]);
    $testUserId = (int) $database->lastInsertId();

    $organizationId = $database->query(
        "SELECT id FROM organizations WHERE status = 'ACTIVE' ORDER BY id LIMIT 1"
    )->fetchColumn();
    if ($organizationId === false) {
        throw new RuntimeException('No active test organization is available.');
    }

    $studentInsert = $database->prepare(
        "INSERT INTO students
            (user_id, student_number, first_name, last_name, program, year_level, section, organization_id, status)
         VALUES
            (:user_id, :student_number, 'Phase One', 'Test Student', 'Test Program', 1, 'TEST', :organization_id, 'ACTIVE')"
    );
    $studentInsert->execute([
        'user_id' => $testUserId,
        'student_number' => $testStudentNumber,
        'organization_id' => $organizationId,
    ]);
    $testStudentId = (int) $database->lastInsertId();

    $firstStart = startStudentClearance($database, $testUserId);
    $secondStart = startStudentClearance($database, $testUserId);
    checkPhaseOne($firstStart['created'] === true, 'A student could not create a clearance application.');
    checkPhaseOne($secondStart['created'] === false, 'Duplicate application prevention did not activate.');

    $statusQuery = $database->prepare(
        "SELECT s.code, cs.status
         FROM clearance_stations cs
         INNER JOIN stations s ON s.id = cs.station_id
         WHERE cs.clearance_id = :clearance_id"
    );
    $statusQuery->execute(['clearance_id' => $firstStart['clearance']['id']]);
    $initialStatuses = [];
    foreach ($statusQuery->fetchAll() as $row) {
        $initialStatuses[$row['code']] = $row['status'];
    }
    checkPhaseOne(($initialStatuses['DEAN'] ?? null) === 'PENDING', 'Dean was not opened as the first station.');
    foreach ($initialStatuses as $code => $status) {
        if ($code !== 'DEAN') {
            checkPhaseOne($status === 'LOCKED', $code . ' was not initially locked.');
        }
    }

    $auditQuery = $database->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE user_id = :user_id AND student_id = :student_id AND action = 'CLEARANCE_STARTED'"
    );
    $auditQuery->execute(['user_id' => $testUserId, 'student_id' => $testStudentId]);
    checkPhaseOne((int) $auditQuery->fetchColumn() === 1, 'Clearance creation was not audited exactly once.');

    $notificationQuery = $database->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE user_id = :user_id AND title = 'Clearance application started'"
    );
    $notificationQuery->execute(['user_id' => $testUserId]);
    checkPhaseOne((int) $notificationQuery->fetchColumn() === 1, 'Clearance creation notification was not created exactly once.');
} catch (Throwable $exception) {
    $failures[] = 'Creation integration check failed: ' . $exception->getMessage();
} finally {
    $deleteStaffNotifications = $database->prepare('DELETE FROM notifications WHERE message LIKE :token');
    $deleteStaffNotifications->execute(['token' => '%' . $testStudentNumber . '%']);
    if ($testUserId !== null && $testStudentId !== null) {
        $deleteAudit = $database->prepare('DELETE FROM audit_logs WHERE user_id = :user_id AND student_id = :student_id');
        $deleteAudit->execute(['user_id' => $testUserId, 'student_id' => $testStudentId]);
        $deleteClearance = $database->prepare('DELETE FROM clearances WHERE student_id = :student_id');
        $deleteClearance->execute(['student_id' => $testStudentId]);
        foreach (['library_cards', 'medical_requirements', 'student_bookstore_fees', 'student_documents'] as $sourceTable) {
            $deleteSource = $database->prepare("DELETE FROM {$sourceTable} WHERE student_id = :student_id");
            $deleteSource->execute(['student_id' => $testStudentId]);
        }
        $deleteStudent = $database->prepare('DELETE FROM students WHERE id = :student_id');
        $deleteStudent->execute(['student_id' => $testStudentId]);
    }
    if ($testUserId !== null) {
        $deleteUser = $database->prepare('DELETE FROM users WHERE id = :user_id');
        $deleteUser->execute(['user_id' => $testUserId]);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Phase 1 checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Phase 1 clearance checks passed.\n";
