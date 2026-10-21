<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/password_recovery_functions.php';
$user = requireRole('STUDENT');
$passwordRedirect = ($_POST['embed'] ?? '') === '1' ? '/student/password.php?embed=1' : '/student/password.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo($passwordRedirect);
}
try {
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = validateNewStudentPassword($_POST['new_password'] ?? null, $_POST['confirm_password'] ?? null);
    if (hash_equals($current, $new)) {
        throw new RuntimeException('Choose a password different from your current password.');
    }
    $database = getDatabaseConnection();
    $database->beginTransaction();
    $statement = $database->prepare("SELECT user.password_hash,student.id AS student_id FROM users user INNER JOIN students student ON student.user_id=user.id WHERE user.id=:id AND user.role='STUDENT' LIMIT 1 FOR UPDATE");
    $statement->execute(['id' => $user['id']]);
    $account = $statement->fetch();
    if ($account === false || !password_verify($current, $account['password_hash'])) {
        throw new RuntimeException('The current password is incorrect.');
    }
    $version = replaceAccountPassword($database, $user['id'], password_hash($new, PASSWORD_DEFAULT));
    queueStudentPasswordChangedMail($database, $user);
    $audit = $database->prepare("INSERT INTO audit_logs(user_id,action,student_id,description,new_status) VALUES(:user,'STUDENT_PASSWORD_CHANGED',:student,'Student changed their own password.','CHANGED')");
    $audit->execute(['user' => $user['id'], 'student' => $account['student_id']]);
    $database->commit();
    $_SESSION['authenticated_user']['auth_version'] = $version;
    setRememberCookie(null);
    session_regenerate_id(true);
    setFlashMessage('Your password was changed successfully.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Student password change failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to change the password.');
}
redirectTo($passwordRedirect);
