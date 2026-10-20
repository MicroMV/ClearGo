<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../library/workbook_functions.php';

function libraryWorkbookCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = getDatabaseConnection();
$workbook = createLibraryWorkbookXlsx($database);
libraryWorkbookCheck(str_starts_with($workbook, "PK"), 'Library export is not an XLSX ZIP archive.');
$temporary = tempnam(sys_get_temp_dir(), 'library-workbook-');
if ($temporary === false) {
    throw new RuntimeException('Unable to create a temporary test file.');
}
try {
    file_put_contents($temporary, $workbook);
    $entries = readZipEntries($temporary, [], 'xl/worksheets/');
    $sheets = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1));
    libraryWorkbookCheck(count($sheets) === 3, 'Library workbook must contain three department sheets.');
    foreach ($sheets as $sheet) {
        $xml = loadImportXml($entries[$sheet], 'Library worksheet');
        libraryWorkbookCheck(str_contains($entries[$sheet], 'Borrowed Book?') && str_contains($entries[$sheet], 'Book Title(s)'), 'Borrowing columns are missing.');
        libraryWorkbookCheck(count($xml->xpath('//*[local-name()="pane"][@state="frozen"]')) === 1, 'Library column headers should stay frozen.');
        libraryWorkbookCheck(count($xml->xpath('//*[local-name()="mergeCell"]')) === 2, 'Library title and instructions should span all columns.');
        libraryWorkbookCheck(count($xml->xpath('//*[local-name()="dataValidation"][@type="list"]')) === 1, 'Borrowing must offer Yes/No choices.');
    }
    $styleEntries = readZipEntries($temporary, ['xl/styles.xml'], 'xl/worksheets/');
    libraryWorkbookCheck(isset($styleEntries['xl/styles.xml']), 'Workbook styling is missing.');
    loadImportXml($styleEntries['xl/styles.xml'], 'Library styles');
    $rows = readLibraryWorkbook($temporary);
    libraryWorkbookCheck($rows !== [], 'Library workbook rows could not be read.');
    libraryWorkbookCheck(isset($rows[0]['student_number'], $rows[0]['status']), 'Library workbook columns are incomplete.');
} finally {
    @unlink($temporary);
}

echo "Library three-sheet workbook test passed.\n";
