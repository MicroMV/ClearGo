<?php

declare(strict_types=1);

/** Both preview and PDF routes use the same ownership and open-semester gate. */
function studentClearanceDocumentContext(PDO $database, int $userId, int $periodId): array|false
{
    $query = $database->prepare(
        "SELECT clearance.id AS clearance_id,clearance.reference_number,clearance.status AS clearance_status,
                period.school_year,period.semester,period.clearance_status AS period_clearance_status,
                student.id AS student_id,student.student_number,student.first_name,student.middle_name,student.last_name,
                student.program,student.year_level,
                organization.id AS organization_id,organization.code AS organization_code,organization.name AS organization_name
         FROM students student
         INNER JOIN clearances clearance ON clearance.student_id=student.id
         INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
         INNER JOIN semester_enrollments enrollment ON enrollment.student_id=student.id
            AND enrollment.academic_period_id=period.id AND enrollment.status='ENROLLED'
         INNER JOIN organizations organization ON organization.id=student.organization_id
         WHERE student.user_id=:user_id AND student.status='ACTIVE' AND period.id=:period_id
           AND period.status='ACTIVE' AND period.clearance_status='OPEN'
         LIMIT 1"
    );
    $query->execute(['user_id' => $userId, 'period_id' => $periodId]);
    return $query->fetch();
}
