<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/report_workbook.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';

$user = requireRole('DEAN');
$search = trim((string) ($_GET['search'] ?? ''));
$filter = strtoupper((string) ($_GET['filter'] ?? 'ALL'));
$courseFilter = trim((string) ($_GET['course'] ?? ''));
$yearFilter = filter_var($_GET['year_level'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]) ?: 0;
$allowedFilters = ['WAITING', 'APPROVED', 'ALL'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'ALL';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;
$cbeGroup = strtoupper((string) ($_GET['group'] ?? 'NON_GRADUATING'));
if (!in_array($cbeGroup, ['NON_GRADUATING', 'FOURTH_YEAR'], true)) {
    $cbeGroup = 'NON_GRADUATING';
}

try {
    $database = getDatabaseConnection();
    $dean = deanAssignment($database, (int) $user['id']);
    $isCbeDean = $dean['organization_code'] === 'CBE';
    $minimumBulkCount = deanMinimumBulkCount($database, $dean);
    $bulkReadyCount = count(bulkReadyClearanceIds($database, 'DEAN', (int) $dean['organization_id'], $isCbeDean ? $cbeGroup : ''));
    $readiness = officeReadiness($database, 'DEAN');
    $verifiedSubmissionQuery = $database->prepare("SELECT COUNT(*) FROM evaluation_submissions submission INNER JOIN clearances clearance ON clearance.id=submission.clearance_id INNER JOIN students student ON student.id=clearance.student_id INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' WHERE student.organization_id=:organization_id AND submission.status='VERIFIED'");
    $verifiedSubmissionQuery->execute(['organization_id' => $dean['organization_id']]);
    $verifiedSubmissionQuery->fetchColumn();
    $statement = $database->prepare(
        "SELECT clearances.id AS clearance_id, students.student_number,
                students.first_name, students.last_name, students.program, students.year_level,
                organizations.name AS organization_name,
                clearance_stations.status AS dean_status,
                (SELECT organization_record.status
                 FROM clearance_stations organization_record
                 INNER JOIN stations organization_station
                    ON organization_station.id=organization_record.station_id
                   AND organization_station.code='ORGANIZATION'
                 WHERE organization_record.clearance_id=clearances.id
                 LIMIT 1) AS organization_status,
                COALESCE(evaluation_verifications.status, 'NOT_SUBMITTED') AS evaluation_status,
                evaluation_verifications.verified_at,
                (SELECT COUNT(*) FROM requirement_records rr
                 INNER JOIN requirements r ON r.id=rr.requirement_id
                 WHERE rr.clearance_station_id=clearance_stations.id AND r.status='ACTIVE' AND r.name<>'Dean Approval') AS required_count,
                (SELECT COUNT(*) FROM requirement_records rr
                 INNER JOIN requirements r ON r.id=rr.requirement_id
                 WHERE rr.clearance_station_id=clearance_stations.id AND r.status='ACTIVE' AND r.name<>'Dean Approval'
                   AND rr.status IN ('VERIFIED','RESOLVED')) AS verified_count
                ,(SELECT COUNT(*) FROM requirement_records rr
                  INNER JOIN requirements r ON r.id=rr.requirement_id
                  WHERE rr.clearance_station_id=clearance_stations.id AND r.status='ACTIVE' AND r.name<>'Dean Approval'
                    AND rr.status NOT IN ('VERIFIED','RESOLVED')
                    AND NOT (organizations.code='CBE' AND students.year_level=4
                        AND r.requirement_code='CBE_OJT_REQUIREMENTS')) AS blocking_count
         FROM clearances
         INNER JOIN students ON students.id = clearances.student_id
         INNER JOIN organizations ON organizations.id = students.organization_id
         INNER JOIN stations ON stations.code = 'DEAN'
         INNER JOIN clearance_stations ON clearance_stations.clearance_id = clearances.id
            AND clearance_stations.station_id = stations.id
         LEFT JOIN evaluation_verifications ON evaluation_verifications.clearance_id = clearances.id
         INNER JOIN academic_periods ON academic_periods.id = clearances.academic_period_id
            AND academic_periods.status = 'ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.academic_period_id=academic_periods.id
            AND enrollment.student_id=students.id AND enrollment.status='ENROLLED'
         WHERE students.status = 'ACTIVE'
           AND students.organization_id = :organization_id
           AND (students.student_number LIKE :number_search
                OR students.first_name LIKE :first_search
                OR students.last_name LIKE :last_search
                OR students.program LIKE :program_search)
         ORDER BY students.last_name, students.first_name"
    );
    $allSearchTerm = '%';
    $statement->execute([
        'organization_id' => $dean['organization_id'],
        'number_search' => $allSearchTerm,
        'first_search' => $allSearchTerm,
        'last_search' => $allSearchTerm,
        'program_search' => $allSearchTerm,
    ]);
    $summaryRows = $statement->fetchAll();

    $cbeGroupCounts = ['NON_GRADUATING' => 0, 'FOURTH_YEAR' => 0];
    if ($isCbeDean) {
        foreach ($summaryRows as $row) {
            if ($row['dean_status'] !== 'APPROVED') {
                $cbeGroupCounts[(int) $row['year_level'] === 4 ? 'FOURTH_YEAR' : 'NON_GRADUATING']++;
            }
        }
    }

    $summaryScopeRows = $summaryRows;
    if ($isCbeDean) {
        $summaryScopeRows = array_values(array_filter(
            $summaryScopeRows,
            static fn(array $row): bool =>
            $cbeGroup === 'FOURTH_YEAR' ? (int) $row['year_level'] === 4 : (int) $row['year_level'] < 4
        ));
    }
    $summary = ['TOTAL' => count($summaryScopeRows), 'WAITING' => 0, 'VERIFIED' => 0, 'APPROVED' => 0];
    foreach ($summaryScopeRows as $row) {
        if ($row['dean_status'] === 'APPROVED') {
            $summary['APPROVED']++;
        } elseif ((int) $row['blocking_count'] === 0) {
            $summary['VERIFIED']++;
        } else {
            $summary['WAITING']++;
        }
    }

    $searchTerm = '%' . $search . '%';
    $statement->execute([
        'organization_id' => $dean['organization_id'],
        'number_search' => $searchTerm,
        'first_search' => $searchTerm,
        'last_search' => $searchTerm,
        'program_search' => $searchTerm,
    ]);
    $allRows = $statement->fetchAll();
    if ($isCbeDean) {
        $allRows = array_values(array_filter(
            $allRows,
            static fn(array $row): bool =>
            $cbeGroup === 'FOURTH_YEAR' ? (int) $row['year_level'] === 4 : (int) $row['year_level'] < 4
        ));
    }
    $courses = array_values(array_unique(array_column($summaryRows, 'program')));
    sort($courses, SORT_NATURAL | SORT_FLAG_CASE);
    $yearLevels = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['year_level'], $summaryRows)));
    sort($yearLevels, SORT_NUMERIC);

    $filteredRows = array_values(array_filter($allRows, static function (array $row) use ($filter, $courseFilter, $yearFilter): bool {
        if (($courseFilter !== '' && $row['program'] !== $courseFilter) || ($yearFilter !== 0 && (int) $row['year_level'] !== $yearFilter)) {
            return false;
        }
        return match ($filter) {
            'WAITING' => $row['dean_status'] !== 'APPROVED' && ((int) $row['blocking_count'] > 0 || $row['dean_status'] === 'RETURNED'),
            'VERIFIED' => $row['dean_status'] !== 'APPROVED' && $row['dean_status'] !== 'RETURNED' && (int) $row['blocking_count'] === 0,
            'APPROVED' => $row['dean_status'] === 'APPROVED',
            default => true,
        };
    }));
    $totalRows = count($filteredRows);
    if (($_GET['export'] ?? '') === 'xlsx') {
        $reportRows = [];
        foreach ($filteredRows as $row) {
            $status = $row['dean_status'] === 'APPROVED'
                ? 'CLEARED'
                : (((int) $row['blocking_count'] === 0 && $row['dean_status'] !== 'RETURNED') ? 'READY TO CLEAR' : 'FOR COMPLIANCE');
            $reportRows[] = [
                $row['student_number'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['program'],
                'Year ' . (int) $row['year_level'],
                $row['organization_name'],
                $row['required_count'] . ' required; ' . $row['verified_count'] . ' verified',
                str_replace('_', ' ', (string) $row['evaluation_status']),
                str_replace('_', ' ', (string) ($row['organization_status'] ?: 'PENDING')),
                $status,
            ];
        }
        $workbook = createClearanceReportXlsx(
            $dean['organization_code'] . ' Dean Clearance Report',
            activePeriodReportLabel($database),
            ['Student Number', 'Full Name', 'Course', 'Year Level', 'College', 'Requirements', 'Evaluation Status', 'Organization Status', 'Clearance Status'],
            [['name' => $dean['organization_code'], 'rows' => $reportRows]]
        );
        downloadClearanceReport($workbook, strtolower($dean['organization_code']) . '-dean-clearance-report.xlsx');
    }
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $rows = array_slice($filteredRows, ($page - 1) * $pageSize, $pageSize);
    $requirementQuery = $database->prepare(
        "SELECT record.id, requirement.name, requirement.requirement_code, record.status, record.remarks
         FROM requirement_records record
         INNER JOIN requirements requirement ON requirement.id=record.requirement_id
         INNER JOIN stations requirement_station ON requirement_station.id=requirement.station_id
            AND requirement_station.code='DEAN'
         INNER JOIN clearance_stations station_record ON station_record.id=record.clearance_station_id
         WHERE station_record.clearance_id=:clearance_id
           AND requirement.status='ACTIVE'
           AND requirement.name<>'Dean Approval'
         ORDER BY requirement.id"
    );
    foreach ($rows as &$row) {
        $requirementQuery->execute(['clearance_id' => $row['clearance_id']]);
        $row['requirements'] = $requirementQuery->fetchAll();
    }
    unset($row);
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Dean dashboard failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the Dean dashboard right now. Please try again later.');
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$pageTitle = $dean['organization_code'] . ' Dean Clearance';
$sidebar = staffDashboardSidebar($pageTitle, $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/dean/dashboard.php', 'icon' => 'dashboard'],
    ...($dean['organization_code'] === 'CCS' ? [['key' => 'submitted_requirements', 'label' => 'Submitted Requirements', 'url' => '/dean/submitted_requirements.php', 'icon' => 'document']] : []),
], 'dashboard', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($dean['organization_code']) ?> Dean Clearance | Digital Clearance System</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/dean-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1><?= escapeHtml($pageTitle) ?></h1>
                    <p>Review academic requirements and process eligible student clearances.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <?php if ($readiness['clearance_status'] === 'PREPARING'): ?><section class="panel">
                        <h2>Semester preparation</h2>
                        <p>Review requirements and take action now. Approve complete students and leave incomplete requirements pending so students can comply when the Registrar opens clearance.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">The Dean office is marked READY.</p>
                            <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input name="notes" maxlength="500" placeholder="Optional readiness note"><button type="submit" name="action" value="ready">Mark Dean Office Ready</button></form><?php endif; ?>
                    </section><?php endif; ?>
                <?php if ($isCbeDean): ?><section class="panel">
                        <h2>CBE student groups</h2>
                        <div class="group-tabs"><a href="?group=NON_GRADUATING&amp;filter=<?= urlencode($filter) ?>" <?= $cbeGroup === 'NON_GRADUATING' ? 'aria-current="page"' : '' ?>>Non-Graduating · <?= $cbeGroupCounts['NON_GRADUATING'] ?></a><a href="?group=FOURTH_YEAR&amp;filter=<?= urlencode($filter) ?>" <?= $cbeGroup === 'FOURTH_YEAR' ? 'aria-current="page"' : '' ?>>Fourth Year · <?= $cbeGroupCounts['FOURTH_YEAR'] ?></a></div>
                        <p class="muted">All CBE students require Organization/YBC sign-off before the CBE Dean can clear them. Fourth-year Dean approval also confirms the OJT requirement.</p>
                    </section><?php endif; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Clearance summary</h2>
                            <p class="muted">Current Dean status for this college.</p>
                        </div>
                    </div>
                    <div class="summary">
                        <div>Total Students<strong><?= $summary['TOTAL'] ?></strong></div>
                        <div>For Compliance<strong><?= $summary['WAITING'] ?></strong></div>
                        <div>Ready to Clear<strong><?= $summary['VERIFIED'] ?></strong></div>
                        <div>Cleared<strong><?= $summary['APPROVED'] ?></strong></div>
                    </div>
                </section>
            <section class="panel">
                <div class="section-heading">
                    <div>
                        <h2>Find student records</h2>
                        <p class="muted">Search the roster or filter by Dean clearance status.</p>
                    </div>
                </div>
                <form class="controls" method="get">
                    <?php if ($isCbeDean): ?><input type="hidden" name="group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?>
                    <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, or program">
                    <select name="course">
                        <option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= escapeHtml($course) ?>" <?= $courseFilter === $course ? 'selected' : '' ?>><?= escapeHtml($course) ?></option><?php endforeach; ?>
                    </select>
                    <select name="year_level">
                        <option value="0">All year levels</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endforeach; ?>
                    </select>
                    <?php $deanFilterLabels = ['WAITING' => 'For Compliance', 'APPROVED' => 'Cleared', 'ALL' => 'All Students']; ?>
                    <select name="filter"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml($deanFilterLabels[$option]) ?></option><?php endforeach; ?></select>
                    <button type="submit">Search</button>
                    <a class="button-link" href="?search=<?= urlencode($search) ?>&amp;course=<?= urlencode($courseFilter) ?>&amp;year_level=<?= $yearFilter ?>&amp;filter=<?= urlencode($filter) ?><?= $isCbeDean ? '&amp;group=' . urlencode($cbeGroup) : '' ?>&amp;export=xlsx">Export Excel report</a>
                </form>
            </section>
            <section class="panel">
                <div class="section-heading">
                    <div>
                        <h2>Student clearance records</h2>
                        <p class="muted">Review applicable requirements before taking action.</p>
                        <?php if ($dean['organization_code'] === 'CCS'): ?><p class="muted">Clear at least 5 ready students per batch. When fewer than 5 uncleared CCS students remain, you can clear the remaining ready students in bulk or individually.</p><?php endif; ?>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
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
                            <?php if ($rows === []): ?><tr>
                                    <td colspan="9">No students match this view.</td>
                                </tr><?php endif; ?>
                            <?php $rowNumber = ($page - 1) * $pageSize;
                            foreach ($rows as $row): $rowNumber++; ?>
                                <tr>
                                    <td><?= $rowNumber ?></td>
                                    <td><?= escapeHtml($row['student_number']) ?></td>
                                    <td><?= escapeHtml($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= escapeHtml($row['program']) ?></td>
                                    <td>Year <?= (int) $row['year_level'] ?></td>
                                    <td><?= escapeHtml($row['organization_name']) ?></td>
                                    <td>
                                        <?php if ($isCbeDean && $row['organization_status'] !== 'APPROVED'): ?><span class="muted">Organization/YBC approval required before Dean clearance</span><?php elseif ($row['requirements'] === []): ?><span class="muted">Dean approval only</span><?php endif; ?>
                                        <?php foreach ($row['requirements'] as $requirement): ?>
                                            <div class="requirement-item"><strong><?= escapeHtml($requirement['name']) ?></strong><?php if (!in_array($requirement['requirement_code'], ['CBE_YBC_APPROVAL', 'CCS_FACULTY_EVALUATION', 'CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true)): ?><br><span class="badge <?= strtolower(str_replace('_', '-', $requirement['status'])) ?>"><?= escapeHtml(statusDisplayLabel($requirement['status'])) ?></span><?php endif; ?>
                                                <?php if (!in_array($requirement['requirement_code'], ['CBE_YBC_APPROVAL', 'CBE_OJT_REQUIREMENTS', 'CCS_FACULTY_EVALUATION', 'CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true) && !in_array($requirement['status'], ['VERIFIED', 'RESOLVED'], true) && $row['dean_status'] !== 'APPROVED'): ?><form class="requirement-action" method="post" action="<?= escapeHtml(applicationUrl('/dean/action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><?php if ($isCbeDean): ?><input type="hidden" name="return_group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><input type="hidden" name="requirement_record_id" value="<?= (int) $requirement['id'] ?>"><button name="action" value="verify_requirement">Verify Requirement</button></form><?php elseif ($requirement['requirement_code'] === 'CBE_YBC_APPROVAL' && !in_array($requirement['status'], ['VERIFIED', 'RESOLVED'], true)): ?><br><span class="muted">Waiting for YBC organization sign-off</span><?php elseif ($requirement['requirement_code'] === 'CBE_OJT_REQUIREMENTS' && !in_array($requirement['status'], ['VERIFIED', 'RESOLVED'], true)): ?><br><span class="muted">Will be confirmed when the CBE Dean approves</span><?php elseif (in_array($requirement['requirement_code'], ['CCS_FACULTY_EVALUATION', 'CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true) && !in_array($requirement['status'], ['VERIFIED', 'RESOLVED'], true)): ?><br><span class="muted">Will be confirmed when the CCS Dean approves</span><?php endif; ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><span class="badge <?= strtolower($row['dean_status']) ?>"><?= $row['dean_status'] === 'APPROVED' ? 'CLEARED' : escapeHtml(statusDisplayLabel($row['dean_status'])) ?></span></td>
                                    <td>
                                        <?php if (in_array($readiness['clearance_status'], ['PREPARING', 'OPEN'], true) && (int) $row['blocking_count'] === 0 && $row['dean_status'] === 'PENDING' && (!$isCbeDean || $row['organization_status'] === 'APPROVED') && $minimumBulkCount === 1): ?>
                                            <form method="post" action="<?= escapeHtml(applicationUrl('/dean/action.php')) ?>" onsubmit="return confirm('Clear this student at the Dean office?');">
                                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                <?php if ($isCbeDean): ?><input type="hidden" name="return_group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?>
                                                <input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>">
                                                <button type="submit" name="action" value="approve">Clear</button>
                                            </form>
                                        <?php elseif ($row['dean_status'] === 'APPROVED'): ?><span class="muted">Approved</span><?php elseif ($minimumBulkCount > 1 && $row['dean_status'] === 'PENDING' && (int) $row['blocking_count'] === 0): ?><span class="muted">Use bulk clearance (minimum 5)</span><?php else: ?><span class="muted">Requirements incomplete</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p>
                <?= renderBulkClearanceFooter($page, $totalPages, array_filter(['search' => $search, 'course' => $courseFilter, 'year_level' => $yearFilter, 'filter' => $filter, 'group' => $isCbeDean ? $cbeGroup : null], static fn($value): bool => $value !== null), applicationUrl('/dean/action.php'), $bulkReadyCount, $dean['organization_code'] . ' Dean clearance' . ($isCbeDean ? ' (' . str_replace('_', ' ', $cbeGroup) . ')' : ''), $isCbeDean ? ['return_group' => $cbeGroup] : [], $minimumBulkCount) ?>
            </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>

</html>
