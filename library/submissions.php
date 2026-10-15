<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('LIBRARY_STAFF');
$filter = strtoupper((string) ($_GET['filter'] ?? 'AWAITING'));
if (!in_array($filter, ['AWAITING', 'VERIFIED', 'RETURNED', 'ALL'], true)) {
    $filter = 'AWAITING';
}

try {
    $database = getDatabaseConnection();
    $period = $database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }
    $where = match ($filter) {
        'AWAITING' => "submission.status='SUBMITTED'",
        'VERIFIED' => "submission.status='VERIFIED'",
        'RETURNED' => "submission.status='RETURNED'",
        default => '1=1',
    };
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 30;
    $count = $database->prepare(
        "SELECT COUNT(*) FROM library_card_submissions submission
         INNER JOIN library_cards card ON card.id=submission.library_card_id
         INNER JOIN students student ON student.id=card.student_id
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
            AND enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'
         WHERE {$where}"
    );
    $count->execute(['period_id' => $period['id']]);
    $totalSubmissions = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($totalSubmissions / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $query = $database->prepare(
        "SELECT submission.id,submission.status,submission.submitted_at,submission.return_reason,
                student.student_number,student.first_name,student.last_name,student.program,student.year_level,
                organization.code AS college
         FROM library_card_submissions submission
         INNER JOIN library_cards card ON card.id=submission.library_card_id
         INNER JOIN students student ON student.id=card.student_id
         INNER JOIN organizations organization ON organization.id=student.organization_id
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
            AND enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'
         WHERE {$where}
         ORDER BY FIELD(submission.status,'SUBMITTED','RETURNED','VERIFIED'),submission.submitted_at
         LIMIT {$pageSize} OFFSET {$offset}"
    );
    $query->execute(['period_id' => $period['id']]);
    $submissions = $query->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Library card submissions failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Library card submissions.');
}

$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('Library Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/library/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'submissions', 'label' => 'Card Submissions', 'url' => '/library/submissions.php', 'icon' => 'document'],
], 'submissions', dashboardLogoutForm());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Library Card Submissions | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/bookstore-receipts.css')) ?>">
</head>
<body class="staff-dashboard"><div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
<header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button><div class="topbar-title"><h1>Library Card Submissions</h1><p>Check captured library cards and confirm whether they are valid.</p></div><div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div></header>
<main class="staff-main"><?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2>Captured cards</h2><p class="muted"><?= escapeHtml($period['semester'] . ' Semester A.Y. ' . $period['school_year']) ?></p></div></div>
<nav class="receipt-filters"><?php foreach (['AWAITING' => 'Awaiting Review', 'VERIFIED' => 'Confirmed', 'RETURNED' => 'Returned', 'ALL' => 'All'] as $value => $label): ?><a class="<?= $filter === $value ? 'active' : '' ?>" href="?filter=<?= $value ?>"><?= $label ?></a><?php endforeach; ?></nav>
<?php if ($submissions === []): ?><p class="muted">No library card submissions in this view.</p><?php else: ?><div class="receipt-card-grid">
<?php foreach ($submissions as $submission): ?><article class="receipt-card"><div class="receipt-card-heading"><div><strong><?= escapeHtml($submission['student_number']) ?></strong><div><?= escapeHtml($submission['last_name'] . ', ' . $submission['first_name']) ?></div></div><span class="badge <?= strtolower($submission['status']) ?>"><?= escapeHtml(statusDisplayLabel($submission['status'])) ?></span></div><div class="receipt-student"><div class="muted"><?= escapeHtml($submission['program']) ?> &middot; Year <?= (int) $submission['year_level'] ?> &middot; <?= escapeHtml($submission['college']) ?></div><div class="muted">Captured <?= escapeHtml(date('M j, Y g:i A', strtotime($submission['submitted_at']))) ?></div></div><a class="receipt-card-image" href="<?= escapeHtml(applicationUrl('/library/card_image.php?id=' . (int) $submission['id'])) ?>" target="_blank" rel="noopener"><img src="<?= escapeHtml(applicationUrl('/library/card_image.php?id=' . (int) $submission['id'])) ?>" alt="Captured library card for <?= escapeHtml($submission['student_number']) ?>"></a><?php if ($submission['status'] === 'SUBMITTED'): ?><div class="receipt-card-actions"><form method="post" action="<?= escapeHtml(applicationUrl('/library/submission_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><button name="action" value="verify">Confirm Valid</button></form><button class="danger" type="button" data-return-submission="<?= (int) $submission['id'] ?>" data-return-label="<?= escapeHtml($submission['student_number']) ?>">Return</button></div><?php elseif ($submission['status'] === 'RETURNED'): ?><p class="notice warning">Return reason: <?= escapeHtml((string) $submission['return_reason']) ?></p><?php endif; ?></article><?php endforeach; ?>
</div><?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Library card submission pages"><?php foreach (paginationPages($page, $totalPages) as $pageNumber): ?><?php if ($pageNumber === 'ellipsis'): ?><span class="pagination-ellipsis">&hellip;</span><?php else: ?><a class="pagination-link <?= $pageNumber === $page ? 'active' : '' ?>" href="?filter=<?= urlencode($filter) ?>&amp;page=<?= $pageNumber ?>"><?= $pageNumber ?></a><?php endif; ?><?php endforeach; ?></nav><?php endif; ?><?php endif; ?>
</section>
<div class="modal-backdrop" id="returnLibraryCardModal" aria-hidden="true"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="returnLibraryCardTitle"><h2 id="returnLibraryCardTitle">Return library card</h2><p id="returnLibraryCardLabel" class="muted"></p><form class="return-receipt-form" method="post" action="<?= escapeHtml(applicationUrl('/library/submission_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" id="returnLibraryCardId"><label>Reason<textarea name="reason" maxlength="1000" required placeholder="Explain why the card photo is invalid or unclear"></textarea></label><div class="modal-actions"><button class="secondary" type="button" data-close-return-modal>Cancel</button><button class="danger" name="action" value="return">Return to Student</button></div></form></section></div>
</main></div></div><script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script><script src="<?= escapeHtml(applicationUrl('/assets/js/library-card-submissions.js')) ?>"></script></body></html>
