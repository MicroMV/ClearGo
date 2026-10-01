<?php

declare(strict_types=1);

require_once __DIR__ . '/semester_functions.php';

const STUDENT_IMPORT_MAX_FILE_SIZE = 5 * 1024 * 1024;
const STUDENT_IMPORT_MAX_ROWS = 5000;
const STUDENT_IMPORT_MAX_XML_SIZE = 20 * 1024 * 1024;
const CLEAR_GO_INITIAL_PASSWORD = 'thelewiscollege';

/** @return array<int, array<int, string>> */
function readStudentImportFile(string $path, string $originalName): array
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        return readStudentCsv($path);
    }
    if ($extension === 'xlsx') {
        return readStudentXlsx($path);
    }

    throw new RuntimeException('Only .xlsx and .csv files are supported.');
}

/** @return array<int, array<int, string>> */
function readStudentCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The CSV file could not be read.');
    }

    try {
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            throw new RuntimeException('The uploaded file is empty.');
        }
        $delimiterCounts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delimiterCounts);
        $delimiter = (string) array_key_first($delimiterCounts);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(static fn($value): string => normalizeImportText((string) $value), $row);
            if (count($rows) > STUDENT_IMPORT_MAX_ROWS + 1) {
                throw new RuntimeException('The file exceeds the maximum of ' . STUDENT_IMPORT_MAX_ROWS . ' student rows.');
            }
        }
    } finally {
        fclose($handle);
    }

    return $rows;
}

/**
 * Minimal OOXML reader. It combines every worksheet containing the official student
 * headers and ignores summary worksheets.
 * @return array<int, array<int, string>>
 */
function readStudentXlsx(string $path): array
{
    $entries = readZipEntries($path, ['xl/sharedStrings.xml'], 'xl/worksheets/');
    $worksheetNames = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name) === 1));
    natsort($worksheetNames);
    if ($worksheetNames === []) {
        throw new RuntimeException('The workbook does not contain a readable worksheet.');
    }

    $sharedStrings = [];
    if (isset($entries['xl/sharedStrings.xml'])) {
        $sharedXml = loadImportXml($entries['xl/sharedStrings.xml'], 'shared strings');
        foreach ($sharedXml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $sharedStrings[] = normalizeImportText(implode('', array_map(static fn(SimpleXMLElement $part): string => (string) $part, $parts)));
        }
    }

    $combinedRows = [];
    $expectedPeriod = null;
    foreach ($worksheetNames as $worksheetName) {
        $sheetRows = parseStudentWorksheet($entries[$worksheetName], $sharedStrings);
        try {
            $layout = locateStudentImportHeader($sheetRows);
        } catch (RuntimeException) {
            continue;
        }
        $periodKey = ($layout['academic_year'] ?? '') . '|' . ($layout['semester'] ?? '');
        if ($expectedPeriod === null) {
            $expectedPeriod = $periodKey;
            $title = 'ACADEMIC YEAR ' . ($layout['academic_year'] ?? '') . ' ' . ($layout['semester'] ?? '') . ' SEMESTER';
            $combinedRows = [[$title], [], ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address']];
        } elseif ($periodKey !== $expectedPeriod) {
            throw new RuntimeException('Student worksheets contain different academic periods.');
        }
        foreach (array_slice($sheetRows, $layout['header_index'] + 1) as $row) {
            $map = $layout['header_map'];
            $studentRow = [
                '',
                normalizeImportText((string) ($row[$map['student_id']] ?? '')),
                normalizeImportText((string) ($row[$map['student_name']] ?? '')),
                normalizeImportText((string) ($row[$map['course_program']] ?? '')),
                normalizeImportText((string) ($row[$map['year_level']] ?? '')),
                normalizeImportText((string) ($row[$map['school_email']] ?? '')),
            ];
            if (count(array_filter(array_slice($studentRow, 1), static fn(string $value): bool => $value !== '')) === 0) {
                continue;
            }
            $combinedRows[] = $studentRow;
            if (count($combinedRows) > STUDENT_IMPORT_MAX_ROWS + 25) {
                throw new RuntimeException('The file exceeds the maximum of ' . STUDENT_IMPORT_MAX_ROWS . ' student rows.');
            }
        }
    }
    if ($combinedRows === []) {
        throw new RuntimeException('No worksheet contains the required student-record headers.');
    }
    return $combinedRows;
}

