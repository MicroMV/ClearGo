<?php

declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../clearance/workbook_styles.php';
require_once __DIR__ . '/../clearance/remarks_functions.php';

const CLINIC_WORKBOOK_MAX_FILE_SIZE = 5 * 1024 * 1024;
const CLINIC_WORKBOOK_MAX_ROWS = 5000;

function clinicWorkbookStudents(PDO $database): array
{
    $statement = $database->query(
        "SELECT student.student_number,student.first_name,student.last_name,student.program,
                student.year_level,organization.code AS organization_code,
                medical.status AS medical_status,clinic_record.office_remarks AS remarks,
                clinic_record.status AS clinic_status
         FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
         INNER JOIN organizations organization ON organization.id=student.organization_id
            AND organization.code IN ('CCS','CBE','CTE')
         LEFT JOIN medical_requirements medical ON medical.student_id=student.id AND medical.academic_period_id=period.id
         LEFT JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         LEFT JOIN stations clinic_station ON clinic_station.code='CLINIC'
         LEFT JOIN clearance_stations clinic_record ON clinic_record.clearance_id=clearance.id
            AND clinic_record.station_id=clinic_station.id
         WHERE period.status='ACTIVE'
         ORDER BY organization.code,student.last_name,student.first_name,student.student_number"
    );
    return $statement->fetchAll();
}

