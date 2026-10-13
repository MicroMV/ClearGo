<?php

declare(strict_types=1);

const APPLICATION_ROLES = [
    'STUDENT',
    'ORG_STAFF',
    'DEAN',
    'SASO_STAFF',
    'STUDENT_COUNCIL_STAFF',
    'LIBRARY_STAFF',
    'BOOKSTORE_STAFF',
    'CLINIC_STAFF',
    'FACILITIES_STAFF',
    'COMLAB_STAFF',
    'ACCOUNTING_STAFF',
    'REGISTRAR',
];

const ROLE_DASHBOARDS = [
    'STUDENT' => '/student/dashboard.php',
    'ORG_STAFF' => '/organization/dashboard.php',
    'DEAN' => '/dean/dashboard.php',
    'SASO_STAFF' => '/saso/dashboard.php',
    'STUDENT_COUNCIL_STAFF' => '/student-council/dashboard.php',
    'LIBRARY_STAFF' => '/library/dashboard.php',
    'BOOKSTORE_STAFF' => '/bookstore/dashboard.php',
    'CLINIC_STAFF' => '/clinic/dashboard.php',
    'FACILITIES_STAFF' => '/facilities/dashboard.php',
    'COMLAB_STAFF' => '/comlab/dashboard.php',
    'ACCOUNTING_STAFF' => '/accounting/dashboard.php',
    'REGISTRAR' => '/registrar/dashboard.php',
];

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $sessionDirectory = __DIR__ . '/../storage/sessions';

    if (!is_dir($sessionDirectory) || !is_writable($sessionDirectory)) {
        http_response_code(500);
        exit('Unable to start a secure session. Please try again later.');
    }

    session_name('clear_go_session');
    session_save_path($sessionDirectory);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    if (!@session_start()) {
        http_response_code(500);
        exit('Unable to start a secure session. Please try again later.');
    }
}

