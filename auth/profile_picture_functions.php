<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

const PROFILE_PICTURE_MAX_BYTES = 2 * 1024 * 1024;

function profilePictureDirectory(): string
{
    return __DIR__ . '/../storage/private/profile-pictures';
}

function profilePicturePath(string $storedName): string
{
    if (!preg_match('/\A[a-f0-9]{40}\.(jpg|png|webp)\z/', $storedName)) {
        throw new RuntimeException('The profile picture record is invalid.');
    }
    return profilePictureDirectory() . '/' . $storedName;
}

function validateProfilePicture(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('Choose a profile picture no larger than 2 MB.');
    }
    $temporaryName = $file['tmp_name'] ?? '';
    if ($error !== UPLOAD_ERR_OK || !is_string($temporaryName) || $temporaryName === '' || !is_file($temporaryName)) {
        throw new RuntimeException('Choose a picture to upload.');
    }
    $size = filesize($temporaryName);
    if ($size === false || $size < 1 || $size > PROFILE_PICTURE_MAX_BYTES) {
        throw new RuntimeException('Choose a profile picture no larger than 2 MB.');
    }
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryName);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensions = @getimagesize($temporaryName);
    if (!is_string($mimeType) || !isset($extensions[$mimeType]) || $dimensions === false || ($dimensions['mime'] ?? '') !== $mimeType) {
        throw new RuntimeException('Choose a valid JPG, PNG or WebP picture.');
    }
    if ($dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 6000 || $dimensions[1] > 6000 || $dimensions[0] * $dimensions[1] > 16000000) {
        throw new RuntimeException('Choose a picture up to 16 megapixels and 6,000 pixels per side.');
    }
    return ['temporary_name' => $temporaryName, 'mime_type' => $mimeType, 'extension' => $extensions[$mimeType]];
}

function saveProfilePicture(PDO $database, array $user, array $file): void
{
    $picture = validateProfilePicture($file);
    if (!is_uploaded_file($picture['temporary_name'])) {
        throw new RuntimeException('The picture upload is invalid. Please select the file again.');
    }
    $directory = profilePictureDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Profile picture storage is unavailable.');
    }
    $storedName = bin2hex(random_bytes(20)) . '.' . $picture['extension'];
    $storedPath = profilePicturePath($storedName);
    if (!move_uploaded_file($picture['temporary_name'], $storedPath)) {
        throw new RuntimeException('Your profile picture could not be saved.');
    }

    $previousName = null;
    try {
        $database->beginTransaction();
        // Serialize updates to the same account, including the first picture upload.
        $account = $database->prepare("SELECT id,role FROM users WHERE id=:id AND status='ACTIVE' FOR UPDATE");
        $account->execute(['id' => $user['id']]);
        $accountRow = $account->fetch();
        if ($accountRow === false) {
            throw new RuntimeException('Your account is no longer active.');
        }
        $previous = $database->prepare('SELECT stored_name FROM user_profile_pictures WHERE user_id=:id');
        $previous->execute(['id' => $user['id']]);
        $previousName = $previous->fetchColumn() ?: null;
        $save = $database->prepare(
            'INSERT INTO user_profile_pictures (user_id,stored_name,mime_type) VALUES (:id,:name,:mime)
             ON DUPLICATE KEY UPDATE stored_name=VALUES(stored_name),mime_type=VALUES(mime_type),updated_at=NOW()'
        );
        $save->execute(['id' => $user['id'], 'name' => $storedName, 'mime' => $picture['mime_type']]);
        $stationId = null;
        $studentId = null;
        if ($accountRow['role'] === 'STUDENT') {
            $student = $database->prepare('SELECT id FROM students WHERE user_id=:id LIMIT 1');
            $student->execute(['id' => $user['id']]);
            $studentId = $student->fetchColumn() ?: null;
        } else {
            $stationCode = $accountRow['role'] === 'ORG_STAFF' ? 'ORGANIZATION' : preg_replace('/_STAFF$/', '', $accountRow['role']);
            $station = $database->prepare('SELECT id FROM stations WHERE code=:code LIMIT 1');
            $station->execute(['code' => $stationCode]);
            $stationId = $station->fetchColumn() ?: null;
        }
        $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status)
            VALUES (:id,'PROFILE_PICTURE_UPDATED',:station,:student,'User updated their own profile picture.',:old_status,:new_status)");
        $audit->execute([
            'id' => $user['id'], 'station' => $stationId, 'student' => $studentId,
            'old_status' => $previousName === null ? 'NO_PICTURE' : 'HAS_PICTURE',
            'new_status' => $previousName === null ? 'UPLOADED' : 'UPDATED',
        ]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        @unlink($storedPath);
        throw $exception;
    }
    if ($previousName !== null) {
        try {
            $previousPath = profilePicturePath($previousName);
            if (is_file($previousPath) && !@unlink($previousPath)) {
                error_log('Unable to remove the replaced profile picture.');
            }
        } catch (RuntimeException $exception) {
            error_log('Invalid previous profile picture record.');
        }
    }
}