function clinicWorksheetXml(string $organizationCode, array $students, string $periodLabel): string
{
    $rows = [
        [$organizationCode . ' CLINIC CLEARANCE - ' . $periodLabel],
        ['Search by name or ID using the Full Name or Student Number header filter Search box, or Ctrl+F. Has Medical Record shows the current ClearGo record. Change Clearance Status to CLEARED only after the student has completed all clinic requirements. Student Number must not be changed. Remarks are saved and shown to the student even when clearance is pending.'],
        ['Student Number', 'Full Name', 'Course', 'Year Level', 'Has Medical Record', 'Clearance Status', 'Remarks'],
    ];
    foreach ($students as $student) {
        $status = $student['clinic_status'] === 'APPROVED' ? 'CLEARED' : 'PENDING';
        $rows[] = [
            $student['student_number'],
            $student['last_name'] . ', ' . $student['first_name'],
            $student['program'],
            'Year ' . (int) $student['year_level'],
            $student['medical_status'] === 'VERIFIED' ? 'YES' : 'NO',
            $status,
            (string) ($student['remarks'] ?? ''),
        ];
    }

    $xmlRows = '';
    foreach ($rows as $rowIndex => $values) {
        $rowNumber = $rowIndex + 1;
        $cells = '';
        foreach ($values as $columnIndex => $value) {
            $column = '';
            $number = $columnIndex + 1;
            while ($number > 0) {
                $number--;
                $column = chr(65 + ($number % 26)) . $column;
                $number = intdiv($number, 26);
            }
            $style = $rowNumber === 1 ? 1 : ($rowNumber === 2 ? 2 : ($rowNumber === 3 ? 3 : ($rowNumber % 2 === 0 ? 4 : 5)));
            $cells .= '<c r="' . $column . $rowNumber . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
        }
        $height = $rowNumber === 1 ? 32 : ($rowNumber === 2 ? 60 : 30);
        $xmlRows .= '<row r="' . $rowNumber . '" ht="' . $height . '" customHeight="1">' . $cells . '</row>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'
        . '<col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/>'
        . '<col min="3" max="3" width="25" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/>'
        . '<col min="5" max="6" width="20" customWidth="1"/><col min="7" max="7" width="40" customWidth="1"/></cols><sheetData>' . $xmlRows
        . '</sheetData><autoFilter ref="A3:G' . max(3, count($rows)) . '"/><mergeCells count="2"><mergeCell ref="A1:G1"/><mergeCell ref="A2:G2"/></mergeCells>'
        . '<dataValidations count="2"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorStyle="stop" errorTitle="Invalid medical record status" error="Choose YES or NO." sqref="E4:E' . max(4, count($rows)) . '"><formula1>"YES,NO"</formula1></dataValidation>'
        . '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorStyle="stop" errorTitle="Invalid clinic status" error="Choose CLEARED or PENDING." sqref="F4:F' . max(4, count($rows)) . '"><formula1>"CLEARED,PENDING"</formula1></dataValidation></dataValidations>'
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9"/></worksheet>';
}

function createClinicWorkbookXlsx(PDO $database): string
{
    $period = $database->query("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $semester = ['1ST' => 'FIRST SEMESTER', '2ND' => 'SECOND SEMESTER', 'SUMMER' => 'SUMMER'][$period['semester']] ?? $period['semester'];
    $periodLabel = $semester . ' A.Y. ' . $period['school_year'];
    $grouped = ['CCS' => [], 'CBE' => [], 'CTE' => []];
    foreach (clinicWorkbookStudents($database) as $student) {
        $grouped[$student['organization_code']][] = $student;
    }

    $sheetEntries = [];
    $sheetDeclarations = '';
    $sheetRelationships = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $contentOverrides = '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach (array_keys($grouped) as $index => $code) {
        $sheetNumber = $index + 1;
        $sheetEntries['xl/worksheets/sheet' . $sheetNumber . '.xml'] = clinicWorksheetXml($code, $grouped[$code], $periodLabel);
        $sheetDeclarations .= '<sheet name="' . $code . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        $sheetRelationships .= '<Relationship Id="rId' . $sheetNumber . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNumber . '.xml"/>';
        $contentOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetNumber . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    return createStoredZipArchive(array_merge([
        'xl/styles.xml' => officeWorkbookStylesXml(),
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $contentOverrides . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetDeclarations . '</sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $sheetRelationships . '</Relationships>',
    ], $sheetEntries));
}

/** @return array<int,array{student_number:string,status:string,remarks:string}> */
function readClinicWorkbook(string $path): array
{
    $entries = readZipEntries($path, ['xl/sharedStrings.xml'], 'xl/worksheets/');
    $sharedStrings = [];
    if (isset($entries['xl/sharedStrings.xml'])) {
        $xml = loadImportXml($entries['xl/sharedStrings.xml'], 'shared strings');
        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $sharedStrings[] = implode('', array_map(static fn(SimpleXMLElement $part): string => (string) $part, $parts));
        }
    }
    $worksheetNames = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name) === 1));
    natsort($worksheetNames);
    if (count($worksheetNames) !== 3) {
        throw new RuntimeException('Use the Clinic workbook with its CCS, CBE, and CTE sheets.');
    }
    $result = [];
    foreach ($worksheetNames as $worksheetName) {
        $rows = parseStudentWorksheet($entries[$worksheetName], $sharedStrings, true);
        $headerIndex = null;
        $map = [];
        foreach ($rows as $index => $row) {
            $normalized = array_map(static fn(string $value): string => normalizedImportKey(rtrim($value, '.')), $row);
            foreach ($normalized as $column => $value) {
                if (in_array($value, ['student number', 'student no'], true)) {
                    $map['student_number'] = $column;
                }
                if (in_array($value, ['clearance status', 'clinic status'], true)) {
                    $map['status'] = $column;
                }
                if ($value === 'remarks') {
                    $map['remarks'] = $column;
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
            $result[] = [
                'student_number' => $studentNumber,
                'status' => strtoupper(normalizeImportText((string) ($row[$map['status']] ?? ''))),
                'remarks' => isset($map['remarks']) ? trim((string) ($row[$map['remarks']] ?? '')) : null,
            ];
            if (count($result) > CLINIC_WORKBOOK_MAX_ROWS) {
                throw new RuntimeException('The workbook exceeds 5,000 student rows.');
            }
        }
    }
    if ($result === []) {
        throw new RuntimeException('No Clinic template rows were found in the workbook.');
    }
    return $result;
}

function clearClinicFromWorkbook(PDO $database, int $staffUserId, string $studentNumber, string $remarks): string
{
    $statement = $database->prepare(
        "SELECT clearance.id
         FROM academic_periods period
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
            AND student.student_number=:student_number
         INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         WHERE period.status='ACTIVE' LIMIT 1"
    );
    $statement->execute(['student_number' => $studentNumber]);
    $clearanceId = $statement->fetchColumn();
    if ($clearanceId === false) {
        throw new RuntimeException($studentNumber . ': no current-semester Clinic clearance was found.');
    }
    try {
        return clearClinicClearance($database, (int) $clearanceId, $staffUserId, $remarks)
            ? 'cleared' : 'already_cleared';
    } catch (RuntimeException $exception) {
        throw new RuntimeException($studentNumber . ': ' . $exception->getMessage(), 0, $exception);
    }
}

function importClinicWorkbook(PDO $database, int $staffUserId, array $rows): array
{
    $seen = [];
    $result = ['cleared' => 0, 'already_cleared' => 0, 'skipped' => 0, 'remarks_updated' => 0, 'errors' => []];
    foreach ($rows as $row) {
        $key = strtoupper(trim($row['student_number']));
        if ($key === '' || isset($seen[$key])) {
            $result['skipped']++;
            continue;
        }
        $seen[$key] = true;
        if ($row['status'] !== 'CLEARED' && trim((string) ($row['remarks'] ?? '')) === '') {
            $result['skipped']++;
            continue;
        }
        try {
            $remarksChanged = saveWorkbookOfficeRemarks($database, $staffUserId, 'CLINIC_STAFF', $row['student_number'], $row['remarks'] ?? null);
            if ($remarksChanged) { $result['remarks_updated']++; }
            if ($row['status'] !== 'CLEARED') {
                if (!$remarksChanged) { $result['skipped']++; }
                continue;
            }
            $outcome = clearClinicFromWorkbook($database, $staffUserId, $row['student_number'], (string) ($row['remarks'] ?? ''));
            $result[$outcome]++;
        } catch (RuntimeException $exception) {
            $result['errors'][] = $exception->getMessage();
        }
    }
    return $result;
}
