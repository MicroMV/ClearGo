<?php
declare(strict_types=1);

function debugRequestIsLocal(array $server): bool
{
    $remote = $server['REMOTE_ADDR'] ?? '';
    $host = strtolower($server['HTTP_HOST'] ?? '');
    return in_array($remote, ['127.0.0.1','::1','::ffff:127.0.0.1'], true)
        && preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?\z/', $host) === 1
        && empty($server['HTTP_FORWARDED']) && empty($server['HTTP_X_FORWARDED_FOR']);
}

function requireDebugAccess(): void
{
    $flag = __DIR__ . '/../storage/private/debug-console.enabled';
    if (!debugRequestIsLocal($_SERVER) || getenv('CLEAR_GO_DEBUG') === '0'
        || !is_file($flag) || trim((string)file_get_contents($flag)) !== 'local-debug-only') {
        http_response_code(404);
        exit('Not found');
    }
    require_once __DIR__ . '/../auth/auth_check.php';
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    // This temporary tool has its own session, independent of school logins.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('clear_go_debug_session');
        session_save_path(__DIR__ . '/../storage/sessions');
        ini_set('session.use_strict_mode','1');
        session_set_cookie_params([
            'lifetime'=>0, 'path'=>applicationUrl('/debug/'),
            'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly'=>true, 'samesite'=>'Strict',
        ]);
        if (!session_start()) { http_response_code(500); exit('Unable to start a local debug session.'); }
    }
}

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit('Not found');
}
