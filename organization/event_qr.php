<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/event_functions.php';

$user = requireRole('ORG_STAFF');
$eventId = filter_var($_GET['event'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($eventId === false) { redirectTo('/organization/dashboard.php'); }

try {
    $database = getDatabaseConnection();
    $organization = assignedOrganization($database, $user['id']);
    $period = activeEventPeriod($database);
    $event = organizationEvent($database, (int) $organization['id'], (int) $period['id'], (int) $eventId);
    if ($event === false) { throw new RuntimeException('The selected event is unavailable.'); }
} catch (Throwable $exception) {
    error_log('Organization QR page failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to display the QR code.');
    redirectTo('/organization/dashboard.php');
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
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event QR Attendance</title>
    <style>
        :root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}main{max-width:760px;margin:30px auto;padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:12px;padding:28px;text-align:center}h1{color:var(--blue)}#qr{display:flex;justify-content:center;margin:24px auto}#qr svg{max-width:100%;height:auto}.notice{padding:13px;background:#fff7e8;border-left:4px solid #b87800;text-align:left}.actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:22px}button,a.button{border:0;border-radius:5px;padding:11px 16px;background:var(--blue);color:#fff;font:inherit;text-decoration:none;cursor:pointer}.danger{background:#aa3824!important}.muted{color:#587086;font-size:14px;word-break:break-all}
    </style>
</head>
<body><main><section class="panel">
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
        <button type="button" onclick="window.print()">Print QR</button><a class="button" href="<?= escapeHtml(applicationUrl('/organization/dashboard.php')) ?>">Back to Events</a>
        <?php if ($validToken): ?><form method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $eventId ?>"><button class="danger" type="submit">Close QR Now</button></form><?php endif; ?>
    </div>
</section></main>
<?php if ($validToken): ?><script src="<?= escapeHtml(applicationUrl('/assets/js/qrcode.js')) ?>"></script><script>
const qr = qrcode(0, 'M');
qr.addData(<?= json_encode($checkInPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
qr.make();
document.getElementById('qr').innerHTML = qr.createSvgTag(7, 4);
</script><?php endif; ?>
</body></html>
