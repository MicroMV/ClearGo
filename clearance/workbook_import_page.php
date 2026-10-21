<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function renderWorkbookImportPage(string $role): never
{
    $config = match ($role) {
        'LIBRARY_STAFF' => ['station' => 'LIBRARY', 'title' => 'Library Clearance', 'directory' => 'library'],
        'BOOKSTORE_STAFF' => ['station' => 'BOOKSTORE', 'title' => 'Bookstore Clearance', 'directory' => 'bookstore'],
        'CLINIC_STAFF' => ['station' => 'CLINIC', 'title' => 'Clinic Clearance', 'directory' => 'clinic'],
        'DEAN' => ['station' => 'DEAN', 'title' => 'CCS Dean Clearance', 'directory' => 'dean'],
        default => throw new InvalidArgumentException('This office has no workbook import page.'),
    };
    $user = requireRole($role);
    try {
        $database = getDatabaseConnection();
        if ($role === 'DEAN') {
            require_once __DIR__ . '/clearance_functions.php';
            $dean = deanAssignment($database, (int) $user['id']);
            if ($dean['organization_code'] !== 'CCS') {
                http_response_code(403);
                exit('Excel requirements completion is available only to the assigned CCS Dean.');
            }
        }
        $periods = $database->query("SELECT id,school_year,semester,clearance_status FROM academic_periods WHERE status='ACTIVE' ORDER BY id DESC LIMIT 2")->fetchAll();
        $period = count($periods) === 1 ? $periods[0] : false;
        $notifications = dashboardNotifications($database, (int) $user['id']);
    } catch (Throwable $exception) {
        error_log('Excel import page failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Unable to load Excel Import right now. Please try again later.');
    }
    $canImport = $period !== false && in_array($period['clearance_status'], ['PREPARING', 'OPEN'], true);
    $stationCode = $config['station'];
    $officeName = $config['title'];
    $dashboardUrl = '/' . $config['directory'] . '/dashboard.php';
    $items = [['key' => 'dashboard', 'label' => 'Dashboard', 'url' => $dashboardUrl, 'icon' => 'dashboard']];
    if ($role === 'DEAN') {
        $items[] = ['key' => 'excel-import', 'label' => 'Excel Import', 'url' => '/dean/excel_import.php', 'icon' => 'import'];
    } elseif ($role === 'CLINIC_STAFF') {
        $items[] = ['key' => 'schedules', 'label' => 'Medical Schedules', 'url' => '/clinic/schedules.php', 'icon' => 'document'];
    } elseif ($role === 'LIBRARY_STAFF') {
        $items[] = ['key' => 'submissions', 'label' => 'Card Submissions', 'url' => '/library/submissions.php', 'icon' => 'document'];
    } else {
        $items[] = ['key' => 'fees', 'label' => 'Event Fees', 'url' => '/bookstore/fees.php', 'icon' => 'document'];
        $items[] = ['key' => 'submissions', 'label' => 'Receipt Submissions', 'url' => '/bookstore/submissions.php', 'icon' => 'document'];
    }
    $sidebar = staffDashboardSidebar($officeName, $user, $items, 'excel-import', dashboardLogoutForm());
    $csrfToken = createCsrfToken();
    $message = pullFlashMessage();
    $readiness = $period === false ? null : $period + ['academic_period_id' => (int) $period['id']];
    require __DIR__ . '/workbook_import_view.php';
    exit;
}
