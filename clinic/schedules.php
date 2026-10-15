<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

$user = requireRole('CLINIC_STAFF');

try {
    $database = getDatabaseConnection();
    $period = $database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    if ($period === false) {
        throw new RuntimeException('There is no active semester.');
    }

    $scheduleQuery = $database->prepare(
        "SELECT schedule.id,schedule.schedule_date,schedule.schedule_time,schedule.end_time,schedule.capacity,schedule.status,
                COUNT(appointment.id) AS booked_count
         FROM clinic_medical_schedules schedule
         LEFT JOIN clinic_medical_appointments appointment ON appointment.schedule_id=schedule.id
            AND appointment.status='SCHEDULED'
         WHERE schedule.academic_period_id=:period_id
         GROUP BY schedule.id,schedule.schedule_date,schedule.schedule_time,schedule.end_time,schedule.capacity,schedule.status
         ORDER BY schedule.schedule_date,schedule.schedule_time"
    );
    $scheduleQuery->execute(['period_id' => $period['id']]);
    $schedules = $scheduleQuery->fetchAll();

    $selectedSchedule = null;
    $bookings = [];
    $selectedScheduleId = filter_var($_GET['schedule'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($selectedScheduleId !== false) {
        foreach ($schedules as $schedule) {
            if ((int) $schedule['id'] === (int) $selectedScheduleId) {
                $selectedSchedule = $schedule;
                break;
            }
        }

        if ($selectedSchedule !== null) {
            $bookingQuery = $database->prepare(
                "SELECT student.student_number,student.first_name,student.last_name,student.program,
                        student.year_level,organization.code AS college,appointment.booked_at
                 FROM clinic_medical_appointments appointment
                 INNER JOIN students student ON student.id=appointment.student_id
                 INNER JOIN organizations organization ON organization.id=student.organization_id
                 WHERE appointment.schedule_id=:schedule_id AND appointment.status='SCHEDULED'
                 ORDER BY student.last_name,student.first_name"
            );
            $bookingQuery->execute(['schedule_id' => $selectedScheduleId]);
            $bookings = $bookingQuery->fetchAll();
        }
    }
    $notifications = dashboardNotifications($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Clinic schedules failed to load: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load Clinic medical schedules.');
}

$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$sidebar = staffDashboardSidebar('Clinic Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/clinic/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'schedules', 'label' => 'Medical Schedules', 'url' => '/clinic/schedules.php', 'icon' => 'document'],
], 'schedules', $logoutForm);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medical Schedules | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/clinic-schedules.css')) ?>">
</head>
<body class="staff-dashboard">
<div class="staff-shell">
    <?= $sidebar ?>
    <div class="staff-workspace">
        <header class="staff-topbar">
            <button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
            <div class="topbar-title"><h1>Medical Schedules</h1><p>Create appointment dates for students without medical records.</p></div>
            <div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div>
        </header>
        <main class="staff-main">
            <?php if ($message !== null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
            <section class="panel">
                <div class="section-heading"><div><h2>Add a medical schedule</h2><p class="muted">Set the date, time, and maximum number of students.</p></div></div>
                <form class="controls" method="post" action="<?= escapeHtml(applicationUrl('/clinic/schedule_action.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <label>Date<input type="date" name="schedule_date" min="<?= escapeHtml(date('Y-m-d')) ?>" required></label>
                    <label>Start time<input type="time" name="start_time" required></label>
                    <label>End time<input type="time" name="end_time" required></label>
                    <label>Student limit<input type="number" name="capacity" min="1" max="500" required></label>
                    <button type="submit">Add Schedule</button>
                </form>
            </section>
            <section class="panel">
                <div class="section-heading"><div><h2>Posted schedules</h2><p class="muted"><?= escapeHtml($period['semester'] . ' Semester A.Y. ' . $period['school_year']) ?></p></div></div>
                <?php if ($schedules === []): ?><p class="muted">No medical schedules have been posted.</p><?php else: ?>
                    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Time</th><th>Booked</th><th>Status</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($schedules as $schedule): ?><tr>
                        <td><?= escapeHtml(date('F j, Y', strtotime($schedule['schedule_date']))) ?></td>
                        <td><?= escapeHtml(date('g:i A', strtotime($schedule['schedule_time']))) ?>–<?= escapeHtml(date('g:i A', strtotime($schedule['end_time']))) ?></td>
                        <td><?= (int) $schedule['booked_count'] ?> / <?= (int) $schedule['capacity'] ?></td>
                        <td><span class="badge <?= strtolower($schedule['status']) ?>"><?= escapeHtml($schedule['status']) ?></span></td>
                        <td><div class="schedule-actions"><a class="booking-link" href="?schedule=<?= (int) $schedule['id'] ?>#booked-students">View Bookings</a><?php if ($schedule['status'] === 'ACTIVE'): ?><form method="post" action="<?= escapeHtml(applicationUrl('/clinic/schedule_action.php')) ?>" onsubmit="return confirm('Close this medical schedule?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="schedule_id" value="<?= (int) $schedule['id'] ?>"><button class="secondary" name="action" value="close">Close</button></form><?php else: ?><span class="muted">Closed</span><?php endif; ?></div></td>
                    </tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <?php if ($selectedSchedule !== null): ?>
                <section class="panel" id="booked-students">
                    <div class="section-heading"><div><h2>Booked students</h2><p class="muted"><?= escapeHtml(date('F j, Y', strtotime($selectedSchedule['schedule_date']))) ?>, <?= escapeHtml(date('g:i A', strtotime($selectedSchedule['schedule_time']))) ?> to <?= escapeHtml(date('g:i A', strtotime($selectedSchedule['end_time']))) ?> &middot; <?= count($bookings) ?> of <?= (int) $selectedSchedule['capacity'] ?> booked</p></div></div>
                    <?php if ($bookings === []): ?>
                        <p class="muted">No students have booked this medical schedule yet.</p>
                    <?php else: ?>
                        <div class="table-wrap"><table><thead><tr><th>No.</th><th>Student ID</th><th>Student Name</th><th>Program</th><th>Year Level</th><th>College</th><th>Booked At</th></tr></thead><tbody>
                        <?php foreach ($bookings as $index => $booking): ?><tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= escapeHtml($booking['student_number']) ?></td>
                            <td><?= escapeHtml($booking['last_name'] . ', ' . $booking['first_name']) ?></td>
                            <td><?= escapeHtml($booking['program']) ?></td>
                            <td>Year <?= (int) $booking['year_level'] ?></td>
                            <td><?= escapeHtml($booking['college']) ?></td>
                            <td><?= escapeHtml(date('M j, Y g:i A', strtotime($booking['booked_at']))) ?></td>
                        </tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
</body>
</html>
