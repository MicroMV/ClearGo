<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clinic/workbook_functions.php';

function clinicCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = getDatabaseConnection();
$temporaryFile = tempnam(sys_get_temp_dir(), 'clinic-workbook-');
$userId = null;
$studentId = null;
$periodId = null;
$originalPeriodStatus = null;
try {
    $workbook = createClinicWorkbookXlsx($database);
    clinicCheck($temporaryFile !== false && file_put_contents($temporaryFile, $workbook) === strlen($workbook), 'The Clinic workbook could not be saved for testing.');
    $entries = readZipEntries($temporaryFile, ['xl/workbook.xml'], 'xl/worksheets/');
    clinicCheck(isset($entries['xl/workbook.xml']), 'The Clinic workbook definition is missing.');
    clinicCheck(substr_count($entries['xl/workbook.xml'], '<sheet ') === 3, 'The Clinic workbook does not contain exactly three sheets.');
    foreach (['CCS', 'CBE', 'CTE'] as $code) {
        clinicCheck(str_contains($entries['xl/workbook.xml'], 'name="' . $code . '"'), "The {$code} Clinic sheet is missing.");
    }
    foreach (array_filter($entries, static fn(string $name): bool => str_starts_with($name, 'xl/worksheets/'), ARRAY_FILTER_USE_KEY) as $worksheet) {
        clinicCheck(str_contains($worksheet, 'Has Medical Record'), 'A Clinic sheet is missing the medical-record column.');
    }
    $rows = readClinicWorkbook($temporaryFile);
    clinicCheck($rows !== [], 'The generated Clinic workbook could not be read back.');

    $period = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $periodId = (int) $period['id'];
    $originalPeriodStatus = (string) $period['clearance_status'];
    $database->prepare("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=:id")->execute(['id' => $periodId]);
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CTE' LIMIT 1")->fetchColumn();
    $clinicStaffId = (int) $database->query("SELECT id FROM users WHERE role='CLINIC_STAFF' AND status='ACTIVE' ORDER BY id LIMIT 1")->fetchColumn();
    clinicCheck($organizationId > 0 && $clinicStaffId > 0, 'Clinic test dependencies are unavailable.');
    $token = strtoupper(bin2hex(random_bytes(4)));
    $user = $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,'STUDENT','ACTIVE')");
    $user->execute(['email' => 'clinic-workbook-' . strtolower($token) . '@test.invalid', 'password' => password_hash(random_bytes(16), PASSWORD_DEFAULT)]);
    $userId = (int) $database->lastInsertId();
    $studentNumber = 'CLINIC-' . $token;
    $student = $database->prepare("INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (:user_id,:number,'Clinic Workbook','Test Student','BEED',1,'TEST',:organization_id,'ACTIVE')");
    $student->execute(['user_id' => $userId, 'number' => $studentNumber, 'organization_id' => $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status) VALUES (:period_id,:student_id,'ENROLLED')")->execute(['period_id' => $periodId, 'student_id' => $studentId]);
    $clearanceId = (int) startStudentClearance($database, $userId)['clearance']['id'];

    $result = importClinicWorkbook($database, $clinicStaffId, [
        ['student_number' => $studentNumber, 'status' => 'CLEARED', 'remarks' => 'Complete medical record.'],
        ['student_number' => $studentNumber, 'status' => 'CLEARED', 'remarks' => 'Duplicate row.'],
        ['student_number' => 'DOES-NOT-CHANGE', 'status' => 'PENDING', 'remarks' => ''],
    ]);
    clinicCheck($result['cleared'] === 1 && $result['skipped'] === 2 && $result['errors'] === [], 'Clinic import totals are incorrect.');
    $medical = $database->prepare("SELECT status,remarks FROM medical_requirements WHERE student_id=:student_id AND academic_period_id=:period_id");
    $medical->execute(['student_id' => $studentId, 'period_id' => $periodId]);
    $medicalRow = $medical->fetch();
    clinicCheck($medicalRow !== false && $medicalRow['status'] === 'VERIFIED', 'Clinic import did not verify the medical record.');
    $clinicStatus = $database->prepare("SELECT record.status FROM clearance_stations record INNER JOIN stations station ON station.id=record.station_id AND station.code='CLINIC' WHERE record.clearance_id=:clearance_id");
    $clinicStatus->execute(['clearance_id' => $clearanceId]);
    clinicCheck($clinicStatus->fetchColumn() === 'APPROVED', 'Clinic import did not approve Clinic clearance.');
    echo "Clinic three-sheet workbook test passed.\n";
} finally {
    if (is_string($temporaryFile) && file_exists($temporaryFile)) {
        unlink($temporaryFile);
    }
    if ($studentId !== null) {
        $database->prepare('DELETE FROM audit_logs WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM notifications WHERE user_id=:user_id')->execute(['user_id' => $userId]);
        $database->prepare('DELETE FROM clearances WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        $database->prepare('DELETE FROM semester_enrollments WHERE student_id=:student_id')->execute(['student_id' => $studentId]);
        foreach (['library_cards', 'medical_requirements', 'student_bookstore_fees', 'student_documents'] as $table) {
            $database->prepare("DELETE FROM {$table} WHERE student_id=:student_id")->execute(['student_id' => $studentId]);
        }
        $database->prepare('DELETE FROM students WHERE id=:student_id')->execute(['student_id' => $studentId]);
    }
    if ($userId !== null) {
        $database->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
    }
    if ($periodId !== null && $originalPeriodStatus !== null) {
        $database->prepare('UPDATE academic_periods SET clearance_status=:status WHERE id=:id')->execute(['status' => $originalPeriodStatus, 'id' => $periodId]);
    }
}
