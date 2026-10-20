<?php

declare(strict_types=1);

require_once __DIR__ . '/office_workflow.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

function officeRemarksAssignment(PDO $database, array $user): array
{
    if ($user['role'] === 'DEAN') {
        $assignment = deanAssignment($database, (int) $user['id']);
        return ['code' => 'DEAN', 'organization_id' => (int) $assignment['organization_id'], 'path' => '/dean/dashboard.php'];
    }
    if ($user['role'] === 'ORG_STAFF') {
        $assignment = assignedOrganization($database, (int) $user['id']);
        return ['code' => 'ORGANIZATION', 'organization_id' => (int) $assignment['id'], 'path' => '/organization/dashboard.php'];
    }
    return officeConfigForRole($user['role']);
}

function saveOfficeRemarks(PDO $database, array $user, int $clearanceId, string $remarks): bool
{
    $remarks = trim($remarks);
    if (mb_strlen($remarks) > 1000) {
        throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
    }
    $ownsTransaction = !$database->inTransaction();
    if ($ownsTransaction) { $database->beginTransaction(); }
    try {
        $assignment = officeRemarksAssignment($database, $user);
        $statement = $database->prepare(
            "SELECT record.id,record.office_remarks,record.status,station.id AS station_id,station.name,
                    student.id AS student_id,student.user_id,student.organization_id,
                    period.id AS period_id,period.clearance_status
             FROM clearance_stations record
             INNER JOIN stations station ON station.id=record.station_id AND station.code=:code AND station.status='ACTIVE'
             INNER JOIN clearances clearance ON clearance.id=record.clearance_id
             INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
             INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
             INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
                 AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
             WHERE clearance.id=:id"
        );
        $statement->execute(['code' => $assignment['code'], 'id' => $clearanceId]);
        $context = $statement->fetch();
        if (!$context || (isset($assignment['organization_id']) && (int) $context['organization_id'] !== $assignment['organization_id'])) {
            throw new RuntimeException('This student is not assigned to your office.');
        }
        if (!in_array($context['clearance_status'], ['PREPARING', 'OPEN'], true)) {
            throw new RuntimeException('Remarks cannot be changed for a closed semester.');
        }
        $changed = (string) $context['office_remarks'] !== $remarks;
        if ($changed && $context['status'] === 'APPROVED') {
            throw new RuntimeException('Remarks cannot be changed after this office has cleared the student.');
        }
        if ($changed) {
            $update = $database->prepare("UPDATE clearance_stations SET office_remarks=:remarks WHERE id=:id AND status<>'APPROVED'");
            $update->execute(['remarks' => $remarks === '' ? null : $remarks, 'id' => $context['id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The record changed. Refresh the dashboard before editing remarks.');
            }
            $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,old_status,new_status)
                VALUES (:user_id,'OFFICE_REMARKS_UPDATED',:station_id,:student_id,:description,:old_status,:new_status)");
            $audit->execute(['user_id' => $user['id'], 'station_id' => $context['station_id'], 'student_id' => $context['student_id'],
                'description' => $context['name'] . ' remarks ' . ($remarks === '' ? 'removed.' : 'updated.'),
                'old_status' => $context['status'], 'new_status' => $context['status']]);
            if ($remarks !== '' && $context['clearance_status'] === 'OPEN') {
                createNotification($database, (int) $context['user_id'], $context['name'] . ' remarks', $remarks, (int) $context['period_id']);
            }
        }
        if ($ownsTransaction) { $database->commit(); }
        return $changed;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function renderOfficeRemarksButton(int $clearanceId, string $remarks, string $studentName, bool $canEdit): string
{
    $label = trim($remarks) === '' ? 'Add remarks' : 'Edit remarks';
    return '<div class="office-remarks-cell"><button type="button" class="secondary" data-office-remarks-open'
        . ' data-clearance-id="' . $clearanceId . '" data-remarks="' . escapeHtml($remarks) . '" data-student-name="' . escapeHtml($studentName) . '"'
        . ' aria-haspopup="dialog" aria-controls="office-remarks-modal"'
        . (!$canEdit || $clearanceId < 1 ? ' disabled title="Remarks are read-only for cleared students or closed semesters."' : '')
        . '>' . $label . '</button>'
        . ($remarks === '' ? '' : '<p class="office-remarks-preview">' . nl2br(escapeHtml($remarks)) . '</p>') . '</div>';
}

function renderOfficeRemarksModal(array $query = []): string
{
    $html = '<dialog class="office-remarks-modal" id="office-remarks-modal" aria-labelledby="office-remarks-title">'
        . '<div class="office-remarks-heading"><h2 id="office-remarks-title">Add remarks</h2>'
        . '<button type="button" class="office-remarks-close" data-office-remarks-close aria-label="Close remarks">&times;</button></div>'
        . '<p class="office-remarks-student" id="office-remarks-student"></p>';
    $html .= '<form class="office-remarks-form" method="post" action="' . escapeHtml(applicationUrl('/clearance/remarks_action.php')) . '">'
        . '<input type="hidden" name="csrf_token" value="' . escapeHtml(createCsrfToken()) . '">'
        . '<input type="hidden" name="clearance_id" value="">';
    foreach (['search', 'course', 'year_level', 'filter', 'group', 'audience', 'department', 'page'] as $key) {
        if (isset($query[$key]) && is_scalar($query[$key])) {
            $html .= '<input type="hidden" name="return_query[' . $key . ']" value="' . escapeHtml((string) $query[$key]) . '">';
        }
    }
    return $html . '<label for="office-remarks-text">Remarks</label>'
        . '<textarea id="office-remarks-text" name="remarks" rows="6" maxlength="1000" placeholder="Write remarks for the student" aria-describedby="office-remarks-help"></textarea>'
        . '<small id="office-remarks-help">Visible to the student. Maximum 1,000 characters.</small>'
        . '<div class="office-remarks-actions"><button type="button" class="secondary" data-office-remarks-close>Cancel</button>'
        . '<button type="submit">Save remarks</button></div></form></dialog>';
}

/** Import a remarks cell independently of the student's clearance decision. Old templates may omit it. */
function saveWorkbookOfficeRemarks(PDO $database, int $staffUserId, string $role, string $studentNumber, ?string $remarks): bool
{
    if ($remarks === null) { return false; }
    $statement = $database->prepare("SELECT clearance.id FROM clearances clearance
        INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
        INNER JOIN students student ON student.id=clearance.student_id AND student.student_number=:number
        WHERE student.status='ACTIVE' LIMIT 1");
    $statement->execute(['number' => $studentNumber]);
    $id = $statement->fetchColumn();
    if ($id === false) { throw new RuntimeException($studentNumber . ': no current-semester clearance was found.'); }
    return saveOfficeRemarks($database, ['id' => $staffUserId, 'role' => $role], (int) $id, $remarks);
}
