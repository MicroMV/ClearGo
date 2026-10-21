<?php
declare(strict_types=1);
if (!isset($student, $csrfToken, $logoutForm)) { http_response_code(403); exit('Forbidden'); }
?>
<section class="portal-view" id="account" data-dashboard-view="account" aria-labelledby="account-title" tabindex="-1" hidden>
    <div class="portal-section-title"><div><span class="portal-card-eyebrow">ACCOUNT &amp; PREFERENCES</span><h2 id="account-title">My account</h2><p>Your student record and account preferences.</p></div></div>
    <section class="portal-account-identity" aria-labelledby="account-student-name">
        <span class="portal-profile-avatar"><?php if ($profilePictureUrl !== ''): ?><img src="<?= escapeHtml($profilePictureUrl) ?>" alt="Your profile picture"><?php else: ?><?= escapeHtml($studentInitials) ?><?php endif; ?></span>
        <div class="portal-identity-copy">
            <span class="portal-identity-eyebrow">THE LEWIS COLLEGE</span>
            <h3 id="account-student-name"><?= escapeHtml(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '')) . ' ' . $student['last_name']) ?></h3>
            <p><?= escapeHtml($student['student_number']) ?><span aria-hidden="true">&middot;</span><?= escapeHtml($student['organization_code']) ?></p>
        </div>
        <span class="portal-student-label"><?= studentDashboardIcon('user') ?>Student</span>
    </section>
    <div class="portal-account-layout">
        <section class="portal-account-card" aria-labelledby="account-record-title">
            <div class="portal-account-card-heading"><span class="portal-account-section-icon"><?= studentDashboardIcon('user') ?></span><div><h3 id="account-record-title">Student information</h3><p>Your official enrollment details.</p></div></div>
            <dl class="portal-student-details">
                <div><dt>Program</dt><dd><?= escapeHtml($student['program']) ?></dd></div>
                <div><dt>Year level</dt><dd>Year <?= (int) $student['year_level'] ?></dd></div>
                <div class="portal-detail-wide"><dt>College</dt><dd><?= escapeHtml($student['organization_name']) ?></dd></div>
                <div class="portal-detail-wide"><dt>School email</dt><dd><?= escapeHtml($student['email']) ?></dd></div>
            </dl>
            <div class="portal-account-record-note"><?= studentDashboardIcon('lock') ?><p>Need to correct your student details?<br><strong>Contact the Registrar.</strong></p></div>
        </section>
        <div class="portal-account-settings">
            <section class="portal-account-photo" aria-labelledby="account-photo-title">
                <div class="portal-account-card-heading"><span class="portal-account-section-icon"><?= studentDashboardIcon('user') ?></span><div><h3 id="account-photo-title">Profile photo</h3><p>Choose how you appear in ClearGo.</p></div></div>
                <?php $profilePictureShowHeading = false; require __DIR__ . '/../auth/profile_picture_form.php'; unset($profilePictureShowHeading); ?>
            </section>
            <section class="portal-account-security" aria-labelledby="account-security-title">
                <div class="portal-account-card-heading"><span class="portal-account-section-icon"><?= studentDashboardIcon('lock') ?></span><div><h3 id="account-security-title">Security &amp; sign-in</h3><p>Manage access to your account.</p></div></div>
                <a class="portal-settings-row" href="<?= escapeHtml(applicationUrl('/student/password.php')) ?>" data-open-password-modal><span class="portal-account-row-icon"><?= studentDashboardIcon('key') ?></span><span><strong>Change password</strong><small>Keep your account secure</small></span><?= studentDashboardIcon('chevron') ?></a>
                <div class="portal-signout"><div><strong>Sign out</strong><p>Log out on this device.</p></div><?= $logoutForm ?></div>
            </section>
            <?= pwaInstallControl() ?>
        </div>
    </div>
</section>
