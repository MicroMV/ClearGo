<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403); exit('Forbidden');
}
require_once __DIR__ . '/mail_functions.php';

const RESET_REQUEST_MESSAGE = 'If this is an active student account, a reset code will be sent to its school email. Check your inbox and spam folder.';

/** The same request context is returned for students, staff, inactive and unknown addresses. */
function requestStudentPasswordReset(PDO $database, string $email, string $binding): string
{
    $requestId = bin2hex(random_bytes(16)); $code = (string) random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT); $now = time();
    $database->beginTransaction();
    try {
        $query = $database->prepare("SELECT user.id,user.email,user.auth_version FROM users user JOIN students student ON student.user_id=user.id
            WHERE user.email=? AND user.role='STUDENT' AND user.status='ACTIVE' AND student.status='ACTIVE' LIMIT 1 FOR UPDATE");
        $query->execute([$email]); $account = $query->fetch();
        if ($account !== false) {
            $database->prepare("UPDATE auth_mail_queue SET status='CANCELLED',payload='' WHERE user_id=? AND kind='STUDENT_RESET_CODE' AND status IN ('PENDING','SENDING')")->execute([$account['id']]);
            $database->prepare('INSERT INTO student_password_resets(user_id,request_id,code_hash,binding_hash,auth_version,attempts,requested_at,expires_at,verified_until,reset_token_hash)
                VALUES(?,?,?,?,?,0,?,?,NULL,NULL) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),code_hash=VALUES(code_hash),binding_hash=VALUES(binding_hash),
                auth_version=VALUES(auth_version),attempts=0,requested_at=VALUES(requested_at),expires_at=VALUES(expires_at),verified_until=NULL,reset_token_hash=NULL')
                ->execute([$account['id'], $requestId, $codeHash, hash('sha256', $binding), $account['auth_version'], $now, $now + 600]);
            queueAuthMail($database, (int) $account['id'], $account['email'], 'STUDENT_RESET_CODE', $requestId, ['code' => $code], $now + 600);
        }
        $database->commit(); return $requestId;
    } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
}

/** Lock user first, then reset row, consistently with password replacement and resend. */
function lockedStudentReset(PDO $database, string $requestId): ?array
{
    $find = $database->prepare('SELECT user_id FROM student_password_resets WHERE request_id=?'); $find->execute([$requestId]);
    $id = $find->fetchColumn(); if ($id === false) { return null; }
    $user = $database->prepare("SELECT user.id,user.email,user.role,user.status,user.auth_version,student.id AS student_id,student.status AS student_status
        FROM users user JOIN students student ON student.user_id=user.id WHERE user.id=? FOR UPDATE");
    $user->execute([$id]); $account = $user->fetch();
    if ($account === false || $account['role'] !== 'STUDENT' || $account['status'] !== 'ACTIVE' || $account['student_status'] !== 'ACTIVE') { return null; }
    $reset = $database->prepare('SELECT * FROM student_password_resets WHERE user_id=? AND request_id=? FOR UPDATE');
    $reset->execute([$id, $requestId]); $row = $reset->fetch();
    if ($row === false || (int) $row['auth_version'] !== (int) $account['auth_version']) { return null; }
    return ['account' => $account, 'reset' => $row];
}

/** A successful code creates a short-lived reset grant bound to this browser session. */
function verifyStudentResetCode(PDO $database, string $requestId, string $binding, string $code): ?string
{
    $database->beginTransaction();
    try {
        $found = lockedStudentReset($database, $requestId); $grant = null;
        if ($found !== null) {
            $row = $found['reset'];
            if (hash_equals($row['binding_hash'], hash('sha256', $binding)) && (int) $row['expires_at'] > time()
                && (int) $row['attempts'] < 5 && $row['verified_until'] === null) {
                $valid = preg_match('/\A[0-9]{6}\z/', $code) && password_verify($code, $row['code_hash']);
                if ($valid) {
                    $grant = bin2hex(random_bytes(32));
                    $database->prepare("UPDATE student_password_resets SET code_hash='',verified_until=?,reset_token_hash=? WHERE user_id=?")
                        ->execute([time() + 600, hash('sha256', $grant), $row['user_id']]);
                    $database->prepare("UPDATE auth_mail_queue SET status='CANCELLED',payload='' WHERE user_id=? AND kind='STUDENT_RESET_CODE' AND status IN ('PENDING','SENDING')")->execute([$row['user_id']]);
                } else {
                    $database->prepare('UPDATE student_password_resets SET attempts=attempts+1 WHERE user_id=?')->execute([$row['user_id']]);
                }
            }
        }
        $database->commit(); return $grant;
    } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
}

function validateNewStudentPassword(mixed $password, mixed $confirmation): string
{
    if (!is_string($password) || !is_string($confirmation) || strlen($password) < 8 || strlen($password) > 72
        || str_contains($password, "\0")) { throw new InvalidArgumentException('Use a password with 8 to 72 characters.'); }
    if (!hash_equals($password, $confirmation)) { throw new InvalidArgumentException('The password confirmation does not match.'); }
    return $password;
}

function completeStudentPasswordReset(PDO $database, string $requestId, string $binding, string $grant, string $password): bool
{
    validateNewStudentPassword($password, $password);
    // Compute outside the transaction so password hashing does not hold record locks.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $database->beginTransaction();
    try {
        $found = lockedStudentReset($database, $requestId);
        if ($found === null || !hash_equals($found['reset']['binding_hash'], hash('sha256', $binding))
            || (int) $found['reset']['verified_until'] <= time() || !is_string($found['reset']['reset_token_hash'])
            || !hash_equals($found['reset']['reset_token_hash'], hash('sha256', $grant))) {
            $database->rollBack(); return false;
        }
        $account = $found['account'];
        replaceAccountPassword($database, (int) $account['id'], $hash);
        clearLoginAccountLimit($database, $account['email']);
        $database->prepare("INSERT INTO audit_logs(user_id,student_id,action,description,new_status) VALUES(?,?,'STUDENT_EMAIL_PASSWORD_RESET','Student reset their password using a verified email code.','RESET')")
            ->execute([$account['id'], $account['student_id']]);
        queueStudentPasswordChangedMail($database, $account);
        $database->commit(); return true;
    } catch (Throwable $error) { if ($database->inTransaction()) { $database->rollBack(); } throw $error; }
}
