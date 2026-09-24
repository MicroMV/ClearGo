<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';

if (authenticatedUser() !== null) {
    redirectToDashboard($_SESSION['authenticated_user']['role']);
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Digital Clearance System</title>
    <style>
        body { align-items: center; background: #f3f8fd; color: #17324d; display: flex; font-family: Arial, sans-serif; justify-content: center; margin: 0; min-height: 100vh; }
        main { background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(23, 50, 77, .12); max-width: 380px; padding: 32px; width: calc(100% - 48px); }
        h1 { color: #0d5ea6; margin-top: 0; } label { display: block; font-weight: 600; margin: 18px 0 6px; } input { border: 1px solid #b7c8d8; border-radius: 5px; box-sizing: border-box; font-size: 16px; padding: 10px; width: 100%; } button { background: #0d5ea6; border: 0; border-radius: 5px; color: #fff; cursor: pointer; font-size: 16px; margin-top: 24px; padding: 11px; width: 100%; } .message { background: #fff1f1; border-radius: 5px; color: #8a1d1d; padding: 10px; }
    </style>
</head>
<body>
    <main>
        <h1>Digital Clearance System</h1>
        <?php if ($message !== null): ?>
            <p class="message"><?= escapeHtml($message) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= escapeHtml(applicationUrl('/auth/login_process.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <label for="email">The Lewis College GSuite Address</label>
            <input id="email" name="email" type="email" autocomplete="username" placeholder="name@thelewiscollege.edu.ph" pattern=".+@thelewiscollege\.edu\.ph" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="submit">Log in</button>
        </form>
    </main>
</body>
</html>
