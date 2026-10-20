<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function officeDetailRecords($database, string $code, array $row): array { return []; }

$database = null;
$csrfToken = 'test';
$actionUrl = '/facilities/action.php';
$selected = null;
foreach ([['LOCKED', true, 'OPEN', true, false], ['PENDING', true, 'OPEN', true, true], ['PENDING', false, 'OPEN', true, false], ['APPROVED', true, 'OPEN', false, false], ['PENDING', true, 'CLOSED', false, false]] as [$stationStatus, $eligible, $clearanceStatus, $canBorrow, $canClear]) {
    $rows = [['clearance_id' => 1, 'station_status' => $stationStatus, 'clearance_status' => $clearanceStatus, 'eligibility' => ['eligible' => $eligible]]];
    ob_start();
    require __DIR__ . '/../clearance/facilities_items_modal.php';
    $html = ob_get_clean();
    if (str_contains($html, 'name="borrowed_at"') !== $canBorrow || str_contains($html, 'value="approve"') !== $canClear) {
        throw new RuntimeException("Incorrect Facilities controls for {$stationStatus}/{$clearanceStatus}.");
    }
}
echo "Facilities borrowing modal checks passed.\n";
