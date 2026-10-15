<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';

$user = requireLogin();
if ($user['role'] === 'STUDENT') {
    redirectTo('/student/dashboard.php#account');
}
$database = getDatabaseConnection();
$items = [['key' => 'dashboard', 'label' => 'Dashboard', 'url' => ROLE_DASHBOARDS[$user['role']], 'icon' => 'dashboard']];
$officeName = match ($user['role']) {
    'REGISTRAR' => 'Registrar Administration',
    'DEAN' => 'Dean Clearance',
    'ORG_STAFF' => 'Organization Clearance',
    default => officeConfigForRole($user['role'])['title'],
};
if ($user['role'] === 'DEAN') {
    require_once __DIR__ . '/../clearance/clearance_functions.php';
    $dean = deanAssignment($database, (int) $user['id']);
    $officeName = $dean['organization_code'] . ' Dean Clearance';
    if ($dean['organization_code'] === 'CCS') {
        $items[] = ['key' => 'submitted_requirements', 'label' => 'Submitted Requirements', 'url' => '/dean/submitted_requirements.php', 'icon' => 'document'];
    }
}
if (in_array($user['role'], ['ORG_STAFF', 'STUDENT_COUNCIL_STAFF'], true)) {
    $items[] = ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => $user['role'] === 'ORG_STAFF' ? '/organization/attendance.php' : '/student-council/attendance.php', 'icon' => 'document'];
}
foreach (match ($user['role']) {
    'CLINIC_STAFF' => [['schedules', 'Medical Schedules', '/clinic/schedules.php']],
    'FACILITIES_STAFF' => [['borrowed-items', 'Borrowed Items', '/facilities/borrowed-items.php']],
    'COMLAB_STAFF' => [['violations', 'Violation History', '/comlab/violations.php']],
    'BOOKSTORE_STAFF' => [['fees', 'Event Fees', '/bookstore/fees.php'], ['submissions', 'Receipt Submissions', '/bookstore/submissions.php']],
    'ACCOUNTING_STAFF' => [['submissions', 'Submitted Requirements', '/accounting/submissions.php']],
    'LIBRARY_STAFF' => [['submissions', 'Card Submissions', '/library/submissions.php']],
    default => [],
} as [$key, $label, $url]) {
    $items[] = ['key' => $key, 'label' => $label, 'url' => $url, 'icon' => 'document'];
}
$logoutForm = dashboardLogoutForm();
$sidebar = $user['role'] === 'REGISTRAR'
    ? registrarDashboardSidebar($user, 'profile', $logoutForm)
    : staffDashboardSidebar($officeName, $user, $items, 'profile', $logoutForm);
$notifications = dashboardNotifications($database, (int) $user['id']);
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$profilePictureUrl = userProfilePictureUrl($user);
$profilePictureInitials = strtoupper(mb_substr($user['email'], 0, 1));
$profileOfficeLabel = preg_replace('/ Clearance$/', '', $officeName);
$profileRoleLabel = match ($user['role']) {
    'DEAN' => 'College Dean',
    'REGISTRAR' => 'Registrar',
    'ORG_STAFF' => 'Organization Staff',
    default => 'Office Staff',
};
$profilePictureShowHeading = false;
$profilePictureIdentity = ['office' => $profileOfficeLabel, 'role' => $profileRoleLabel, 'email' => $user['email']];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Profile | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/profile-picture.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/profile-picture.css') ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-profile.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/staff-profile.css') ?>">
</head>
<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar">
                <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title"><h1>My Profile</h1><p><?= escapeHtml($officeName) ?></p></div>
                <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
            </header>
            <main class="staff-main staff-profile-page">
                <div class="profile-page-heading"><p class="profile-eyebrow">ACCOUNT SETTINGS</p><h2>Your profile</h2></div>
                <?php if ($message !== null): ?><p class="message" role="status" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <section class="profile-card" aria-labelledby="profile-identity-title">
                    <div class="profile-identity-cover">
                        <div><span>THE LEWIS COLLEGE</span><strong>ClearGo<span>.</span></strong></div>
                        <span class="profile-active-status">Active account</span>
                    </div>
                    <div class="profile-card-body"><?php require __DIR__ . '/profile_picture_form.php'; ?></div>
                </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/profile-picture.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/profile-picture.js') ?>"></script>
</body>
</html>
