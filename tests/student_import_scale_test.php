<?php
declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';

$database = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database->exec(
    "CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password_hash TEXT, role TEXT, status TEXT
    );
    CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER UNIQUE, student_number TEXT UNIQUE,
        first_name TEXT, last_name TEXT, program TEXT, year_level INTEGER, section TEXT,
        organization_id INTEGER, status TEXT
    );"
);

$rows = [];
for ($number = 1; $number <= 2500; $number++) {
    $rows[] = [
        'errors' => [],
        'action' => 'ADD',
        'school_email' => "scale{$number}@thelewiscollege.edu.ph",
        'student_id' => 'SCALE-' . $number,
        'first_name' => 'Scale',
        'last_name' => 'Student ' . $number,
        'course_program' => 'Test Program',
        'year_level' => 1,
        'organization_id' => 1,
    ];
}

$startedAt = microtime(true);
$result = importValidatedStudents($database, ['rows' => $rows]);
$elapsed = microtime(true) - $startedAt;
$studentCount = (int) $database->query('SELECT COUNT(*) FROM students')->fetchColumn();
$hashCount = (int) $database->query('SELECT COUNT(DISTINCT password_hash) FROM users')->fetchColumn();
$sampleHash = (string) $database->query('SELECT password_hash FROM users LIMIT 1')->fetchColumn();

if ($result['added'] !== 2500 || $studentCount !== 2500 || $hashCount !== 1
    || !password_verify(CLEAR_GO_INITIAL_PASSWORD, $sampleHash)) {
    fwrite(STDERR, "Student import scale test failed.\n");
    exit(1);
}

echo sprintf("Student import scale test passed: 2,500 students in %.2f seconds.\n", $elapsed);