/** @param array<int,string> $sharedStrings @return array<int,array<int,string>> */
function parseStudentWorksheet(string $worksheetXml, array $sharedStrings): array
{
    $sheetXml = loadImportXml($worksheetXml, 'worksheet');
    $rows = [];
    foreach ($sheetXml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $rowNode) {
        $row = [];
        foreach ($rowNode->xpath('./*[local-name()="c"]') ?: [] as $cell) {
            $attributes = $cell->attributes();
            $reference = (string) ($attributes['r'] ?? '');
            $type = (string) ($attributes['t'] ?? '');
            $index = xlsxColumnIndex($reference);
            $value = '';
            if ($type === 'inlineStr') {
                $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                $value = implode('', array_map(static fn(SimpleXMLElement $part): string => (string) $part, $parts));
            } else {
                $values = $cell->xpath('./*[local-name()="v"]') ?: [];
                $value = isset($values[0]) ? (string) $values[0] : '';
                if ($type === 's' && ctype_digit($value)) {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
            }
            $row[$index] = normalizeImportText($value);
        }
        if ($row !== []) {
            $maxIndex = max(array_keys($row));
            $rows[] = array_map(static fn($value): string => (string) $value, array_replace(array_fill(0, $maxIndex + 1, ''), $row));
        }
    }
    return $rows;
}

/** @return array<string, string> */
function readZipEntries(string $path, array $exactNames, string $prefix): array
{
    $archive = file_get_contents($path);
    if ($archive === false || strlen($archive) < 22) {
        throw new RuntimeException('The XLSX file could not be read.');
    }
    $eocdOffset = strrpos($archive, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('The XLSX file is not a valid ZIP workbook.');
    }
    $eocd = unpack('vdisk/vstart/ventries_disk/ventries/Vsize/Voffset/vcomment', substr($archive, $eocdOffset + 4, 18));
    if ($eocd === false || $eocd['disk'] !== 0 || $eocd['start'] !== 0) {
        throw new RuntimeException('Multi-part XLSX files are not supported.');
    }

    $entries = [];
    $position = (int) $eocd['offset'];
    for ($i = 0; $i < (int) $eocd['entries']; $i++) {
        if (substr($archive, $position, 4) !== "PK\x01\x02") {
            throw new RuntimeException('The XLSX directory is damaged.');
        }
        $header = unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Vlocal', substr($archive, $position + 4, 42));
        if ($header === false) {
            throw new RuntimeException('The XLSX directory could not be parsed.');
        }
        $name = str_replace('\\', '/', substr($archive, $position + 46, $header['name']));
        $wanted = in_array($name, $exactNames, true) || str_starts_with($name, $prefix);
        if ($wanted) {
            if (($header['flags'] & 1) !== 0 || $header['uncompressed'] > STUDENT_IMPORT_MAX_XML_SIZE) {
                throw new RuntimeException('The XLSX contains an encrypted or oversized worksheet.');
            }
            $localOffset = (int) $header['local'];
            if (substr($archive, $localOffset, 4) !== "PK\x03\x04") {
                throw new RuntimeException('The XLSX contains a damaged worksheet entry.');
            }
            $local = unpack('vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname/vextra', substr($archive, $localOffset + 4, 26));
            $dataOffset = $localOffset + 30 + $local['name'] + $local['extra'];
            $compressed = substr($archive, $dataOffset, $header['compressed']);
            if ($header['method'] === 0) {
                $content = $compressed;
            } elseif ($header['method'] === 8) {
                $content = gzinflate($compressed);
            } else {
                throw new RuntimeException('The XLSX uses an unsupported compression method.');
            }
            if ($content === false || strlen($content) !== (int) $header['uncompressed']) {
                throw new RuntimeException('An XLSX worksheet could not be decompressed.');
            }
            $entries[$name] = $content;
        }
        $position += 46 + $header['name'] + $header['extra'] + $header['comment'];
    }

    return $entries;
}

function loadImportXml(string $xml, string $part): SimpleXMLElement
{
    $previous = libxml_use_internal_errors(true);
    $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($element === false) {
        throw new RuntimeException('The XLSX ' . $part . ' XML is invalid.');
    }
    return $element;
}

function xlsxColumnIndex(string $reference): int
{
    if (preg_match('/^([A-Z]+)/i', $reference, $matches) !== 1) {
        return 0;
    }
    $index = 0;
    foreach (str_split(strtoupper($matches[1])) as $character) {
        $index = ($index * 26) + (ord($character) - 64);
    }
    return max(0, $index - 1);
}

function normalizeImportText(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
    return trim(preg_replace('/\s+/u', ' ', $value) ?? trim($value));
}

/** @return array<string, int> */
function studentImportHeaderMap(array $headers): array
{
    $aliases = [
        'student_id' => ['student id', 'student number', 'student no', 'student no.'],
        'student_name' => ['student name', 'name', 'full name'],
        'school_email' => ['gsuite address', 'g suite address', 'school email', 'email', 'email address'],
        'course_program' => ['course/program', 'course program', 'course', 'program'],
        'year_level' => ['year level', 'year'],
    ];
    $map = [];
    foreach ($headers as $index => $header) {
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', normalizeImportText((string) $header)) ?? ''));
        foreach ($aliases as $canonical => $accepted) {
            $acceptedNormalized = array_map(static fn(string $item): string => trim(preg_replace('/[^a-z0-9]+/i', ' ', $item) ?? ''), $accepted);
            if (in_array($normalized, $acceptedNormalized, true) && !isset($map[$canonical])) {
                $map[$canonical] = (int) $index;
            }
        }
    }
    $missing = array_diff(array_keys($aliases), array_keys($map));
    if ($missing !== []) {
        throw new RuntimeException('Missing required column(s): ' . implode(', ', array_map(static fn(string $name): string => ucwords(str_replace('_', ' ', $name)), $missing)) . '.');
    }
    return $map;
}

/** @return array{header_index:int,header_map:array<string,int>,academic_year:?string,semester:?string} */
function locateStudentImportHeader(array $rows): array
{
    $academicYear = null;
    $semester = null;
    foreach (array_slice($rows, 0, 25, true) as $index => $row) {
        $rowText = normalizeImportText(implode(' ', array_map('strval', $row)));
        if (preg_match('/ACADEMIC\s+YEAR\s+(\d{4}\s*-\s*\d{4})\s+(1ST|2ND|SUMMER)(?:\s+SEMESTER)?/i', $rowText, $matches) === 1) {
            $academicYear = preg_replace('/\s+/', '', $matches[1]);
            $semester = strtoupper($matches[2]);
        }
        try {
            return [
                'header_index' => (int) $index,
                'header_map' => studentImportHeaderMap($row),
                'academic_year' => $academicYear,
                'semester' => $semester,
            ];
        } catch (RuntimeException) {
            // Title and blank rows before the actual column headings are expected.
        }
    }
    throw new RuntimeException('The worksheet must contain these headers: Student No., Full Name, Course, Year Level, and Gsuite Address.');
}

/** @return array{0:string,1:string} */
function splitStudentName(string $name): array
{
    $name = normalizeImportText($name);
    if (str_contains($name, ',')) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $name)), static fn(string $part): bool => $part !== ''));
        $lastName = (string) array_shift($parts);
        return [implode(' ', $parts), $lastName];
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    if (count($parts) <= 1) {
        return [$name, ''];
    }
    $lastName = (string) array_pop($parts);
    return [implode(' ', $parts), $lastName];
}

