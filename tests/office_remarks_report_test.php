<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../clearance/remarks_functions.php';
require_once __DIR__ . '/../clearance/report_workbook.php';
require_once __DIR__ . '/../config/database.php';
$database = getDatabaseConnection();
$role = $argv[1] ?? 'DEAN';
$account = $database->prepare("SELECT id,email,role FROM users WHERE role=:role AND status='ACTIVE' ORDER BY id LIMIT 1");
$account->execute(['role' => $role]);
$user = $account->fetch();
if (!$user) { throw new RuntimeException('No active report test account for ' . $role); }
$assignment = officeRemarksAssignment($database, $user);
$scope = isset($assignment['organization_id']) ? ' AND student.organization_id=' . (int) $assignment['organization_id'] : '';
if ($role === 'DEAN' && deanAssignment($database, (int) $user['id'])['organization_code'] === 'CBE') { $scope .= ' AND student.year_level<4'; }
$target = $database->prepare("SELECT clearance.id,student.year_level FROM clearance_stations record
    INNER JOIN stations station ON station.id=record.station_id AND station.code=:code AND station.status='ACTIVE'
    INNER JOIN clearances clearance ON clearance.id=record.clearance_id
    INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' AND period.clearance_status IN ('PREPARING','OPEN')
    INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
    INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
    WHERE record.status<>'APPROVED' {$scope} LIMIT 1");
$target->execute(['code' => $assignment['code']]);
$targetRow = $target->fetch();
$id = $targetRow['id'] ?? null;
if (!$id) { throw new RuntimeException('No enrolled report fixture for ' . $role); }
startSecureSession();
$_SESSION['authenticated_user'] = $user;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['export' => 'xlsx', 'filter' => 'ALL'];
if ($role === 'FACILITIES_STAFF') { $_GET['audience'] = (int) $targetRow['year_level'] >= 4 ? 'GRADUATING' : 'NON_GRADUATING'; }
$copy = 'REPORT-REMARKS-' . bin2hex(random_bytes(6));
$database->beginTransaction();
saveOfficeRemarks($database, $user, (int) $id, $copy);
ob_start();
register_shutdown_function(static function () use ($database, $role, $copy): void {
    $workbook = ob_get_clean();
    $temporary = tempnam(sys_get_temp_dir(), 'remarks-report-');
    $failure = null;
    try {
        file_put_contents($temporary, $workbook);
        $entries = readZipEntries($temporary, [], 'xl/worksheets/');
        $found = false;
        foreach ($entries as $sheet) {
            $rows = parseStudentWorksheet($sheet, []);
            if (end($rows[2]) !== 'Remarks') { throw new RuntimeException('Remarks is not the final report column.'); }
            foreach (array_slice($rows, 3) as $row) { if (end($row) === $copy) { $found = true; } }
        }
        if (!$found) { throw new RuntimeException('The saved office remarks are missing from the report.'); }
    } catch (Throwable $exception) { $failure = $exception; }
    finally {
        if (is_string($temporary)) { unlink($temporary); }
        if ($database->inTransaction()) { $database->rollBack(); }
        $_SESSION = [];
        session_destroy();
    }
    if ($failure) { fwrite(STDERR, $role . ': ' . $failure->getMessage() . "\n"); exit(1); }
    echo $role . " Excel export contains saved remarks in its final column; changes rolled back.\n";
});
if ($role === 'DEAN') { require __DIR__ . '/../dean/dashboard.php'; }
elseif ($role === 'ORG_STAFF') { require __DIR__ . '/../organization/dashboard.php'; }
else {
    require_once __DIR__ . '/../clearance/office_dashboard.php';
    $config = officeConfigForRole($role);
    renderOfficeDashboard($role, $config['code'], $config['title']);
}
