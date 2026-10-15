<?php

declare(strict_types=1);

require_once __DIR__ . '/../clearance/bulk_clearance.php';

// All fixtures live in memory; this test never approves real student records.
$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$database->sqliteCreateFunction('NOW', static fn(): string => gmdate('Y-m-d H:i:s'));
$database->exec("
    CREATE TABLE organizations (id INTEGER PRIMARY KEY, code TEXT);
    CREATE TABLE students (id INTEGER PRIMARY KEY, organization_id INTEGER, year_level INTEGER, status TEXT);
    CREATE TABLE academic_periods (id INTEGER PRIMARY KEY, status TEXT, clearance_status TEXT, semester TEXT);
    CREATE TABLE semester_enrollments (student_id INTEGER, academic_period_id INTEGER, status TEXT);
    CREATE TABLE clearances (id INTEGER PRIMARY KEY, student_id INTEGER, academic_period_id INTEGER);
    CREATE TABLE stations (id INTEGER PRIMARY KEY, code TEXT, parent_station_id INTEGER, status TEXT);
    CREATE TABLE clearance_stations (id INTEGER PRIMARY KEY, clearance_id INTEGER, station_id INTEGER, status TEXT);
    CREATE TABLE requirements (id INTEGER PRIMARY KEY, status TEXT, name TEXT, requirement_code TEXT);
    CREATE TABLE requirement_records (clearance_station_id INTEGER, requirement_id INTEGER, status TEXT);
    CREATE TABLE events (id INTEGER PRIMARY KEY, station_id INTEGER, organization_id INTEGER, academic_period_id INTEGER, is_required INTEGER, status TEXT);
    CREATE TABLE event_attendance (id INTEGER PRIMARY KEY, event_id INTEGER, student_id INTEGER, status TEXT);
    CREATE TABLE library_cards (student_id INTEGER, status TEXT);
    CREATE TABLE medical_requirements (student_id INTEGER, academic_period_id INTEGER, status TEXT);
    CREATE TABLE bookstore_obligations (student_id INTEGER, academic_period_id INTEGER, status TEXT);
    CREATE TABLE bookstore_fees (id INTEGER PRIMARY KEY, academic_period_id INTEGER, status TEXT, is_required INTEGER);
    CREATE TABLE student_bookstore_fees (student_id INTEGER, fee_id INTEGER, status TEXT);
    CREATE TABLE facilities_obligations (student_id INTEGER, academic_period_id INTEGER, status TEXT, item_type TEXT);
    CREATE TABLE violations (student_id INTEGER, station_id INTEGER, status TEXT);
    CREATE TABLE accounting_receipts (student_id INTEGER, academic_period_id INTEGER, status TEXT, receipt_code TEXT);
    CREATE TABLE accounting_obligations (student_id INTEGER, academic_period_id INTEGER, status TEXT);
    CREATE TABLE clearance_exceptions (clearance_station_id INTEGER, status TEXT, expires_at TEXT);
    CREATE TABLE document_requirements (id INTEGER PRIMARY KEY, academic_period_id INTEGER, status TEXT, is_required INTEGER);
    CREATE TABLE student_documents (id INTEGER PRIMARY KEY, student_id INTEGER, document_requirement_id INTEGER, status TEXT);
    INSERT INTO organizations VALUES (1,'CCS'),(2,'CBE');
    INSERT INTO academic_periods VALUES (1,'ACTIVE','OPEN','1ST');
");
$codes = ['DEAN', 'ORGANIZATION', ...array_column(OFFICE_ROLE_CONFIG, 'code')];
$stationIds = array_flip($codes);
foreach ($codes as $id => $code) {
    $parentId = in_array($code, ['STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC'], true) ? $stationIds['SASO'] : 'NULL';
    $database->exec("INSERT INTO stations VALUES ({$id},'{$code}',{$parentId},'ACTIVE')");
}
for ($id = 1; $id <= 35; $id++) {
    $database->exec("INSERT INTO students VALUES ({$id},1,1,'ACTIVE');
        INSERT INTO semester_enrollments VALUES ({$id},1,'ENROLLED');
        INSERT INTO clearances VALUES ({$id},{$id},1);
        INSERT INTO library_cards VALUES ({$id},'VERIFIED');
        INSERT INTO medical_requirements VALUES ({$id},1,'VERIFIED');
        INSERT INTO accounting_receipts VALUES ({$id},1,'SCANNED','PORTAL-{$id}')");
    foreach ($stationIds as $stationId) {
        $recordId = $id * 100 + $stationId;
        $database->exec("INSERT INTO clearance_stations VALUES ({$recordId},{$id},{$stationId},'APPROVED')");
    }
}
function bulkCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ($codes as $code) {
    $stationId = $stationIds[$code];
    $database->exec("UPDATE clearance_stations SET status='APPROVED'; UPDATE clearance_stations SET status='PENDING' WHERE station_id={$stationId}");
    $organizationId = in_array($code, ['DEAN', 'ORGANIZATION'], true) ? 1 : null;
    bulkCheck(bulkReadyClearanceIds($database, $code, $organizationId) === range(1, 35), "{$code} did not include ready students beyond page one.");

    $database->beginTransaction();
    $database->exec("UPDATE clearance_stations SET status='LOCKED' WHERE station_id={$stationId} AND clearance_id=1;
        UPDATE clearance_stations SET status='RETURNED' WHERE station_id={$stationId} AND clearance_id=2;
        UPDATE clearance_stations SET status='APPROVED' WHERE station_id={$stationId} AND clearance_id=3;
        UPDATE students SET status='INACTIVE' WHERE id=4;
        UPDATE semester_enrollments SET status='REMOVED' WHERE student_id=5");
    bulkCheck(bulkReadyClearanceIds($database, $code, $organizationId) === range(6, 35), "{$code} included a locked, returned, cleared, inactive or unenrolled student.");
    $database->rollBack();

    $database->beginTransaction();
    $blockedRecordId = 100 + $stationId;
    $blockingSql = match ($code) {
        'DEAN' => "INSERT INTO requirements VALUES (1,'ACTIVE','Academic requirement','ACADEMIC'); INSERT INTO requirement_records VALUES ({$blockedRecordId},1,'NOT_STARTED')",
        'ORGANIZATION', 'STUDENT_COUNCIL' => "INSERT INTO events VALUES (1,{$stationId},1,1,1,'ACTIVE'); INSERT INTO event_attendance (event_id,student_id,status) SELECT 1,id,'PRESENT' FROM students WHERE id<>1",
        'LIBRARY' => "UPDATE library_cards SET status='PENDING' WHERE student_id=1",
        'BOOKSTORE' => "INSERT INTO bookstore_obligations VALUES (1,1,'UNPAID')",
        'CLINIC' => "UPDATE medical_requirements SET status='PENDING' WHERE student_id=1",
        'SASO' => "UPDATE clearance_stations SET status='PENDING' WHERE clearance_id=1 AND station_id=" . $stationIds['ORGANIZATION'],
        'FACILITIES' => "INSERT INTO facilities_obligations VALUES (1,1,'OUTSTANDING','BORROWED_ITEM')",
        'COMLAB' => "INSERT INTO violations VALUES (1,{$stationId},'OPEN')",
        'ACCOUNTING' => "INSERT INTO accounting_obligations VALUES (1,1,'UNPAID')",
        'REGISTRAR' => "INSERT INTO document_requirements VALUES (1,1,'ACTIVE',1); INSERT INTO student_documents (student_id,document_requirement_id,status) SELECT id,1,'VERIFIED' FROM students WHERE id<>1",
    };
    $database->exec($blockingSql);
    bulkCheck(bulkReadyClearanceIds($database, $code, $organizationId) === range(2, 35), "{$code} included a student with incomplete requirements.");
    $database->rollBack();

    $database->beginTransaction();
    $database->exec("UPDATE academic_periods SET clearance_status='CLOSED'");
    bulkCheck(bulkReadyClearanceIds($database, $code, $organizationId) === [], "{$code} allowed closed-period approvals.");
    $database->rollBack();
}

// CBE remains scoped to the assigned college and selected year group, with YBC sign-off required.
$database->exec("UPDATE clearance_stations SET status='APPROVED';
    INSERT INTO students VALUES (36,2,4,'ACTIVE'),(37,2,1,'ACTIVE');
    INSERT INTO semester_enrollments VALUES (36,1,'ENROLLED'),(37,1,'ENROLLED');
    INSERT INTO clearances VALUES (36,36,1),(37,37,1);
    INSERT INTO clearance_stations VALUES (3600,36,0,'PENDING'),(3700,37,0,'PENDING'),(3601,36,1,'APPROVED'),(3701,37,1,'APPROVED');
    INSERT INTO requirements VALUES (1,'ACTIVE','OJT','CBE_OJT_REQUIREMENTS');
    INSERT INTO requirement_records VALUES (3600,1,'NOT_STARTED')");
bulkCheck(bulkReadyClearanceIds($database, 'DEAN', 2, 'FOURTH_YEAR') === [36], 'CBE fourth-year scope or OJT handling changed.');
bulkCheck(bulkReadyClearanceIds($database, 'DEAN', 2, 'NON_GRADUATING') === [37], 'CBE non-graduating scope was not respected.');
$database->exec("UPDATE clearance_stations SET status='PENDING' WHERE id=3601");
bulkCheck(bulkReadyClearanceIds($database, 'DEAN', 2, 'FOURTH_YEAR') === [], 'CBE Dean bulk clearance bypassed YBC sign-off.');
bulkCheck(bulkReadyClearanceIds($database, 'DEAN', 1) === [], 'Dean bulk clearance included another college.');

$database->exec("UPDATE clearance_stations SET status='PENDING' WHERE station_id=" . $stationIds['FACILITIES']);
bulkCheck(bulkReadyClearanceIds($database, 'FACILITIES', null, '', 'GRADUATING', 'CCS') === [], 'Facilities ignored the selected group.');
bulkCheck(bulkReadyClearanceIds($database, 'FACILITIES', null, '', 'NON_GRADUATING', 'CCS') === range(1, 35), 'Facilities lost ready students in the selected department.');

try {
    approveAllReadyClearances($database, ['id' => 1, 'role' => 'STUDENT']);
    throw new LogicException('Student accounts must not be allowed to bulk approve.');
} catch (RuntimeException $exception) {
    bulkCheck(str_contains($exception->getMessage(), 'not assigned'), 'Unexpected role rejection.');
}

startSecureSession();
$footer = renderBulkClearanceFooter(1, 2, [], '/office_action.php', 35, 'the office');
bulkCheck(str_contains($footer, 'Clear all ready (35)') && str_contains($footer, 'value="approve_all_ready"') && str_contains($footer, 'name="csrf_token"'), 'Bulk approval form is incomplete.');
bulkCheck(str_contains(renderBulkClearanceFooter(1, 1, [], '/office_action.php', 0, 'the office'), ' disabled'), 'Empty ready roster did not disable the button.');
// CCS uses the whole uncleared roster for its final-batch exception.
$database->exec("ALTER TABLE organizations ADD COLUMN name TEXT DEFAULT 'Test college';
    ALTER TABLE organizations ADD COLUMN status TEXT DEFAULT 'ACTIVE';
    CREATE TABLE staff_assignments (user_id INTEGER,station_id INTEGER,organization_id INTEGER,status TEXT);
    INSERT INTO staff_assignments VALUES (100,0,1,'ACTIVE');");
$ccsDean = ['organization_id' => 1, 'organization_code' => 'CCS'];
$database->beginTransaction();
try {
    for ($remaining = 0; $remaining <= 6; $remaining++) {
        $database->exec("UPDATE clearance_stations SET status='APPROVED' WHERE station_id=0 AND clearance_id<=35;
            UPDATE clearance_stations SET status='PENDING' WHERE station_id=0 AND clearance_id<={$remaining}");
        bulkCheck(deanRemainingClearanceCount($database, 1) === $remaining, 'CCS remaining count included another college or cleared students.');
        $minimum = deanMinimumBulkCount($database, $ccsDean);
        bulkCheck($minimum === ($remaining < 5 ? 1 : 5), 'CCS final-batch exception failed at ' . $remaining . ' remaining.');
        $footer = renderBulkClearanceFooter(1, 1, [], '/dean/action.php', $remaining, 'CCS Dean', [], $minimum);
        bulkCheck(str_contains($footer, ' disabled') === ($remaining === 0), 'CCS ready batch button is incorrect at ' . $remaining . ' remaining.');
    }
    // Four ready out of six uncleared students must be rejected before any approvals.
    $database->exec("INSERT INTO requirements VALUES (2,'ACTIVE','Required evaluation','CCS_FACULTY_EVALUATION');
        INSERT INTO requirement_records VALUES (500,2,'NOT_STARTED'),(600,2,'NOT_STARTED')");
    bulkCheck(bulkReadyClearanceIds($database, 'DEAN', 1) === [1,2,3,4], 'CCS bulk readiness included incomplete students.');
    bulkCheck(str_contains(renderBulkClearanceFooter(1, 1, [], '/dean/action.php', 4, 'CCS Dean', [], 5), ' disabled'), 'CCS undersized batch button was enabled.');
    try {
        approveAllReadyClearances($database, ['id' => 100, 'role' => 'DEAN']);
        throw new LogicException('CCS accepted four ready students while six remained.');
    } catch (RuntimeException $exception) {
        bulkCheck(str_contains($exception->getMessage(), 'at least 5'), 'Unexpected CCS rejection: ' . $exception->getMessage());
    }
    bulkCheck(deanRemainingClearanceCount($database, 1) === 6, 'Rejected CCS batch changed student decisions.');
    $database->exec("UPDATE clearance_stations SET status='APPROVED' WHERE station_id=0 AND clearance_id IN (5,6);
        UPDATE students SET status='INACTIVE' WHERE id=4;");
    bulkCheck(deanRemainingClearanceCount($database, 1) === 3 && deanMinimumBulkCount($database, $ccsDean) === 1, 'Inactive students or another college blocked the CCS final batch.');
    $database->exec("UPDATE semester_enrollments SET status='REMOVED' WHERE student_id=3");
    bulkCheck(deanRemainingClearanceCount($database, 1) === 2, 'Removed enrollments blocked the CCS final batch.');
    bulkCheck(deanMinimumBulkCount($database, ['organization_id' => 2, 'organization_code' => 'CBE']) === 1, 'CCS minimum affected another college.');
    $database->exec("UPDATE academic_periods SET status='CLOSED' WHERE id=1");
    bulkCheck(deanRemainingClearanceCount($database, 1) === 0, 'Archived semester students blocked the CCS final batch.');
} finally {
    $database->rollBack();
}

echo "Bulk clearance test passed for all 11 approving offices.\n";
echo "CCS minimum batch, final 1-4 students, roster scope and server rejection checks passed.\n";
