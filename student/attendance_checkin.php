<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../organization/event_functions.php';

startSecureSession();
$token = (string) ($_SESSION['pending_attendance_token'] ?? '');
$confirmation = $_SESSION['attendance_confirmation'] ?? null;
unset($_SESSION['attendance_confirmation']);
if (!is_array($confirmation) && !preg_match('/^[a-f0-9]{64}$/', $token)) {
    setFlashMessage('Use the ClearGo Attendance Scanner to scan the event QR code.');
    redirectTo('/student/dashboard.php');
}

$user = authenticatedUser();
if ($user === null) {
    setFlashMessage('Sign in with your student GSuite account to confirm attendance.');
    redirectTo('/login.php');
}
if ($user['role'] !== 'STUDENT') {
    http_response_code(403);
    exit('Only students can use an attendance QR code.');
}
$user = requireRole('STUDENT');

if (is_array($confirmation)) {
    $context = false;
} else {
    try {
        $database = getDatabaseConnection();
        $context = qrAttendanceContext($database, $token, $user['id']);
    } catch (Throwable $exception) {
        error_log('Attendance QR validation failed: ' . $exception->getMessage());
        $context = false;
    }
}
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm Attendance</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/attendance-checkin.css')) ?>">
</head>

<body>
    <main>
        <section class="panel">
            <?php if (is_array($confirmation)): ?>
                <h1>✓ Attendance Confirmed</h1>
                <p class="success notice">You are marked <strong>PRESENT</strong> for <strong><?= escapeHtml((string) ($confirmation['event_name'] ?? 'this event')) ?></strong>.</p><a target="_top" href="<?= escapeHtml(applicationUrl('/student/dashboard.php#attendance')) ?>">Student Dashboard</a>
            <?php elseif ($context === false): ?>
                <h1>QR Check-in Unavailable</h1>
                <p class="notice">This QR code is expired, closed, or belongs to a different organization. Ask your organization officer to open a new QR code.</p><a target="_top" href="<?= escapeHtml(applicationUrl('/student/dashboard.php#attendance')) ?>">Student Dashboard</a>
            <?php elseif ($context['attendance_status'] === 'PRESENT'): ?>
                <h1>Attendance Already Recorded</h1>
                <p class="success notice">Your attendance for <strong><?= escapeHtml($context['name']) ?></strong> is already marked present.</p><a target="_top" href="<?= escapeHtml(applicationUrl('/student/dashboard.php#attendance')) ?>">Student Dashboard</a>
            <?php else: ?>
                <h1>Confirm Attendance</h1>
                <p><strong><?= escapeHtml($context['name']) ?></strong></p>
                <p class="muted"><?= escapeHtml($context['organization_name'] . ' (' . $context['organization_code'] . ')') ?> · <?= escapeHtml($context['event_date']) ?></p>
                <p>Student: <strong><?= escapeHtml($context['student_number'] . ' — ' . $context['first_name'] . ' ' . $context['last_name']) ?></strong></p>
                <p class="notice">Only confirm while you are physically attending this event.</p>
                <form method="post" action="<?= escapeHtml(applicationUrl('/student/attendance_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="token" value="<?= escapeHtml($token) ?>"><button type="submit">Confirm My Attendance</button></form>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>
