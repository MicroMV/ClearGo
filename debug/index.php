<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
requireDebugAccess();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';
$database = getDatabaseConnection();
$periods = $database->query("SELECT id,school_year,semester,clearance_status FROM academic_periods WHERE status='ACTIVE' ORDER BY id LIMIT 2")->fetchAll();
$period = count($periods) === 1 ? $periods[0] : false;
$counts = debugTableCounts($database);
$ready = $period === false ? [] : semesterReadiness($database,(int)$period['id']);
$readyCount = count(array_filter($ready,static fn(array $row): bool=>$row['status']==='READY'));
$token = createCsrfToken();
$message = pullFlashMessage();
$backups = debugBackups();
function debugFormFields(string $token, string $action, int $periodId = 0): void {
    echo '<input type="hidden" name="csrf_token" value="' . escapeHtml($token) . '"><input type="hidden" name="action" value="' . escapeHtml($action) . '">';
    if ($periodId > 0) { echo '<input type="hidden" name="period_id" value="' . $periodId . '">'; }
}
$actionUrl = applicationUrl('/debug/action.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Local debug tools | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/debug-console.css')) ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/css/debug-console.css') ?>">
</head>
<body>
    <header><a href="<?= escapeHtml(applicationUrl('/debug/')) ?>">ClearGo<span>Standalone debug tools</span></a><a class="back" href="<?= escapeHtml(applicationUrl('/login.php')) ?>">Open ClearGo</a></header>
    <main>
        <div class="page-heading"><p class="eyebrow">TEMPORARY DEVELOPMENT SCREEN</p><h1>Start, test, reset.</h1><p>Standalone tools for testing on this computer. No school account login required.</p></div>
        <?php if ($message !== null): ?><div class="message" role="status"><?= escapeHtml($message) ?></div><?php endif; ?>
        <section class="panel semester-panel"><div><p class="eyebrow">ACTIVE SEMESTER</p><h2><?= $period === false ? 'No single active semester' : escapeHtml(semesterLabel($period)) ?></h2><p><?= $period === false ? 'Create a semester in Registrar to begin.' : escapeHtml($period['clearance_status']) . ' · ' . $readyCount . ' / ' . count($ready) . ' offices ready' ?></p></div><a class="link-button" href="<?= escapeHtml(applicationUrl('/registrar/dashboard.php')) ?>">Semester settings &rarr;</a></section>
        <div class="stats"><div><strong><?= $counts['academic_periods'] ?></strong><span>Semesters</span></div><div><strong><?= $counts['students'] ?></strong><span>Students</span></div><div><strong><?= $counts['clearances'] ?></strong><span>Clearance records</span></div><div><strong><?= $counts['users'] ?></strong><span>Accounts</span></div></div>
        <?php if ($periods === []): ?>
            <section class="panel"><h2>Start a new semester</h2><p>Create a semester here after resetting, then enroll or import students through Registrar.</p><form class="new-semester" method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'create_semester'); ?><label>School year<input name="school_year" required pattern="[0-9]{4}-[0-9]{4}" placeholder="2026-2027"></label><label>Semester<select name="semester"><option value="1ST">First Semester</option><option value="2ND">Second Semester</option><option value="SUMMER">Summer</option></select></label><button>Create semester</button></form></section>
        <?php endif; ?>
        <div class="tool-grid">
            <section class="panel"><p class="eyebrow">1 · PREPARE</p><h2>Ready all offices</h2><p>Mark every office ready for the active semester in one click. Student requirements and approvals are unchanged.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'ready_all',(int)($period['id'] ?? 0)); ?><button <?= $period === false || $period['clearance_status'] !== 'PREPARING' ? 'disabled' : '' ?>>Ready all offices</button></form></section>
            <section class="panel"><p class="eyebrow">2 · OPEN</p><h2>Open clearance</h2><p>Use the normal Registrar opening checks. Enrolled students can view their offices and preview the clearance paper.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'open',(int)($period['id'] ?? 0)); ?><button <?= $period === false || $period['clearance_status'] !== 'PREPARING' ? 'disabled' : '' ?>>Open clearance</button></form></section>
        </div>
        <section class="panel reset-intro"><h2>Reset testing records</h2><p>Every reset creates a complete database backup first. Accounts, passwords, student profiles, signature choices, office configuration and audit history are kept. Private uploaded files remain on disk for recovery.</p></section>
        <div class="tool-grid">
            <section class="panel reset-panel"><p class="eyebrow">ALL SCHOOL YEARS</p><h2>Clear all semesters</h2><p>Remove every semester, enrollment, clearance decision, signed record, semester fee, event, medical schedule and associated submission. Global library records and violations remain.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'semesters'); ?><label for="confirm-semesters">Type <strong>CLEAR SEMESTERS</strong><input id="confirm-semesters" name="confirmation" required autocomplete="off" spellcheck="false"></label><button class="danger">Back up &amp; clear semesters</button></form></section>
            <section class="panel reset-panel"><p class="eyebrow">FRESH ACTIVITY START</p><h2>Reset activity data</h2><p>Clear all semesters and their records, plus library cards, borrowed books, violations and all notifications. Keep the student roster and accounts so you can start a new test semester.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'activity'); ?><label for="confirm-activity">Type <strong>RESET ACTIVITY</strong><input id="confirm-activity" name="confirmation" required autocomplete="off" spellcheck="false"></label><button class="danger">Back up &amp; reset activity</button></form></section>
        </div>
        <section class="panel"><div class="section-heading"><div><h2>Database backups</h2><p>Download a backup before testing, or keep the automatic backup from a reset. Restoring requires importing the SQL into MySQL; retained private files supply uploaded documents.</p></div><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'backup'); ?><button class="secondary">Create backup now</button></form></div>
            <?php if ($backups === []): ?><p class="empty">No debug backups yet.</p><?php else: ?><ul class="backups"><?php foreach ($backups as $backup): ?><li><a href="<?= escapeHtml(applicationUrl('/debug/backup.php?file=' . rawurlencode($backup['name']))) ?>"><?= escapeHtml($backup['name']) ?></a><span><?= number_format($backup['size']/1024,1) ?> KB</span></li><?php endforeach; ?></ul><?php endif; ?>
        </section>
        <section class="panel disable-panel"><div><h2>Finished testing?</h2><p>Disable this temporary screen when you are done. It is accessible only through localhost.</p></div><form method="post" action="<?= escapeHtml($actionUrl) ?>"><?php debugFormFields($token,'disable'); ?><button class="secondary">Disable debug screen</button></form></section>
    </main>
</body>
</html>
