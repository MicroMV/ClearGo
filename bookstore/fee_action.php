<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/obligation_functions.php';

requireRole('BOOKSTORE_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo('/bookstore/fees.php');
}

try {
    $database = getDatabaseConnection();
    $periodId = (int) $database->query("SELECT id FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($periodId < 1) {
        throw new RuntimeException('There is no active semester.');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($name === '' || mb_strlen($name) > 200 || $amount === false || $amount < 0 || $amount > 99999999.99) {
            throw new RuntimeException('Enter a valid event name and amount.');
        }

        $assigned = createBookstoreEventFee($database, $periodId, $name, (float) $amount);
        setFlashMessage('Event fee added for ' . $assigned . ' enrolled student(s).');
    } elseif ($action === 'deactivate') {
        $feeId = filter_var($_POST['fee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($feeId === false) {
            throw new RuntimeException('The selected event fee is invalid.');
        }
        $closed = deactivateBookstoreEventFee($database, $periodId, (int) $feeId);
        setFlashMessage($closed ? 'Event fee closed.' : 'The event fee is no longer active.');
    } else {
        throw new RuntimeException('Invalid event fee action.');
    }
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Bookstore event fee action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to save the event fee.');
}

redirectTo('/bookstore/fees.php');
