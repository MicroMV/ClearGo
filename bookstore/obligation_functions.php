<?php

declare(strict_types=1);

require_once __DIR__ . '/../clearance/office_workflow.php';

function bookstoreObligationPreparation(PDO $database, int $periodId, bool $lock = false): array
{
    $statement = $database->prepare(
        "SELECT period.status AS period_status,period.clearance_status,readiness.status AS readiness_status
         FROM academic_periods period
         LEFT JOIN stations station ON station.code='BOOKSTORE' AND station.status='ACTIVE'
         LEFT JOIN office_period_readiness readiness ON readiness.academic_period_id=period.id AND readiness.station_id=station.id
         WHERE period.id=:period_id LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['period_id' => $periodId]);
    $context = $statement->fetch();
    if ($context === false) {
        throw new RuntimeException('There is no semester available for Bookstore preparation.');
    }
    return $context;
}

function bookstoreObligationLockReason(array $context): ?string
{
    if ($context['readiness_status'] === 'READY') {
        return 'Bookstore obligations and event fees are locked because the office is Ready. Payments and student clearance remain available.';
    }
    if ($context['period_status'] !== 'ACTIVE' || $context['clearance_status'] !== 'PREPARING') {
        return 'Bookstore obligations and event fees can only be changed during semester preparation.';
    }
    if ($context['readiness_status'] !== 'NOT_READY') {
        return 'Bookstore readiness must be configured before changing obligations or event fees.';
    }
    return null;
}

function requireBookstoreObligationPreparation(PDO $database, int $periodId): void
{
    if (!$database->inTransaction()) {
        throw new LogicException('Bookstore obligation changes require a transaction.');
    }
    // Lock the same period/readiness rows used by Ready and semester opening.
    $reason = bookstoreObligationLockReason(bookstoreObligationPreparation($database, $periodId, true));
    if ($reason !== null) {
        throw new RuntimeException($reason);
    }
}

function bookstoreHasSignedClearances(PDO $database, int $periodId): bool
{
    return countQuery($database,
        "SELECT COUNT(*) FROM clearances clearance
         INNER JOIN clearance_stations record ON record.clearance_id=clearance.id
         INNER JOIN stations station ON station.id=record.station_id AND station.code='BOOKSTORE'
         WHERE clearance.academic_period_id=:period_id AND (record.status='APPROVED' OR clearance.status='COMPLETED')",
        ['period_id' => $periodId]
    ) > 0;
}

function addBookstoreObligation(PDO $database, int $clearanceId, int $staffUserId, string $description, float $amount, string $remarks = ''): void
{
    $description = trim($description);
    $remarks = trim($remarks);
    if ($description === '' || mb_strlen($description) > 255 || !is_finite($amount) || $amount < 0 || $amount > 99999999.99) {
        throw new RuntimeException('Enter a valid Bookstore obligation and amount.');
    }
    if (mb_strlen($remarks) > 1000) {
        throw new RuntimeException('Remarks cannot exceed 1,000 characters.');
    }
    $database->beginTransaction();
    try {
        $context = officeClearanceContext($database, 'BOOKSTORE', $clearanceId, true);
        requireBookstoreObligationPreparation($database, (int) $context['academic_period_id']);
        if ($context['station_status'] === 'APPROVED' || $context['overall_status'] === 'COMPLETED') {
            throw new RuntimeException('New obligations cannot be added after this student has been cleared by Bookstore.');
        }
        if ($context['station_status'] === 'LOCKED') {
            throw new RuntimeException('The required preceding offices have not cleared this student yet.');
        }
        $statement = $database->prepare("INSERT INTO bookstore_obligations (student_id,academic_period_id,description,amount,status,remarks,recorded_by) VALUES (:student_id,:period_id,:description,:amount,'UNPAID',:remarks,:user_id)");
        $statement->execute(['student_id' => $context['student_id'], 'period_id' => $context['academic_period_id'], 'description' => $description, 'amount' => number_format($amount, 2, '.', ''), 'remarks' => $remarks ?: null, 'user_id' => $staffUserId]);
        notifyAndAuditOffice($database, $context, $staffUserId, 'BOOKSTORE_OBLIGATION_ADDED', 'Bookstore recorded a pending obligation: ' . $description . '.', $context['station_status'], $context['station_status']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function createBookstoreEventFee(PDO $database, int $periodId, string $name, float $amount): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 200 || !is_finite($amount) || $amount < 0 || $amount > 99999999.99) {
        throw new RuntimeException('Enter a valid event name and amount.');
    }
    $database->beginTransaction();
    try {
        requireBookstoreObligationPreparation($database, $periodId);
        if (bookstoreHasSignedClearances($database, $periodId)) {
            throw new RuntimeException('Event fees cannot be added after Bookstore has cleared students for this semester.');
        }
        $duplicate = $database->prepare("SELECT COUNT(*) FROM bookstore_fees WHERE academic_period_id=:period_id AND LOWER(name)=LOWER(:name) AND status='ACTIVE'");
        $duplicate->execute(['period_id' => $periodId, 'name' => $name]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new RuntimeException('That event fee already exists for this semester.');
        }
        $fee = $database->prepare("INSERT INTO bookstore_fees (academic_period_id,name,amount,is_required,status) VALUES (:period_id,:name,:amount,1,'ACTIVE')");
        $fee->execute(['period_id' => $periodId, 'name' => $name, 'amount' => number_format($amount, 2, '.', '')]);
        $feeId = (int) $database->lastInsertId();
        $assign = $database->prepare("INSERT IGNORE INTO student_bookstore_fees (student_id,fee_id,status) SELECT enrollment.student_id,:fee_id,'UNPAID' FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id AND student.status='ACTIVE' WHERE enrollment.academic_period_id=:period_id AND enrollment.status='ENROLLED'");
        $assign->execute(['fee_id' => $feeId, 'period_id' => $periodId]);
        $assigned = $assign->rowCount();
        $notify = $database->prepare("INSERT INTO notifications (user_id,academic_period_id,title,message) SELECT student.user_id,:period_id,'New Bookstore event receipt required',:message FROM student_bookstore_fees student_fee INNER JOIN students student ON student.id=student_fee.student_id WHERE student_fee.fee_id=:fee_id");
        $notify->execute(['period_id' => $periodId, 'fee_id' => $feeId, 'message' => 'Upload your receipt for ' . $name . ' in the Bookstore requirements.']);
        $database->commit();
        return $assigned;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function deactivateBookstoreEventFee(PDO $database, int $periodId, int $feeId): bool
{
    $database->beginTransaction();
    try {
        requireBookstoreObligationPreparation($database, $periodId);
        $statement = $database->prepare("UPDATE bookstore_fees SET status='INACTIVE' WHERE id=:id AND academic_period_id=:period_id AND status='ACTIVE'");
        $statement->execute(['id' => $feeId, 'period_id' => $periodId]);
        $changed = $statement->rowCount() === 1;
        $database->commit();
        return $changed;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}
