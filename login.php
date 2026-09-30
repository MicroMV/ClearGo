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
    <title>ClearGo | The Lewis College</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/login.css')) ?>">

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
                <?php if ($message !== null): ?>
                    <p class="message" role="alert"><?= escapeHtml($message) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= escapeHtml(applicationUrl('/auth/login_process.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <label for="email">The Lewis College GSuite Address</label>
                    <input id="email" name="email" type="email" autocomplete="username" placeholder="name@thelewiscollege.edu.ph" pattern=".+@thelewiscollege\.edu\.ph" required>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="submit">Sign in to ClearGo</button>
                </form>
                <p class="form-note">Use your official @thelewiscollege.edu.ph account.</p>
            </div>
        </section>
    </main>
</body>

</html>