function parseStudentYearLevel(string $value): int
{
    $normalized = normalizedImportKey($value);
    $words = [
        'first' => 1,
        'one' => 1,
        'second' => 2,
        'two' => 2,
        'third' => 3,
        'three' => 3,
        'fourth' => 4,
        'four' => 4,
        'fifth' => 5,
        'five' => 5,
        'sixth' => 6,
        'six' => 6,
    ];
    if (preg_match('/\b([1-6])(?:st|nd|rd|th)?\b/i', $normalized, $matches) === 1) {
        return (int) $matches[1];
    }
    foreach ($words as $word => $year) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $normalized) === 1) {
            return $year;
        }
    }
    return 0;
}

/** @param array<string,array<string,mixed>> $organizations @param array<string,string> $knownCourses */
function inferStudentOrganization(string $course, array $organizations, array $knownCourses): ?array
{
    $courseKey = normalizedImportKey($course);
    $code = $knownCourses[$courseKey] ?? null;
    if ($code === null) {
        $rules = [
            'CCS' => '/\b(ACT|BSCS|BSIT|BSIS|COMPUTER|INFORMATION\s+TECHNOLOGY|APPDEV|DATA\s+ENGR|NETWORKING)\b/i',
            'CBE' => '/\b(BSBA|BSA|BSAIS|BSHM|BSTM|BSENTREP|BUSINESS|ACCOUNTANCY|HOSPITALITY|TOURISM|ENTREP|ENTREPRENEUR)\b/i',
            'CTE' => '/\b(BEED|BSED|BTVTED|TCP|EDUCATION|TEACHER)\b/i',
        ];
        foreach ($rules as $candidate => $pattern) {
            if (preg_match($pattern, $course) === 1) {
                $code = $candidate;
                break;
            }
        }
    }
    return $code !== null ? ($organizations[normalizedImportKey($code)] ?? null) : null;
}

