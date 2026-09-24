<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $database = getDatabaseConnection();
    $statement = $database->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1'
    );
    $statement->execute(['table_name' => 'users']);

    if ($statement->fetchColumn() === false) {
        throw new RuntimeException('Required table is unavailable.');
    }

    http_response_code(200);
    echo 'Database connection successful.';
} catch (Throwable $exception) {
    error_log('Database connection test failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Database connection failed.';
}
