<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/event_functions.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';
require_once __DIR__ . '/../clearance/report_workbook.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';
require_once __DIR__ . '/../clearance/remarks_functions.php';

$user = requireRole('ORG_STAFF');
$search = trim((string) ($_GET['search'] ?? ''));
$filter = (string) ($_GET['filter'] ?? 'ALL');
$courseFilter = trim((string) ($_GET['course'] ?? ''));
$yearFilter = filter_var($_GET['year_level'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]) ?: 0;
$allowedFilters = ['ALL', 'ELIGIBLE', 'PENDING', 'APPROVED', 'RETURNED'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'ALL';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;

try {
    $database = getDatabaseConnection();
    $readiness = officeReadiness($database, 'ORGANIZATION');
    $organization = assignedOrganization($database, $user['id']);
    $bulkReadyCount = count(bulkReadyClearanceIds($database, 'ORGANIZATION', (int) $organization['id']));
    $summary = organizationStatusSummary($database, (int) $organization['id']);
    $studentQuery = $database->prepare(
        "SELECT students.id, students.student_number, students.first_name, students.last_name,
                students.program, students.year_level, users.email,
                clearances.id AS clearance_id, clearance_stations.status AS clearance_status,
                clearance_stations.returned_reason, clearance_stations.office_remarks, dean_clearance.status AS dean_status
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
                OR students.last_name LIKE :last_name_search OR students.program LIKE :program_search
                OR users.email LIKE :email_search)
         ORDER BY students.last_name, students.first_name"
    );
    $searchTerm = '%' . $search . '%';
    $studentQuery->execute([
        'organization_id' => $organization['id'],
        'student_number_search' => $searchTerm,
        'first_name_search' => $searchTerm,
        'last_name_search' => $searchTerm,
        'program_search' => $searchTerm,
        'email_search' => $searchTerm,
    ]);
    $students = $studentQuery->fetchAll();
    $courses = array_values(array_unique(array_column($students, 'program')));
    sort($courses, SORT_NATURAL | SORT_FLAG_CASE);
    $yearLevels = array_values(array_unique(array_map(static fn(array $student): int => (int) $student['year_level'], $students)));
    sort($yearLevels, SORT_NUMERIC);

    $rows = [];
    foreach ($students as $student) {
        $eligibility = organizationEligibility($database, $organization['id'], (int) $student['id']);
        $status = $student['clearance_status'];
        if ($status === 'APPROVED' || $status === 'RETURNED') {
            $displayStatus = $status;
        } elseif (
            $eligibility['eligible'] && !empty($student['clearance_id'])
            && ($student['dean_status'] === 'APPROVED' || $organization['code'] === 'CBE')
        ) {
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
        foreach ($rows as $row) {
            if ((int) $row['id'] === $studentId) {
                $selectedStudent = $row;
                break;
            }
        }
    }
    $selectedWaitingForDean = $selectedStudent !== null
        && $selectedStudent['dean_status'] !== 'APPROVED'
        && $organization['code'] !== 'CBE';
    $filteredRows = array_values(array_filter($rows, static function (array $row) use ($filter, $courseFilter, $yearFilter): bool {
        if ($courseFilter !== '' && $row['program'] !== $courseFilter) {
            return false;
        }
        if ($yearFilter !== 0 && (int) $row['year_level'] !== $yearFilter) {
            return false;
        }
        return $filter === 'ALL' || $row['display_status'] === $filter;
    }));
    $totalRows = count($filteredRows);
    if (($_GET['export'] ?? '') === 'xlsx') {
        $reportRows = [];
        foreach ($filteredRows as $student) {
            $events = $student['eligibility']['events'];
            $attended = count(array_filter($events, static fn(array $event): bool => (int) $event['attended'] === 1));
            $requirements = $events === []
                ? ($organization['code'] === 'CBE' ? 'Direct YBC sign-off' : 'Direct organization sign-off')
                : $attended . '/' . count($events) . ' event requirements satisfied';
            $reportRows[] = [
                $student['student_number'],
                $student['last_name'] . ', ' . $student['first_name'],
                $student['program'],
                'Year ' . (int) $student['year_level'],
                $student['email'],
                $requirements,
                str_replace('_', ' ', (string) ($student['dean_status'] ?: 'PENDING')),
                $student['display_status'] === 'APPROVED' ? 'CLEARED' : str_replace('_', ' ', $student['display_status']),
                $student['returned_reason'] ?: '',
                (string) ($student['office_remarks'] ?? ''),
            ];
        }
        $workbook = createClearanceReportXlsx(
            $organization['name'] . ' Clearance Report',
            activePeriodReportLabel($database),
            ['Student Number', 'Full Name', 'Course', 'Year Level', 'Email', 'Requirements', 'Dean Status', 'Clearance Status', 'Return Reason', 'Remarks'],
            [['name' => $organization['code'], 'rows' => $reportRows]]
        );
        downloadClearanceReport($workbook, strtolower($organization['code']) . '-organization-clearance-report.xlsx');
    }
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
    ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => '/organization/attendance.php', 'icon' => 'document'],
    ['key' => 'sanctions', 'label' => 'Sanction Submissions', 'url' => '/organization/sanction_submissions.php', 'icon' => 'document'],
], 'dashboard', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Clearance | Digital Clearance System</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/organization-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1><?= escapeHtml($pageTitle) ?></h1>
                    <p><?= escapeHtml($organization['name'] . ' (' . $organization['code'] . ')') ?> &middot; Manage attendance and organization clearance.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Clearance summary</h2>
                            <p class="muted">Current organization status across active students.</p>
                        </div>
                        <a class="button-link summary-export" href="?search=<?= urlencode($search) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;filter=<?= urlencode($filter) ?>&amp;export=xlsx">Export Excel report</a>
                    </div>
                    <div class="summary">
                        <?php foreach (['ALL' => ['Total Students', $summary['TOTAL']], 'ELIGIBLE' => ['Eligible', $summary['ELIGIBLE']], 'PENDING' => ['Pending', $summary['PENDING']], 'APPROVED' => ['Cleared', $summary['APPROVED']], 'RETURNED' => ['Returned', $summary['RETURNED']]] as $summaryFilter => [$summaryLabel, $summaryCount]): ?>
                            <a class="summary-filter" href="?filter=<?= $summaryFilter ?>#student-records" <?= $filter === $summaryFilter ? 'aria-current="true"' : '' ?>><?= escapeHtml($summaryLabel) ?><strong><?= $summaryCount ?></strong></a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <?php if ($readiness['clearance_status'] === 'PREPARING'): ?><section class="panel">
                    <h2>Semester preparation</h2>
                    <p>Save and select an authorized signature in <a href="<?= escapeHtml(applicationUrl('/auth/profile.php#signature-card-title')) ?>">My Profile</a> before marking Ready. Signatures lock while Ready.</p>
                        <p>Review attendance and take action now. Approve complete students or return incomplete records before confirming readiness.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">Organization clearance is marked READY.</p>
                            <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="ready">Mark Organization Ready</button></form><?php endif; ?>
                    </section><?php endif; ?>

                <?php $organizationFilterLabels = ['ALL' => 'All Students', 'ELIGIBLE' => 'Ready to Clear', 'PENDING' => 'Pending', 'APPROVED' => 'Cleared', 'RETURNED' => 'Returned']; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Find student records</h2>
                            <p class="muted">Search the active roster or focus on a clearance status.</p>
                        </div>
                    </div>
                    <form class="controls" method="get"><input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search number, name, email, or program" aria-label="Search students"><select name="course" aria-label="Filter by course">
                            <option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= escapeHtml($course) ?>" <?= $courseFilter === $course ? 'selected' : '' ?>><?= escapeHtml($course) ?></option><?php endforeach; ?>
                        </select><select name="year_level" aria-label="Filter by year level">
                            <option value="0">All year levels</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endforeach; ?>
                        </select><select name="filter" aria-label="Filter by clearance status"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml($organizationFilterLabels[$option]) ?></option><?php endforeach; ?></select><button type="submit">Search</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl('/organization/dashboard.php')) ?>">Clear</a></form>
                </section>
                <section class="panel" id="student-records" tabindex="-1">
                    <div class="section-heading">
                        <div>
                            <h2>Student clearance records</h2>
                            <p class="muted">Review requirements and clear students who are eligible.</p>
                        </div>
                    </div>
                    <?php if ($rows === []): ?><p>No active students are assigned to this organization.</p><?php else: ?>

                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Program</th>
                                            <th>Year Level</th>
                                            <th>Requirements</th>
                                            <th>Clearance Status</th>
                                            <th>Action</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php $rowNumber = ($page - 1) * $pageSize;
                                                                                                            foreach ($rows as $student): $rowNumber++; ?><tr>
                                                <td><?= $rowNumber ?></td>
                                                <td><?= escapeHtml($student['student_number']) ?></td>
                                                <td><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                                <td><?= escapeHtml($student['program']) ?></td>
                                                <td>Year <?= (int) $student['year_level'] ?></td>
                                                <td><?php if ($student['eligibility']['events'] === []): ?><?= $organization['code'] === 'CBE' ? 'Direct YBC sign-off' : 'Direct organization sign-off' ?><?php else: ?><?= count($student['eligibility']['events']) - count(array_filter($student['eligibility']['events'], static fn(array $event): bool => !(int) $event['attended'])) ?>/<?= count($student['eligibility']['events']) ?> event requirements satisfied<?php endif; ?></td>
                                                <td><span class="badge <?= strtolower($student['display_status']) ?>"><?= $student['display_status'] === 'APPROVED' ? 'CLEARED' : escapeHtml(statusDisplayLabel($student['display_status'])) ?></span></td>
                                                <td><?php if (in_array($readiness['clearance_status'], ['PREPARING', 'OPEN'], true) && $student['display_status'] === 'ELIGIBLE'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="approve_selected"><button name="student_ids[]" value="<?= (int) $student['id'] ?>" type="submit">Clear</button></form><?php elseif ($student['display_status'] === 'APPROVED'): ?><span class="muted">Cleared</span><?php elseif ($organization['code'] !== 'CBE' && $student['dean_status'] !== 'APPROVED'): ?><span class="muted">Waiting for Dean</span><?php else: ?><span class="muted">Requirements incomplete</span><?php endif; ?></td>
                                            <td><?= renderOfficeRemarksButton((int) ($student['clearance_id'] ?? 0), (string) ($student['office_remarks'] ?? ''), $student['first_name'] . ' ' . $student['last_name'], in_array($readiness['clearance_status'], ['PREPARING', 'OPEN'], true) && $student['clearance_status'] !== 'APPROVED') ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                            <p>Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p>
                        <?php endif; ?>
                    <?= renderBulkClearanceFooter($page, $totalPages, ['search' => $search, 'course' => $courseFilter, 'year_level' => $yearFilter, 'filter' => $filter], applicationUrl('/organization/action.php'), $bulkReadyCount, $organization['code'] . ' Organization clearance') ?>
                </section>
                <?php if ($selectedStudent !== null): ?><section class="panel">
                        <h2><?= escapeHtml($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></h2>
                        <p><?= escapeHtml($selectedStudent['student_number']) ?> | <?= escapeHtml($selectedStudent['email']) ?> | <?= escapeHtml($organization['code']) ?></p>
                        <p><strong>Organization clearance:</strong> <?= escapeHtml(statusDisplayLabel($selectedStudent['display_status'])) ?></p>
                        <h3>Required Organization Events</h3><?php if ($selectedStudent['eligibility']['events'] === []): ?><p>No event attendance is required. This student is available for direct <?= $organization['code'] === 'CBE' ? 'YBC' : 'organization' ?> sign-off.</p><?php else: ?><ul class="events"><?php foreach ($selectedStudent['eligibility']['events'] as $event): ?><li><strong><?= escapeHtml($event['name']) ?></strong> <span class="<?= (int) $event['attended'] === 1 ? 'yes' : 'no' ?>"><?= ($event['attendance_status'] ?? '') === 'PRESENT' ? 'PRESENT' : (!empty($event['sanction_verified_at']) ? 'SANCTION VERIFIED' : 'NOT ATTENDED') ?></span></li><?php endforeach; ?></ul><?php endif; ?><p><?= escapeHtml($selectedStudent['eligibility']['reason'] ?? 'All event requirements are satisfied.') ?></p><?php if ($selectedWaitingForDean): ?><p class="message">Waiting for Dean clearance. Organization clearance becomes available after the Dean clears this student.</p><?php elseif ($selectedStudent['display_status'] === 'ELIGIBLE'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_ids[]" value="<?= (int) $selectedStudent['id'] ?>"><button name="action" value="approve_selected">Clear</button></form><?php elseif (!empty($selectedStudent['clearance_id']) && $selectedStudent['display_status'] !== 'APPROVED'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="return"><input type="hidden" name="student_id" value="<?= (int) $selectedStudent['id'] ?>">
                                <p><textarea name="reason" required maxlength="1000" placeholder="Reason for return"></textarea></p><button class="danger" type="submit">Return Student</button>
                            </form><?php endif; ?>
                    </section><?php endif; ?>
            </main>
        </div>
    </div>
    <?= renderOfficeRemarksModal($_GET) ?>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>

</html>
