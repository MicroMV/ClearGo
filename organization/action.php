<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/clearance_functions.php';

$user = requireRole('ORG_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/organization/dashboard.php');
}

try {
    $database = getDatabaseConnection();
    $organization = assignedOrganization($database, $user['id']);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'return') {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($studentId === false || $reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Provide a valid student and return reason.');
        }
        returnOrganizationStudent($database, $user['id'], $organization['id'], $studentId, $reason);
        setFlashMessage('Student clearance returned.');
    } else {
        $studentIds = $action === 'approve_all' ? [] : ($_POST['student_ids'] ?? []);
        if ($action !== 'approve_all' && (!is_array($studentIds) || $studentIds === [])) {
            throw new RuntimeException('Select at least one student.');
        }
        if ($action === 'approve_all') {
            $statement = $database->prepare('SELECT id FROM students WHERE organization_id = :organization_id AND status = :status');
            $statement->execute(['organization_id' => $organization['id'], 'status' => 'ACTIVE']);
            $studentIds = array_column($statement->fetchAll(), 'id');
        }
        $approved = 0;
        $notApproved = 0;
        foreach (array_unique($studentIds) as $studentId) {
            if (filter_var($studentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $notApproved++;
                continue;
            }
            try {
                approveOrganizationStudent($database, $user['id'], $organization['id'], (int) $studentId);
                $approved++;
            } catch (RuntimeException $exception) {
                $notApproved++;
            }
        }
        setFlashMessage("Approved: {$approved}. Not approved: {$notApproved}.");
    }
} catch (Throwable $exception) {
    error_log('Organization clearance action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to process that request.');
}

redirectTo('/organization/dashboard.php');
