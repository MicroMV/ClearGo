<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../clearance/simple_pdf.php';
require_once __DIR__ . '/../auth/signature_functions.php';
$image = renderStaffSignature('[[[0.1,0.2],[0.3,0.6],[0.7,0.4]]]')['data'];
$documents = [];
foreach ([false,true] as $preview) {
    $document = new SimplePdf($preview);
    $document->setStroke(.1,.2,.3); $document->setFill(.4,.5,.6);
    $document->line(10,20,30,40,.5); $document->rect(10,20,50,60,true);
    $document->text(10,100,'Name <script>alert("x")</script> & position',9,true,'center',500);
    $document->imageFitted($document->addImage($image,'image/png'),10,20,100,50);
    $documents[] = $document;
}
$checks = [
    'PDF bytes are unchanged by enabling the preview' => $documents[0]->output() === $documents[1]->output(),
    'Preview uses the same landscape page coordinates' => str_contains($documents[1]->previewSvg(),'viewBox="0 0 841.89 595.28"'),
    'Text is escaped and cannot create markup' => str_contains($documents[1]->previewSvg(),'&lt;script&gt;') && !str_contains($documents[1]->previewSvg(),'<script>'),
    'Lines use the matching top-down coordinate conversion' => str_contains($documents[1]->previewSvg(),'y1="575.28"'),
    'Signature images are embedded without public file links' => str_contains($documents[1]->previewSvg(),'href="data:image/png;base64,'),
];
$xml = new DOMDocument();
$checks['Preview is well-formed SVG'] = $xml->loadXML($documents[1]->previewSvg());
foreach ($checks as $name=>$passed) { if (!$passed) { throw new RuntimeException($name); } echo 'PASS: ' . $name . "\n"; }
echo 'All ' . count($checks) . " PDF/preview rendering checks passed.\n";
