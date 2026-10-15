<?php

declare(strict_types=1);

require_once __DIR__ . '/../student/dashboard_functions.php';

function dashboardCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database->exec("CREATE TABLE academic_periods (id INTEGER PRIMARY KEY,school_year TEXT,semester TEXT,status TEXT,clearance_status TEXT);
    CREATE TABLE semester_enrollments (academic_period_id INTEGER,student_id INTEGER,status TEXT);
    CREATE TABLE clearances (id INTEGER PRIMARY KEY,academic_period_id INTEGER,student_id INTEGER);
    INSERT INTO academic_periods VALUES (1,'2026-2027','1ST','CLOSED','CLOSED'),(2,'2026-2027','2ND','ACTIVE','OPEN'),(3,'2027-2028','1ST','DRAFT','DRAFT');
    INSERT INTO semester_enrollments VALUES (1,10,'ENROLLED'),(2,10,'REMOVED'),(1,20,'ENROLLED'),(2,20,'ENROLLED'),(2,40,'ENROLLED');
    INSERT INTO clearances VALUES (1,1,10),(2,2,20),(3,1,30),(4,2,10),(5,3,50);");

$firstSemesterOnly = studentDashboardPeriods($database, 10);
dashboardCheck(array_column($firstSemesterOnly, 'id') === [1], 'A removed student can see the ongoing semester, including its stale clearance.');
dashboardCheck(selectStudentDashboardPeriod($firstSemesterOnly)['id'] === 1, 'A student missing from second semester did not default to first semester.');
dashboardCheck(selectStudentDashboardPeriod($firstSemesterOnly, 2)['id'] === 1, 'A URL can select an unenrolled active semester.');
dashboardCheck(selectStudentDashboardPeriod($firstSemesterOnly, 3)['id'] === 1, 'A URL can select another student\'s semester.');
dashboardCheck(!studentDashboardCanSubmit($firstSemesterOnly[0]) && studentDashboardIsHistory($firstSemesterOnly[0]), 'Closed semester permits submissions or is not historical.');

$bothSemesters = studentDashboardPeriods($database, 20);
dashboardCheck(array_column($bothSemesters, 'id') === [2,1], 'An enrolled student cannot select both semesters.');
dashboardCheck(selectStudentDashboardPeriod($bothSemesters)['id'] === 2, 'An enrolled student did not default to the active semester.');
dashboardCheck(selectStudentDashboardPeriod($bothSemesters, '1')['id'] === 1, 'A student cannot explicitly view their old semester.');
dashboardCheck(selectStudentDashboardPeriod($bothSemesters, 'invalid')['id'] === 2, 'Invalid selection did not fall back to the enrolled semester.');
dashboardCheck(studentDashboardCanSubmit($bothSemesters[0]), 'Active enrolled clearance no longer permits submissions.');

$legacyHistory = studentDashboardPeriods($database, 30);
dashboardCheck(array_column($legacyHistory, 'id') === [1], 'Legacy clearance history without an enrollment row is unavailable.');
dashboardCheck(selectStudentDashboardPeriod($legacyHistory)['id'] === 1, 'Legacy history did not become the default.');
dashboardCheck(studentDashboardPeriods($database, 99) === [] && selectStudentDashboardPeriod([]) === false, 'A student with no history can see other students\' semesters.');
dashboardCheck(!studentDashboardCanSubmit(false) && !studentDashboardIsHistory(false), 'An empty dashboard permits submissions.');
$database->exec("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=2");
$preparing = studentDashboardPeriods($database, 40);
dashboardCheck($preparing[0]['id'] === 2 && !studentDashboardCanSubmit($preparing[0]), 'Preparing enrollment was hidden or permits clearance submissions.');

// Render the real dashboard view with historical and active data, without sessions or database writes.
function escapeHtml(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function applicationUrl(string $path): string { return $path; }
function statusDisplayLabel(string $status): string { return str_replace('_', ' ', $status); }
function clearGoLogoUrl(): string { return '/logo.png'; }
function pullFlashMessage(): ?string { return null; }
function createCsrfToken(): string { return 'test'; }
function dashboardLogoutForm(): string { return ''; }

$source = file_get_contents(__DIR__ . '/../student/dashboard.php');
$viewStart = strpos($source, '$displayStationStatuses =');
$labelStart = strpos($source, 'function studentStatusLabel');
eval(substr($source, $labelStart, $viewStart - $labelStart));
$view = str_replace("require __DIR__ . '/dashboard_view.php';", 'require ' . var_export(realpath(__DIR__ . '/../student/dashboard_view.php'), true) . ';', substr($source, $viewStart));

function renderDashboardFixture(string $view, array|false $period, array $availablePeriods, string $pendingStation = 'DEAN', bool $selectStation = false, string $requirementStatus = 'NOT_STARTED'): string
{
    $isEnrolled = $period !== false && (int) $period['is_enrolled'] === 1;
    $isHistoricalRecord = studentDashboardIsHistory($period);
    $canSubmit = studentDashboardCanSubmit($period);
    $isCbeStudent = false;
    $student = ['id' => 10,'first_name' => 'Test','last_name' => 'Student','student_number' => 'TEST-10',
        'program' => 'BSCS','year_level' => 2,'section' => 'N/A','organization_name' => 'Computer Studies',
        'organization_code' => 'CCS','email' => 'test@thelewiscollege.edu.ph'];
    $clearance = $period === false ? false : ['id' => 1,'reference_number' => 'FIRST-SEM-RECORD','status' => 'IN_PROGRESS'];
    $stations = [];
    $stationStatuses = [];
    $stationRequirements = [];
    foreach (['DEAN','ORGANIZATION','SASO','FACILITIES','COMLAB','ACCOUNTING','LIBRARY','BOOKSTORE','CLINIC'] as $index => $code) {
        $id = $index + 1;
        $stations[] = ['id' => $id,'code' => $code,'name' => $code,'display_order' => (string) $id,'parent_name' => null];
        $stationStatuses[$id] = ['status' => $isHistoricalRecord
            ? ($code === 'ORGANIZATION' ? 'APPROVED' : 'PENDING')
            : ($code === $pendingStation ? 'PENDING' : 'APPROVED')];
        $stationRequirements[$id] = [['name' => 'Saved requirement','description' => '',
            'requirement_code' => 'CCS_FACULTY_EVALUATION','status' => $requirementStatus,'remarks' => null]];
    }
    $evaluationApplicable = true;
    $evaluation = false;
    $accountingReceipt = false;
    $libraryCardSubmission = false;
    $bookstoreFees = [['student_fee_id' => 1,'name' => 'Semester event','amount' => 100,'payment_status' => 'UNPAID',
        'submission_status' => null,'submitted_at' => null,'return_reason' => null]];
    $medicalStatus = 'RETURNED';
    $medicalAppointment = false;
    $medicalSchedules = [['id' => 1,'schedule_date' => '2026-10-15','schedule_time' => '09:00:00','end_time' => '10:00:00','capacity' => 10,'booked_count' => 0]];
    $selectedStation = $selectStation ? $stations[0] : null;
    $comlabViolations = $facilitiesItems = $organizationEvents = $notifications = [];
    $unreadNotificationCount = 0;
    ob_start();
    eval($view);
    return ob_get_clean();
}

$html = renderDashboardFixture($view, $firstSemesterOnly[0], $firstSemesterOnly);
dashboardCheck(str_contains($html, 'First Semester A.Y. 2026-2027') && !str_contains($html, 'Second Semester A.Y.'), 'Historical dashboard did not restrict the semester dropdown.');
dashboardCheck(str_contains($html, 'portal-period-select--static') && !str_contains($html, 'data-period-select'), 'A single semester still renders an unnecessary native dropdown.');
dashboardCheck(str_contains($html, 'FIRST-SEM-RECORD') && str_contains($html, '1 of 9 stations cleared'), 'Historical clearance reference or stored station decision is missing.');
dashboardCheck(str_contains($html, 'Saved requirement') && str_contains($html, 'Submissions are closed'), 'Historical requirements or viewing notice is missing.');
preg_match_all('/<form\b[^>]*method="post"[^>]*>/i', $html, $historicalForms);
foreach ($historicalForms[0] as $historicalForm) {
    dashboardCheck(str_contains($historicalForm, 'action="/auth/profile_picture_action.php"'), 'Historical dashboard exposes a clearance submission form.');
}
dashboardCheck(str_contains($html, 'data-profile-picture-form'), 'Historical viewing prevents account picture updates.');
dashboardCheck(!str_contains($html, '/student/attendance_scanner.php'), 'Historical dashboard exposes the attendance scanner.');

$activeHtml = renderDashboardFixture($view, $bothSemesters[0], $bothSemesters);
dashboardCheck(str_contains($activeHtml, '/student/evaluation_action.php') && str_contains($activeHtml, '/student/attendance_scanner.php'), 'Active enrolled students lost their available submission controls.');
dashboardCheck(str_contains($activeHtml, 'data-attendance-scanner-modal')
    && str_contains($activeHtml, 'data-open-attendance-scanner')
    && str_contains($activeHtml, 'allow="camera"'),
    'Active student attendance scanner is not available as a dashboard modal.');
dashboardCheck(str_contains($activeHtml, 'data-password-modal')
    && str_contains($activeHtml, 'data-open-password-modal'),
    'Student change-password form is not available as a dashboard modal.');
dashboardCheck(preg_match('/id="overview"[^>]*?(?<!\s)hidden|id="overview"[^>]*\shidden/', $activeHtml) !== 1, 'The server hides the default Home view.');
dashboardCheck(preg_match('/id="clearance"[^>]*\shidden/', $activeHtml) === 1
    && preg_match('/id="attendance"[^>]*\shidden/', $activeHtml) === 1
    && preg_match('/id="account"[^>]*\shidden/', $activeHtml) === 1,
    'The server exposes non-Home navigation views before JavaScript loads.');
dashboardCheck(preg_match('/<section[^>]+id="overview"[\s\S]*?portal-page-heading[\s\S]*?YOUR STUDENT SPACE[\s\S]*?<\/section>/', $activeHtml) === 1,
    'The student greeting is not contained in the Home view.');
dashboardCheck(str_contains($activeHtml, 'student-portal.css?v=') && str_contains($activeHtml, 'student-dashboard.js?v='), 'Dashboard assets are not cache-versioned.');
foreach (['ACCOUNTING' => 'accounting_receipt_action.php','LIBRARY' => 'library_card_action.php',
    'BOOKSTORE' => 'bookstore_receipt_action.php','CLINIC' => 'medical_schedule_action.php'] as $stationCode => $actionFile) {
    $html = renderDashboardFixture($view, $bothSemesters[0], $bothSemesters, $stationCode);
    dashboardCheck(str_contains($html, '/student/' . $actionFile), 'Active enrolled students lost the ' . $stationCode . ' submission controls.');
}
$verifiedBookstoreHtml = renderDashboardFixture($view, $bothSemesters[0], $bothSemesters, 'BOOKSTORE', false, 'VERIFIED');
dashboardCheck(str_contains($verifiedBookstoreHtml, 'No further receipt submission is needed.')
    && !str_contains($verifiedBookstoreHtml, '/student/bookstore_receipt_action.php'),
    'A verified Bookstore requirement still exposes receipt submission controls.');
$emptyHtml = renderDashboardFixture($view, false, []);
dashboardCheck(str_contains($emptyHtml, 'No semester record is available.') && !str_contains($emptyHtml, 'name="period"'), 'Empty history does not render a usable dashboard.');

echo "Student dashboard history tests passed.\n";
