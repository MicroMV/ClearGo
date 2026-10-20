<?php

declare(strict_types=1);

function connectionStatusAssets(bool $reloadOnRecovery = false): string
{
    $css = applicationUrl('/assets/css/connection-status.css?v=' . filemtime(__DIR__ . '/../assets/css/connection-status.css'));
    $js = applicationUrl('/assets/js/connection-status.js?v=' . filemtime(__DIR__ . '/../assets/js/connection-status.js'));
    return '<link rel="stylesheet" href="' . escapeHtml($css) . '">'
        . '<link rel="preload" as="image" href="' . escapeHtml(clearGoLogoUrl()) . '">'
        . '<script defer src="' . escapeHtml($js) . '" data-connection-status'
        . ' data-logo-url="' . escapeHtml(clearGoLogoUrl()) . '"'
        . ' data-status-url="' . escapeHtml(applicationUrl('/auth/connection_status.php')) . '"'
        . ' data-worker-url="' . escapeHtml(applicationUrl('/connection-worker.js')) . '"'
        . ($reloadOnRecovery ? ' data-reload-on-recovery="true"' : '') . '></script>';
}

/** Stop a failed web request before its caller redirects or renders an incomplete page. */
function displayDatabaseUnavailable(): never
{
    require_once __DIR__ . '/../auth/auth_check.php';
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(503);
    header('Cache-Control: no-store, max-age=0');
    header('Retry-After: 20');
    header('X-ClearGo-Service-Unavailable: database');
    $json = str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type: application/json') === 0) { $json = true; }
    }
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['available' => false, 'error' => 'ClearGo is temporarily unavailable. Please try again in a moment.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header_remove('Content-Disposition');
    require __DIR__ . '/../errors/unavailable.php';
    exit;
}
