<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

requireRole('BOOKSTORE_STAFF');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
    exit;
}

$database = getDatabaseConnection();
$query = $database->prepare(
    "SELECT submission.stored_name,submission.mime_type
     FROM bookstore_receipt_submissions submission
     INNER JOIN student_bookstore_fees student_fee ON student_fee.id=submission.student_fee_id
     INNER JOIN bookstore_fees fee ON fee.id=student_fee.fee_id
     INNER JOIN academic_periods period ON period.id=fee.academic_period_id AND period.status='ACTIVE'
     WHERE submission.id=:id AND submission.stored_name IS NOT NULL"
);
$query->execute(['id' => $id]);
$image = $query->fetch();
$path = __DIR__ . '/../storage/private/bookstore-receipts/' . basename((string) ($image['stored_name'] ?? ''));
if ($image === false || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $image['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
