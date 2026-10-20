<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../clearance/remarks_functions.php';
require_once __DIR__ . '/../library/workbook_functions.php';
require_once __DIR__ . '/../clinic/workbook_functions.php';
require_once __DIR__ . '/../bookstore/workbook_functions.php';
require_once __DIR__ . '/../clearance/report_workbook.php';

function remarksCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function remarksDenied(callable $operation): void
{
    try { $operation(); } catch (RuntimeException $exception) {
        if ($exception instanceof PDOException) { throw $exception; }
        return;
    }
    throw new LogicException('An unauthorized remarks change was accepted.');
}

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$database->exec("CREATE TABLE organizations (id INTEGER PRIMARY KEY,code TEXT,name TEXT,status TEXT);
    CREATE TABLE staff_assignments (user_id INTEGER,station_id INTEGER,organization_id INTEGER,status TEXT);
    CREATE TABLE stations (id INTEGER PRIMARY KEY,code TEXT,name TEXT,status TEXT);
    CREATE TABLE students (id INTEGER PRIMARY KEY,user_id INTEGER,organization_id INTEGER,student_number TEXT,status TEXT);
    CREATE TABLE academic_periods (id INTEGER PRIMARY KEY,status TEXT,clearance_status TEXT);
    CREATE TABLE semester_enrollments (student_id INTEGER,academic_period_id INTEGER,status TEXT);
    CREATE TABLE clearances (id INTEGER PRIMARY KEY,student_id INTEGER,academic_period_id INTEGER);
    CREATE TABLE clearance_stations (id INTEGER PRIMARY KEY,clearance_id INTEGER,station_id INTEGER,status TEXT,office_remarks TEXT,returned_reason TEXT);
    CREATE TABLE audit_logs (user_id INTEGER,action TEXT,station_id INTEGER,student_id INTEGER,description TEXT,old_status TEXT,new_status TEXT);
    CREATE TABLE notifications (user_id INTEGER,academic_period_id INTEGER,title TEXT,message TEXT,is_read INTEGER DEFAULT 0);
    INSERT INTO organizations VALUES (1,'CCS','Computer Studies','ACTIVE'),(2,'CBE','Business','ACTIVE');
    INSERT INTO students VALUES (1,10,1,'TEST-1','ACTIVE'),(2,20,2,'TEST-2','ACTIVE');
    INSERT INTO academic_periods VALUES (1,'ACTIVE','OPEN'),(2,'CLOSED','CLOSED');
    INSERT INTO semester_enrollments VALUES (1,1,'ENROLLED'),(1,2,'ENROLLED'),(2,1,'ENROLLED');
    INSERT INTO clearances VALUES (1,1,1),(2,1,2),(3,2,1);");
$roles = ['DEAN' => 'DEAN', 'ORG_STAFF' => 'ORGANIZATION', ...array_map(static fn(array $config): string => $config['code'], OFFICE_ROLE_CONFIG)];
$stationIds = [];
foreach ($roles as $role => $code) {
    $id = count($stationIds) + 1;
    $stationIds[$code] = $id;
    $database->exec("INSERT INTO stations VALUES ({$id},'{$code}','{$code}','ACTIVE');
        INSERT INTO clearance_stations VALUES ({$id},1,{$id},'PENDING',NULL,'Original return reason');
        INSERT INTO clearance_stations VALUES (" . ($id + 100) . ",2,{$id},'APPROVED','Historical message',NULL);
        INSERT INTO clearance_stations VALUES (" . ($id + 200) . ",3,{$id},'PENDING',NULL,NULL);");
    if (in_array($role, ['DEAN','ORG_STAFF'], true)) {
        $database->exec("INSERT INTO staff_assignments VALUES (100,{$id},1,'ACTIVE')");
    }
    $user = ['id' => 100, 'role' => $role];
    $copy = "Bring the original document.\n<message> & follow up.";
    remarksCheck(saveOfficeRemarks($database, $user, 1, $copy), $role . ' could not save remarks.');
    remarksCheck(!saveOfficeRemarks($database, $user, 1, $copy), $role . ' duplicated an unchanged message.');
    $record = $database->query("SELECT * FROM clearance_stations WHERE id={$id}")->fetch();
    remarksCheck($record['office_remarks'] === $copy && $record['status'] === 'PENDING' && $record['returned_reason'] === 'Original return reason', $role . ' changed a decision or lost its message.');
    remarksDenied(fn() => saveOfficeRemarks($database, $user, 2, 'Rewrite history'));
    remarksDenied(fn() => saveOfficeRemarks($database, $user, 1, str_repeat('x', 1001)));
    if (in_array($role, ['DEAN','ORG_STAFF'], true)) { remarksDenied(fn() => saveOfficeRemarks($database, $user, 3, 'Wrong college')); }
    remarksCheck(saveOfficeRemarks($database, $user, 1, ''), 'Staff cannot remove remarks.');
    saveOfficeRemarks($database, $user, 1, $copy);
    $database->exec("UPDATE clearance_stations SET status='APPROVED' WHERE id={$id}");
    remarksDenied(fn() => saveOfficeRemarks($database, $user, 1, 'Comment after clearance'));
    remarksDenied(fn() => saveOfficeRemarks($database, $user, 1, ''));
    remarksCheck(!saveOfficeRemarks($database, $user, 1, $copy), 'An unchanged cleared workbook row must remain a no-op.');
    remarksCheck($database->query("SELECT office_remarks FROM clearance_stations WHERE id={$id}")->fetchColumn() === $copy, 'Rejected cleared-student edits changed the remarks.');
}
remarksDenied(fn() => saveOfficeRemarks($database, ['id' => 10,'role' => 'STUDENT'], 1, 'Student edit'));
$database->exec("UPDATE semester_enrollments SET status='REMOVED' WHERE student_id=1 AND academic_period_id=1");
remarksDenied(fn() => saveOfficeRemarks($database, ['id' => 100,'role' => 'CLINIC_STAFF'], 1, 'Removed student'));
$database->exec("UPDATE semester_enrollments SET status='ENROLLED' WHERE student_id=1 AND academic_period_id=1;
    UPDATE clearance_stations SET status='PENDING' WHERE clearance_id=1;
    UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=1;");
$notificationCount = (int) $database->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
saveOfficeRemarks($database, ['id' => 100,'role' => 'CLINIC_STAFF'], 1, 'Preparation message');
remarksCheck((int) $database->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === $notificationCount, 'Preparation remarks sent premature notifications.');
foreach (['library' => 'LIBRARY', 'clinic' => 'CLINIC', 'bookstore' => 'BOOKSTORE'] as $office => $code) {
    $import = 'import' . ucfirst($office) . 'Workbook';
    $result = $import($database, 100, [['student_number' => 'TEST-1', 'status' => 'PENDING', 'remarks' => $office . ' pending message']]);
    remarksCheck($result['errors'] === [] && $result['remarks_updated'] === 1 && $result['cleared'] === 0, $office . ' pending workbook remarks failed.');
    $record = $database->query('SELECT office_remarks,status FROM clearance_stations WHERE id=' . $stationIds[$code])->fetch();
    remarksCheck($record['office_remarks'] === $office . ' pending message' && $record['status'] === 'PENDING', $office . ' remarks import changed the decision.');
    $database->exec('UPDATE clearance_stations SET status=\'APPROVED\' WHERE id=' . $stationIds[$code]);
    $result = $import($database, 100, [['student_number' => 'TEST-1', 'status' => 'PENDING', 'remarks' => 'Rewrite cleared remarks']]);
    remarksCheck(count($result['errors']) === 1 && $result['remarks_updated'] === 0, $office . ' workbook bypassed the cleared-student remarks lock.');
}
remarksCheck(str_contains(renderOfficeRemarksButton(1, '', 'Test Student', true), '>Add remarks</button>'), 'Empty remarks must offer Add remarks.');
remarksCheck(str_contains(renderOfficeRemarksButton(1, 'Existing message', 'Test Student', true), '>Edit remarks</button>'), 'Saved remarks must offer Edit remarks.');
remarksCheck(str_contains(renderOfficeRemarksButton(1, 'Existing message', 'Test Student', false), ' disabled'), 'Cleared remarks button must be disabled.');
// Exercise generated templates, multiline text, and older header orders through the actual readers.
$sample = ['student_number' => 'TEST-1', 'first_name' => 'Test', 'last_name' => 'Student', 'program' => 'BSCS', 'year_level' => 1,
    'office_remarks' => "Bring your original.\nFollow up & <confirm>.", 'remarks' => "Bring your original.\nFollow up & <confirm>.",
    'library_status' => 'PENDING', 'clinic_status' => 'PENDING', 'medical_status' => 'PENDING', 'bookstore_status' => 'PENDING', 'pending_balance' => 0];
foreach (['library','clinic','bookstore'] as $office) {
    $worksheet = $office . 'WorksheetXml';
    $reader = 'read' . ucfirst($office) . 'Workbook';
    $sheet = $worksheet('CCS', [$sample], 'TEST SEMESTER');
    $parsed = parseStudentWorksheet($sheet, [], true);
    remarksCheck(end($parsed[2]) === 'Remarks', $office . ' template does not end with Remarks.');
    $temporary = tempnam(sys_get_temp_dir(), 'remarks-xlsx-');
    try {
        file_put_contents($temporary, createStoredZipArchive(['xl/worksheets/sheet1.xml' => $sheet, 'xl/worksheets/sheet2.xml' => $sheet, 'xl/worksheets/sheet3.xml' => $sheet]));
        $rows = $reader($temporary);
        remarksCheck($rows[0]['remarks'] === $sample['office_remarks'], $office . ' lost multiline remarks in template readback.');
        $legacyHeaders = $office === 'library' ? ['Student Number','Library Status'] : ['Student Number','Remarks','Clearance Status'];
        $legacyRow = $office === 'library' ? ['TEST-1','PENDING'] : ['TEST-1','Legacy message','PENDING'];
        $legacySheet = clearanceReportWorksheetXml('Old template', $legacyHeaders, [$legacyRow]);
        file_put_contents($temporary, createStoredZipArchive(['xl/worksheets/sheet1.xml' => $legacySheet, 'xl/worksheets/sheet2.xml' => $legacySheet, 'xl/worksheets/sheet3.xml' => $legacySheet]));
        $rows = $reader($temporary);
        remarksCheck($rows[0]['remarks'] === ($office === 'library' ? null : 'Legacy message'), $office . ' no longer accepts its older template.');
    } finally { if (is_string($temporary)) { unlink($temporary); } }
}
echo "Office remarks save, removal, ownership, semester protection, notifications and pending workbook imports passed for all 11 offices.\n";
echo "Multiline remarks, final template columns and older workbook compatibility passed.\n";
