<?php

declare(strict_types=1);

require_once __DIR__ . '/remarks_functions.php';
require_once __DIR__ . '/../config/database.php';

$user = requireLogin();
// Resolve a fixed, authorized office destination; never accept a redirect URL from the form.
$paths = ['DEAN' => '/dean/dashboard.php', 'ORG_STAFF' => '/organization/dashboard.php'];
$path = $paths[$user['role']] ?? (OFFICE_ROLE_CONFIG[$user['role']]['path'] ?? null);
if ($path === null) { http_response_code(403); exit('Access denied.'); }
$query = [];
foreach (['search', 'course', 'year_level', 'filter', 'group', 'audience', 'department', 'page'] as $key) {
    if (isset($_POST['return_query'][$key]) && is_scalar($_POST['return_query'][$key])) { $query[$key] = (string) $_POST['return_query'][$key]; }
}
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('Unable to process that request. Please try again.');
    }
    $id = filter_var($_POST['clearance_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || !is_string($_POST['remarks'] ?? null)) { throw new RuntimeException('Provide a valid student and remarks.'); }
    $changed = saveOfficeRemarks(getDatabaseConnection(), $user, (int) $id, $_POST['remarks']);
    setFlashMessage($changed ? 'Remarks saved.' : 'Remarks are already up to date.');
} catch (Throwable $exception) {
    error_log('Office remarks update failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Unable to save remarks. Please try again.');
}
redirectTo($path . ($query === [] ? '' : '?' . http_build_query($query)));
