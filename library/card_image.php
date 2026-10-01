<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

requireRole('LIBRARY_STAFF');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
    exit;
}

$database = getDatabaseConnection();
$query = $database->prepare(
    "SELECT submission.stored_name,submission.mime_type
     FROM library_card_submissions submission
     INNER JOIN library_cards card ON card.id=submission.library_card_id
     INNER JOIN students student ON student.id=card.student_id
     INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
     INNER JOIN academic_periods period ON period.id=enrollment.academic_period_id AND period.status='ACTIVE'
     WHERE submission.id=:id AND enrollment.status='ENROLLED' LIMIT 1"
);
$query->execute(['id' => $id]);
$image = $query->fetch();
$path = __DIR__ . '/../storage/private/library-cards/' . basename((string) ($image['stored_name'] ?? ''));
if ($image === false || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $image['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
