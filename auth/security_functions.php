<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403); exit('Forbidden');
}
require_once __DIR__ . '/auth_check.php';

const REMEMBER_COOKIE = 'clear_go_remember';
const REMEMBER_LIFETIME = 30 * 86400;

function displayAuthRateLimit(string $authReturnPath, int $authRetryAfter): never
{
    http_response_code(429); header('Retry-After: ' . max(1, $authRetryAfter)); header('Cache-Control: no-store');
    require __DIR__ . '/rate_limited.php'; exit;
}

function normalizedSchoolEmail(mixed $value): string
{
    if (!is_string($value)) { return ''; }
    $email = strtolower(trim($value));
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)
        && str_ends_with($email, '@thelewiscollege.edu.ph') ? $email : '';
}

/** Use the trusted web-server client address, never an arbitrary forwarded header. */
function authClientAddress(): string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) ? $address : 'unknown';
}

function authLimitSetting(string $name, int $default): int
{
    $value = filter_var(appSetting($name, (string) $default), FILTER_VALIDATE_INT);
    return $value !== false && $value >= 1 && $value <= 100000 ? $value : $default;
}

/** Atomically reserve attempts across account and IP buckets; returns seconds to retry. */
function consumeAuthLimits(PDO $database, array $rules, ?int $now = null): int
{
    $now ??= time();
    if ($database->inTransaction()) { throw new LogicException('Rate limiting requires its own transaction.'); }
    $buckets = [];
    foreach ($rules as [$scope, $identity, $limit, $window]) {
        $buckets[hash('sha256', $scope . ':' . $identity)] = [(int) $limit, (int) $window];
    }
    ksort($buckets);
    $database->beginTransaction();
    try {
        $states = []; $retry = 0;
        foreach ($buckets as $key => [$limit, $window]) {
            // A no-op upsert obtains an exclusive lock. INSERT IGNORE would obtain a shared
            // duplicate-key lock that can deadlock when simultaneous requests upgrade it.
            $database->prepare('INSERT INTO auth_rate_limits(bucket_key,attempts,window_started,updated_at) VALUES(?,0,?,?) ON DUPLICATE KEY UPDATE bucket_key=VALUES(bucket_key)')->execute([$key, $now, $now]);
            $query = $database->prepare('SELECT attempts,window_started FROM auth_rate_limits WHERE bucket_key=? FOR UPDATE');
            $query->execute([$key]); $row = $query->fetch();
            $start = (int) $row['window_started']; $count = (int) $row['attempts'];
            if ($now - $start >= $window) { $start = $now; $count = 0; }
            if ($count >= $limit) { $retry = max($retry, $start + $window - $now, 1); }
            $states[$key] = [$count, $start];
        }
        if ($retry === 0) {
            $update = $database->prepare('UPDATE auth_rate_limits SET attempts=?,window_started=?,updated_at=? WHERE bucket_key=?');
            foreach ($states as $key => [$count, $start]) { $update->execute([$count + 1, $start, $now, $key]); }
        }
        $database->commit(); return $retry;
    } catch (Throwable $error) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $error;
    }
}

function clearLoginAccountLimit(PDO $database, string $email): void
{
    $database->prepare('DELETE FROM auth_rate_limits WHERE bucket_key=?')->execute([hash('sha256', 'login-account:' . $email)]);
}

function establishAuthenticatedSession(array $account): void
{
    startSecureSession(); session_regenerate_id(true);
    $_SESSION['authenticated_user'] = ['id' => (int) $account['id'], 'email' => (string) $account['email'],
        'role' => (string) $account['role'], 'auth_version' => (int) $account['auth_version']];
    unset($_SESSION['csrf_token'], $_SESSION['student_password_recovery']);
}

function authSessionVersionMatches(array $sessionAccount, array $currentAccount): bool
{
    // Existing sessions from before migration 037 are version 1, and fail after any password change.
    return (int) ($sessionAccount['auth_version'] ?? 1) === (int) $currentAccount['auth_version'];
}

