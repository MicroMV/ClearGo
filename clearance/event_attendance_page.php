<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/readiness_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';
require_once __DIR__ . '/../organization/event_functions.php';
require_once __DIR__ . '/../student-council/event_functions.php';

function renderEventAttendancePage(string $role, string $eventSource): void
{
    $user = requireRole($role);
    $database = getDatabaseConnection();
    $organization = null;
    if ($eventSource === 'ORGANIZATION') {
        $organization = assignedOrganization($database, (int) $user['id']);
    }

    $period = $database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        http_response_code(500);
        exit('There is no active semester.');
    }

    if ($eventSource === 'ORGANIZATION') {
        $eventQuery = $database->prepare(
            "SELECT event.id,event.name,event.event_date
             FROM events event
             INNER JOIN stations station ON station.id=event.station_id AND station.code='ORGANIZATION'
             WHERE event.academic_period_id=:period_id AND event.organization_id=:organization_id
             ORDER BY event.event_date DESC,event.id DESC"
        );
        $eventQuery->execute(['period_id' => $period['id'], 'organization_id' => $organization['id']]);
    } else {
        $eventQuery = $database->prepare(
            "SELECT event.id,event.name,event.event_date
             FROM events event
             INNER JOIN stations station ON station.id=event.station_id AND station.code='STUDENT_COUNCIL'
             WHERE event.academic_period_id=:period_id
             ORDER BY event.event_date DESC,event.id DESC"
        );
        $eventQuery->execute(['period_id' => $period['id']]);
    }
    $events = $eventQuery->fetchAll();

    $selectedEvent = $events[0] ?? null;
    $requestedEventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($requestedEventId !== false) {
        foreach ($events as $event) {
            if ((int) $event['id'] === (int) $requestedEventId) {
                $selectedEvent = $event;
                break;
            }
        }
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 50;
    $totalRows = 0;
    $totalPages = 1;
    $attendees = [];
    if ($selectedEvent !== null) {
        $term = '%' . $search . '%';
        $parameters = ['event_id' => $selectedEvent['id'], 'number_search' => $term, 'first_search' => $term, 'last_search' => $term, 'program_search' => $term];
        $attendanceFrom = "FROM event_attendance attendance
            INNER JOIN students student ON student.id=attendance.student_id
            INNER JOIN organizations organization ON organization.id=student.organization_id
            WHERE attendance.event_id=:event_id AND attendance.status='PRESENT'
              AND (student.student_number LIKE :number_search OR student.first_name LIKE :first_search OR student.last_name LIKE :last_search OR student.program LIKE :program_search)";
        $countQuery = $database->prepare("SELECT COUNT(*) {$attendanceFrom}");
        $countQuery->execute($parameters);
        $totalRows = (int) $countQuery->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $attendanceQuery = $database->prepare(
            "SELECT student.student_number,student.first_name,student.last_name,student.program,
                    student.year_level,student.section,organization.code AS college,
                    attendance.check_in_method,attendance.recorded_at
             {$attendanceFrom}
             ORDER BY student.last_name,student.first_name
             LIMIT {$pageSize} OFFSET {$offset}"
        );
        $attendanceQuery->execute($parameters);
        $attendees = $attendanceQuery->fetchAll();
    }

    $managementEvents = $eventSource === 'ORGANIZATION'
        ? organizationEvents($database, (int) $organization['id'], (int) $period['id'])
        : studentCouncilEvents($database, (int) $period['id']);
    startSecureSession();
    $qrDisplay = is_array($_SESSION['organization_qr_display'] ?? null) ? $_SESSION['organization_qr_display'] : [];
    $csrfToken = createCsrfToken();
    $message = pullFlashMessage();
    $notifications = dashboardNotifications($database, (int) $user['id']);
    if ($eventSource === 'ORGANIZATION') {
        $title = ($organization['code'] === 'CBE' ? 'YBC Organization' : 'Organization') . ' Event Attendance';
        $sidebar = staffDashboardSidebar($organization['code'] === 'CBE' ? 'YBC Organization Clearance' : 'Organization Clearance', $user, [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/organization/dashboard.php', 'icon' => 'dashboard'],
            ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => '/organization/attendance.php', 'icon' => 'document'],
        ], 'attendance', dashboardLogoutForm());
        $pageUrl = '/organization/attendance.php';
    } else {
        $title = 'Student Council Event Attendance';
        $sidebar = staffDashboardSidebar('Student Council Clearance', $user, [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/student-council/dashboard.php', 'icon' => 'dashboard'],
            ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => '/student-council/attendance.php', 'icon' => 'document'],
        ], 'attendance', dashboardLogoutForm());
        $pageUrl = '/student-council/attendance.php';
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= escapeHtml($title) ?> | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/event-attendance.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/event-attendance.css') ?>">
</head>
<body class="staff-dashboard"><div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
<header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button><div class="topbar-title"><h1><?= escapeHtml($title) ?></h1><p>Manage required events, open QR check-in, and view attendance records.</p></div><div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div></header>
<main class="staff-main">
<?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
<?php
    if ($eventSource === 'ORGANIZATION') {
        $attendanceEvents = $events;
        $events = $managementEvents;
        require __DIR__ . '/organization_event_management.php';
        $events = $attendanceEvents;
    } else {
        $councilEvents = $managementEvents;
        require __DIR__ . '/council_event_management.php';
    }
