<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
$user = requireRole('DEAN');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that review.');
    redirectTo('/dean/submitted_requirements.php');
}
try {
    $database = getDatabaseConnection();
    $dean = deanAssignment($database, (int) $user['id']);
    if ($dean['organization_code'] !== 'CCS') {
        throw new RuntimeException('Only the CCS Dean can review these submissions.');
    }
    $action = $_POST['action'] ?? '';
    $submissionId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$submissionId || !in_array($action, ['verify', 'return'], true)) {
        throw new RuntimeException('Invalid review request.');
    }
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($action === 'return' && $reason === '') {
        throw new RuntimeException('Provide a reason for returning the screenshots.');
    }
    $database->beginTransaction();
    $submission = $database->prepare("SELECT submission.clearance_id, clearance.academic_period_id, student.user_id, station.id AS station_id FROM evaluation_submissions submission INNER JOIN clearances clearance ON clearance.id=submission.clearance_id INNER JOIN students student ON student.id=clearance.student_id AND student.organization_id=:organization_id INNER JOIN stations station ON station.code='DEAN' WHERE submission.id=:id AND submission.status='SUBMITTED' FOR UPDATE");
    $submission->execute(['organization_id' => $dean['organization_id'], 'id' => $submissionId]);
    $row = $submission->fetch();
    if (!$row) {
        throw new RuntimeException('This submission is no longer awaiting review.');
    }
    $status = $action === 'verify' ? 'VERIFIED' : 'RETURNED';
    $database->prepare('UPDATE evaluation_submissions SET status=:status,reviewed_at=NOW(),reviewed_by=:user_id,return_reason=:reason WHERE id=:id')->execute(['status' => $status, 'user_id' => $user['id'], 'reason' => $action === 'return' ? $reason : null, 'id' => $submissionId]);
    $recordStatus = $action === 'verify' ? 'VERIFIED' : 'RETURNED';
    $database->prepare("UPDATE requirement_records record INNER JOIN clearance_stations clearance_station ON clearance_station.id=record.clearance_station_id INNER JOIN requirements requirement ON requirement.id=record.requirement_id SET record.status=:status,record.remarks=:remarks,record.verified_by=:user_id,record.verified_at=NOW() WHERE clearance_station.clearance_id=:clearance_id AND requirement.requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')")->execute(['status' => $recordStatus, 'remarks' => $action === 'return' ? $reason : null, 'user_id' => $user['id'], 'clearance_id' => $row['clearance_id']]);
    $title = $action === 'verify' ? 'Faculty Evaluation verified' : 'Faculty Evaluation screenshots returned';
    $message = $action === 'verify' ? 'Your Faculty Evaluation screenshots were verified by the CCS Dean.' : $reason;
    createNotification($database, (int) $row['user_id'], $title, $message, (int) $row['academic_period_id']);
    $database->commit();
    setFlashMessage($action === 'verify' ? 'Submission verified.' : 'Submission returned to the student.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Evaluation submission review failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review the submission.');
}
redirectTo('/dean/submitted_requirements.php');
