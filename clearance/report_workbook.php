<?php

declare(strict_types=1);

require_once __DIR__ . '/../registrar/student_import_functions.php';
require_once __DIR__ . '/workbook_styles.php';

/**
 * Build a styled clearance report workbook. Each item in $sheets must contain
 * a name and a rows array; every row is an array in the same order as $headers.
 */
function createClearanceReportXlsx(string $title, string $periodLabel, array $headers, array $sheets): string
{
    if ($headers === [] || $sheets === []) {
        throw new RuntimeException('The report does not contain any columns or worksheets.');
    }

    $sheetEntries = [];
    $sheetDeclarations = '';
    $sheetRelationships = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $contentOverrides = '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

    foreach (array_values($sheets) as $index => $sheet) {
        $sheetNumber = $index + 1;
        $sheetName = clearanceReportSheetName((string) ($sheet['name'] ?? ('Sheet ' . $sheetNumber)));
        $sheetEntries['xl/worksheets/sheet' . $sheetNumber . '.xml'] = clearanceReportWorksheetXml(
            $title . ' - ' . $sheetName . ' - ' . $periodLabel,
            $headers,
            (array) ($sheet['rows'] ?? [])
        );
        $sheetDeclarations .= '<sheet name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
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

function clearanceReportWorksheetXml(string $title, array $headers, array $dataRows): string
{
    $lastColumn = clearanceReportColumnName(count($headers));
    $rows = [
        [$title],
        ['Generated from the active ClearGo roster. Use the header filters to search or narrow the report; Ctrl+F also finds a student.'],
        array_values($headers),
    ];
    foreach ($dataRows as $row) {
        $rows[] = array_values(array_pad(array_slice((array) $row, 0, count($headers)), count($headers), ''));
    }

    $xmlRows = '';
    foreach ($rows as $rowIndex => $values) {
        $rowNumber = $rowIndex + 1;
        $cells = '';
        foreach ($values as $columnIndex => $value) {
            $style = $rowNumber === 1 ? 1 : ($rowNumber === 2 ? 2 : ($rowNumber === 3 ? 3 : ($rowNumber % 2 === 0 ? 4 : 5)));
            $cells .= '<c r="' . clearanceReportColumnName($columnIndex + 1) . $rowNumber . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
        }
        $height = $rowNumber === 1 ? 32 : ($rowNumber === 2 ? 48 : 30);
        $xmlRows .= '<row r="' . $rowNumber . '" ht="' . $height . '" customHeight="1">' . $cells . '</row>';
    }

    $columnWidths = '';
    foreach ($headers as $index => $header) {
        $width = match (strtolower((string) $header)) {
            'full name' => 34,
            'important details', 'requirements' => 56,
            'remarks' => 42,
            'course', 'college' => 24,
            default => 18,
        };
        $column = $index + 1;
        $columnWidths .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'
        . $columnWidths . '</cols><sheetData>' . $xmlRows . '</sheetData><autoFilter ref="A3:' . $lastColumn . max(3, count($rows)) . '"/>'
        . '<mergeCells count="2"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/></mergeCells>'
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9"/></worksheet>';
}

function clearanceReportColumnName(int $number): string
{
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
}

function clearanceReportSheetName(string $name): string
{
    $name = preg_replace('/[\\\\\/?*\[\]:]/', '-', trim($name)) ?: 'Report';
    return substr($name, 0, 31);
}

function activePeriodReportLabel(PDO $database): string
{
    $period = $database->query("SELECT school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $semester = ['1ST' => 'FIRST SEMESTER', '2ND' => 'SECOND SEMESTER', 'SUMMER' => 'SUMMER'][$period['semester']] ?? $period['semester'];
    return $semester . ' A.Y. ' . $period['school_year'];
}

function downloadClearanceReport(string $workbook, string $filename): never
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]+/i', '-', $filename) . '"');
    header('Content-Length: ' . strlen($workbook));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $workbook;
    exit;
}
