<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/mail_functions.php';
try {
    $database = getDatabaseConnection();
    // A database-specific advisory lock prevents overlapping scheduled workers.
    $name = 'cleargo_auth_mail_' . substr(hash('sha256', (string) $database->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    $lock = $database->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$name]);
    if ((int) $lock->fetchColumn() !== 1) { echo "Another mail worker is active.\n"; exit(0); }
    try {
        cleanupAuthRecords($database);
        $options = getopt('', ['limit:']); $limit = filter_var($options['limit'] ?? 20, FILTER_VALIDATE_INT);
        $result = dispatchAuthMail($database, $limit === false ? 20 : $limit);
        if (file_put_contents(authMailHeartbeatPath($database), json_encode(['time' => time(), 'delivery_errors' => $result['retry'] + $result['failed']]), LOCK_EX) === false) {
            throw new RuntimeException('Unable to record mail worker health.');
        }
        echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    } finally { $release = $database->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$name]); }
    if ($result['retry'] + $result['failed'] > 0) { exit(1); }
} catch (Throwable $error) {
    error_log('Authentication mail worker failed (' . get_class($error) . ').');
    fwrite(STDERR, "Mail delivery is unavailable. Check private SMTP settings and database migrations.\n"); exit(1);
}
