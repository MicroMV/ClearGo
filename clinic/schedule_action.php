<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('CLINIC_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request. Please try again.');
    redirectTo('/clinic/schedules.php');
}

try {
    $database = getDatabaseConnection();
    $periodId = (int) $database->query("SELECT id FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($periodId < 1) {
        throw new RuntimeException('There is no active semester.');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $date = trim((string) ($_POST['schedule_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $startTimeValue = DateTimeImmutable::createFromFormat('!H:i', $startTime);
        $endTimeValue = DateTimeImmutable::createFromFormat('!H:i', $endTime);
        $scheduleDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $startTime);
        if ($dateValue === false || $dateValue->format('Y-m-d') !== $date || $startTimeValue === false || $startTimeValue->format('H:i') !== $startTime || $endTimeValue === false || $endTimeValue->format('H:i') !== $endTime || $endTimeValue <= $startTimeValue || $scheduleDateTime === false || $scheduleDateTime <= new DateTimeImmutable() || $capacity === false) {
            throw new RuntimeException('Enter a valid future date, start time, end time, and student limit. The end time must be later than the start time.');
        }
        $statement = $database->prepare("INSERT INTO clinic_medical_schedules (academic_period_id,schedule_date,schedule_time,end_time,capacity,created_by) VALUES (:period_id,:schedule_date,:start_time,:end_time,:capacity,:user_id)");
        $statement->execute(['period_id' => $periodId, 'schedule_date' => $date, 'start_time' => $startTime . ':00', 'end_time' => $endTime . ':00', 'capacity' => $capacity, 'user_id' => $user['id']]);
        setFlashMessage('Medical schedule added.');
    } elseif ($action === 'close') {
        $scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($scheduleId === false) {
            throw new RuntimeException('The selected schedule is invalid.');
        }
        $statement = $database->prepare("UPDATE clinic_medical_schedules SET status='CLOSED' WHERE id=:id AND academic_period_id=:period_id");
        $statement->execute(['id' => $scheduleId, 'period_id' => $periodId]);
        setFlashMessage('Medical schedule closed.');
    } else {
        throw new RuntimeException('Invalid schedule action.');
    }
} catch (PDOException $exception) {
    setFlashMessage($exception->getCode() === '23000' ? 'That medical schedule already exists.' : 'Unable to save the medical schedule.');
} catch (RuntimeException $exception) {
    setFlashMessage($exception->getMessage());
}

redirectTo('/clinic/schedules.php');
