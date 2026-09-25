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

function redirectTo(string $path): never
{
    header('Location: ' . applicationUrl($path), true, 303);
    exit;
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function paginationPages(int $currentPage, int $totalPages): array
{
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($currentPage, $totalPages));
    if ($totalPages <= 5) { return range(1, $totalPages); }
    if ($currentPage <= 2) { return [1, 2, 3, 'ellipsis', $totalPages]; }
    if ($currentPage >= $totalPages - 1) {
        return [1, 'ellipsis', $totalPages - 2, $totalPages - 1, $totalPages];
    }
    return [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $totalPages];
}

function renderPagination(int $currentPage, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) { return ''; }
    unset($query['page'], $query['edit'], $query['export']);
    $pageLink = static function (int $page, string $label, string $class = '') use ($query): string {
        $url = '?' . http_build_query(array_merge($query, ['page' => $page]));
        return '<a class="pagination-link' . ($class !== '' ? ' ' . $class : '') . '" href="'
            . escapeHtml($url) . '">' . escapeHtml($label) . '</a>';
    };

    $html = '<nav class="pagination" aria-label="Student list pages">';
    if ($currentPage > 1) { $html .= $pageLink($currentPage - 1, 'Previous'); }
    foreach (paginationPages($currentPage, $totalPages) as $page) {
        if ($page === 'ellipsis') {
            $html .= '<span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>';
        } elseif ((int) $page === $currentPage) {
            $html .= '<span class="pagination-link active" aria-current="page">' . (int) $page . '</span>';
        } else {
            $html .= $pageLink((int) $page, (string) $page);
        }
    }
    if ($currentPage < $totalPages) { $html .= $pageLink($currentPage + 1, 'Next'); }
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

    if (!is_array($user)
        || !isset($user['id'], $user['email'], $user['role'])
        || !is_int($user['id'])
        || !is_string($user['email'])
        || !is_string($user['role'])
        || !in_array($user['role'], APPLICATION_ROLES, true)) {
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

function renderDashboard(array $user, string $title): never
{
    $email = escapeHtml($user['email']);
    $role = escapeHtml($user['role']);
    $pageTitle = escapeHtml($title);
    $logoutForm = dashboardLogoutForm();

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$pageTitle} | Digital Clearance System</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f3f8fd; color: #17324d; }
        main { max-width: 680px; margin: 12vh auto; padding: 32px; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(23, 50, 77, .12); }
        h1 { margin-top: 0; color: #0d5ea6; } p { line-height: 1.6; } button { background: #0d5ea6; border: 0; border-radius: 5px; color: #fff; cursor: pointer; padding: 10px 16px; }
    </style>
</head>
<body>
    <main>
        <h1>Welcome, {$email}</h1>
        <p>You are signed in to the {$pageTitle}.</p>
        <p><strong>Role:</strong> {$role}</p>
        {$logoutForm}
    </main>
</body>
</html>
HTML;
    exit;
}
