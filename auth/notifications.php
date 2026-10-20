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
    $account = $database->prepare("SELECT id FROM users WHERE id=:id AND email=:email AND role=:role AND status='ACTIVE'");
    $account->execute(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
    if (!$account->fetchColumn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required.']);
        exit;
    }
    $periodId = notificationPeriodForUser($database, $user, $_GET['period'] ?? null);
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
        markSemesterNotificationsRead($database, (int) $user['id'], $periodId);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    notifyExpiredAttendanceWindows($database, (int) $user['id']);
    if (in_array($user['role'], ['STUDENT','ORG_STAFF','STUDENT_COUNCIL_STAFF'], true)) {
        require_once __DIR__ . '/../clearance/sanction_functions.php';
        $activePeriodId = $database->query("SELECT id FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
        if ($activePeriodId !== false) { syncExpiredEventSanctions($database, (int) $activePeriodId); }
    }
    $notifications = dashboardNotificationPreview($database, (int) $user['id'], $periodId);
    echo json_encode([
        'academic_period_id' => $periodId,
        'unread_count' => dashboardUnreadNotificationCount($database, (int) $user['id'], $periodId),
        'notifications' => array_map(static fn(array $notification): array => [
            'id' => (int) $notification['id'],
            'title' => (string) $notification['title'],
            'message' => (string) $notification['message'],
            'is_read' => (bool) $notification['is_read'],
            'created_at' => (string) $notification['created_at'],
        ], $notifications),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(403);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Notification endpoint failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Notifications are temporarily unavailable.']);
}
