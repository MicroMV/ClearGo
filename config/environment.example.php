<?php
// Copy to environment.local.php, which is ignored by Git and denied over HTTP.
// Environment variables supplied by the hosting service override these values.
return [
    'APP_ENV' => 'production',
    'APP_PUBLIC_URL' => 'https://clearance.example.edu.ph',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'lewis_clearance',
    'DB_USERNAME' => 'cleargo_app',
    'DB_PASSWORD' => '',
    // Generate once: php -r "echo 'base64:'.base64_encode(random_bytes(32)),PHP_EOL;"
    'APP_KEY' => '',
    'MAIL_HOST' => '',
    'MAIL_PORT' => '587',
    'MAIL_ENCRYPTION' => 'tls', // tls (STARTTLS) or smtps
    'MAIL_USERNAME' => '',
    'MAIL_PASSWORD' => '',
    'MAIL_FROM_ADDRESS' => '',
    'MAIL_FROM_NAME' => 'ClearGo | The Lewis College',
    'AUTH_LOGIN_IP_LIMIT' => '10000', // 15-minute aggregate, shared campus networks
    'AUTH_RESET_IP_LIMIT' => '300',   // One-hour aggregate; account limits are separate
];