function normalizedImportKey(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
}

/** @param array<string, string> $entries */
function createStoredZipArchive(array $entries): string
{
    $body = '';
    $directory = '';
    $offset = 0;
    foreach ($entries as $name => $content) {
        $crc = crc32($content);
        $size = strlen($content);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0)
            . $name . $content;
        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset)
            . $name;
        $body .= $local;
        $offset += strlen($local);
    }
    $count = count($entries);
    return $body . $directory
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);
}

function createStudentImportTemplateXlsx(string $periodTitle = 'ACADEMIC YEAR 2026-2027 1ST SEMESTER'): string
{
    require_once __DIR__ . '/../clearance/workbook_styles.php';
    $headers = ['#', 'Student No.', 'Full Name', 'Course', 'Year Level', 'Gsuite Address'];
    $example = ['1', 'C-2026-0889', 'Gogolin, Ella Victoria, Dawis', 'ACT - AppDev', 'First Year', 'ellavictoriagogolin@thelewiscollege.edu.ph'];
    $rows = '';
    $templateRows = [1 => [$periodTitle], 2 => ['Search by name or ID using the Full Name or Student No. header filter Search box, or Ctrl+F. Replace the example with student records.'], 3 => $headers, 4 => $example];
    foreach ($templateRows as $rowNumber => $values) {
        $cells = '';
        foreach ($values as $column => $value) {
            $reference = chr(65 + $column) . $rowNumber;
            $style = $rowNumber === 1 ? 1 : ($rowNumber === 2 ? 2 : ($rowNumber === 3 ? 3 : ($rowNumber % 2 === 0 ? 4 : 5)));
            $cells .= '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t>'
                . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
        }
        $height = $rowNumber === 1 ? 32 : ($rowNumber === 2 ? 60 : 30);
        $rows .= '<row r="' . $rowNumber . '" ht="' . $height . '" customHeight="1">' . $cells . '</row>';
    }

    return createStoredZipArchive([
        'xl/styles.xml' => officeWorkbookStylesXml(),
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="40" customWidth="1"/><col min="4" max="4" width="25" customWidth="1"/><col min="5" max="5" width="16" customWidth="1"/><col min="6" max="6" width="48" customWidth="1"/></cols><sheetData>' . $rows . '</sheetData><autoFilter ref="A3:F4"/><mergeCells count="2"><mergeCell ref="A1:F1"/><mergeCell ref="A2:F2"/></mergeCells><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9"/></worksheet>',
    ]);
}

/**
 * @return array{rows:array<int,array<string,mixed>>,valid_count:int,error_count:int,added_count:int,updated_count:int}
 */
