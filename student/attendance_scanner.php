<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

$user = requireRole('STUDENT');
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ClearGo Attendance Scanner</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/attendance-scanner.css')) ?>">
</head>

<body>
    <main>
        <section class="panel">
            <h1>ClearGo Attendance Scanner</h1>
            <p class="muted">Point your camera at the event QR code. Only ClearGo attendance QR codes are accepted.</p>
            <div id="camera" aria-label="Camera preview"></div>
            <p id="status" class="notice">Starting camera…</p>
            <p><button id="retry" type="button" hidden>Enable camera and retry</button> <a href="<?= escapeHtml(applicationUrl('/student/dashboard.php')) ?>">Back to dashboard</a></p>
            <form id="scan-form" method="post" action="<?= escapeHtml(applicationUrl('/student/attendance_scanner_action.php')) ?>" hidden>
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input id="token" type="hidden" name="token">
            </form>
        </section>
    </main>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/attendance-scanner.js')) ?>"></script>
</body>

</html>