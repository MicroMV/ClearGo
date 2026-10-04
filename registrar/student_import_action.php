<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/student_import_functions.php';

$registrar = requireRole('REGISTRAR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/registrar/student_import.php');
}

$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'preview') {
        $file = $_FILES['student_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Select an .xlsx or .csv file to upload.');
        }
        if (($file['size'] ?? 0) < 1 || (int) $file['size'] > STUDENT_IMPORT_MAX_FILE_SIZE) {
            throw new RuntimeException('The upload must be no larger than 5 MB.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('Only .xlsx and .csv files are supported.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('The uploaded file could not be verified.');
        }
        $preview = validateStudentImport(
            getDatabaseConnection(),
            readStudentImportFile((string) $file['tmp_name'], (string) $file['name'])
        );
        $_SESSION['student_import_preview'] = $preview;
        $_SESSION['student_import_name'] = basename((string) $file['name']);
        $_SESSION['student_import_token'] = bin2hex(random_bytes(32));
    } elseif ($action === 'import') {
        $preview = $_SESSION['student_import_preview'] ?? null;
        $token = (string) ($_POST['import_token'] ?? '');
        if (!is_array($preview) || empty($_SESSION['student_import_token']) || !hash_equals((string) $_SESSION['student_import_token'], $token)) {
            throw new RuntimeException('The import preview has expired. Upload the file again.');
        }
        if (($preview['valid_count'] ?? 0) < 1) {
            throw new RuntimeException('There are no valid records to import.');
        }
        $database = getDatabaseConnection();
        $result = importValidatedStudents($database, $preview, $registrar['id']);
        createNotification(
            $database,
            (int) $registrar['id'],
            'Enrollment Excel import completed',
            'Import successful. Added: ' . $result['added'] . '; updated: ' . $result['updated']
                . '; removed from semester: ' . $result['removed_from_semester'] . '; skipped: ' . $result['skipped'] . '.'
        );
        unset($_SESSION['student_import_preview'], $_SESSION['student_import_name'], $_SESSION['student_import_token']);
        $_SESSION['student_import_result'] = $result;
    } elseif ($action === 'cancel') {
        unset($_SESSION['student_import_preview'], $_SESSION['student_import_name'], $_SESSION['student_import_token']);
    } else {
        throw new RuntimeException('Invalid import action.');
    }
} catch (Throwable $exception) {
    error_log('Student data import failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'The import could not be completed. No student records were changed.');
}

redirectTo('/registrar/student_import.php');