function validateStudentImport(PDO $database, array $rawRows): array
{
    if ($rawRows === []) {
        throw new RuntimeException('The uploaded file is empty.');
    }
    $layout = locateStudentImportHeader($rawRows);
    $headerMap = $layout['header_map'];
    $rawRows = array_slice($rawRows, $layout['header_index'] + 1);

    $periodStatement = $database->prepare("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' ORDER BY id LIMIT 2");
    $periodStatement->execute();
    $activePeriods = $periodStatement->fetchAll();
    if (count($activePeriods) !== 1) {
        throw new RuntimeException('Exactly one active academic period is required before importing students.');
    }
    $activePeriod = $activePeriods[0];
    if ($layout['academic_year'] !== null && ($layout['academic_year'] !== $activePeriod['school_year'] || $layout['semester'] !== $activePeriod['semester'])) {
        throw new RuntimeException('The workbook academic year/semester does not match the active academic period.');
    }

    $organizations = [];
    $organizationStatement = $database->prepare('SELECT id, code, name FROM organizations WHERE status = :status');
    $organizationStatement->execute(['status' => 'ACTIVE']);
    foreach ($organizationStatement->fetchAll() as $organization) {
        $organizations[normalizedImportKey((string) $organization['code'])] = $organization;
        $organizations[normalizedImportKey((string) $organization['name'])] = $organization;
    }

    $knownCourses = [];
    $ambiguousCourses = [];
    $courseStatement = $database->query('SELECT DISTINCT s.program, o.code FROM students s INNER JOIN organizations o ON o.id=s.organization_id');
    foreach ($courseStatement->fetchAll() as $courseRow) {
        $courseKey = normalizedImportKey((string) $courseRow['program']);
        if (isset($knownCourses[$courseKey]) && $knownCourses[$courseKey] !== $courseRow['code']) {
            $ambiguousCourses[$courseKey] = true;
        }
        $knownCourses[$courseKey] = (string) $courseRow['code'];
    }
    foreach (array_keys($ambiguousCourses) as $courseKey) {
        unset($knownCourses[$courseKey]);
    }

    $studentsByNumber = [];
    $studentStatement = $database->query(
        'SELECT s.id, s.user_id, s.student_number, u.email
         FROM students s INNER JOIN users u ON u.id = s.user_id'
    );
    foreach ($studentStatement->fetchAll() as $student) {
        $studentsByNumber[normalizedImportKey((string) $student['student_number'])] = $student;
    }
    $usersByEmail = [];
    foreach ($database->query('SELECT id, email, role FROM users')->fetchAll() as $account) {
        $usersByEmail[normalizedImportKey((string) $account['email'])] = $account;
    }

    $rows = [];
    foreach ($rawRows as $offset => $rawRow) {
        $values = [];
        foreach ($headerMap as $field => $index) {
            $values[$field] = normalizeImportText((string) ($rawRow[$index] ?? ''));
        }
        if (count(array_filter($values, static fn(string $value): bool => $value !== '')) === 0) {
            continue;
        }

        [$firstName, $lastName] = splitStudentName($values['student_name']);
        $yearLevel = parseStudentYearLevel($values['year_level']);
        $organization = inferStudentOrganization($values['course_program'], $organizations, $knownCourses);
        $existing = $studentsByNumber[normalizedImportKey($values['student_id'])] ?? null;
        $errors = [];
        $warnings = [];
        if ($values['student_id'] === '') {
            $errors[] = 'Missing Student ID.';
        }
        if ($values['student_name'] === '') {
            $errors[] = 'Missing student name.';
        }
        if (
            !filter_var($values['school_email'], FILTER_VALIDATE_EMAIL)
            || !str_ends_with(strtolower($values['school_email']), '@thelewiscollege.edu.ph')
        ) {
            $errors[] = 'Gsuite Address must use @thelewiscollege.edu.ph.';
        }
        if ($values['course_program'] === '') {
            $errors[] = 'Missing course/program.';
        }
        if ($yearLevel < 1 || $yearLevel > 6) {
            $errors[] = 'Year Level must be First Year through Sixth Year.';
        }
        if ($organization === null) {
            $errors[] = 'The Course could not be assigned to a college. Add a recognized course name.';
        }
        $emailOwner = $usersByEmail[normalizedImportKey($values['school_email'])] ?? null;
        if ($emailOwner !== null && ($existing === null || (int) $emailOwner['id'] !== (int) $existing['user_id'])) {
            $errors[] = 'School email already belongs to another account.';
        }
        if ($existing !== null) {
            $warnings[] = 'Student ID already exists; basic information will be updated.';
        }

        $rows[] = [
            'row_number' => $layout['header_index'] + $offset + 2,
            'student_id' => $values['student_id'],
            'student_name' => $values['student_name'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'school_email' => strtolower($values['school_email']),
            'course_program' => $values['course_program'],
            'year_level' => $yearLevel,
            'organization' => $organization === null ? '' : (string) $organization['code'],
            'organization_id' => $organization === null ? null : (int) $organization['id'],
            'organization_label' => $organization === null ? 'Unrecognized course' : $organization['name'] . ' (' . $organization['code'] . ')',
            'existing_student_id' => $existing === null ? null : (int) $existing['id'],
            'existing_user_id' => $existing === null ? null : (int) $existing['user_id'],
            'action' => $existing === null ? 'ADD' : 'UPDATE',
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
    if ($rows === []) {
        throw new RuntimeException('The uploaded file contains no student records.');
    }

    $idCounts = [];
    $emailCounts = [];
    foreach ($rows as $row) {
        if ($row['student_id'] !== '') {
            $idCounts[normalizedImportKey($row['student_id'])] = ($idCounts[normalizedImportKey($row['student_id'])] ?? 0) + 1;
        }
        if ($row['school_email'] !== '') {
            $emailCounts[normalizedImportKey($row['school_email'])] = ($emailCounts[normalizedImportKey($row['school_email'])] ?? 0) + 1;
        }
    }
    foreach ($rows as &$row) {
        if ($row['student_id'] !== '' && ($idCounts[normalizedImportKey($row['student_id'])] ?? 0) > 1) {
            $row['errors'][] = 'Duplicate Student ID within the file.';
        }
        if ($row['school_email'] !== '' && ($emailCounts[normalizedImportKey($row['school_email'])] ?? 0) > 1) {
            $row['errors'][] = 'Duplicate school email within the file.';
        }
    }
    unset($row);

    $valid = array_filter($rows, static fn(array $row): bool => $row['errors'] === []);
    return [
        'rows' => $rows,
        'valid_count' => count($valid),
        'error_count' => count($rows) - count($valid),
        'added_count' => count(array_filter($valid, static fn(array $row): bool => $row['action'] === 'ADD')),
        'updated_count' => count(array_filter($valid, static fn(array $row): bool => $row['action'] === 'UPDATE')),
        'academic_period' => $activePeriod['school_year'] . ' ' . $activePeriod['semester'] . ' SEMESTER',
    ];
}

/** @return array{added:int,updated:int,skipped:int,errors:int} */
function importValidatedStudents(PDO $database, array $preview, ?int $registrarUserId = null): array
{
    $rows = $preview['rows'] ?? [];
    $added = 0;
    $updated = 0;
    $skipped = 0;
    $semesterRemoved = 0;
    $importedStudentIds = [];
    $database->beginTransaction();
    try {
        $activePeriod = null;
        $registrarStationId = null;
        $audit = null;
        if ($registrarUserId !== null) {
            $periodQuery = $database->query("SELECT id,school_year,semester,clearance_status FROM academic_periods WHERE status='ACTIVE' ORDER BY id LIMIT 2");
            $periods = $periodQuery->fetchAll();
            if (count($periods) !== 1) {
                throw new RuntimeException('Exactly one active semester is required.');
            }
            $activePeriod = $periods[0];
            if ($activePeriod['clearance_status'] !== 'PREPARING') {
                throw new RuntimeException('The enrollment roster can only be replaced before semester clearance is opened.');
            }
            $registrarStationId = $database->query("SELECT id FROM stations WHERE code='REGISTRAR' LIMIT 1")->fetchColumn();
            $audit = $database->prepare(
                "INSERT INTO audit_logs
                    (user_id,action,station_id,student_id,description,new_status)
                 VALUES
                    (:user_id,:action,:station_id,:student_id,:description,'ACTIVE')"
            );
        }
        $updateUser = $database->prepare(
            "UPDATE users SET email = :email, role = 'STUDENT', status = 'ACTIVE' WHERE id = :id"
        );
        $updateStudent = $database->prepare(
            "UPDATE students SET first_name = :first_name, last_name = :last_name, program = :program,
                    year_level = :year_level, organization_id = :organization_id, status = 'ACTIVE'
             WHERE id = :id"
        );
        $insertUser = $database->prepare(
            "INSERT INTO users (email, password_hash, role, status)
             VALUES (:email, :password_hash, 'STUDENT', 'ACTIVE')"
        );
        $insertStudent = $database->prepare(
            "INSERT INTO students
                (user_id, student_number, first_name, last_name, program, year_level, section, organization_id, status)
             VALUES
                (:user_id, :student_number, :first_name, :last_name, :program, :year_level, :section, :organization_id, 'ACTIVE')"
        );
        $initialPasswordHash = password_hash(CLEAR_GO_INITIAL_PASSWORD, PASSWORD_DEFAULT);

        foreach ($rows as $row) {
            if (($row['errors'] ?? []) !== []) {
                $skipped++;
                continue;
            }
            if ($row['action'] === 'UPDATE') {
                $updateUser->execute(['email' => $row['school_email'], 'id' => $row['existing_user_id']]);
                $updateStudent->execute([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'program' => $row['course_program'],
                    'year_level' => $row['year_level'],
                    'organization_id' => $row['organization_id'],
                    'id' => $row['existing_student_id'],
                ]);
                $updated++;
                $studentId = (int) $row['existing_student_id'];
            } else {
                $insertUser->execute([
                    'email' => $row['school_email'],
                    'password_hash' => $initialPasswordHash,
                ]);
                $insertStudent->execute([
                    'user_id' => (int) $database->lastInsertId(),
                    'student_number' => $row['student_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'program' => $row['course_program'],
                    'year_level' => $row['year_level'],
                    'section' => 'N/A',
                    'organization_id' => $row['organization_id'],
                ]);
                $studentId = (int) $database->lastInsertId();
                $added++;
            }
            $importedStudentIds[] = $studentId;
            if ($registrarUserId !== null && $audit instanceof PDOStatement) {
                $audit->execute([
                    'user_id' => $registrarUserId,
                    'action' => $row['action'] === 'ADD' ? 'STUDENT_ACCOUNT_CREATED' : 'STUDENT_ACCOUNT_UPDATED',
                    'station_id' => $registrarStationId,
                    'student_id' => $studentId,
                    'description' => 'Registrar ' . strtolower($row['action'] === 'ADD' ? 'created' : 'updated') . ' the student account for ' . $row['student_id'] . '.',
                ]);
            }
        }

        if ($registrarUserId !== null && $activePeriod !== null) {
            $enroll = $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status,enrolled_by,enrolled_at,removed_by,removed_at) VALUES (:period_id,:student_id,'ENROLLED',:user_id,NOW(),NULL,NULL) ON DUPLICATE KEY UPDATE status='ENROLLED',enrolled_by=VALUES(enrolled_by),enrolled_at=NOW(),removed_by=NULL,removed_at=NULL");
            foreach (array_unique($importedStudentIds) as $studentId) {
                $enroll->execute(['period_id' => $activePeriod['id'], 'student_id' => $studentId, 'user_id' => $registrarUserId]);
            }
            if ($importedStudentIds !== []) {
                $placeholders = implode(',', array_fill(0, count($importedStudentIds), '?'));
                $toRemove = $database->prepare("SELECT student_id FROM semester_enrollments WHERE academic_period_id=? AND status='ENROLLED' AND student_id NOT IN ({$placeholders})");
                $toRemove->execute(array_merge([(int) $activePeriod['id']], array_values(array_unique($importedStudentIds))));
                $removedIds = array_map('intval', $toRemove->fetchAll(PDO::FETCH_COLUMN));
                if ($removedIds !== []) {
                    $removePlaceholders = implode(',', array_fill(0, count($removedIds), '?'));
                    $remove = $database->prepare("UPDATE semester_enrollments SET status='REMOVED',removed_by=?,removed_at=NOW() WHERE academic_period_id=? AND student_id IN ({$removePlaceholders})");
                    $remove->execute(array_merge([$registrarUserId, (int) $activePeriod['id']], $removedIds));
                    $semesterRemoved = count($removedIds);
                    $auditRemoved = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status) VALUES (:user_id,'STUDENT_REMOVED_FROM_SEMESTER',:station_id,:student_id,:description,'ENROLLED','REMOVED')");
                    foreach ($removedIds as $removedId) {
                        $auditRemoved->execute(['user_id' => $registrarUserId, 'station_id' => $registrarStationId, 'student_id' => $removedId, 'description' => 'Registrar removed the student from the current semester roster because the student was not present in the official import.']);
                    }
                }
            }
            prepareSemesterClearanceRecords($database, (int) $activePeriod['id']);
            $resetReadiness = $database->prepare("UPDATE office_period_readiness SET status='NOT_READY',ready_by=NULL,ready_at=NULL,notes=NULL WHERE academic_period_id=:period_id");
            $resetReadiness->execute(['period_id' => $activePeriod['id']]);
        }
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }

    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $skipped, 'removed_from_semester' => $semesterRemoved];
}
