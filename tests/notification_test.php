<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$database = getDatabaseConnection();
$database->beginTransaction();
try {
    $userId = (int) $database->query("SELECT id FROM users WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($userId < 1) {
        throw new RuntimeException('No active account is available for the notification test.');
    }
    $message = 'NOTIFICATION-TEST-' . bin2hex(random_bytes(5));
    createNotification($database, $userId, 'Notification test', $message);
    createNotification($database, $userId, 'Notification test', $message);
    $count = $database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND message=:message');
    $count->execute(['user_id' => $userId, 'message' => $message]);
    if ((int) $count->fetchColumn() !== 1) {
        throw new RuntimeException('An identical unread notification was inserted more than once.');
    }
    dashboardNotifications($database, $userId);
    $unread = $database->prepare('SELECT is_read FROM notifications WHERE user_id=:user_id AND message=:message');
    $unread->execute(['user_id' => $userId, 'message' => $message]);
    if ((int) $unread->fetchColumn() !== 0) {
        throw new RuntimeException('Loading a dashboard incorrectly marked the notification as read.');
    }

    $database->prepare('UPDATE notifications SET is_read=1 WHERE user_id=:user_id AND message=:message')
        ->execute(['user_id' => $userId, 'message' => $message]);
    createNotification($database, $userId, 'Notification test', $message);
    $count->execute(['user_id' => $userId, 'message' => $message]);
    if ((int) $count->fetchColumn() !== 2) {
        throw new RuntimeException('A valid later notification was incorrectly suppressed.');
    }
    echo "Notification deduplication test passed.\n";
} finally {
    $database->rollBack();
}
