<?php

declare(strict_types=1);

require_once __DIR__ . '/office_workflow.php';
require_once __DIR__ . '/clearance_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';

/** Count the assigned college's uncleared students across the whole active roster. */
function deanRemainingClearanceCount(PDO $database, int $organizationId): int
{
    $statement = $database->prepare(
        "SELECT COUNT(*) FROM clearance_stations record
         INNER JOIN stations station ON station.id=record.station_id AND station.code='DEAN' AND station.status='ACTIVE'
         INNER JOIN clearances clearance ON clearance.id=record.clearance_id
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
         INNER JOIN students student ON student.id=clearance.student_id AND student.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
             AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         WHERE student.organization_id=:organization_id AND record.status<>'APPROVED'"
    );
    $statement->execute(['organization_id' => $organizationId]);
    return (int) $statement->fetchColumn();
}

function deanMinimumBulkCount(PDO $database, array $assignment): int
{
    return $assignment['organization_code'] === 'CCS'
        && deanRemainingClearanceCount($database, (int) $assignment['organization_id']) >= 5 ? 5 : 1;
}

/** Return the complete ready roster, independently of search and pagination. */
function bulkReadyClearanceIds(PDO $database, string $stationCode, ?int $organizationId = null, string $group = '', string $audience = 'ALL', string $department = 'ALL'): array
{
    $parameters = ['station_code' => $stationCode];
    $scope = '1=1';
    if (in_array($stationCode, ['DEAN', 'ORGANIZATION'], true)) {
        if ($organizationId === null || $organizationId < 1) {
            throw new RuntimeException('A college assignment is required for bulk clearance.');
        }
        $scope = 'student.organization_id=:organization_id';
        $parameters['organization_id'] = $organizationId;
    }
    if ($stationCode === 'DEAN') {
        if ($group !== '') {
            if (!in_array($group, ['NON_GRADUATING', 'FOURTH_YEAR'], true)) {
                throw new RuntimeException('Select a valid CBE student group.');
            }
            $scope .= $group === 'FOURTH_YEAR' ? ' AND student.year_level=4' : ' AND student.year_level<4';
        }
        $eligible = "NOT EXISTS (
            SELECT 1 FROM requirement_records record
            INNER JOIN requirements requirement ON requirement.id=record.requirement_id
            WHERE record.clearance_station_id=cs.id AND requirement.status='ACTIVE'
              AND requirement.name<>'Dean Approval' AND record.status NOT IN ('VERIFIED','RESOLVED')
              AND NOT (organization.code='CBE' AND student.year_level=4 AND requirement.requirement_code='CBE_OJT_REQUIREMENTS')
        ) AND (organization.code<>'CBE' OR EXISTS (
            SELECT 1 FROM clearance_stations prior_record
            INNER JOIN stations prior_station ON prior_station.id=prior_record.station_id AND prior_station.code='ORGANIZATION'
            WHERE prior_record.clearance_id=c.id AND prior_record.status='APPROVED'
        ))";
    } elseif ($stationCode === 'ORGANIZATION') {
        $eligible = "(organization.code='CBE' OR EXISTS (
            SELECT 1 FROM clearance_stations dean_record
            INNER JOIN stations dean_station ON dean_station.id=dean_record.station_id AND dean_station.code='DEAN'
            WHERE dean_record.clearance_id=c.id AND dean_record.status='APPROVED'
        )) AND NOT EXISTS (
            SELECT 1 FROM events event
            INNER JOIN stations event_station ON event_station.id=event.station_id AND event_station.code='ORGANIZATION'
            LEFT JOIN event_attendance attendance ON attendance.event_id=event.id
                AND attendance.student_id=student.id AND attendance.status='PRESENT'
            WHERE event.organization_id=student.organization_id AND event.academic_period_id=period.id
              AND event.is_required=1 AND event.status='ACTIVE' AND attendance.id IS NULL
        )";
    } else {
        if (!in_array($stationCode, array_column(OFFICE_ROLE_CONFIG, 'code'), true)) {
            throw new RuntimeException('This office does not support bulk clearance.');
        }
        $eligible = officeListEligibilitySql($stationCode);
        if ($stationCode === 'REGISTRAR') {
            // Batch approval must use recorded document verification, since there is no individual review.
            $eligible .= " AND NOT EXISTS (
                SELECT 1 FROM document_requirements document_requirement
                LEFT JOIN student_documents document ON document.document_requirement_id=document_requirement.id
                    AND document.student_id=student.id
                WHERE document_requirement.academic_period_id=period.id AND document_requirement.status='ACTIVE'
                    AND document_requirement.is_required=1 AND (document.id IS NULL OR document.status<>'VERIFIED')
            )";
        }
        if ($stationCode === 'FACILITIES') {
            if (!in_array($audience, ['ALL', 'GRADUATING', 'NON_GRADUATING'], true)) {
                throw new RuntimeException('Select a valid Facilities student group.');
            }
            $scope .= ' AND ' . officeAudienceSql($audience);
            if ($department !== 'ALL') {
                $scope .= ' AND organization.code=:department';
                $parameters['department'] = $department;
            }
        }
    }
    $statement = $database->prepare(
        "SELECT c.id FROM clearance_stations cs
         INNER JOIN stations station ON station.id=cs.station_id AND station.code=:station_code AND station.status='ACTIVE'
         INNER JOIN clearances c ON c.id=cs.clearance_id
         INNER JOIN academic_periods period ON period.id=c.academic_period_id AND period.status='ACTIVE'
             AND period.clearance_status IN ('PREPARING','OPEN')
         INNER JOIN students student ON student.id=c.student_id AND student.status='ACTIVE'
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
             AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN organizations organization ON organization.id=student.organization_id
         WHERE cs.status='PENDING' AND ({$scope}) AND ({$eligible})
         ORDER BY c.id"
    );
    $statement->execute($parameters);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function approveAllReadyClearances(PDO $database, array $user, string $group = '', string $audience = 'ALL', string $department = 'ALL'): array
{
    $userId = (int) $user['id'];
    $organizationId = null;
    if ($user['role'] === 'DEAN') {
        $assignment = deanAssignment($database, $userId);
        $stationCode = 'DEAN';
        $organizationId = (int) $assignment['organization_id'];
        if ($assignment['organization_code'] === 'CBE' && !in_array($group, ['NON_GRADUATING', 'FOURTH_YEAR'], true)) {
            throw new RuntimeException('Select a valid CBE student group.');
        }
        if ($assignment['organization_code'] !== 'CBE') {
            $group = '';
        }
    } elseif ($user['role'] === 'ORG_STAFF') {
        $assignment = assignedOrganization($database, $userId);
        $stationCode = 'ORGANIZATION';
        $organizationId = (int) $assignment['id'];
    } else {
        $stationCode = officeConfigForRole($user['role'])['code'];
    }

    $ids = bulkReadyClearanceIds($database, $stationCode, $organizationId, $group, $audience, $department);
    if ($ids !== [] && $stationCode === 'DEAN' && count($ids) < deanMinimumBulkCount($database, $assignment)) {
        throw new RuntimeException('CCS Dean bulk clearance requires at least 5 ready students while 5 or more students remain uncleared.');
    }
    $result = ['approved' => 0, 'skipped' => 0];
    foreach ($ids as $clearanceId) {
        try {
            if ($stationCode === 'DEAN') {
                $approved = approveDeanClearance($database, $userId, $clearanceId, true);
            } elseif ($stationCode === 'ORGANIZATION') {
                $student = $database->prepare('SELECT student_id FROM clearances WHERE id=:id');
                $student->execute(['id' => $clearanceId]);
                approveOrganizationStudent($database, $userId, $organizationId, (int) $student->fetchColumn(), true);
                $approved = true;
            } elseif ($stationCode === 'REGISTRAR') {
                finalizeRegistrarClearance($database, $clearanceId, $userId, true);
                $approved = true;
            } else {
                $context = officeClearanceContext($database, $stationCode, $clearanceId);
                ensureOfficeSourceRecords($database, (int) $context['student_id'], (int) $context['academic_period_id']);
                approveOfficeClearance($database, $stationCode, $clearanceId, $userId, null, true);
                $approved = true;
            }
            $result[$approved ? 'approved' : 'skipped']++;
        } catch (PDOException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            // Another staff member may have reviewed the student since the roster loaded.
            $result['skipped']++;
        }
    }
    return $result;
}

function renderBulkClearanceFooter(int $page, int $totalPages, array $query, string $actionUrl, int $readyCount, string $scope, array $fields = [], int $minimumReadyCount = 1): string
{
    $confirmation = 'Clear ' . $readyCount . ' ready student(s) in ' . $scope . ' across all pages? This includes students outside the current search, course, year and status filters.';
    $html = '<div class="clearance-list-footer">' . renderPagination($page, $totalPages, $query);
    $html .= '<form class="bulk-clearance-form" method="post" action="' . escapeHtml($actionUrl)
        . '" onsubmit="return confirm(' . escapeHtml(json_encode($confirmation, JSON_HEX_APOS | JSON_HEX_QUOT)) . ');">';
    $fields = array_merge($fields, ['csrf_token' => createCsrfToken(), 'action' => 'approve_all_ready']);
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . escapeHtml((string) $name) . '" value="' . escapeHtml((string) $value) . '">';
    }
    return $html . '<button type="submit"' . ($readyCount < $minimumReadyCount ? ' disabled' : '')
        . ' title="Clear all eligible students in ' . escapeHtml($scope) . ' across all pages">Clear all ready (' . $readyCount . ')</button></form></div>';
}
