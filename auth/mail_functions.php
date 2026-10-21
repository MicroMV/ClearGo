<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403); exit('Forbidden');
}
require_once __DIR__ . '/security_functions.php';

function authMailKey(): string
{
    $configured = appSetting('APP_KEY');
    $key = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : false;
    if (!is_string($key) || strlen($key) !== 32) { throw new RuntimeException('Email recovery is not configured.'); }
    return $key;
}

function authMailSettings(): array
{
    authMailKey();
    $settings = ['host' => trim(appSetting('MAIL_HOST')), 'port' => appSetting('MAIL_PORT', '587'),
        'encryption' => strtolower(appSetting('MAIL_ENCRYPTION', 'tls')), 'username' => appSetting('MAIL_USERNAME'),
        'password' => appSetting('MAIL_PASSWORD'), 'from' => appSetting('MAIL_FROM_ADDRESS'),
        'name' => appSetting('MAIL_FROM_NAME', 'ClearGo | The Lewis College')];
    if ($settings['host'] === '' || preg_match('/[\s\/;\x00-\x1f]/', $settings['host'])
        || !ctype_digit($settings['port']) || (int) $settings['port'] < 1 || (int) $settings['port'] > 65535
        || !filter_var($settings['from'], FILTER_VALIDATE_EMAIL)
        || !in_array($settings['encryption'], ['tls', 'smtps', 'none'], true)) {
        throw new RuntimeException('Email recovery is not configured.');
    }
    $localTest = !productionEnvironment() && in_array($settings['host'], ['localhost', '127.0.0.1', '::1'], true);
    if (($settings['encryption'] === 'none' && !$localTest)
        || (!$localTest && ($settings['username'] === '' || $settings['password'] === ''))) {
        throw new RuntimeException('Secure email recovery is not configured.');
    }
    return $settings;
}

function authMailConfigured(): bool
{
    try { authMailSettings(); return true; } catch (RuntimeException $error) { return false; }
}

