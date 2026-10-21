<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?= connectionStatusAssets() ?>
    <title>Excel Import | <?= escapeHtml($officeName) ?></title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/office-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/office-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/dean-completion.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/dean-completion.css') ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
    <?= $sidebar ?>
    <div class="staff-workspace">
        <header class="staff-topbar">
            <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
            <div class="topbar-title"><h1>Excel Import</h1><p><?= escapeHtml($officeName) ?> &middot; Download, complete and import your workbook.</p></div>
            <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
        </header>
        <main class="staff-main">
            <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
            <?php if (!$canImport): ?>
                <section class="panel"><h2>Workbook imports are closed</h2><p class="muted">Workbook tools are available during semester preparation and open clearance. Check the active semester with the Registrar.</p></section>
            <?php elseif ($stationCode === 'DEAN'): ?>
                <?php require __DIR__ . '/../dean/completion_workbook_panel.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/workbook_upload_panel.php'; ?>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>
</html>
