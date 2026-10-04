<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
$user = authenticatedUser();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

try {
    $database = getDatabaseConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || !verifyCsrfToken($payload['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request token.']);
            exit;
        }
        if (($payload['action'] ?? '') !== 'mark_read') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification action.']);
            exit;
        }
        $statement = $database->prepare('UPDATE notifications SET is_read=1 WHERE user_id=:user_id AND is_read=0');
        $statement->execute(['user_id' => $user['id']]);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    notifyExpiredAttendanceWindows($database, (int) $user['id']);
    $notifications = dashboardNotificationPreview($database, (int) $user['id']);
    echo json_encode([
        'unread_count' => dashboardUnreadNotificationCount($database, (int) $user['id']),
        'notifications' => array_map(static fn(array $notification): array => [
            'id' => (int) $notification['id'],
            'title' => (string) $notification['title'],
            'message' => (string) $notification['message'],
            'is_read' => (bool) $notification['is_read'],
            'created_at' => (string) $notification['created_at'],
        ], $notifications),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Notification endpoint failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Notifications are temporarily unavailable.']);
}
