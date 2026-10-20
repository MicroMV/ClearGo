<?php
declare(strict_types=1);

require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/report_workbook.php';

const CCS_COMPLETION_MAX_FILE_SIZE = 5 * 1024 * 1024;
const CCS_COMPLETION_MAX_ROWS = 10000;

function ccsCompletionImportContext(PDO $database, int $deanUserId, bool $lock = false): array
{
    $dean = deanAssignment($database, $deanUserId);
    if ($dean['organization_code'] !== 'CCS') { throw new RuntimeException('Only the assigned CCS Dean can manage requirements completion lists.'); }
    $query = $database->query("SELECT id,school_year,semester,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 2" . ($lock ? ' FOR UPDATE' : ''));
    $periods = $query->fetchAll();
    if (count($periods) !== 1 || !in_array($periods[0]['clearance_status'], ['PREPARING','OPEN'], true)) {
        throw new RuntimeException('Requirements completion lists are available only during preparation or open clearance.');
    }
    return $periods[0] + ['organization_id' => (int) $dean['organization_id']];
}

function ccsCompletionRosterSql(): string
{
    return "SELECT DISTINCT student.student_number,student.first_name,student.middle_name,student.last_name,student.program,student.year_level,clearance.id AS clearance_id,record.id AS clearance_station_id,record.status AS dean_status
        FROM academic_periods period
        INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
        INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE'
        INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
        INNER JOIN stations station ON station.code='DEAN'
        INNER JOIN clearance_stations record ON record.clearance_id=clearance.id AND record.station_id=station.id
        WHERE period.id=:period_id AND student.organization_id=:organization_id";
}

