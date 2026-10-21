<?php
declare(strict_types=1);
if (!isset($authRetryAfter, $authReturnPath)) { http_response_code(403); exit('Forbidden'); }
if ($authReturnPath === '/login.php?recovery=1') {
    $recoveryRateLimitMessage = 'Too many attempts. Please wait ' . (int) ceil($authRetryAfter / 60)
        . ' minute' . ($authRetryAfter > 60 ? 's' : '') . ' before trying again.';
    require __DIR__ . '/../login.php';
    exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Please wait | ClearGo</title><link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/login.css')) ?>"></head>
<body class="recovery-page"><main class="recovery-shell"><section class="signin-card recovery-card">
<h1>Too many attempts</h1><p role="alert">Please wait <?= (int) ceil($authRetryAfter / 60) ?> minute<?= $authRetryAfter > 60 ? 's' : '' ?> before trying again.</p>
<a class="recovery-back" href="<?= escapeHtml(applicationUrl($authReturnPath)) ?>">Return to ClearGo</a></section></main></body></html>
