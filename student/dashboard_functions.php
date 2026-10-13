<?php

declare(strict_types=1);

/** @return array<int,array<string,mixed>> */
function studentDashboardPeriods(PDO $database, int $studentId): array
{
    $query = $database->prepare(
        "SELECT DISTINCT period.id,period.school_year,period.semester,period.status,period.clearance_status,
                CASE WHEN enrollment.status='ENROLLED' THEN 1 ELSE 0 END AS is_enrolled
         FROM academic_periods period
         LEFT JOIN semester_enrollments enrollment ON enrollment.academic_period_id=period.id
            AND enrollment.student_id=:enrollment_student_id
         LEFT JOIN clearances clearance ON clearance.academic_period_id=period.id
            AND clearance.student_id=:clearance_student_id
         WHERE enrollment.status='ENROLLED'
            OR ((period.status<>'ACTIVE' OR period.clearance_status='CLOSED') AND clearance.id IS NOT NULL)
         ORDER BY CASE WHEN period.status='ACTIVE' THEN 0 ELSE 1 END,period.id DESC"
    );
    $query->execute(['enrollment_student_id' => $studentId, 'clearance_student_id' => $studentId]);
    return $query->fetchAll();
}

/** @param array<int,array<string,mixed>> $periods */
function selectStudentDashboardPeriod(array $periods, mixed $requestedId = null): array|false
{
    $requestedId = filter_var($requestedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($requestedId !== false) {
        foreach ($periods as $period) {
            if ((int) $period['id'] === $requestedId) {
                return $period;
            }
        }
    }
    foreach ($periods as $period) {
        if ((int) $period['is_enrolled'] === 1) {
            return $period;
        }
    }
    return $periods[0] ?? false;
}

function studentDashboardIsHistory(array|false $period): bool
{
    return $period !== false && ($period['status'] !== 'ACTIVE' || $period['clearance_status'] === 'CLOSED');
}

function studentDashboardCanSubmit(array|false $period): bool
{
    return $period !== false && $period['status'] === 'ACTIVE'
        && $period['clearance_status'] === 'OPEN' && (int) $period['is_enrolled'] === 1;
}

function studentDashboardIcon(string $name): string
{
    $paths = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'clearance' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 11l2 2 4-4M9 17h6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 6v2"/>',
        'scan' => '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m8 0h5v-5M3 12h18M7 7h3v3H7zm7 0h3v3h-3zM7 15h3v3H7zm7 0h3v3h-3z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'building' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 8h1m4 0h1M9 12h1m4 0h1"/>',
        'book' => '<path d="M12 6v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z"/>',
        'heart' => '<path d="M20 5a5 5 0 0 0-8 1 5 5 0 0 0-8-1c-4 5 2 10 8 15 6-5 12-10 8-15Z"/>',
        'screen' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/>',
        'wallet' => '<path d="M20 8V5H5a2 2 0 0 0 0 4h16v11H5a2 2 0 0 1-2-2V7m18 5h-6v4h6"/>',
        'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/>',
        'key' => '<circle cx="8" cy="8" r="5"/><path d="m12 12 9 9m-6-6 3-3m0 6 3-3"/>',
        'alert' => '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5m0 3h.01"/>',
    ];
    return '<svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? $paths['building']) . '</svg>';
}

function studentDashboardStationIcon(string $code): string
{
    return studentDashboardIcon(match ($code) {
        'DEAN', 'REGISTRAR' => 'building',
        'ORGANIZATION', 'SASO', 'STUDENT_COUNCIL' => 'people',
        'LIBRARY', 'BOOKSTORE' => 'book',
        'CLINIC' => 'heart',
        'COMLAB' => 'screen',
        'ACCOUNTING' => 'wallet',
        default => 'clearance',
    });
}

function studentDashboardStationLabel(array $station): string
{
    return match ($station['code']) {
        'DEAN' => 'Dean', 'ORGANIZATION' => 'Organization', 'SASO' => 'Student Affairs',
        'STUDENT_COUNCIL' => 'Student Council', 'LIBRARY' => 'Library', 'BOOKSTORE' => 'Bookstore',
        'CLINIC' => 'Clinic', 'FACILITIES' => 'Facilities', 'COMLAB' => 'Computer Lab',
        'ACCOUNTING' => 'Accounting', 'REGISTRAR' => 'Registrar',
        default => $station['name'],
    };
}

function studentDashboardFriendlyStatus(string $status): string
{
    return match ($status) {
        'APPROVED', 'VERIFIED', 'COMPLETED' => 'Cleared',
        'RETURNED', 'REJECTED', 'ERROR' => 'Needs attention',
        'SUBMITTED', 'UNDER_REVIEW' => 'In review',
        'LOCKED' => 'Upcoming',
        'PENDING', 'ELIGIBLE' => 'Ready',
        'IN_PROGRESS' => 'In progress',
        default => ucfirst(strtolower(str_replace('_', ' ', $status))),
    };
}
