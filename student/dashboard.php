<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

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

    $periodQuery = $database->prepare(
        "SELECT DISTINCT period.id,period.school_year,period.semester,period.status,period.clearance_status
         FROM academic_periods period
         LEFT JOIN clearances clearance ON clearance.academic_period_id=period.id AND clearance.student_id=:student_id
         WHERE period.status='ACTIVE' OR clearance.id IS NOT NULL
         ORDER BY period.id DESC"
    );
    $periodQuery->execute(['student_id' => $student['id']]);
    $availablePeriods = $periodQuery->fetchAll();
    $period = $availablePeriods[0] ?? false;
    $requestedPeriodId = filter_var($_GET['period'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($requestedPeriodId !== false) {
        foreach ($availablePeriods as $candidatePeriod) {
            if ((int) $candidatePeriod['id'] === (int) $requestedPeriodId) { $period = $candidatePeriod; break; }
        }
    }
    $isEnrolled = false;
    if ($period !== false) {
        $enrollmentQuery = $database->prepare("SELECT COUNT(*) FROM semester_enrollments WHERE academic_period_id=:period_id AND student_id=:student_id AND status='ENROLLED'");
        $enrollmentQuery->execute(['period_id' => $period['id'], 'student_id' => $student['id']]);
        $isEnrolled = (int) $enrollmentQuery->fetchColumn() === 1;
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
         WHERE station.status = :status
         ORDER BY workflow_order,
                  CASE WHEN parent.id IS NULL THEN 0 ELSE 1 END,
                  station.sort_order"
    );
    $stationQuery->execute([
        'status' => 'ACTIVE',
        'is_cbe_display' => $isCbeStudent ? 1 : 0,
        'is_cbe_child' => $isCbeStudent ? 1 : 0,
        'is_cbe_order' => $isCbeStudent ? 1 : 0,
    ]);
    $stations = $stationQuery->fetchAll();

    $clearance = false;
    $stationStatuses = [];
    $evaluation = false;
    $evaluationApplicable = false;
    if ($period !== false && $period['clearance_status'] === 'OPEN') {
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
            if ($evaluationSubmission !== false) { $evaluation = $evaluationSubmission; }

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
    if ($clearance !== false) {
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
        foreach ($allRequirementsQuery->fetchAll() as $requirement) {
            $stationRequirements[(int) $requirement['station_id']][] = $requirement;
        }
    }

    if ($selectedStation !== null && $selectedStation['code'] === 'ACCOUNTING' && $clearance !== false && $period !== false) {
        $receiptQuery = $database->prepare(
            "SELECT receipt.id, receipt.receipt_code, receipt.status, receipt.scanned_at,
                    verification.status AS verification_status, verification.attempt_count, verification.last_submitted_at
             FROM accounting_receipts receipt
             LEFT JOIN accounting_receipt_verifications verification ON verification.accounting_receipt_id=receipt.id
             WHERE receipt.student_id=:student_id AND receipt.academic_period_id=:period_id
             ORDER BY receipt.id DESC LIMIT 1"
        );
        $receiptQuery->execute(['student_id' => $student['id'], 'period_id' => $period['id']]);
        $accountingReceipt = $receiptQuery->fetch();
    }

    $notificationQuery = $database->prepare(
        'SELECT title, message, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT 10'
    );
    $notificationQuery->execute(['user_id' => $user['id']]);
    $notifications = $notificationQuery->fetchAll();
    $unreadNotificationQuery = $database->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
    );
    $unreadNotificationQuery->execute(['user_id' => $user['id']]);
    $unreadNotificationCount = (int) $unreadNotificationQuery->fetchColumn();
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
        default => str_replace('_', ' ', $status),
    };
}

$displayStationStatuses = $stationStatuses;
$stationIdByCode = [];
foreach ($stations as $station) { $stationIdByCode[$station['code']] = (int) $station['id']; }
$rawStationStatus = static function (string $code) use ($stationIdByCode, $stationStatuses): string {
    $stationId = $stationIdByCode[$code] ?? 0;
    return $stationStatuses[$stationId]['status'] ?? 'LOCKED';
};
$setDisplayStatus = static function (string $code, string $status) use (&$displayStationStatuses, $stationIdByCode): void {
    $stationId = $stationIdByCode[$code] ?? 0;
    if ($stationId > 0) { $displayStationStatuses[$stationId]['status'] = $status; }
};

