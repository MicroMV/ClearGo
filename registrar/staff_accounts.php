<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('REGISTRAR');
try {
    $database = getDatabaseConnection();
    $staff = $database->query("SELECT user.id,user.email,user.role,user.status,user.last_login_at,
            GROUP_CONCAT(DISTINCT organization.code ORDER BY organization.code SEPARATOR ', ') AS colleges
        FROM users user
        LEFT JOIN staff_assignments assignment ON assignment.user_id=user.id AND assignment.status='ACTIVE'
        LEFT JOIN organizations organization ON organization.id=assignment.organization_id
        WHERE user.role<>'STUDENT'
        GROUP BY user.id,user.email,user.role,user.status,user.last_login_at
        ORDER BY user.role,user.email")->fetchAll();
    $organizations = $database->query("SELECT id,code,name FROM organizations WHERE status='ACTIVE' ORDER BY code")->fetchAll();
    $notifications = dashboardNotificationPreview($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Staff account page failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load staff accounts.');
}
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$sidebar = registrarDashboardSidebar($user, 'staff', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head><?= connectionStatusAssets() ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Staff Accounts | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/registrar-staff-accounts.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar">
                <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Staff Accounts</h1>
                    <p>Manage authorized office accounts and credentials.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <?php if ($message): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Create staff account</h2>
                            <p class="muted">Add an authorized account for an existing ClearGo office role.</p>
                        </div>
                    </div>
                    <p>Initial password: <strong>thelewiscollege</strong>.</p>
                    <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/registrar/staff_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" placeholder="Gsuite Address" required>
                        <select name="role" aria-label="Staff role"><?php foreach (['ORG_STAFF', 'DEAN', 'SASO_STAFF', 'STUDENT_COUNCIL_STAFF', 'LIBRARY_STAFF', 'BOOKSTORE_STAFF', 'CLINIC_STAFF', 'FACILITIES_STAFF', 'COMLAB_STAFF', 'ACCOUNTING_STAFF', 'REGISTRAR'] as $role): ?><option value="<?= $role ?>"><?= escapeHtml(str_replace('_', ' ', $role)) ?></option><?php endforeach; ?></select>
                        <select name="organization_id" aria-label="Assigned college">
                            <option value="">College (Dean/Organization Staff)</option><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>"><?= escapeHtml($org['code'] . ' - ' . $org['name']) ?></option><?php endforeach; ?>
                        </select>
                        <button name="action" value="create">Create Staff Account</button>
                    </form>
                </section>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Staff directory</h2>
                            <p class="muted">Only the Registrar can change office emails and passwords.</p>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>College</th>
                                    <th>Gsuite Address</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Registrar Controls</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($staff as $account): ?><tr>
                                        <td><strong><?= escapeHtml(str_replace('_', ' ', $account['role'])) ?></strong></td>
                                        <td><?= escapeHtml($account['colleges'] ?? '—') ?></td>
                                        <td><?= escapeHtml($account['email']) ?></td>
                                        <td><span class="badge <?= strtolower($account['status']) ?>"><?= escapeHtml($account['status']) ?></span></td>
                                        <td><?= escapeHtml($account['last_login_at'] ?? 'Never') ?></td>
                                        <td>
                                            <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/registrar/staff_action.php')) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                                <input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" value="<?= escapeHtml($account['email']) ?>" required aria-label="Staff email">
                                                <button name="action" value="update_email">Save Email</button>
                                                <input type="password" name="new_password" minlength="8" placeholder="New password" aria-label="New password">
                                                <button name="action" value="change_password">Change Password</button>
                                                <button class="secondary" name="action" value="reset_default">Reset to Default</button>
                                            </form>
                                        </td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>

</html>