?>
<section class="panel"><div class="section-heading"><div><h2>Attendance records</h2><p class="muted"><?= escapeHtml($period['semester'] . ' Semester A.Y. ' . $period['school_year']) ?></p></div></div>
<?php if ($events === []): ?><p class="muted">No events have been created for this semester.</p><?php else: ?>
<form class="attendance-controls" method="get"><label>Event<select name="event" onchange="this.form.submit()"><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= (int) $event['id'] === (int) $selectedEvent['id'] ? 'selected' : '' ?>><?= escapeHtml($event['name'] . ' - ' . $event['event_date']) ?></option><?php endforeach; ?></select></label><label>Search student<input type="search" name="search" value="<?= escapeHtml($search) ?>" placeholder="Student ID, name, or program"></label><button type="submit">Search</button></form>
<div class="attendance-summary"><strong><?= $totalRows ?></strong><span>student<?= $totalRows === 1 ? '' : 's' ?> present</span></div>
<?php if ($attendees === []): ?><p class="muted">No present students match this view.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>No.</th><th>Student ID</th><th>Student Name</th><th>Program</th><th>Year Level</th><th>College</th><th>Check-in</th><th>Recorded At</th></tr></thead><tbody><?php foreach ($attendees as $index => $attendee): ?><tr><td><?= (($page - 1) * $pageSize) + $index + 1 ?></td><td><strong><?= escapeHtml($attendee['student_number']) ?></strong></td><td><?= escapeHtml($attendee['last_name'] . ', ' . $attendee['first_name']) ?></td><td><?= escapeHtml($attendee['program']) ?></td><td>Year <?= (int) $attendee['year_level'] ?></td><td><?= escapeHtml($attendee['college']) ?></td><td><span class="badge approved"><?= escapeHtml($attendee['check_in_method']) ?></span></td><td><?= escapeHtml(date('M j, Y g:i A', strtotime($attendee['recorded_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Attendance pages"><?php foreach (paginationPages($page, $totalPages) as $pageNumber): ?><?php if ($pageNumber === 'ellipsis'): ?><span class="pagination-ellipsis">&hellip;</span><?php else: ?><a class="pagination-link <?= $pageNumber === $page ? 'active' : '' ?>" href="<?= escapeHtml(applicationUrl($pageUrl)) ?>?event=<?= (int) $selectedEvent['id'] ?>&amp;search=<?= urlencode($search) ?>&amp;page=<?= $pageNumber ?>"><?= $pageNumber ?></a><?php endif; ?><?php endforeach; ?></nav><?php endif; ?>
<?php endif; ?></section></main></div></div><script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script></body></html>
<?php
}
