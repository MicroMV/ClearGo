<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/office_workflow.php';

$user = requireRole('LIBRARY_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that library card review.');
    redirectTo('/library/submissions.php');
}

try {
    $submissionId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($submissionId === false || !in_array($action, ['verify', 'return'], true)) {
        throw new RuntimeException('Invalid library card review request.');
    }
    if ($action === 'return' && ($reason === '' || mb_strlen($reason) > 1000)) {
        throw new RuntimeException('Provide a reason for returning the library card photo.');
    }

    $database = getDatabaseConnection();
    $database->beginTransaction();
    $query = $database->prepare(
        "SELECT submission.library_card_id,card.student_id,student.user_id,clearance.id AS clearance_id
         FROM library_card_submissions submission
         INNER JOIN library_cards card ON card.id=submission.library_card_id
         INNER JOIN students student ON student.id=card.student_id
         INNER JOIN academic_periods period ON period.status='ACTIVE' AND period.clearance_status IN ('PREPARING','OPEN')
         INNER JOIN clearances clearance ON clearance.student_id=student.id AND clearance.academic_period_id=period.id
         WHERE submission.id=:id AND submission.status='SUBMITTED' FOR UPDATE"
    );
    $query->execute(['id' => $submissionId]);
    $submission = $query->fetch();
    if ($submission === false) {
        throw new RuntimeException('This library card is no longer awaiting review.');
    }

    $context = officeClearanceContext($database, 'LIBRARY', (int) $submission['clearance_id'], true);
    if ($context['station_status'] === 'LOCKED') {
        throw new RuntimeException('The student has not reached the Library step yet.');
    }

    if ($action === 'verify') {
        $database->prepare("UPDATE library_card_submissions SET status='VERIFIED',reviewed_at=NOW(),reviewed_by=:user_id,return_reason=NULL WHERE id=:id")
            ->execute(['user_id' => $user['id'], 'id' => $submissionId]);
        $database->prepare("UPDATE library_cards SET status='VERIFIED',verified_by=:user_id,verified_at=NOW() WHERE id=:id")
            ->execute(['user_id' => $user['id'], 'id' => $submission['library_card_id']]);

        if ($context['station_status'] !== 'APPROVED') {
            $database->prepare("UPDATE clearance_stations SET status='APPROVED',approved_by=:user_id,approved_at=NOW(),returned_by=NULL,returned_at=NULL,returned_reason=NULL WHERE id=:id")
                ->execute(['user_id' => $user['id'], 'id' => $context['clearance_station_id']]);
            $database->prepare("UPDATE requirement_records SET status='VERIFIED',verified_by=:user_id,verified_at=NOW(),remarks=NULL WHERE clearance_station_id=:id AND status NOT IN ('VERIFIED','RESOLVED')")
                ->execute(['user_id' => $user['id'], 'id' => $context['clearance_station_id']]);
            unlockAfterOfficeApproval($database, 'LIBRARY', (int) $submission['clearance_id']);
            notifyAndAuditOffice($database, $context, (int) $user['id'], 'LIBRARY_CARD_CONFIRMED', 'The Library confirmed your captured library card and cleared your Library requirement.', $context['station_status'], 'APPROVED');
        }
        setFlashMessage('Library card confirmed and the student was cleared.');
    } else {
        $database->prepare("UPDATE library_card_submissions SET status='RETURNED',reviewed_at=NOW(),reviewed_by=:user_id,return_reason=:reason WHERE id=:id")
            ->execute(['user_id' => $user['id'], 'reason' => $reason, 'id' => $submissionId]);
        $database->prepare("UPDATE library_cards SET status='INVALID',verified_by=:user_id,verified_at=NOW() WHERE id=:id")
            ->execute(['user_id' => $user['id'], 'id' => $submission['library_card_id']]);
        $database->prepare("UPDATE clearance_stations SET status='RETURNED',approved_by=NULL,approved_at=NULL,returned_by=:user_id,returned_at=NOW(),returned_reason=:reason WHERE id=:id AND status<>'APPROVED'")
            ->execute(['user_id' => $user['id'], 'reason' => $reason, 'id' => $context['clearance_station_id']]);
        notifyAndAuditOffice($database, $context, (int) $user['id'], 'LIBRARY_CARD_RETURNED', 'Your library card photo was returned: ' . $reason, $context['station_status'], 'RETURNED');
        setFlashMessage('Library card returned to the student.');
    }
    $database->commit();
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Library card review failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review the library card.');
}

redirectTo('/library/submissions.php');
