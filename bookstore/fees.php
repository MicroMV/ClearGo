<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$user = requireRole('BOOKSTORE_STAFF');

try {
    $database = getDatabaseConnection();
    $period = $database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $query = $database->prepare(
        "SELECT fee.id,fee.name,fee.amount,fee.status,
                COUNT(student_fee.id) AS student_count,
                SUM(CASE WHEN student_fee.status='PAID' THEN 1 ELSE 0 END) AS verified_count
         FROM bookstore_fees fee
         LEFT JOIN student_bookstore_fees student_fee ON student_fee.fee_id=fee.id
         WHERE fee.academic_period_id=:period_id
         GROUP BY fee.id,fee.name,fee.amount,fee.status
         ORDER BY fee.status='ACTIVE' DESC,fee.id DESC"
    );
    $query->execute(['period_id' => $period['id']]);
    $fees = $query->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Bookstore event fees failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Bookstore event fees.');
}

$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('Bookstore Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/bookstore/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'fees', 'label' => 'Event Fees', 'url' => '/bookstore/fees.php', 'icon' => 'document'],
    ['key' => 'submissions', 'label' => 'Receipt Submissions', 'url' => '/bookstore/submissions.php', 'icon' => 'document'],
], 'fees', dashboardLogoutForm());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bookstore Event Fees | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/bookstore-receipts.css')) ?>">
</head>
<body class="staff-dashboard"><div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
<header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button><div class="topbar-title"><h1>Event Fees</h1><p>Add the receipts students must submit this semester.</p></div><div class="topbar-actions"><div class="topbar-account"><strong><?= escapeHtml($user['email']) ?></strong><span>Bookstore staff account</span></div><?= dashboardNotificationBell($notifications) ?></div></header>
<main class="staff-main">
    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
    <section class="panel"><div class="section-heading"><div><h2>Add event fee</h2><p class="muted"><?= escapeHtml($period['semester'] . ' Semester A.Y. ' . $period['school_year']) ?></p></div></div>
        <form class="controls" method="post" action="<?= escapeHtml(applicationUrl('/bookstore/fee_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="create"><label>Event name<input type="text" name="name" maxlength="200" required placeholder="Example: Acquaintance Party"></label><label>Fee amount<input type="number" name="amount" min="0" max="99999999.99" step="0.01" required placeholder="0.00"></label><button type="submit">Add Event Fee</button></form>
    </section>
    <section class="panel"><div class="section-heading"><div><h2>Semester event fees</h2><p class="muted">Each active event requires one receipt or one lost-receipt report per student. Active events and amounts appear in new Excel downloads; closed events are excluded.</p></div></div>
        <?php if ($fees === []): ?><p class="muted">No event fees have been added.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Event</th><th>Amount</th><th>Students</th><th>Confirmed</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($fees as $fee): ?><tr><td><strong><?= escapeHtml($fee['name']) ?></strong></td><td>PHP <?= number_format((float) $fee['amount'], 2) ?></td><td><?= (int) $fee['student_count'] ?></td><td><?= (int) $fee['verified_count'] ?></td><td><span class="badge <?= strtolower($fee['status']) ?>"><?= escapeHtml($fee['status']) ?></span></td><td><?php if ($fee['status'] === 'ACTIVE'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/bookstore/fee_action.php')) ?>" onsubmit="return confirm('Close this event fee? It will no longer be required for clearance.');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="fee_id" value="<?= (int) $fee['id'] ?>"><button class="secondary" name="action" value="deactivate">Close</button></form><?php else: ?><span class="muted">Closed</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</main></div></div><script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script></body></html>