function createCcsCompletionWorkbook(PDO $database, int $deanUserId): string
{
    $context = ccsCompletionImportContext($database, $deanUserId);
    $query = $database->prepare(ccsCompletionRosterSql() . " AND record.status<>'APPROVED' ORDER BY student.year_level,student.last_name,student.first_name,student.student_number");
    $query->execute(['period_id' => $context['id'], 'organization_id' => $context['organization_id']]);
    $yearRows = [1 => [], 2 => [], 3 => [], 4 => []];
    foreach ($query->fetchAll() as $student) {
        $yearRows[(int) $student['year_level']][] = [$student['student_number'], trim($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']), $student['program'], '', $context['school_year'], $context['semester']];
    }
    ksort($yearRows, SORT_NUMERIC);
    $sheets = [];
    foreach ($yearRows as $year => $rows) {
        $sheets[] = ['name' => 'Year ' . $year, 'rows' => $rows, 'options' => [
            'instructions' => 'Completed verifies the applicable Dean requirements. Year 4: Faculty Evaluation in first semester; Application for Graduation in second semester. Blanks count as Incomplete. Keep School Year and Semester unchanged. Clearance approval is separate.',
            'dropdowns' => [['column' => 4, 'values' => ['Completed','Incomplete']]],
        ]];
    }
    return createClearanceReportXlsx('CCS Requirements Completion',
        $context['semester'] . ' Semester A.Y. ' . $context['school_year'],
        ['Student Number','Full Name','Course','Requirements Completion','School Year','Semester'], $sheets);
}

function readCcsCompletionWorkbook(string $path): array
{
    if (!is_file($path) || filesize($path) < 1 || filesize($path) > CCS_COMPLETION_MAX_FILE_SIZE) { throw new RuntimeException('The completion workbook must be no larger than 5 MB.'); }
    $entries = readZipEntries($path, ['xl/sharedStrings.xml'], 'xl/worksheets/');
    $sheets = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name) === 1));
    if ($sheets === []) { throw new RuntimeException('The completion workbook does not contain a readable worksheet.'); }
    natsort($sheets);
    $strings = [];
    if (isset($entries['xl/sharedStrings.xml'])) {
        foreach (loadImportXml($entries['xl/sharedStrings.xml'], 'shared strings')->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $strings[] = implode('', array_map(static fn(SimpleXMLElement $part): string => (string) $part, $item->xpath('.//*[local-name()="t"]') ?: []));
        }
    }
    $result = [];
    foreach ($sheets as $sheet) {
        $xml = loadImportXml($entries[$sheet], 'completion worksheet');
        if (($xml->xpath('//*[local-name()="f"]') ?: []) !== []) { throw new RuntimeException('Enter completion values directly; formulas are not accepted.'); }
        $rows = parseStudentWorksheet($entries[$sheet], $strings);
        $layouts = [
            ['student number','full name','course','requirements completion','school year','semester'],
            ['student number','full name','course','evaluation completed','school year','semester'],
            ['student number','full name','evaluation completed','school year','semester'],
            ['student number','evaluation completed','school year','semester'],
        ];
        $headerIndex = null; $headers = [];
        foreach (array_slice($rows, 0, 25, true) as $index => $row) {
            $normalized = array_map('strtolower', array_map('trim', $row));
            if (in_array($normalized, $layouts, true)) {
                $headerIndex = $index; $headers = $normalized; break;
            }
        }
        if ($headerIndex === null) { throw new RuntimeException('Use the downloaded CCS completion template on every year-level sheet.'); }
        $columnCount = count($headers);
        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]/*[local-name()="c"]') ?: [] as $cell) {
            if (xlsxColumnIndex((string) ($cell['r'] ?? '')) >= $columnCount) { throw new RuntimeException('Use only the columns in the completion template.'); }
        }
        $completionColumn = array_search('requirements completion', $headers, true);
        if ($completionColumn === false) { $completionColumn = array_search('evaluation completed', $headers, true); }
        $nameColumn = array_search('full name', $headers, true);
        $courseColumn = array_search('course', $headers, true);
        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            if (implode('', $row) === '') { continue; }
            $result[] = ['student_number' => trim($row[0] ?? ''), 'full_name' => $nameColumn !== false ? trim($row[$nameColumn] ?? '') : '', 'course' => $courseColumn !== false ? trim($row[$courseColumn] ?? '') : '', 'completed' => trim($row[$completionColumn] ?? ''), 'school_year' => trim($row[$completionColumn + 1] ?? ''), 'semester' => strtoupper(trim($row[$completionColumn + 2] ?? '')), 'row' => $headerIndex + $offset + 2];
            if (count($result) > CCS_COMPLETION_MAX_ROWS) { throw new RuntimeException('Import up to 10,000 rows per file across all sheets. Larger rosters can use multiple imports.'); }
        }
    }
    if ($result === []) { throw new RuntimeException('The completion list contains no student rows.'); }
    return $result;
}

/** Validate the entire list before writing anything. Import never signs clearance. */
function importCcsCompletionWorkbook(PDO $database, int $deanUserId, int $periodId, array $rows): array
{
    if ($rows === [] || count($rows) > CCS_COMPLETION_MAX_ROWS) { throw new RuntimeException('The completion list has an invalid number of rows.'); }
    $database->beginTransaction();
    try {
        $context = ccsCompletionImportContext($database, $deanUserId, true);
        if ((int) $context['id'] !== $periodId) { throw new RuntimeException('The active semester changed. Download its current completion template.'); }
        $find = $database->prepare(ccsCompletionRosterSql() . ' AND student.student_number=:student_number FOR UPDATE');
        $seen = []; $validated = []; $result = ['completed' => 0, 'not_completed' => 0, 'already_cleared' => 0];
        foreach ($rows as $index => $row) {
            $number = trim((string) ($row['student_number'] ?? '')); $key = strtoupper($number); $line = (int) ($row['row'] ?? ($index + 1));
            if ($number === '' || strlen($number) > 100 || isset($seen[$key])) { throw new RuntimeException('Missing or duplicate student number at row ' . $line . '.'); }
            $seen[$key] = true;
            if (($row['school_year'] ?? '') !== $context['school_year'] || ($row['semester'] ?? '') !== $context['semester']) { throw new RuntimeException('Row ' . $line . ' belongs to a different semester. Use the current template.'); }
            $value = strtoupper(trim((string) ($row['completed'] ?? '')));
            if (!in_array($value, ['', 'COMPLETED', 'INCOMPLETE', 'YES', 'NO'], true)) { throw new RuntimeException('Choose Completed or Incomplete at row ' . $line . '.'); }
            $find->execute(['period_id' => $periodId, 'organization_id' => $context['organization_id'], 'student_number' => $number]);
            $student = $find->fetch();
            if ($student === false) { throw new RuntimeException('Row ' . $line . ' is not an enrolled CCS student with a current clearance record.'); }
            $validated[] = [$student, $value];
        }
        $save = $database->prepare("INSERT INTO ccs_evaluation_completions(clearance_id,status,recorded_by,recorded_at) VALUES (:clearance_id,:status,:user_id,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by),recorded_at=NOW()");
        $scope = ccsCompletionRequirementScopeSql('requirement', 'student', 'period');
        $requirements = $database->prepare("INSERT INTO requirement_records(clearance_station_id,requirement_id,status,verified_by,verified_at,remarks)
            SELECT record.id,requirement.id,:status,:user_id,CASE WHEN :completed=1 THEN NOW() ELSE NULL END,:remarks
            FROM clearances clearance
            INNER JOIN students student ON student.id=clearance.student_id
            INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
            INNER JOIN clearance_stations record ON record.clearance_id=clearance.id
            INNER JOIN stations station ON station.id=record.station_id AND station.code='DEAN'
            INNER JOIN requirements requirement ON requirement.station_id=station.id
            WHERE clearance.id=:clearance_id AND ({$scope})
            ON DUPLICATE KEY UPDATE status=VALUES(status),verified_by=VALUES(verified_by),verified_at=VALUES(verified_at),remarks=VALUES(remarks)");
        $audit = $database->prepare("INSERT INTO audit_logs(user_id,action,station_id,student_id,description,new_status)
            SELECT :user_id,'CCS_REQUIREMENTS_COMPLETION_IMPORTED',station.id,clearance.student_id,:description,:status FROM clearances clearance CROSS JOIN stations station WHERE clearance.id=:clearance_id AND station.code='DEAN'");
        foreach ($validated as [$student, $value]) {
            if ($student['dean_status'] === 'APPROVED') { $result['already_cleared']++; continue; }
            $completed = in_array($value, ['COMPLETED', 'YES'], true);
            $save->execute(['clearance_id' => $student['clearance_id'], 'status' => $completed ? 'COMPLETED' : 'NOT_COMPLETED', 'user_id' => $deanUserId]);
            $requirements->execute(['status' => $completed ? 'VERIFIED' : 'NOT_STARTED', 'user_id' => $completed ? $deanUserId : null, 'completed' => $completed ? 1 : 0, 'remarks' => $completed ? 'Applicable Dean requirements confirmed complete by the CCS Dean through the requirements completion list.' : 'Applicable Dean requirements marked incomplete on the requirements completion list.', 'clearance_id' => $student['clearance_id']]);
            if ($completed && $student['dean_status'] === 'RETURNED') {
                $database->prepare("UPDATE clearance_stations SET status='PENDING',returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=? AND status='RETURNED'")->execute([$student['clearance_station_id']]);
            }
            $audit->execute(['user_id' => $deanUserId, 'description' => 'CCS Dean confirmed the applicable semester requirements through an Excel list; clearance approval remains separate.', 'status' => $completed ? 'COMPLETED' : 'NOT_COMPLETED', 'clearance_id' => $student['clearance_id']]);
            $result[$completed ? 'completed' : 'not_completed']++;
        }
        $database->commit(); return $result;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}
