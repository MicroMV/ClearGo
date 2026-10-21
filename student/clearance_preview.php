<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_document_functions.php';
require_once __DIR__ . '/dashboard_functions.php';

$user = requireRole('STUDENT');
$periodId = filter_var($_GET['period'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$context = $periodId === false ? false : studentClearanceDocumentContext(getDatabaseConnection(), (int) $user['id'], $periodId);
if ($context === false) {
    http_response_code(404);
    exit('The clearance preview is available only while semester clearance is open.');
}
header('Cache-Control: private, no-store, max-age=0');
header('X-Frame-Options: SAMEORIGIN');
$pdfUrl = applicationUrl('/student/clearance_pdf.php?period=' . $periodId);
$backUrl = applicationUrl('/student/dashboard.php?period=' . $periodId . '#clearance');
$semester = ['1ST'=>'First Semester','2ND'=>'Second Semester','SUMMER'=>'Summer'][$context['semester']] ?? $context['semester'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?= pwaAssets() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clearance preview | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/clearance-preview.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/clearance-preview.css') ?>">
</head>
<body>
    <header class="preview-header">
        <a class="preview-brand" href="<?= escapeHtml($backUrl) ?>"><img src="<?= escapeHtml(applicationUrl('/assets/the-lewis-college-logo-blue.png')) ?>" alt="The Lewis College"><span>ClearGo<small>Student clearance</small></span></a>
        <a class="preview-back" href="<?= escapeHtml($backUrl) ?>">&larr; Back to clearance</a>
    </header>
    <main>
        <section class="preview-toolbar" aria-labelledby="preview-title">
            <div><h1 id="preview-title">Clearance preview</h1><p><?= escapeHtml($semester . ' · ' . $context['school_year']) ?></p></div>
            <a class="preview-download" href="<?= escapeHtml($pdfUrl) ?>"><?= studentDashboardIcon('download') ?>Download PDF</a>
        </section>
        <p class="preview-help">Approved offices show their saved signatures. Pending offices remain unsigned. Complete the blank fields and personal signature lines after printing.</p>
        <iframe class="preview-document" title="Your clearance paper" src="<?= escapeHtml($pdfUrl . '&view=paper') ?>"></iframe>
        <p class="preview-fallback">If the preview does not appear, <a href="<?= escapeHtml($pdfUrl . '&view=inline') ?>" target="_blank" rel="noopener">open the PDF in a new tab</a> or download it above.</p>
    </main>
</body>
</html>
