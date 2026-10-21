<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

$database = getDatabaseConnection();
foreach (['STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'SASO', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
    $summary = officeStatusSummary($database, $code);
    $parts = $summary['READY'] + $summary['PENDING'] + $summary['RETURNED'] + $summary['APPROVED'];
    if ($summary['TOTAL'] !== $parts) {
        throw new RuntimeException("{$code} summary does not add up to its total.");
    }
    $filters = ['READY', 'PENDING', 'RETURNED', 'APPROVED'];
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
$departments = $database->query("SELECT code FROM organizations WHERE status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['GRADUATING', 'NON_GRADUATING'] as $audience) {
    foreach (array_merge(['ALL'], $departments) as $department) {
        $summary = officeStatusSummary($database, 'FACILITIES', $audience, $department);
        foreach (['ALL' => 'TOTAL', 'READY' => 'READY', 'PENDING' => 'PENDING', 'RETURNED' => 'RETURNED', 'APPROVED' => 'APPROVED'] as $filter => $key) {
            if (officeRowCount($database, 'FACILITIES', '', $filter, $audience, $department) !== $summary[$key]) {
                throw new RuntimeException("Facilities {$audience}/{$department} {$filter} summary and destination list differ.");
            }
        }
    }
}
$clinic = officeStatusSummary($database, 'CLINIC');
if (officeRowCount($database, 'CLINIC', '', 'COMPLIANCE') !== $clinic['TOTAL'] - $clinic['APPROVED']) {
    throw new RuntimeException('Clinic compliance summary and destination list differ.');
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