function applicationUrl(string $path = ''): string
{
    $configuredBaseUrl = getenv('APP_BASE_URL');
    if ($configuredBaseUrl !== false && $configuredBaseUrl !== '') {
        $baseUrl = '/' . trim($configuredBaseUrl, '/');
    } else {
        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
        $documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
        $baseUrl = '';

        if ($projectRoot !== '' && $documentRoot !== '' && stripos($projectRoot, $documentRoot) === 0) {
            $baseUrl = substr($projectRoot, strlen($documentRoot));
        }
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function clearGoLogoUrl(): string
{
    return applicationUrl('/assets/the-lewis-college-logo.png');
}

function redirectTo(string $path): never
{
    header('Location: ' . applicationUrl($path), true, 303);
    exit;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusDisplayLabel(string $status): string
{
    return strtoupper($status) === 'LOCKED' ? 'UNAVAILABLE' : str_replace('_', ' ', $status);
}

function paginationPages(int $currentPage, int $totalPages): array
{
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($currentPage, $totalPages));
    if ($totalPages <= 5) {
        return range(1, $totalPages);
    }
    if ($currentPage <= 2) {
        return [1, 2, 3, 'ellipsis', $totalPages];
    }
    if ($currentPage >= $totalPages - 1) {
        return [1, 'ellipsis', $totalPages - 2, $totalPages - 1, $totalPages];
    }
    return [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $totalPages];
}

function renderPagination(int $currentPage, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    unset($query['page'], $query['edit'], $query['export']);
    $pageLink = static function (int $page, string $label, string $class = '') use ($query): string {
        $url = '?' . http_build_query(array_merge($query, ['page' => $page]));
        return '<a class="pagination-link' . ($class !== '' ? ' ' . $class : '') . '" href="'
            . escapeHtml($url) . '">' . escapeHtml($label) . '</a>';
    };

    $html = '<nav class="pagination" aria-label="Student list pages">';
    if ($currentPage > 1) {
        $html .= $pageLink($currentPage - 1, 'Previous');
    }
    foreach (paginationPages($currentPage, $totalPages) as $page) {
        if ($page === 'ellipsis') {
            $html .= '<span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>';
        } elseif ((int) $page === $currentPage) {
            $html .= '<span class="pagination-link active" aria-current="page">' . (int) $page . '</span>';
        } else {
            $html .= $pageLink((int) $page, (string) $page);
        }
    }
    if ($currentPage < $totalPages) {
        $html .= $pageLink($currentPage + 1, 'Next');
    }
    return $html . '</nav>';
}

function createCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    startSecureSession();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlashMessage(string $message): void
{
    startSecureSession();
    $_SESSION['flash_message'] = $message;
}

function pullFlashMessage(): ?string
{
    startSecureSession();
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    return is_string($message) ? $message : null;
}

function authenticatedUser(): ?array
{
    startSecureSession();
    $user = $_SESSION['authenticated_user'] ?? null;

    if (
        !is_array($user)
        || !isset($user['id'], $user['email'], $user['role'])
        || !is_int($user['id'])
        || !is_string($user['email'])
        || !is_string($user['role'])
        || !in_array($user['role'], APPLICATION_ROLES, true)
    ) {
        return null;
    }

    return $user;
}

function requireLogin(): array
{
    $user = authenticatedUser();
    if ($user === null) {
        setFlashMessage('Please sign in to continue.');
        redirectTo('/login.php');
    }

    require_once __DIR__ . '/../config/database.php';
    try {
        $database = getDatabaseConnection();
        $statement = $database->prepare(
            "SELECT id,email,role,status FROM users
             WHERE id=:id AND email=:email AND role=:role
             LIMIT 1"
        );
        $statement->execute([
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
        $currentAccount = $statement->fetch();
    } catch (Throwable $exception) {
        error_log('Session account validation failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('Unable to verify your account right now. Please try again later.');
    }

    if ($currentAccount === false || $currentAccount['status'] !== 'ACTIVE') {
        unset($_SESSION['authenticated_user'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
        setFlashMessage('Your previous session is no longer active. Please sign in again.');
        redirectTo('/login.php');
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    return $user;
}

function requireRole(string $role): array
{
    $user = requireLogin();
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }

    return $user;
}

function redirectToDashboard(string $role): never
{
    if (!isset(ROLE_DASHBOARDS[$role])) {
        redirectTo('/login.php');
    }

    redirectTo(ROLE_DASHBOARDS[$role]);
}

function dashboardLogoutForm(): string
{
    $csrfToken = escapeHtml(createCsrfToken());
    $logoutUrl = escapeHtml(applicationUrl('/logout.php'));

    return <<<HTML
<form class="logout-form" method="post" action="{$logoutUrl}">
    <input type="hidden" name="csrf_token" value="{$csrfToken}">
    <button type="submit">Log out</button>
</form>
HTML;
}

/** Returns the current user's notifications without changing their read state. */
function dashboardNotifications(PDO $database, int $userId): array
{
    return dashboardNotificationPreview($database, $userId);
}

/** Returns notifications without changing their existing read state. */
function dashboardNotificationPreview(PDO $database, int $userId): array
{
    $statement = $database->prepare(
        'SELECT notification.id,notification.title,notification.message,notification.is_read,notification.created_at
         FROM notifications notification
         WHERE notification.user_id=:user_id
           AND NOT EXISTS (
               SELECT 1 FROM notifications newer
               WHERE newer.user_id=notification.user_id
                 AND newer.title=notification.title AND newer.message=notification.message
                 AND newer.id>notification.id
           )
         ORDER BY notification.created_at DESC,notification.id DESC LIMIT 100'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

/** Counts unread notification events while collapsing any historical exact duplicates. */
function dashboardUnreadNotificationCount(PDO $database, int $userId): int
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM notifications notification
         WHERE notification.user_id=:user_id AND notification.is_read=0
           AND NOT EXISTS (
               SELECT 1 FROM notifications newer
               WHERE newer.user_id=notification.user_id
                 AND newer.title=notification.title AND newer.message=notification.message
                 AND newer.id>notification.id
           )'
    );
    $statement->execute(['user_id' => $userId]);
    return (int) $statement->fetchColumn();
}

/**
 * Creates one unread notification for an event. An identical unread item is not inserted again.
 * A later occurrence is allowed after the earlier item has been viewed.
 */
function createNotification(PDO $database, int $userId, string $title, string $message): bool
{
    $title = trim($title);
    $message = trim($message);
    if ($userId < 1 || $title === '' || $message === '') {
        return false;
    }

    $statement = $database->prepare(
        "INSERT INTO notifications (user_id,title,message)
         SELECT :user_id,:title,:message
         WHERE NOT EXISTS (
             SELECT 1 FROM notifications
             WHERE user_id=:duplicate_user_id AND title=:duplicate_title
               AND message=:duplicate_message AND is_read=0
         )"
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => mb_substr($title, 0, 200),
        'message' => $message,
        'duplicate_user_id' => $userId,
        'duplicate_title' => mb_substr($title, 0, 200),
        'duplicate_message' => $message,
    ]);
    return $statement->rowCount() === 1;
}

/** Creates a deduplicated notification for each active user in the supplied roles. */
function notifyActiveRoles(PDO $database, array $roles, string $title, string $message): int
{
    $roles = array_values(array_unique(array_filter(
        $roles,
        static fn(mixed $role): bool => is_string($role) && in_array($role, APPLICATION_ROLES, true)
    )));
    if ($roles === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $users = $database->prepare("SELECT id FROM users WHERE role IN ({$placeholders}) AND status='ACTIVE'");
    $users->execute($roles);
    $created = 0;
    foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        $created += createNotification($database, (int) $userId, $title, $message) ? 1 : 0;
    }
    return $created;
}

/** Finalizes elapsed QR windows owned by a user and creates one useful summary notification. */
function notifyExpiredAttendanceWindows(PDO $database, int $userId): void
{
    try {
        $query = $database->prepare(
            "SELECT event.id,event.name,event.check_in_closes_at,
                    COUNT(CASE WHEN attendance.status='PRESENT' THEN 1 END) AS present_count
             FROM events event
             LEFT JOIN event_attendance attendance ON attendance.event_id=event.id
             WHERE event.created_by=:user_id AND event.status='ACTIVE'
               AND event.check_in_token_hash IS NOT NULL
               AND event.check_in_closes_at IS NOT NULL AND event.check_in_closes_at<NOW()
             GROUP BY event.id,event.name,event.check_in_closes_at"
        );
        $query->execute(['user_id' => $userId]);
    } catch (Throwable $exception) {
        // Older installations may not have QR attendance yet.
        return;
    }
    foreach ($query->fetchAll() as $event) {
        $close = $database->prepare(
            'UPDATE events SET check_in_token_hash=NULL WHERE id=:id AND check_in_token_hash IS NOT NULL AND check_in_closes_at<NOW()'
        );
        $close->execute(['id' => $event['id']]);
        if ($close->rowCount() === 1) {
            createNotification(
                $database,
                $userId,
                'Attendance time limit reached',
                'Check-in for "' . $event['name'] . '" closed at ' . $event['check_in_closes_at']
                    . '. Recorded present: ' . (int) $event['present_count'] . '.'
            );
        }
    }
}

function dashboardNotificationBell(array $notifications): string
{
    $unread = count(array_filter($notifications, static fn(array $notification): bool => !(int) $notification['is_read']));
    $badge = $unread > 0 ? '<span class="notification-count">' . ($unread > 99 ? '99+' : $unread) . '</span>' : '';
    $items = '';
    foreach ($notifications as $notification) {
        $items .= '<li class="notification-item' . (!(int) $notification['is_read'] ? ' unread' : '') . '" data-notification-id="' . (int) $notification['id'] . '">'
            . '<strong>' . escapeHtml((string) $notification['title']) . '</strong>'
            . '<span>' . escapeHtml((string) $notification['message']) . '</span>'
            . '<time>' . escapeHtml((string) $notification['created_at']) . '</time></li>';
    }
    if ($items === '') {
        $items = '<li class="notification-empty">No alerts or notifications yet.</li>';
    }

    $latestId = $notifications === [] ? 0 : max(array_map(static fn(array $item): int => (int) $item['id'], $notifications));
    $endpoint = escapeHtml(applicationUrl('/auth/notifications.php'));
    $csrfToken = escapeHtml(createCsrfToken());
    return '<div class="notification-center" data-notification-endpoint="' . $endpoint . '" data-csrf-token="' . $csrfToken . '" data-latest-notification-id="' . $latestId . '"><button class="notification-toggle" type="button" aria-label="View alerts and notifications' . ($unread > 0 ? ' (' . $unread . ' unread)' : '') . '" aria-expanded="false">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>' . $badge . '</button><div class="notification-menu" hidden>'
        . '<div class="notification-heading"><strong>Alerts &amp; notifications</strong><span>' . count($notifications) . ' total</span></div>'
        . '<ul>' . $items . '</ul></div></div>';
}

/**
 * Builds the shared staff navigation shell without changing role permissions or routes.
 *
 * @param array<int,array{key:string,label:string,url:string,icon?:string}> $items
 */
function staffDashboardSidebar(string $officeName, array $user, array $items, string $activeKey, string $logoutForm): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
        'students' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11a3 3 0 0 0 0-6M20 20v-1a4 4 0 0 0-3-3.87"/></svg>',
        'import' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3"/></svg>',
        'staff' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 8h4M19 6v4M17 16h4"/></svg>',
        'clearance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v4H7zM5 5H3v16h18V5h-2M8 13l3 3 5-6"/></svg>',
        'audit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"/></svg>',
        'document' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6zM14 3v5h5M9 12h6M9 16h6"/></svg>',
    ];
    $links = '';
    foreach ($items as $item) {
        $key = (string) $item['key'];
        $icon = $icons[$item['icon'] ?? $key] ?? $icons['dashboard'];
        $isActive = $key === $activeKey;
        $links .= '<a href="' . escapeHtml(applicationUrl((string) $item['url'])) . '"'
            . ($isActive ? ' class="active" aria-current="page"' : '') . '><span class="nav-icon">'
            . $icon . '</span><span>' . escapeHtml((string) $item['label']) . '</span></a>';
    }
    $email = escapeHtml((string) $user['email']);
    $initial = escapeHtml(strtoupper(substr((string) $user['email'], 0, 1)));
    $logo = escapeHtml(clearGoLogoUrl());
    $pageScript = $activeKey === 'submitted_requirements'
        ? '<script defer src="' . escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) . '"></script>'
        : '';

    return '<aside class="staff-sidebar" id="staff-sidebar" aria-label="Office navigation">'
        . '<button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">&#10005;</button>'
        . '<div class="sidebar-brand"><img src="' . $logo . '" alt="The Lewis College logo"><div class="sidebar-brand-copy"><span>The Lewis College</span><strong>ClearGo</strong></div></div>'
        . '<div class="sidebar-office"><p class="sidebar-kicker">Staff workspace</p><p class="sidebar-office-name">' . escapeHtml($officeName) . '</p></div>'
        . '<nav class="staff-nav">' . $links . '</nav>'
        . '<div class="sidebar-footer"><div class="sidebar-user"><span class="sidebar-avatar">' . $initial . '</span><span class="sidebar-user-copy"><strong>' . $email . '</strong><span>Authorized staff</span></span></div>'
        . $logoutForm . '</div></aside><button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Close navigation"></button>' . $pageScript;
}

function registrarDashboardSidebar(array $user, string $activeKey, string $logoutForm): string
{
    return staffDashboardSidebar('Registrar Administration', $user, [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/registrar/dashboard.php', 'icon' => 'dashboard'],
        ['key' => 'import', 'label' => 'Import Enrollment', 'url' => '/registrar/student_import.php', 'icon' => 'import'],
        ['key' => 'students', 'label' => 'Student Accounts', 'url' => '/registrar/students.php', 'icon' => 'students'],
        ['key' => 'staff', 'label' => 'Staff Accounts', 'url' => '/registrar/staff_accounts.php', 'icon' => 'staff'],
        ['key' => 'clearance', 'label' => 'Clearance Review', 'url' => '/registrar/clearance.php', 'icon' => 'clearance'],
        ['key' => 'audit', 'label' => 'Audit Logs', 'url' => '/registrar/audit_logs.php', 'icon' => 'audit'],
    ], $activeKey, $logoutForm);
}
