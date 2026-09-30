<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
$user = requireRole('DEAN');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}
$database = getDatabaseConnection();
$dean = deanAssignment($database, (int)$user['id']);
if ($dean['organization_code'] !== 'CCS') {
    http_response_code(403);
    exit;
}
$query = $database->prepare("SELECT image.stored_name,image.mime_type FROM evaluation_submission_images image INNER JOIN evaluation_submissions submission ON submission.id=image.submission_id INNER JOIN clearances clearance ON clearance.id=submission.clearance_id INNER JOIN students student ON student.id=clearance.student_id INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' WHERE image.id=:id AND student.organization_id=:organization_id");
$query->execute(['id' => $id, 'organization_id' => $dean['organization_id']]);
$image = $query->fetch();
$path = __DIR__ . '/../storage/private/evaluation-submissions/' . ($image['stored_name'] ?? '');
if (!$image || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $image['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
