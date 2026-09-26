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
    <style>
        :root{--blue:#155eae;--navy:#103f74;--ink:#172b42;--muted:#607289;--line:#dce5ef;--page:#f4f8fc;--danger:#ab3932;--danger-bg:#fff1ef}*{box-sizing:border-box}body{min-height:100vh;margin:0;background:var(--page);color:var(--ink);font:15px/1.5 "Segoe UI",Arial,sans-serif}.shell{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(390px,.85fr);min-height:100vh}.welcome{position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:48px clamp(32px,7vw,112px);background:linear-gradient(145deg,#0b315d,#155eae);color:#fff}.welcome:before,.welcome:after{position:absolute;content:"";border-radius:50%;pointer-events:none}.welcome:before{right:-12vw;bottom:-22vw;width:52vw;height:52vw;border:1px solid rgba(255,255,255,.13);box-shadow:0 0 0 62px rgba(255,255,255,.035),0 0 0 124px rgba(255,255,255,.025)}.welcome:after{left:-80px;top:-100px;width:250px;height:250px;background:rgba(255,255,255,.055)}.brand,.welcome-content,.welcome-footer{position:relative;z-index:1}.brand{display:flex;align-items:center;gap:12px;font-weight:700}.brand-logo{width:58px;height:58px;object-fit:contain}.brand-copy small{display:block;color:#c9ddf2;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}.brand-copy strong{display:block;font-size:18px;line-height:1.1}.welcome-content{max-width:610px;margin:72px 0}.eyebrow{margin:0 0 14px;color:#bdd8f4;font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase}.welcome h1{max-width:560px;margin:0;font-size:clamp(34px,4.2vw,58px);line-height:1.08;letter-spacing:-.04em}.lead{max-width:520px;margin:22px 0 0;color:#dceafa;font-size:18px}.benefits{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:38px}.benefit{padding:14px;border:1px solid rgba(255,255,255,.18);border-radius:10px;background:rgba(5,35,72,.17);font-size:13px}.benefit strong{display:block;margin-bottom:4px;font-size:14px}.welcome-footer{color:#c9ddf2;font-size:12px}.signin-area{display:grid;place-items:center;padding:32px;background:#fff}.signin-card{width:min(100%,420px)}.signin-card h2{margin:0;color:var(--ink);font-size:28px;letter-spacing:-.025em}.intro{margin:7px 0 28px;color:var(--muted)}.message{margin:0 0 18px;padding:11px 13px;border:1px solid #f3cbc7;border-left:4px solid var(--danger);border-radius:8px;background:var(--danger-bg);color:#842d28}label{display:block;margin:18px 0 6px;color:#334b66;font-size:13px;font-weight:700}input{width:100%;border:1px solid #bfcddb;border-radius:8px;background:#fff;color:var(--ink);font:inherit;padding:12px}input:focus{outline:3px solid #cfe4fa;border-color:var(--blue)}button{width:100%;min-height:45px;margin-top:26px;border:0;border-radius:8px;background:var(--blue);color:#fff;cursor:pointer;font:inherit;font-weight:700}button:hover{background:var(--navy)}.form-note{margin:17px 0 0;color:var(--muted);font-size:12px;text-align:center}@media(max-width:850px){.shell{grid-template-columns:1fr}.welcome{min-height:330px;padding:30px 28px}.welcome-content{margin:42px 0}.welcome h1{font-size:36px}.lead{font-size:16px}.benefits{display:none}.signin-area{padding:42px 24px}}@media(max-width:420px){.welcome{min-height:295px;padding:24px}.welcome h1{font-size:31px}.signin-area{padding:32px 20px}}
    </style>
    <style>.brand-logo{width:80px;height:80px;object-fit:cover;object-position:center}</style>
</head>
<body>
    <main class="shell">
        <section class="welcome" aria-labelledby="welcome-title">
            <div class="brand"><img class="brand-logo" src="<?= escapeHtml(clearGoLogoUrl()) ?>" alt="The Lewis College logo"><div class="brand-copy"><small>The Lewis College</small><strong>ClearGo</strong></div></div>
            <div class="welcome-content"><p class="eyebrow">End-of-semester clearance</p><h1 id="welcome-title">Clearance, made clear.</h1><p class="lead">A focused, secure workspace for students and offices to manage end-of-semester clearance with confidence.</p><div class="benefits"><div class="benefit"><strong>One clear process</strong>Follow your clearance progress in one place.</div><div class="benefit"><strong>Office-ready</strong>Review student records with the right context.</div><div class="benefit"><strong>Built for the college</strong>Designed for The Lewis College community.</div></div></div>
            <div class="welcome-footer">Automated End-of-Semester Clearance System</div>
        </section>
        <section class="signin-area" aria-labelledby="signin-title"><div class="signin-card"><h2 id="signin-title">Welcome back</h2><p class="intro">Sign in with your official Lewis College account.</p>
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
        </div></section>
    </main>
</body>
</html>
