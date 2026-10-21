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

/** Build reminders from the current student's records; a general reminder is not a confirmed debt. */
function studentDashboardPreparationReminders(array $data): array
{
    $period = $data['period'] ?? false;
    if ($period === false || empty($data['enrolled']) || studentDashboardIsHistory($period)) {
        return [];
    }
    $offices = [];
    foreach ($data['stations'] as $station) {
        $offices[$station['code']] = $station + ['dashboard_status' => $data['statuses'][(int) $station['id']]['status'] ?? 'LOCKED'];
    }
    $needsOffice = static fn(string $code): bool => isset($offices[$code]) && $offices[$code]['dashboard_status'] !== 'APPROVED';
    $reminders = [];
    $add = static function (string $key, string $code, string $icon, string $title, string $hint, string $guidance, array $items = [], bool $attention = false, bool $attendance = false) use (&$reminders, $offices): void {
        $office = $offices[$code] ?? null;
        $reminders[] = [
            'key' => $key, 'icon' => $icon, 'title' => $title, 'hint' => $hint,
            'guidance' => $guidance, 'items' => $items, 'attention' => $attention,
            'attendance' => $attendance,
            'modal' => !$attendance && ($office['dashboard_status'] ?? 'LOCKED') !== 'LOCKED' ? (int) $office['id'] : null,
        ];
    };

    if ($needsOffice('ACCOUNTING')) {
        $items = [];
        foreach ($data['accounting'] ?? [] as $charge) {
            $items[] = $charge['description'] . (is_numeric($charge['amount'] ?? null) ? ' — ₱' . number_format((float) $charge['amount'], 2) : '');
        }
        $add('accounting', 'ACCOUNTING', 'wallet', 'Settle any remaining balance',
            $items === [] ? 'Check your balance with Accounting before clearance signing.' : count($items) . ' recorded unpaid ' . (count($items) === 1 ? 'item' : 'items'),
            'If you still have a remaining balance, pay it and confirm that Accounting has posted the payment. Prepare your updated TLC Student Portal assessment screenshot for Accounting review.', $items, $items !== []);
    }
    if ($needsOffice('CLINIC') && ($data['medical_status'] ?? 'PENDING') !== 'VERIFIED') {
        $appointment = $data['appointment'] ?? false;
        $hint = $appointment !== false
            ? 'Appointment: ' . date('M j, Y', strtotime($appointment['schedule_date'])) . ' at ' . date('g:i A', strtotime($appointment['schedule_time']))
            : 'Complete your medical or check available appointment schedules.';
        $add('clinic', 'CLINIC', 'heart', 'Prepare your medical requirement', $hint,
            $appointment !== false
                ? 'Attend your scheduled medical examination and have the Clinic verify your medical requirement.'
                : 'If you have not completed your medical examination, contact the Clinic. When online scheduling is available in your Clinic requirements, choose a date with an available slot. If you already completed your medical, ask the Clinic to verify your record.');
    }
    if ($needsOffice('LIBRARY')) {
        $items = array_map(static fn(array $book): string => $book['book_title'] . (!empty($book['due_at']) ? ' — due ' . $book['due_at'] : ''), $data['books'] ?? []);
        $add('library', 'LIBRARY', 'book', 'Return any borrowed library books',
            $items === [] ? 'Return any books you still have before Library clearance.' : count($items) . ' borrowed ' . (count($items) === 1 ? 'book' : 'books') . ' to return',
            'Return borrowed books to the Library and have the return recorded. Keep your library card ready for confirmation.', $items, $items !== []);
    }
    if ($needsOffice('FACILITIES')) {
        $items = array_map(static fn(array $item): string => $item['description'] . (!empty($item['remarks']) ? ' — ' . $item['remarks'] : ''), $data['facilities'] ?? []);
        $add('facilities', 'FACILITIES', 'building', 'Return borrowed items to Facilities',
            $items === [] ? 'Return any borrowed equipment or other school items.' : count($items) . ' outstanding ' . (count($items) === 1 ? 'item' : 'items'),
            'Return any borrowed school items to the responsible office. Settle any recorded damage or other obligations and ask Facilities to update your record.', $items, $items !== []);
    }
    $fees = array_values(array_filter($data['bookstore'] ?? [], static fn(array $fee): bool => $fee['payment_status'] === 'UNPAID'));
    if ($needsOffice('BOOKSTORE') && $fees !== []) {
        $items = array_map(static fn(array $fee): string => $fee['name'] . ' — ₱' . number_format((float) $fee['amount'], 2), $fees);
        $add('bookstore', 'BOOKSTORE', 'book', 'Check your Bookstore fees', 'Prepare payment or receipts for your assigned fees.',
            'If a listed fee has already been paid, submit its receipt when Bookstore submissions are available and wait for verification. Do not pay the same fee again while a receipt is being reviewed.', $items, true);
    }
    $violations = $data['violations'] ?? [];
    if ($needsOffice('COMLAB') || $violations !== []) {
        $items = array_map(static fn(array $violation): string => ($violation['office_name'] ?? 'Responsible office') . ': ' . $violation['description'], $violations);
        $violationOffices = array_unique(array_column($violations, 'office_code'));
        $violationOffice = $violations === [] ? 'COMLAB' : (count($violationOffices) === 1 ? (string) reset($violationOffices) : '');
        $add('violations', $violationOffice, 'alert', 'Settle any outstanding violations',
            $items === [] ? 'If you have a violation, coordinate with the office that recorded it.' : count($items) . ' unresolved ' . (count($items) === 1 ? 'violation' : 'violations'),
            'Complete the required action and ask the responsible office to mark your violation as resolved before clearance signing.', $items, $items !== []);
    }
    $groups = $data['sanctions'] ?? [];
    foreach ($groups as $code => $group) {
        $submitted = ($group['submission']['status'] ?? '') === 'SUBMITTED';
        $items = array_map(static fn(array $item): string => $item['event_name'] . ' — ' . $item['hours'] . ' hours: ' . $item['task'], $group['items']);
        $add('sanctions-' . $code, $code, 'people', 'Complete your ' . $group['name'] . ' sanctions',
            $submitted ? 'Your photos are awaiting office verification.' : (string) $group['hours'] . ' hours of outstanding sanctions',
            $submitted
                ? 'Wait for the office to review your submitted photos. Check Attendance for feedback and any new absences added after your submission.'
                : 'Complete the assigned tasks and hours with your monitor, then submit one Before/After photo pair for this office. Organization and Student Council require separate submissions.', $items, true, true);
    }
    if ($groups === [] && ($needsOffice('ORGANIZATION') || $needsOffice('STUDENT_COUNCIL'))) {
        $add('sanctions', 'ORGANIZATION', 'people', 'Complete any absence sanctions',
            'Check Attendance for sanctions from your Organization or Student Council.',
            'If you missed a required event and were assigned a sanction, complete the work with your monitor and submit your Before/After photos. Use a separate photo pair for each office.', [], false, true);
    }
    return $reminders;
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
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 19h16"/>',
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
