<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/workbook_functions.php';

requireRole('CLINIC_STAFF');
try {
    $workbook = createClinicWorkbookXlsx(getDatabaseConnection());
} catch (Throwable $exception) {
    error_log('Clinic workbook export failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to create the Clinic workbook.');
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="cleargo-clinic-clearance.xlsx"');
header('Content-Length: ' . strlen($workbook));
header('Cache-Control: no-store');
echo $workbook;
