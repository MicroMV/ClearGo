<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
requireDebugAccess();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Use a button on the debug screen.'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request. Refresh the debug screen.'); }
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';
try {
    $database = getDatabaseConnection();
    $action = $_POST['action'] ?? null;
    if ($action === 'ready_all' || $action === 'open') {
        $periodId = filter_var($_POST['period_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($periodId === false) { throw new RuntimeException('Refresh the screen and select the active semester.'); }
        if ($action === 'ready_all') {
            debugReadyAllOffices($database,null,$periodId);
            setFlashMessage('All offices are ready. Use Open clearance to make student clearance and PDF previews available.');
        } else {
            $period = registrarActivePeriod($database);
            if ((int)$period['id'] !== $periodId) { throw new RuntimeException('The active semester changed. Refresh this screen.'); }
            $students = openRegistrarClearance($database,null);
            setFlashMessage('Clearance opened for ' . $students . ' enrolled students. Student PDF previews are available.');
        }
    } elseif ($action === 'create_semester') {
        if ((int)$database->query("SELECT COUNT(*) FROM academic_periods WHERE status='ACTIVE'")->fetchColumn() !== 0) {
            throw new RuntimeException('An active semester already exists. Use Semester settings.');
        }
        $schoolYear = is_string($_POST['school_year'] ?? null) ? $_POST['school_year'] : '';
        $semester = is_string($_POST['semester'] ?? null) ? $_POST['semester'] : '';
        createRegistrarSemester($database,null,$schoolYear,$semester);
        setFlashMessage('Semester created. Enroll or import the student roster in Registrar, then use Ready all offices and Open clearance.');
    } elseif (in_array($action,['semesters','activity','backup'],true)) {
        $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
        $backup = debugResetData($database,null,$action,$confirmation);
        setFlashMessage(($action === 'backup' ? 'Backup created.' : 'Reset completed. Accounts and student roster were preserved.') . ' Backup: ' . $backup);
    } elseif ($action === 'disable') {
        $flag = __DIR__ . '/../storage/private/debug-console.enabled';
        if (!unlink($flag)) { throw new RuntimeException('Could not disable the debug screen.'); }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Debug disabled</title><p>Temporary debug screen disabled.</p><a href="' . escapeHtml(applicationUrl('/login.php')) . '">Open ClearGo</a></html>';
        exit;
    } else {
        throw new RuntimeException('Choose a debug action from the screen.');
    }
} catch (Throwable $exception) {
    error_log('Local debug action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException && !$exception instanceof PDOException
        ? $exception->getMessage() : 'The debug operation failed and database changes were rolled back. Check the server log.');
}
redirectTo('/debug/index.php');
