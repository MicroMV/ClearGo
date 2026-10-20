<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('REGISTRAR');
$search = trim((string) ($_GET['search'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$pageSize = 30;
try {
    $db = getDatabaseConnection();
    $selectSql = "SELECT audit.id,audit.created_at,audit.action,audit.description,audit.old_status,audit.new_status,
                actor.email AS actor_email,actor.role AS actor_role,
                COALESCE(station.name,profile_office.name) AS station_name,
                COALESCE(student.student_number,profile_student.student_number) AS student_number,
                CONCAT(COALESCE(student.first_name,profile_student.first_name),' ',COALESCE(student.last_name,profile_student.last_name)) AS student_name";
    $fromSql = " FROM audit_logs audit
            LEFT JOIN users actor ON actor.id=audit.user_id
            LEFT JOIN stations station ON station.id=audit.station_id
            LEFT JOIN students student ON student.id=audit.student_id
            LEFT JOIN students profile_student ON audit.action='PROFILE_PICTURE_UPDATED'
                AND actor.role='STUDENT' AND profile_student.user_id=actor.id
            LEFT JOIN stations profile_office ON audit.action='PROFILE_PICTURE_UPDATED' AND profile_office.code=CASE
                WHEN actor.role='STUDENT' THEN NULL WHEN actor.role='ORG_STAFF' THEN 'ORGANIZATION'
                ELSE REPLACE(actor.role,'_STAFF','') END
            WHERE (audit.description LIKE :description OR actor.email LIKE :actor
                OR COALESCE(student.student_number,profile_student.student_number) LIKE :number
                OR COALESCE(student.first_name,profile_student.first_name) LIKE :first_name
                OR COALESCE(student.last_name,profile_student.last_name) LIKE :last_name)";
    $params = [
        'description' => '%' . $search . '%',
        'actor' => '%' . $search . '%',
        'number' => '%' . $search . '%',
        'first_name' => '%' . $search . '%',
        'last_name' => '%' . $search . '%',
    ];
    if ($actionFilter !== '') {
        $fromSql .= ' AND audit.action=:action';
        $params['action'] = $actionFilter;
    }
    $countStatement = $db->prepare('SELECT COUNT(*)' . $fromSql);
    $countStatement->execute($params);
    $totalRows = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $statement = $db->prepare($selectSql . $fromSql . ' ORDER BY audit.created_at DESC,audit.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $logs = $statement->fetchAll();
    $actions = $db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    $notifications = dashboardNotificationPreview($db, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Audit log page failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load audit logs.');
}
$logoutForm = dashboardLogoutForm();
$sidebar = registrarDashboardSidebar($user, 'audit', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head><?= connectionStatusAssets() ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Audit Logs | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/registrar-audit-logs.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar">
                <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Audit Logs</h1>
                    <p>Review recorded account, clearance, and system activity.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>System activity</h2>
                            <p class="muted">Showing <?= $totalRows === 0 ? 0 : $offset + 1 ?>&ndash;<?= min($offset + $pageSize, $totalRows) ?> of <?= number_format($totalRows) ?> matching records. Page <?= $page ?> of <?= $totalPages ?>.</p>
                        </div>
                    </div>
                    <form class="controls" method="get">
                        <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search actor, student, or description" aria-label="Search audit logs">
                        <select name="action" aria-label="Filter by action">
                            <option value="">All actions</option><?php foreach ($actions as $action): ?><option value="<?= escapeHtml($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= escapeHtml($action) ?></option><?php endforeach; ?>
                        </select>
                        <button>Filter</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl('/registrar/audit_logs.php')) ?>">Clear</a>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Student</th>
                                    <th>Office</th>
                                    <th>Change</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($logs === []): ?><tr>
                                        <td colspan="7">No audit records match.</td>
                                    </tr><?php endif; ?>
                                <?php foreach ($logs as $log):
                                    $isProfileUpdate = $log['action'] === 'PROFILE_PICTURE_UPDATED';
                                    $studentPlaceholder = $isProfileUpdate && isset($log['actor_role']) && $log['actor_role'] !== 'STUDENT' ? 'Not applicable' : '—';
                                    $officePlaceholder = $isProfileUpdate && ($log['actor_role'] ?? '') === 'STUDENT' ? 'Not applicable' : '—';
                                    $oldStatus = $log['old_status'] ?? ($isProfileUpdate ? 'Not recorded' : '—');
                                    $newStatus = $log['new_status'] ?? '—';
                                    $change = $isProfileUpdate ? str_replace('_', ' ', $oldStatus . ' → ' . $newStatus) : $oldStatus . ' → ' . $newStatus;
                                ?><tr>
                                        <td><?= escapeHtml($log['created_at']) ?></td>
                                        <td><?= escapeHtml($log['actor_email'] ?? 'System') ?></td>
                                        <td><strong><?= escapeHtml($log['action']) ?></strong></td>
                                        <td><?= escapeHtml($log['student_number'] ?? $studentPlaceholder) ?><div class="muted"><?= escapeHtml($log['student_name'] ?? '') ?></div>
                                        </td>
                                        <td><?= escapeHtml($log['station_name'] ?? $officePlaceholder) ?></td>
                                        <td><?= escapeHtml($change) ?></td>
                                        <td><?= escapeHtml($log['description']) ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= renderPagination($page, $totalPages, ['search' => $search, 'action' => $actionFilter], '', 'Audit log pages') ?>
                </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>

</html>
