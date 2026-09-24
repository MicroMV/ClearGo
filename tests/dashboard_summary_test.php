<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

$database = getDatabaseConnection();
foreach (['STUDENT_COUNCIL','LIBRARY','BOOKSTORE','CLINIC','SASO','FACILITIES','ACCOUNTING','REGISTRAR'] as $code) {
    $summary = officeStatusSummary($database, $code);
    $parts = $summary['READY'] + $summary['PENDING'] + $summary['RETURNED'] + $summary['APPROVED'];
    if ($summary['TOTAL'] !== $parts) {
        throw new RuntimeException("{$code} summary does not add up to its total.");
    }
    $filters = in_array($code, ['CLINIC', 'LIBRARY', 'BOOKSTORE'], true)
        ? ['PENDING', 'APPROVED']
        : ['READY', 'PENDING', 'RETURNED', 'APPROVED'];
    foreach ($filters as $filter) {
        $filteredTotal = officeRowCount($database, $code, '', $filter);
        if ($filteredTotal !== $summary[$filter]) {
            throw new RuntimeException("{$code} {$filter} filter count does not match its summary.");
        }
        $filteredRows = officeRows($database, $code, '', 30, 0, $filter);
        if (count($filteredRows) !== min(30, $filteredTotal)) {
            throw new RuntimeException("{$code} {$filter} page does not contain the expected rows.");
        }
    }
}
$organizations = $database->query("SELECT id,code FROM organizations WHERE status='ACTIVE'")->fetchAll();
foreach ($organizations as $organization) {
    $summary = organizationStatusSummary($database, (int) $organization['id']);
    $parts = $summary['ELIGIBLE'] + $summary['PENDING'] + $summary['RETURNED'] + $summary['APPROVED'];
    if ($summary['TOTAL'] !== $parts) {
        throw new RuntimeException($organization['code'] . ' organization summary does not add up to its total.');
    }
}
echo "Dashboard summary consistency test passed.\n";
