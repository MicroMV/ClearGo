<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/workbook_functions.php';

$user = requireRole('LIBRARY_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process the Library workbook. Please try again.');
    redirectTo('/library/dashboard.php');
}
try {
    $file = $_FILES['library_workbook'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Select the completed Library Excel workbook.');
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > LIBRARY_WORKBOOK_MAX_FILE_SIZE) {
        throw new RuntimeException('The Library workbook must be no larger than 5 MB.');
    }
    if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') {
        throw new RuntimeException('Upload the .xlsx Library workbook downloaded from ClearGo.');
    }
    $database = getDatabaseConnection();
    $result = importLibraryWorkbook($database, (int) $user['id'], readLibraryWorkbook((string) $file['tmp_name']));
    $message = 'Library workbook imported. Cleared: ' . $result['cleared'] . '. Already cleared: ' . $result['already_cleared']
        . '. Unchanged: ' . $result['skipped'] . '. Errors: ' . count($result['errors']) . '.';
    if ($result['errors'] !== []) {
        $message .= ' ' . implode(' ', array_slice($result['errors'], 0, 3));
    }
    createNotification($database, (int) $user['id'], 'Library Excel import completed', $message);
    setFlashMessage($message);
} catch (Throwable $exception) {
    error_log('Library workbook import failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to import the Library workbook.');
}
redirectTo('/library/dashboard.php');
