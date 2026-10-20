<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// Exercise the real POST handler with a disposable session and roll back every database change.
require_once __DIR__ . '/../clearance/remarks_functions.php';
require_once __DIR__ . '/../config/database.php';
$database = getDatabaseConnection();
$mode = $argv[1] ?? 'save';
$user = $database->query("SELECT id,email,role FROM users WHERE role='LIBRARY_STAFF' AND status='ACTIVE' LIMIT 1")->fetch();
$record = $database->query("SELECT record.id,record.clearance_id,record.office_remarks,record.status,record.returned_reason
    FROM clearance_stations record INNER JOIN stations station ON station.id=record.station_id AND station.code='LIBRARY'
    INNER JOIN clearances clearance ON clearance.id=record.clearance_id
    INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' AND period.clearance_status IN ('PREPARING','OPEN')
    INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
    INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
    WHERE record.status<>'APPROVED' LIMIT 1")->fetch();
if (!$user || !$record) { throw new RuntimeException('An active Library account and enrolled student are required.'); }
startSecureSession();
$_SESSION['authenticated_user'] = $user;
$token = createCsrfToken();
$copy = "POST remarks test " . bin2hex(random_bytes(6)) . ".\nBring the original.";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['csrf_token' => $mode === 'csrf' ? 'invalid' : $token, 'clearance_id' => $record['clearance_id'], 'remarks' => $copy,
    'return_query' => ['page' => '2', 'search' => 'test']];
$database->beginTransaction();
if ($mode === 'cleared') {
    $database->prepare("UPDATE clearance_stations SET status='APPROVED' WHERE id=:id")->execute(['id' => $record['id']]);
    $record['status'] = 'APPROVED';
}
register_shutdown_function(static function () use ($database, $record, $copy, $mode): void {
    $failure = null;
    try {
        $statement = $database->prepare('SELECT office_remarks,status,returned_reason FROM clearance_stations WHERE id=:id');
        $statement->execute(['id' => $record['id']]);
        $updated = $statement->fetch();
        $flash = pullFlashMessage();
        if ($mode === 'csrf') {
            if ($updated['office_remarks'] !== $record['office_remarks'] || $flash !== 'Unable to process that request. Please try again.') { throw new RuntimeException('Invalid CSRF was not rejected.'); }
        } elseif ($mode === 'cleared') {
            if ($updated['office_remarks'] !== $record['office_remarks'] || $flash !== 'Remarks cannot be changed after this office has cleared the student.') { throw new RuntimeException('Cleared-student remarks edit was not rejected.'); }
        } elseif ($updated['office_remarks'] !== $copy || $flash !== 'Remarks saved.') {
            throw new RuntimeException('POST did not persist remarks or confirm the save: ' . (string) $flash);
        }
        if ($updated['status'] !== $record['status'] || $updated['returned_reason'] !== $record['returned_reason']) { throw new RuntimeException('Remarks POST changed the clearance decision.'); }
    } catch (Throwable $exception) { $failure = $exception; }
    finally {
        if ($database->inTransaction()) { $database->rollBack(); }
        $_SESSION = [];
        session_destroy();
    }
    if ($failure) { fwrite(STDERR, $failure->getMessage() . "\n"); exit(1); }
    echo 'Office remarks POST ' . $mode . " check passed; all database changes rolled back.\n";
});
require __DIR__ . '/../clearance/remarks_action.php';
