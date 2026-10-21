<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';
$user = requireRole('ACCOUNTING_STAFF');
$filter = strtoupper((string) ($_GET['filter'] ?? 'AWAITING'));
if (!in_array($filter, ['AWAITING', 'APPROVED', 'REJECTED', 'ALL'], true)) {
    $filter = 'AWAITING';
}
try {
    $database = getDatabaseConnection();
    $period = $database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if (!$period) {
        throw new RuntimeException('No active semester.');
    }
    $where = match ($filter) {
        'AWAITING' => "receipt.status='SCANNED' AND cs.status NOT IN ('LOCKED','APPROVED')",
        'APPROVED' => "cs.status='APPROVED'",
        'REJECTED' => "receipt.status='ISSUED' AND cs.status='RETURNED'",
        default => '1=1'
    };
    $from = "FROM accounting_receipts receipt INNER JOIN students student ON student.id=receipt.student_id INNER JOIN clearances c ON c.student_id=receipt.student_id AND c.academic_period_id=receipt.academic_period_id INNER JOIN clearance_stations cs ON cs.clearance_id=c.id INNER JOIN stations station ON station.id=cs.station_id AND station.code='ACCOUNTING' WHERE receipt.academic_period_id=:period_id AND receipt.receipt_code LIKE 'PORTAL-%' AND receipt.id=(SELECT MAX(latest.id) FROM accounting_receipts latest WHERE latest.student_id=receipt.student_id AND latest.academic_period_id=receipt.academic_period_id) AND ($where)";
    $count = $database->prepare('SELECT COUNT(*) ' . $from);
    $count->execute(['period_id' => $period['id']]);
    $totalPages = max(1, (int) ceil((int) $count->fetchColumn() / 25));
    $page = min($totalPages, max(1, (int) ($_GET['page'] ?? 1)));
    $offset = ($page - 1) * 25;
    $query = $database->prepare("SELECT receipt.id,receipt.status,receipt.scanned_at,student.student_number,student.first_name,student.last_name,student.program,student.year_level,cs.status AS clearance_status,cs.returned_reason,
        (SELECT COUNT(*) FROM accounting_obligations obligation WHERE obligation.student_id=student.id AND obligation.academic_period_id=receipt.academic_period_id AND obligation.status='UNPAID') AS unpaid_count $from ORDER BY receipt.scanned_at ASC,receipt.id ASC LIMIT 25 OFFSET $offset");
    $query->execute(['period_id' => $period['id']]);
    $submissions = $query->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Accounting submissions failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Accounting submissions.');
}
$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('Accounting Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/accounting/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'submissions', 'label' => 'Submitted Requirements', 'url' => '/accounting/submissions.php', 'icon' => 'document'],
], 'submissions', dashboardLogoutForm());
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Submitted Requirements | Accounting</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/bookstore-receipts.css')) ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Submitted Requirements</h1>
                    <p>Review TLC portal assessment screenshots.</p>
                </div>
                <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
            </header>
            <main class="staff-main"><?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <section class="panel">
                    <h2>Accounting screenshot submissions</h2>
                    <p class="muted"><?= escapeHtml($period['school_year'] . ' / ' . $period['semester'] . ' Semester') ?></p>
                    <p>Compare the name, school year, semester, charges, payments, and remaining balance with official records. Approve when the payment requirement is satisfied, or reject with instructions for the student.</p>
                    <nav class="receipt-filters" aria-label="Submission status"><?php foreach (['AWAITING' => 'Awaiting Review', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'ALL' => 'All'] as $value => $label): ?><a class="<?= $filter === $value ? 'active' : '' ?>" href="?filter=<?= $value ?>"><?= $label ?></a><?php endforeach; ?></nav>
                    <?php if (!$submissions): ?><p class="muted">No submissions in this view.</p><?php else: ?><div class="receipt-card-grid">
                            <?php foreach ($submissions as $submission): ?>
                                <?php $status = $submission['clearance_status'] === 'APPROVED' ? 'APPROVED' : ($submission['status'] === 'ISSUED' ? 'REJECTED' : 'AWAITING REVIEW'); ?>
                                <article class="receipt-card">
                                    <div class="receipt-card-heading"><strong><?= escapeHtml($submission['first_name'] . ' ' . $submission['last_name']) ?></strong><span class="badge"><?= escapeHtml($status) ?></span></div>
                                    <div class="receipt-student"><strong><?= escapeHtml($submission['student_number']) ?></strong>
                                        <div class="muted"><?= escapeHtml($submission['program']) ?> &middot; Year <?= (int) $submission['year_level'] ?></div>
                                        <div class="muted">Submitted <?= escapeHtml($submission['scanned_at']) ?></div>
                                    </div>
                                    <a class="receipt-card-image" href="<?= escapeHtml(applicationUrl('/accounting/assessment_image.php?id=' . (int) $submission['id'])) ?>" target="_blank" rel="noopener"><img loading="lazy" src="<?= escapeHtml(applicationUrl('/accounting/assessment_image.php?id=' . (int) $submission['id'])) ?>" alt="TLC portal assessment for <?= escapeHtml($submission['student_number']) ?>"></a>
                                    <?php if ($status === 'AWAITING REVIEW' && $submission['clearance_status'] !== 'LOCKED'): ?>
                                        <?php if ((int) $submission['unpaid_count'] > 0): ?><p class="notice warning">Settle the recorded unpaid obligations in the Dashboard before approving.</p><?php endif; ?>
                                        <div class="receipt-card-actions">
                                            <form method="post" action="<?= escapeHtml(applicationUrl('/accounting/submission_action.php')) ?>" onsubmit="return confirm('Approve this screenshot and complete Accounting clearance?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><button name="action" value="approve" <?= (int) $submission['unpaid_count'] > 0 ? 'disabled' : '' ?>>Approve</button></form><button class="danger" type="button" data-reject-submission="<?= (int) $submission['id'] ?>" data-reject-label="<?= escapeHtml($submission['student_number'] . ' - ' . $submission['first_name'] . ' ' . $submission['last_name']) ?>">Reject</button>
                                        </div>
                                    <?php elseif ($status === 'REJECTED'): ?><p class="notice warning">Reason: <?= escapeHtml((string) $submission['returned_reason']) ?></p><?php endif; ?>
                                </article><?php endforeach; ?>
                        </div><?php endif; ?>
                    <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Submission pages"><?php foreach (paginationPages($page, $totalPages) as $number): ?><?php if ($number === 'ellipsis'): ?><span>&hellip;</span><?php else: ?><a class="pagination-link <?= $page === $number ? 'active' : '' ?>" href="?filter=<?= $filter ?>&amp;page=<?= $number ?>"><?= $number ?></a><?php endif; ?><?php endforeach; ?></nav><?php endif; ?>
                </section>
                <div class="modal-backdrop" id="rejectAccountingModal" aria-hidden="true">
                    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="rejectAccountingTitle">
                        <h2 id="rejectAccountingTitle">Reject submission</h2>
                        <p id="rejectAccountingLabel" class="muted"></p>
                        <form class="return-receipt-form" method="post" action="<?= escapeHtml(applicationUrl('/accounting/submission_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" id="rejectAccountingSubmissionId"><label>Reason<textarea name="reason" maxlength="1000" required placeholder="Explain what the student needs to correct"></textarea></label>
                            <div class="modal-actions"><button class="secondary" type="button" data-close-reject-modal>Cancel</button><button class="danger" name="action" value="reject">Reject Submission</button></div>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/accounting-submissions.js')) ?>"></script>
</body>

</html>
