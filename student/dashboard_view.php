<?php
declare(strict_types=1);

if (!isset($student)) {
    return;
}

$studentInitials = strtoupper(mb_substr($student['first_name'], 0, 1) . mb_substr($student['last_name'], 0, 1));
$shortName = explode(' ', trim($student['first_name']))[0];
$stationTotal = count($stations);
$progressPercent = $stationTotal === 0 ? 0 : (int) round($approved / $stationTotal * 100);
$isComplete = $clearance !== false && ($clearance['status'] === 'COMPLETED' || ($stationTotal > 0 && $approved === $stationTotal));
$nextStation = null;
$reviewCount = 0;
$actionCount = 0;
foreach ($stations as $station) {
    $status = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED';
    if (in_array($status, ['SUBMITTED', 'UNDER_REVIEW'], true)) {
        $reviewCount++;
    }
    if (!in_array($status, ['APPROVED', 'LOCKED', 'SUBMITTED', 'UNDER_REVIEW'], true)) {
        $actionCount++;
    }
    if ($clearance !== false && $status !== 'APPROVED' && $status !== 'LOCKED') {
        $isReturned = in_array($status, ['RETURNED', 'REJECTED', 'ERROR'], true);
        $nextIsReview = $nextStation !== null && in_array($nextStation['dashboard_status'], ['SUBMITTED', 'UNDER_REVIEW'], true);
        if ($nextStation === null || ($isReturned && !in_array($nextStation['dashboard_status'], ['RETURNED', 'REJECTED', 'ERROR'], true))
            || ($nextIsReview && !in_array($status, ['SUBMITTED', 'UNDER_REVIEW'], true))) {
            $nextStation = $station + ['dashboard_status' => $status];
        }
    }
}
$presentEvents = count(array_filter($organizationEvents, static fn(array $event): bool => $event['attendance_status'] === 'PRESENT'));
$nextIsReview = $nextStation !== null && in_array($nextStation['dashboard_status'], ['SUBMITTED', 'UNDER_REVIEW'], true);
$initialDashboardView = $selectedStation !== null ? 'clearance' : 'overview';
$portalStylesVersion = (string) (filemtime(__DIR__ . '/../assets/css/student-portal.css') ?: 1);
$portalScriptVersion = (string) (filemtime(__DIR__ . '/../assets/js/student-dashboard.js') ?: 1);
$nextHint = $nextStation === null ? '' : match ($nextStation['code']) {
    'DEAN' => 'Check your Dean requirements to get started.',
    'ORGANIZATION' => 'Review your organization requirements.',
    'ACCOUNTING' => 'Submit your TLC portal assessment screenshot.',
    'LIBRARY' => 'Capture your library card for confirmation.',
    'BOOKSTORE' => 'Check your event fees and upload receipts.',
    'CLINIC' => 'Check your medical requirement and appointment.',
    'COMLAB' => 'Review your Computer Lab clearance.',
    'FACILITIES' => 'Check any items you need to return.',
    default => 'Open this office to see your requirements.',
};
$dashboardNavigation = ['overview' => ['home', 'Home'], 'clearance' => ['clearance', 'Clearance'],
    'attendance' => ['calendar', 'Attendance'], 'account' => ['user', 'Account']];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2545">
    <title>My Dashboard | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-page.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/library-card-camera.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-portal.css')) ?>?v=<?= escapeHtml($portalStylesVersion) ?>">
