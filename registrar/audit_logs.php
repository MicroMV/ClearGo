<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('REGISTRAR');
$search = trim((string) ($_GET['search'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
try {
    $db = getDatabaseConnection();
    $sql = "SELECT audit.id,audit.created_at,audit.action,audit.description,audit.old_status,audit.new_status,
                actor.email AS actor_email,station.name AS station_name,student.student_number,
                CONCAT(student.first_name,' ',student.last_name) AS student_name
            FROM audit_logs audit
            LEFT JOIN users actor ON actor.id=audit.user_id
            LEFT JOIN stations station ON station.id=audit.station_id
            LEFT JOIN students student ON student.id=audit.student_id
            WHERE (audit.description LIKE :description OR actor.email LIKE :actor
                OR student.student_number LIKE :number OR student.first_name LIKE :first_name
                OR student.last_name LIKE :last_name)";
    $params = [
        'description' => '%' . $search . '%',
        'actor' => '%' . $search . '%',
        'number' => '%' . $search . '%',
        'first_name' => '%' . $search . '%',
        'last_name' => '%' . $search . '%',
    ];
    if ($actionFilter !== '') {
        $sql .= ' AND audit.action=:action';
        $params['action'] = $actionFilter;
    }
    $sql .= ' ORDER BY audit.created_at DESC,audit.id DESC LIMIT 1000';
    $statement = $db->prepare($sql);
    $statement->execute($params);
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
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Audit Logs | ClearGo</title>
    <style>
        body{margin:0;background:#f3f8fd;color:#17324d;font-family:Arial,sans-serif}header{padding:18px 24px;background:#fff;border-bottom:1px solid #d8e3ed}.topbar,main{max-width:1250px;margin:auto}.topbar{display:flex;justify-content:space-between;align-items:center}h1{margin:0;color:#0d5ea6;font-size:24px}main{padding:24px}.panel{background:#fff;border:1px solid #d8e3ed;border-radius:10px;padding:20px}.controls{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:18px}input,select,button{padding:9px;border:1px solid #b7c8d8;border-radius:5px;font:inherit}button{background:#0d5ea6;color:#fff;border:0}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1050px}th,td{padding:9px;border-top:1px solid #d8e3ed;text-align:left;vertical-align:top}th{font-size:13px;color:#587086}.muted{font-size:12px;color:#587086}a{color:#0d5ea6}
    </style>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
<?= $sidebar ?>
<div class="staff-workspace">
<header class="staff-topbar">
    <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
    <div class="topbar-title"><h1>Audit Logs</h1><p>Review recorded account, clearance, and system activity.</p></div>
    <div class="topbar-actions"><div class="topbar-account"><strong><?= escapeHtml($user['email']) ?></strong><span>Registrar account</span></div><?= dashboardNotificationBell($notifications) ?></div>
</header>
<main class="staff-main">
    <section class="panel">
        <div class="section-heading"><div><h2>System activity</h2><p class="muted">Showing up to 1,000 of the most recent matching audit records.</p></div></div>
        <form class="controls" method="get">
            <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search actor, student, or description" aria-label="Search audit logs">
            <select name="action" aria-label="Filter by action"><option value="">All actions</option><?php foreach ($actions as $action): ?><option value="<?= escapeHtml($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= escapeHtml($action) ?></option><?php endforeach; ?></select>
            <button>Filter</button>
        </form>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Student</th><th>Office</th><th>Change</th><th>Description</th></tr></thead>
            <tbody>
            <?php if ($logs === []): ?><tr><td colspan="7">No audit records match.</td></tr><?php endif; ?>
            <?php foreach ($logs as $log): ?><tr>
                <td><?= escapeHtml($log['created_at']) ?></td>
                <td><?= escapeHtml($log['actor_email'] ?? 'System') ?></td>
                <td><strong><?= escapeHtml($log['action']) ?></strong></td>
                <td><?= escapeHtml($log['student_number'] ?? '—') ?><div class="muted"><?= escapeHtml($log['student_name'] ?? '') ?></div></td>
                <td><?= escapeHtml($log['station_name'] ?? '—') ?></td>
                <td><?= escapeHtml(($log['old_status'] ?? '—') . ' → ' . ($log['new_status'] ?? '—')) ?></td>
                <td><?= escapeHtml($log['description']) ?></td>
            </tr><?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</main>
</div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script>
</body>
</html>
