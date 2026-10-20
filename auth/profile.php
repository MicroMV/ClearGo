<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/signature_functions.php';
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
}
if (in_array($user['role'], ['ORG_STAFF', 'STUDENT_COUNCIL_STAFF'], true)) {
    $items[] = ['key' => 'attendance', 'label' => 'Event Attendance', 'url' => $user['role'] === 'ORG_STAFF' ? '/organization/attendance.php' : '/student-council/attendance.php', 'icon' => 'document'];
    $items[] = ['key' => 'sanctions', 'label' => 'Sanction Submissions', 'url' => $user['role'] === 'ORG_STAFF' ? '/organization/sanction_submissions.php' : '/student-council/sanction_submissions.php', 'icon' => 'document'];
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
$staffSignature = staffSignature($database, (int) $user['id']);
$savedSignatures = staffSavedSignatures($database, (int) $user['id']);
?>
<!doctype html>
<html lang="en">
<head><?= connectionStatusAssets() ?>
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
                <section class="profile-card signature-card" aria-labelledby="signature-card-title">
                    <div class="signature-card-heading"><div><p class="profile-eyebrow">DIGITAL CLEARANCE</p><h2 id="signature-card-title">Authorized signatures</h2><p>Save signatories with their name and position, then choose the signature to use for future approvals.</p></div><span class="signature-state <?= $staffSignature === false ? 'is-missing' : '' ?>"><?= $staffSignature === false ? 'None selected' : 'Signature selected' ?></span></div>
                    <?php if ($savedSignatures !== []): ?>
                        <div class="saved-signatures" aria-label="Saved signatures">
                            <?php foreach ($savedSignatures as $savedSignature): ?>
                                <?php $isSelectedSignature = $staffSignature !== false && (int) $staffSignature['saved_signature_id'] === (int) $savedSignature['id']; ?>
                                <article class="saved-signature <?= $isSelectedSignature ? 'is-selected' : '' ?>" data-saved-signature="<?= (int) $savedSignature['id'] ?>">
                                    <div class="saved-signature-preview"><img src="<?= escapeHtml(applicationUrl('/auth/signature_image.php?id=' . (int) $savedSignature['id'])) ?>" alt="Signature of <?= escapeHtml($savedSignature['printed_name']) ?>" loading="lazy"></div>
                                    <h3><?= escapeHtml($savedSignature['printed_name']) ?></h3>
                                    <p><?= escapeHtml($savedSignature['position_title']) ?></p>
                                    <?php if ($isSelectedSignature): ?><span class="signature-state" aria-label="Selected for future approvals">Selected</span><?php else: ?>
                                        <form method="post" action="<?= escapeHtml(applicationUrl('/auth/signature_action.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                            <input type="hidden" name="action" value="select">
                                            <input type="hidden" name="signature_id" value="<?= (int) $savedSignature['id'] ?>">
                                            <button type="submit">Use this signature</button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h3 class="signature-add-title">Add a signature</h3>
                    <form class="signature-form" method="post" action="<?= escapeHtml(applicationUrl('/auth/signature_action.php')) ?>" data-signature-form>
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="signature_drawing" value="" data-signature-drawing>
                        <div class="signature-fields">
                            <label>Printed name<input name="printed_name" maxlength="150" required placeholder="Full name of authorized signatory"></label>
                            <label>Position<input name="position_title" maxlength="150" required placeholder="Example: College Dean"></label>
                        </div>
                        <div class="signature-drawing-area">
                            <div class="signature-drawing-heading"><label for="signature-pad">Draw your signature</label><div class="signature-drawing-tools"><button type="button" data-signature-undo disabled>Undo</button><button type="button" data-signature-clear disabled>Clear</button></div></div>
                            <canvas id="signature-pad" class="signature-pad" width="900" height="240" aria-describedby="signature-pad-help signature-pad-status" aria-label="Signature drawing box. Sign here with a mouse, finger or stylus."></canvas>
                            <p id="signature-pad-help">Sign inside the box using your mouse, finger or stylus.</p>
                            <p id="signature-pad-status" class="signature-pad-status" role="status" aria-live="polite" data-signature-status></p>
                            <noscript><p>Enable JavaScript to draw and save your signature.</p></noscript>
                        </div>
                        <div class="signature-form-footer"><span>Saving adds a signature. Your first saved signature is selected automatically.</span><button type="submit" data-signature-save disabled>Save signature</button></div>
                    </form>
                </section>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/profile-picture.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/profile-picture.js') ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/signature-pad.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/signature-pad.js') ?>"></script>
</body>
</html>
