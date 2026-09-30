<?php

declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';

const BOOKSTORE_WORKBOOK_MAX_FILE_SIZE = 5 * 1024 * 1024;
const BOOKSTORE_WORKBOOK_MAX_ROWS = 5000;

function bookstoreWorkbookStudents(PDO $database): array
{
    return $database->query(
        "SELECT student.student_number,student.first_name,student.last_name,student.program,student.year_level,
                organization.code AS organization_code,bookstore_record.status AS bookstore_status,
                COUNT(CASE WHEN obligation.status='UNPAID' THEN 1 END) AS pending_count,
                COALESCE(SUM(CASE WHEN obligation.status='UNPAID' THEN obligation.amount ELSE 0 END),0) AS pending_balance,
                GROUP_CONCAT(CASE WHEN obligation.status='UNPAID' THEN obligation.description END ORDER BY obligation.id SEPARATOR '; ') AS pending_obligations
         FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
         INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code IN ('CCS','CBE','CTE')
         LEFT JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         LEFT JOIN stations bookstore_station ON bookstore_station.code='BOOKSTORE'
         LEFT JOIN clearance_stations bookstore_record ON bookstore_record.clearance_id=clearance.id AND bookstore_record.station_id=bookstore_station.id
         LEFT JOIN bookstore_obligations obligation ON obligation.student_id=student.id AND obligation.academic_period_id=period.id
         WHERE period.status='ACTIVE'
         GROUP BY student.id,student.student_number,student.first_name,student.last_name,student.program,student.year_level,organization.code,bookstore_record.status
         ORDER BY organization.code,student.last_name,student.first_name,student.student_number"
    )->fetchAll();
}

