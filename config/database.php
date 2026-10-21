<?php
declare(strict_types=1);

require_once __DIR__ . '/availability.php';

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Forbidden');
}

function getDatabaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = appSetting('DB_HOST', 'localhost');
    $port = appSetting('DB_PORT', '3306');
    $database = appSetting('DB_DATABASE', 'lewis_clearance');
    $username = appSetting('DB_USERNAME', 'root');
    $password = appSetting('DB_PASSWORD');

    if (productionEnvironment() && (strtolower($username) === 'root' || $username === '' || $password === '')) {
        error_log('Production database configuration requires a dedicated user and password.');
        if (PHP_SAPI !== 'cli') { displayDatabaseUnavailable(); }
        throw new RuntimeException('Secure database configuration is required.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    try {
        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        if (PHP_SAPI !== 'cli') {
            displayDatabaseUnavailable();
        }
        throw new RuntimeException('Database connection failed.', 0, $exception);
    }

    return $connection;
}
