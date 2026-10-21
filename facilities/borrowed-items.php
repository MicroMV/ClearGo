<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$user = requireRole('FACILITIES_STAFF');
$search = trim((string) ($_GET['search'] ?? ''));
$status = strtoupper((string) ($_GET['status'] ?? 'ALL'));
if (!in_array($status, ['ALL', 'OUTSTANDING', 'RETURNED'], true)) {
    $status = 'ALL';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
try {
    $database = getDatabaseConnection();
    $where = "WHERE item.item_type='BORROWED_ITEM'";
    $parameters = [];
    if ($status !== 'ALL') {
        $where .= ' AND item.status=:status';
        $parameters['status'] = $status;
    }
    if ($search !== '') {
        $where .= " AND CONCAT(student.student_number,' ',student.first_name,' ',student.last_name,' ',item.description) LIKE :search";
        $parameters['search'] = '%' . $search . '%';
    }
    $from = " FROM facilities_obligations item INNER JOIN students student ON student.id=item.student_id INNER JOIN academic_periods period ON period.id=item.academic_period_id LEFT JOIN clearances clearance ON clearance.student_id=item.student_id AND clearance.academic_period_id=item.academic_period_id LEFT JOIN clearance_stations office ON office.clearance_id=clearance.id AND office.station_id=(SELECT id FROM stations WHERE code='FACILITIES') ";
    $countQuery = $database->prepare('SELECT COUNT(*)' . $from . $where);
    $countQuery->execute($parameters);
    $totalRows = (int) $countQuery->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $query = $database->prepare('SELECT item.id,item.description,item.status,item.created_at,item.returned_at,student.student_number,student.first_name,student.last_name,period.school_year,period.semester,period.status AS period_status,period.clearance_status,clearance.id AS clearance_id,office.status AS station_status' . $from . $where . " ORDER BY item.created_at DESC,item.id DESC LIMIT {$pageSize} OFFSET {$offset}");
    $query->execute($parameters);
    $items = $query->fetchAll();
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Facilities borrowing history failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Facilities borrowed items.');
}
$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('Facilities Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/facilities/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'borrowed-items', 'label' => 'Borrowed Items', 'url' => '/facilities/borrowed-items.php', 'icon' => 'document'],
], 'borrowed-items', dashboardLogoutForm());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facilities Borrowed Items | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/office-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
    <?= $sidebar ?>
    <div class="staff-workspace">
        <header class="staff-topbar">
            <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
            <div class="topbar-title"><h1>Borrowed Items</h1><p>Facilities borrowing and return history across semesters.</p></div>
            <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
        </header>
        <main class="staff-main">
            <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
            <section class="panel">
                <form class="controls" method="get">
                    <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student or item" aria-label="Search student or item">
                    <select name="status" aria-label="Return status">
                        <?php foreach (['ALL' => 'All items', 'OUTSTANDING' => 'Pending return', 'RETURNED' => 'Returned'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Search</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl('/facilities/borrowed-items.php')) ?>">Clear</a>
                </form>
            </section>
            <section class="panel">
                <div class="table-wrap"><table>
                    <thead><tr><th>Student ID</th><th>Student Name</th><th>Item Borrowed</th><th>Borrowed On</th><th>Semester</th><th>Status</th><th>Returned On</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($items === []): ?><tr><td colspan="8">No borrowed items match this view.</td></tr><?php endif; ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= escapeHtml($item['student_number']) ?></td>
                            <td><?= escapeHtml($item['first_name'] . ' ' . $item['last_name']) ?></td>
                            <td><?= escapeHtml($item['description']) ?></td>
                            <td><?= escapeHtml($item['created_at']) ?></td>
                            <td><?= escapeHtml($item['school_year'] . ' / ' . $item['semester']) ?></td>
                            <td><span class="badge <?= $item['status'] === 'RETURNED' ? 'resolved' : 'pending' ?>"><?= $item['status'] === 'RETURNED' ? 'Returned' : 'Pending return' ?></span></td>
                            <td><?= $item['returned_at'] ? escapeHtml($item['returned_at']) : '—' ?></td>
                            <td>
                                <?php if ($item['status'] === 'RETURNED'): ?><span class="muted">Returned</span>
                                <?php elseif ($item['clearance_id'] && $item['period_status'] === 'ACTIVE' && in_array($item['clearance_status'], ['PREPARING', 'OPEN'], true) && in_array($item['station_status'], ['LOCKED', 'PENDING', 'RETURNED'], true)): ?>
                                <form method="post" action="<?= escapeHtml(applicationUrl('/clearance/office_action.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                    <input type="hidden" name="clearance_id" value="<?= (int) $item['clearance_id'] ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="return_history" value="1">
                                    <button name="action" value="return_facilities_item">Returned</button>
                                </form>
                                <?php else: ?><span class="muted">Unavailable</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p class="muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> item(s)</p>
                <?= renderPagination($page, $totalPages, ['search' => $search, 'status' => $status]) ?>
            </section>
        </main>
    </div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>
</html>
