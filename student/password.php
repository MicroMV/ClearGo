<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
$user = requireRole('STUDENT');
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-password.css')) ?>">
</head>

<body>
    <header>
        <div class="topbar">
            <div>
                <h1>Change Password</h1><a href="<?= escapeHtml(applicationUrl('/student/dashboard.php')) ?>">Back to Dashboard</a>
            </div><?= $logoutForm ?>
        </div>
    </header>
    <main><?php if ($message): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?><section class="panel">
            <p>Signed in as <strong><?= escapeHtml($user['email']) ?></strong></p>
            <form method="post" action="<?= escapeHtml(applicationUrl('/student/password_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label><label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label><button>Change Password</button></form>
        </section>
    </main>
</body>

</html>