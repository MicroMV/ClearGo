<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/event_functions.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$user = requireRole('ORG_STAFF');
$search = trim((string) ($_GET['search'] ?? ''));
$filter = (string) ($_GET['filter'] ?? 'ALL');
$allowedFilters = ['ALL', 'ELIGIBLE', 'PENDING', 'APPROVED', 'RETURNED'];
if (!in_array($filter, $allowedFilters, true)) { $filter = 'ALL'; }
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;

try {
    $database = getDatabaseConnection();
    $readiness = officeReadiness($database, 'ORGANIZATION');
    $organization = assignedOrganization($database, $user['id']);
    $summary = organizationStatusSummary($database, (int) $organization['id']);
    $eventPeriod = activeEventPeriod($database);
    $events = organizationEvents($database, (int) $organization['id'], (int) $eventPeriod['id']);
    startSecureSession();
    $qrDisplay = is_array($_SESSION['organization_qr_display'] ?? null)
        ? $_SESSION['organization_qr_display'] : [];
    $studentQuery = $database->prepare(
        "SELECT students.id, students.student_number, students.first_name, students.last_name,
                students.year_level, users.email,
                clearances.id AS clearance_id, clearance_stations.status AS clearance_status,
                clearance_stations.returned_reason, dean_clearance.status AS dean_status
         FROM students
         INNER JOIN users ON users.id = students.user_id
         INNER JOIN academic_periods ON academic_periods.status = 'ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=academic_periods.id AND enrollment.student_id=students.id AND enrollment.status='ENROLLED'
         LEFT JOIN clearances ON clearances.student_id = students.id AND clearances.academic_period_id = academic_periods.id
         LEFT JOIN stations ON stations.code = 'ORGANIZATION'
         LEFT JOIN clearance_stations ON clearance_stations.clearance_id = clearances.id AND clearance_stations.station_id = stations.id
         LEFT JOIN stations dean_station ON dean_station.code='DEAN'
         LEFT JOIN clearance_stations dean_clearance ON dean_clearance.clearance_id=clearances.id
            AND dean_clearance.station_id=dean_station.id
         WHERE students.organization_id = :organization_id AND students.status = 'ACTIVE'
           AND (students.student_number LIKE :student_number_search OR students.first_name LIKE :first_name_search
                OR students.last_name LIKE :last_name_search OR users.email LIKE :email_search)
         ORDER BY students.last_name, students.first_name"
    );
    $searchTerm = '%' . $search . '%';
    $studentQuery->execute([
        'organization_id' => $organization['id'],
        'student_number_search' => $searchTerm,
        'first_name_search' => $searchTerm,
        'last_name_search' => $searchTerm,
        'email_search' => $searchTerm,
    ]);
    $students = $studentQuery->fetchAll();

    $rows = [];
    foreach ($students as $student) {
        $eligibility = organizationEligibility($database, $organization['id'], (int) $student['id']);
        $status = $student['clearance_status'];
        if ($status === 'APPROVED' || $status === 'RETURNED') {
            $displayStatus = $status;
        } elseif ($eligibility['eligible'] && !empty($student['clearance_id'])
            && ($student['dean_status'] === 'APPROVED' || $organization['code'] === 'CBE')) {
            $displayStatus = 'ELIGIBLE';
        } else {
            $displayStatus = 'PENDING';
        }
        $student['eligibility'] = $eligibility;
        $student['display_status'] = $displayStatus;
        $rows[] = $student;
    }

    $selectedStudent = null;
    $studentId = filter_var($_GET['student'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($studentId !== false) {
        foreach ($rows as $row) { if ((int) $row['id'] === $studentId) { $selectedStudent = $row; break; } }
    }
    $selectedWaitingForDean = $selectedStudent !== null
        && $selectedStudent['dean_status'] !== 'APPROVED'
        && $organization['code'] !== 'CBE';
    $filteredRows = array_values(array_filter($rows, static fn(array $row): bool =>
        $filter === 'ALL' || $row['display_status'] === $filter
    ));
    $totalRows = count($filteredRows);
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $rows = array_slice($filteredRows, ($page - 1) * $pageSize, $pageSize);
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Organization dashboard failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the organization dashboard right now. Please try again later.');
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$pageTitle = $organization['code'] === 'CBE' ? 'YBC Organization Clearance' : 'Organization Clearance';
$sidebar = staffDashboardSidebar($pageTitle, $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/organization/dashboard.php', 'icon' => 'dashboard'],
], 'dashboard', $logoutForm);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Clearance | Digital Clearance System</title>
    <style>
        :root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}header{background:#fff;border-bottom:1px solid var(--line);padding:18px 24px}header div,main{max-width:1100px;margin:auto}.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px}.header-copy{display:block}.logout-form{margin:0}h1{margin:0;color:var(--blue);font-size:24px}h2{font-size:19px;margin-top:0}main{padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}.summary{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.summary div{background:#f4f8fc;border-radius:7px;padding:12px}.summary strong{display:block;font-size:24px;color:var(--blue)}form.controls,.event-form,.inline{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.event-form label{display:grid;gap:5px;color:#587086;font-size:13px;flex:1}.event-form label:first-child{flex:2}input,select,button,textarea{border:1px solid #b7c8d8;border-radius:5px;font:inherit;padding:9px}input{min-width:180px}button{background:var(--blue);border:0;color:#fff;cursor:pointer}button.secondary{background:#587086}.message{padding:12px;background:#edf6ff;border-left:4px solid var(--blue)}table{border-collapse:collapse;width:100%}th,td{border-top:1px solid var(--line);padding:12px;text-align:left;vertical-align:middle}th{font-size:13px;color:#587086}.badge{border-radius:99px;font-size:12px;font-weight:bold;padding:6px 9px}.approved{background:#e4f5eb;color:#176b3a}.eligible{background:#edf4fb;color:#165b94}.pending{background:#fff7e8;color:#956200}.returned{background:#fff0ed;color:#a33722}.events{list-style:none;padding:0}.events li{border-top:1px solid var(--line);padding:10px 0}.yes{color:#176b3a}.no{color:#a33722}.muted{color:#587086;font-size:13px}@media(max-width:760px){main,header{padding:16px}.summary{grid-template-columns:repeat(2,1fr)}.table-wrap{overflow-x:auto}.event-form{display:grid}.event-form label,input{min-width:0;width:100%}}
        .notification-center{position:relative}.notification-toggle{position:relative;min-width:42px;min-height:40px;padding:6px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--dark);font-size:18px}.notification-count{position:absolute;top:-7px;right:-7px;min-width:18px;padding:1px 5px;border:2px solid #fff;border-radius:99px;background:#a33722;color:#fff;font-size:10px;font-weight:bold;line-height:16px}.notification-menu{position:absolute;z-index:20;top:calc(100% + 10px);right:0;width:min(380px,calc(100vw - 32px));max-height:440px;overflow:auto;border:1px solid var(--line);border-radius:10px;background:#fff;box-shadow:0 18px 48px rgba(23,50,77,.18)}.notification-heading{display:flex;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--line)}.notification-heading span,.notification-item time{color:#587086;font-size:11px}.notification-menu ul{margin:0;padding:0;list-style:none}.notification-item{display:grid;gap:3px;padding:13px 16px;border-bottom:1px solid var(--line)}.notification-item.unread{background:#f4f9ff}.notification-item span{color:#4f6278;font-size:13px}.notification-empty{padding:24px 16px;color:#587086;text-align:center}.topbar-actions{display:flex;gap:10px;align-items:center}.pagination{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:14px}.pagination-link,.pagination-ellipsis{display:grid;place-items:center;min-width:42px;height:42px;padding:0 12px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--dark);font-weight:600;text-decoration:none}.pagination-link.active{border-color:var(--blue);background:var(--blue);color:#fff}.pagination-ellipsis{border:0;background:transparent}
    </style>
    <style>.header-brand img{width:60px!important;height:60px!important;object-fit:cover!important;object-position:center}</style>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
<?= $sidebar ?>
<div class="staff-workspace">
<header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button><div class="topbar-title"><h1><?= escapeHtml($pageTitle) ?></h1><p><?= escapeHtml($organization['name'] . ' (' . $organization['code'] . ')') ?> &middot; Manage attendance and organization clearance.</p></div><div class="topbar-actions"><div class="topbar-account"><strong><?= escapeHtml($user['email']) ?></strong><span>Organization staff account</span></div><?= dashboardNotificationBell($notifications) ?></div></header>
<main class="staff-main">
    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
    <?php if ($readiness['clearance_status'] === 'PREPARING'): ?><section class="panel"><h2>Semester preparation</h2><p>Review attendance and take action now. Approve complete students or return incomplete records before confirming readiness.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">Organization clearance is marked READY.</p><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input name="notes" maxlength="500" placeholder="Optional readiness note"><button type="submit" name="action" value="ready">Mark Organization Ready</button></form><?php endif; ?></section><?php endif; ?>
    <section class="panel">
        <h2>QR Event Attendance</h2>
        <p>Create each required <?= $organization['code'] === 'CBE' ? 'YBC' : ($organization['code'] === 'CTE' ? 'FEO' : 'CCS organization') ?> event. During the event, open a short-lived QR code for students to scan.</p>
        <form class="event-form" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="create">
            <label>Event name<input name="name" maxlength="200" required placeholder="Required organization event"></label>
            <label>Date<input name="event_date" type="date" required value="<?= escapeHtml(date('Y-m-d')) ?>"></label>
            <button type="submit">Create Required Event</button>
        </form>
        <?php if ($events === []): ?><p class="muted">No required events have been created for this semester. Until an event is created, direct organization sign-off remains available.</p><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Event</th><th>Date / Time</th><th>Attendance</th><th>QR Check-in</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($events as $event): ?><?php $qrOpen = (int) $event['qr_is_open'] === 1; ?><tr>
            <td><strong><?= escapeHtml($event['name']) ?></strong></td><td><?= escapeHtml($event['event_date']) ?><br><span class="muted"><?= escapeHtml(trim(($event['start_time'] ?? '') . ' - ' . ($event['end_time'] ?? ''), ' -')) ?></span></td><td><?= (int) $event['present_count'] ?> / <?= (int) $event['enrolled_count'] ?> present</td>
            <td><?php if ($qrOpen): ?><span class="badge approved">OPEN</span> until <?= escapeHtml($event['check_in_closes_at']) ?><div class="inline"><?php if ((int) ($qrDisplay['event_id'] ?? 0) === (int) $event['id']): ?><a href="<?= escapeHtml(applicationUrl('/organization/event_qr.php?event=' . (int) $event['id'])) ?>">Display QR</a><?php endif; ?><form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="secondary" type="submit">Close QR</button></form></div><?php else: ?><form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="open_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><label class="muted">Attendance time limit<select name="minutes" aria-label="Attendance time limit"><option value="5">5 minutes</option><option value="10" selected>10 minutes</option><option value="15">15 minutes</option><option value="30">30 minutes</option><option value="60">1 hour</option><option value="120">2 hours</option><option value="240">4 hours</option><option value="480">8 hours</option><option value="720">12 hours</option></select></label><button type="submit">Open check-in</button></form><?php endif; ?></td>
            <td><form method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>" onsubmit="return confirm('Delete this event and all of its attendance records?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="danger" type="submit">Delete</button></form></td>
        </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <section class="panel"><div class="section-heading"><div><h2>Clearance summary</h2><p class="muted">Current organization status across active students.</p></div></div><div class="summary"><div>Total Students<strong><?= $summary['TOTAL'] ?></strong></div><div>Eligible<strong><?= $summary['ELIGIBLE'] ?></strong></div><div>Pending<strong><?= $summary['PENDING'] ?></strong></div><div>Cleared<strong><?= $summary['APPROVED'] ?></strong></div><div>Returned<strong><?= $summary['RETURNED'] ?></strong></div></div></section>
    <?php $organizationFilterLabels = ['ALL' => 'All Students', 'ELIGIBLE' => 'Ready to Clear', 'PENDING' => 'Pending', 'APPROVED' => 'Cleared', 'RETURNED' => 'Returned']; ?>
    <section class="panel"><div class="section-heading"><div><h2>Find student records</h2><p class="muted">Search the active roster or focus on a clearance status.</p></div></div><form class="controls" method="get"><input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search number, name, or email" aria-label="Search students"><select name="filter" aria-label="Filter by clearance status"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml($organizationFilterLabels[$option]) ?></option><?php endforeach; ?></select><button type="submit">Search</button></form></section>
    <section class="panel"><div class="section-heading"><div><h2>Student clearance records</h2><p class="muted">Review requirements and clear students who are eligible.</p></div></div>
        <?php if ($rows === []): ?><p>No active students are assigned to this organization.</p><?php else: ?>
        <form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="action" value="approve_selected">
            <div class="table-wrap"><table>
                <thead><tr><th>No.</th><th>Student ID</th><th>Student Name</th><th>Year Level</th><th>Requirements</th><th>Clearance Status</th><th>Action</th></tr></thead>
                <tbody><?php $rowNumber = ($page - 1) * $pageSize; foreach ($rows as $student): $rowNumber++; ?><tr>
                    <td><?= $rowNumber ?></td><td><?= escapeHtml($student['student_number']) ?></td>
                    <td><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></td><td>Year <?= (int) $student['year_level'] ?></td>
                    <td><?php if ($student['eligibility']['events'] === []): ?><?= $organization['code'] === 'CBE' ? 'Direct YBC sign-off' : 'Direct organization sign-off' ?><?php else: ?><?= count($student['eligibility']['events']) - count(array_filter($student['eligibility']['events'], static fn(array $event): bool => !(int) $event['attended'])) ?>/<?= count($student['eligibility']['events']) ?> events attended<?php endif; ?></td>
                    <td><span class="badge <?= strtolower($student['display_status']) ?>"><?= $student['display_status'] === 'APPROVED' ? 'CLEARED' : escapeHtml($student['display_status']) ?></span></td>
                    <td><?php if (in_array($readiness['clearance_status'], ['PREPARING','OPEN'], true) && $student['display_status'] === 'ELIGIBLE'): ?><button name="student_ids[]" value="<?= (int) $student['id'] ?>" type="submit">Clear</button><?php elseif ($student['display_status'] === 'APPROVED'): ?><span class="muted">Cleared</span><?php elseif ($organization['code'] !== 'CBE' && $student['dean_status'] !== 'APPROVED'): ?><span class="muted">Waiting for Dean</span><?php else: ?><span class="muted">Requirements incomplete</span><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <p>Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p>
            <?= renderPagination($page, $totalPages, ['search' => $search, 'filter' => $filter]) ?>
        </form><?php endif; ?>
    </section>
    <?php if ($selectedStudent !== null): ?><section class="panel"><h2><?= escapeHtml($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></h2><p><?= escapeHtml($selectedStudent['student_number']) ?> | <?= escapeHtml($selectedStudent['email']) ?> | <?= escapeHtml($organization['code']) ?></p><p><strong>Organization clearance:</strong> <?= escapeHtml($selectedStudent['display_status']) ?></p><h3>Required Organization Events</h3><?php if ($selectedStudent['eligibility']['events'] === []): ?><p>No event attendance is required. This student is available for direct <?= $organization['code'] === 'CBE' ? 'YBC' : 'organization' ?> sign-off.</p><?php else: ?><ul class="events"><?php foreach ($selectedStudent['eligibility']['events'] as $event): ?><li><strong><?= escapeHtml($event['name']) ?></strong> <span class="<?= (int) $event['attended'] === 1 ? 'yes' : 'no' ?>"><?= (int) $event['attended'] === 1 ? 'PRESENT' : 'NOT ATTENDED' ?></span></li><?php endforeach; ?></ul><?php endif; ?><p><?= escapeHtml($selectedStudent['eligibility']['reason'] ?? 'All required attendance is complete.') ?></p><?php if ($selectedWaitingForDean): ?><p class="message">Waiting for Dean clearance. Organization clearance becomes available after the Dean clears this student.</p><?php elseif ($selectedStudent['display_status'] === 'ELIGIBLE'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_ids[]" value="<?= (int) $selectedStudent['id'] ?>"><button name="action" value="approve_selected">Clear</button></form><?php elseif (!empty($selectedStudent['clearance_id']) && $selectedStudent['display_status'] !== 'APPROVED'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="return"><input type="hidden" name="student_id" value="<?= (int) $selectedStudent['id'] ?>"><p><textarea name="reason" required maxlength="1000" placeholder="Reason for return"></textarea></p><button class="danger" type="submit">Return Student</button></form><?php endif; ?></section><?php endif; ?>
</main></div></div><script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script>
</body>
</html>
