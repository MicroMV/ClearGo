<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
putenv('APP_BASE_URL=/school/clearance');
require __DIR__ . '/../../auth/auth_check.php';
$logoutForm = dashboardLogoutForm();
$head = connectionStatusAssets();
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">', $head,
    '<link rel="stylesheet" href="/school/clearance/assets/css/student-dashboard.css">',
    '<link rel="stylesheet" href="/school/clearance/assets/css/student-portal.css">',
    '<link rel="stylesheet" href="/school/clearance/assets/css/staff-dashboard.css">',
    '<title>Logout confirmation fixture</title></head><body class="TEST_BODY">',
    '<main style="max-width:600px;margin:40px auto;padding:20px"><h1>Account settings</h1>',
    '<label for="draft">Unsaved note</label><input id="draft" value="Keep this note">',
    '<div class="TEST_FORM_WRAPPER">', $logoutForm, '</div></main></body></html>';
session_destroy();
