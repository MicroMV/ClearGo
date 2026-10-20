<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../auth/signature_functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/simple_pdf.php';
require_once __DIR__ . '/clearance_document_functions.php';

$user = requireRole('STUDENT');
$periodId = filter_var($_GET['period'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($periodId === false) {
    http_response_code(404);
    exit('Clearance record not found.');
}

$database = getDatabaseConnection();
$context = studentClearanceDocumentContext($database, (int) $user['id'], $periodId);
if ($context === false) {
    http_response_code(404);
    exit('The clearance PDF is available only while semester clearance is open.');
}

syncClearanceSignatureSnapshots($database, (int) $context['clearance_id']);
$stationQuery = $database->prepare(
    "SELECT record.id,record.status,record.office_remarks,
            station.code,station.name,station.sort_order,parent.name AS parent_name,
            snapshot.printed_name,snapshot.position_title,snapshot.image_data,snapshot.mime_type
     FROM clearance_stations record
     INNER JOIN stations station ON station.id=record.station_id
     LEFT JOIN stations parent ON parent.id=station.parent_station_id
     LEFT JOIN clearance_station_signatures snapshot ON snapshot.clearance_station_id=record.id
     WHERE record.clearance_id=:clearance_id
     ORDER BY
       CASE WHEN :is_cbe_organization=1 AND station.code='ORGANIZATION' THEN 1
            WHEN :is_cbe_dean=1 AND station.code='DEAN' THEN 2
            WHEN :is_other_dean=0 AND station.code='DEAN' THEN 1
            WHEN :is_other_organization=0 AND station.code='ORGANIZATION' THEN 2
            WHEN COALESCE(parent.code,station.code)='SASO' THEN 3
            WHEN station.code='FACILITIES' THEN 4
            WHEN station.code='COMLAB' THEN 5
            WHEN station.code='ACCOUNTING' THEN 6
            WHEN station.code='REGISTRAR' THEN 7 ELSE 99 END,
       CASE WHEN parent.id IS NULL THEN 0 ELSE 1 END,station.sort_order,station.id"
);
$stationQuery->execute([
    'clearance_id' => $context['clearance_id'],
    'is_cbe_organization' => $context['organization_code'] === 'CBE' ? 1 : 0,
    'is_cbe_dean' => $context['organization_code'] === 'CBE' ? 1 : 0,
    'is_other_dean' => $context['organization_code'] === 'CBE' ? 1 : 0,
    'is_other_organization' => $context['organization_code'] === 'CBE' ? 1 : 0,
]);
$stations = $stationQuery->fetchAll();
if ($stations === []) {
    http_response_code(404);
    exit('No clearance offices were found.');
}

$pdf = new SimplePdf();
$pdf->setStroke(0.10, 0.20, 0.32);
$pdf->setFill(0.10, 0.20, 0.32);
// The original seal has transparent lettering that disappears on white paper.
$logoData = file_get_contents(__DIR__ . '/../assets/the-lewis-college-logo-blue.png');
if (!is_string($logoData)) {
    throw new RuntimeException('The college logo is unavailable.');
}
$logo = $pdf->addImage($logoData, 'image/png');
$pdf->imageFitted($logo, 34, 514, 78, 58);
$pdf->text(106, 554, 'THE LEWIS COLLEGE', 18, true);
$pdf->text(106, 539, 'HIGHER EDUCATION DEPARTMENT', 8.5, true);
$pdf->text(106, 522, 'CLEARANCE FORM', 14, true);
$pdf->text(650, 554, 'CLEARANCE REFERENCE', 7, true);
$pdf->text(650, 539, (string) $context['reference_number'], 10, true);
$pdf->text(650, 523, 'Generated: ' . date('M j, Y'), 7.5);
$pdf->line(32, 505, 810, 505, 1.0);

function clearancePdfField(SimplePdf $pdf, float $x, float $y, float $width, string $label, string $value): void
{
    $pdf->text($x, $y + 13, strtoupper($label), 6.6, true);
    clearancePdfFittedText($pdf, $x + 4, $y + 2, $width - 8, $value, 9.2);
    $pdf->line($x, $y, $x + $width, $y, 0.55);
}

function clearancePdfFittedText(SimplePdf $pdf, float $x, float $y, float $width, string $value, float $size, bool $bold = false, string $align = 'left'): void
{
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    $estimatedWidth = strlen($encoded === false ? $value : $encoded) * $size * ($bold ? 0.55 : 0.5);
    if ($estimatedWidth > $width) {
        $size *= $width / $estimatedWidth;
    }
    $pdf->text($x, $y, $value, $size, $bold, $align, $width);
}

$pdf->setStroke(0.10, 0.10, 0.10);
$pdf->setFill(0.05, 0.05, 0.05);
$studentName = trim($context['last_name'] . ', ' . $context['first_name'] . ' ' . $context['middle_name']);
$semesterLabel = ['1ST' => 'First Semester', '2ND' => 'Second Semester', 'SUMMER' => 'Summer'][$context['semester']] ?? $context['semester'];
clearancePdfField($pdf, 35, 477, 165, 'Last name', $context['last_name']);
clearancePdfField($pdf, 212, 477, 165, 'First name', $context['first_name']);
clearancePdfField($pdf, 389, 477, 165, 'Middle name', $context['middle_name']);
clearancePdfField($pdf, 570, 477, 90, 'Date', date('M j, Y'));
clearancePdfField($pdf, 677, 477, 128, 'Student number', $context['student_number']);
clearancePdfField($pdf, 35, 450, 365, 'Course / Program', $context['program']);
clearancePdfField($pdf, 415, 450, 110, 'Year level', 'Year ' . $context['year_level']);
clearancePdfField($pdf, 540, 450, 265, 'SY / Semester', $context['school_year'] . ' / ' . $semesterLabel);
// These details are not stored in the roster. Leave writing space rather than inventing values.
clearancePdfField($pdf, 35, 423, 519, 'Address', '');
clearancePdfField($pdf, 570, 423, 235, 'Phone number', '');
clearancePdfField($pdf, 35, 396, 519, 'Name of the guardian', '');
clearancePdfField($pdf, 570, 396, 235, 'Guardian phone number', '');

$pdf->text(35, 376, 'PURPOSE OF CLEARANCE', 7, true);
// The student chooses the purpose; year level alone does not establish graduation or transfer intent.
foreach ([
    'Final Exam - Graduating', 'Final Exam - Non-Graduating', 'Withdrawal',
    'Transfer Out', 'Readmission', 'Others:',
] as $index => $label) {
    $x = [180, 405, 650][$index % 3];
    $y = $index < 3 ? 374 : 358;
    $pdf->rect($x, $y, 8, 8, false, 0.7);
    $pdf->text($x + 13, $y + 1, $label, 8);
    if ($label === 'Others:') {
        $pdf->line($x + 43, $y, 805, $y, 0.55);
    }
}

$tableTop = 348.0;
$tableBottom = 162.0;
$tableWidth = 380.0;
$headerHeight = 18.0;
$rowsPerTable = (int) ceil(count($stations) / 2);
$rowHeight = ($tableTop - $tableBottom - $headerHeight) / $rowsPerTable;
$columns = [108.0, 116.0, 156.0];
$headers = ['OFFICE / DEPARTMENT', 'REMARKS', 'SIGNATURE / SIGNATORY'];
// Two office tables reproduce the paper layout while retaining every applicable system office.
foreach (array_chunk($stations, $rowsPerTable) as $tableIndex => $tableStations) {
    $tableX = 32.0 + ($tableIndex * 398.0);
    $cursorX = $tableX;
    foreach ($columns as $index => $columnWidth) {
        if ($index > 0) {
            $pdf->line($cursorX, $tableBottom, $cursorX, $tableTop, 0.55);
        }
        $pdf->text($cursorX, $tableTop - 12, $headers[$index], 6.6, true, 'center', $columnWidth);
        $cursorX += $columnWidth;
    }
    $pdf->rect($tableX, $tableBottom, $tableWidth, $tableTop - $tableBottom, false, 0.8);
    $pdf->line($tableX, $tableTop - $headerHeight, $tableX + $tableWidth, $tableTop - $headerHeight, 0.75);
    $rowTop = $tableTop - $headerHeight;
    foreach ($tableStations as $stationIndex => $station) {
        $rowBottom = $rowTop - $rowHeight;
        $number = ($tableIndex * $rowsPerTable) + $stationIndex + 1;
        $officeLines = array_slice($pdf->wrap($number . '. ' . $station['name'], 7.1, $columns[0] - 10, true), 0, 2);
        foreach ($officeLines as $lineIndex => $line) {
            $pdf->text($tableX + 5, $rowBottom + ($rowHeight / 2) + (count($officeLines) === 1 ? -2 : 2) - ($lineIndex * 8), $line, 7.1, true);
        }
        $isApproved = $station['status'] === 'APPROVED';
        $remarks = $isApproved ? trim((string) ($station['office_remarks'] ?? '')) : '';
        if ($isApproved && $remarks === '') {
            $remarks = 'Cleared';
        }
        $remarksX = $tableX + $columns[0];
        $remarkLines = array_slice($pdf->wrap($remarks, 6.8, $columns[1] - 10), 0, 2);
        foreach ($remarkLines as $lineIndex => $line) {
            $pdf->text($remarksX + 5, $rowTop - 9 - ($lineIndex * 7.2), $line, 6.8);
        }
        $signatureX = $remarksX + $columns[1];
        if ($isApproved) {
            if (!empty($station['printed_name']) && !empty($station['image_data'])) {
                $signatureImage = $pdf->addImage($station['image_data'], $station['mime_type']);
                $pdf->imageFitted($signatureImage, $signatureX + 7, $rowBottom + 14, $columns[2] - 14, $rowHeight - 15);
                clearancePdfFittedText($pdf, $signatureX + 5, $rowBottom + 7.3, $columns[2] - 10, $station['printed_name'], 6.4, true, 'center');
                clearancePdfFittedText($pdf, $signatureX + 5, $rowBottom + 1.5, $columns[2] - 10, $station['position_title'], 5.5, false, 'center');
            } else {
                foreach ($pdf->wrap('Approved - signature not registered', 6, $columns[2] - 10) as $lineIndex => $line) {
                    $pdf->text($signatureX + 5, $rowTop - 11 - ($lineIndex * 7), $line, 6);
                }
            }
        }
        $pdf->line($tableX, $rowBottom, $tableX + $tableWidth, $rowBottom, 0.45);
        $rowTop = $rowBottom;
    }
}

$pdf->rect(32, 110, 778, 40, false, 0.8);
$pdf->text(39, 138, 'Purpose for Transfer Out/Withdrawal', 8, true);
$pdf->line(39, 126, 803, 126, 0.4);
$pdf->line(39, 114, 803, 114, 0.4);
$pdf->text(35, 98, 'Notes: For withdrawals and transfer out, parents/guardians must be informed.', 7.2);
$pdf->text(35, 88, 'If represented, present an authorization letter with a photocopy of a valid ID.', 7.2);

// Approval signatures above are official snapshots. These personal signature lines are completed by hand.
clearancePdfFittedText($pdf, 40, 64, 355, $studentName, 9, false, 'center');
$pdf->line(35, 60, 400, 60, 0.6);
$pdf->text(35, 49, "Student's Name and Signature", 8, false, 'center', 365);
$pdf->line(450, 60, 805, 60, 0.6);
$pdf->text(450, 49, "Representative's Name and Signature", 8, false, 'center', 355);

$privacyNotice = 'Data Privacy Notice: The Lewis College values your privacy and complies with the Data Privacy Act of 2012. Information on this clearance form is used to verify and process your clearance request. '
    . 'By submitting this form, you consent to its use for that purpose. For privacy concerns, contact the school Data Protection Officer.';
foreach ($pdf->wrap($privacyNotice, 6.2, 770) as $lineIndex => $line) {
    $pdf->text(35, 34 - ($lineIndex * 7.5), $line, 6.2);
}
$pdf->text(35, 13, 'ClearGo | Official record at download. Approved offices are signed; pending offices remain unsigned.', 6.2);
$pdf->text(600, 13, 'Status: ' . ($context['clearance_status'] === 'COMPLETED' ? 'COMPLETED' : 'IN PROGRESS'), 7.2, true, 'right', 205);

$pdfData = $pdf->output();
$safeStudentNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $context['student_number']);
$filename = 'clearance-' . trim((string) $safeStudentNumber, '-') . '-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $context['school_year']) . '.pdf';
header('Content-Type: application/pdf');
$disposition = ($_GET['view'] ?? null) === 'inline' ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfData));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
echo $pdfData;
