<?php

declare(strict_types=1);

require_once __DIR__ . '/profile_picture_functions.php';

$user = requireLogin();
try {
    $path = profilePicturePath((string) ($user['profile_picture'] ?? ''));
    if (!is_file($path) || !in_array($user['profile_picture_mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Profile picture unavailable.');
    }
} catch (RuntimeException $exception) {
    http_response_code(404);
    exit('Profile picture unavailable.');
}
header('Content-Type: ' . $user['profile_picture_mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="profile-picture.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
