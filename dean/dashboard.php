<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';

$user = requireRole('DEAN');
$search = trim((string) ($_GET['search'] ?? ''));
$filter = strtoupper((string) ($_GET['filter'] ?? 'WAITING'));
$allowedFilters = ['WAITING', 'VERIFIED', 'APPROVED', 'ALL'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'WAITING';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;
$cbeGroup = strtoupper((string) ($_GET['group'] ?? 'NON_GRADUATING'));
if (!in_array($cbeGroup, ['NON_GRADUATING','FOURTH_YEAR'], true)) { $cbeGroup = 'NON_GRADUATING'; }

try {
    $database = getDatabaseConnection();
    $dean = deanAssignment($database, (int) $user['id']);
    $isCbeDean = $dean['organization_code'] === 'CBE';
    $readiness = officeReadiness($database, 'DEAN');
    $statement = $database->prepare(
        "SELECT clearances.id AS clearance_id, students.student_number,
                students.first_name, students.last_name, students.program, students.year_level,
                organizations.name AS organization_name,
                clearance_stations.status AS dean_status,
                COALESCE(evaluation_verifications.status, 'NOT_SUBMITTED') AS evaluation_status,
                evaluation_verifications.verified_at,
                (SELECT COUNT(*) FROM requirement_records rr
                 INNER JOIN requirements r ON r.id=rr.requirement_id
                 WHERE rr.clearance_station_id=clearance_stations.id AND r.name<>'Dean Approval') AS required_count,
                (SELECT COUNT(*) FROM requirement_records rr
                 INNER JOIN requirements r ON r.id=rr.requirement_id
                 WHERE rr.clearance_station_id=clearance_stations.id AND r.name<>'Dean Approval'
                   AND rr.status IN ('VERIFIED','RESOLVED')) AS verified_count
                ,(SELECT COUNT(*) FROM requirement_records rr
                  INNER JOIN requirements r ON r.id=rr.requirement_id
                  WHERE rr.clearance_station_id=clearance_stations.id AND r.name<>'Dean Approval'
                    AND rr.status NOT IN ('VERIFIED','RESOLVED')
                    AND NOT (organizations.code='CCS' AND r.requirement_code IN
                        ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION'))
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
    $searchTerm = '%' . $search . '%';
    $statement->execute([
        'organization_id' => $dean['organization_id'],
        'number_search' => $searchTerm,
        'first_search' => $searchTerm,
        'last_search' => $searchTerm,
        'program_search' => $searchTerm,
    ]);
    $allRows = $statement->fetchAll();

    $cbeGroupCounts = ['NON_GRADUATING' => 0, 'FOURTH_YEAR' => 0];
    if ($isCbeDean) {
        foreach ($allRows as $row) {
            $cbeGroupCounts[(int) $row['year_level'] === 4 ? 'FOURTH_YEAR' : 'NON_GRADUATING']++;
        }
        $allRows = array_values(array_filter($allRows, static fn(array $row): bool =>
            $cbeGroup === 'FOURTH_YEAR' ? (int) $row['year_level'] === 4 : (int) $row['year_level'] < 4
        ));
    }

    $summary = ['WAITING' => 0, 'VERIFIED' => 0, 'APPROVED' => 0];
    foreach ($allRows as $row) {
        if ($row['dean_status'] === 'APPROVED') {
            $summary['APPROVED']++;
        } else {
            $summary['WAITING']++;
            if ((int) $row['blocking_count'] === 0) {
                $summary['VERIFIED']++;
            }
        }
    }

    $filteredRows = array_values(array_filter($allRows, static function (array $row) use ($filter): bool {
        return match ($filter) {
            'WAITING' => $row['dean_status'] !== 'APPROVED',
            'VERIFIED' => $row['dean_status'] !== 'APPROVED' && (int) $row['blocking_count'] === 0,
            'APPROVED' => $row['dean_status'] === 'APPROVED',
            default => true,
        };
    }));
    $totalRows = count($filteredRows);
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
           AND requirement.name<>'Dean Approval'
         ORDER BY requirement.id"
    );
    foreach ($rows as &$row) {
        $requirementQuery->execute(['clearance_id' => $row['clearance_id']]);
        $row['requirements'] = $requirementQuery->fetchAll();
    }
    unset($row);
} catch (Throwable $exception) {
    error_log('Dean dashboard failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the Dean dashboard right now. Please try again later.');
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($dean['organization_code']) ?> Dean Clearance | Digital Clearance System</title>
    <style>
        :root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}header{background:#fff;border-bottom:1px solid var(--line);padding:18px 24px}header div,main{max-width:1150px;margin:auto}.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px}.logout-form{margin:0}h1{margin:0;color:var(--blue);font-size:24px}main{padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.summary div{background:#f4f8fc;border-radius:7px;padding:12px}.summary strong{display:block;font-size:24px;color:var(--blue)}.controls,.group-tabs{display:flex;gap:10px;flex-wrap:wrap}.group-tabs a{border:1px solid var(--blue);border-radius:5px;color:var(--blue);padding:10px 14px;text-decoration:none}.group-tabs a[aria-current=page]{background:var(--blue);color:#fff}input,select,button{border:1px solid #b7c8d8;border-radius:5px;font:inherit;padding:9px}input{min-width:260px}button{background:var(--blue);border:0;color:#fff;cursor:pointer}.logout-form button{padding:9px 14px}.message{padding:12px;background:#edf6ff;border-left:4px solid var(--blue)}table{border-collapse:collapse;width:100%}th,td{border-top:1px solid var(--line);padding:12px;text-align:left;vertical-align:middle}th{font-size:13px;color:#587086}.badge{border-radius:99px;font-size:12px;font-weight:bold;padding:6px 9px;white-space:nowrap}.verified,.approved{background:#e4f5eb;color:#176b3a}.not-submitted,.error{background:#eef1f4;color:#536473}.rejected,.returned{background:#fff0ed;color:#a33722}.pending{background:#fff7e8;color:#956200}.muted{color:#587086;font-size:13px}@media(max-width:760px){main,header{padding:16px}.summary{grid-template-columns:1fr}.table-wrap{overflow-x:auto}input{min-width:0;width:100%}}
        .pagination{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:14px}.pagination-link,.pagination-ellipsis{display:grid;place-items:center;min-width:42px;height:42px;padding:0 12px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--dark);font-weight:600;text-decoration:none}.pagination-link.active{border-color:var(--blue);background:var(--blue);color:#fff}.pagination-ellipsis{border:0;background:transparent}
    </style>
</head>
<body>
<header><div class="topbar"><h1><?= escapeHtml($dean['organization_code']) ?> Dean Clearance</h1><?= $logoutForm ?></div></header>
<main>
    <section class="panel"><strong><?= escapeHtml($dean['organization_name']) ?></strong><p class="muted">This dashboard only includes students whose courses belong to <?= escapeHtml($dean['organization_code']) ?>.</p></section>
    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
    <?php if ($readiness['clearance_status'] === 'PREPARING'): ?><section class="panel"><h2>Semester preparation</h2><p>Review requirements and take action now. Approve complete students and leave incomplete requirements pending so students can comply when the Registrar opens clearance.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">The Dean office is marked READY.</p><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input name="notes" maxlength="500" placeholder="Optional readiness note"><button type="submit" name="action" value="ready">Mark Dean Office Ready</button></form><?php endif; ?></section><?php endif; ?>
    <?php if ($isCbeDean): ?><section class="panel"><h2>CBE student groups</h2><div class="group-tabs"><a href="?group=NON_GRADUATING&amp;filter=<?= urlencode($filter) ?>" <?= $cbeGroup === 'NON_GRADUATING' ? 'aria-current="page"' : '' ?>>Non-Graduating · <?= $cbeGroupCounts['NON_GRADUATING'] ?></a><a href="?group=FOURTH_YEAR&amp;filter=<?= urlencode($filter) ?>" <?= $cbeGroup === 'FOURTH_YEAR' ? 'aria-current="page"' : '' ?>>Fourth Year · <?= $cbeGroupCounts['FOURTH_YEAR'] ?></a></div><p class="muted"><?= $cbeGroup === 'NON_GRADUATING' ? 'Years 1–3 require YBC organization sign-off before final CBE Dean approval.' : 'For fourth-year students, one Approve click confirms the OJT requirement and clears the Dean step.' ?></p><form method="post" action="<?= escapeHtml(applicationUrl('/dean/action.php')) ?>" onsubmit="return confirm('Approve every eligible student in this group?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="return_group" value="<?= escapeHtml($cbeGroup) ?>"><button name="action" value="approve_all_cbe_group"><?= $cbeGroup === 'NON_GRADUATING' ? 'Approve All Approved by YBC (CBE Org)' : 'Approve All Fourth-Year Students' ?></button></form></section><?php endif; ?>
    <section class="panel"><div class="summary"><div>Waiting<strong><?= $summary['WAITING'] ?></strong></div><div>Ready for Approval<strong><?= $summary['VERIFIED'] ?></strong></div><div>Approved<strong><?= $summary['APPROVED'] ?></strong></div></div></section>
    <section class="panel">
        <form class="controls" method="get">
            <?php if ($isCbeDean): ?><input type="hidden" name="group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?>
            <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, or program">
            <select name="filter"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml(ucwords(strtolower($option))) ?></option><?php endforeach; ?></select>
            <button type="submit">Search</button>
        </form>
    </section>
    <section class="panel">
        <div class="table-wrap"><table>
            <thead><tr><th>Student ID</th><th>Student Name</th><th>Program</th><th>College</th><th>Applicable Requirements</th><th>Dean Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="7">No students match this view.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= escapeHtml($row['student_number']) ?></td>
                    <td><?= escapeHtml($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= escapeHtml($row['program']) ?></td>
                    <td><?= escapeHtml($row['organization_name']) ?></td>
                    <td>
                        <?php if ($row['requirements'] === []): ?><span class="muted">Dean approval only</span><?php endif; ?>
                        <?php foreach ($row['requirements'] as $requirement): ?>
                            <div style="margin-bottom:8px"><strong><?= escapeHtml($requirement['name']) ?></strong><br><span class="badge <?= strtolower(str_replace('_', '-', $requirement['status'])) ?>"><?= escapeHtml(str_replace('_', ' ', $requirement['status'])) ?></span>
                            <?php if (!in_array($requirement['requirement_code'], ['CBE_YBC_APPROVAL','CBE_OJT_REQUIREMENTS','CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true) && !in_array($requirement['status'], ['VERIFIED','RESOLVED'], true) && $row['dean_status'] !== 'APPROVED'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/dean/action.php')) ?>" style="margin-top:5px"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><?php if ($isCbeDean): ?><input type="hidden" name="return_group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?><input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>"><input type="hidden" name="requirement_record_id" value="<?= (int) $requirement['id'] ?>"><button name="action" value="verify_requirement">Verify Requirement</button></form><?php elseif ($requirement['requirement_code'] === 'CBE_YBC_APPROVAL' && !in_array($requirement['status'], ['VERIFIED','RESOLVED'], true)): ?><br><span class="muted">Waiting for YBC organization sign-off</span><?php elseif ($requirement['requirement_code'] === 'CBE_OJT_REQUIREMENTS' && !in_array($requirement['status'], ['VERIFIED','RESOLVED'], true)): ?><br><span class="muted">Will be confirmed when the CBE Dean approves</span><?php elseif (in_array($requirement['requirement_code'], ['CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true) && !in_array($requirement['status'], ['VERIFIED','RESOLVED'], true)): ?><br><span class="muted">Will be confirmed when the CCS Dean approves</span><?php endif; ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td><span class="badge <?= strtolower($row['dean_status']) ?>"><?= $row['dean_status'] === 'APPROVED' ? 'CLEARED' : escapeHtml(str_replace('_', ' ', $row['dean_status'])) ?></span></td>
                    <td>
                        <?php if (in_array($readiness['clearance_status'], ['PREPARING','OPEN'], true) && (int) $row['blocking_count'] === 0 && $row['dean_status'] !== 'APPROVED'): ?>
                            <form method="post" action="<?= escapeHtml(applicationUrl('/dean/action.php')) ?>" onsubmit="return confirm('Approve Dean clearance for this student?');">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                <?php if ($isCbeDean): ?><input type="hidden" name="return_group" value="<?= escapeHtml($cbeGroup) ?>"><?php endif; ?>
                                <input type="hidden" name="clearance_id" value="<?= (int) $row['clearance_id'] ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                            </form>
                        <?php elseif ($row['dean_status'] === 'APPROVED'): ?><span class="muted">Approved</span><?php else: ?><span class="muted">Requirements incomplete</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p>
        <?= renderPagination($page, $totalPages, array_filter(['search' => $search, 'filter' => $filter, 'group' => $isCbeDean ? $cbeGroup : null], static fn($value): bool => $value !== null)) ?>
    </section>
</main>
</body>
</html>
