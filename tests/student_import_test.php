<?php

declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';

$failures = [];
function importCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function createStoredZip(array $entries): string
{
    $body = '';
    $directory = '';
    $offset = 0;
    foreach ($entries as $name => $content) {
        $crc = crc32($content);
        $size = strlen($content);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0) . $name . $content;
        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $body .= $local;
        $offset += strlen($local);
    }
    $count = count($entries);
    return $body . $directory . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);
}

$csv = tempnam(sys_get_temp_dir(), 'clear-go-csv-');
$xlsx = tempnam(sys_get_temp_dir(), 'clear-go-xlsx-');
try {
    file_put_contents($csv, "ACADEMIC YEAR 2026-2027 1ST SEMESTER\n\n#,Student No.,Full Name,Course,Year Level,Gsuite Address\n1,001,\"Dela Cruz, Juan, Santos\",BSCS,First Year,juan@thelewiscollege.edu.ph\n");
    $csvRows = readStudentImportFile($csv, 'students.csv');
    importCheck($csvRows[3][1] === '001', 'CSV reader did not preserve a text Student No.');
    importCheck($csvRows[3][2] === 'Dela Cruz, Juan, Santos', 'CSV reader did not parse a quoted full name.');

    $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . '<row r="1"><c r="A1" t="inlineStr"><is><t>ACADEMIC YEAR 2026-2027 1ST SEMESTER</t></is></c></row>'
        . '<row r="2"><c r="A2" t="inlineStr"><is><t>Student No.</t></is></c><c r="B2" t="inlineStr"><is><t>Full Name</t></is></c><c r="C2" t="inlineStr"><is><t>Course</t></is></c><c r="D2" t="inlineStr"><is><t>Year Level</t></is></c><c r="E2" t="inlineStr"><is><t>Gsuite Address</t></is></c></row>'
        . '<row r="3"><c r="A3" t="inlineStr"><is><t>002</t></is></c><c r="B3" t="inlineStr"><is><t>Reyes, Ana</t></is></c><c r="C3" t="inlineStr"><is><t>BSIT</t></is></c><c r="D3" t="inlineStr"><is><t>First Year</t></is></c><c r="E3" t="inlineStr"><is><t>ana@thelewiscollege.edu.ph</t></is></c></row>'
        . '</sheetData></worksheet>';
    file_put_contents($xlsx, createStoredZip(['xl/worksheets/sheet1.xml' => $sheet]));
    $xlsxRows = readStudentImportFile($xlsx, 'students.xlsx');
    importCheck($xlsxRows[3] === ['', '002', 'Reyes, Ana', 'BSIT', 'First Year', 'ana@thelewiscollege.edu.ph'], 'XLSX reader did not parse inline strings.');
    file_put_contents($xlsx, createStudentImportTemplateXlsx());
    $templateRows = readStudentImportFile($xlsx, 'template.xlsx');
    importCheck($templateRows[0] === ['ACADEMIC YEAR 2026-2027 1ST SEMESTER'], 'Generated Excel template period title could not be read back.');
    importCheck($templateRows[2] === ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address'], 'Generated Excel template headers are incorrect.');

    $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $database->exec("CREATE TABLE academic_periods (id INTEGER PRIMARY KEY, school_year TEXT, semester TEXT, status TEXT);
        CREATE TABLE organizations (id INTEGER PRIMARY KEY, code TEXT, name TEXT, status TEXT);
        CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password_hash TEXT, role TEXT, status TEXT);
        CREATE TABLE students (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER UNIQUE, student_number TEXT UNIQUE, first_name TEXT, last_name TEXT, program TEXT, year_level INTEGER, section TEXT, organization_id INTEGER, status TEXT);
        CREATE TABLE clearances (id INTEGER PRIMARY KEY, student_id INTEGER);
        INSERT INTO academic_periods VALUES (1, '2026-2027', '1ST', 'ACTIVE');
        INSERT INTO organizations VALUES (1, 'CCS', 'Computer Studies', 'ACTIVE');
        INSERT INTO users VALUES (1, 'old@thelewiscollege.edu.ph', 'hash', 'STUDENT', 'ACTIVE');
        INSERT INTO students VALUES (1, 1, 'STU-1', 'Old', 'Name', 'Old Program', 1, 'A', 1, 'ACTIVE');
        INSERT INTO clearances VALUES (1, 1);");

    $rawRows = [
        ['ACADEMIC YEAR 2026-2027 1ST SEMESTER'],
        [],
        ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address'],
        ['1', 'STU-1', 'Updated, Student, Middle', 'BSCS', 'Second Year', 'updated@thelewiscollege.edu.ph'],
        ['2', 'STU-2', 'New, Student', 'BSCS', 'First Year', 'new@thelewiscollege.edu.ph'],
        ['3', '', 'Missing, Id', 'BSCS', 'First Year', 'missing@thelewiscollege.edu.ph'],
        ['4', 'STU-4', 'Bad, Course', 'Unknown Course', 'First Year', 'bad@thelewiscollege.edu.ph'],
    ];
    $preview = validateStudentImport($database, $rawRows);
    importCheck($preview['valid_count'] === 2, 'Validation did not identify exactly two valid rows.');
    importCheck($preview['updated_count'] === 1 && $preview['added_count'] === 1, 'Validation did not distinguish add and update rows.');
    importCheck($preview['error_count'] === 2, 'Validation did not count invalid records correctly.');
    importCheck($preview['rows'][0]['action'] === 'UPDATE', 'An existing Student ID was not detected as an update.');

    $result = importValidatedStudents($database, $preview);
    importCheck($result === ['added' => 1, 'updated' => 1, 'skipped' => 2, 'errors' => 2, 'removed_from_semester' => 0], 'Import result totals are incorrect.');
    importCheck($database->query("SELECT email FROM users WHERE id = 1")->fetchColumn() === 'updated@thelewiscollege.edu.ph', 'Existing student email was not updated.');
    importCheck($database->query("SELECT first_name || ' ' || last_name FROM students WHERE id = 1")->fetchColumn() === 'Student Middle Updated', 'Existing full name was not parsed correctly.');
    importCheck((int) $database->query('SELECT COUNT(*) FROM clearances WHERE student_id = 1')->fetchColumn() === 1, 'Existing clearance history was changed.');
    importCheck((int) $database->query('SELECT COUNT(*) FROM students')->fetchColumn() === 2, 'The valid new student was not inserted.');

    $officialLayoutPreview = validateStudentImport($database, [
        ['ACADEMIC YEAR 2026-2027 1ST SEMESTER'],
        [],
        ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address'],
        ['1', 'C-2026-0889', 'Gogolin, Ella Victoria, Dawis', 'ACT - AppDev', 'First Year', 'ella.gogolin@thelewiscollege.edu.ph'],
        ['2', 'C-2026-0528', 'Trajico, Sean Jumar, Derla', 'ACT - Data Engr', 'Second Year', 'sean.trajico@thelewiscollege.edu.ph'],
        ['3', 'C-2023-0648', 'Nazareno, Khenzie, Garalde', 'ACT - Networking', 'Third Year', 'khenzie.nazareno@thelewiscollege.edu.ph'],
    ]);
    importCheck($officialLayoutPreview['valid_count'] === 3, 'The official student-record layout was not accepted.');
    importCheck($officialLayoutPreview['rows'][0]['first_name'] === 'Ella Victoria Dawis' && $officialLayoutPreview['rows'][0]['last_name'] === 'Gogolin', 'Last, first, middle name parsing is incorrect.');
    importCheck($officialLayoutPreview['rows'][0]['year_level'] === 1 && $officialLayoutPreview['rows'][1]['year_level'] === 2 && $officialLayoutPreview['rows'][2]['year_level'] === 3, 'Written year levels were not converted correctly.');
    importCheck(count(array_filter($officialLayoutPreview['rows'], static fn(array $row): bool => $row['organization'] === 'CCS')) === 3, 'ACT courses were not assigned to CCS.');

    $invalidDomainPreview = validateStudentImport($database, [
        $rawRows[0],
        $rawRows[1],
        $rawRows[2],
        ['1', 'DOMAIN-1', 'Wrong, Domain', 'BSCS', 'First Year', 'student@gmail.com'],
    ]);
    importCheck($invalidDomainPreview['valid_count'] === 0, 'A non-GSuite student address was accepted.');

    $duplicatePreview = validateStudentImport($database, [
        $rawRows[0],
        $rawRows[1],
        $rawRows[2],
        ['1', 'DUP-1', 'One, Student', 'BSCS', 'First Year', 'same@thelewiscollege.edu.ph'],
        ['2', 'DUP-1', 'Two, Student', 'BSCS', 'First Year', 'same@thelewiscollege.edu.ph'],
    ]);
    importCheck($duplicatePreview['valid_count'] === 0 && $duplicatePreview['error_count'] === 2, 'Duplicate IDs/emails within a file were not rejected.');
} finally {
    @unlink($csv);
    @unlink($xlsx);
}

if ($failures !== []) {
    fwrite(STDERR, "Student import tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Student import tests passed.\n";
