<?php

declare(strict_types=1);

require_once __DIR__ . '/signature_functions.php';
require_once __DIR__ . '/../config/database.php';

$user = requireLogin();
if ($user['role'] === 'STUDENT') {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/auth/profile.php');
}
try {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'select') {
        $signatureId = filter_var($_POST['signature_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($signatureId === false) { throw new RuntimeException('Choose one of your saved signatures.'); }
        selectStaffSignature(getDatabaseConnection(), $user, $signatureId);
        setFlashMessage('Signature selected for future clearance approvals.');
    } elseif ($action === 'save') {
        saveStaffSignature(
            getDatabaseConnection(),
            $user,
            is_string($_POST['printed_name'] ?? null) ? $_POST['printed_name'] : '',
            is_string($_POST['position_title'] ?? null) ? $_POST['position_title'] : '',
            $_POST['signature_drawing'] ?? null
        );
        setFlashMessage('Signature saved. Use the saved signature cards to choose which signature to use.');
    } else {
        throw new RuntimeException('Choose a valid signature action.');
    }
} catch (Throwable $exception) {
    error_log('Authorized signature update failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException && !$exception instanceof PDOException
        ? $exception->getMessage() : 'Unable to update the authorized signature. Please try again.');
}
redirectTo('/auth/profile.php');
