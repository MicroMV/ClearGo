<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('STUDENT');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/student/dashboard.php');
}

$scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($scheduleId === false) {
    setFlashMessage('The selected medical schedule is invalid.');
    redirectTo('/student/dashboard.php');
}

try {
    $database = getDatabaseConnection();
    $database->beginTransaction();

    $studentQuery = $database->prepare("SELECT id,student_number FROM students WHERE user_id=:user_id AND status='ACTIVE' LIMIT 1");
    $studentQuery->execute(['user_id' => $user['id']]);
    $student = $studentQuery->fetch();
    $studentId = (int) ($student['id'] ?? 0);
    if ($studentId < 1) {
        throw new RuntimeException('No active student profile was found.');
    }

    $scheduleQuery = $database->prepare(
        "SELECT schedule.id,schedule.academic_period_id,schedule.schedule_date,schedule.schedule_time,schedule.capacity,schedule.status
         FROM clinic_medical_schedules schedule
         INNER JOIN academic_periods period ON period.id=schedule.academic_period_id AND period.status='ACTIVE'
         WHERE schedule.id=:schedule_id AND TIMESTAMP(schedule.schedule_date,schedule.schedule_time)>NOW()
         FOR UPDATE"
    );
    $scheduleQuery->execute(['schedule_id' => $scheduleId]);
    $schedule = $scheduleQuery->fetch();
    if ($schedule === false || $schedule['status'] !== 'ACTIVE') {
        throw new RuntimeException('This medical schedule is no longer available.');
    }

    $medicalQuery = $database->prepare("SELECT status FROM medical_requirements WHERE student_id=:student_id AND academic_period_id=:period_id LIMIT 1");
    $medicalQuery->execute(['student_id' => $studentId, 'period_id' => $schedule['academic_period_id']]);
    if ($medicalQuery->fetchColumn() !== 'RETURNED') {
        throw new RuntimeException('Clinic must mark your record as No Medical before you can schedule an appointment.');
    }

    $countQuery = $database->prepare("SELECT COUNT(*) FROM clinic_medical_appointments WHERE schedule_id=:schedule_id AND status='SCHEDULED'");
    $countQuery->execute(['schedule_id' => $scheduleId]);
    if ((int) $countQuery->fetchColumn() >= (int) $schedule['capacity']) {
        throw new RuntimeException('This medical schedule is already full. Please choose another available date.');
    }

    $insert = $database->prepare("INSERT INTO clinic_medical_appointments (schedule_id,academic_period_id,student_id) VALUES (:schedule_id,:period_id,:student_id)");
    $insert->execute(['schedule_id' => $scheduleId, 'period_id' => $schedule['academic_period_id'], 'student_id' => $studentId]);
    notifyActiveRoles(
        $database,
        ['CLINIC_STAFF'],
        'Medical appointment scheduled',
        $student['student_number'] . ' scheduled a medical appointment for ' . $schedule['schedule_date'] . ' at ' . $schedule['schedule_time'] . '.',
        (int) $schedule['academic_period_id']
    );
    $database->commit();
    setFlashMessage('Your medical appointment has been scheduled.');
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        setFlashMessage('You already have a medical appointment for this semester.');
    } else {
        setFlashMessage($exception->getMessage());
    }
}

redirectTo('/student/dashboard.php');
