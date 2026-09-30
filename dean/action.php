<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';

$user = requireRole('DEAN');
$returnGroup = strtoupper((string) ($_POST['return_group'] ?? ''));
$returnGroup = in_array($returnGroup, ['NON_GRADUATING', 'FOURTH_YEAR'], true) ? $returnGroup : '';
$returnPath = '/dean/dashboard.php' . ($returnGroup !== '' ? '?group=' . urlencode($returnGroup) : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo($returnPath);
}

$action = (string) ($_POST['action'] ?? 'approve');
$isBulkAction = in_array($action, ['approve_all_cbe_group', 'approve_all_ready_ccs'], true);
$clearanceId = $isBulkAction
    ? null
    : filter_var($_POST['clearance_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$isBulkAction && $clearanceId === false) {
    setFlashMessage('Select a valid clearance application.');
    redirectTo($returnPath);
}

try {
    $database = getDatabaseConnection();
    $dean = deanAssignment($database, (int) $user['id']);
    if ($action === 'approve_all_cbe_group') {
        if ($returnGroup === '') {
            throw new RuntimeException('Select a valid CBE student group.');
        }
        $result = approveAllReadyCbeDeanStudents($database, (int) $user['id'], $returnGroup);
        setFlashMessage('Dean approvals completed: ' . $result['approved'] . '. Skipped: ' . $result['skipped'] . '.');
    } elseif ($action === 'approve_all_ready_ccs') {
        if ($dean['organization_code'] !== 'CCS') {
            throw new RuntimeException('Bulk clearance is only available to the CCS Dean.');
        }
        $ready = $database->prepare("SELECT clearance.id FROM clearances clearance INNER JOIN students student ON student.id=clearance.student_id AND student.organization_id=:organization_id INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' INNER JOIN stations station ON station.code='DEAN' INNER JOIN clearance_stations dean_record ON dean_record.clearance_id=clearance.id AND dean_record.station_id=station.id WHERE dean_record.status<>'APPROVED' AND NOT EXISTS (SELECT 1 FROM requirement_records record INNER JOIN requirements requirement ON requirement.id=record.requirement_id WHERE record.clearance_station_id=dean_record.id AND requirement.status='ACTIVE' AND requirement.name<>'Dean Approval' AND record.status NOT IN ('VERIFIED','RESOLVED'))");
        $ready->execute(['organization_id' => $dean['organization_id']]);
        $approved = 0;
        foreach ($ready->fetchAll(PDO::FETCH_COLUMN) as $readyClearanceId) {
            if (approveDeanClearance($database, (int) $user['id'], (int) $readyClearanceId)) {
                $approved++;
            }
        }
        setFlashMessage($approved . ' ready student(s) cleared.');
    } elseif ($action === 'verify_requirement') {
        $requirementRecordId = filter_var($_POST['requirement_record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($requirementRecordId === false) {
            throw new RuntimeException('Select a valid requirement.');
        }
        $statement = $database->prepare(
            "UPDATE requirement_records record
             INNER JOIN requirements requirement ON requirement.id=record.requirement_id
             INNER JOIN stations station ON station.id=requirement.station_id AND station.code='DEAN'
             INNER JOIN clearance_stations clearance_station ON clearance_station.id=record.clearance_station_id
             INNER JOIN clearances clearance ON clearance.id=clearance_station.clearance_id
             INNER JOIN students student ON student.id=clearance.student_id
             SET record.status='VERIFIED',record.verified_by=:user_id,record.verified_at=NOW(),record.remarks=NULL
             WHERE record.id=:record_id AND clearance_station.clearance_id=:clearance_id
               AND student.organization_id=:organization_id
               AND requirement.requirement_code NOT IN
                   ('CBE_YBC_APPROVAL','CBE_OJT_REQUIREMENTS','CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')"
        );
        $statement->execute([
            'user_id' => $user['id'],
            'record_id' => $requirementRecordId,
            'clearance_id' => $clearanceId,
            'organization_id' => $dean['organization_id'],
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The requirement could not be verified.');
        }
        $student = $database->prepare("SELECT students.id,students.user_id,period.clearance_status
            FROM clearances INNER JOIN students ON students.id=clearances.student_id
            INNER JOIN academic_periods period ON period.id=clearances.academic_period_id
            WHERE clearances.id=:id");
        $student->execute(['id' => $clearanceId]);
        $studentRow = $student->fetch();
        if ($studentRow !== false) {
            if ($studentRow['clearance_status'] === 'OPEN') {
                $notify = $database->prepare("INSERT INTO notifications (user_id,title,message) VALUES (:user_id,'Dean requirement verified','The Dean verified one of your applicable clearance requirements.')");
                $notify->execute(['user_id' => $studentRow['user_id']]);
            }
            $stationId = $database->query("SELECT id FROM stations WHERE code='DEAN' LIMIT 1")->fetchColumn();
            $audit = $database->prepare("INSERT INTO audit_logs (user_id,action,station_id,student_id,description,new_status) VALUES (:user_id,'DEAN_REQUIREMENT_VERIFIED',:station_id,:student_id,'Dean verified an applicable college or year-level requirement.','VERIFIED')");
            $audit->execute(['user_id' => $user['id'], 'station_id' => $stationId, 'student_id' => $studentRow['id']]);
        }
        setFlashMessage('Dean requirement verified.');
    } else {
        $approved = approveDeanClearance($database, $user['id'], (int) $clearanceId);
        setFlashMessage($approved ? 'Dean clearance approved. Organization is now available.' : 'Dean clearance was already approved.');
    }
} catch (Throwable $exception) {
    error_log('Dean clearance action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to approve Dean clearance right now.');
}

redirectTo($returnPath);