</head>
<body class="student-dashboard student-portal" data-initial-view="<?= escapeHtml($initialDashboardView) ?>" data-initial-modal="<?= $selectedStation !== null ? 'requirements-modal-' . (int) $selectedStation['id'] : '' ?>">
    <a class="portal-skip-link" href="#dashboard-content">Skip to content</a>
    <header class="student-header">
        <div class="student-topbar">
            <a class="student-brand" href="<?= escapeHtml(applicationUrl('/student/dashboard.php')) ?>">
                <img src="<?= escapeHtml(applicationUrl('/assets/the-lewis-college-logo-blue.png?v=' . (string) filemtime(__DIR__ . '/../assets/the-lewis-college-logo-blue.png'))) ?>" alt="The Lewis College logo">
                <span class="student-brand-copy"><strong>ClearGo<span class="brand-dot">.</span></strong><small>Student portal</small></span>
            </a>
            <nav class="portal-desktop-nav" aria-label="Main navigation">
                <?php foreach ($dashboardNavigation as $viewName => [$icon, $label]): ?>
                    <a href="#<?= $viewName ?>" data-dashboard-link="<?= $viewName ?>" <?= $viewName === 'overview' ? 'aria-current="page"' : '' ?>><?= studentDashboardIcon($icon) ?><span><?= $label ?></span></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-actions">
                <div class="notification-wrap" data-notification-endpoint="<?= escapeHtml(applicationUrl('/auth/notifications.php')) ?>" data-csrf-token="<?= escapeHtml($csrfToken) ?>" data-latest-notification-id="<?= $notifications === [] ? 0 : max(array_map(static fn(array $notification): int => (int) $notification['id'], $notifications)) ?>">
                    <button class="notification-button" id="notificationButton" type="button" aria-label="Notifications<?= $unreadNotificationCount > 0 ? ' (' . $unreadNotificationCount . ' unread)' : '' ?>" aria-controls="notificationBox" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span><?php endif; ?>
                    </button>
                    <div class="notification-box" id="notificationBox" hidden>
                        <h2>Notifications</h2>
                        <?php if ($notifications === []): ?><p class="notification-empty muted">You're all caught up.</p><ul class="notification-list"></ul><?php else: ?><ul class="notification-list"><?php foreach ($notifications as $notification): ?><li data-notification-id="<?= (int) $notification['id'] ?>" class="<?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>"><strong><?= escapeHtml($notification['title']) ?></strong><div><?= escapeHtml($notification['message']) ?></div><div class="muted"><?= escapeHtml($notification['created_at']) ?></div></li><?php endforeach; ?></ul><?php endif; ?>
                    </div>
                </div>
                <a class="portal-avatar" href="#account" data-dashboard-link="account" aria-label="Open your account"><?= escapeHtml($studentInitials) ?></a>
            </div>
        </div>
    </header>
    <main class="student-main" id="dashboard-content">
        <?php if ($message !== null): ?><p class="notice flash" role="status"><?= escapeHtml($message) ?></p><?php endif; ?>
        <?php if ($isHistoricalRecord): ?><div class="portal-archive-notice"><?= studentDashboardIcon('clock') ?><span>Saved semester record. Submissions are closed.</span></div><?php endif; ?>

        <section class="portal-view" id="overview" data-dashboard-view="overview" aria-label="Dashboard overview" tabindex="-1" <?= $initialDashboardView !== 'overview' ? 'hidden' : '' ?>>
            <div class="portal-page-heading">
                <div><p class="portal-eyebrow">YOUR STUDENT SPACE</p><h1>Hi, <?= escapeHtml($shortName) ?><span class="greeting-dot">.</span></h1><p class="portal-greeting-meta"><?= escapeHtml($student['program']) ?> <span aria-hidden="true">&middot;</span> Year <?= (int) $student['year_level'] ?> <span aria-hidden="true">&middot;</span> <?= escapeHtml($student['organization_code']) ?></p></div>
                <?php if (count($availablePeriods) > 1): ?>
                    <form class="portal-period-form" method="get"><label for="period">Viewing semester<?= $isHistoricalRecord ? ' &middot; Archive' : '' ?></label><div class="portal-period-select"><?= studentDashboardIcon('calendar') ?><select id="period" name="period" data-period-select><?php foreach ($availablePeriods as $availablePeriod): ?><option value="<?= (int) $availablePeriod['id'] ?>" <?= $period !== false && (int) $period['id'] === (int) $availablePeriod['id'] ? 'selected' : '' ?>><?= escapeHtml(($semesterLabels[$availablePeriod['semester']] ?? $availablePeriod['semester']) . ' A.Y. ' . $availablePeriod['school_year']) ?></option><?php endforeach; ?></select></div><noscript><button type="submit">View semester</button></noscript></form>
                <?php elseif ($availablePeriods !== []): ?>
                    <div class="portal-period-form"><span class="portal-period-label">Viewing semester<?= $isHistoricalRecord ? ' &middot; Archive' : '' ?></span><div class="portal-period-select portal-period-select--static"><?= studentDashboardIcon('calendar') ?><span class="portal-period-value"><?= escapeHtml($periodLabel) ?></span></div></div>
                <?php endif; ?>
            </div>
            <div class="portal-overview-grid">
                <section class="portal-progress-card">
                    <div class="portal-progress-heading"><span class="portal-card-eyebrow">MY CLEARANCE</span><span class="portal-live-label"><i aria-hidden="true"></i><?= $isHistoricalRecord ? 'Semester archive' : ($isComplete ? 'Completed' : 'Semester progress') ?></span></div>
                    <div class="portal-progress-content">
                        <div><h2><?= $isComplete ? 'All set. All cleared.' : ($isHistoricalRecord ? 'Your semester, saved.' : ($clearance === false ? 'A fresh start.' : 'One step closer.')) ?></h2><p><?= $clearance === false ? 'Your clearance will appear here when it opens.' : ($isComplete ? 'Every office has approved your clearance.' : ($isHistoricalRecord ? 'Your clearance progress for this semester.' : 'A little progress goes a long way.')) ?></p><div class="portal-count"><strong><?= $approved ?></strong><span>/ <?= $stationTotal ?> offices cleared</span></div><span class="sr-only"><?= $approved ?> of <?= $stationTotal ?> stations cleared</span></div>
                        <div class="portal-progress-ring" style="--progress: <?= $progressPercent ?>" role="progressbar" aria-label="Clearance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressPercent ?>"><span><strong><?= $progressPercent ?><small>%</small></strong><em>complete</em></span></div>
                    </div>
                    <div class="portal-progress-footer"><span><?= studentDashboardIcon($isComplete ? 'check' : 'clearance') ?><?= $clearance === false ? 'Not open yet' : escapeHtml(studentDashboardFriendlyStatus($clearance['status'])) ?></span><a href="#clearance" data-dashboard-link="clearance">View clearance <?= studentDashboardIcon('arrow') ?></a></div>
                </section>
                <section class="portal-next-card <?= $isComplete ? 'is-complete' : '' ?>">
                    <span class="portal-card-eyebrow"><?= $isHistoricalRecord ? 'LOOKING BACK' : ($isComplete ? 'NICE WORK' : 'YOUR NEXT STEP') ?></span>
                    <span class="portal-next-icon"><?= studentDashboardIcon($isComplete ? 'check' : ($isHistoricalRecord ? 'clock' : ($nextIsReview ? 'clock' : 'clearance'))) ?></span>
                    <?php if ($isHistoricalRecord): ?>
                        <h2>Your record is here.</h2><p>Check your saved office decisions and requirements.</p><a class="portal-action-button" href="#clearance" data-dashboard-link="clearance">View saved record <?= studentDashboardIcon('arrow') ?></a>
                    <?php elseif ($isComplete): ?>
                        <h2>You're good to go!</h2><p>Your semester clearance is complete.</p><a class="portal-action-button" href="#clearance" data-dashboard-link="clearance">See cleared offices <?= studentDashboardIcon('arrow') ?></a>
                    <?php elseif ($nextStation !== null): ?>
                        <h2><?= $nextIsReview ? 'With the office.' : escapeHtml(studentDashboardStationLabel($nextStation)) ?></h2><p><?= $nextIsReview ? 'Your submission is being reviewed. We’ll notify you of updates.' : (in_array($nextStation['dashboard_status'], ['RETURNED', 'REJECTED', 'ERROR'], true) ? 'Something needs a fix. Check the office feedback.' : escapeHtml($nextHint)) ?></p><button class="portal-action-button" type="button" data-requirements-modal="requirements-modal-<?= (int) $nextStation['id'] ?>"><?= $nextIsReview ? 'View submission' : 'Open requirements' ?> <?= studentDashboardIcon('arrow') ?></button>
                    <?php else: ?>
                        <h2><?= $period === false ? 'Welcome aboard.' : 'Getting things ready.' ?></h2><p><?= $period === false ? 'No semester record is available. Contact the Registrar for enrollment help.' : 'The Registrar will open clearance once the offices are ready.' ?></p><a class="portal-action-button" href="#account" data-dashboard-link="account">View my account <?= studentDashboardIcon('arrow') ?></a>
                    <?php endif; ?>
                </section>
            </div>
            <?php if ($comlabViolations !== [] || $facilitiesItems !== []): ?>
                <section class="portal-attention"><div class="portal-section-title"><div><span class="portal-card-eyebrow">BEFORE YOU CONTINUE</span><h2>A few things to check</h2></div><span class="portal-count-badge"><?= count($comlabViolations) + count($facilitiesItems) ?></span></div>
                    <?php if ($comlabViolations !== []): ?><details class="portal-alert"><summary><?= studentDashboardIcon('alert') ?><span><strong>Visit the Computer Lab</strong><small><?= count($comlabViolations) ?> unresolved <?= count($comlabViolations) === 1 ? 'violation' : 'violations' ?></small></span><?= studentDashboardIcon('chevron') ?></summary><ul><?php foreach ($comlabViolations as $violation): ?><li><?= nl2br(escapeHtml($violation['description'])) ?><small>Reported <?= escapeHtml($violation['created_at']) ?></small></li><?php endforeach; ?></ul></details><?php endif; ?>
                    <?php if ($facilitiesItems !== []): ?><details class="portal-alert"><summary><?= studentDashboardIcon('building') ?><span><strong>Return items to Facilities</strong><small><?= count($facilitiesItems) ?> <?= count($facilitiesItems) === 1 ? 'item' : 'items' ?> pending return</small></span><?= studentDashboardIcon('chevron') ?></summary><ul><?php foreach ($facilitiesItems as $item): ?><li><?= escapeHtml($item['description']) ?><?php if (!empty($item['remarks'])): ?><small><?= escapeHtml($item['remarks']) ?></small><?php endif; ?></li><?php endforeach; ?></ul></details><?php endif; ?>
                </section>
            <?php endif; ?>
            <div class="portal-section-title portal-quick-title"><div><span class="portal-card-eyebrow">A PLACE FOR EVERYTHING</span><h2>Quick access</h2></div></div>
            <div class="portal-shortcut-grid">
                <a class="portal-shortcut" href="#clearance" data-dashboard-link="clearance"><span class="portal-shortcut-icon tone-teal"><?= studentDashboardIcon('clearance') ?></span><span><strong>Clearance offices</strong><small><?= $approved ?> cleared<?= !$isHistoricalRecord && $actionCount > 0 ? ' · ' . $actionCount . ' ready' : '' ?></small></span><?= studentDashboardIcon('chevron') ?></a>
                <a class="portal-shortcut" href="#attendance" data-dashboard-link="attendance"><span class="portal-shortcut-icon tone-blue"><?= studentDashboardIcon('calendar') ?></span><span><strong>Event attendance</strong><small><?= count($organizationEvents) > 0 ? $presentEvents . ' of ' . count($organizationEvents) . ' attended' : 'Events & QR scanner' ?></small></span><?= studentDashboardIcon('chevron') ?></a>
                <a class="portal-shortcut" href="#account" data-dashboard-link="account"><span class="portal-shortcut-icon tone-lilac"><?= studentDashboardIcon('user') ?></span><span><strong>My account</strong><small><?= escapeHtml($student['student_number']) ?></small></span><?= studentDashboardIcon('chevron') ?></a>
            </div>
            <div class="portal-footer-note"><?= studentDashboardIcon('lock') ?><span>Your progress is saved to your student account.</span><span class="portal-footer-brand">ClearGo <span aria-hidden="true">&middot;</span> The Lewis College</span></div>
        </section>

        <section class="portal-view" id="clearance" data-dashboard-view="clearance" aria-labelledby="clearance-title" tabindex="-1" <?= $initialDashboardView !== 'clearance' ? 'hidden' : '' ?>>
            <div class="portal-section-title"><div><span class="portal-card-eyebrow"><?= $isHistoricalRecord ? 'SEMESTER ARCHIVE' : 'YOUR CLEARANCE JOURNEY' ?></span><h2 id="clearance-title">Clearance offices</h2><p>Tap an office to see its requirements.</p></div><?php if ($clearance !== false): ?><span class="portal-summary-pill"><?= studentDashboardIcon('check') ?><?= $approved ?> / <?= $stationTotal ?> cleared</span><?php endif; ?></div>
            <?php if ($clearance === false): ?>
                <div class="portal-empty-state"><?= studentDashboardIcon('clearance') ?><h3><?= $period === false ? 'No semester record is available.' : ($isHistoricalRecord ? 'No saved clearance for this semester.' : 'Clearance is getting ready.') ?></h3><p><?= $period === false ? 'Contact the Registrar if your enrollment is missing.' : ($isHistoricalRecord ? 'Your account and other semester records remain available.' : 'Your offices will appear after the Registrar opens clearance.') ?></p></div>
            <?php else: ?>
                <div class="portal-clearance-toolbar"><div class="portal-status-key"><span><i class="key-cleared"></i>Cleared</span><span><i class="key-ready"></i>Ready</span><span><i class="key-upcoming"></i>Upcoming</span></div><div class="portal-filter-group" role="group" aria-label="Filter clearance offices"><button type="button" class="is-active" data-station-filter="all" aria-pressed="true">All offices</button><button type="button" data-station-filter="attention" aria-pressed="false">To do</button><button type="button" data-station-filter="cleared" aria-pressed="false">Cleared</button></div></div>
                <ol class="portal-station-grid">
                    <?php foreach ($stations as $station): ?>
                        <?php $status = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED';
                        $canView = $status !== 'LOCKED' || $isHistoricalRecord;
                        $stationTone = $status === 'APPROVED' ? 'cleared' : ($status === 'LOCKED' ? 'upcoming' : (in_array($status, ['RETURNED', 'REJECTED', 'ERROR'], true) ? 'attention' : 'ready')); ?>
                        <li class="portal-station-card is-<?= $stationTone ?>" data-station-status="<?= escapeHtml($status) ?>">
                            <div class="portal-station-top"><span class="portal-station-icon"><?= studentDashboardStationIcon($station['code']) ?></span><span class="portal-office-step">Step <?= escapeHtml($station['display_order']) ?></span></div>
                            <h3 title="<?= escapeHtml($station['name']) ?>"><?= escapeHtml(studentDashboardStationLabel($station)) ?></h3>
                            <div class="portal-station-bottom"><span class="portal-station-status"><?= studentDashboardIcon($status === 'APPROVED' ? 'check' : ($status === 'LOCKED' ? 'lock' : (in_array($status, ['SUBMITTED', 'UNDER_REVIEW'], true) ? 'clock' : ($stationTone === 'attention' ? 'alert' : 'clearance')))) ?><?= escapeHtml(studentDashboardFriendlyStatus($status)) ?></span><?= studentDashboardIcon($canView ? 'chevron' : 'lock') ?></div>
                            <?php if ($canView): ?><button class="portal-station-open" type="button" data-requirements-modal="requirements-modal-<?= (int) $station['id'] ?>" aria-label="View <?= escapeHtml(studentDashboardStationLabel($station)) ?> requirements"><span class="sr-only">View requirements</span></button><?php else: ?><span class="sr-only">Complete previous offices to unlock this step.</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="portal-filter-empty" data-filter-empty role="status" hidden>No offices in this filter.</p>
                <details class="portal-record-details"><summary><?= studentDashboardIcon('clearance') ?>Clearance reference<?= studentDashboardIcon('chevron') ?></summary><p><?= escapeHtml($clearance['reference_number']) ?></p></details>
            <?php endif; ?>
        </section>

        <section class="portal-view" id="attendance" data-dashboard-view="attendance" aria-labelledby="attendance-title" tabindex="-1" hidden>
            <div class="portal-section-title"><div><span class="portal-card-eyebrow">SHOW UP. CHECK IN.</span><h2 id="attendance-title">Event attendance</h2><p><?= $isHistoricalRecord ? 'Your saved attendance for this semester.' : 'Your organization events, in one place.' ?></p></div><span class="portal-summary-pill"><?= studentDashboardIcon('calendar') ?><?= $presentEvents ?> / <?= count($organizationEvents) ?> attended</span></div>
            <?php if ($isEnrolled && !$isHistoricalRecord): ?><div class="portal-scanner-card"><span class="portal-scanner-icon"><?= studentDashboardIcon('scan') ?></span><div><h3>At an event?</h3><p>Scan your officer's QR code to check in.</p></div><a class="portal-action-button" href="<?= escapeHtml(applicationUrl('/student/attendance_scanner.php')) ?>" data-open-attendance-scanner><?= studentDashboardIcon('scan') ?>Open scanner</a></div><?php endif; ?>
            <?php if ($organizationEvents === []): ?><div class="portal-empty-state"><?= studentDashboardIcon('calendar') ?><h3>No events listed yet</h3><p>Required organization events will appear here.</p></div><?php else: ?><ul class="portal-event-list"><?php foreach ($organizationEvents as $event): ?>
                <?php $eventTime = strtotime($event['event_date']); ?>
                <li class="portal-event-card"><time class="portal-event-date" datetime="<?= escapeHtml($event['event_date']) ?>"><span><?= escapeHtml(date('M', $eventTime)) ?></span><strong><?= escapeHtml(date('d', $eventTime)) ?></strong></time><div><h3><?= escapeHtml($event['name']) ?></h3><p><?= escapeHtml(date('l', $eventTime)) ?><?= !empty($event['start_time']) ? ' · ' . escapeHtml(date('g:i A', strtotime($event['start_time']))) : '' ?></p></div><span class="portal-event-status <?= $event['attendance_status'] === 'PRESENT' ? 'is-present' : '' ?>"><?= studentDashboardIcon($event['attendance_status'] === 'PRESENT' ? 'check' : 'clock') ?><?= $event['attendance_status'] === 'PRESENT' ? 'Attended' : 'Not recorded' ?></span></li>
            <?php endforeach; ?></ul><?php endif; ?>
        </section>

        <section class="portal-view" id="account" data-dashboard-view="account" aria-labelledby="account-title" tabindex="-1" hidden>
            <div class="portal-section-title"><div><span class="portal-card-eyebrow">YOUR STUDENT IDENTITY</span><h2 id="account-title">My account</h2></div></div>
            <div class="portal-account-layout"><section class="portal-account-card"><div class="portal-account-banner"><span class="portal-profile-avatar"><?= escapeHtml($studentInitials) ?></span><span class="portal-student-label">STUDENT</span></div><div class="portal-account-info"><h3><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></h3><p><?= escapeHtml($student['student_number']) ?></p><dl><div><dt>Program</dt><dd><?= escapeHtml($student['program']) ?></dd></div><div><dt>Year & section</dt><dd>Year <?= (int) $student['year_level'] ?><?php $section = trim((string) ($student['section'] ?? '')); ?><?= $section === '' || strtoupper($section) === 'N/A' ? '' : ' · ' . escapeHtml($section) ?></dd></div><div><dt>College</dt><dd><?= escapeHtml($student['organization_name']) ?></dd></div><div><dt>School email</dt><dd><?= escapeHtml($student['email']) ?></dd></div></dl></div></section><section class="portal-account-settings"><h3>Account settings</h3><a class="portal-settings-row" href="<?= escapeHtml(applicationUrl('/student/password.php')) ?>" data-open-password-modal><span class="portal-shortcut-icon tone-blue"><?= studentDashboardIcon('key') ?></span><span><strong>Change password</strong><small>Keep your account secure</small></span><?= studentDashboardIcon('chevron') ?></a><div class="portal-signout"><p>Finished for now?</p><?= $logoutForm ?></div><p class="portal-account-help">Need to update your student details?<br>Contact the Registrar.</p></section></div>
        </section>
