<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/semester_functions.php';
$user = requireRole('REGISTRAR');
try {
    $database = getDatabaseConnection();
    $period = registrarActivePeriod($database);
    $readiness = semesterReadiness($database, (int) $period['id']);
    $readyCount = count(array_filter($readiness, static fn(array $row): bool => $row['status'] === 'READY'));
    $enrollment = $database->prepare("SELECT COUNT(*) FROM semester_enrollments WHERE academic_period_id=:id AND status='ENROLLED'");
    $enrollment->execute(['id' => $period['id']]);
    $enrolledCount = (int) $enrollment->fetchColumn();
    $periods = $database->query("SELECT id,school_year,semester,status,clearance_status,opened_at,closed_at FROM academic_periods ORDER BY id DESC")->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Registrar dashboard failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the Registrar dashboard.');
}
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$sidebar = registrarDashboardSidebar($user, 'dashboard', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registrar | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/registrar-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Registrar Administration</h1>
                    <p>Manage semester operations, accounts, and final clearance review.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <?php if ($message): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2><?= escapeHtml(semesterLabel($period)) ?></h2>
                            <p class="muted">Active semester overview and clearance lifecycle.</p>
                        </div>
                    </div>
                    <div class="summary"><span>Lifecycle<b><?= escapeHtml($period['clearance_status']) ?></b></span><span>Enrolled students<b><?= $enrolledCount ?></b></span><span>Offices ready<b><?= $readyCount ?>/<?= count($readiness) ?></b></span></div><?php if ($period['clearance_status'] === 'PREPARING'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/registrar/semester_action.php')) ?>" onsubmit="return confirm('Open clearance for all enrolled students? Roster changes will be unavailable for this semester.');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button name="action" value="open_clearance" <?= $readyCount !== count($readiness) || $enrolledCount < 1 ? 'disabled' : '' ?>>Open Semester Clearance</button></form><?php elseif ($period['clearance_status'] === 'OPEN'): ?><p class="message">Clearance is visible to enrolled students. This semester remains available after it is archived.</p><?php endif; ?>
                </section>
                <section class="panel">
                    <h2>Office readiness</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Office</th>
                                <th>Status</th>
                                <th>Confirmed by</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($readiness as $row): ?><tr>
                                    <td><?= escapeHtml($row['name']) ?></td>
                                    <td><span class="badge <?= strtolower(str_replace('_', '-', $row['status'])) ?>"><?= escapeHtml(statusDisplayLabel($row['status'])) ?></span></td>
                                    <td><?= escapeHtml($row['ready_by_email'] ?? '—') ?></td>
                                    <td><?= escapeHtml($row['ready_at'] ?? '—') ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </section>
                <section class="panel">
                    <h2>Start the next semester</h2>
                    <p class="muted">This archives the current semester without deleting its students, approvals, documents, or audit history. The new semester begins with a fresh roster and fresh office readiness.</p>
                    <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/registrar/semester_action.php')) ?>" onsubmit="return confirm('Archive the current semester and start a new preparation cycle?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><label>Academic Year<br><input name="school_year" required pattern="\d{4}-\d{4}" placeholder="2026-2027"></label><label>Semester<br><select name="semester">
                                <option value="1ST">First Semester</option>
                                <option value="2ND">Second Semester</option>
                                <option value="SUMMER">Summer</option>
                            </select></label><button name="action" value="create_semester">Create Semester</button></form>
                </section>
                <section class="panel">
                    <h2>Semester archive</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Semester</th>
                                <th>Status</th>
                                <th>Opened</th>
                                <th>Closed</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($periods as $archive): ?><tr>
                                    <td><?= escapeHtml(semesterLabel($archive)) ?></td>
                                    <td><span class="badge <?= strtolower($archive['clearance_status']) ?>"><?= escapeHtml($archive['clearance_status']) ?></span></td>
                                    <td><?= escapeHtml($archive['opened_at'] ?? '—') ?></td>
                                    <td><?= escapeHtml($archive['closed_at'] ?? '—') ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>

</html>
