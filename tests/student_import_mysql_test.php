<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../registrar/student_import_functions.php';

$database = getDatabaseConnection();
$token = strtolower(bin2hex(random_bytes(5)));
$existingNumber = 'IMPORT-UPDATE-' . strtoupper($token);
$newNumber = 'IMPORT-ADD-' . strtoupper($token);
$oldEmail = 'import-old-' . $token . '@thelewiscollege.edu.ph';
$updatedEmail = 'import-updated-' . $token . '@thelewiscollege.edu.ph';
$newEmail = 'import-new-' . $token . '@thelewiscollege.edu.ph';
$existingUserId = null;
$existingStudentId = null;
$failures = [];

try {
    $organization = $database->query("SELECT id, code FROM organizations WHERE status = 'ACTIVE' ORDER BY id LIMIT 1")->fetch();
    if ($organization === false) {
        throw new RuntimeException('No active organization is available for the importer test.');
    }

    $insertUser = $database->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, 'STUDENT', 'ACTIVE')");
    $insertUser->execute(['email' => $oldEmail, 'hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $existingUserId = (int) $database->lastInsertId();
    $insertStudent = $database->prepare("INSERT INTO students (user_id, student_number, first_name, last_name, program, year_level, section, organization_id, status) VALUES (:user_id, :number, 'Before', 'Import', 'Old Program', 1, 'TEST', :organization_id, 'ACTIVE')");
    $insertStudent->execute(['user_id' => $existingUserId, 'number' => $existingNumber, 'organization_id' => $organization['id']]);
    $existingStudentId = (int) $database->lastInsertId();

    $preview = validateStudentImport($database, [
        ['ACADEMIC YEAR 2026-2027 1ST SEMESTER'],
        [],
        ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address'],
        ['1', $existingNumber, 'Import, Updated', 'BSCS - Updated Program', 'Second Year', $updatedEmail],
        ['2', $newNumber, 'Import, Added', 'BSCS - New Program', 'First Year', $newEmail],
    ]);
    if ($preview['valid_count'] !== 2 || $preview['updated_count'] !== 1 || $preview['added_count'] !== 1) {
        $failures[] = 'Live validation totals were incorrect.';
    }
    $result = importValidatedStudents($database, $preview);
    if ($result !== ['added' => 1, 'updated' => 1, 'skipped' => 0, 'errors' => 0, 'removed_from_semester' => 0]) {
        $failures[] = 'Live import totals were incorrect.';
    }

    $check = $database->prepare('SELECT s.id, s.first_name, s.last_name, s.program, s.year_level, s.section, u.email FROM students s INNER JOIN users u ON u.id = s.user_id WHERE s.student_number = :number');
    $check->execute(['number' => $existingNumber]);
    $updated = $check->fetch();
    if ($updated === false || (int) $updated['id'] !== $existingStudentId || $updated['email'] !== $updatedEmail || $updated['first_name'] !== 'Updated' || $updated['last_name'] !== 'Import' || $updated['program'] !== 'BSCS - Updated Program' || (int) $updated['year_level'] !== 2 || $updated['section'] !== 'TEST') {
        $failures[] = 'The live update did not preserve the student identity/section or update basic information.';
    }
    $check->execute(['number' => $newNumber]);
    $addedStudent = $check->fetch();
    if ($addedStudent === false) {
        $failures[] = 'The live add did not create a student profile.';
    } else {
        $passwordCheck = $database->prepare('SELECT users.password_hash FROM users INNER JOIN students ON students.user_id=users.id WHERE students.student_number=:number');
        $passwordCheck->execute(['number' => $newNumber]);
        if (!password_verify('thelewiscollege', (string) $passwordCheck->fetchColumn())) {
            $failures[] = 'The imported student did not receive the institutional initial password.';
        }
    }
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
} finally {
    try {
        $deleteStudents = $database->prepare('DELETE FROM students WHERE student_number IN (:existing_number, :new_number)');
        $deleteStudents->execute(['existing_number' => $existingNumber, 'new_number' => $newNumber]);
        $deleteUsers = $database->prepare('DELETE FROM users WHERE email IN (:old_email, :updated_email, :new_email)');
        $deleteUsers->execute(['old_email' => $oldEmail, 'updated_email' => $updatedEmail, 'new_email' => $newEmail]);
    } catch (Throwable $cleanupException) {
        $failures[] = 'Test cleanup failed: ' . $cleanupException->getMessage();
    }
}

if ($failures !== []) {
    fwrite(STDERR, "MySQL student import test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "MySQL student import test passed.\n";
