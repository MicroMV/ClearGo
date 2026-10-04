<?php

declare(strict_types=1);

require_once __DIR__ . '/../clearance/report_workbook.php';

function clearanceReportCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$workbook = createClearanceReportXlsx(
    'Clinic Clearance Report',
    'FIRST SEMESTER A.Y. 2026-2027',
    ['Student Number', 'Full Name', 'Course', 'Year Level', 'College', 'Clearance Status', 'Important Details', 'Remarks'],
    [
        ['name' => 'CCS', 'rows' => [['2026-0001', 'Dela Cruz, Ana', 'BSIT', 'Year 4', 'CCS', 'CLEARED', 'Medical record: VERIFIED', '']]],
        ['name' => 'CBE', 'rows' => []],
        ['name' => 'CTE', 'rows' => []],
    ]
);

clearanceReportCheck(str_starts_with($workbook, 'PK'), 'Report export is not an XLSX ZIP archive.');
$temporary = tempnam(sys_get_temp_dir(), 'clearance-report-');
if ($temporary === false) {
    throw new RuntimeException('Unable to create a temporary test workbook.');
}
try {
    file_put_contents($temporary, $workbook);
    $entries = readZipEntries($temporary, ['xl/styles.xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml'], 'xl/worksheets/');
    clearanceReportCheck(count(array_filter(array_keys($entries), static fn(string $name): bool => str_starts_with($name, 'xl/worksheets/'))) === 3, 'The report must contain three college worksheets.');
    $sheet = $entries['xl/worksheets/sheet1.xml'];
    loadImportXml($entries['xl/workbook.xml'], 'workbook');
    loadImportXml($entries['xl/styles.xml'], 'styles');
    loadImportXml($sheet, 'first worksheet');
    clearanceReportCheck(str_contains($sheet, 'A1:H1'), 'The report title is not merged across all columns.');
    clearanceReportCheck(str_contains($sheet, 'autoFilter ref="A3:H4"'), 'The report filter does not cover the headings and data.');
    clearanceReportCheck(str_contains($sheet, 'pane ySplit="3"'), 'The first three report rows are not frozen.');
    clearanceReportCheck(str_contains($sheet, 'Medical record: VERIFIED'), 'Important report details are missing.');
    clearanceReportCheck(str_contains($entries['xl/workbook.xml'], 'sheet name="CCS"'), 'The CCS worksheet is missing.');
    clearanceReportCheck(str_contains($entries['xl/_rels/workbook.xml.rels'], 'Target="styles.xml"'), 'Workbook styles are not connected.');
} finally {
    @unlink($temporary);
}

echo "Clearance report workbook test passed.\n";
