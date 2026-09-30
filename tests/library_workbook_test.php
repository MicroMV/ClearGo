<?php

declare(strict_types=1);

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
    $rows = readLibraryWorkbook($temporary);
    libraryWorkbookCheck($rows !== [], 'Library workbook rows could not be read.');
    libraryWorkbookCheck(isset($rows[0]['student_number'], $rows[0]['status']), 'Library workbook columns are incomplete.');
} finally {
    @unlink($temporary);
}

echo "Library three-sheet workbook test passed.\n";
