<?php

declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';

const LIBRARY_WORKBOOK_MAX_FILE_SIZE = 5 * 1024 * 1024;
const LIBRARY_WORKBOOK_MAX_ROWS = 5000;

function libraryWorkbookStudents(PDO $database): array
{
    return $database->query(
        "SELECT student.student_number,student.first_name,student.last_name,student.program,
                student.year_level,organization.code AS organization_code,
                library_record.status AS library_status
         FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
         INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code IN ('CCS','CBE','CTE')
         LEFT JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         LEFT JOIN stations library_station ON library_station.code='LIBRARY'
         LEFT JOIN clearance_stations library_record ON library_record.clearance_id=clearance.id AND library_record.station_id=library_station.id
         WHERE period.status='ACTIVE'
         ORDER BY organization.code,student.last_name,student.first_name,student.student_number"
    )->fetchAll();
}

function libraryWorksheetXml(string $organizationCode, array $students, string $periodLabel): string
{
    $rows = [
        [$organizationCode . ' LIBRARY CLEARANCE - ' . $periodLabel],
        ['Enter CLEARED only after the student presents a valid library card. Student Number must not be changed.'],
        ['Student Number', 'Full Name', 'Course', 'Year Level', 'Library Status'],
    ];
    foreach ($students as $student) {
        $rows[] = [
            $student['student_number'],
            $student['last_name'] . ', ' . $student['first_name'],
            $student['program'],
            'Year ' . (int) $student['year_level'],
            $student['library_status'] === 'APPROVED' ? 'CLEARED' : '',
        ];
    }
    $xmlRows = '';
    foreach ($rows as $rowIndex => $values) {
        $rowNumber = $rowIndex + 1;
        $cells = '';
        foreach ($values as $columnIndex => $value) {
            $number = $columnIndex + 1;
            $column = '';
            while ($number > 0) {
                $number--;
                $column = chr(65 + ($number % 26)) . $column;
                $number = intdiv($number, 26);
            }
            $cells .= '<c r="' . $column . $rowNumber . '" t="inlineStr"><is><t xml:space="preserve">'
                . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
        }
        $xmlRows .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>'
        . '<col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/>'
        . '<col min="3" max="3" width="25" customWidth="1"/><col min="4" max="5" width="16" customWidth="1"/>'
        . '</cols><sheetData>' . $xmlRows . '</sheetData><autoFilter ref="A3:E' . max(3, count($rows)) . '"/></worksheet>';
}

function createLibraryWorkbookXlsx(PDO $database): string
{
    $period = $database->query("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $semester = ['1ST' => 'FIRST SEMESTER', '2ND' => 'SECOND SEMESTER', 'SUMMER' => 'SUMMER'][$period['semester']] ?? $period['semester'];
    $periodLabel = $semester . ' A.Y. ' . $period['school_year'];
    $grouped = ['CCS' => [], 'CBE' => [], 'CTE' => []];
    foreach (libraryWorkbookStudents($database) as $student) {
        $grouped[$student['organization_code']][] = $student;
    }
    $sheetEntries = [];
    $sheetDeclarations = '';
    $sheetRelationships = '';
    $contentOverrides = '';
    foreach (array_keys($grouped) as $index => $code) {
        $sheetNumber = $index + 1;
        $sheetEntries['xl/worksheets/sheet' . $sheetNumber . '.xml'] = libraryWorksheetXml($code, $grouped[$code], $periodLabel);
        $sheetDeclarations .= '<sheet name="' . $code . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        $sheetRelationships .= '<Relationship Id="rId' . $sheetNumber . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNumber . '.xml"/>';
        $contentOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetNumber . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    return createStoredZipArchive(array_merge([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $contentOverrides . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetDeclarations . '</sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $sheetRelationships . '</Relationships>',
    ], $sheetEntries));
}

function readLibraryWorkbook(string $path): array
{
    $entries = readZipEntries($path, ['xl/sharedStrings.xml'], 'xl/worksheets/');
    $sharedStrings = [];
    if (isset($entries['xl/sharedStrings.xml'])) {
        $xml = loadImportXml($entries['xl/sharedStrings.xml'], 'shared strings');
        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $sharedStrings[] = normalizeImportText(implode('', array_map(static fn(SimpleXMLElement $part): string => (string) $part, $parts)));
        }
    }
    $worksheetNames = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name) === 1));
    natsort($worksheetNames);
    if (count($worksheetNames) !== 3) {
        throw new RuntimeException('Use the Library workbook with its CCS, CBE, and CTE sheets.');
    }
    $result = [];
    foreach ($worksheetNames as $worksheetName) {
        $rows = parseStudentWorksheet($entries[$worksheetName], $sharedStrings);
        $headerIndex = null;
        $map = [];
        foreach ($rows as $index => $row) {
            foreach (array_map(static fn(string $value): string => normalizedImportKey(rtrim($value, '.')), $row) as $column => $value) {
                if (in_array($value, ['student number', 'student no'], true)) {
                    $map['student_number'] = $column;
                }
                if ($value === 'library status') {
                    $map['status'] = $column;
                }
            }
            if (isset($map['student_number'], $map['status'])) {
                $headerIndex = $index;
                break;
            }
            $map = [];
        }
        if ($headerIndex === null) {
            continue;
        }
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $studentNumber = normalizeImportText((string) ($row[$map['student_number']] ?? ''));
            if ($studentNumber === '') {
                continue;
            }
            $result[] = ['student_number' => $studentNumber, 'status' => strtoupper(normalizeImportText((string) ($row[$map['status']] ?? '')))];
            if (count($result) > LIBRARY_WORKBOOK_MAX_ROWS) {
                throw new RuntimeException('The workbook exceeds 5,000 student rows.');
            }
        }
    }
    if ($result === []) {
        throw new RuntimeException('No Library template rows were found in the workbook.');
    }
    return $result;
}

function clearLibraryFromWorkbook(PDO $database, int $staffUserId, string $studentNumber): string
{
    $statement = $database->prepare(
        "SELECT clearance.id FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE' AND student.student_number=:student_number
         INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         WHERE period.status='ACTIVE' LIMIT 1"
    );
    $statement->execute(['student_number' => $studentNumber]);
    $clearanceId = $statement->fetchColumn();
    if ($clearanceId === false) {
        throw new RuntimeException($studentNumber . ': no current-semester Library clearance was found.');
    }
    try {
        return clearLibraryClearance($database, (int) $clearanceId, $staffUserId) ? 'cleared' : 'already_cleared';
    } catch (RuntimeException $exception) {
        throw new RuntimeException($studentNumber . ': ' . $exception->getMessage(), 0, $exception);
    }
}

function importLibraryWorkbook(PDO $database, int $staffUserId, array $rows): array
{
    $seen = [];
    $result = ['cleared' => 0, 'already_cleared' => 0, 'skipped' => 0, 'errors' => []];
    foreach ($rows as $row) {
        $key = strtoupper(trim($row['student_number']));
        if ($key === '' || isset($seen[$key])) {
            $result['skipped']++;
            continue;
        }
        $seen[$key] = true;
        if ($row['status'] !== 'CLEARED') {
            $result['skipped']++;
            continue;
        }
        try {
            $result[clearLibraryFromWorkbook($database, $staffUserId, $row['student_number'])]++;
        } catch (RuntimeException $exception) {
            $result['errors'][] = $exception->getMessage();
        }
    }
    return $result;
}
