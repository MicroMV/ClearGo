<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../registrar/semester_functions.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

// Clone definitions and office configuration only. Never run mutations on school records.
$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_cbe_order_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $template) || $temporary === $template) {
    throw new RuntimeException('Unsafe test database configuration.');
}
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD');
ini_set('mysqlnd.net_read_timeout', '5');
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password === false ? '' : $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$checks = 0;
function cbeOrderCheck(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($description); }
    $checks++;
    echo "PASS: {$description}\n";
}
function cbeOrderStatuses(PDO $database, int $clearanceId): array
{
    $query = $database->prepare('SELECT station.code,record.status FROM clearance_stations record JOIN stations station ON station.id=record.station_id WHERE record.clearance_id=?');
    $query->execute([$clearanceId]);
    return $query->fetchAll(PDO::FETCH_KEY_PAIR);
}
function cbeOrderStudent(PDO $database, int $periodId, int $organizationId, int $year, bool $prepare = true): array
{
    $token = bin2hex(random_bytes(6));
    $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,'STUDENT','ACTIVE')")
        ->execute(['cbe-order-' . $token . '@test.invalid', password_hash($token, PASSWORD_DEFAULT)]);
    $userId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,?,'Synthetic','CBE Order Test','Test Program',?,'QA',?,'ACTIVE')")
        ->execute([$userId, 'CBE-ORDER-' . $token, $year, $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$periodId, $studentId]);
    if ($prepare) { prepareSemesterClearanceRecords($database, $periodId); }
    $query = $database->prepare('SELECT id FROM clearances WHERE student_id=? AND academic_period_id=?');
    $query->execute([$studentId, $periodId]);
    return ['student_id' => $studentId, 'clearance_id' => (int) $query->fetchColumn()];
}
function cbeOrderSet(PDO $database, int $clearanceId, string $code, string $status): void
{
    $database->prepare('UPDATE clearance_stations record JOIN stations station ON station.id=record.station_id SET record.status=? WHERE record.clearance_id=? AND station.code=?')
        ->execute([$status, $clearanceId, $code]);
}

