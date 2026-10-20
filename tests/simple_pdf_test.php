<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../clearance/simple_pdf.php';

$pdf = new SimplePdf();
$logoData = file_get_contents(__DIR__ . '/../assets/the-lewis-college-logo-blue.png');
if (!is_string($logoData)) {
    throw new RuntimeException('The test logo could not be read.');
}
$logo = $pdf->addImage($logoData, 'image/png');
$pdf->imageFitted($logo, 30, 500, 80, 60);
$pdf->text(120, 540, 'THE LEWIS COLLEGE', 18, true);
$pdf->line(30, 495, 810, 495);
$pdf->rect(30, 450, 780, 35);
$data = $pdf->output();

$checks = [
    [str_starts_with($data, '%PDF-1.4'), 'The renderer emits a PDF 1.4 document.'],
    [str_contains($data, '/MediaBox [0 0 841.89 595.28]'), 'The document uses A4 landscape dimensions.'],
    [str_contains($data, '/SMask'), 'The transparent college logo keeps its alpha mask.'],
    [(bool)preg_match('/\/ColorSpace \/DeviceGray \/BitsPerComponent 8 \/Filter \/FlateDecode/', $data), 'Compressed transparency masks declare their decoder so logos and signatures render correctly.'],
    [str_contains($data, '(THE LEWIS COLLEGE)'), 'Text is included in the page content.'],
    [str_contains($data, "xref\n") && str_contains($data, "startxref\n"), 'The PDF contains its cross-reference table.'],
    [str_ends_with($data, "%%EOF\n"), 'The PDF has a valid end marker.'],
    [strlen($data) > 10000, 'The generated PDF contains the embedded logo data.'],
];

foreach ($checks as [$passed, $message]) {
    if (!$passed) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}
echo count($checks) . " PDF renderer checks passed.\n";
