<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/dashboard_functions.php';

$user = requireRole('STUDENT');

try {
    $database = getDatabaseConnection();
    $studentQuery = $database->prepare(
        'SELECT s.id, s.student_number, s.first_name, s.last_name, s.program, s.year_level, s.section, s.organization_id,
                o.name AS organization_name, o.code AS organization_code, u.email
         FROM students s
         INNER JOIN users u ON u.id = s.user_id
         INNER JOIN organizations o ON o.id = s.organization_id
         WHERE s.user_id = :user_id AND s.status = :status
         LIMIT 1'
    );
    $studentQuery->execute(['user_id' => $user['id'], 'status' => 'ACTIVE']);
    $student = $studentQuery->fetch();

    if ($student === false) {
        throw new RuntimeException('No active student profile found.');
    }

    $availablePeriods = studentDashboardPeriods($database, (int) $student['id']);
    $period = selectStudentDashboardPeriod($availablePeriods, $_GET['period'] ?? null);
    $isEnrolled = $period !== false && (int) $period['is_enrolled'] === 1;
    $isHistoricalRecord = studentDashboardIsHistory($period);
    $canSubmit = studentDashboardCanSubmit($period);

    $comlabQuery = $database->prepare("SELECT violation.description,violation.created_at FROM violations violation INNER JOIN stations station ON station.id=violation.station_id WHERE violation.student_id=:student_id AND station.code='COMLAB' AND violation.status='OPEN' ORDER BY violation.created_at DESC,violation.id DESC");
    $comlabQuery->execute(['student_id' => $student['id']]);
    $comlabViolations = $comlabQuery->fetchAll();
    $facilitiesItems = [];
    if ($period !== false) {
        $facilitiesQuery = $database->prepare(
            "SELECT description, remarks, item_type
             FROM facilities_obligations
             WHERE student_id=:student_id AND academic_period_id=:period_id AND status='OUTSTANDING'
             ORDER BY created_at DESC, id DESC"
        );
        $facilitiesQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $facilitiesItems = $facilitiesQuery->fetchAll();
    }

    $organizationEvents = [];
    if ($period !== false && $isEnrolled) {
        $eventQuery = $database->prepare(
            "SELECT event.name,event.event_date,event.start_time,event.end_time,
                    attendance.status AS attendance_status,attendance.recorded_at
             FROM events event
             LEFT JOIN event_attendance attendance ON attendance.event_id=event.id AND attendance.student_id=:student_id
             WHERE event.academic_period_id=:period_id AND event.organization_id=:organization_id
               AND event.is_required=1 AND event.status='ACTIVE'
             ORDER BY event.event_date,event.start_time,event.id"
        );
        $eventQuery->execute([
            'student_id' => $student['id'],
            'period_id' => $period['id'],
            'organization_id' => $student['organization_id'] ?? 0,
        ]);
        $organizationEvents = $eventQuery->fetchAll();
    }

    $isCbeStudent = ($student['organization_code'] ?? '') === 'CBE';
    $stationFilter = $isHistoricalRecord
        ? 'EXISTS (SELECT 1 FROM clearance_stations saved_station INNER JOIN clearances saved_clearance ON saved_clearance.id=saved_station.clearance_id WHERE saved_station.station_id=station.id AND saved_clearance.student_id=:history_student_id AND saved_clearance.academic_period_id=:history_period_id)'
        : 'station.status = :status';
    $stationQuery = $database->prepare(
        "SELECT station.id, station.name, station.code, station.sort_order,
                parent.name AS parent_name,
                CASE
                    WHEN :is_cbe_display = 1 AND parent.id IS NULL THEN CAST(CASE station.code
                        WHEN 'ORGANIZATION' THEN 1 WHEN 'DEAN' THEN 2 WHEN 'SASO' THEN 3
                        WHEN 'FACILITIES' THEN 4 WHEN 'COMLAB' THEN 5 WHEN 'ACCOUNTING' THEN 6
                        WHEN 'REGISTRAR' THEN 7 ELSE station.sort_order END AS CHAR)
                    WHEN :is_cbe_child = 1 THEN CONCAT(3, '.', station.sort_order)
                    WHEN parent.id IS NULL THEN CAST(station.sort_order AS CHAR)
                    ELSE CONCAT(parent.sort_order, '.', station.sort_order)
                END AS display_order,
                CASE
                    WHEN :is_cbe_order = 1 THEN CASE COALESCE(parent.code, station.code)
                        WHEN 'ORGANIZATION' THEN 1 WHEN 'DEAN' THEN 2 WHEN 'SASO' THEN 3
                        WHEN 'FACILITIES' THEN 4 WHEN 'COMLAB' THEN 5 WHEN 'ACCOUNTING' THEN 6
                        WHEN 'REGISTRAR' THEN 7 ELSE station.sort_order END
                    ELSE COALESCE(parent.sort_order, station.sort_order)
                END AS workflow_order
         FROM stations station
         LEFT JOIN stations parent ON parent.id = station.parent_station_id
         WHERE {$stationFilter}
         ORDER BY workflow_order,
                  CASE WHEN parent.id IS NULL THEN 0 ELSE 1 END,
                  station.sort_order"
    );
    $stationParameters = [
        'is_cbe_display' => $isCbeStudent ? 1 : 0,
        'is_cbe_child' => $isCbeStudent ? 1 : 0,
        'is_cbe_order' => $isCbeStudent ? 1 : 0,
    ];
    if ($isHistoricalRecord) {
        $stationParameters['history_student_id'] = $student['id'];
        $stationParameters['history_period_id'] = $period['id'];
    } else {
        $stationParameters['status'] = 'ACTIVE';
    }
    $stationQuery->execute($stationParameters);
    $stations = $stationQuery->fetchAll();

    $clearance = false;
    $stationStatuses = [];
    $evaluation = false;
    $evaluationApplicable = false;
    if ($period !== false && ($isHistoricalRecord || $canSubmit)) {
        $clearanceQuery = $database->prepare(
            'SELECT id, reference_number, status
             FROM clearances
             WHERE student_id = :student_id AND academic_period_id = :period_id
             LIMIT 1'
        );
        $clearanceQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $clearance = $clearanceQuery->fetch();

        if ($clearance !== false) {
            $statusQuery = $database->prepare(
                'SELECT station_id, id, status, returned_reason
                 FROM clearance_stations
                 WHERE clearance_id = :clearance_id'
            );
            $statusQuery->execute(['clearance_id' => $clearance['id']]);
            foreach ($statusQuery->fetchAll() as $stationStatus) {
                $stationStatuses[(int) $stationStatus['station_id']] = $stationStatus;
            }

            $evaluationQuery = $database->prepare(
                'SELECT status, attempt_count, last_submitted_at, verified_at
                 FROM evaluation_verifications
                 WHERE clearance_id = :clearance_id
                 LIMIT 1'
            );
            $evaluationQuery->execute(['clearance_id' => $clearance['id']]);
            $evaluation = $evaluationQuery->fetch();

            $submissionQuery = $database->prepare(
                'SELECT status, submitted_at AS last_submitted_at, reviewed_at AS verified_at, return_reason
                 FROM evaluation_submissions WHERE clearance_id=:clearance_id LIMIT 1'
            );
            $submissionQuery->execute(['clearance_id' => $clearance['id']]);
            $evaluationSubmission = $submissionQuery->fetch();
            if ($evaluationSubmission !== false) {
                $evaluation = $evaluationSubmission;
            }

            $evaluationRequirementQuery = $database->prepare(
                "SELECT COUNT(*)
                 FROM requirement_records record
                 INNER JOIN requirements requirement ON requirement.id = record.requirement_id
                 INNER JOIN clearance_stations station_record ON station_record.id = record.clearance_station_id
                 WHERE station_record.clearance_id = :clearance_id
                   AND requirement.requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')"
            );
            $evaluationRequirementQuery->execute(['clearance_id' => $clearance['id']]);
            $evaluationApplicable = (int) $evaluationRequirementQuery->fetchColumn() === 1;
        }
    }

    $selectedStation = null;
    $requestedCode = strtoupper((string) ($_GET['station'] ?? ''));
    foreach ($stations as $station) {
        if ($station['code'] === $requestedCode) {
            $selectedStation = $station;
            break;
        }
    }

    $stationRequirements = [];
    $accountingReceipt = false;
    $libraryCardSubmission = false;
    $bookstoreFees = [];
    $medicalStatus = 'PENDING';
    $medicalAppointment = false;
    $medicalSchedules = [];
    if ($clearance !== false) {
        if ($isHistoricalRecord) {
            $allRequirementsQuery = $database->prepare(
                'SELECT r.station_id,r.name,r.requirement_code,r.description,rr.status,rr.remarks
                 FROM requirement_records rr
                 INNER JOIN requirements r ON r.id=rr.requirement_id
                 INNER JOIN clearance_stations clearance_station ON clearance_station.id=rr.clearance_station_id
                 WHERE clearance_station.clearance_id=:clearance_id
                 ORDER BY r.station_id,r.id'
            );
            $allRequirementsQuery->execute(['clearance_id' => $clearance['id']]);
        } else {
            $allRequirementsQuery = $database->prepare(
                'SELECT r.station_id, r.name, r.requirement_code, r.description, rr.status, rr.remarks
                 FROM requirements r
                 INNER JOIN clearance_stations clearance_station
                    ON clearance_station.station_id = r.station_id AND clearance_station.clearance_id = :clearance_id
                 LEFT JOIN requirement_records rr
                    ON rr.requirement_id = r.id AND rr.clearance_station_id = clearance_station.id
                 WHERE r.status = :status AND r.is_required = 1
                   AND (r.applicable_organization_id IS NULL OR r.applicable_organization_id = :organization_id)
                   AND (r.minimum_year_level IS NULL OR :minimum_year_level >= r.minimum_year_level)
                   AND (r.maximum_year_level IS NULL OR :maximum_year_level <= r.maximum_year_level)
                   AND (r.applicable_semester IS NULL OR r.applicable_semester = :semester)
                 ORDER BY r.station_id, r.id'
            );
            $allRequirementsQuery->execute([
                'clearance_id' => $clearance['id'],
                'status' => 'ACTIVE',
                'organization_id' => $student['organization_id'],
                'minimum_year_level' => $student['year_level'],
                'maximum_year_level' => $student['year_level'],
                'semester' => $period['semester'],
            ]);
        }
        foreach ($allRequirementsQuery->fetchAll() as $requirement) {
            $stationRequirements[(int) $requirement['station_id']][] = $requirement;
        }
    }

    if ($clearance !== false && $period !== false) {
        $receiptQuery = $database->prepare(
            "SELECT receipt.id, receipt.receipt_code, receipt.status, receipt.scanned_at
             FROM accounting_receipts receipt
             WHERE receipt.student_id=:student_id AND receipt.academic_period_id=:period_id
             ORDER BY receipt.id DESC LIMIT 1"
        );
        $receiptQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $accountingReceipt = $receiptQuery->fetch();
    }

    if ($clearance !== false && $period !== false) {
        $libraryCardQuery = $database->prepare(
            "SELECT card.status AS card_status,submission.status AS submission_status,
                    submission.submitted_at,submission.return_reason
             FROM library_cards card
             LEFT JOIN library_card_submissions submission ON submission.library_card_id=card.id
             WHERE card.student_id=:student_id LIMIT 1"
        );
        $libraryCardQuery->execute(['student_id' => $student['id']]);
        $libraryCardSubmission = $libraryCardQuery->fetch();

        $bookstoreFeeQuery = $database->prepare(
            "SELECT student_fee.id AS student_fee_id,student_fee.status AS payment_status,
                    fee.name,fee.amount,submission.status AS submission_status,
                    submission.submitted_at,submission.return_reason
             FROM student_bookstore_fees student_fee
             INNER JOIN bookstore_fees fee ON fee.id=student_fee.fee_id
                AND fee.academic_period_id=:period_id AND (:include_archived=1 OR fee.status='ACTIVE')
             LEFT JOIN bookstore_receipt_submissions submission ON submission.student_fee_id=student_fee.id
             WHERE student_fee.student_id=:student_id
             ORDER BY fee.id"
        );
        $bookstoreFeeQuery->execute(['period_id' => $period['id'], 'student_id' => $student['id'], 'include_archived' => $isHistoricalRecord ? 1 : 0]);
        $bookstoreFees = $bookstoreFeeQuery->fetchAll();

        $medicalQuery = $database->prepare("SELECT status FROM medical_requirements WHERE student_id=:student_id AND academic_period_id=:period_id LIMIT 1");
        $medicalQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $medicalStatus = (string) ($medicalQuery->fetchColumn() ?: 'PENDING');

        if ($medicalStatus === 'RETURNED') {
            $appointmentQuery = $database->prepare(
                "SELECT appointment.id,schedule.schedule_date,schedule.schedule_time,schedule.end_time
                 FROM clinic_medical_appointments appointment
                 INNER JOIN clinic_medical_schedules schedule ON schedule.id=appointment.schedule_id
                 WHERE appointment.student_id=:student_id AND appointment.academic_period_id=:period_id
                   AND appointment.status='SCHEDULED' LIMIT 1"
            );
            $appointmentQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
            $medicalAppointment = $appointmentQuery->fetch();

            if ($medicalAppointment === false && $canSubmit) {
                $scheduleQuery = $database->prepare(
                    "SELECT schedule.id,schedule.schedule_date,schedule.schedule_time,schedule.end_time,schedule.capacity,
                            COUNT(appointment.id) AS booked_count
                     FROM clinic_medical_schedules schedule
                     LEFT JOIN clinic_medical_appointments appointment ON appointment.schedule_id=schedule.id
                        AND appointment.status='SCHEDULED'
                     WHERE schedule.academic_period_id=:period_id AND schedule.status='ACTIVE'
                       AND TIMESTAMP(schedule.schedule_date,schedule.schedule_time)>NOW()
                     GROUP BY schedule.id,schedule.schedule_date,schedule.schedule_time,schedule.end_time,schedule.capacity
                     ORDER BY schedule.schedule_date,schedule.schedule_time"
                );
                $scheduleQuery->execute(['period_id' => $period['id']]);
                $medicalSchedules = $scheduleQuery->fetchAll();
            }
        }
    }

    $notifications = dashboardNotificationPreview($database, (int) $user['id']);
    $unreadNotificationCount = dashboardUnreadNotificationCount($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Student dashboard failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the student dashboard right now. Please try again later.');
}

function studentStatusLabel(string $status): string
{
    return match ($status) {
        'APPROVED' => 'CLEARED',
        'RETURNED' => 'REQUIRES ACTION',
        'SUBMITTED' => 'WAITING FOR VERIFICATION',
        'UNDER_REVIEW' => 'UNDER REVIEW',
        default => statusDisplayLabel($status),
    };
}

$displayStationStatuses = $stationStatuses;
$stationIdByCode = [];
foreach ($stations as $station) {
    $stationIdByCode[$station['code']] = (int) $station['id'];
}
$rawStationStatus = static function (string $code) use ($stationIdByCode, $stationStatuses): string {
    $stationId = $stationIdByCode[$code] ?? 0;
    return $stationStatuses[$stationId]['status'] ?? 'LOCKED';
};
$setDisplayStatus = static function (string $code, string $status) use (&$displayStationStatuses, $stationIdByCode): void {
    $stationId = $stationIdByCode[$code] ?? 0;
    if ($stationId > 0) {
        $displayStationStatuses[$stationId]['status'] = $status;
    }
};

// Never reveal an early approval ahead of its clearance sequence. CBE starts at
// Organization/YBC; every other college starts at the Dean.
$deanComplete = $rawStationStatus('DEAN') === 'APPROVED';
$organizationComplete = $rawStationStatus('ORGANIZATION') === 'APPROVED';
if ($isHistoricalRecord) {
    // Show the stored station decisions without applying today's workflow.
} elseif ($isCbeStudent && !$organizationComplete) {
    foreach (['DEAN', 'SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
        $setDisplayStatus($code, 'LOCKED');
    }
} elseif (!$isCbeStudent && !$deanComplete) {
    foreach (['ORGANIZATION', 'SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
        $setDisplayStatus($code, 'LOCKED');
    }
} elseif (!$deanComplete) {
    foreach (['SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
        $setDisplayStatus($code, 'LOCKED');
    }
} elseif (!$organizationComplete) {
    foreach (['SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
        $setDisplayStatus($code, 'LOCKED');
    }
} else {
    $sasoComplete = $rawStationStatus('SASO') === 'APPROVED';
    if (!$sasoComplete) {
        foreach (['FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
            $setDisplayStatus($code, 'LOCKED');
        }
    } else {
        $facilitiesComplete = $rawStationStatus('FACILITIES') === 'APPROVED';
        if (!$facilitiesComplete) {
            foreach (['COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) {
                $setDisplayStatus($code, 'LOCKED');
            }
        } else {
            $comlabComplete = $rawStationStatus('COMLAB') === 'APPROVED';
            if (!$comlabComplete) {
                foreach (['ACCOUNTING', 'REGISTRAR'] as $code) {
                    $setDisplayStatus($code, 'LOCKED');
                }
            } elseif ($rawStationStatus('ACCOUNTING') !== 'APPROVED') {
                $setDisplayStatus('REGISTRAR', 'LOCKED');
            }
        }
    }
}

$approved = 0;
foreach ($stations as $station) {
    $status = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED';
    if ($status === 'APPROVED') {
        $approved++;
    }
}

$semesterLabels = ['1ST' => 'First Semester', '2ND' => 'Second Semester', 'SUMMER' => 'Summer'];
$periodLabel = $period === false
    ? 'No semester record'
    : ($semesterLabels[$period['semester']] ?? $period['semester']) . ' A.Y. ' . $period['school_year'];
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
require __DIR__ . '/dashboard_view.php';
?>

        <?php if ($clearance !== false): ?>
            <?php foreach ($stations as $station): ?>
                <?php $modalStatus = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED'; ?>
                <?php if ($modalStatus === 'LOCKED' && !$isHistoricalRecord) {
                    continue;
                } ?>
                <div class="requirements-modal" id="requirements-modal-<?= (int) $station['id'] ?>" hidden aria-hidden="true">
                    <div class="requirements-modal__backdrop" data-close-requirements-modal></div>
                    <section class="requirements-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="requirements-modal-title-<?= (int) $station['id'] ?>" aria-describedby="requirements-modal-help-<?= (int) $station['id'] ?>" tabindex="-1">
                        <div class="requirements-modal__header">
                            <div class="requirements-modal__heading">
                                <span class="requirements-modal__eyebrow">Clearance requirements</span>
                                <h2 id="requirements-modal-title-<?= (int) $station['id'] ?>"><?= escapeHtml($station['name']) ?> Requirements</h2>
                                <p id="requirements-modal-help-<?= (int) $station['id'] ?>"><?= $isHistoricalRecord ? 'Review the saved requirements and decisions for this semester.' : 'Review the items below and complete any action marked as required.' ?></p>
                            </div>
                            <button class="requirements-modal__close" type="button" data-close-requirements-modal aria-label="Close requirements">&times;</button>
                        </div>
                        <div class="requirements-modal__content">
                        <?php $modalRequirements = $stationRequirements[(int) $station['id']] ?? [];
                        if ($station['code'] === 'DEAN' && $evaluationApplicable) {
                            $modalRequirements = array_values(array_filter($modalRequirements, static fn(array $requirement): bool => in_array($requirement['requirement_code'] ?? '', ['CCS_FACULTY_EVALUATION', 'CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true)));
                        }
                        $completedRequirementStatuses = ['VERIFIED', 'RESOLVED', 'APPROVED'];
                        $completedRequirementCount = count(array_filter($modalRequirements, static fn(array $requirement): bool => in_array((string) ($requirement['status'] ?? ''), $completedRequirementStatuses, true)));
                        $allModalRequirementsComplete = $modalRequirements !== [] && $completedRequirementCount === count($modalRequirements);
                        $hasReturnedRequirement = count(array_filter($modalRequirements, static fn(array $requirement): bool => in_array((string) ($requirement['status'] ?? ''), ['RETURNED', 'REJECTED', 'ERROR'], true))) > 0;
                        $modalOverviewState = $modalStatus === 'APPROVED' ? 'approved' : ($modalStatus === 'RETURNED' || $hasReturnedRequirement ? 'returned' : strtolower(str_replace('_', '-', $modalStatus)));
                        $modalGuidanceTitle = $modalStatus === 'APPROVED' ? 'You are cleared at this station' : ($modalStatus === 'RETURNED' || $hasReturnedRequirement ? 'Your submission needs attention' : (in_array($modalStatus, ['SUBMITTED', 'UNDER_REVIEW'], true) ? 'Your submission is being reviewed' : 'Complete the items below'));
                        $modalGuidanceCopy = $modalStatus === 'APPROVED' ? 'No further action is needed. This station has approved your clearance.' : ($modalStatus === 'RETURNED' || $hasReturnedRequirement ? 'Read the highlighted feedback, correct the item, and submit it again.' : (in_array($modalStatus, ['SUBMITTED', 'UNDER_REVIEW'], true) ? 'No need to submit again. You will be notified after the office reviews it.' : 'Check each required item and follow its submission instructions.'));
                        if ($isHistoricalRecord) {
                            $modalGuidanceTitle = 'Saved semester status';
                            $modalGuidanceCopy = 'These are the recorded decisions for this semester. Submissions are closed.';
                        } elseif ($station['code'] === 'BOOKSTORE' && $allModalRequirementsComplete && $modalStatus !== 'APPROVED') {
                            $modalGuidanceTitle = 'Required items complete';
                            $modalGuidanceCopy = 'Your event fees are verified. No further receipt submission is needed.';
                        } ?>
                        <div class="requirements-overview requirements-overview--<?= escapeHtml($modalOverviewState) ?>">
                            <span class="requirements-overview__icon" aria-hidden="true"><?php if ($modalStatus === 'APPROVED'): ?>&#10003;<?php elseif ($modalStatus === 'RETURNED' || $hasReturnedRequirement): ?>!<?php else: ?>&#8594;<?php endif; ?></span>
                            <div class="requirements-overview__copy"><strong><?= escapeHtml($modalGuidanceTitle) ?></strong><span><?= escapeHtml($modalGuidanceCopy) ?></span></div>
                            <span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $modalStatus))) ?>"><?= escapeHtml(studentStatusLabel($modalStatus)) ?></span>
                        </div>
                        <?php if ($station['code'] === 'DEAN' && $evaluationApplicable && in_array($evaluation['status'] ?? '', ['RETURNED', 'REJECTED', 'ERROR'], true)): ?>
                            <p class="notice warning"><?= escapeHtml((string) ($evaluation['return_reason'] ?? ($evaluation['status'] === 'ERROR' ? 'The previous verification was unavailable. Please submit your screenshots again.' : 'Please check your screenshots and submit corrected proof.'))) ?></p>
                        <?php endif; ?>
                        <?php if ($modalRequirements === []): ?>
                            <?php if (!in_array($station['code'], ['BOOKSTORE', 'ACCOUNTING', 'LIBRARY'], true)): ?>
                                <div class="requirements-empty"><span aria-hidden="true">&#10003;</span><div><strong>No document upload is needed</strong><p>This office handles its requirement directly. Check the status above for your next step.</p></div></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="requirements-section-heading">
                                <div><span class="requirements-section-heading__step">1</span><div><h3>Required items</h3><p>Make sure every item is complete.</p></div></div>
                                <span class="requirements-count"><?= $completedRequirementCount ?> of <?= count($modalRequirements) ?> complete</span>
                            </div>
                            <ul class="requirements requirements-checklist">
                                <?php foreach ($modalRequirements as $requirementIndex => $requirement): ?>
                                    <?php $requirementStatus = (string) ($requirement['status'] ?? 'NOT_STARTED');
                                    $requirementStateClass = in_array($requirementStatus, $completedRequirementStatuses, true) ? 'is-complete' : (in_array($requirementStatus, ['RETURNED', 'REJECTED', 'ERROR'], true) ? 'needs-attention' : 'is-pending'); ?>
                                    <li class="requirement-card <?= $requirementStateClass ?>">
                                        <span class="requirement-card__number" aria-hidden="true"><?= in_array($requirementStatus, $completedRequirementStatuses, true) ? '&#10003;' : (int) $requirementIndex + 1 ?></span>
                                        <div class="requirement-card__content">
                                            <div class="requirement-card__heading"><strong><?= escapeHtml($requirement['name']) ?></strong><span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $requirementStatus))) ?>"><?= escapeHtml(studentStatusLabel($requirementStatus)) ?></span></div>
                                            <?php if (!empty($requirement['description'])): ?><p><?= escapeHtml($requirement['description']) ?></p><?php endif; ?>
                                            <?php if (in_array($requirementStatus, ['RETURNED', 'REJECTED', 'ERROR'], true) && !empty($requirement['remarks'])): ?><div class="requirement-feedback"><strong>What to fix</strong><span><?= escapeHtml($requirement['remarks']) ?></span></div><?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($canSubmit && $station['code'] === 'DEAN' && $evaluationApplicable && $modalStatus !== 'APPROVED' && in_array(($evaluation['status'] ?? 'NOT_SUBMITTED'), ['NOT_SUBMITTED', 'RETURNED', 'REJECTED', 'ERROR'], true)): ?>
                                <div class="requirements-section-heading requirements-section-heading--action"><div><span class="requirements-section-heading__step">2</span><div><h3>Submit your proof</h3><p>Upload clear images for Dean review.</p></div></div></div>
                                <form class="upload-form submission-panel" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/evaluation_action.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                    <label for="modal_evaluation_proofs"><strong>Upload evaluation screenshots</strong></label>
                                    <input id="modal_evaluation_proofs" name="evaluation_proofs[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-file-input data-maximum-files="3">
                                    <p class="file-selection-status" data-file-status aria-live="polite">No files selected</p>
                                    <p class="muted">Choose 1–3 screenshots. JPG, PNG, or WebP; 8 MB maximum per image. The CCS Dean reviews them anonymously.</p>
                                    <button class="primary-button" type="submit">Submit screenshots</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
        <?php if ($station['code'] === 'ACCOUNTING'): ?>
            <div class="accounting-submission-box requirement-action-box">
                <span class="action-kicker"><?= $canSubmit ? 'What to submit' : 'Semester record' ?></span>
                <h3><?= $canSubmit ? 'Submit your TLC portal screenshot' : 'Accounting assessment' ?></h3>
                <?php if ($canSubmit): ?>
                <p>Open the <strong>Assessment of Fees</strong> page in your TLC Student Portal and take one complete, readable screenshot.</p>
                <div class="submission-checklist" aria-label="Screenshot must show"><strong>Your screenshot must clearly show:</strong><ul><li>Full name</li><li>School year and semester</li><li>Total charges and payments</li><li>Remaining balance</li></ul></div>
                <?php endif; ?>
                <?php $accountingSubmissionPending = $accountingReceipt !== false && $accountingReceipt['status'] === 'SCANNED'; ?>
                <?php if (!$canSubmit): ?>
                    <p class="notice">Saved Accounting status: <?= escapeHtml(studentStatusLabel($modalStatus)) ?>.</p>
                <?php elseif ($modalStatus === 'APPROVED'): ?>
                    <p class="notice">Your Accounting clearance has been approved.</p>
                <?php else: ?>
                    <?php if ($accountingSubmissionPending): ?><p class="notice">Your screenshot has already been submitted and is awaiting manual Accounting review.</p><?php endif; ?>
                    <?php if ($accountingReceipt !== false && $accountingReceipt['status'] === 'ISSUED' && str_starts_with($accountingReceipt['receipt_code'], 'PORTAL-') && $modalStatus === 'RETURNED'): ?>
                        <p class="notice warning">Your screenshot was rejected: <?= escapeHtml((string) ($stationStatuses[(int) $station['id']]['returned_reason'] ?? 'Please upload a corrected screenshot.')) ?></p>
                    <?php endif; ?>
                    <form class="upload-form submission-panel" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/accounting_receipt_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <label for="modal_accounting_receipt"><strong>TLC Student Portal assessment screenshot</strong></label>
                        <input id="modal_accounting_receipt" name="accounting_receipt" type="file" accept="image/jpeg,image/png,image/webp" required data-file-input <?= $accountingSubmissionPending ? 'disabled' : '' ?>>
                        <p class="file-selection-status" data-file-status aria-live="polite"><?= $accountingSubmissionPending ? 'Submission received' : 'No file selected' ?></p>
                        <p class="muted">JPG, PNG, or WebP; maximum 8 MB. Accounting will check your submission against official records before approving clearance.</p>
                        <button class="primary-button" type="submit" <?= $accountingSubmissionPending ? 'disabled' : '' ?>><?= $accountingSubmissionPending ? 'Screenshot Submitted' : 'Submit Screenshot' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
                        <?php if ($station['code'] === 'LIBRARY'): ?>
                            <div class="library-card-box requirement-action-box">
                                <span class="action-kicker"><?= $canSubmit ? 'What to submit' : 'Semester record' ?></span>
                                <h3><?= $canSubmit ? 'Capture your library card' : 'Library card' ?></h3>
                                <?php $librarySubmissionStatus = (string) ($libraryCardSubmission['submission_status'] ?? ''); ?>
                                <?php if (!$canSubmit): ?>
                                    <p class="notice">Saved Library status: <?= escapeHtml(studentStatusLabel($modalStatus)) ?>.</p>
                                <?php elseif ($modalStatus === 'APPROVED' || ($libraryCardSubmission['card_status'] ?? '') === 'VERIFIED'): ?>
                                    <p class="notice">Your library card has been confirmed by the Library.</p>
                                <?php elseif ($librarySubmissionStatus === 'SUBMITTED'): ?>
                                    <p class="notice">Your captured library card is waiting for Library confirmation.</p>
                                <?php else: ?>
                                    <?php if ($librarySubmissionStatus === 'RETURNED'): ?><p class="notice warning">Please capture your card again: <?= escapeHtml((string) ($libraryCardSubmission['return_reason'] ?? 'The previous photo was not accepted.')) ?></p><?php endif; ?>
                                    <form class="library-card-capture" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/library_card_action.php')) ?>" data-library-card-capture>
                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                        <input type="hidden" name="capture_source" value="live_camera">
                                        <video playsinline muted hidden aria-label="Library card camera preview"></video>
                                        <canvas hidden></canvas>
                                        <img class="library-card-preview" hidden alt="Captured library card preview">
                                        <p class="upload-guidance"><strong>Before you capture:</strong> Place the front of your physical library card on a flat, well-lit surface. Keep all four corners and the printed details visible. Gallery uploads are not allowed.</p>
                                        <div class="library-card-buttons"><button type="button" data-open-camera>Open Camera</button><button type="button" data-capture-photo hidden>Capture Card</button><button class="secondary" type="button" data-retake-photo hidden>Retake</button><button class="primary-button" type="submit" data-submit-photo disabled>Submit to Library</button></div>
                                        <p class="library-camera-status muted" aria-live="polite"></p>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($station['code'] === 'BOOKSTORE'): ?>
                            <?php $bookstoreRequirementsComplete = $allModalRequirementsComplete;
                            $bookstoreIsCleared = $modalStatus === 'APPROVED'
                                || (($stationStatuses[(int) $station['id']]['status'] ?? '') === 'APPROVED')
                                || $bookstoreRequirementsComplete; ?>
                            <div class="bookstore-fee-box requirement-action-box">
                                <span class="action-kicker"><?= $bookstoreIsCleared ? 'Requirement complete' : ($canSubmit ? 'What to submit' : 'Semester record') ?></span>
                                <h3>Event fee receipts</h3>
                                <?php if ($bookstoreIsCleared): ?>
                                    <p class="notice">Your required event fees have been verified. No further receipt submission is needed.</p>
                                <?php elseif ($bookstoreFees === []): ?>
                                    <p class="notice">The Bookstore has not posted any event fees for this semester.</p>
                                <?php else: ?>
                                    <p><?= $canSubmit ? 'Submit one clear receipt image for each event below. Every receipt must be confirmed by the Bookstore before you can be cleared.' : 'Saved event fee receipt statuses for this semester.' ?></p>
                                    <div class="bookstore-fee-list">
                                    <?php foreach ($bookstoreFees as $fee): ?>
                                        <article class="bookstore-fee-item">
                                            <div class="bookstore-fee-heading"><div><strong><?= escapeHtml($fee['name']) ?></strong><div class="muted">Required receipt &middot; PHP <?= number_format((float) $fee['amount'], 2) ?></div></div>
                                            <?php if ($fee['payment_status'] === 'PAID'): ?><span class="badge approved">CONFIRMED</span><?php elseif ($fee['submission_status'] === 'SUBMITTED'): ?><span class="badge under-review">AWAITING REVIEW</span><?php elseif ($fee['submission_status'] === 'LOST_REPORTED'): ?><span class="badge under-review">LOST RECEIPT REVIEW</span><?php elseif ($fee['submission_status'] === 'RETURNED'): ?><span class="badge returned">RETURNED</span><?php else: ?><span class="badge not-submitted">NOT SUBMITTED</span><?php endif; ?></div>
                                            <?php if ($fee['submission_status'] === 'RETURNED' && !empty($fee['return_reason'])): ?><p class="notice warning">Action needed: <?= escapeHtml($fee['return_reason']) ?></p><?php endif; ?>
                                            <?php if ($canSubmit && $fee['payment_status'] !== 'PAID' && !in_array($fee['submission_status'] ?? '', ['SUBMITTED', 'LOST_REPORTED'], true)): ?>
                                                <div class="bookstore-receipt-actions">
                                                    <form class="upload-form submission-panel" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/bookstore_receipt_action.php')) ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_fee_id" value="<?= (int) $fee['student_fee_id'] ?>">
                                                        <label><strong>Upload <?= escapeHtml($fee['name']) ?> receipt</strong><input name="receipt_image" type="file" accept="image/jpeg,image/png,image/webp" required data-file-input></label>
                                                        <p class="file-selection-status" data-file-status aria-live="polite">No file selected</p>
                                                        <p class="muted">One clear JPG, PNG, or WebP image; maximum 8 MB.</p><button class="primary-button" name="action" value="upload">Submit Receipt</button>
                                                    </form>
                                                    <form method="post" action="<?= escapeHtml(applicationUrl('/student/bookstore_receipt_action.php')) ?>" onsubmit="return confirm('Report this receipt as lost? The Bookstore must confirm your report.');">
                                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_fee_id" value="<?= (int) $fee['student_fee_id'] ?>"><button class="lost-receipt-button" name="action" value="lost">Report Lost Receipt</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($fee['payment_status'] !== 'PAID' && !empty($fee['submitted_at'])): ?><p class="muted">Submitted <?= escapeHtml(date('M j, Y g:i A', strtotime((string) $fee['submitted_at']))) ?>.<?= $canSubmit ? ' Please wait for Bookstore confirmation.' : '' ?></p><?php endif; ?>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canSubmit && $station['code'] === 'CLINIC' && $medicalStatus === 'RETURNED'): ?>
                            <div class="medical-schedule-box requirement-action-box">
                                <span class="action-kicker">Action required</span>
                                <h3>Schedule your medical appointment</h3>
                                <?php if ($medicalAppointment !== false): ?>
                                    <p class="notice">Your appointment is scheduled for <strong><?= escapeHtml(date('F j, Y', strtotime($medicalAppointment['schedule_date']))) ?>, <?= escapeHtml(date('g:i A', strtotime($medicalAppointment['schedule_time']))) ?>–<?= escapeHtml(date('g:i A', strtotime($medicalAppointment['end_time']))) ?></strong>.</p>
                                <?php elseif ($medicalSchedules === []): ?>
                                    <p class="notice warning">The Clinic has not posted an available medical schedule yet. Please check again later.</p>
                                <?php else: ?>
                                    <ul class="medical-schedule-list">
                                        <?php foreach ($medicalSchedules as $schedule): ?>
                                            <?php $remainingSlots = max(0, (int) $schedule['capacity'] - (int) $schedule['booked_count']); ?>
                                            <li>
                                                <div><strong><?= escapeHtml(date('F j, Y', strtotime($schedule['schedule_date']))) ?></strong><div class="muted"><?= escapeHtml(date('g:i A', strtotime($schedule['schedule_time']))) ?>–<?= escapeHtml(date('g:i A', strtotime($schedule['end_time']))) ?> · <?= $remainingSlots ?> slot(s) left</div></div>
                                                <?php if ($remainingSlots > 0): ?>
                                                    <form method="post" action="<?= escapeHtml(applicationUrl('/student/medical_schedule_action.php')) ?>" onsubmit="return confirm('Schedule your medical appointment on this date?');">
                                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                        <input type="hidden" name="schedule_id" value="<?= (int) $schedule['id'] ?>">
                                                        <button class="primary-button" type="submit">Schedule Medical</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="schedule-full">FULL</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count(array_filter($medicalSchedules, static fn(array $schedule): bool => (int) $schedule['booked_count'] < (int) $schedule['capacity'])) === 0): ?>
                                        <p class="notice warning">All posted medical schedules are full. Please wait for the Clinic to add another schedule.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
    <?php if ($isEnrolled && !$isHistoricalRecord): ?>
        <div class="portal-scanner-modal" data-attendance-scanner-modal hidden aria-hidden="true">
            <div class="portal-scanner-modal__backdrop" data-close-attendance-scanner></div>
            <section class="portal-scanner-modal__dialog" role="dialog" aria-modal="true" aria-label="ClearGo Attendance Scanner" tabindex="-1">
                <button class="portal-scanner-modal__close" type="button" data-close-attendance-scanner aria-label="Close attendance scanner">&times;</button>
                <iframe title="ClearGo Attendance Scanner" allow="camera" data-scanner-src="<?= escapeHtml(applicationUrl('/student/attendance_scanner.php?embed=1')) ?>"></iframe>
            </section>
        </div>
    <?php endif; ?>
    <div class="portal-password-modal" data-password-modal hidden aria-hidden="true">
        <div class="portal-password-modal__backdrop" data-close-password-modal></div>
        <section class="portal-password-modal__dialog" role="dialog" aria-modal="true" aria-label="Change password" tabindex="-1">
            <button class="portal-password-modal__close" type="button" data-close-password-modal aria-label="Close change password">&times;</button>
            <iframe title="Change password" data-password-src="<?= escapeHtml(applicationUrl('/student/password.php?embed=1')) ?>"></iframe>
        </section>
    </div>
    <nav class="portal-mobile-nav" aria-label="Mobile navigation"><?php foreach ($dashboardNavigation as $viewName => [$icon, $label]): ?><a href="#<?= $viewName ?>" data-dashboard-link="<?= $viewName ?>" <?= $viewName === 'overview' ? 'aria-current="page"' : '' ?>><?= studentDashboardIcon($icon) ?><span><?= $label ?></span></a><?php endforeach; ?></nav>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/student-dashboard.js')) ?>?v=<?= escapeHtml($portalScriptVersion) ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/library-card-camera.js')) ?>"></script>
</body>

</html>
