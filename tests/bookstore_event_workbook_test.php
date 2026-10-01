<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../bookstore/workbook_functions.php';

function eventWorkbookCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = getDatabaseConnection();
$before = bookstoreWorkbookStudents($database);
eventWorkbookCheck($before !== [], 'An enrolled student is required for this test.');
$studentId = (int) $before[0]['student_id'];
$periodId = (int) $database->query("SELECT id FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetchColumn();
$eventName = 'Workbook Test ' . bin2hex(random_bytes(6));
$otherEvent = $eventName . ' Second';
$database->beginTransaction();
try {
    $insert = $database->prepare("INSERT INTO bookstore_fees (academic_period_id,name,amount,is_required,status) VALUES (:period,:name,:amount,1,'ACTIVE')");
    $insert->execute(['period' => $periodId, 'name' => $eventName, 'amount' => '123.45']);
    $feeId = (int) $database->lastInsertId();
    $insert->execute(['period' => $periodId, 'name' => $otherEvent, 'amount' => '67.89']);
    $otherFeeId = (int) $database->lastInsertId();
    $assignment = $database->prepare("INSERT INTO student_bookstore_fees (student_id,fee_id,status) VALUES (:student,:fee,'PAID')");
    $assignment->execute(['student' => $studentId, 'fee' => $feeId]);
    $after = bookstoreWorkbookStudents($database);
    $student = array_values(array_filter($after, static fn(array $row): bool => (int) $row['student_id'] === $studentId))[0];
    eventWorkbookCheck(str_contains($student['pending_obligations'], $eventName . ' - PHP 123.45'), 'New event name and amount are missing from obligations.');
    eventWorkbookCheck(str_contains($student['pending_obligations'], $otherEvent . ' - PHP 67.89'), 'Second active event is missing.');
    eventWorkbookCheck($student['event_receipt_status'] === 'UNPAID', 'An unpaid event should make the receipt status UNPAID.');
    eventWorkbookCheck(abs($student['pending_balance'] - $before[0]['pending_balance'] - 67.89) < 0.001, 'Pending Balance must include only unpaid event amounts.');
    $sheet = bookstoreWorksheetXml('CCS', [$student], 'TEST PERIOD');
    eventWorkbookCheck(str_contains($sheet, $eventName . ' - PHP 123.45'), 'Event is missing from Excel XML.');
    $parsed = parseStudentWorksheet($sheet, []);
    eventWorkbookCheck(count($parsed[2]) === 10 && $parsed[2][9] === 'Clearance Status', 'Clearance Status must be the last of ten columns.');
    eventWorkbookCheck($parsed[2][5] === 'Pending Balance' && $parsed[2][6] === 'Payment Event Receipt Status', 'Payment Event Receipt Status must immediately follow Pending Balance.');
    eventWorkbookCheck($parsed[3][4] === normalizeImportText($student['pending_obligations']) && $parsed[3][6] === 'UNPAID', 'Event details or receipt status are in the wrong columns.');
    eventWorkbookCheck(str_contains($sheet, '<formula1>"PAID,UNPAID"</formula1>') && str_contains($sheet, 'sqref="G4:G4"'), 'Receipt status dropdown is missing.');
    eventWorkbookCheck(!str_contains($sheet, 'Active Event Fees / Amounts') && !str_contains($sheet, 'Bookstore Status'), 'Removed headers remain.');
    $close = $database->prepare("UPDATE bookstore_fees SET status='INACTIVE' WHERE id=:id");
    $close->execute(['id' => $feeId]);
    $closed = bookstoreWorkbookStudents($database);
    foreach ($closed as $row) {
        eventWorkbookCheck(!str_contains($row['pending_obligations'], $eventName . ' - PHP'), 'Closed event remains in the export.');
    }
    eventWorkbookCheck(str_contains($closed[0]['pending_obligations'], $otherEvent), 'Closing one event removed another active event.');
    $close->execute(['id' => $otherFeeId]);
    $restored = bookstoreWorkbookStudents($database);
    eventWorkbookCheck(abs($restored[0]['pending_balance'] - $before[0]['pending_balance']) < 0.001, 'Closing unpaid events must remove their amounts from Pending Balance.');
} finally {
    $database->rollBack();
}
echo "Bookstore active/closed event export test passed.\n";
