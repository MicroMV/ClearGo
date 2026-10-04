<?php
declare(strict_types=1);

function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function applicationUrl(string $path): string { return $path; }
function officeDetailRecords($database, string $code, array $row): array { return $GLOBALS['testIncidents']; }

$database = null;
$csrfToken = 'test';
$actionUrl = '/comlab/action.php';
$selected = null;
foreach (['LOCKED', 'PENDING', 'APPROVED'] as $stationStatus) {
    foreach ([[], [['status' => 'OPEN', 'id' => 1, 'description' => '<unsafe>', 'created_at' => '2026-01-01 12:00:00', 'resolved_at' => null]], [['status' => 'RESOLVED', 'id' => 1, 'description' => 'Resolved incident', 'created_at' => '2026-01-01 12:00:00', 'resolved_at' => '2026-01-02 12:00:00']]] as $testIncidents) {
        $rows = [['clearance_id' => 1, 'first_name' => 'Test', 'last_name' => 'Student', 'station_status' => $stationStatus, 'clearance_status' => 'OPEN']];
        ob_start();
        require __DIR__ . '/../clearance/comlab_violations_modal.php';
        $html = ob_get_clean();
        $hasOpen = ($testIncidents[0]['status'] ?? '') === 'OPEN';
        if (str_contains($html, 'must visit') !== $hasOpen || str_contains($html, 'name="violation_at"') !== ($stationStatus !== 'APPROVED') || str_contains($html, 'value="resolve_comlab_incident"') !== ($hasOpen && $stationStatus !== 'APPROVED') || str_contains($html, '<unsafe>')) {
            throw new RuntimeException('Incorrect Comlab modal controls or warning.');
        }
    }
}
echo "Comlab violation modal checks passed.\n";