// Never reveal an early approval ahead of its clearance sequence. CBE starts at
// Organization/YBC; every other college starts at the Dean.
$deanComplete = $rawStationStatus('DEAN') === 'APPROVED';
$organizationComplete = $rawStationStatus('ORGANIZATION') === 'APPROVED';
if ($isCbeStudent && !$organizationComplete) {
    foreach (['DEAN', 'SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
} elseif (!$isCbeStudent && !$deanComplete) {
    foreach (['ORGANIZATION', 'SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
} elseif (!$deanComplete) {
    foreach (['SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
} elseif (!$organizationComplete) {
    foreach (['SASO', 'STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC', 'FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
} else {
    $sasoComplete = $rawStationStatus('SASO') === 'APPROVED';
    if (!$sasoComplete) {
        foreach (['FACILITIES', 'COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
    } else {
        $facilitiesComplete = $rawStationStatus('FACILITIES') === 'APPROVED';
        if (!$facilitiesComplete) {
            foreach (['COMLAB', 'ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
        } else {
            $comlabComplete = $rawStationStatus('COMLAB') === 'APPROVED';
            if (!$comlabComplete) {
                foreach (['ACCOUNTING', 'REGISTRAR'] as $code) { $setDisplayStatus($code, 'LOCKED'); }
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
    ? 'No active academic period'
    : ($semesterLabels[$period['semester']] ?? $period['semester']) . ' A.Y. ' . $period['school_year'];
$detailStatus = $selectedStation === null
    ? null
    : ($displayStationStatuses[(int) $selectedStation['id']]['status'] ?? 'LOCKED');
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard | Digital Clearance System</title>
    <style>
        :root { --blue:#0d5ea6; --dark:#17324d; --line:#d8e3ed; --page:#f3f8fd; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page); color:var(--dark); font-family:Arial,sans-serif; }
        header { padding:18px 24px; background:#fff; border-bottom:1px solid var(--line); }
        header>div,main { max-width:1000px; margin:auto; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .header-actions { display:flex; align-items:center; gap:14px; }
        .logout-form { margin:0; }
        .logout-form button,.primary-button { padding:10px 16px; border:0; border-radius:5px; background:var(--blue); color:#fff; cursor:pointer; font:inherit; }
        h1 { margin:0; color:var(--blue); font-size:24px; }
        h2 { margin-top:0; font-size:19px; }
        h3 { margin:0; font-size:16px; }
        main { padding:24px; }
        .grid { display:grid; grid-template-columns:1.1fr .9fr; gap:20px; }
        .panel { padding:22px; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .profile { display:grid; gap:10px; }
        dt,.muted { color:#587086; font-size:13px; }
        dd { margin:2px 0 0; font-weight:600; }
        .notice { margin:0; padding:14px; background:#edf6ff; border-left:4px solid var(--blue); }
        .flash { margin-bottom:20px; }
        .warning { background:#fff7e8; border-left-color:#b87800; }
        .progress { height:12px; overflow:hidden; background:#dce8f1; border-radius:99px; }
        .progress span { display:block; height:100%; background:var(--blue); }
        .stations,.requirements,.notification-list { list-style:none; margin:0; padding:0; }
        .station,.requirements li,.notification-list li { padding:14px 0; border-top:1px solid var(--line); }
        .station:first-child,.requirements li:first-child,.notification-list li:first-child { border-top:0; }
        .station { display:flex; align-items:center; gap:14px; }
        .station-info { flex:1; }
        .step { padding:7px 10px; border-radius:50%; background:#e8f2fb; color:var(--blue); font-weight:bold; }
        .badge { padding:6px 9px; border-radius:99px; font-size:12px; font-weight:bold; white-space:nowrap; }
        .approved,.verified { background:#e4f5eb; color:#176b3a; }
        .pending,.under-review,.eligible { background:#edf4fb; color:#165b94; }
        .locked,.not-submitted,.error { background:#eef1f4; color:#536473; }
        .returned,.rejected { background:#fff0ed; color:#a33722; }
        .unread { border-left:3px solid var(--blue); padding-left:12px!important; }
        .notification-wrap { position:relative; }
        .notification-button { position:relative; display:grid; place-items:center; width:42px; height:42px; padding:0; border:1px solid var(--line); border-radius:50%; background:#fff; color:var(--dark); cursor:pointer; }
        .notification-button svg { width:22px; height:22px; }
        .notification-count { position:absolute; top:-5px; right:-5px; min-width:20px; height:20px; padding:0 5px; border:2px solid #fff; border-radius:99px; background:#c33b27; color:#fff; font-size:11px; font-weight:bold; line-height:16px; text-align:center; }
        .notification-box { position:absolute; z-index:20; top:50px; right:0; width:min(360px,calc(100vw - 32px)); max-height:420px; overflow:auto; padding:8px 16px; border:1px solid var(--line); border-radius:10px; background:#fff; box-shadow:0 12px 32px rgba(23,50,77,.18); }
        .notification-box[hidden] { display:none; }
        .notification-box h2 { margin:8px 0; font-size:17px; }
        .notification-list li { font-size:14px; }
        .notification-list li strong { display:block; margin-bottom:4px; }
        .upload-form { margin-top:16px; }
        .upload-form input[type=file] { display:block; margin:8px 0 14px; max-width:100%; }
        a { color:var(--blue); font-weight:bold; text-decoration:none; }
        @media(max-width:700px) { header,main { padding:16px; } .topbar { align-items:flex-start; flex-wrap:wrap; } .header-actions { width:100%; justify-content:flex-end; } .grid { grid-template-columns:1fr; } .station { align-items:flex-start; flex-wrap:wrap; } .station a { width:100%; } }
    </style>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/student-dashboard.css')) ?>">
</head>
<body class="student-dashboard">
    <header class="student-header"><div class="student-topbar"><a class="student-brand" href="<?= escapeHtml(applicationUrl('/student/dashboard.php')) ?>"><img src="<?= escapeHtml(clearGoLogoUrl()) ?>" alt="The Lewis College logo"><span class="student-brand-copy"><strong>ClearGo</strong><small>Student Portal</small></span></a><div class="header-actions">
        <div class="notification-wrap">
            <button class="notification-button" id="notificationButton" type="button" aria-label="Notifications<?= $unreadNotificationCount > 0 ? ' (' . $unreadNotificationCount . ' unread)' : '' ?>" aria-controls="notificationBox" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6v-5a7 7 0 0 0-5-6.71V3a2 2 0 0 0-4 0v1.29A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
                <?php if ($unreadNotificationCount > 0): ?><span class="notification-count"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span><?php endif; ?>
            </button>
            <div class="notification-box" id="notificationBox" hidden>
                <h2>Notifications</h2>
                <?php if ($notifications === []): ?><p class="muted">You have no notifications yet.</p><?php else: ?><ul class="notification-list"><?php foreach ($notifications as $notification): ?><li class="<?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>"><strong><?= escapeHtml($notification['title']) ?></strong><div><?= escapeHtml($notification['message']) ?></div><div class="muted"><?= escapeHtml($notification['created_at']) ?></div></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
        </div>
        <a class="header-link" href="<?= escapeHtml(applicationUrl('/student/password.php')) ?>" aria-label="Change password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 7a5 5 0 1 0-4.6 5H13l2 2 2-2 2 2 2-2-4.4-4.4A5 5 0 0 0 15 7Z"/></svg><span>Change Password</span></a><?= $logoutForm ?>
    </div></div></header>
    <main class="student-main">
        <section class="student-hero" aria-labelledby="student-dashboard-title"><div><p class="eyebrow">Student clearance</p><h1 id="student-dashboard-title">Welcome, <?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></h1><p>Track your requirements and see what needs attention.</p></div><?php if ($clearance !== false): ?><span class="overall-status"><?= escapeHtml(studentStatusLabel($clearance['status'])) ?></span><?php endif; ?></section>
        <?php if ($message !== null): ?><p class="notice flash"><?= escapeHtml($message) ?></p><?php endif; ?>
        <div class="grid">
            <section class="panel profile-card">
                <div class="profile-heading"><span class="profile-avatar"><?= escapeHtml(strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1))) ?></span><div><h2><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></h2><p><?= escapeHtml($student['student_number']) ?></p></div></div>
                <dl class="profile">
                    <div><dt>Program</dt><dd><?= escapeHtml($student['program']) ?></dd></div>
                    <?php $section = trim((string) ($student['section'] ?? '')); ?>
                    <div><dt><?= $section === '' || strtoupper($section) === 'N/A' ? 'Year Level' : 'Year and Section' ?></dt><dd><?= (int) $student['year_level'] ?><?= $section === '' || strtoupper($section) === 'N/A' ? '' : ' - ' . escapeHtml($section) ?></dd></div>
                    <div><dt>Organization</dt><dd><?= escapeHtml($student['organization_name'] . ' (' . $student['organization_code'] . ')') ?></dd></div>
                    <div><dt>Email</dt><dd><?= escapeHtml($student['email']) ?></dd></div>
                </dl>
            </section>
            <section class="panel clearance-card">
                <div class="clearance-card-header"><div><h2>My Clearance</h2><p class="section-copy">Progress for <?= escapeHtml($periodLabel) ?></p></div></div>
                <form class="period-form" method="get"><label for="period">Semester record</label><select id="period" name="period" onchange="this.form.submit()"><?php foreach ($availablePeriods as $availablePeriod): ?><option value="<?= (int) $availablePeriod['id'] ?>" <?= $period !== false && (int) $period['id'] === (int) $availablePeriod['id'] ? 'selected' : '' ?>><?= escapeHtml(($semesterLabels[$availablePeriod['semester']] ?? $availablePeriod['semester']) . ' A.Y. ' . $availablePeriod['school_year']) ?></option><?php endforeach; ?></select></form>
                <?php if ($clearance === false): ?>
                    <?php if ($period === false): ?><p class="notice">No semester record is available.</p><?php elseif (!$isEnrolled): ?><p class="notice warning">You are not included in the enrollment roster for this semester.</p><?php elseif ($period['clearance_status'] !== 'OPEN'): ?><p class="notice">Clearance is not open yet. It will appear after all offices are ready and the Registrar opens the semester.</p><?php else: ?><p class="notice warning">Your semester clearance record is unavailable. Please contact the Registrar.</p><?php endif; ?>
                <?php else: ?>
                    <p class="clearance-reference">Reference <?= escapeHtml($clearance['reference_number']) ?></p>
                    <div class="progress-copy"><strong><?= $approved ?> of <?= count($stations) ?> stations cleared</strong><span><?= count($stations) === 0 ? 0 : (int) round(($approved / count($stations)) * 100) ?>%</span></div>
                    <div class="progress" role="progressbar" aria-label="Clearance progress" aria-valuemin="0" aria-valuemax="<?= count($stations) ?>" aria-valuenow="<?= $approved ?>"><span style="width:<?= count($stations) === 0 ? 0 : ($approved / count($stations)) * 100 ?>%"></span></div>
                    <p class="clearance-status-line"><strong>Overall status</strong><span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $clearance['status']))) ?>"><?= escapeHtml(studentStatusLabel($clearance['status'])) ?></span></p>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel event-panel">
            <h2>Organization Event Attendance</h2>
            <p class="section-copy">Use the ClearGo scanner to scan the QR displayed by your organization officer. Attendance is tied to your student account.</p>
            <p><a class="button-link" href="<?= escapeHtml(applicationUrl('/student/attendance_scanner.php')) ?>">Open ClearGo Attendance Scanner</a></p>
            <?php if ($organizationEvents === []): ?>
                <p class="notice">No required organization events are listed for this semester.</p>
            <?php else: ?>
                <ul class="requirements">
                    <?php foreach ($organizationEvents as $event): ?>
                        <li><strong><?= escapeHtml($event['name']) ?></strong><div class="muted"><?= escapeHtml($event['event_date'] . ($event['start_time'] ? ' · ' . $event['start_time'] : '')) ?></div><?php if ($event['attendance_status'] === 'PRESENT'): ?><span class="badge approved">PRESENT</span><?php else: ?><span class="badge not-submitted">NOT RECORDED</span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel station-panel">
            <h2>Clearance Stations</h2>
            <p class="section-copy">Follow your clearance in order. Available stations can be opened for more details.</p>
            <?php if ($clearance === false): ?>
                <p class="muted">Station statuses and requirements will appear after you start your clearance.</p>
            <?php else: ?>
                <ol class="stations">
                    <?php foreach ($stations as $station): ?>
                        <?php $status = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED'; ?>
                        <li class="station">
                            <span class="step"><?= escapeHtml($station['display_order']) ?></span>
                            <div class="station-info"><h3><?= escapeHtml($station['name']) ?></h3><?php if ($station['parent_name'] !== null): ?><div class="muted">SASO sub-check</div><?php endif; ?></div>
                            <span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $status))) ?>"><?= escapeHtml(studentStatusLabel($status)) ?></span>
                            <?php if ($status !== 'LOCKED'): ?><button class="requirements-button" type="button" data-requirements-modal="requirements-modal-<?= (int) $station['id'] ?>">View requirements</button><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <?php if ($clearance !== false): ?>
            <?php foreach ($stations as $station): ?>
                <?php $modalStatus = $displayStationStatuses[(int) $station['id']]['status'] ?? 'LOCKED'; ?>
                <?php if ($modalStatus === 'LOCKED') { continue; } ?>
                <div class="requirements-modal" id="requirements-modal-<?= (int) $station['id'] ?>" hidden aria-hidden="true">
                    <div class="requirements-modal__backdrop" data-close-requirements-modal></div>
                    <section class="requirements-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="requirements-modal-title-<?= (int) $station['id'] ?>" tabindex="-1">
                        <div class="requirements-modal__header">
                            <div><h2 id="requirements-modal-title-<?= (int) $station['id'] ?>"><?= escapeHtml($station['name']) ?> Requirements</h2><p>Station status: <span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $modalStatus))) ?>"><?= escapeHtml(studentStatusLabel($modalStatus)) ?></span></p></div>
                            <button class="requirements-modal__close" type="button" data-close-requirements-modal aria-label="Close requirements">&times;</button>
                        </div>
                        <?php $modalRequirements = $stationRequirements[(int) $station['id']] ?? []; if ($station['code'] === 'DEAN' && $evaluationApplicable) { $modalRequirements = array_values(array_filter($modalRequirements, static fn(array $requirement): bool => in_array($requirement['requirement_code'] ?? '', ['CCS_FACULTY_EVALUATION', 'CCS_FOURTH_YEAR_FACULTY_EVALUATION'], true))); } ?>
                        <?php if ($modalRequirements === []): ?>
                            <p class="muted">No requirement records are available for this station.</p>
                        <?php else: ?>
                            <ul class="requirements">
                                <?php foreach ($modalRequirements as $requirement): ?>
                                    <li>
                                        <strong><?= escapeHtml($requirement['name']) ?></strong>
                                        <div class="muted"><?= escapeHtml($requirement['description'] ?? '') ?></div>
                                        <div><strong>Status:</strong> <span class="<?= ($requirement['status'] ?? '') === 'RETURNED' ? 'status-returned' : '' ?>"><?= escapeHtml(studentStatusLabel($requirement['status'] ?? 'NOT_STARTED')) ?></span></div>
                                        <?php if (($requirement['status'] ?? '') === 'RETURNED' && !empty($requirement['remarks'])): ?><div class="status-returned">Action needed: <?= escapeHtml($requirement['remarks']) ?></div><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($station['code'] === 'DEAN' && $evaluationApplicable && $modalStatus !== 'APPROVED' && in_array(($evaluation['status'] ?? 'NOT_SUBMITTED'), ['NOT_SUBMITTED', 'RETURNED', 'REJECTED', 'ERROR'], true)): ?>
                                <form class="upload-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/evaluation_action.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                    <label for="modal_evaluation_proofs"><strong>Upload evaluation screenshots</strong></label>
                                    <input id="modal_evaluation_proofs" name="evaluation_proofs[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required onchange="if(this.files.length>3){alert('Choose no more than 3 screenshots.');this.value=''}">
                                    <p class="muted">Choose 1–3 screenshots. JPG, PNG, or WebP; 8 MB maximum per image. The CCS Dean reviews them anonymously.</p>
                                    <button class="primary-button" type="submit">Submit screenshots</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($selectedStation !== null && $selectedStation['code'] === 'DEAN' && $clearance !== false && $detailStatus !== 'LOCKED' && $evaluationApplicable): ?>
            <section class="panel">
                <h2>Faculty Evaluation Verification</h2>
                <?php $evaluationStatus = $evaluation === false ? 'NOT_SUBMITTED' : $evaluation['status']; ?>
                <p><strong>Status:</strong> <span class="badge <?= escapeHtml(strtolower(str_replace('_', '-', $evaluationStatus))) ?>"><?= escapeHtml(studentStatusLabel($evaluationStatus)) ?></span></p>
                <?php if ($evaluationStatus === 'VERIFIED'): ?>
                    <p class="notice">Your Faculty Evaluation completion is verified and ready for Dean review.</p>
                <?php elseif ($detailStatus !== 'APPROVED' && in_array($evaluationStatus, ['NOT_SUBMITTED', 'RETURNED', 'REJECTED', 'ERROR'], true)): ?>
                    <?php if ($evaluationStatus === 'RETURNED'): ?><p class="notice warning"><?= escapeHtml($evaluation['return_reason'] ?? 'Your screenshots need another submission.') ?></p><?php endif; ?>
                    <?php if ($evaluationStatus === 'ERROR'): ?><p class="notice warning">The previous verification attempt was unavailable. The screenshot was not retained.</p><?php endif; ?>
                    <form class="upload-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/evaluation_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <label for="evaluation_proofs"><strong>Evaluation completion screenshots</strong></label>
                        <input id="evaluation_proofs" name="evaluation_proofs[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required onchange="if(this.files.length>3){alert('Choose no more than 3 screenshots.');this.value=''}">
                        <p class="muted">Upload 1 to 3 JPG, PNG, or WebP screenshots (8 MB each). Your name and student number are hidden from the CCS Dean during review.</p>
                        <button class="primary-button" type="submit">Submit for Dean Review</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($selectedStation !== null && $selectedStation['code'] === 'ACCOUNTING' && $clearance !== false && $detailStatus !== 'LOCKED'): ?>
            <section class="panel">
                <h2>Accounting Receipt Verification</h2>
                <?php if ($accountingReceipt === false): ?>
                    <p class="notice">Accounting has not yet issued a receipt code for this clearance. Please contact the Accounting office after payment.</p>
                <?php elseif ($accountingReceipt['status'] === 'APPROVED'): ?>
                    <p class="notice">Your Accounting receipt has been approved.</p>
                <?php elseif ($accountingReceipt['status'] === 'SCANNED'): ?>
                    <p class="notice">Your receipt photo was verified and is waiting for Accounting approval.</p>
                <?php else: ?>
                    <?php if (($accountingReceipt['verification_status'] ?? '') === 'REJECTED'): ?><p class="notice warning">Your previous receipt photo could not be verified. Upload a clear photo of the complete official receipt.</p><?php endif; ?>
                    <?php if (($accountingReceipt['verification_status'] ?? '') === 'ERROR'): ?><p class="notice warning">Receipt verification was unavailable. Your photo was deleted; please try again later.</p><?php endif; ?>
                    <form class="upload-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/accounting_receipt_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <label for="accounting_receipt"><strong>Official Accounting receipt photo</strong></label>
                        <input id="accounting_receipt" name="accounting_receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
                        <p class="muted">JPG, PNG, or WebP; maximum 8 MB. The photo must clearly show the complete paid receipt and Accounting-issued receipt code. It is deleted immediately after AI verification.</p>
                        <button class="primary-button" type="submit">Upload and Verify Receipt</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </main>
    <script>
        const notificationButton = document.getElementById('notificationButton');
        const notificationBox = document.getElementById('notificationBox');
        notificationButton.addEventListener('click', function (event) {
            event.stopPropagation();
            const willOpen = notificationBox.hidden;
            notificationBox.hidden = !willOpen;
            notificationButton.setAttribute('aria-expanded', String(willOpen));
        });
        notificationBox.addEventListener('click', function (event) { event.stopPropagation(); });
        document.addEventListener('click', function () {
            notificationBox.hidden = true;
            notificationButton.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                notificationBox.hidden = true;
                notificationButton.setAttribute('aria-expanded', 'false');
                notificationButton.focus();
            }
        });

        let activeRequirementsModal = null;
        let requirementsModalTrigger = null;
        function closeRequirementsModal() {
            if (activeRequirementsModal === null) return;
            activeRequirementsModal.hidden = true;
            activeRequirementsModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('requirements-modal-open');
            requirementsModalTrigger?.focus();
            activeRequirementsModal = null;
            requirementsModalTrigger = null;
        }
        document.querySelectorAll('[data-requirements-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = document.getElementById(button.dataset.requirementsModal);
                if (modal === null) return;
                activeRequirementsModal = modal;
                requirementsModalTrigger = button;
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('requirements-modal-open');
                modal.querySelector('.requirements-modal__dialog').focus();
            });
        });
        document.querySelectorAll('[data-close-requirements-modal]').forEach(function (button) {
            button.addEventListener('click', closeRequirementsModal);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeRequirementsModal !== null) {
                event.preventDefault();
                closeRequirementsModal();
            }
        });
    </script>
</body>
</html>
