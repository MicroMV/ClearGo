<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/../auth/signature_functions.php';
require_once __DIR__ . '/../clearance/simple_pdf.php';

$checks = 0;
foreach ([null, [], '', 'null', '{}', '[]', 'invalid', '[[[0.5,0.5]]]', '[[[0,0],[2,1]]]',
    '[[["0",0],[1,1]]]', '[[[0,0],[true,1]]]', '[[[0,0],[1]]]', str_repeat('x',400001),
    json_encode(array_fill(0,201,[[0,0],[1,1]])),
    json_encode([array_fill(0,12001,[0.5,0.5])]),
    '{"strokes":[[[0,0],[1,1]]],"aspect_ratio":0}',
    '{"strokes":[[[0,0],[1,1]]],"aspect_ratio":100}',
    '{"strokes":[[[0,0],[1,1]]],"aspect_ratio":[]}'
    ] as $invalid) {
    $rejected = false;
    try { renderStaffSignature($invalid); } catch (RuntimeException $exception) { $rejected = true; }
    if (!$rejected) { throw new RuntimeException('An invalid or blank signature drawing was accepted.'); }
    $checks++;
}
$image = renderStaffSignature('[[[0.1,0.5],[0.2,0.2],[0.4,0.7]],[[0.5,0.3],[0.8,0.6]]]');
$dimensions = getimagesizefromstring($image['data']);
if ($dimensions === false || $dimensions[0] !== 900 || $dimensions[1] !== 240 || $dimensions['mime'] !== 'image/png') {
    throw new RuntimeException('The drawing was not rendered as the expected PNG.');
}
$checks++;
$mobileImage = renderStaffSignature('{"strokes":[[[0.1,0.5],[0.2,0.2],[0.4,0.7]]],"aspect_ratio":2}');
$mobileDimensions = getimagesizefromstring($mobileImage['data']);
if ($mobileDimensions[0] !== 900 || $mobileDimensions[1] !== 450) {
    throw new RuntimeException('The signature did not preserve the mobile signing box proportions.');
}
$checks++;
$pdf = new SimplePdf();
$name = $pdf->addImage($image['data'],'image/png');
$pdf->imageFitted($name,30,400,160,40);
$output = $pdf->output();
if (!str_starts_with($output,'%PDF-1.4') || !str_contains($output,'/SMask')) {
    throw new RuntimeException('The drawn signature could not be embedded in a PDF with transparency.');
}
$checks++;
echo "All {$checks} signature drawing validation and PDF checks passed.\n";
