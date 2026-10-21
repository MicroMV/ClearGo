<?php

declare(strict_types=1);

function pwaAssets(): string
{
    $css = applicationUrl('/assets/css/pwa.css?v=' . filemtime(__DIR__ . '/../assets/css/pwa.css'));
    $js = applicationUrl('/assets/js/pwa.js?v=' . filemtime(__DIR__ . '/../assets/js/pwa.js'));
    return '<link rel="manifest" href="' . escapeHtml(applicationUrl('/manifest.webmanifest?v=' . filemtime(__DIR__ . '/../manifest.webmanifest'))) . '">'
        . '<meta name="theme-color" content="#0b2545">'
        . '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-title" content="ClearGo">'
        . '<link rel="apple-touch-icon" sizes="180x180" href="' . escapeHtml(applicationUrl('/assets/icons/apple-touch-icon-v3.png')) . '">'
        . '<link rel="icon" type="image/png" sizes="192x192" href="' . escapeHtml(applicationUrl('/assets/icons/cleargo-v3-192.png')) . '">'
        . '<link rel="stylesheet" href="' . escapeHtml($css) . '">'
        . '<script defer src="' . escapeHtml($js) . '"></script>';
}

function pwaInstallControl(): string
{
    return '<section class="pwa-install" data-pwa-install hidden aria-label="Install ClearGo">'
        . '<div><strong>ClearGo on your device</strong><p data-pwa-install-help>Open ClearGo from your home screen. An internet connection is required for transactions.</p></div>'
        . '<button type="button" data-pwa-install-button>Install ClearGo</button>'
        . '<p class="pwa-install-status" data-pwa-install-status role="status" aria-live="polite"></p></section>';
}
