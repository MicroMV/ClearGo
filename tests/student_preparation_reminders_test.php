<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../student/dashboard_functions.php';

$checks = 0;
function reminderCheck(bool $passed, string $message): void
{
    global $checks;
    if (!$passed) { throw new RuntimeException($message); }
    $checks++;
}
function keyedReminders(array $data): array
{
    return array_column(studentDashboardPreparationReminders($data), null, 'key');
}
$period = ['id' => 1, 'status' => 'ACTIVE', 'clearance_status' => 'PREPARING'];
$data = ['period' => $period, 'enrolled' => true, 'stations' => [], 'statuses' => []];
foreach (['ACCOUNTING','CLINIC','LIBRARY','FACILITIES','BOOKSTORE','COMLAB','ORGANIZATION','STUDENT_COUNCIL'] as $index => $code) {
    $data['stations'][] = ['id' => $index + 1, 'code' => $code];
    $data['statuses'][$index + 1] = ['status' => 'LOCKED'];
}
$general = keyedReminders($data);
reminderCheck(count($general) === 6, 'Preparation guidance must be available before clearance opens.');
reminderCheck($general['accounting']['items'] === [] && !$general['accounting']['attention'], 'General Accounting guidance must not claim an unpaid charge.');
reminderCheck(array_filter($general, static fn(array $entry): bool => $entry['modal'] !== null) === [], 'Preparation reminders must not bypass locked office requirements.');
reminderCheck(keyedReminders(array_replace($data, ['enrolled' => false])) === [], 'Unenrolled students must not receive this semester checklist.');
reminderCheck(keyedReminders(array_replace($data, ['period' => false])) === [], 'A missing semester must not generate current obligations.');
reminderCheck(keyedReminders(array_replace($data, ['period' => $period + []])) !== [], 'Preparing semester guidance was lost.');
foreach ([['status' => 'CLOSED','clearance_status' => 'CLOSED'], ['status' => 'ACTIVE','clearance_status' => 'CLOSED']] as $history) {
    reminderCheck(keyedReminders(array_replace($data, ['period' => $history])) === [], 'Historical records must not show current action reminders.');
}
$recorded = keyedReminders($data + [
    'accounting' => [['description' => '<script>unpaid</script>', 'amount' => '1250.50']],
    'books' => [['book_title' => 'Borrowed textbook', 'due_at' => '2026-10-25']],
    'facilities' => [['description' => 'Borrowed equipment', 'remarks' => 'Return to Facilities']],
    'violations' => [['office_name' => 'Computer Lab', 'description' => 'Recorded violation']],
]);
reminderCheck($recorded['accounting']['attention'] && str_contains($recorded['accounting']['items'][0], '1,250.50'), 'Recorded unpaid charges must retain their amount.');
reminderCheck(str_contains($recorded['library']['items'][0], '2026-10-25'), 'Borrowed-book due dates must be shown.');
reminderCheck(str_contains($recorded['facilities']['items'][0], 'Return to Facilities'), 'Return instructions must be retained.');
reminderCheck(str_contains($recorded['violations']['items'][0], 'Computer Lab'), 'Violations must name the responsible office.');
reminderCheck($recorded['violations']['modal'] === null, 'A violation with no known owner must not link to another office.');
reminderCheck(!isset(keyedReminders($data + ['medical_status' => 'VERIFIED'])['clinic']), 'A verified medical must not be presented as unfinished.');
$scheduled = keyedReminders($data + ['appointment' => ['schedule_date' => '2026-10-25', 'schedule_time' => '09:00:00']]);
reminderCheck(str_contains($scheduled['clinic']['hint'], 'Oct 25, 2026') && str_contains($scheduled['clinic']['guidance'], 'Attend'), 'A booked appointment must show attendance guidance.');
$groups = [];
foreach (['ORGANIZATION' => 'Organization', 'STUDENT_COUNCIL' => 'Student Council'] as $code => $name) {
    $groups[$code] = ['name' => $name, 'hours' => 2, 'items' => [['event_name' => 'Missed event', 'hours' => '2.00', 'task' => 'Community service']], 'submission' => ['status' => 'SUBMITTED']];
}
$sanctions = keyedReminders($data + ['sanctions' => $groups]);
reminderCheck(isset($sanctions['sanctions-ORGANIZATION'], $sanctions['sanctions-STUDENT_COUNCIL']) && !isset($sanctions['sanctions']), 'Organization and Council sanctions must remain separate.');
reminderCheck(str_contains($sanctions['sanctions-ORGANIZATION']['guidance'], 'Wait for the office'), 'Pending photo review must not ask students to submit again.');
$groups['ORGANIZATION']['submission']['status'] = 'RETURNED';
reminderCheck(str_contains(keyedReminders($data + ['sanctions' => $groups])['sanctions-ORGANIZATION']['guidance'], 'separate submissions'), 'Returned sanctions must explain the separate photo pairs.');
$cleared = $data;
foreach ($cleared['statuses'] as &$status) { $status['status'] = 'APPROVED'; }
unset($status);
reminderCheck(keyedReminders($cleared) === [], 'A fully cleared student must not retain general preparation reminders.');
$unlocked = $data;
$unlocked['statuses'][1]['status'] = 'PENDING';
reminderCheck(keyedReminders($unlocked)['accounting']['modal'] === 1, 'Available Accounting requirements must remain accessible.');

// Render actual markup to check untrusted office descriptions are escaped.
function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$isEnrolled = true;
$stations = $data['stations'];
$displayStationStatuses = $data['statuses'];
$medicalStatus = 'PENDING'; $medicalAppointment = false;
$accountingObligations = [['description' => '<script>unpaid</script>', 'amount' => '1250.50']];
$facilitiesItems = []; $bookstoreFees = [];
ob_start(); require __DIR__ . '/../student/preparation_reminders.php'; $html = ob_get_clean();
reminderCheck(str_contains($html, '&lt;script&gt;unpaid&lt;/script&gt;') && !str_contains($html, '<script>unpaid</script>'), 'Office descriptions must be escaped in the student checklist.');
echo "All $checks student preparation reminder checks passed.\n";