try {
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`');
    $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Invalid template table.'); }
        $ddl = $database->query('SHOW CREATE TABLE `' . $template . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        $database->exec($ddl);
    }
    foreach (['organizations', 'stations', 'requirements'] as $table) {
        $database->exec('INSERT INTO `' . $temporary . '`.`' . $table . '` SELECT * FROM `' . $template . '`.`' . $table . '`');
    }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $organizations = $database->query('SELECT code,id FROM organizations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $users = [];
    foreach (['REGISTRAR', 'ORG_STAFF', 'DEAN'] as $role) {
        $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,? ,?,'ACTIVE')")
            ->execute([strtolower($role) . '@cbe-order.test.invalid', password_hash('test-only', PASSWORD_DEFAULT), $role]);
        $users[$role] = (int) $database->lastInsertId();
    }
    foreach (['ORG_STAFF' => 'ORGANIZATION', 'DEAN' => 'DEAN'] as $role => $code) {
        $database->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) SELECT ?,id,?,'ACTIVE' FROM stations WHERE code=?")
            ->execute([$users[$role], $organizations['CBE'], $code]);
    }
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2098-2099','1ST','ACTIVE','PREPARING')");
    $periodId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
    $cbe = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 1);
    $fourthYear = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 4);
    foreach ([$cbe, $fourthYear] as $student) {
        $status = cbeOrderStatuses($database, $student['clearance_id']);
        cbeOrderCheck($status['ORGANIZATION'] === 'PENDING' && $status['DEAN'] === 'LOCKED', 'Registrar preparation starts CBE at Organization/YBC, including fourth year.');
    }
    foreach (['CCS', 'CTE'] as $code) {
        $student = cbeOrderStudent($database, $periodId, (int) $organizations[$code], 2);
        $status = cbeOrderStatuses($database, $student['clearance_id']);
        cbeOrderCheck($status['DEAN'] === 'PENDING' && $status['ORGANIZATION'] === 'LOCKED', $code . ' retains its existing Dean-first rule.');
    }
    $denied = false;
    try { approveDeanClearance($database, $users['DEAN'], $cbe['clearance_id']); }
    catch (RuntimeException $exception) { $denied = true; }
    cbeOrderCheck($denied && cbeOrderStatuses($database, $cbe['clearance_id'])['DEAN'] === 'LOCKED', 'Dean cannot sign before CBE Organization approval.');
    approveOrganizationStudent($database, $users['ORG_STAFF'], (int) $organizations['CBE'], $cbe['student_id']);
    $status = cbeOrderStatuses($database, $cbe['clearance_id']);
    cbeOrderCheck($status['ORGANIZATION'] === 'APPROVED' && $status['DEAN'] === 'PENDING', 'Real Organization approval unlocks CBE Dean.');
    $before = $status;
    prepareSemesterClearanceRecords($database, $periodId);
    cbeOrderCheck(cbeOrderStatuses($database, $cbe['clearance_id']) === $before, 'Repeated preparation preserves existing approvals.');
    $openingStudent = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 2, false);
    $enrolledCount = openRegistrarClearance($database, $users['REGISTRAR']);
    $query = $database->prepare('SELECT id FROM clearances WHERE student_id=? AND academic_period_id=?');
    $query->execute([$openingStudent['student_id'], $periodId]);
    $status = cbeOrderStatuses($database, (int) $query->fetchColumn());
    cbeOrderCheck($enrolledCount === 5 && $status['ORGANIZATION'] === 'PENDING' && $status['DEAN'] === 'LOCKED', 'Semester opening also starts previously unprepared CBE students at Organization.');
    cbeOrderCheck(cbeOrderStatuses($database, $cbe['clearance_id']) === $before, 'Semester opening preserves earlier CBE Organization approval.');
    $notice = $database->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND academic_period_id=? AND title='Semester clearance opened'");
    $notice->execute([$users['ORG_STAFF'], $periodId]);
    cbeOrderCheck((int) $notice->fetchColumn() === 1, 'CBE Organization staff receive the semester-opening notice.');
    cbeOrderCheck((int) $database->query("SELECT COUNT(*) FROM notifications WHERE title='Clearance opened - Dean action needed'")->fetchColumn() === 0, 'Opening does not falsely announce Dean-first action for all colleges.');

    $legacy = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 2);
    $decided = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 2);
    $complete = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 2);
    foreach ([$legacy, $decided, $complete] as $student) {
        cbeOrderSet($database, $student['clearance_id'], 'ORGANIZATION', 'LOCKED');
        cbeOrderSet($database, $student['clearance_id'], 'DEAN', 'PENDING');
    }
    $database->prepare("UPDATE clearances SET status='COMPLETED',completed_at=NOW() WHERE id=?")->execute([$complete['clearance_id']]);
    cbeOrderSet($database, $decided['clearance_id'], 'CLINIC', 'APPROVED');
    $archive = cbeOrderStudent($database, $periodId, (int) $organizations['CBE'], 2);
    cbeOrderSet($database, $archive['clearance_id'], 'ORGANIZATION', 'LOCKED');
    cbeOrderSet($database, $archive['clearance_id'], 'DEAN', 'PENDING');
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2097-2098','1ST','CLOSED','CLOSED')");
    $archivePeriod = (int) $database->lastInsertId();
    $database->prepare('UPDATE clearances SET academic_period_id=? WHERE id=?')->execute([$archivePeriod, $archive['clearance_id']]);
    $migration = file_get_contents(__DIR__ . '/../database/migrations/028_cbe_registrar_initial_station_order.sql');
    $database->exec($migration);
    $status = cbeOrderStatuses($database, $legacy['clearance_id']);
    cbeOrderCheck($status['ORGANIZATION'] === 'PENDING' && $status['DEAN'] === 'LOCKED', 'Migration repairs an untouched active CBE deadlock.');
    cbeOrderCheck(cbeOrderStatuses($database, $decided['clearance_id'])['CLINIC'] === 'APPROVED' && cbeOrderStatuses($database, $decided['clearance_id'])['DEAN'] === 'PENDING', 'Migration leaves applications with office decisions intact.');
    cbeOrderCheck(cbeOrderStatuses($database, $complete['clearance_id'])['ORGANIZATION'] === 'LOCKED', 'Migration does not alter a completed clearance.');
    cbeOrderCheck(cbeOrderStatuses($database, $archive['clearance_id'])['ORGANIZATION'] === 'LOCKED', 'Migration does not alter archived clearances.');
    cbeOrderCheck(cbeOrderStatuses($database, $cbe['clearance_id']) === $before, 'Migration preserves actual Organization approvals.');
    cbeOrderCheck($database->exec($migration) === 0, 'Migration is safe to repeat.');
    echo "All {$checks} Registrar CBE initialization and repair checks passed.\n";
} finally {
    if (!preg_match('/\Acleargo_cbe_order_test_[a-f0-9]{12}\z/', $temporary) || $temporary === $template) {
        throw new RuntimeException('Refusing unsafe test cleanup.');
    }
    $database->exec('DROP DATABASE IF EXISTS `' . $temporary . '`');
}