function setRememberCookie(?string $value, int $expires = 0): void
{
    setcookie(REMEMBER_COOKIE, $value ?? '', ['expires' => $value === null ? time() - 3600 : $expires,
        'path' => rtrim(applicationUrl('/'), '/') ?: '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    if ($value === null) { unset($_COOKIE[REMEMBER_COOKIE]); }
    else { $_COOKIE[REMEMBER_COOKIE] = $value; }
}

function rememberCookieParts(mixed $cookie): ?array
{
    return is_string($cookie) && preg_match('/\A([a-f0-9]{24})\.([a-f0-9]{64})\z/', $cookie, $match)
        ? [$match[1], $match[2]] : null;
}

function revokeCurrentRememberToken(PDO $database): void
{
    $parts = rememberCookieParts($_COOKIE[REMEMBER_COOKIE] ?? null);
    if ($parts !== null) { $database->prepare('DELETE FROM auth_remember_tokens WHERE selector=?')->execute([$parts[0]]); }
    setRememberCookie(null);
}

/** Saves a random verifier hash, never a password or reusable plaintext token. */
function issueRememberToken(PDO $database, array $account): string
{
    $selector = bin2hex(random_bytes(12)); $validator = bin2hex(random_bytes(32)); $now = time();
    $database->beginTransaction();
    try {
        $lock = $database->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->execute([$account['id']]);
        $database->prepare('DELETE FROM auth_remember_tokens WHERE user_id=? AND (expires_at<=? OR auth_version<>?)')->execute([$account['id'], $now, $account['auth_version']]);
        $old = $database->prepare('SELECT selector FROM auth_remember_tokens WHERE user_id=? ORDER BY created_at DESC,selector DESC LIMIT 4,1000');
        $old->execute([$account['id']]);
        foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $key) { $database->prepare('DELETE FROM auth_remember_tokens WHERE selector=?')->execute([$key]); }
        $database->prepare('INSERT INTO auth_remember_tokens(selector,user_id,validator_hash,auth_version,expires_at,created_at,last_used_at) VALUES(?,?,?,?,?,?,?)')
            ->execute([$selector, $account['id'], hash('sha256', $validator), $account['auth_version'], $now + REMEMBER_LIFETIME, $now, $now]);
        $database->commit();
    } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
    $value = $selector . '.' . $validator; setRememberCookie($value, $now + REMEMBER_LIFETIME); return $value;
}

/** Rotates tokens; a brief previous-verifier grace handles simultaneous first-page requests. */
function consumeRememberToken(PDO $database, string $cookie, ?int $now = null): ?array
{
    $parts = rememberCookieParts($cookie); if ($parts === null) { return null; }
    $now ??= time(); $digest = hash('sha256', $parts[1]);
    $database->beginTransaction();
    try {
        $query = $database->prepare('SELECT token.*,user.email,user.role,user.status,user.auth_version AS current_version FROM auth_remember_tokens token JOIN users user ON user.id=token.user_id WHERE token.selector=? FOR UPDATE');
        $query->execute([$parts[0]]); $row = $query->fetch(); $valid = false; $previous = false;
        if ($row !== false && $row['status'] === 'ACTIVE' && in_array($row['role'], APPLICATION_ROLES, true)
            && (int) $row['expires_at'] > $now && (int) $row['auth_version'] === (int) $row['current_version']) {
            $valid = hash_equals($row['validator_hash'], $digest);
            $previous = !$valid && is_string($row['previous_validator_hash']) && (int) $row['previous_valid_until'] >= $now
                && hash_equals($row['previous_validator_hash'], $digest);
        }
        if (!$valid && !$previous) {
            $database->prepare('DELETE FROM auth_remember_tokens WHERE selector=?')->execute([$parts[0]]);
            $database->commit(); return null;
        }
        $next = null;
        if ($valid) {
            $next = bin2hex(random_bytes(32));
            $database->prepare('UPDATE auth_remember_tokens SET previous_validator_hash=validator_hash,previous_valid_until=?,validator_hash=?,last_used_at=? WHERE selector=?')
                ->execute([$now + 30, hash('sha256', $next), $now, $parts[0]]);
        }
        $database->commit();
        if ($next !== null) { setRememberCookie($parts[0] . '.' . $next, (int) $row['expires_at']); }
        return ['id' => (int) $row['user_id'], 'email' => $row['email'], 'role' => $row['role'], 'auth_version' => (int) $row['current_version']];
    } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
}

function restoreRememberedAccount(): ?array
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? null;
    if ($cookie === null) { return null; }
    if (rememberCookieParts($cookie) === null) { setRememberCookie(null); return null; }
    require_once __DIR__ . '/../config/database.php';
    $account = consumeRememberToken(getDatabaseConnection(), $cookie);
    if ($account === null) { setRememberCookie(null); return null; }
    establishAuthenticatedSession($account); return $_SESSION['authenticated_user'];
}

/** Password changes invalidate old sessions, remembered devices and pending reset codes atomically. */
function replaceAccountPassword(PDO $database, int $userId, string $hash): int
{
    $ownTransaction = !$database->inTransaction();
    if ($ownTransaction) { $database->beginTransaction(); }
    try {
        $database->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([$hash, $userId]);
        $database->prepare('DELETE FROM auth_remember_tokens WHERE user_id=?')->execute([$userId]);
        $database->prepare('DELETE FROM student_password_resets WHERE user_id=?')->execute([$userId]);
        $database->prepare("UPDATE auth_mail_queue SET status='CANCELLED',payload='' WHERE user_id=? AND kind='STUDENT_RESET_CODE' AND status IN ('PENDING','SENDING')")->execute([$userId]);
        $query = $database->prepare('SELECT auth_version FROM users WHERE id=?'); $query->execute([$userId]); $version = (int) $query->fetchColumn();
        if ($ownTransaction) { $database->commit(); } return $version;
    } catch (Throwable $error) { if ($ownTransaction && $database->inTransaction()) { $database->rollBack(); } throw $error; }
}
