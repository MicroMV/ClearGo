<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$user = requireRole('COMLAB_STAFF');
$search = trim((string) ($_GET['search'] ?? ''));
$status = strtoupper((string) ($_GET['status'] ?? 'ALL'));
if (!in_array($status, ['ALL', 'OPEN', 'RESOLVED'], true)) {
    $status = 'ALL';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
try {
    $database = getDatabaseConnection();
    $where = "WHERE station.code='COMLAB'";
    $parameters = [];
    if ($status !== 'ALL') {
        $where .= ' AND item.status=:status';
        $parameters['status'] = $status;
    }
    if ($search !== '') {
        $where .= " AND CONCAT(student.student_number,' ',student.first_name,' ',student.last_name,' ',item.description) LIKE :search";
        $parameters['search'] = '%' . $search . '%';
    }
    $from = " FROM violations item INNER JOIN students student ON student.id=item.student_id INNER JOIN stations station ON station.id=item.station_id LEFT JOIN academic_periods period ON period.status='ACTIVE' LEFT JOIN clearances clearance ON clearance.student_id=item.student_id AND clearance.academic_period_id=period.id LEFT JOIN clearance_stations office ON office.clearance_id=clearance.id AND office.station_id=station.id ";
    $countQuery = $database->prepare('SELECT COUNT(*)' . $from . $where);
    $countQuery->execute($parameters);
    $totalRows = (int) $countQuery->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $query = $database->prepare('SELECT item.id,item.description,item.status,item.created_at,item.resolved_at,student.student_number,student.first_name,student.last_name,period.clearance_status,clearance.id AS clearance_id,office.status AS station_status' . $from . $where . " ORDER BY item.created_at DESC,item.id DESC LIMIT {$pageSize} OFFSET {$offset}");
    $query->execute($parameters);
    $items = $query->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Comlab violation history failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Comlab violations.');
}
$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('Computer Laboratory Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/comlab/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'violations', 'label' => 'Violation History', 'url' => '/comlab/violations.php', 'icon' => 'document'],
], 'violations', dashboardLogoutForm());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comlab Violation History | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/office-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
    <?= $sidebar ?>
    <div class="staff-workspace">
        <header class="staff-topbar">
            <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
            <div class="topbar-title"><h1>Violation History</h1><p>Comlab violations and resolution history.</p></div>
            <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
        </header>
        <main class="staff-main">
            <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
            <section class="panel">
                <form class="controls" method="get">
                    <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student or violation" aria-label="Search student or violation">
                    <select name="status" aria-label="Resolution status">
                        <?php foreach (['ALL' => 'All violations', 'OPEN' => 'Unresolved', 'RESOLVED' => 'Resolved'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Search</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl('/comlab/violations.php')) ?>">Clear</a>
                </form>
            </section>
            <section class="panel">
                <div class="table-wrap"><table>
                    <thead><tr><th>Student ID</th><th>Student Name</th><th>Violation</th><th>Reported On</th><th>Status</th><th>Resolved On</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($items === []): ?><tr><td colspan="7">No violations match this view.</td></tr><?php endif; ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= escapeHtml($item['student_number']) ?></td>
                            <td><?= escapeHtml($item['first_name'] . ' ' . $item['last_name']) ?></td>
                            <td><?= escapeHtml($item['description']) ?></td>
                            <td><?= escapeHtml($item['created_at']) ?></td>
                            <td><span class="badge <?= $item['status'] === 'RESOLVED' ? 'resolved' : 'pending' ?>"><?= $item['status'] === 'RESOLVED' ? 'Resolved' : 'Unresolved' ?></span></td>
                            <td><?= $item['resolved_at'] ? escapeHtml($item['resolved_at']) : '—' ?></td>
                            <td>
                                <?php if ($item['status'] === 'RESOLVED'): ?><span class="muted">Resolved</span>
                                <?php elseif ($item['clearance_id'] && in_array($item['clearance_status'], ['PREPARING', 'OPEN'], true) && in_array($item['station_status'], ['LOCKED', 'PENDING', 'RETURNED'], true)): ?>
                                <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/office_action.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                    <input type="hidden" name="clearance_id" value="<?= (int) $item['clearance_id'] ?>">
                                    <input type="hidden" name="incident_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="return_history" value="1">
                                    <button name="action" value="resolve_comlab_incident">Resolve</button>
                                </form>
                                <?php else: ?><span class="muted">Unavailable</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> violation(s)</p>
                <?= renderPagination($page, $totalPages, ['search' => $search, 'status' => $status]) ?>
            </section>
        </main>
    </div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>
</html>
