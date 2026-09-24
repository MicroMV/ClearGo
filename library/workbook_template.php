<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/workbook_functions.php';

requireRole('LIBRARY_STAFF');
try { $workbook = createLibraryWorkbookXlsx(getDatabaseConnection()); }
catch (Throwable $exception) {
    error_log('Library workbook export failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to create the Library workbook.');
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="cleargo-library-clearance.xlsx"');
header('Content-Length: ' . strlen($workbook));
header('Cache-Control: no-store');
echo $workbook;
