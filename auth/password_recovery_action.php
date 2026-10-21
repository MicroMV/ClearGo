<?php
declare(strict_types=1);
require_once __DIR__ . '/password_recovery_functions.php';
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirectTo('/login.php?recovery=1'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.'); redirectTo('/login.php?recovery=1');
}
if (($signedIn = authenticatedUser()) !== null) { redirectToDashboard($signedIn['role']); }
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$flow = $_SESSION['student_password_recovery'] ?? null;
try {
    if ($action === 'start_over') { unset($_SESSION['student_password_recovery']); redirectTo('/login.php?recovery=1'); }
    $database = getDatabaseConnection();
    if ($action === 'request' || $action === 'resend') {
        $email = normalizedSchoolEmail($action === 'resend' ? ($flow['email'] ?? null) : ($_POST['email'] ?? null));
        if ($email === '') { throw new InvalidArgumentException('Enter a valid Lewis College school email.'); }
        if (!authMailConfigured()) { throw new InvalidArgumentException('Email recovery is not available yet. Please contact the Registrar for help.'); }
        $retry = consumeAuthLimits($database, [['reset-send-ip', authClientAddress(), authLimitSetting('AUTH_RESET_IP_LIMIT', 300), 3600],
            ['reset-send-browser', session_id(), 10, 3600],
            ['reset-send-account', $email, 3, 3600], ['reset-resend', $email, 1, 60]]);
        if ($retry > 0) { displayAuthRateLimit('/login.php?recovery=1', $retry); }
        $binding = bin2hex(random_bytes(32)); $requestId = requestStudentPasswordReset($database, $email, $binding);
        $_SESSION['student_password_recovery'] = ['email' => $email, 'binding' => $binding, 'request_id' => $requestId, 'created_at' => time()];
        setFlashMessage(RESET_REQUEST_MESSAGE);
    } elseif ($action === 'verify') {
        $retry = consumeAuthLimits($database, [['reset-verify-ip', authClientAddress(), 1000, 900], ['reset-verify-browser', session_id(), 30, 900]]);
        if ($retry > 0) { displayAuthRateLimit('/login.php?recovery=1', $retry); }
        $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
        $grant = is_array($flow) && is_string($flow['request_id'] ?? null) && is_string($flow['binding'] ?? null)
            ? verifyStudentResetCode($database, $flow['request_id'], $flow['binding'], $code) : null;
        if ($grant === null) { throw new InvalidArgumentException('The code is invalid or expired. Request a new code if needed.'); }
        session_regenerate_id(true); unset($_SESSION['csrf_token']);
        $_SESSION['student_password_recovery']['grant'] = $grant;
        setFlashMessage('Code verified. You can now choose a new password.');
    } elseif ($action === 'reset') {
        $retry = consumeAuthLimits($database, [['reset-complete-ip', authClientAddress(), 1000, 900], ['reset-complete-browser', session_id(), 30, 900]]);
        if ($retry > 0) { displayAuthRateLimit('/login.php?recovery=1', $retry); }
        $password = validateNewStudentPassword($_POST['new_password'] ?? null, $_POST['confirm_password'] ?? null);
        $valid = is_array($flow) && is_string($flow['request_id'] ?? null) && is_string($flow['binding'] ?? null) && is_string($flow['grant'] ?? null)
            && completeStudentPasswordReset($database, $flow['request_id'], $flow['binding'], $flow['grant'], $password);
        if (!$valid) { unset($_SESSION['student_password_recovery']); throw new InvalidArgumentException('Your reset session expired. Please request a new code.'); }
        $_SESSION = []; session_regenerate_id(true); setRememberCookie(null);
        setFlashMessage('Your password was reset. Sign in with your new password.'); redirectTo('/login.php');
    } else { throw new InvalidArgumentException('Select a valid password recovery action.'); }
} catch (InvalidArgumentException $error) {
    setFlashMessage($error->getMessage());
} catch (Throwable $error) {
    if (isset($database) && $database->inTransaction()) { $database->rollBack(); }
    error_log('Student email password recovery failed (' . get_class($error) . ').');
    setFlashMessage('Password recovery is temporarily unavailable. Please try again later.');
}
redirectTo('/login.php?recovery=1');
