<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../bookstore/workbook_functions.php';

function bookstoreWorkbookCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = getDatabaseConnection();
$workbook = createBookstoreWorkbookXlsx($database);
bookstoreWorkbookCheck(str_starts_with($workbook, 'PK'), 'Bookstore export is not an XLSX ZIP archive.');
$temporary = tempnam(sys_get_temp_dir(), 'bookstore-workbook-');
if ($temporary === false) {
    throw new RuntimeException('Unable to create a temporary test file.');
}
try {
    file_put_contents($temporary, $workbook);
    $entries = readZipEntries($temporary, [], 'xl/worksheets/');
    $sheets = array_values(array_filter(array_keys($entries), static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1));
    bookstoreWorkbookCheck(count($sheets) === 3, 'Bookstore workbook must contain three department sheets.');
    $rows = readBookstoreWorkbook($temporary);
    bookstoreWorkbookCheck($rows !== [], 'Bookstore workbook rows could not be read.');
    bookstoreWorkbookCheck(isset($rows[0]['student_number'], $rows[0]['status'], $rows[0]['reference'], $rows[0]['remarks']), 'Bookstore workbook columns are incomplete.');
} finally {
    @unlink($temporary);
}

echo "Bookstore three-sheet workbook test passed.\n";
