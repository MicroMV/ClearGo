<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
// Bound stalled handshakes and reads so the browser receives the database 503
// before its own timeout. PDO's connect timeout alone does not bound these reads.
ini_set('mysqlnd.net_read_timeout', '3');
try {
    getDatabaseConnection()->query('SELECT 1');
    echo json_encode(['available' => true]);
} catch (Throwable $exception) {
    error_log('Connection check failed: ' . $exception->getMessage());
    displayDatabaseUnavailable();
}
