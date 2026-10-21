<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/office_workflow.php';
require_once __DIR__ . '/readiness_functions.php';
require_once __DIR__ . '/report_workbook.php';
require_once __DIR__ . '/bulk_clearance.php';
require_once __DIR__ . '/remarks_functions.php';
require_once __DIR__ . '/../bookstore/obligation_functions.php';

function officeDetailRecords(PDO $database, string $code, array $row): array
{
    $studentId = (int) $row['student_id'];
    $periodId = (int) $row['academic_period_id'];
    $records = [];
    if ($code === 'STUDENT_COUNCIL') {
        $statement = $database->prepare('SELECT rr.id,rr.status,rr.remarks,r.name,r.description FROM requirement_records rr INNER JOIN requirements r ON r.id=rr.requirement_id WHERE rr.clearance_station_id=:station_id ORDER BY r.id');
        $statement->execute(['station_id' => $row['clearance_station_id']]);
        $records = $statement->fetchAll();
    } elseif ($code === 'BOOKSTORE') {
        $statement = $database->prepare("SELECT id,description,amount,status,payment_reference,remarks,created_at,cleared_at FROM bookstore_obligations WHERE student_id=:student_id AND academic_period_id=:period_id ORDER BY status DESC,id DESC");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $records = $statement->fetchAll();
    } elseif ($code === 'FACILITIES') {
        $statement = $database->prepare("SELECT id,item_type,description,status,remarks,created_at,returned_at FROM facilities_obligations WHERE student_id=:student_id AND academic_period_id=:period_id ORDER BY status,item_type DESC,created_at DESC");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $records = $statement->fetchAll();
    } elseif ($code === 'COMLAB') {
        $statement = $database->prepare("SELECT incident.id,incident.description,incident.status,incident.created_at,incident.resolved_at FROM violations incident INNER JOIN stations station ON station.id=incident.station_id AND station.code='COMLAB' WHERE incident.student_id=:student_id ORDER BY incident.status,incident.created_at DESC");
        $statement->execute(['student_id' => $studentId]);
        $records = $statement->fetchAll();
    } elseif ($code === 'ACCOUNTING') {
        $statement = $database->prepare('SELECT id,description,amount,status,payment_reference,remarks,cleared_at FROM accounting_obligations WHERE student_id=:student_id AND academic_period_id=:period_id ORDER BY status DESC,id');
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $records = $statement->fetchAll();
    }
    return $records;
}

