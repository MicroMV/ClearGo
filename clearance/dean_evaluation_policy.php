<?php
declare(strict_types=1);

/** SQL aliases are supplied by application queries, never request input. */
function ccsEvaluationRequirementScopeSql(string $requirement, string $student, string $period): string
{
    return "{$requirement}.requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION') AND "
        . ccsDeanRequirementApplicabilitySql($requirement, $student, $period);
}

/** The assigned Dean may confirm either semester's applicable requirement by workbook. */
function ccsCompletionRequirementScopeSql(string $requirement, string $student, string $period): string
{
    return "{$requirement}.requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION','CCS_GRADUATION_APPLICATION') AND "
        . ccsDeanRequirementApplicabilitySql($requirement, $student, $period);
}

function ccsDeanRequirementApplicabilitySql(string $requirement, string $student, string $period): string
{
    return "{$requirement}.status='ACTIVE' AND {$requirement}.is_required=1
        AND ({$requirement}.applicable_organization_id IS NULL OR {$requirement}.applicable_organization_id={$student}.organization_id)
        AND ({$requirement}.minimum_year_level IS NULL OR {$student}.year_level>={$requirement}.minimum_year_level)
        AND ({$requirement}.maximum_year_level IS NULL OR {$student}.year_level<={$requirement}.maximum_year_level)
        AND ({$requirement}.applicable_semester IS NULL OR {$requirement}.applicable_semester={$period}.semester)";
}

/** The same prerequisite drives roster eligibility and dashboard actions. */
function ccsEvaluationCompletionEligibilitySql(string $clearance): string
{
    $scope = ccsEvaluationRequirementScopeSql('evaluation_requirement', 'evaluation_student', 'evaluation_period');
    return "NOT EXISTS (
        SELECT 1 FROM requirements evaluation_requirement
        INNER JOIN stations evaluation_station ON evaluation_station.id=evaluation_requirement.station_id AND evaluation_station.code='DEAN'
        INNER JOIN students evaluation_student ON evaluation_student.id={$clearance}.student_id
        INNER JOIN organizations evaluation_college ON evaluation_college.id=evaluation_student.organization_id AND evaluation_college.code='CCS'
        INNER JOIN academic_periods evaluation_period ON evaluation_period.id={$clearance}.academic_period_id
        WHERE ({$scope}) AND (
            NOT EXISTS (
                SELECT 1 FROM requirement_records evaluation_record
                INNER JOIN clearance_stations evaluation_clearance_station ON evaluation_clearance_station.id=evaluation_record.clearance_station_id
                WHERE evaluation_clearance_station.clearance_id={$clearance}.id
                  AND evaluation_clearance_station.station_id=evaluation_requirement.station_id
                  AND evaluation_record.requirement_id=evaluation_requirement.id AND evaluation_record.status='VERIFIED'
            ) OR NOT EXISTS (
                SELECT 1 FROM ccs_evaluation_completions completion
                WHERE completion.clearance_id={$clearance}.id AND completion.status='COMPLETED'
                  AND completion.recorded_by IS NOT NULL AND completion.recorded_at IS NOT NULL
            )
        )
    )";
}

/** Completion import and clearance approval remain separate, auditable operations. */
function requireCcsEvaluationCompletion(PDO $database, int $clearanceId): void
{
    if (!$database->inTransaction()) { throw new LogicException('Evaluation eligibility must be checked in the approval transaction.'); }
    $scope = ccsEvaluationRequirementScopeSql('requirement', 'student', 'period');
    $requirements = $database->prepare(
        "SELECT requirement.id,record.status
         FROM clearances clearance
         INNER JOIN students student ON student.id=clearance.student_id
         INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code='CCS'
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
         INNER JOIN stations station ON station.code='DEAN'
         INNER JOIN requirements requirement ON requirement.station_id=station.id
         INNER JOIN clearance_stations clearance_station ON clearance_station.clearance_id=clearance.id AND clearance_station.station_id=station.id
         LEFT JOIN requirement_records record ON record.clearance_station_id=clearance_station.id AND record.requirement_id=requirement.id
         WHERE clearance.id=:clearance_id AND ({$scope}) FOR UPDATE"
    );
    $requirements->execute(['clearance_id' => $clearanceId]);
    $records = $requirements->fetchAll();
    if ($records === []) { return; }
    $message = 'Faculty Evaluation completion must be confirmed through the Excel completion list before Dean clearance.';
    foreach ($records as $record) {
        if ($record['status'] !== 'VERIFIED') { throw new RuntimeException($message); }
    }
    $completion = $database->prepare('SELECT status,recorded_by,recorded_at FROM ccs_evaluation_completions WHERE clearance_id=:clearance_id FOR UPDATE');
    $completion->execute(['clearance_id' => $clearanceId]);
    $record = $completion->fetch();
    if ($record === false || $record['status'] !== 'COMPLETED' || $record['recorded_by'] === null || $record['recorded_at'] === null) { throw new RuntimeException($message); }
}
