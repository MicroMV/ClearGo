<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/workbook_functions.php';
$user = requireRole('BOOKSTORE_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process the Bookstore workbook. Please try again.');
    redirectTo('/bookstore/dashboard.php');
}
try {
    $file = $_FILES['bookstore_workbook'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Select the completed Bookstore Excel workbook.');
    }
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > BOOKSTORE_WORKBOOK_MAX_FILE_SIZE) {
        throw new RuntimeException('The Bookstore workbook must be no larger than 5 MB.');
    }
    if (strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') {
        throw new RuntimeException('Upload the .xlsx Bookstore workbook downloaded from ClearGo.');
    }
    $database = getDatabaseConnection();
    $result = importBookstoreWorkbook($database, (int)$user['id'], readBookstoreWorkbook((string)$file['tmp_name']));
    $message = 'Bookstore workbook imported. Cleared: ' . $result['cleared'] . '. Already cleared: ' . $result['already_cleared'] . '. Unchanged: ' . $result['skipped'] . '. Errors: ' . count($result['errors']) . '.';
    $message .= ' Remarks updated: ' . $result['remarks_updated'] . '.';
    if ($result['errors'] !== []) {
        $message .= ' ' . implode(' ', array_slice($result['errors'], 0, 3));
    }
    createNotification($database, (int) $user['id'], 'Bookstore Excel import completed', $message);
    setFlashMessage($message);
} catch (Throwable $exception) {
    error_log('Bookstore workbook import failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to import the Bookstore workbook.');
}
redirectTo('/bookstore/dashboard.php');
