<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/event_functions.php';

requireRole('STUDENT_COUNCIL_STAFF');
$eventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
startSecureSession();
$display = $_SESSION['student_council_qr_display'] ?? null;
$token = $eventId !== false && is_array($display) && (int) ($display['event_id'] ?? 0) === (int) $eventId ? (string) ($display['token'] ?? '') : '';
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
try {
    $database = getDatabaseConnection();
    $period = activeEventPeriod($database);
    $event = $eventId === false ? false : studentCouncilEvent($database, (int) $period['id'], (int) $eventId);
    if ($event === false) {
        throw new RuntimeException('Event unavailable.');
    }
} catch (Throwable $exception) {
    setFlashMessage('Unable to display the event QR code.');
    redirectTo('/student-council/attendance.php');
}
$host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$publicUrl = rtrim((string) (getenv('APP_PUBLIC_URL') ?: ''), '/');
$checkInUrl = $validToken ? ($publicUrl !== '' ? $publicUrl : $scheme . '://' . $host) . applicationUrl('/student/attendance_checkin.php?token=' . rawurlencode($token)) : '';
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Student Council Event QR</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-council-event-qr.css')) ?>">
</head>

<body>
    <main>
        <section class="panel">
            <h1><?= escapeHtml($event['name']) ?></h1>
            <p>Student Council institutional attendance · <?= escapeHtml($event['event_date']) ?></p><?php if (!$validToken): ?><p class="notice">This QR display is no longer available in this browser. Open a new QR code from the Student Council dashboard.</p><?php else: ?><div id="qr" aria-label="Student attendance QR code"></div>
                <p><strong>Check-in closes:</strong> <?= escapeHtml((string) $event['check_in_closes_at']) ?></p>
                <p class="muted">Students scan this code with their phone camera, sign in with their school account, and confirm attendance.</p><?php endif; ?><div class="actions"><button type="button" onclick="window.print()">Print QR</button><a class="button" href="<?= escapeHtml(applicationUrl('/student-council/attendance.php')) ?>">Back to Events</a><?php if ($validToken): ?><form method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $eventId ?>"><button class="danger" type="submit">Close QR Now</button></form><?php endif; ?></div>
        </section>
    </main><?php if ($validToken): ?><script src="<?= escapeHtml(applicationUrl('/assets/js/qrcode.js')) ?>"></script>
        <script>
            const qr = qrcode(0, 'M');
            qr.addData(<?= json_encode($checkInUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            qr.make();
            document.getElementById('qr').innerHTML = qr.createSvgTag(7, 4);
        </script><?php endif; ?>
</body>

</html>
