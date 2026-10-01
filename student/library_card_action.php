<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that library card submission.');
    redirectTo('/student/dashboard.php');
}

if (($_POST['capture_source'] ?? '') !== 'live_camera') {
    setFlashMessage('Use the live camera to capture your library card.');
    redirectTo('/student/dashboard.php');
}

$file = $_FILES['library_card_photo'] ?? [];
$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
$size = (int) ($file['size'] ?? 0);
$temporaryName = (string) ($file['tmp_name'] ?? '');
$originalName = basename((string) ($file['name'] ?? ''));
$storedPath = null;

try {
    if ($error !== UPLOAD_ERR_OK || $temporaryName === '' || !is_uploaded_file($temporaryName)) {
        throw new RuntimeException('Capture a clear photo of your library card.');
    }
    if ($size < 1 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('The library card photo must be no larger than 8 MB.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryName) ?: '';
    if ($mimeType !== 'image/jpeg' || $originalName !== 'library-card-camera.jpg') {
        throw new RuntimeException('Use the live camera to capture your library card.');
    }

    $directory = __DIR__ . '/../storage/private/library-cards';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Secure library card storage is unavailable.');
    }
    $storedName = bin2hex(random_bytes(20)) . '.jpg';
    $storedPath = $directory . '/' . $storedName;
    if (!move_uploaded_file($temporaryName, $storedPath)) {
        throw new RuntimeException('The library card photo could not be stored.');
    }

    $database = getDatabaseConnection();
    $database->beginTransaction();
    $query = $database->prepare(
        "SELECT student.id AS student_id,card.id AS library_card_id,card.status AS card_status,
                station_record.status AS station_status
         FROM students student
         INNER JOIN library_cards card ON card.student_id=student.id
         INNER JOIN academic_periods period ON period.status='ACTIVE' AND period.clearance_status='OPEN'
         INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         INNER JOIN stations station ON station.code='LIBRARY'
         INNER JOIN clearance_stations station_record ON station_record.clearance_id=clearance.id AND station_record.station_id=station.id
         WHERE student.user_id=:user_id AND student.status='ACTIVE' LIMIT 1 FOR UPDATE"
    );
    $query->execute(['user_id' => $user['id']]);
    $card = $query->fetch();
    if ($card === false || in_array($card['station_status'], ['LOCKED', 'APPROVED'], true)) {
        throw new RuntimeException('The Library card requirement is not available right now.');
    }

    $oldQuery = $database->prepare('SELECT status,stored_name FROM library_card_submissions WHERE library_card_id=:card_id FOR UPDATE');
    $oldQuery->execute(['card_id' => $card['library_card_id']]);
    $oldSubmission = $oldQuery->fetch();
    if ($oldSubmission !== false && in_array($oldSubmission['status'], ['SUBMITTED', 'VERIFIED'], true)) {
        throw new RuntimeException('Your library card photo is already waiting for confirmation.');
    }

    $save = $database->prepare(
        "INSERT INTO library_card_submissions (library_card_id,status,stored_name,original_name,mime_type,submitted_at)
         VALUES (:card_id,'SUBMITTED',:stored_name,:original_name,:mime_type,NOW())
         ON DUPLICATE KEY UPDATE status='SUBMITTED',stored_name=VALUES(stored_name),original_name=VALUES(original_name),
             mime_type=VALUES(mime_type),submitted_at=NOW(),reviewed_at=NULL,reviewed_by=NULL,return_reason=NULL"
    );
    $save->execute([
        'card_id' => $card['library_card_id'],
        'stored_name' => $storedName,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
    ]);
    $database->prepare("UPDATE library_cards SET status='PENDING',verified_by=NULL,verified_at=NULL WHERE id=:id")
        ->execute(['id' => $card['library_card_id']]);
    $notify = $database->prepare("INSERT INTO notifications (user_id,title,message) SELECT id,'Library card awaiting review',:message FROM users WHERE role='LIBRARY_STAFF' AND status='ACTIVE'");
    $notify->execute(['message' => 'A student captured and submitted a library card photo.']);
    $database->commit();

    if ($oldSubmission !== false && !empty($oldSubmission['stored_name']) && $oldSubmission['stored_name'] !== $storedName) {
        $oldPath = $directory . '/' . basename((string) $oldSubmission['stored_name']);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    setFlashMessage('Library card photo submitted. Please wait for Library confirmation.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    if ($storedPath !== null && is_file($storedPath)) {
        @unlink($storedPath);
    }
    error_log('Library card submission failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to submit the library card photo.');
}

redirectTo('/student/dashboard.php');
