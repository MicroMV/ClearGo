<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/completion_workbook_functions.php';
$user = requireRole('DEAN');
try {
    $workbook = createCcsCompletionWorkbook(getDatabaseConnection(), (int) $user['id']);
    downloadClearanceReport($workbook, 'ccs-requirements-completion.xlsx');
} catch (Throwable $exception) {
    error_log('CCS completion download failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to download the completion list.');
    redirectTo('/dean/excel_import.php');
}
