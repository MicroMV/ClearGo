<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/completion_workbook_functions.php';
$user = requireRole('DEAN');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($_POST['csrf_token'] ?? null) || !verifyCsrfToken($_POST['csrf_token'])) { throw new RuntimeException('Unable to process that request. Please try again.'); }
    $database = getDatabaseConnection();
    ccsCompletionImportContext($database, (int) $user['id']);
    $periodId = filter_var($_POST['period_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $file = $_FILES['completion_workbook'] ?? null;
    if ($periodId === false || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($file['name'] ?? null) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx'
        || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) { throw new RuntimeException('Select the current CCS requirements completion .xlsx workbook.'); }
    $result = importCcsCompletionWorkbook($database, (int) $user['id'], (int) $periodId, readCcsCompletionWorkbook($file['tmp_name']));
    setFlashMessage('Completion list imported. Completed: ' . $result['completed'] . '. Incomplete (including blanks): ' . $result['not_completed'] . '. Already cleared preserved: ' . $result['already_cleared'] . '. Use Clear or Clear all ready to approve students.');
} catch (Throwable $exception) {
    error_log('CCS completion import failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to import the completion list.');
}
redirectTo('/dean/excel_import.php');