function authMailHeartbeatPath(PDO $database): string
{
    $key = substr(hash('sha256', (string) $database->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    return __DIR__ . '/../storage/private/auth-mail-worker-' . $key . '.heartbeat';
}

function encryptAuthMail(array $payload, string $context): string
{
    $iv = random_bytes(12); $tag = '';
    $ciphertext = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm', authMailKey(), OPENSSL_RAW_DATA, $iv, $tag, $context);
    if ($ciphertext === false) { throw new RuntimeException('Unable to prepare email.'); }
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptAuthMail(string $payload, string $context): array
{
    $bytes = base64_decode($payload, true);
    if (!is_string($bytes) || strlen($bytes) < 29) { throw new RuntimeException('Invalid mail payload.'); }
    $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', authMailKey(), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), $context);
    if (!is_string($plain)) { throw new RuntimeException('Invalid mail payload.'); }
    $decoded = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new RuntimeException('Invalid mail payload.'); }
    return $decoded;
}

function queueAuthMail(PDO $database, int $userId, string $recipient, string $kind, string $requestId, array $payload, int $expires): void
{
    $context = $kind . ':' . $userId . ':' . $requestId;
    $database->prepare('INSERT INTO auth_mail_queue(user_id,kind,request_id,recipient,payload,available_at,expires_at,created_at) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$userId, $kind, $requestId, $recipient, encryptAuthMail($payload, $context), time(), $expires, time()]);
}

function queueStudentPasswordChangedMail(PDO $database, array $account): void
{
    if ($account['role'] !== 'STUDENT' || !authMailConfigured()) { return; }
    queueAuthMail($database, (int) $account['id'], $account['email'], 'PASSWORD_CHANGED', bin2hex(random_bytes(16)), [], time() + 86400);
}

/** A CLI worker sends queued mail so SMTP latency cannot reveal which accounts exist. */
function dispatchAuthMail(PDO $database, int $limit = 20): array
{
    $settings = authMailSettings();
    require_once __DIR__ . '/../lib/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../lib/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../lib/phpmailer/src/PHPMailer.php';
    $counts = ['sent' => 0, 'cancelled' => 0, 'retry' => 0, 'failed' => 0];
    for ($i = 0; $i < min(max($limit, 1), 100); $i++) {
        $now = time();
        $database->beginTransaction();
        try {
            $query = $database->prepare("SELECT * FROM auth_mail_queue WHERE expires_at>? AND attempts<3
                AND ((status='PENDING' AND available_at<=?) OR (status='SENDING' AND claimed_until<=?)) ORDER BY id LIMIT 1 FOR UPDATE");
            $query->execute([$now, $now, $now]); $job = $query->fetch();
            if ($job === false) { $database->commit(); break; }
            $attempt = (int) $job['attempts'] + 1;
            $database->prepare("UPDATE auth_mail_queue SET status='SENDING',attempts=?,claimed_until=? WHERE id=?")->execute([$attempt, $now + 120, $job['id']]);
            $database->commit();
        } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
        try {
            $accountQuery = $database->prepare("SELECT user.id FROM users user JOIN students student ON student.user_id=user.id
                WHERE user.id=? AND user.email=? AND user.role='STUDENT' AND user.status='ACTIVE' AND student.status='ACTIVE'");
            $accountQuery->execute([$job['user_id'], $job['recipient']]); $eligible = (bool) $accountQuery->fetchColumn();
            if ($job['kind'] === 'STUDENT_RESET_CODE') {
                $current = $database->prepare('SELECT reset.user_id FROM student_password_resets reset JOIN users user ON user.id=reset.user_id
                    WHERE reset.user_id=? AND reset.request_id=? AND reset.expires_at>? AND reset.attempts<5
                    AND reset.verified_until IS NULL AND reset.auth_version=user.auth_version');
                $current->execute([$job['user_id'], $job['request_id'], time()]); $eligible = $eligible && (bool) $current->fetchColumn();
            }
            if (!$eligible) {
                $database->prepare("UPDATE auth_mail_queue SET status='CANCELLED',payload='',claimed_until=NULL WHERE id=? AND status='SENDING' AND attempts=?")->execute([$job['id'], $attempt]);
                $counts['cancelled']++; continue;
            }
            $payload = decryptAuthMail($job['payload'], $job['kind'] . ':' . $job['user_id'] . ':' . $job['request_id']);
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->Host = $settings['host']; $mail->Port = (int) $settings['port'];
            $mail->SMTPAuth = $settings['username'] !== ''; $mail->Username = $settings['username']; $mail->Password = $settings['password'];
            $mail->SMTPSecure = $settings['encryption'] === 'none' ? '' : $settings['encryption'];
            $mail->SMTPAutoTLS = $settings['encryption'] !== 'none';
            $mail->Timeout = 10; $mail->Timelimit = 20; $mail->CharSet = 'UTF-8';
            $mail->setFrom($settings['from'], $settings['name']); $mail->addAddress($job['recipient']);
            if ($job['kind'] === 'STUDENT_RESET_CODE') {
                if (!is_string($payload['code'] ?? null) || !preg_match('/\A[0-9]{6}\z/', $payload['code'])) { throw new RuntimeException('Invalid reset mail.'); }
                $mail->Subject = 'Your ClearGo password reset code';
                $mail->Body = "Your ClearGo password reset code is: " . $payload['code']
                    . "\n\nThis code expires 10 minutes after it was requested and can be used once. Enter it only in the ClearGo page where you requested it."
                    . "\n\nDo not share this code. ClearGo staff will never ask for it. If you did not request this, you can ignore this email; your password has not changed.\n";
            } else {
                $mail->Subject = 'Your ClearGo password was changed';
                $mail->Body = "Your ClearGo student account password was changed. Previous sign-ins and saved sign-ins have been revoked."
                    . "\n\nIf you did not make this change, contact the Registrar using the college's official contact details. Your password is never included in email.\n";
            }
            $mail->send();
            $database->prepare("UPDATE auth_mail_queue SET status='SENT',payload='',sent_at=?,claimed_until=NULL,last_error=NULL WHERE id=? AND status='SENDING' AND attempts=?")
                ->execute([time(), $job['id'], $attempt]); $counts['sent']++;
        } catch (Throwable $error) {
            error_log('Authentication email delivery failed for queue item #' . (int) $job['id'] . ' (' . get_class($error) . ').');
            $failed = $attempt >= 3;
            $database->prepare('UPDATE auth_mail_queue SET status=?,available_at=?,claimed_until=NULL,last_error=?,payload=CASE WHEN ?=1 THEN \'\' ELSE payload END WHERE id=? AND status=\'SENDING\' AND attempts=?')
                ->execute([$failed ? 'FAILED' : 'PENDING', time() + 30 * $attempt, 'Email delivery failed.', $failed ? 1 : 0, $job['id'], $attempt]);
            $counts[$failed ? 'failed' : 'retry']++;
        }
    }
    return $counts;
}

function cleanupAuthRecords(PDO $database): void
{
    $now = time();
    $database->prepare('DELETE FROM auth_remember_tokens WHERE expires_at<=?')->execute([$now]);
    $database->prepare('DELETE FROM student_password_resets WHERE expires_at<=? AND (verified_until IS NULL OR verified_until<=?)')->execute([$now, $now]);
    $database->prepare('DELETE FROM auth_rate_limits WHERE updated_at<?')->execute([$now - 86400]);
    $database->prepare("UPDATE auth_mail_queue SET status='CANCELLED',payload='',claimed_until=NULL WHERE expires_at<=? AND status IN ('PENDING','SENDING')")->execute([$now]);
    $database->prepare('DELETE FROM auth_mail_queue WHERE created_at<?')->execute([$now - 7 * 86400]);
}
