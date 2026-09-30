<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/office_workflow.php';
require_once __DIR__ . '/readiness_functions.php';

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
    } elseif ($code === 'REGISTRAR') {
        $statement = $database->prepare("SELECT document.id,requirement.name,requirement.description,document.status,document.original_file_name,document.return_reason FROM document_requirements requirement INNER JOIN student_documents document ON document.document_requirement_id=requirement.id AND document.student_id=:student_id WHERE requirement.academic_period_id=:period_id AND requirement.status='ACTIVE' AND requirement.is_required=1 ORDER BY requirement.id");
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
        'RETURNED' => 'Returned',
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
        $summary = officeStatusSummary($database, $stationCode);
        $councilEvents = $stationCode === 'STUDENT_COUNCIL' ? studentCouncilEvents($database, (int) $readiness['academic_period_id']) : [];
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
    if (($_GET['export'] ?? '') === 'csv' && !$directClearOffice) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . strtolower(str_replace('_', '-', $stationCode)) . '-clearance-report.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Unable to create the report.');
        }
        fputcsv($output, ['Student Number', 'Student Name', 'Program', 'Year', 'College', 'Office Status', 'Eligible', 'Eligibility Details']);
        foreach ($rows as $row) {
            if ($filter !== 'ALL' && $row['view_status'] !== $filter) {
                continue;
            }
            fputcsv($output, [
                $row['student_number'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['program'],
                $row['year_level'],
                $row['organization_code'],
                $row['view_status'],
                $row['eligibility']['eligible'] ? 'YES' : 'NO',
                implode('; ', $row['eligibility']['details']),
            ]);
        }
        fclose($output);
        exit;
    }
    $message = pullFlashMessage();
    $csrfToken = createCsrfToken();
    $logoutForm = dashboardLogoutForm();
    $actionUrl = applicationUrl('/clearance/office_action.php');
    $officeDescriptions = [
        'SASO' => 'Review student conduct requirements and resolve outstanding cases.',
        'STUDENT_COUNCIL' => 'Review council requirements and confirm eligible students.',
        'LIBRARY' => 'Verify library accountabilities and physical library cards.',
        'BOOKSTORE' => 'Review balances, accountabilities, and payment references.',
        'CLINIC' => 'Verify medical requirements and process clinic clearance.',
        'FACILITIES' => 'Track borrowed property and confirm required returns.',
        'ACCOUNTING' => 'Review financial obligations and receipt verification.',
        'REGISTRAR' => 'Review final documents and clearance requirements.',
        'COMLAB' => 'Review laboratory incidents and equipment accountabilities.',
    ];
    $pageDescription = $officeDescriptions[$stationCode] ?? 'Review and process assigned student clearance records.';
    $sidebarItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/' . strtolower(str_replace('_', '-', $stationCode)) . '/dashboard.php', 'icon' => 'dashboard'],
    ];
    if ($stationCode === 'CLINIC') {
        $sidebarItems[] = ['key' => 'schedules', 'label' => 'Medical Schedules', 'url' => '/clinic/schedules.php', 'icon' => 'document'];
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
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= escapeHtml($title) ?> | ClearGo</title>
        <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/office-dashboard.css')) ?>">
        <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
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
                        <div class="topbar-account"><strong><?= escapeHtml($user['email']) ?></strong><span>Office staff account</span></div><?= dashboardNotificationBell($notifications) ?>
                    </div>
                </header>
                <main class="staff-main">
                    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
                    <?php if ($stationCode === 'CLINIC'): ?><section class="panel workbook-panel">
                            <div class="section-heading">
                                <div>
                                    <h2>Clinic workbook processing</h2>
                                    <p class="muted">Process current student clearance records in the official ClearGo workbook.</p>
                                </div>
                            </div>
                            <div class="workbook-grid">
                                <div class="workbook-step"><strong>1. Download and update</strong><span class="muted">The workbook contains separate CCS, CBE, and CTE sheets. Enter <strong>CLEARED</strong> in Clinic Status only after requirements are complete.</span>
                                    <p><a class="button-link" href="<?= escapeHtml(applicationUrl('/clinic/workbook_template.php')) ?>">Download 3-sheet template</a></p>
                                </div>
                                <div class="workbook-step"><strong>2. Upload the completed workbook</strong>
                                    <form class="file-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/clinic/workbook_import.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="file" name="clinic_workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-label="Completed Clinic Excel workbook">
                                        <div class="inline"><button type="submit">Upload and clear students</button></div>
                                    </form>
                                </div>
                            </div>
                            <p class="muted">Students are matched by Student Number. Names are shown for checking only, so spelling differences do not affect the import.</p>
                        </section><?php endif; ?>
                    <?php if ($stationCode === 'LIBRARY'): ?><section class="panel workbook-panel">
                            <h2>Library Excel Workbook</h2>
                            <p>Download the student list separated into CCS, CBE, and CTE sheets. Enter <strong>CLEARED</strong> only after the Librarian or Library Desk verifies the student's library card.</p>
                            <div class="controls"><a class="button-link" href="<?= escapeHtml(applicationUrl('/library/workbook_template.php')) ?>">Download 3-Sheet Excel Template</a>
                                <form class="inline" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/library/workbook_import.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="file" name="library_workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-label="Completed Library Excel workbook"><button type="submit">Upload and Clear Students</button></form>
                            </div>
                            <p class="muted">Students are matched by Student Number, so name spelling differences will not affect the import.</p>
                        </section><?php endif; ?>
                    <?php if ($stationCode === 'BOOKSTORE'): ?><section class="panel workbook-panel">
                            <h2>Bookstore Excel Workbook</h2>
                            <p>Download the current roster with pending balances and accountabilities separated into CCS, CBE, and CTE sheets. Verify each student's ID and payment receipt or reference against Bookstore records, then mark <strong>CLEARED</strong> only after all obligations are settled.</p>
                            <div class="controls"><a class="button-link" href="<?= escapeHtml(applicationUrl('/bookstore/workbook_template.php')) ?>">Download 3-Sheet Excel Template</a>
                                <form class="inline" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/bookstore/workbook_import.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="file" name="bookstore_workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-label="Completed Bookstore Excel workbook"><button type="submit">Upload and Clear Students</button></form>
                            </div>
                            <p class="muted">Students are matched by Student Number. Name spelling differences do not affect the import. Use remarks to record missing documents or follow-up instructions.</p>
                        </section><?php endif; ?>
                    <?php if ($readiness !== null && $readiness['clearance_status'] === 'PREPARING'): ?><section class="panel">
                            <h2>Semester preparation</h2>
                            <p><strong><?= escapeHtml($readiness['semester'] . ' Semester A.Y. ' . $readiness['school_year']) ?></strong></p>
                            <p>Review records and take action now. Clear complete students or return incomplete records, then mark the office Ready.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">Your office is READY.</p>
                                <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="text" name="notes" maxlength="500" placeholder="Optional readiness note"><button type="submit" name="action" value="ready">Mark Office Ready</button></form><?php endif; ?>
                        </section><?php endif; ?>
                    <?php if ($stationCode === 'STUDENT_COUNCIL'): ?><section class="panel">
                            <h2>Institutional Event Attendance</h2>
                            <p>Create required institutional activities and open a QR code during each activity. Every enrolled student can check in; attendance is recorded in ClearGo for clearance verification.</p>
                            <form class="controls" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="create"><input type="text" name="name" maxlength="200" required placeholder="Required institutional activity"><label>Date <input type="date" name="event_date" required value="<?= escapeHtml(date('Y-m-d')) ?>"></label><label>Start <input type="time" name="start_time"></label><label>End <input type="time" name="end_time"></label><button type="submit">Create Required Event</button></form><?php if ($councilEvents === []): ?><p class="muted">No institutional activities have been created for this semester.</p><?php else: ?><div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Date / Time</th>
                                                <th>Attendance</th>
                                                <th>QR Check-in</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($councilEvents as $event): ?><tr>
                                                    <td><strong><?= escapeHtml($event['name']) ?></strong></td>
                                                    <td><?= escapeHtml($event['event_date']) ?><br><span class="muted"><?= escapeHtml(trim(($event['start_time'] ?? '') . ' - ' . ($event['end_time'] ?? ''), ' -')) ?></span></td>
                                                    <td><?= (int) $event['present_count'] ?> / <?= (int) $event['enrolled_count'] ?> present</td>
                                                    <td><?php if ((int) $event['qr_is_open'] === 1): ?><span class="badge approved">OPEN</span> until <?= escapeHtml($event['check_in_closes_at']) ?> <a href="<?= escapeHtml(applicationUrl('/student-council/event_qr.php?event=' . (int) $event['id'])) ?>">Display QR</a>
                                                            <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="secondary" type="submit">Close QR</button></form><?php else: ?><form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="open_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><select name="minutes">
                                                                    <option value="5">5 minutes</option>
                                                                    <option value="10" selected>10 minutes</option>
                                                                    <option value="15">15 minutes</option>
                                                                    <option value="30">30 minutes</option>
                                                                    <option value="60">1 hour</option>
                                                                </select><button type="submit">Open QR</button></form><?php endif; ?>
                                                    </td>
                                                </tr><?php endforeach; ?></tbody>
                                    </table>
                                </div><?php endif; ?>
                        </section><?php endif; ?>
                    <?php if ($stationCode === 'STUDENT_COUNCIL' && $councilEvents !== []): ?>
                        <section class="panel">
                            <h2>Delete institutional activity</h2>
                            <p class="muted">Deleting an activity permanently removes its QR access and recorded attendance.</p>
                            <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>" onsubmit="return confirm('Delete this event and all of its attendance records?');">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <select name="event_id" required><?php foreach ($councilEvents as $event): ?><option value="<?= (int) $event['id'] ?>"><?= escapeHtml($event['name'] . ' — ' . $event['event_date']) ?></option><?php endforeach; ?></select>
                                <button class="danger" type="submit">Delete event</button>
                            </form>
                        </section>
                    <?php endif; ?>
                    <section class="panel">
                        <div class="section-heading">
                            <div>
                                <h2>Clearance summary</h2>
                                <p class="muted">Current status across the active student roster.</p>
                            </div>
                        </div>
                        <div class="summary">
                            <div>Total Students<strong><?= $summary['TOTAL'] ?></strong></div>
                            <?php if ($stationCode === 'CLINIC'): ?>
                                <div>For Compliance<strong><?= $summary['TOTAL'] - $summary['APPROVED'] ?></strong></div>
                            <?php else: ?>
                                <div>Ready<strong><?= $summary['READY'] ?></strong></div>
                                <div>Pending<strong><?= $summary['PENDING'] ?></strong></div>
                                <div>Returned<strong><?= $summary['RETURNED'] ?></strong></div>
                            <?php endif; ?>
                            <div>Cleared<strong><?= $summary['APPROVED'] ?></strong></div>
                        </div>
                    </section>
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
                            </select><select name="filter" aria-label="Filter by clearance status"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml($filterLabels[$option]) ?></option><?php endforeach; ?></select><button type="submit">Search</button><?php if (!$directClearOffice): ?><a href="?search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;audience=<?= urlencode($facilitiesAudience) ?>&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;export=csv">Export CSV report</a><?php endif; ?></form>
                    </section>
                    <?php $pendingAccountingRows = $stationCode === 'ACCOUNTING' ? array_filter($rows, static fn(array $row): bool => $row['station_status'] === 'PENDING') : []; ?>
                    <?php if ($pendingAccountingRows !== []): ?><section class="panel">
                            <h2>Manual Accounting clearance</h2>
                            <p class="muted">Accounting may manually clear students who have reached this office. This settles listed Accounting obligations and records the approval in the audit log.</p>
                            <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Manually clear this student in Accounting?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><select name="clearance_id" required><?php foreach ($pendingAccountingRows as $row): ?><option value="<?= (int) $row['clearance_id'] ?>"><?= escapeHtml($row['student_number'] . ' — ' . $row['first_name'] . ' ' . $row['last_name']) ?></option><?php endforeach; ?></select><button name="action" value="manual_accounting_clear">Manually Clear</button></form>
                        </section><?php endif; ?>
                    <?php $pendingRegistrarRows = $stationCode === 'REGISTRAR' ? array_filter($rows, static fn(array $row): bool => $row['station_status'] === 'PENDING') : []; ?>
                    <?php if ($pendingRegistrarRows !== []): ?><section class="panel">
                            <h2>Manual final clearance</h2>
                            <p class="muted">Registrar may manually complete a clearance once the student has reached Registrar. This approval is retained in the audit log.</p>
                            <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Manually complete this student’s clearance?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><select name="clearance_id" required><?php foreach ($pendingRegistrarRows as $row): ?><option value="<?= (int) $row['clearance_id'] ?>"><?= escapeHtml($row['student_number'] . ' — ' . $row['first_name'] . ' ' . $row['last_name']) ?></option><?php endforeach; ?></select><button name="action" value="manual_registrar_clear">Complete Clearance</button></form>
                        </section><?php endif; ?>
                    <section class="panel">
                        <div class="section-heading">
                            <div>
                                <h2>Student clearance records</h2>
                                <p class="muted">Review each student's current eligibility and available action.</p>
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
                                            <?php $section = trim((string) $row['section']); ?>
                                            <td>Year <?= (int) $row['year_level'] ?><?php if ($section !== '' && strtoupper($section) !== 'N/A'): ?><div class="muted"><?= escapeHtml($section) ?></div><?php endif; ?></td>
                                            <td><?= escapeHtml($row['organization_code']) ?></td>
                                            <td><?= $stationCode === 'BOOKSTORE' ? escapeHtml($row['eligibility']['details'][0] ?? 'Needs Bookstore review') : ($row['eligibility']['eligible'] ? 'All checks satisfied' : escapeHtml($row['eligibility']['details'][0] ?? 'Needs review')) ?></td>
                                            <td><span class="badge <?= strtolower(str_replace('_', '-', $row['view_status'])) ?>"><?= $row['station_status'] === 'APPROVED' ? 'CLEARED' : escapeHtml(str_replace('_', ' ', $row['view_status'])) ?></span></td>
                                            <td><?php if (in_array($row['station_status'], ['APPROVED', 'LOCKED'], true)): ?><span class="muted">—</span><?php elseif ($stationCode === 'FACILITIES'): ?><button type="button" class="secondary" onclick="openFacilitiesItemModal(<?= (int) $row['clearance_id'] ?>)">Borrowed Item</button><?php if ($row['eligibility']['eligible']): ?> <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Facilities?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php endif; ?><?php elseif ($stationCode === 'CLINIC'): ?><div class="inline"><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Mark this student as having no medical record?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><input type="hidden" name="status" value="RETURNED"><input type="hidden" name="remarks" value="No medical record on file."><button class="danger" name="action" value="medical_status">No Medical</button></form><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student at the Clinic?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="clinic_clear">Clear</button></form></div><?php elseif ($directClearOffice): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="library_clear">Clear</button></form><?php elseif ($stationCode === 'SASO'): ?><?php if ($row['eligibility']['eligible']): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for SASO?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php else: ?><span class="muted">—</span><?php endif; ?><?php elseif ($stationCode === 'STUDENT_COUNCIL' && $row['eligibility']['eligible']): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Student Council?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><button name="action" value="approve">Clear</button></form><?php else: ?><a href="?clearance=<?= (int) $row['clearance_id'] ?>&amp;search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;audience=<?= urlencode($facilitiesAudience) ?>&amp;department=<?= urlencode($facilitiesDepartment) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;page=<?= $page ?>"><?= $stationCode === 'BOOKSTORE' ? 'Review' : ($row['clearance_status'] === 'PREPARING' ? 'Take Action' : 'Review') ?></a><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach;
                                    if ($visible === 0): ?><tr>
                                            <td colspan="9">No students match this view.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p><?= renderPagination($page, $totalPages, ['search' => $search, 'course' => $courseFilter, 'year_level' => $yearFilter, 'filter' => $filter, 'audience' => $facilitiesAudience, 'department' => $facilitiesDepartment]) ?>
                    </section>
                    <?php if ($stationCode === 'FACILITIES'): ?><div id="facilities-item-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="facilities-item-title">
                            <div class="modal">
                                <h2 id="facilities-item-title">Record borrowed item</h2>
                                <p class="muted">This item must be returned before Facilities can clear the student.</p>
                                <form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" id="facilities-item-clearance" name="clearance_id"><input type="text" name="description" maxlength="255" required placeholder="Borrowed item">
                                    <p><input type="text" name="remarks" maxlength="1000" placeholder="Optional item details"></p>
                                    <div class="actions"><button type="button" class="secondary" onclick="closeFacilitiesItemModal()">Cancel</button><button name="action" value="add_facilities_item">Save borrowed item</button></div>
                                </form>
                            </div>
                        </div>
                        <script src="<?= escapeHtml(applicationUrl('/assets/js/office-dashboard.js')) ?>"></script><?php endif; ?>

                    <?php if ($selected !== null): ?>
                        <?php if (!in_array($stationCode, ['BOOKSTORE', 'SASO', 'FACILITIES'], true)): ?>
                            <section class="panel">
                                <div class="detail-grid">
                                    <div>
                                        <h2><?= escapeHtml($selected['first_name'] . ' ' . $selected['last_name']) ?></h2>
                                        <p><strong><?= escapeHtml($selected['student_number']) ?></strong><br><?= escapeHtml($selected['program']) ?> · Year <?= (int) $selected['year_level'] ?><br><?= escapeHtml($selected['organization_name']) ?></p><?php $selectedDisplayStatus = $selected['station_status'];
                                                                                                                                                                                                                                                            if ($selected['station_status'] === 'PENDING' && $selected['eligibility']['eligible']) {
                                                                                                                                                                                                                                                                $selectedDisplayStatus = 'READY';
                                                                                                                                                                                                                                                            } ?><p>Status: <span class="badge <?= strtolower($selectedDisplayStatus) ?>"><?= escapeHtml(str_replace('_', ' ', $selectedDisplayStatus)) ?></span></p><?php if ($selected['returned_reason']): ?><p class="message"><?= escapeHtml($selected['returned_reason']) ?></p><?php endif; ?>
                                    </div><?php if ($stationCode !== 'BOOKSTORE'): ?><div>
                                            <h2>Eligibility checks</h2>
                                            <ul class="requirement-list"><?php foreach ($selected['eligibility']['details'] as $detail): ?><li><?= escapeHtml($detail) ?></li><?php endforeach; ?></ul>
                                        </div><?php endif; ?>
                                </div>
                            </section>

                        <?php endif; ?>
                        <?php if (!in_array($stationCode, ['SASO', 'FACILITIES'], true) && $selected['station_status'] !== 'LOCKED'): ?><section class="panel">
                                <h2>Office records</h2>
                                <?php if ($stationCode === 'CLINIC'): ?>
                                    <p>Review the official medical record. Clicking Clear verifies the medical requirement and approves Clinic clearance in one step.</p><?php if ($selected['station_status'] === 'APPROVED'): ?><p class="message">This student is already cleared by the Clinic.</p><?php else: ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student at the Clinic?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="remarks" placeholder="Optional Clinic remarks"></textarea><button name="action" value="clinic_clear">Clear</button></form><?php endif; ?>
                                <?php elseif ($stationCode === 'STUDENT_COUNCIL'): ?>
                                    <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml(str_replace('_', ' ', $record['status'])) ?></span>
                                            <div class="muted"><?= escapeHtml($record['description'] ?? '') ?></div><?php if (!in_array($record['status'], ['VERIFIED', 'RESOLVED'], true)): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="requirement_record_id" value="<?= (int) $record['id'] ?>"><input type="text" name="remarks" placeholder="Verification note"><button name="action" value="verify_requirement">Verify</button></form><?php endif; ?>
                                        </div><?php endforeach; ?>
                                <?php elseif ($stationCode === 'LIBRARY'): ?>
                                    <p>Verify the student's physical library card. No library card means the student cannot be cleared.</p><?php if ($selected['station_status'] === 'APPROVED'): ?><p class="message">This student is already cleared by the Library.</p><?php else: ?><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Confirm that the library card was presented and clear this student?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><button name="action" value="library_clear">Clear</button></form><?php endif; ?>
                                <?php elseif ($stationCode === 'BOOKSTORE'): ?>
                                    <p>Review the student's name and ID, verify balances and accountabilities against Bookstore records, and check the payment receipt before marking an obligation settled. Add remarks for missing requirements or follow-up. Bookstore clearance requires all obligations to be settled; exceptions are not allowed.</p><?php if ($detailRecords === []): ?><p class="muted">No Bookstore obligations recorded for this semester.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?> — PHP <?= number_format((float) $record['amount'], 2) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><?php if ($record['payment_reference']): ?><div class="muted">Receipt/reference: <?= escapeHtml($record['payment_reference']) ?></div><?php endif; ?><?php if ($record['remarks']): ?><div class="muted"><?= escapeHtml($record['remarks']) ?></div><?php endif; ?><?php if ($record['status'] === 'UNPAID'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="obligation_id" value="<?= (int) $record['id'] ?>"><input type="text" name="payment_reference" required placeholder="Payment receipt/reference"><input type="text" name="remarks" maxlength="1000" placeholder="Settlement remarks"><button name="action" value="clear_bookstore_obligation">Mark Settled</button></form><?php endif; ?></div><?php endforeach; ?><h3>Add pending obligation</h3>
                                    <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="description" maxlength="255" required placeholder="Balance, missing requirement, or accountability"><input type="number" name="amount" min="0" step="0.01" required value="0" placeholder="Amount"><input type="text" name="remarks" maxlength="1000" placeholder="Remarks / document needed"><button name="action" value="add_bookstore_obligation">Add Obligation</button></form>
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
                                    <h3>Issue receipt for photo verification</h3>
                                    <p class="muted">Enter the receipt number printed on the official paid receipt. The student must upload a clear photo that shows this exact code before Accounting can approve the clearance.</p>
                                    <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                        <input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>">
                                        <input type="text" name="receipt_code" required maxlength="255" placeholder="Official receipt number / QR code">
                                        <button name="action" value="register_accounting_receipt">Issue receipt code</button>
                                    </form>
                                <?php elseif ($stationCode === 'REGISTRAR'): ?>
                                    <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?></strong> <span class="badge <?= strtolower(str_replace('_', '-', $record['status'])) ?>"><?= escapeHtml(str_replace('_', ' ', $record['status'])) ?></span><?php if ($record['return_reason']): ?><div><?= escapeHtml($record['return_reason']) ?></div><?php endif; ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="document_id" value="<?= (int) $record['id'] ?>"><select name="status">
                                                    <option value="VERIFIED">Verified</option>
                                                    <option value="RETURNED">Returned</option>
                                                </select><input type="text" name="reason" placeholder="Return reason / verification note"><button name="action" value="document_status">Save</button></form>
                                        </div><?php endforeach; ?>
                                <?php endif; ?>
                            </section><?php endif; ?>

                        <?php if (in_array($stationCode, ['ACCOUNTING', 'REGISTRAR'], true) && $selected['station_status'] !== 'LOCKED'): ?><section class="panel">
                                <h2>Temporary exception</h2>
                                <p class="muted">Use only for an approved promissory note, pending grade/document, or formally authorized special case. This is recorded in the audit trail.</p>
                                <form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="reason" required placeholder="Approved exception and conditions"></textarea>
                                    <div class="inline"><input type="text" name="proof_reference" required placeholder="Promissory note / authorization reference"><label>Expires <input type="date" name="expires_at"></label><button name="action" value="approve_exception">Approve exception</button></div>
                                </form>
                            </section><?php endif; ?>

                        <?php if (!$directClearOffice && $stationCode !== 'FACILITIES'): ?><section class="panel">
                                <h2>Clearance decision</h2><?php if (!in_array($selected['clearance_status'], ['PREPARING', 'OPEN'], true)): ?><p class="message">Clearance processing is closed for this semester.</p><?php elseif ($selected['station_status'] === 'LOCKED'): ?><p class="message">The required preceding offices must clear this student before this office can take action.</p><?php elseif ($selected['station_status'] === 'APPROVED'): ?><p class="message">This office has cleared the student.</p><?php else: ?><?php if ($selected['clearance_status'] === 'PREPARING'): ?><p class="message">Preparation mode: your decision will be ready when students gain access.</p><?php endif; ?><div class="actions">
                                    <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for this office?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><button name="action" value="approve" <?= !$selected['eligibility']['eligible'] ? 'disabled title="Complete all requirements first"' : '' ?>>Clear</button></form>
                                    <form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="reason" required placeholder="Explain what the student must complete"></textarea><button class="danger" name="action" value="return">Return to student</button></form>
                                </div><?php endif; ?>
                            </section><?php endif; ?>
                    <?php endif; ?>
                </main>
            </div>
        </div>
        <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script>
    </body>

    </html>
<?php
    exit;
}
