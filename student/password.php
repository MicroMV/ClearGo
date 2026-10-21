<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
$user = requireRole('STUDENT');
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$isEmbedded = ($_GET['embed'] ?? '') === '1';
$passwordStylesVersion = (string) (filemtime(__DIR__ . '/../assets/css/student-password.css') ?: 1);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-password.css')) ?>?v=<?= escapeHtml($passwordStylesVersion) ?>">
</head>

<body class="<?= $isEmbedded ? 'password-embedded' : 'password-page' ?>">
    <?php if (!$isEmbedded): ?><header>
        <div class="topbar">
            <div>
                <h1>Change Password</h1><a href="<?= escapeHtml(applicationUrl('/student/dashboard.php#account')) ?>">Back to Dashboard</a>
            </div><?= $logoutForm ?>
        </div>
    </header><?php endif; ?>
    <main><section class="panel">
            <span class="password-kicker">Account security</span><h1>Change password</h1>
            <p class="password-intro">Use at least 8 characters and choose a password you do not use elsewhere.</p>
            <?php if ($message): ?><p class="message" role="status"><?= escapeHtml($message) ?></p><?php endif; ?>
            <p class="signed-in">Signed in as <strong><?= escapeHtml($user['email']) ?></strong></p>
            <form method="post" action="<?= escapeHtml(applicationUrl('/student/password_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><?php if ($isEmbedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label><label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label><button>Update password</button></form>
        </section>
    </main>
</body>

</html>
