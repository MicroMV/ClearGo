<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function previewCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$database = getDatabaseConnection();
$registrar = $database->query("SELECT id,email,role FROM users WHERE role='REGISTRAR' AND status='ACTIVE' ORDER BY id LIMIT 1")->fetch();
previewCheck($registrar !== false, 'An active Registrar is required for the preview render test.');
$registrar['id'] = (int) $registrar['id'];
$rows = [];
for ($index = 1; $index <= 2319; $index++) {
    $rows[] = ['row_number' => $index + 5, 'action' => 'UPDATE', 'student_id' => 'PREVIEW-' . $index,
        'student_name' => 'Preview Student ' . $index, 'course_program' => 'BSCS', 'year_level' => 1,
        'school_email' => 'preview-' . $index . '@test.invalid', 'organization_label' => 'CCS',
        'errors' => $index > 2317 ? ['Test validation error'] : [], 'warnings' => []];
}
$preview = ['rows' => $rows, 'academic_period' => '2027-2028 2ND SEMESTER', 'valid_count' => 2317,
    'added_count' => 0, 'updated_count' => 2317, 'error_count' => 2];
$importToken = bin2hex(random_bytes(32));
startSecureSession();
$_SESSION = ['authenticated_user' => $registrar, 'student_import_preview' => $preview,
    'student_import_name' => 'pagination-preview.xlsx', 'student_import_token' => $importToken];
$sessionId = session_id();
session_write_close();

try {
    // GET requests render a synthetic preview; no import is submitted and no student data is changed.
    foreach (['' => 1, '2' => 2, '78' => 78, '99999' => 78, '-1' => 1, 'invalid' => 1] as $requestedPage => $expectedPage) {
        $url = (getenv('CLEAR_GO_TEST_BASE_URL') ?: 'http://localhost/ClearGo') . '/registrar/student_import.php' . ($requestedPage === '' ? '' : '?page=' . rawurlencode((string) $requestedPage));
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIE => 'clear_go_session=' . $sessionId]);
        $html = curl_exec($curl);
        previewCheck(is_string($html) && curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200, 'Preview page request failed.');
        curl_close($curl);
        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new DOMXPath($document);
        $visibleRows = $xpath->query('//section[@id="import-preview"]//tbody/tr');
        $expectedOffset = ($expectedPage - 1) * 30;
        previewCheck($visibleRows->length === min(30, 2319 - $expectedOffset), 'Preview page contains the wrong number of rows.');
        $firstCells = $visibleRows->item(0)->getElementsByTagName('td');
        previewCheck(trim($firstCells->item(0)->textContent) === (string) ($expectedOffset + 6), 'Preview pagination renumbered the original spreadsheet row.');
        previewCheck(trim($firstCells->item(2)->textContent) === 'PREVIEW-' . ($expectedOffset + 1), 'Preview page starts with the wrong student.');
        previewCheck(str_contains($html, 'Page ' . $expectedPage . ' of 78'), 'Preview page count is incorrect.');
        previewCheck(str_contains($html, 'of 2319 rows') && str_contains($html, 'Import 2317 Enrolled Students'), 'Pagination changed the full-file summary or import count.');
        $pagination = $xpath->query('//section[@id="import-preview"]//nav[@class="pagination"]');
        previewCheck($pagination->length === 1, 'Preview pagination is missing.');
        foreach ($pagination->item(0)->getElementsByTagName('a') as $link) {
            previewCheck(str_ends_with($link->getAttribute('href'), '#import-preview'), 'Page links do not return to the preview.');
        }
        $confirmation = $xpath->query('//form[input[@name="action" and @value="import"]]/input[@name="import_token"]');
        previewCheck($confirmation->length === 1 && $confirmation->item(0)->getAttribute('value') === $importToken, 'Paging changed the import confirmation token.');
    }
    startSecureSession();
    previewCheck($_SESSION['student_import_preview'] === $preview, 'Pagination changed the full preview stored for import.');
    previewCheck($_SESSION['student_import_token'] === $importToken, 'Pagination expired the import token.');
    previewCheck(!str_contains(renderPagination(1, 2), '#'), 'Default pagination links were changed for other pages.');
    echo "Import preview pagination passed: 2,319 rows, page limits, row numbers and full-file confirmation preserved.\n";
} finally {
    if (session_status() !== PHP_SESSION_ACTIVE) { startSecureSession(); }
    $_SESSION = [];
    session_destroy();
}
