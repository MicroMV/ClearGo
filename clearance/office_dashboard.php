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
    } elseif ($code === 'LIBRARY') {
        $statement = $database->prepare('SELECT id,book_title,borrowed_at,due_at,returned_at,status FROM borrowed_books WHERE student_id=:student_id ORDER BY status,borrowed_at DESC,id DESC');
        $statement->execute(['student_id' => $studentId]);
        $records = $statement->fetchAll();
    } elseif ($code === 'BOOKSTORE') {
        $statement = $database->prepare("SELECT fee.id AS fee_id,fee.name,fee.amount,student_fee.status,student_fee.payment_reference,student_fee.remarks FROM bookstore_fees fee INNER JOIN student_bookstore_fees student_fee ON student_fee.fee_id=fee.id AND student_fee.student_id=:student_id WHERE fee.academic_period_id=:period_id AND fee.status='ACTIVE' AND fee.is_required=1 ORDER BY fee.id");
        $statement->execute(['student_id' => $studentId, 'period_id' => $periodId]);
        $records = $statement->fetchAll();
    } elseif (in_array($code, ['SASO', 'FACILITIES'], true)) {
        $statement = $database->prepare('SELECT violation.id,violation.description,violation.status,violation.created_at,violation.resolved_at FROM violations violation INNER JOIN stations station ON station.id=violation.station_id AND station.code=:code WHERE violation.student_id=:student_id ORDER BY violation.status,violation.created_at DESC');
        $statement->execute(['code' => $code, 'student_id' => $studentId]);
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
    $allowedFilters = ['ALL', 'READY', 'PENDING', 'RETURNED', 'APPROVED', 'LOCKED'];
    if (!in_array($filter, $allowedFilters, true)) { $filter = 'ALL'; }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 30;
    try {
        $database = getDatabaseConnection();
        $readiness = $stationCode === 'REGISTRAR' ? null : officeReadiness($database, $stationCode);
        $totalRows = officeRowCount($database, $stationCode, $search);
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $rows = officeRows($database, $stationCode, $search, $pageSize, ($page - 1) * $pageSize);
        foreach ($rows as &$row) {
            if (($row['clearance_status'] ?? null) === 'PREPARING'
                && !in_array($row['station_status'], ['APPROVED', 'RETURNED'], true)) {
                $row['view_status'] = $row['eligibility']['eligible'] ? 'READY' : 'PENDING';
            } else {
                $row['view_status'] = $row['station_status'] === 'PENDING' && $row['eligibility']['eligible']
                    ? 'READY'
                    : $row['station_status'];
            }
        }
        unset($row);
        $summary = ['TOTAL' => $totalRows, 'READY' => 0, 'PENDING' => 0, 'RETURNED' => 0, 'APPROVED' => 0];
        foreach ($rows as $row) {
            if (isset($summary[$row['view_status']])) { $summary[$row['view_status']]++; }
        }
        $selected = null;
        $selectedId = filter_var($_GET['clearance'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($selectedId !== false) {
            foreach ($rows as $row) {
                if ((int) $row['clearance_id'] === (int) $selectedId) { $selected = $row; break; }
            }
        }
        if ($selected !== null) {
            ensureOfficeSourceRecords($database, (int) $selected['student_id'], (int) $selected['academic_period_id']);
            $selected['eligibility'] = officeEligibility($database, $stationCode, $selected);
            $detailRecords = officeDetailRecords($database, $stationCode, $selected);
        } else {
            $detailRecords = [];
        }
        $notificationQuery = $database->prepare('SELECT id,title,message,is_read,created_at FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC,id DESC LIMIT 8');
        $notificationQuery->execute(['user_id' => $user['id']]);
        $notifications = $notificationQuery->fetchAll();
        $markNotificationsRead = $database->prepare('UPDATE notifications SET is_read=1 WHERE user_id=:user_id AND is_read=0');
        $markNotificationsRead->execute(['user_id' => $user['id']]);
    } catch (Throwable $exception) {
        error_log($title . ' dashboard failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Unable to load this office dashboard right now. Please try again later.');
    }
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . strtolower(str_replace('_', '-', $stationCode)) . '-clearance-report.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) { throw new RuntimeException('Unable to create the report.'); }
        fputcsv($output, ['Student Number', 'Student Name', 'Program', 'Year', 'College', 'Office Status', 'Eligible', 'Eligibility Details']);
        foreach ($rows as $row) {
            if ($filter !== 'ALL' && $row['view_status'] !== $filter) { continue; }
            fputcsv($output, [
                $row['student_number'], $row['first_name'] . ' ' . $row['last_name'], $row['program'],
                $row['year_level'], $row['organization_code'], $row['view_status'],
                $row['eligibility']['eligible'] ? 'YES' : 'NO', implode('; ', $row['eligibility']['details']),
            ]);
        }
        fclose($output);
        exit;
    }
    $message = pullFlashMessage();
    $csrfToken = createCsrfToken();
    $logoutForm = dashboardLogoutForm();
    $actionUrl = applicationUrl('/clearance/office_action.php');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= escapeHtml($title) ?> | ClearGo</title>
    <style>
        :root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd;--green:#176b3a;--red:#a33722}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}header{background:#fff;border-bottom:1px solid var(--line);padding:18px 24px}.topbar,main{max-width:1180px;margin:auto}.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px}.logout-form{margin:0}h1{margin:0;color:var(--blue);font-size:24px}h2{margin-top:0;font-size:20px}h3{margin-bottom:8px}.muted{color:#587086;font-size:13px}main{padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}.summary{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.summary div{background:#f4f8fc;border-radius:7px;padding:12px}.summary strong{display:block;color:var(--blue);font-size:24px}.controls,.inline{display:flex;gap:9px;flex-wrap:wrap;align-items:end}input,select,textarea,button{border:1px solid #b7c8d8;border-radius:5px;font:inherit;padding:9px}input[type=text],input[type=number],textarea{min-width:220px}textarea{width:100%;min-height:70px}button{border:0;background:var(--blue);color:#fff;cursor:pointer}.danger{background:#a33722}.secondary{background:#587086}.message{padding:12px;background:#edf6ff;border-left:4px solid var(--blue)}table{border-collapse:collapse;width:100%}th,td{border-top:1px solid var(--line);padding:11px;text-align:left;vertical-align:top}th{font-size:13px;color:#587086}.badge{display:inline-block;border-radius:99px;padding:6px 9px;font-size:12px;font-weight:bold}.approved,.ready,.verified,.cleared,.paid,.resolved{background:#e4f5eb;color:var(--green)}.pending,.under-review{background:#fff7e8;color:#956200}.returned,.invalid,.unpaid,.open{background:#fff0ed;color:var(--red)}.locked,.missing{background:#eef1f4;color:#536473}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.requirement-list{margin:0;padding-left:20px}.requirement-list li{margin:8px 0}.record{border-top:1px solid var(--line);padding:14px 0}.record:first-child{border-top:0}.record form{margin-top:9px}.actions{display:flex;gap:12px;flex-wrap:wrap}.actions form{flex:1;min-width:280px}.table-wrap{overflow-x:auto}a{color:var(--blue);font-weight:bold;text-decoration:none}@media(max-width:780px){header,main{padding:16px}.summary{grid-template-columns:repeat(2,1fr)}.detail-grid{grid-template-columns:1fr}input[type=text],input[type=number]{min-width:0;width:100%}}
        .pagination{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:14px}.pagination-link,.pagination-ellipsis{display:grid;place-items:center;min-width:42px;height:42px;padding:0 12px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--dark);font-weight:600;text-decoration:none}.pagination-link.active{border-color:var(--blue);background:var(--blue);color:#fff}.pagination-ellipsis{border:0;background:transparent}
    </style>
</head>
<body>
<header><div class="topbar"><div><h1><?= escapeHtml($title) ?></h1><div class="muted">Signed in as <?= escapeHtml($user['email']) ?></div></div><?= $logoutForm ?></div></header>
<main>
    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
    <?php if ($readiness !== null && $readiness['clearance_status'] === 'PREPARING'): ?><section class="panel"><h2>Semester preparation</h2><p><strong><?= escapeHtml($readiness['semester'] . ' Semester A.Y. ' . $readiness['school_year']) ?></strong></p><p>Review records and take action now. Approve complete students or return incomplete records, then mark the office Ready.</p><?php if ($readiness['readiness_status'] === 'READY'): ?><p class="message">Your office is READY.</p><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button type="submit" name="action" value="unready">Undo Ready</button></form><?php else: ?><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/readiness_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="text" name="notes" maxlength="500" placeholder="Optional readiness note"><button type="submit" name="action" value="ready">Mark Office Ready</button></form><?php endif; ?></section><?php endif; ?>
    <section class="panel"><div class="summary"><div>Total<strong><?= $summary['TOTAL'] ?></strong></div><div>Ready<strong><?= $summary['READY'] ?></strong></div><div>Pending<strong><?= $summary['PENDING'] ?></strong></div><div>Returned<strong><?= $summary['RETURNED'] ?></strong></div><div>Approved<strong><?= $summary['APPROVED'] ?></strong></div></div></section>
    <?php if ($notifications !== []): ?><section class="panel"><h2>Recent alerts</h2><?php foreach ($notifications as $notification): ?><div class="record"><strong><?= escapeHtml($notification['title']) ?></strong><?php if (!(int) $notification['is_read']): ?> <span class="badge pending">NEW</span><?php endif; ?><div><?= escapeHtml($notification['message']) ?></div><div class="muted"><?= escapeHtml($notification['created_at']) ?></div></div><?php endforeach; ?></section><?php endif; ?>
    <section class="panel"><form class="controls" method="get"><input type="text" name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, or program"><select name="filter"><?php foreach ($allowedFilters as $option): ?><option value="<?= $option ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escapeHtml(ucfirst(strtolower($option))) ?></option><?php endforeach; ?></select><button type="submit">Search</button><a href="?search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;export=csv">Export CSV report</a></form></section>
    <section class="panel"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Program / Year</th><th>Eligibility</th><th>Status</th><th></th></tr></thead><tbody>
    <?php $visible = 0; foreach ($rows as $row): if ($filter !== 'ALL' && $row['view_status'] !== $filter) { continue; } $visible++; ?>
        <tr><td><strong><?= escapeHtml($row['student_number']) ?></strong><br><?= escapeHtml($row['first_name'] . ' ' . $row['last_name']) ?><div class="muted"><?= escapeHtml($row['organization_code']) ?></div></td><td><?= escapeHtml($row['program']) ?><br><span class="muted">Year <?= (int) $row['year_level'] ?> · <?= escapeHtml($row['section']) ?></span></td><td><?= $row['eligibility']['eligible'] ? 'All checks satisfied' : escapeHtml($row['eligibility']['details'][0] ?? 'Needs review') ?></td><td><span class="badge <?= strtolower(str_replace('_','-',$row['view_status'])) ?>"><?= escapeHtml(str_replace('_',' ',$row['view_status'])) ?></span></td><td><a href="?clearance=<?= (int) $row['clearance_id'] ?>&amp;search=<?= urlencode($search) ?>&amp;filter=<?= urlencode($filter) ?>&amp;page=<?= $page ?>"><?= $row['clearance_status'] === 'PREPARING' ? 'Take Action' : 'Review' ?></a></td></tr>
    <?php endforeach; if ($visible === 0): ?><tr><td colspan="5">No students match this view.</td></tr><?php endif; ?>
    </tbody></table></div><p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s)</p><?= renderPagination($page, $totalPages, ['search' => $search, 'filter' => $filter]) ?></section>

    <?php if ($selected !== null): ?>
    <section class="panel"><div class="detail-grid"><div><h2><?= escapeHtml($selected['first_name'] . ' ' . $selected['last_name']) ?></h2><p><strong><?= escapeHtml($selected['student_number']) ?></strong><br><?= escapeHtml($selected['program']) ?> · Year <?= (int) $selected['year_level'] ?><br><?= escapeHtml($selected['organization_name']) ?></p><?php $selectedDisplayStatus = $selected['clearance_status'] === 'PREPARING' && $selected['station_status'] === 'LOCKED' ? ($selected['eligibility']['eligible'] ? 'READY' : 'PENDING') : $selected['station_status']; ?><p>Status: <span class="badge <?= strtolower($selectedDisplayStatus) ?>"><?= escapeHtml(str_replace('_',' ',$selectedDisplayStatus)) ?></span></p><?php if ($selected['returned_reason']): ?><p class="message"><?= escapeHtml($selected['returned_reason']) ?></p><?php endif; ?></div><div><h2>Eligibility checks</h2><ul class="requirement-list"><?php foreach ($selected['eligibility']['details'] as $detail): ?><li><?= escapeHtml($detail) ?></li><?php endforeach; ?></ul></div></div></section>

    <section class="panel"><h2>Office records</h2>
        <?php if ($stationCode === 'CLINIC'): ?>
            <p>Review the student's official medical record, then record the result and any missing requirement.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="remarks" placeholder="Remarks or missing medical requirement"></textarea><div class="inline"><button name="action" value="medical_status">Save as</button><select name="status"><option value="VERIFIED">Verified</option><option value="RETURNED">Returned</option></select></div></form>
        <?php elseif ($stationCode === 'STUDENT_COUNCIL'): ?>
            <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml(str_replace('_',' ',$record['status'])) ?></span><div class="muted"><?= escapeHtml($record['description'] ?? '') ?></div><?php if (!in_array($record['status'], ['VERIFIED','RESOLVED'], true)): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="requirement_record_id" value="<?= (int) $record['id'] ?>"><input type="text" name="remarks" placeholder="Verification note"><button name="action" value="verify_requirement">Verify</button></form><?php endif; ?></div><?php endforeach; ?>
        <?php elseif ($stationCode === 'LIBRARY'): ?>
            <form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><select name="status"><option value="VERIFIED">Card verified</option><option value="INVALID">Card invalid/missing</option></select><button name="action" value="library_card">Update library card</button></form><h3>Borrowed books</h3><?php if ($detailRecords === []): ?><p class="muted">No borrowed books recorded.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['book_title']) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><div class="muted">Borrowed <?= escapeHtml($record['borrowed_at']) ?><?= $record['due_at'] ? ' · Due ' . escapeHtml($record['due_at']) : '' ?></div><?php if ($record['status'] === 'BORROWED'): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="book_id" value="<?= (int) $record['id'] ?>"><button name="action" value="return_book">Confirm return</button></form><?php endif; ?></div><?php endforeach; ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="book_title" required placeholder="Book title"><label>Due <input type="date" name="due_at"></label><button name="action" value="add_book">Record borrowed book</button></form>
        <?php elseif ($stationCode === 'BOOKSTORE'): ?>
            <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?> — PHP <?= number_format((float) $record['amount'],2) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="fee_id" value="<?= (int) $record['fee_id'] ?>"><select name="status"><option value="PAID">Paid</option><option value="UNPAID">Unpaid</option></select><input type="text" name="payment_reference" value="<?= escapeHtml($record['payment_reference'] ?? '') ?>" placeholder="Receipt/payment reference"><input type="text" name="remarks" value="<?= escapeHtml($record['remarks'] ?? '') ?>" placeholder="Remarks"><button name="action" value="bookstore_payment">Save</button></form></div><?php endforeach; ?>
        <?php elseif (in_array($stationCode, ['SASO','FACILITIES'], true)): ?>
            <?php if ($detailRecords === []): ?><p class="muted">No cases or incidents recorded.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><div class="muted"><?= escapeHtml($record['created_at']) ?></div><?php if ($record['status'] === 'OPEN'): ?><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="violation_id" value="<?= (int) $record['id'] ?>"><button name="action" value="resolve_violation">Mark settled</button></form><?php endif; ?></div><?php endforeach; ?><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="description" required placeholder="Incident, missing item, rule violation, or unresolved case"></textarea><button name="action" value="add_violation">Record open case</button></form>
        <?php elseif ($stationCode === 'ACCOUNTING'): ?>
            <?php if ($detailRecords === []): ?><p class="muted">No financial obligations recorded.</p><?php endif; ?><?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['description']) ?> — PHP <?= number_format((float)$record['amount'],2) ?></strong> <span class="badge <?= strtolower($record['status']) ?>"><?= escapeHtml($record['status']) ?></span><?php if ($record['payment_reference']): ?><div class="muted">Reference: <?= escapeHtml($record['payment_reference']) ?></div><?php endif; ?><?php if ($record['status'] === 'UNPAID'): ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="obligation_id" value="<?= (int) $record['id'] ?>"><input type="text" name="payment_reference" required placeholder="Payment reference"><input type="text" name="remarks" placeholder="Remarks"><button name="action" value="clear_obligation">Verify settled</button></form><?php endif; ?></div><?php endforeach; ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="text" name="description" required placeholder="Financial obligation"><input type="number" name="amount" min="0" step="0.01" required placeholder="Amount"><input type="text" name="remarks" placeholder="Remarks"><button name="action" value="add_obligation">Add obligation</button></form>
        <?php elseif ($stationCode === 'REGISTRAR'): ?>
            <?php foreach ($detailRecords as $record): ?><div class="record"><strong><?= escapeHtml($record['name']) ?></strong> <span class="badge <?= strtolower(str_replace('_','-',$record['status'])) ?>"><?= escapeHtml(str_replace('_',' ',$record['status'])) ?></span><?php if ($record['return_reason']): ?><div><?= escapeHtml($record['return_reason']) ?></div><?php endif; ?><form class="inline" method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><input type="hidden" name="document_id" value="<?= (int) $record['id'] ?>"><select name="status"><option value="VERIFIED">Verified</option><option value="RETURNED">Returned</option></select><input type="text" name="reason" placeholder="Return reason / verification note"><button name="action" value="document_status">Save</button></form></div><?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if (in_array($stationCode, ['ACCOUNTING','REGISTRAR'], true)): ?><section class="panel"><h2>Temporary exception</h2><p class="muted">Use only for an approved promissory note, pending grade/document, or formally authorized special case. This is recorded in the audit trail.</p><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="reason" required placeholder="Approved exception and conditions"></textarea><div class="inline"><input type="text" name="proof_reference" required placeholder="Promissory note / authorization reference"><label>Expires <input type="date" name="expires_at"></label><button name="action" value="approve_exception">Approve exception</button></div></form></section><?php endif; ?>

    <section class="panel"><h2>Clearance decision</h2><?php if (!in_array($selected['clearance_status'], ['PREPARING','OPEN'], true)): ?><p class="message">Clearance processing is closed for this semester.</p><?php elseif ($selected['clearance_status'] === 'OPEN' && $selected['station_status'] === 'LOCKED'): ?><p class="message">This station is locked until the required preceding offices approve the student.</p><?php elseif ($selected['station_status'] === 'APPROVED'): ?><p class="message">This office has approved the student.</p><?php else: ?><?php if ($selected['clearance_status'] === 'PREPARING'): ?><p class="message">Preparation mode: your decision will be ready when students gain access.</p><?php endif; ?><div class="actions"><form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Approve this office clearance?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><button name="action" value="approve" <?= !$selected['eligibility']['eligible'] ? 'disabled title="Complete all requirements first"' : '' ?>>Approve clearance</button></form><form method="post" action="<?= escapeHtml($actionUrl) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="clearance_id" value="<?= (int) $selected['clearance_id'] ?>"><textarea name="reason" required placeholder="Explain what the student must complete"></textarea><button class="danger" name="action" value="return">Return to student</button></form></div><?php endif; ?></section>
    <?php endif; ?>
</main></body></html>
<?php
    exit;
}
