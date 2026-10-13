<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
$user = requireRole('STUDENT');
$passwordRedirect = ($_POST['embed'] ?? '') === '1' ? '/student/password.php?embed=1' : '/student/password.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo($passwordRedirect);
}
try {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($new) < 8) {
        throw new RuntimeException('The new password must contain at least 8 characters.');
    }
    if ($new !== $confirm) {
        throw new RuntimeException('The new password confirmation does not match.');
    }
    if (hash_equals($current, $new)) {
        throw new RuntimeException('Choose a password different from your current password.');
    }
    $database = getDatabaseConnection();
    $statement = $database->prepare("SELECT user.password_hash,student.id AS student_id FROM users user INNER JOIN students student ON student.user_id=user.id WHERE user.id=:id AND user.role='STUDENT' LIMIT 1");
    $statement->execute(['id' => $user['id']]);
    $account = $statement->fetch();
    if ($account === false || !password_verify($current, $account['password_hash'])) {
        throw new RuntimeException('The current password is incorrect.');
    }
    $database->beginTransaction();
    $database->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
    $audit = $database->prepare("INSERT INTO audit_logs(user_id,action,student_id,description,new_status) VALUES(:user,'STUDENT_PASSWORD_CHANGED',:student,'Student changed their own password.','CHANGED')");
    $audit->execute(['user' => $user['id'], 'student' => $account['student_id']]);
    $database->commit();
    session_regenerate_id(true);
    setFlashMessage('Your password was changed successfully.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Student password change failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to change the password.');
}
redirectTo($passwordRedirect);
