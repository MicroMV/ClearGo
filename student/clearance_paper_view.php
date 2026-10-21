<?php
declare(strict_types=1);
if (!isset($pdf) || !$pdf instanceof SimplePdf) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Your clearance paper | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/clearance-paper.css')) ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/css/clearance-paper.css') ?>">
</head>
<body>
    <div class="paper-controls" role="group" aria-label="Paper zoom">
        <button type="button" data-paper-zoom="out" aria-label="Zoom out">−</button>
        <output data-paper-scale aria-live="polite">100%</output>
        <button type="button" data-paper-zoom="in" aria-label="Zoom in">+</button>
        <button type="button" data-paper-zoom="fit">Fit page</button>
        <button type="button" data-paper-zoom="read">Read size</button>
    </div>
    <p class="paper-hint">Scroll or swipe across the paper. Use + and − to adjust the size.</p>
    <div class="paper-scroll" tabindex="0" role="region" aria-label="Clearance paper. Scroll horizontally and vertically to read." data-paper-scroll>
        <div class="paper-sheet" data-paper-sheet><?= $pdf->previewSvg() ?></div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/clearance-paper.js')) ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/js/clearance-paper.js') ?>"></script>
</body>
</html>
