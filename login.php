<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';

if (authenticatedUser() !== null) {
    redirectToDashboard($_SESSION['authenticated_user']['role']);
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$showRecovery = ($_GET['recovery'] ?? '') === '1' || isset($recoveryRateLimitMessage);
$flow = $_SESSION['student_password_recovery'] ?? null;
if (!is_array($flow) || (int) ($flow['created_at'] ?? 0) < time() - 1200) {
    unset($_SESSION['student_password_recovery']);
    $flow = null;
}
$stage = is_string($flow['grant'] ?? null) ? 'reset' : (is_string($flow['request_id'] ?? null) ? 'verify' : 'email');
$recoveryMessage = $showRecovery ? ($recoveryRateLimitMessage ?? $message) : null;
$action = escapeHtml(applicationUrl('/auth/password_recovery_action.php'));
header('Cache-Control: no-store, max-age=0');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearGo | The Lewis College</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/login.css?v=' . filemtime(__DIR__ . '/assets/css/login.css'))) ?>">
    <script defer src="<?= escapeHtml(applicationUrl('/assets/js/login.js?v=' . filemtime(__DIR__ . '/assets/js/login.js'))) ?>"></script>

</head>

<body>
    <main class="shell">
        <section class="welcome" aria-labelledby="welcome-title">
            <div class="brand"><img class="brand-logo" src="<?= escapeHtml(clearGoLogoUrl()) ?>" alt="The Lewis College logo">
                <div class="brand-copy"><small>The Lewis College</small><strong>ClearGo</strong></div>
            </div>
            <div class="welcome-content">
                <p class="eyebrow">End-of-semester clearance</p>
                <h1 id="welcome-title">Clearance, made clear.</h1>
                <p class="lead">A focused, secure workspace for students and offices to manage end-of-semester clearance with confidence.</p>
                <div class="benefits">
                    <div class="benefit"><strong>One clear process</strong>Follow your clearance progress in one place.</div>
                    <div class="benefit"><strong>Office-ready</strong>Review student records with the right context.</div>
                    <div class="benefit"><strong>Built for the college</strong>Designed for The Lewis College community.</div>
                </div>
            </div>
            <div class="welcome-footer">Automated End-of-Semester Clearance System</div>
        </section>
        <section class="signin-area" aria-labelledby="signin-title">
            <div class="signin-card">
                <h2 id="signin-title">Welcome back</h2>
                <p class="intro">Sign in with your official Lewis College account.</p>
                <form method="post" action="<?= escapeHtml(applicationUrl('/auth/login_process.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <label for="email">The Lewis College GSuite Address</label>
                    <input id="email" name="email" type="email" autocomplete="username" placeholder="name@thelewiscollege.edu.ph" pattern=".+@thelewiscollege\.edu\.ph" required>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <div class="signin-options">
                        <label class="remember-option" for="remember-me"><input id="remember-me" name="remember_me" type="checkbox" value="1"> <span>Keep me signed in</span></label>
                        <button class="forgot-password-trigger" type="button" data-open-recovery aria-haspopup="dialog" aria-controls="password-recovery-modal">Forgot password?</button>
                    </div>
                    <button type="submit">Sign in to ClearGo</button>
                </form>
                <p class="form-note">Use your official @thelewiscollege.edu.ph account.</p>
                <?= pwaInstallControl() ?>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/auth/password_recovery_view.php'; ?>
    <?php if ($message !== null && !$showRecovery): ?>
        <aside class="login-toast" role="alert">
            <div><strong>ClearGo</strong><p><?= escapeHtml($message) ?></p></div>
            <button type="button" aria-label="Dismiss notification">&times;</button>
        </aside>
    <?php endif; ?>
</body>

</html>
