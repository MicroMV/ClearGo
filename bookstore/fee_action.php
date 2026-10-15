<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

requireRole('BOOKSTORE_STAFF');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo('/bookstore/fees.php');
}

try {
    $database = getDatabaseConnection();
    $periodId = (int) $database->query("SELECT id FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
    if ($periodId < 1) {
        throw new RuntimeException('There is no active semester.');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($name === '' || mb_strlen($name) > 200 || $amount === false || $amount < 0 || $amount > 99999999.99) {
            throw new RuntimeException('Enter a valid event name and amount.');
        }

        $duplicate = $database->prepare("SELECT COUNT(*) FROM bookstore_fees WHERE academic_period_id=:period_id AND LOWER(name)=LOWER(:name) AND status='ACTIVE'");
        $duplicate->execute(['period_id' => $periodId, 'name' => $name]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new RuntimeException('That event fee already exists for this semester.');
        }

        $database->beginTransaction();
        $fee = $database->prepare("INSERT INTO bookstore_fees (academic_period_id,name,amount,is_required,status) VALUES (:period_id,:name,:amount,1,'ACTIVE')");
        $fee->execute(['period_id' => $periodId, 'name' => $name, 'amount' => number_format((float) $amount, 2, '.', '')]);
        $feeId = (int) $database->lastInsertId();
        $assign = $database->prepare("INSERT IGNORE INTO student_bookstore_fees (student_id,fee_id,status) SELECT enrollment.student_id,:fee_id,'UNPAID' FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE' WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'");
        $assign->execute(['fee_id' => $feeId, 'period_id' => $periodId]);
        $notify = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) SELECT student.user_id,:period_id,'New Bookstore event receipt required',:message FROM student_bookstore_fees student_fee INNER JOIN students student ON student.id=student_fee.student_id WHERE student_fee.fee_id=:fee_id");
        $notify->execute(['period_id' => $periodId, 'fee_id' => $feeId, 'message' => 'Upload your receipt for ' . $name . ' in the Bookstore requirements.']);
        $database->commit();
        setFlashMessage('Event fee added for ' . $assign->rowCount() . ' enrolled student(s).');
    } elseif ($action === 'deactivate') {
        $feeId = filter_var($_POST['fee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($feeId === false) {
            throw new RuntimeException('The selected event fee is invalid.');
        }
        $statement = $database->prepare("UPDATE bookstore_fees SET status='INACTIVE' WHERE id=:id AND academic_period_id=:period_id AND status='ACTIVE'");
        $statement->execute(['id' => $feeId, 'period_id' => $periodId]);
        setFlashMessage($statement->rowCount() === 1 ? 'Event fee closed.' : 'The event fee is no longer active.');
    } else {
        throw new RuntimeException('Invalid event fee action.');
    }
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Bookstore event fee action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to save the event fee.');
}

redirectTo('/bookstore/fees.php');