function renderOfficeDashboard(string $role, string $stationCode, string $title): never
{
    $user = requireRole($role);
    $search = trim((string) ($_GET['search'] ?? ''));
    $filter = strtoupper((string) ($_GET['filter'] ?? 'ALL'));
    $courseFilter = trim((string) ($_GET['course'] ?? ''));
    $yearFilter = filter_var($_GET['year_level'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]) ?: 0;
    $directClearOffice = in_array($stationCode, ['CLINIC', 'LIBRARY'], true);
    $allowedFilters = $stationCode === 'CLINIC'
        ? ['ALL', 'COMPLIANCE', 'APPROVED']
        : ['ALL', 'READY', 'PENDING', 'RETURNED', 'APPROVED'];
    $filterLabels = [
        'ALL' => 'All students',
        'COMPLIANCE' => 'For Compliance',
        'READY' => 'Ready to Clear',
        'PENDING' => 'Pending',
        'RETURNED' => $stationCode === 'REGISTRAR' ? 'For Compliance' : 'Returned',
        'APPROVED' => 'Cleared',
    ];
    if (!in_array($filter, $allowedFilters, true)) {
        $filter = 'ALL';
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 30;
    $facilitiesTabs = false;
    $facilitiesAudience = 'ALL';
    $facilitiesDepartment = 'ALL';
    $facilitiesDepartments = [];
    try {
        $database = getDatabaseConnection();
        $rosterFilters = officeRosterFilterOptions($database, $stationCode);
        $courses = $rosterFilters['courses'];
        $yearLevels = $rosterFilters['year_levels'];
        $readiness = $stationCode === 'REGISTRAR' ? null : officeReadiness($database, $stationCode);
        $bookstoreLockReason = $stationCode === 'BOOKSTORE'
            ? bookstoreObligationLockReason(bookstoreObligationPreparation($database, (int) $readiness['academic_period_id']))
            : null;
        $facilitiesTabs = $stationCode === 'FACILITIES' && ($readiness['semester'] ?? null) === '2ND';
        if ($facilitiesTabs) {
            $facilitiesAudience = strtoupper((string) ($_GET['audience'] ?? 'GRADUATING'));
            if (!in_array($facilitiesAudience, ['GRADUATING', 'NON_GRADUATING'], true)) {
                $facilitiesAudience = 'GRADUATING';
            }
            $facilitiesDepartments = $database->query("SELECT code,name FROM organizations WHERE status='ACTIVE' ORDER BY code")->fetchAll();
            $allowedDepartments = array_column($facilitiesDepartments, 'code');
            $facilitiesDepartment = strtoupper((string) ($_GET['department'] ?? 'ALL'));
            if ($facilitiesDepartment !== 'ALL' && !in_array($facilitiesDepartment, $allowedDepartments, true)) {
                $facilitiesDepartment = 'ALL';
            }
        }
        $summary = officeStatusSummary($database, $stationCode, $facilitiesAudience, $facilitiesDepartment);
        $bulkReadyCount = count(bulkReadyClearanceIds($database, $stationCode, null, '', $facilitiesAudience, $facilitiesDepartment));
        $totalRows = officeRowCount($database, $stationCode, $search, $filter, $facilitiesAudience, $facilitiesDepartment, $courseFilter, $yearFilter);
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $rows = officeRows($database, $stationCode, $search, $pageSize, ($page - 1) * $pageSize, $filter, $facilitiesAudience, $facilitiesDepartment, $courseFilter, $yearFilter);
        foreach ($rows as &$row) {
            if ($row['station_status'] === 'LOCKED') {
                $row['view_status'] = 'LOCKED';
            } elseif (!in_array($row['station_status'], ['APPROVED', 'RETURNED'], true)) {
                $row['view_status'] = $row['eligibility']['eligible'] ? 'READY' : 'PENDING';
            } else {
                $row['view_status'] = $row['station_status'];
            }
        }
        unset($row);
        $selected = null;
        $selectedId = filter_var($_GET['clearance'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($selectedId !== false) {
            foreach ($rows as $row) {
                if ((int) $row['clearance_id'] === (int) $selectedId) {
                    $selected = $row;
                    break;
                }
            }
        }
        if ($selected !== null) {
            ensureOfficeSourceRecords($database, (int) $selected['student_id'], (int) $selected['academic_period_id']);
            $selected['eligibility'] = officeEligibility($database, $stationCode, $selected);
            $detailRecords = officeDetailRecords($database, $stationCode, $selected);
        } else {
            $detailRecords = [];
        }
        $notifications = dashboardNotifications($database, (int) $user['id']);
    } catch (Throwable $exception) {
        error_log($title . ' dashboard failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Unable to load this office dashboard right now. Please try again later.');
    }
    if (($_GET['export'] ?? '') === 'xlsx') {
        $exportRows = [];
        for ($offset = 0; $offset < $totalRows; $offset += 200) {
            $exportRows = array_merge($exportRows, officeRows($database, $stationCode, $search, 200, $offset, $filter, $facilitiesAudience, $facilitiesDepartment, $courseFilter, $yearFilter));
        }
        $sheets = ['CCS' => [], 'CBE' => [], 'CTE' => []];
        foreach ($exportRows as $row) {
            $viewStatus = $row['station_status'];
            if ($viewStatus === 'LOCKED') {
                $viewStatus = 'LOCKED';
            } elseif (!in_array($viewStatus, ['APPROVED', 'RETURNED'], true)) {
                $viewStatus = $row['eligibility']['eligible'] ? 'READY' : 'PENDING';
            }
            $sheets[$row['organization_code']][] = [
                $row['student_number'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['program'],
                'Year ' . (int) $row['year_level'],
                $row['organization_code'],
                $viewStatus === 'APPROVED' ? 'CLEARED' : str_replace('_', ' ', $viewStatus),
                implode('; ', $row['eligibility']['details']),
                $row['returned_reason'] ?: '',
                (string) ($row['office_remarks'] ?? ''),
            ];
        }
        $reportSheets = [];
        foreach ($sheets as $code => $sheetRows) {
            $reportSheets[] = ['name' => $code, 'rows' => $sheetRows];
        }
        $workbook = createClearanceReportXlsx($title . ' Report', activePeriodReportLabel($database), ['Student Number', 'Full Name', 'Course', 'Year Level', 'College', 'Clearance Status', 'Important Details', 'Return Reason', 'Remarks'], $reportSheets);
        downloadClearanceReport($workbook, strtolower(str_replace('_', '-', $stationCode)) . '-clearance-report.xlsx');
    }
    $message = pullFlashMessage();
    $csrfToken = createCsrfToken();
    $logoutForm = dashboardLogoutForm();
    $actionUrl = applicationUrl('/clearance/office_action.php');
    $officeDescriptions = [
        'SASO' => 'Confirm clearance from Student Council, Library, Bookstore and Clinic.',
        'STUDENT_COUNCIL' => 'Review council requirements and confirm eligible students.',
        'LIBRARY' => 'Verify library accountabilities and physical library cards.',
        'BOOKSTORE' => 'Review balances, accountabilities, and payment references.',
        'CLINIC' => 'Verify medical requirements and process clinic clearance.',
        'FACILITIES' => 'Track borrowed property and confirm required returns.',
        'ACCOUNTING' => 'Review financial obligations and receipt verification.',
        'REGISTRAR' => 'Check documents manually and confirm all office signatures. Record missing documents in remarks and choose For Compliance.',
        'COMLAB' => 'Review laboratory incidents and equipment accountabilities.',
    ];
    $pageDescription = $officeDescriptions[$stationCode] ?? 'Review and process assigned student clearance records.';
    $sidebarItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/' . strtolower(str_replace('_', '-', $stationCode)) . '/dashboard.php', 'icon' => 'dashboard'],
    ];
    if ($stationCode === 'CLINIC') {
        $sidebarItems[] = ['key' => 'schedules', 'label' => 'Medical Schedules', 'url' => '/clinic/schedules.php', 'icon' => 'document'];
    }
    if ($stationCode === 'FACILITIES') {
        $sidebarItems[] = ['key' => 'borrowed-items', 'label' => 'Borrowed Items', 'url' => '/facilities/borrowed-items.php', 'icon' => 'document'];
    }
    if ($stationCode === 'COMLAB') {
        $sidebarItems[] = ['key' => 'violations', 'label' => 'Violation History', 'url' => '/comlab/violations.php', 'icon' => 'document'];
    }
    if ($stationCode === 'BOOKSTORE') {
        $sidebarItems[] = ['key' => 'fees', 'label' => 'Event Fees', 'url' => '/bookstore/fees.php', 'icon' => 'document'];
        $sidebarItems[] = ['key' => 'submissions', 'label' => 'Receipt Submissions', 'url' => '/bookstore/submissions.php', 'icon' => 'document'];
    }
    if ($stationCode === 'ACCOUNTING') {
        $sidebarItems[] = ['key' => 'submissions', 'label' => 'Submitted Requirements', 'url' => '/accounting/submissions.php', 'icon' => 'document'];
    }
    if ($stationCode === 'LIBRARY') {
        $sidebarItems[] = ['key' => 'submissions', 'label' => 'Card Submissions', 'url' => '/library/submissions.php', 'icon' => 'document'];
    }
    if ($stationCode === 'STUDENT_COUNCIL') {
        $sidebarItems[] = ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => '/student-council/attendance.php', 'icon' => 'document'];
        $sidebarItems[] = ['key' => 'sanctions', 'label' => 'Sanction Submissions', 'url' => '/student-council/sanction_submissions.php', 'icon' => 'document'];
    }
    $activeSidebarItem = 'dashboard';
    if ($stationCode === 'REGISTRAR') {
        $sidebar = registrarDashboardSidebar($user, 'clearance', $logoutForm);
    } else {
        $sidebar = staffDashboardSidebar($title, $user, $sidebarItems, $activeSidebarItem, $logoutForm);
    }
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <?= connectionStatusAssets() ?>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= escapeHtml($title) ?> | ClearGo</title>
        <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/office-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/office-dashboard.css') ?>">
        <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
    </head>

    <body class="staff-dashboard">
        <div class="staff-shell">
            <?= $sidebar ?>
            <div class="staff-workspace">
                <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                    <div class="topbar-title">
                        <h1><?= escapeHtml($title) ?></h1>
                        <p><?= escapeHtml($pageDescription) ?></p>
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
                                <p class="muted"><?= $facilitiesTabs ? 'Current status for the selected student group and department.' : 'Current status across the active student roster.' ?></p>
                            </div>
                            <a class="button-link summary-export" href="?search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;audience=<?= urlencode($facilitiesAudience) ?>&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;export=xlsx">Export Excel report</a>
                        </div>
                        <div class="summary">
                            <?php $summaryCards = ['ALL' => ['Total Students', $summary['TOTAL']]];
                            if ($stationCode === 'CLINIC') {
                                $summaryCards['COMPLIANCE'] = ['For Compliance', $summary['TOTAL'] - $summary['APPROVED']];
                            } else {
                                $summaryCards += ['READY' => ['Ready', $summary['READY']], 'PENDING' => ['Pending', $summary['PENDING']], 'RETURNED' => [$stationCode === 'REGISTRAR' ? 'For Compliance' : 'Returned', $summary['RETURNED']]];
                            }
                            $summaryCards['APPROVED'] = ['Cleared', $summary['APPROVED']]; ?>
                            <?php foreach ($summaryCards as $summaryFilter => [$summaryLabel, $summaryCount]): ?>
                                <a class="summary-filter" href="?filter=<?= $summaryFilter ?>&amp;audience=<?= urlencode($facilitiesAudience) ?>&amp;department=<?= urlencode($facilitiesDepartment) ?>#student-records" <?= $filter === $summaryFilter ? 'aria-current="true"' : '' ?>><?= escapeHtml($summaryLabel) ?><strong><?= $summaryCount ?></strong></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                    <?php if ($readiness !== null && $readiness['clearance_status'] === 'PREPARING'): ?><section class="panel">
                            <h2>Semester preparation</h2>
                            <p>Save and select an authorized signature in <a href="<?= escapeHtml(applicationUrl('/auth/profile.php#signature-card-title')) ?>">My Profile</a> before marking Ready. Signatures lock while Ready.</p>
                            <p><strong><?= escapeHtml($readiness['semester'] . ' Semester A.Y. ' . $readiness['school_year']) ?></strong></p>
                            <?php if ($stationCode === 'BOOKSTORE'): ?><p class="muted">Marking Bookstore Ready locks new obligations and event fee changes. You can still record payments and clear students. Undo Ready during preparation to edit unsigned records.</p><?php endif; ?>
                            <p>Review records and take action now. Clear complete students or return incomplete records, then mark the office Ready.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">Your office is READY.</p>
                                <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="ready">Mark Office Ready</button></form><?php endif; ?>
                        </section><?php endif; ?>


                    <section class="panel">
                        <div class="section-heading">
                            <div>
                                <h2>Find student records</h2>
                                <p class="muted">Search the roster or focus on a clearance status.</p>
                            </div>
                        </div><?php if ($facilitiesTabs): ?><div class="facility-tabs"><a class="facility-tab <?= $facilitiesAudience === 'GRADUATING' ? 'active' : '' ?>" href="?audience=GRADUATING&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;filter=<?= urlencode($filter) ?>&amp;search=<?= urlencode($search) ?>">Graduating (Year 4)</a><a class="facility-tab <?= $facilitiesAudience === 'NON_GRADUATING' ? 'active' : '' ?>" href="?audience=NON_GRADUATING&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;filter=<?= urlencode($filter) ?>&amp;search=<?= urlencode($search) ?>">Non-graduating</a></div><?php endif; ?><form class="controls" method="get"><?php if ($facilitiesTabs): ?><input type="hidden" name="audience" value="<?= escapeHtml($facilitiesAudience) ?>"><select name="department" aria-label="Filter by department">
                                    <option value="ALL">All departments</option><?php foreach ($facilitiesDepartments as $department): ?><option value="<?= escapeHtml($department['code']) ?>" <?= $facilitiesDepartment === $department['code'] ? 'selected' : '' ?>><?= escapeHtml($department['name']) ?></option><?php endforeach; ?>
                                </select><?php endif; ?><input type="text" name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, or program" aria-label="Search students"><select name="course" aria-label="Filter by course">
                                <option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= escapeHtml($course) ?>" <?= $courseFilter === $course ? 'selected' : '' ?>><?= escapeHtml($course) ?></option><?php endforeach; ?>
                            </select><select name="year_level" aria-label="Filter by year level">
                                <option value="0">All year levels</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endforeach; ?>
                            </select><select name="filter" aria-label="Filter by clearance status"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml($filterLabels[$option]) ?></option><?php endforeach; ?></select><button type="submit">Search</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl($stationCode === 'REGISTRAR' ? '/registrar/clearance.php' : ROLE_DASHBOARDS[$role])) ?>">Clear</a></form>
                    </section>
                    <section class="panel" id="student-records" tabindex="-1">
                        <div class="section-heading">
                            <div>
                                <h2>Student clearance records</h2>
                                <p class="muted"><?= $stationCode === 'REGISTRAR' ? 'Students reaching the final office require complete documents and signatures from every office. Choose Clear or For Compliance.' : 'Review each student\'s current eligibility and available action.' ?></p>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table id="clearance-records">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                        <th>College</th>
                                        <th>Requirements</th>
                                        <th>Clearance Status</th>
                                        <th>Action</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $visible = count($rows);
                                    foreach ($rows as $rowIndex => $row): ?>
                                        <tr>
                                            <td><?= (($page - 1) * $pageSize) + $rowIndex + 1 ?></td>
                                            <td><strong><?= escapeHtml($row['student_number']) ?></strong></td>
                                            <td><?= escapeHtml($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                            <td><?= escapeHtml($row['program']) ?></td>
                                            <td>Year <?= (int) $row['year_level'] ?></td>
                                            <td><?= escapeHtml($row['organization_code']) ?></td>
                                            <td>
                                                <?php if ($stationCode === 'ACCOUNTING'): ?>
                                                    <?= escapeHtml(implode('; ', $row['eligibility']['details'])) ?>
                                                    <?php if ($row['station_status'] !== 'LOCKED'): ?>
                                                        <div><a href="?<?= escapeHtml(http_build_query(['clearance' => (int) $row['clearance_id'], 'search' => $search, 'filter' => $filter, 'course' => $courseFilter, 'year_level' => $yearFilter, 'page' => $page])) ?>">Payment records</a></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?= $stationCode === 'COMLAB' ? ($row['station_status'] === 'APPROVED' ? 'Passed: No violation' : 'Must have no record of violation inside Comlab') : ($stationCode === 'REGISTRAR' ? escapeHtml(implode('; ', $row['eligibility']['details'])) : (in_array($stationCode, ['BOOKSTORE', 'LIBRARY'], true) ? escapeHtml(implode('; ', $row['eligibility']['details']) ?: 'Needs office review') : ($row['eligibility']['eligible'] ? ($stationCode === 'FACILITIES' ? 'No borrowed items' : 'All checks satisfied') : escapeHtml($row['eligibility']['details'][0] ?? 'Needs review')))) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge <?= strtolower(str_replace('_', '-', $row['view_status'])) ?>"><?= $row['station_status'] === 'APPROVED' ? 'CLEARED' : ($stationCode === 'REGISTRAR' && $row['view_status'] === 'RETURNED' ? 'FOR COMPLIANCE' : escapeHtml(statusDisplayLabel($row['view_status']))) ?></span></td>
                                            <?php if ($stationCode === 'REGISTRAR'): ?>
                                                <td>
                                                    <?php if (in_array($row['station_status'], ['APPROVED', 'LOCKED'], true) || !in_array($row['clearance_status'], ['PREPARING', 'OPEN'], true)): ?>
                                                        <span class="muted">&mdash;</span>
                                                    <?php else: ?>
                                                        <div class="registrar-row-actions">
                                                            <button type="button" class="danger" data-registrar-compliance data-clearance-id="<?= (int) $row['clearance_id'] ?>" data-student-name="<?= escapeHtml($row['first_name'] . ' ' . $row['last_name']) ?>">For Compliance</button>
                                                            <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student and complete the clearance?');">
                                                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                                <input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>">
                                                                <button name="action" value="registrar_clear">Clear</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($stationCode === 'ACCOUNTING'): ?>
                                                <td>
                                                    <?php if (in_array($row['station_status'], ['APPROVED', 'LOCKED'], true) || !in_array($row['clearance_status'], ['PREPARING', 'OPEN'], true)): ?>
                                                        <span class="muted"><?= $row['station_status'] === 'APPROVED' ? 'Cleared' : '&mdash;' ?></span>
                                                    <?php else: ?>
                                                        <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Confirm that all balances are fully paid and the submitted portal assessment matches official Accounting records. Clear this student?');">
                                                            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                            <input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>">
                                                            <button name="action" value="approve" <?= !$row['eligibility']['eligible'] ? 'disabled title="All recorded balances must be settled and a portal assessment screenshot submitted before clearance."' : '' ?>>Clear</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($stationCode === 'BOOKSTORE'): ?>
                                                <td>
                                                    <?php if (in_array($row['station_status'], ['APPROVED', 'LOCKED'], true)): ?>
                                                        <span class="muted">&mdash;</span>
                                                    <?php elseif ($row['eligibility']['eligible']): ?>
                                                        <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Bookstore?');">
                                                            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                            <input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>">
                                                            <button name="action" value="approve">Clear</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <a href="?clearance=<?= (int) $row['clearance_id'] ?>&amp;search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;page=<?= $page ?>">Review</a>
                                                    <?php endif; ?>
                                                </td>
                                            <?php else: ?>
                                            <td><?php if ($stationCode === 'COMLAB' && $row['station_status'] === 'APPROVED'): ?><span class="muted">Cleared</span><?php elseif ($stationCode === 'COMLAB'): ?><div class="comlab-row-actions"><button type="button" class="danger" onclick="openComlabViolationModal(<?= (int) $row['clearance_id'] ?>)">Add violation</button><?php if (in_array($row['station_status'], ['PENDING', 'RETURNED'], true) && $row['eligibility']['eligible'] && in_array($row['clearance_status'], ['PREPARING', 'OPEN'], true)): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve" <?= !$row['eligibility']['eligible'] ? 'disabled title="Resolve violations in the Comlab room first"' : '' ?>>Clear</button></form><?php endif; ?></div><?php elseif ($stationCode !== 'FACILITIES' && in_array($row['station_status'], ['APPROVED', 'LOCKED'], true)): ?><span class="muted">—</span><?php elseif ($stationCode === 'FACILITIES' && $row['station_status'] === 'APPROVED'): ?><span class="muted">Cleared</span><?php elseif ($stationCode === 'FACILITIES'): ?><div class="facilities-row-actions"><button type="button" class="secondary" onclick="openFacilitiesItemModal(<?= (int) $row['clearance_id'] ?>)">Borrowed Item</button><?php if (!in_array($row['station_status'], ['APPROVED', 'LOCKED'], true) && $row['eligibility']['eligible']): ?> <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Facilities?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php endif; ?></div><?php elseif ($stationCode === 'CLINIC'): ?><div class="inline"><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Mark this student as having no medical record?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><input type="hidden" name="status" value="RETURNED"><input type="hidden" name="remarks" value="No medical record on file."><button class="danger" name="action" value="medical_status">No Medical</button></form><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student at the Clinic?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="clinic_clear">Clear</button></form></div><?php elseif ($directClearOffice): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="library_clear" <?= !empty($row['eligibility']['outstanding_books']) ? 'disabled title="Return all borrowed books first"' : '' ?>>Clear</button><?php if (!empty($row['eligibility']['outstanding_books'])): ?> <a href="?<?= escapeHtml(http_build_query(['clearance' => (int) $row['clearance_id'], 'search' => $search, 'filter' => $filter, 'course' => $courseFilter, 'year_level' => $yearFilter, 'page' => $page])) ?>">Review books</a><?php endif; ?></form><?php elseif ($stationCode === 'SASO'): ?><?php if ($row['eligibility']['eligible']): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for SASO?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php else: ?><span class="muted">—</span><?php endif; ?><?php elseif ($stationCode === 'STUDENT_COUNCIL' && $row['eligibility']['eligible']): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Student Council?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php else: ?><a href="?clearance=<?= (int) $row['clearance_id'] ?>&amp;search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;audience=<?= urlencode($facilitiesAudience) ?>&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;page=<?= $page ?>"><?= $stationCode === 'BOOKSTORE' ? 'Review' : ($row['clearance_status'] === 'PREPARING' ? 'Take Action' : 'Review') ?></a><?php endif; ?></td>
                                            <?php endif; ?>
                                        <td><?= renderOfficeRemarksButton((int) $row['clearance_id'], (string) ($row['office_remarks'] ?? ''), $row['first_name'] . ' ' . $row['last_name'], in_array($row['clearance_status'], ['PREPARING', 'OPEN'], true) && $row['station_status'] !== 'APPROVED') ?></td>
                                        </tr>
                                    <?php endforeach;
                                    if ($visible === 0): ?><tr>
                                            <td colspan="10">No students match this view.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p>
                        <?= renderBulkClearanceFooter($page, $totalPages, ['search' => $search, 'course' => $courseFilter, 'year_level' => $yearFilter, 'filter' => $filter, 'audience' => $facilitiesAudience, 'department' => $facilitiesDepartment], $actionUrl, $bulkReadyCount, $stationCode === 'FACILITIES' ? 'the current Facilities group and department' : $title, ['audience' => $facilitiesAudience, 'department' => $facilitiesDepartment]) ?>
                    </section>
                    <?php if ($stationCode === 'REGISTRAR'): ?>
                        <div id="registrar-compliance-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="registrar-compliance-title">
                            <div class="modal" tabindex="-1">
                                <h2 id="registrar-compliance-title">Mark for Compliance</h2>
                                <p>Enter the reason <span id="registrar-compliance-student"></span> needs to comply.</p>
                                <form method="post" action="<?= escapeHtml($actionUrl) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                    <input id="registrar-compliance-clearance-id" type="hidden" name="clearance_id" value="">
                                    <textarea id="registrar-compliance-reason" name="reason" required maxlength="1000" placeholder="Reason for compliance"></textarea>
                                    <div class="actions">
                                        <button type="button" class="secondary" data-close-registrar-compliance>Cancel</button>
                                        <button class="danger" name="action" value="return">For Compliance</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <script src="<?= escapeHtml(applicationUrl('/assets/js/registrar-clearance.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/registrar-clearance.js') ?>"></script>
                    <?php endif; ?>
                    <?php if ($stationCode === 'FACILITIES'): ?>
                        <?php require __DIR__ . '/facilities_items_modal.php'; ?>
                        <script src="<?= escapeHtml(applicationUrl('/assets/js/office-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/office-dashboard.js') ?>"></script>
                    <?php endif; ?>

                    <?php if ($stationCode === 'COMLAB'): ?><?php require __DIR__ . '/comlab_violations_modal.php'; ?><?php endif; ?>
                    <?php if ($selected !== null && !in_array($stationCode, ['COMLAB', 'REGISTRAR'], true)): ?>
                        <?php if (!in_array($stationCode, ['BOOKSTORE', 'SASO', 'FACILITIES'], true)): ?>
                            <section class="panel">
                                <div class="detail-grid">
                                    <div>
                                        <h2><?= escapeHtml($selected['first_name'] . ' ' . $selected['last_name']) ?></h2>
                                        <p><strong><?= escapeHtml($selected['student_number']) ?></strong><br><?= escapeHtml($selected['program']) ?> · Year <?= (int) $selected['year_level'] ?><br><?= escapeHtml($selected['organization_name']) ?></p><?php $selectedDisplayStatus = $selected['station_status'];
                                                                                                                                                                                                                                                            if ($selected['station_status'] === 'PENDING' && $selected['eligibility']['eligible']) {
                                                                                                                                                                                                                                                                $selectedDisplayStatus = 'READY';
                                                                                                                                                                                                                                                            } ?><p>Status: <span class="badge <?= strtolower($selectedDisplayStatus) ?>"><?= escapeHtml(statusDisplayLabel($selectedDisplayStatus)) ?></span></p><?php if ($selected['returned_reason']): ?><p class="message"><?= escapeHtml($selected['returned_reason']) ?></p><?php endif; ?>
                                    </div><?php if ($stationCode !== 'BOOKSTORE'): ?><div>
                                            <h2>Eligibility checks</h2>
                                            <ul class="requirement-list"><?php foreach ($selected['eligibility']['details'] as $detail): ?><li><?= escapeHtml($detail) ?></li><?php endforeach; ?></ul>
                                        </div><?php endif; ?>
                                </div>
                            </section>

                        <?php endif; ?>
                        <?php if (!in_array($stationCode, ['SASO', 'FACILITIES', 'REGISTRAR'], true) && $selected['station_status'] !== 'LOCKED'): ?><section class="panel">
                                <h2>Office records</h2>
                                <?php if ($stationCode === 'CLINIC'): ?>
                                    <p>Review the official medical record. Clicking Clear verifies the medical requirement and approves Clinic clearance in one step.</p><?php if ($selected['station_status'] === 'APPROVED'): ?><p class="message">This student is already cleared by the Clinic.</p><?php else: ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student at the Clinic?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="remarks" placeholder="Optional Clinic remarks"></textarea><button name="action" value="clinic_clear">Clear</button></form><?php endif; ?>
                                <?php elseif ($stationCode === 'STUDENT_COUNCIL'): ?>
                                    <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml(statusDisplayLabel($record['status'])) ?></span>
                                            <div class="muted"><?= escapeHtml($record['description'] ?? '') ?></div><?php if (!in_array($record['status'], ['VERIFIED', 'RESOLVED'], true)): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="requirement_record_id" value="<?= (int) $record['id'] ?>"><input type="text" name="remarks" placeholder="Verification note"><button name="action" value="verify_requirement">Verify</button></form><?php endif; ?>
                                        </div><?php endforeach; ?>
                                <?php elseif ($stationCode === 'LIBRARY'): ?>
                                    <?php if ($selected['eligibility']['outstanding_books'] !== []): ?><p class="message">Return all borrowed books before Library clearance.</p><?php foreach ($selected['eligibility']['outstanding_books'] as $book): ?><div class="record"><strong><?= escapeHtml($book['book_title']) ?></strong><?php if ($book['due_at'] !== null): ?><span class="muted"> (due <?= escapeHtml($book['due_at']) ?>)</span><?php endif; ?><?php if (in_array($selected['clearance_status'], ['PREPARING', 'OPEN'], true)): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Confirm this book was returned?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>"><button name="action" value="return_library_book">Mark Returned</button></form><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
                                    <p>Verify the student's physical library card. No library card means the student cannot be cleared.</p><?php if ($selected['station_status'] === 'APPROVED'): ?><p class="message">This student is already cleared by the Library.</p><?php else: ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Confirm that the library card was presented and clear this student?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><button name="action" value="library_clear" <?= !empty($selected['eligibility']['outstanding_books']) ? 'disabled title="Return all borrowed books first"' : '' ?>>Clear</button></form><?php endif; ?>
                                <?php elseif ($stationCode === 'BOOKSTORE'): ?>
                                    <p>Review the student's name and ID, verify balances and accountabilities against Bookstore records, and check the payment receipt before marking an obligation settled. Add remarks for missing requirements or follow-up. Bookstore clearance requires all obligations to be settled; exceptions are not allowed.</p><?php if ($detailRecords === []): ?><p class="muted">No Bookstore obligations recorded for this semester.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?> — PHP <?= number_format((float) $record['amount'], 2) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><?php if ($record['payment_reference']): ?><div class="muted">Receipt/reference: <?= escapeHtml($record['payment_reference']) ?></div><?php endif; ?><?php if ($record['remarks']): ?><div class="muted"><?= escapeHtml($record['remarks']) ?></div><?php endif; ?><?php if ($record['status'] === 'UNPAID'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="obligation_id" value="<?= (int) $record['id'] ?>"><input type="text" name="payment_reference" required placeholder="Payment receipt/reference"><input type="text" name="remarks" maxlength="1000" placeholder="Settlement remarks"><button name="action" value="clear_bookstore_obligation">Mark Settled</button></form><?php endif; ?></div><?php endforeach; ?><h3>Add pending obligation</h3>
                                    <?php $addObligationLockReason = $bookstoreLockReason ?? ($selected['station_status'] === 'APPROVED' || $selected['overall_status'] === 'COMPLETED' ? 'This student is already cleared by Bookstore.' : ($selected['station_status'] === 'LOCKED' ? 'The preceding offices must clear this student first.' : null)); ?>
                                    <?php if ($addObligationLockReason !== null): ?><p class="message"><?= escapeHtml($addObligationLockReason) ?></p><?php endif; ?>
                                    <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="description" maxlength="255" required placeholder="Balance, missing requirement, or accountability" <?= $addObligationLockReason !== null ? 'disabled' : '' ?>><input type="number" name="amount" min="0" step="0.01" required value="0" placeholder="Amount" <?= $addObligationLockReason !== null ? 'disabled' : '' ?>><input type="text" name="remarks" maxlength="1000" placeholder="Remarks / document needed" <?= $addObligationLockReason !== null ? 'disabled' : '' ?>><button name="action" value="add_bookstore_obligation" <?= $addObligationLockReason !== null ? 'disabled' : '' ?>>Add Obligation</button></form>
                                <?php elseif ($stationCode === 'FACILITIES'): ?>
                                    <p>Confirm that all items borrowed from Facilities have been returned. Year 4 students must also return their toga during the second semester before clearance.</p><?php if ($detailRecords === []): ?><p class="muted">No borrowed Facilities items are recorded for this student.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?></strong> <span class="badge <?= $record['status'] === 'RETURNED' ? 'resolved' : 'open' ?>"><?= escapeHtml($record['status']) ?></span>
                                            <div class="muted"><?= $record['item_type'] === 'TOGA' ? 'Graduation requirement' : 'Borrowed item' ?> · Recorded <?= escapeHtml($record['created_at']) ?></div><?php if ($record['remarks']): ?><div class="muted"><?= escapeHtml($record['remarks']) ?></div><?php endif; ?><?php if ($record['status'] === 'OUTSTANDING'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="item_id" value="<?= (int) $record['id'] ?>"><input type="text" name="remarks" maxlength="1000" placeholder="Optional return note"><button name="action" value="return_facilities_item">Mark Returned</button></form><?php endif; ?>
                                        </div><?php endforeach; ?><h3>Record borrowed item</h3>
                                    <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="description" maxlength="255" required placeholder="Borrowed item"><input type="text" name="remarks" maxlength="1000" placeholder="Optional item details"><button name="action" value="add_facilities_item">Add Item</button></form>
                                <?php elseif ($stationCode === 'COMLAB'): ?>
                                    <p>Record laboratory incidents such as damage, missing equipment, theft, or rule violations. Every incident must be settled before Computer Laboratory can clear the student.</p><?php if ($detailRecords === []): ?><p class="muted">No Computer Laboratory incident reports are recorded for this student.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong>Incident report</strong> <span class="badge <?= $record['status'] === 'RESOLVED' ? 'resolved' : 'open' ?>"><?= escapeHtml($record['status']) ?></span>
                                            <div><?= escapeHtml($record['description']) ?></div>
                                            <div class="muted">Reported <?= escapeHtml($record['created_at']) ?><?= $record['resolved_at'] ? ' · Settled ' . escapeHtml($record['resolved_at']) : '' ?></div><?php if ($record['status'] === 'OPEN'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="incident_id" value="<?= (int) $record['id'] ?>"><button name="action" value="resolve_comlab_incident">Mark Settled</button></form><?php endif; ?>
                                        </div><?php endforeach; ?><?php if ($selected['station_status'] !== 'APPROVED'): ?><h3>Record incident report</h3>
                                        <form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="description" required maxlength="2000" placeholder="Describe the laboratory incident, damage, missing item, or rule violation"></textarea><button name="action" value="add_comlab_incident">Record Incident</button></form><?php endif; ?>
                                <?php elseif ($stationCode === 'ACCOUNTING'): ?>
                                    <?php if ($detailRecords === []): ?><p class="muted">No financial obligations recorded.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?> — PHP <?= number_format((float)$record['amount'], 2) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><?php if ($record['payment_reference']): ?><div class="muted">Reference: <?= escapeHtml($record['payment_reference']) ?></div><?php endif; ?><?php if ($record['status'] === 'UNPAID'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="obligation_id" value="<?= (int) $record['id'] ?>"><input type="text" name="payment_reference" required placeholder="Payment reference"><input type="text" name="remarks" placeholder="Remarks"><button name="action" value="clear_obligation">Verify settled</button></form><?php endif; ?></div><?php endforeach; ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="description" required placeholder="Financial obligation"><input type="number" name="amount" min="0" step="0.01" required placeholder="Amount"><input type="text" name="remarks" placeholder="Remarks"><button name="action" value="add_obligation">Add obligation</button></form>
                                    <h3>TLC portal screenshot review</h3>
                                    <p>Check the student's name, school year, semester, charges, payments, and balance against official Accounting records. Approve only when the payment requirement is satisfied. Return with a reason if details are unclear, do not match, or a balance remains due.</p>
                                    <?php
                                    $portalQuery = $database->prepare("SELECT receipt.id,receipt.receipt_code FROM accounting_receipts receipt INNER JOIN clearances c ON c.student_id=receipt.student_id AND c.academic_period_id=receipt.academic_period_id WHERE c.id=:clearance_id ORDER BY receipt.id DESC LIMIT 1");
                                    $portalQuery->execute(['clearance_id' => $selected['clearance_id']]);
                                    $portalSubmission = $portalQuery->fetch();
                                    ?>
                                    <?php if ($portalSubmission && str_starts_with($portalSubmission['receipt_code'], 'PORTAL-')): ?>
                                        <p><a class="button-link" href="<?= escapeHtml(applicationUrl('/accounting/assessment_image.php?id=' . (int) $portalSubmission['id'])) ?>" target="_blank" rel="noopener">View Portal Screenshot</a></p>
                                    <?php else: ?><p class="muted">No TLC portal screenshot submitted yet.</p><?php endif; ?>
                                <?php endif; ?>
                            </section><?php endif; ?>

                        <?php if (!$directClearOffice && $stationCode !== 'FACILITIES'): ?><section class="panel">
                                <h2>Clearance decision</h2><?php if (!in_array($selected['clearance_status'], ['PREPARING', 'OPEN'], true)): ?><p class="message">Clearance processing is closed for this semester.</p><?php elseif ($selected['station_status'] === 'LOCKED'): ?><p class="message">The required preceding offices must clear this student before this office can take action.</p><?php elseif ($selected['station_status'] === 'APPROVED'): ?><p class="message">This office has cleared the student.</p><?php else: ?><?php if ($selected['clearance_status'] === 'PREPARING'): ?><p class="message">Preparation mode: your decision will be ready when students gain access.</p><?php endif; ?><div class="actions">
                                    <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><button name="action" value="<?= $stationCode === 'REGISTRAR' ? 'registrar_clear' : 'approve' ?>" <?= !$selected['eligibility']['eligible'] ? 'disabled title="Complete all requirements first"' : '' ?>>Clear</button></form>
                                    <form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="reason" required placeholder="Explain what the student must complete"></textarea><button class="danger" name="action" value="return"><?= $stationCode === 'REGISTRAR' ? 'For Compliance' : 'Return to student' ?></button></form>
                                </div><?php endif; ?>
                            </section><?php endif; ?>
                    <?php endif; ?>
                </main>
            </div>
        </div>
        <?= renderOfficeRemarksModal($_GET) ?>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
    </body>

    </html>
<?php
    exit;
}
