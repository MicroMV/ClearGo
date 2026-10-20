<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/event_functions.php';

$user = requireRole('ORG_STAFF');
$eventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($eventId === false) {
    redirectTo('/organization/attendance.php');
}

try {
    $database = getDatabaseConnection();
    $organization = assignedOrganization($database, $user['id']);
    $period = activeEventPeriod($database);
    $event = organizationEvent($database, (int) $organization['id'], (int) $period['id'], (int) $eventId);
    if ($event === false) {
        throw new RuntimeException('The selected event is unavailable.');
    }
} catch (Throwable $exception) {
    error_log('Organization QR page failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to display the QR code.');
    redirectTo('/organization/attendance.php');
}

startSecureSession();
$display = $_SESSION['organization_qr_display'] ?? null;
$token = is_array($display) && (int) ($display['event_id'] ?? 0) === (int) $eventId
    ? (string) ($display['token'] ?? '') : '';
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$checkInPayload = $validToken ? 'CLEARGO-ATTENDANCE:' . $token : '';
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">

<head><?= connectionStatusAssets() ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event QR Attendance</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/organization-event-qr.css')) ?>">
</head>

<body>
    <main>
        <section class="panel">
            <h1><?= escapeHtml($event['name']) ?></h1>
            <p><?= escapeHtml($organization['code']) ?> attendance · <?= escapeHtml($event['event_date']) ?></p>
            <?php if (!$validToken): ?>
                <p class="notice">This QR display is no longer available in this browser. Open a new QR code from the organization dashboard.</p>
            <?php else: ?>
                <div id="qr" aria-label="Student attendance QR code"></div>
                <p><strong>Check-in closes:</strong> <?= escapeHtml((string) $event['check_in_closes_at']) ?></p>
                <p class="muted">Students must use the ClearGo Attendance Scanner while signed in to their student account. Other QR scanner apps cannot check them in.</p>
            <?php endif; ?>
            <div class="actions">
                <button type="button" onclick="window.print()">Print QR</button><a class="button" href="<?= escapeHtml(applicationUrl('/organization/attendance.php')) ?>">Back to Events</a>
                <?php if ($validToken): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $eventId ?>"><button class="danger" type="submit">Close QR Now</button></form><?php endif; ?>
            </div>
        </section>
    </main>
    <?php if ($validToken): ?><script src="<?= escapeHtml(applicationUrl('/assets/js/qrcode.js')) ?>"></script>
        <script>
            const qr = qrcode(0, 'M');
            qr.addData(<?= json_encode($checkInPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            qr.make();
            document.getElementById('qr').innerHTML = qr.createSvgTag(7, 4);
        </script><?php endif; ?>
</body>

</html>