function bookstoreWorksheetXml(string $organizationCode, array $students, string $periodLabel): string
{
    $rows = [
        [$organizationCode . ' BOOKSTORE CLEARANCE - ' . $periodLabel],
        ['Enter CLEARED only after balances/accountabilities are settled. A receipt/reference is required when a pending obligation is listed. Student Number must not be changed.'],
        ['Student Number', 'Full Name', 'Course', 'Year Level', 'Pending Obligations', 'Pending Balance', 'Payment Receipt / Reference', 'Remarks', 'Bookstore Status'],
    ];
    foreach ($students as $student) {
        $rows[] = [
            $student['student_number'],
            $student['last_name'] . ', ' . $student['first_name'],
            $student['program'],
            'Year ' . (int) $student['year_level'],
            (string) ($student['pending_obligations'] ?? ''),
            number_format((float) $student['pending_balance'], 2, '.', ''),
            '',
            '',
            $student['bookstore_status'] === 'APPROVED' ? 'CLEARED' : '',
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
        . '<col min="3" max="3" width="25" customWidth="1"/><col min="4" max="4" width="14" customWidth="1"/>'
        . '<col min="5" max="5" width="42" customWidth="1"/><col min="6" max="6" width="17" customWidth="1"/>'
        . '<col min="7" max="8" width="28" customWidth="1"/><col min="9" max="9" width="18" customWidth="1"/>'
        . '</cols><sheetData>' . $xmlRows . '</sheetData><autoFilter ref="A3:I' . max(3, count($rows)) . '"/></worksheet>';
}

function createBookstoreWorkbookXlsx(PDO $database): string
{
    $period = $database->query("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $semester = ['1ST' => 'FIRST SEMESTER', '2ND' => 'SECOND SEMESTER', 'SUMMER' => 'SUMMER'][$period['semester']] ?? $period['semester'];
    $grouped = ['CCS' => [], 'CBE' => [], 'CTE' => []];
    foreach (bookstoreWorkbookStudents($database) as $student) {
        $grouped[$student['organization_code']][] = $student;
    }
    $entries = [];
    $sheets = '';
    $relationships = '';
    $overrides = '';
    foreach (array_keys($grouped) as $index => $code) {
        $number = $index + 1;
        $entries['xl/worksheets/sheet' . $number . '.xml'] = bookstoreWorksheetXml($code, $grouped[$code], $semester . ' A.Y. ' . $period['school_year']);
        $sheets .= '<sheet name="' . $code . '" sheetId="' . $number . '" r:id="rId' . $number . '"/>';
        $relationships .= '<Relationship Id="rId' . $number . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $number . '.xml"/>';
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $number . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    return createStoredZipArchive(array_merge([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $overrides . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>',
    ], $entries));
}

function readBookstoreWorkbook(string $path): array
{
    $entries = readZipEntries($path, ['xl/sharedStrings.xml'], 'xl/worksheets/');
    $shared = [];
    if (isset($entries['xl/sharedStrings.xml'])) {
        $xml = loadImportXml($entries['xl/sharedStrings.xml'], 'shared strings');
        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $shared[] = normalizeImportText(implode('', array_map(static fn(SimpleXMLElement $part): string => (string)$part, $parts)));
        }
    }
    $worksheets = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name) === 1));
    natsort($worksheets);
    if (count($worksheets) !== 3) {
        throw new RuntimeException('Use the Bookstore workbook with its CCS, CBE, and CTE sheets.');
    }
    $result = [];
    foreach ($worksheets as $worksheet) {
        $rows = parseStudentWorksheet($entries[$worksheet], $shared);
        $header = null;
        $map = [];
        foreach ($rows as $index => $row) {
            foreach (array_map(static fn(string $value): string => normalizedImportKey(rtrim($value, '.')), $row) as $column => $value) {
                if (in_array($value, ['student number', 'student no'], true)) {
                    $map['student_number'] = $column;
                }
                if (in_array($value, ['payment receipt reference', 'payment receipt / reference'], true)) {
                    $map['reference'] = $column;
                }
                if ($value === 'remarks') {
                    $map['remarks'] = $column;
                }
                if ($value === 'bookstore status') {
                    $map['status'] = $column;
                }
            }
            if (isset($map['student_number'], $map['status'])) {
                $header = $index;
                break;
            }
            $map = [];
        }
        if ($header === null) {
            continue;
        }
        foreach (array_slice($rows, $header + 1) as $row) {
            $number = normalizeImportText((string)($row[$map['student_number']] ?? ''));
            if ($number === '') {
                continue;
            }
            $result[] = [
                'student_number' => $number,
                'status' => strtoupper(normalizeImportText((string)($row[$map['status']] ?? ''))),
                'reference' => normalizeImportText((string)($row[$map['reference'] ?? -1] ?? '')),
                'remarks' => normalizeImportText((string)($row[$map['remarks'] ?? -1] ?? ''))
            ];
            if (count($result) > BOOKSTORE_WORKBOOK_MAX_ROWS) {
                throw new RuntimeException('The workbook exceeds 5,000 student rows.');
            }
        }
    }
    if ($result === []) {
        throw new RuntimeException('No Bookstore template rows were found in the workbook.');
    }
    return $result;
}

function clearBookstoreFromWorkbook(PDO $database, int $staffUserId, array $row): string
{
    $statement = $database->prepare("SELECT clearance.id FROM academic_periods period INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED' INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE' AND student.student_number=:number INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id WHERE period.status='ACTIVE' LIMIT 1");
    $statement->execute(['number' => $row['student_number']]);
    $id = $statement->fetchColumn();
    if ($id === false) {
        throw new RuntimeException($row['student_number'] . ': no current-semester Bookstore clearance was found.');
    }
    try {
        return clearBookstoreClearance($database, (int)$id, $staffUserId, $row['reference'], $row['remarks']) ? 'cleared' : 'already_cleared';
    } catch (RuntimeException $exception) {
        throw new RuntimeException($row['student_number'] . ': ' . $exception->getMessage(), 0, $exception);
    }
}

function importBookstoreWorkbook(PDO $database, int $staffUserId, array $rows): array
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
            $result[clearBookstoreFromWorkbook($database, $staffUserId, $row)]++;
        } catch (RuntimeException $exception) {
            $result['errors'][] = $exception->getMessage();
        }
    }
    return $result;
}
