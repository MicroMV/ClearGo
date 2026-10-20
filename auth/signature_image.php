<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireLogin();
if ($user['role'] === 'STUDENT') {
    http_response_code(403);
    exit('Forbidden');
}
$database = getDatabaseConnection();
if (array_key_exists('id', $_GET)) {
    $signatureId = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($signatureId === false) { http_response_code(404); exit; }
    $query = $database->prepare('SELECT image_data,mime_type,image_sha256 FROM staff_saved_signatures WHERE id=:id AND user_id=:user_id LIMIT 1');
    $query->execute(['id' => $signatureId, 'user_id' => $user['id']]);
} else {
    $query = $database->prepare('SELECT image_data,mime_type,image_sha256 FROM staff_signatures WHERE user_id=:user_id LIMIT 1');
    $query->execute(['user_id' => $user['id']]);
}
$signature = $query->fetch();
if ($signature === false) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $signature['mime_type']);
header('Content-Length: ' . strlen($signature['image_data']));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; sandbox');
header('ETag: "' . $signature['image_sha256'] . '"');
echo $signature['image_data'];
