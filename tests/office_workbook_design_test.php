<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../library/workbook_functions.php';
require_once __DIR__ . '/../clinic/workbook_functions.php';
require_once __DIR__ . '/../bookstore/workbook_functions.php';

$database = getDatabaseConnection();
$workbooks = [
    'Library' => [createLibraryWorkbookXlsx($database), 'H', 'readLibraryWorkbook'],
    'Clinic' => [createClinicWorkbookXlsx($database), 'G', 'readClinicWorkbook'],
    'Bookstore' => [createBookstoreWorkbookXlsx($database), 'J', 'readBookstoreWorkbook'],
    'Registrar' => [createStudentImportTemplateXlsx(), 'F', 'readStudentXlsx'],
];
foreach ($workbooks as $office => [$workbook, $lastColumn, $reader]) {
    $temporary = tempnam(sys_get_temp_dir(), 'office-design-');
    if ($temporary === false) {
        throw new RuntimeException('Unable to create test file.');
    }
    try {
        file_put_contents($temporary, $workbook);
        $entries = readZipEntries($temporary, ['xl/styles.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml'], 'xl/worksheets/');
        foreach (['xl/styles.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml'] as $part) {
            loadImportXml($entries[$part], $part);
        }
        if (!str_contains($entries['xl/_rels/workbook.xml.rels'], 'Target="styles.xml"') || !str_contains($entries['[Content_Types].xml'], '/xl/styles.xml')) {
            throw new RuntimeException($office . ': styles are not connected to the workbook.');
        }
        foreach ($entries as $name => $worksheet) {
            if (!str_starts_with($name, 'xl/worksheets/')) {
                continue;
            }
            $xml = loadImportXml($worksheet, $name);
            if (count($xml->xpath('//*[local-name()="pane"][@state="frozen"]')) !== 1
                || !str_contains($worksheet, 'A1:' . $lastColumn . '1')
                || !str_contains($worksheet, 'A3:' . $lastColumn)
                || !str_contains($worksheet, $office === 'Registrar' ? 'Ctrl+F' : 'Search by name or ID')
                || !str_contains($worksheet, 's="4"')) {
                throw new RuntimeException($office . ': worksheet design is incomplete.');
            }
        }
        if ($reader($temporary) === []) {
            throw new RuntimeException($office . ': workbook no longer reads back.');
        }
    } finally {
        @unlink($temporary);
    }
}
echo "All four office workbook designs and readbacks passed.\n";
