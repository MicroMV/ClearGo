<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../dean/completion_workbook_functions.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';
require_once __DIR__ . '/../auth/signature_functions.php';

$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_ccs_excel_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $template) || $temporary === $template) { throw new RuntimeException('Unsafe test database.'); }
$host = getenv('DB_HOST') ?: 'localhost'; $port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'root'; $password = getenv('DB_PASSWORD');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password === false ? '' : $password, $options);
$checks = 0; $server = null; $base = ''; $clients = []; $files = [];
$log = tempnam(sys_get_temp_dir(), 'ccs-excel-log-');

function completionCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++; echo "PASS: {$message}\n";
}

function completionRejected(callable $action, string $message): void
{
    $rejected = false;
    try { $action(); } catch (RuntimeException $exception) { $rejected = true; }
    completionCheck($rejected, $message);
}

function completionStudent(PDO $database, int $organizationId, int $periodId, int $year, string $number): array
{
    $email = strtolower($number) . '@thelewiscollege.edu.ph';
    $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,'STUDENT','ACTIVE')")->execute([$email, password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT)]);
    $userId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,?,'Synthetic','Excel Student','Test Program',?,'QA',?,'ACTIVE')")->execute([$userId, $number, $year, $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$periodId, $studentId]);
    $started = startStudentClearance($database, $userId);
    return ['student_id' => $studentId, 'user_id' => $userId, 'clearance_id' => (int) $started['clearance']['id'], 'number' => $number, 'email' => $email, 'year_level' => $year, 'full_name' => 'Excel Student, Synthetic'];
}

function completionRow(array $student, string $value, array $context): array
{
    return ['student_number' => $student['number'], 'full_name' => $student['full_name'], 'course' => 'Test Program', 'year_level' => $student['year_level'], 'completed' => $value, 'school_year' => $context['school_year'], 'semester' => $context['semester']];
}

function completionRequirement(PDO $database, int $clearanceId, string $code): ?array
{
    $query = $database->prepare("SELECT record.* FROM requirement_records record
        JOIN requirements requirement ON requirement.id=record.requirement_id
        JOIN clearance_stations station ON station.id=record.clearance_station_id
        WHERE station.clearance_id=? AND requirement.requirement_code=?");
    $query->execute([$clearanceId, $code]);
    return $query->fetch() ?: null;
}

function completionRosterNumbers(string $html): array
{
    $document = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors($previous);
    $cells = (new DOMXPath($document))->query('//table/tbody/tr/td[2]');
    $numbers = []; foreach ($cells as $cell) { $numbers[] = trim($cell->textContent); } return $numbers;
}

/** Optional synthetic response snapshots for a separate browser layout check. */
function completionSnapshot(string $name, string $html): void
{
    if (getenv('CCS_COMPLETION_PREVIEW') !== '1') { return; }
    $directory = __DIR__ . '/../.tmp/ccs-dean-check';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Cannot create the preview directory.'); }
    file_put_contents($directory . '/' . $name . '.html', $html);
}

function completionFile(array $rows, bool $legacy = false): string
{
    global $files;
    $path = tempnam(sys_get_temp_dir(), 'ccs-completion-');
    if ($path === false) { throw new RuntimeException('Cannot create the test workbook.'); }
    $files[] = $path;
    if ($legacy) {
        $headers = ['Student Number','Evaluation Completed','School Year','Semester'];
        $sheets = [['name' => 'Completion List', 'rows' => array_map(static fn(array $row): array => [$row['student_number'], $row['completed'], $row['school_year'], $row['semester']], $rows)]];
    } else {
        $headers = ['Student Number','Full Name','Course','Requirements Completion','School Year','Semester'];
        $yearRows = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($rows as $row) { $yearRows[$row['year_level']][] = [$row['student_number'], $row['full_name'], $row['course'], $row['completed'], $row['school_year'], $row['semester']]; }
        $sheets = []; foreach ($yearRows as $year => $data) { $sheets[] = ['name' => 'Year ' . $year, 'rows' => $data]; }
    }
    file_put_contents($path, createClearanceReportXlsx('CCS Completion Test', 'Synthetic', $headers, $sheets));
    return $path;
}

function completionHttp(CurlHandle $client, string $path, ?array $post = null, bool $json = false): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . $path);
    $headers = '';
    curl_setopt($client, CURLOPT_HEADERFUNCTION, static function ($handle, string $line) use (&$headers): int { $headers .= $line; return strlen($line); });
    curl_setopt($client, CURLOPT_HTTPHEADER, $json ? ['Content-Type: application/json'] : []);
    if ($post === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, $json ? json_encode($post) : $post); }
    $body = curl_exec($client);
    if ($body === false) { throw new RuntimeException(curl_error($client)); }
    $status = (int) curl_getinfo($client, CURLINFO_HTTP_CODE);
    if (!str_starts_with($body, 'PK') && preg_match('/Fatal error|Uncaught |Warning:/', $body)) { throw new RuntimeException('HTTP PHP error: ' . strip_tags($body)); }
    return ['status' => $status, 'body' => $body, 'headers' => $headers];
}

function completionLogin(string $email): array
{
    global $clients;
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $page = completionHttp($handle, '/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $match)) { throw new RuntimeException('No login token.'); }
    $client = ['handle' => $handle, 'csrf' => html_entity_decode($match[1])]; $clients[] = $client;
    $response = completionHttp($handle, '/auth/login_process.php', ['csrf_token' => $client['csrf'], 'email' => $email, 'password' => 'Synthetic-Only-2026!']);
    if ($response['status'] !== 303) { throw new RuntimeException('Synthetic login failed.'); }
    return $client;
}

try {
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`'); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Unsafe template table.'); }
        $database->exec($database->query('SHOW CREATE TABLE `' . $template . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations','stations','requirements'] as $table) { $database->exec('INSERT INTO `' . $temporary . '`.`' . $table . '` SELECT * FROM `' . $template . '`.`' . $table . '`'); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $database->exec(file_get_contents(__DIR__ . '/../database/migrations/029_ccs_evaluation_completion_list.sql'));
    $database->exec(file_get_contents(__DIR__ . '/../database/migrations/030_remove_evaluation_screenshot_storage.sql'));
    completionCheck((int) $database->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('evaluation_submission_images','evaluation_submissions','evaluation_verifications')")->fetchColumn() === 0, 'The obsolete screenshot tables are absent before exercising the current workflow.');
    $organizations = $database->query('SELECT code,id FROM organizations')->fetchAll(PDO::FETCH_KEY_PAIR); $staff = [];
    foreach (array_diff(APPLICATION_ROLES, ['STUDENT']) as $role) {
        $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,?,'ACTIVE')")->execute([strtolower($role) . '-excel-test@thelewiscollege.edu.ph', password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT), $role]);
        $staff[$role] = (int) $database->lastInsertId();
    }
    foreach (['DEAN' => 'DEAN', 'ORG_STAFF' => 'ORGANIZATION'] as $role => $code) {
        $database->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) SELECT ?,id,?,'ACTIVE' FROM stations WHERE code=?")->execute([$staff[$role], $organizations['CCS'], $code]);
    }
    $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES ('wrong-dean-excel-test@thelewiscollege.edu.ph',?,'DEAN','ACTIVE')")->execute([password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT)]);
    $wrongDean = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) SELECT ?,id,?,'ACTIVE' FROM stations WHERE code='DEAN'")->execute([$wrongDean, $organizations['CTE']]);
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2098-2099','1ST','ACTIVE','OPEN')");
    $periodId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'NOT_READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
    $students = [];
    for ($index = 0; $index < 37; $index++) { $students[] = completionStudent($database, (int) $organizations['CCS'], $periodId, [1 => 4, 2 => 1, 3 => 3][$index] ?? 2, $index === 0 ? '0000123' : 'EXCEL-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT)); }
    $deanId = $staff['DEAN']; $context = ccsCompletionImportContext($database, $deanId);
    $download = createCcsCompletionWorkbook($database, $deanId); $templateFile = tempnam(sys_get_temp_dir(), 'completion-template-'); $files[] = $templateFile; file_put_contents($templateFile, $download);
    $rows = readCcsCompletionWorkbook($templateFile);
    $byNumber = array_column($rows, null, 'student_number');
    completionCheck(count($rows) === 37 && isset($byNumber['0000123']) && array_unique(array_column($rows, 'completed')) === [''], 'All year-level sheets are read, leading-zero IDs are preserved and completion cells start blank.');
    completionCheck($byNumber['0000123']['full_name'] === 'Excel Student, Synthetic' && $byNumber['0000123']['course'] === 'Test Program' && array_keys($rows[0]) === ['student_number','full_name','course','completed','school_year','semester','row'], 'The template includes full names, courses and completion metadata without evaluation answers or comments.');
    $templateEntries = readZipEntries($templateFile, ['xl/workbook.xml'], 'xl/worksheets/');
    $declarations = loadImportXml($templateEntries['xl/workbook.xml'], 'workbook')->xpath('//*[local-name()="sheet"]');
    completionCheck(array_map(static fn(SimpleXMLElement $sheet): string => (string) $sheet['name'], $declarations) === ['Year 1','Year 2','Year 3','Year 4'], 'The workbook has four named year-level tabs in order.');
    completionCheck(str_contains($templateEntries['xl/worksheets/sheet1.xml'], 'Requirements Completion') && !str_contains($templateEntries['xl/worksheets/sheet1.xml'], 'Evaluation Completed'), 'New workbooks use the Requirements Completion column label.');
    foreach ([1 => 2, 2 => 0, 3 => 3, 4 => 1] as $year => $studentIndex) {
        $sheetRows = parseStudentWorksheet($templateEntries['xl/worksheets/sheet' . $year . '.xml'], []);
        completionCheck(in_array($students[$studentIndex]['number'], array_column(array_slice($sheetRows, 3), 0), true), 'Year ' . $year . ' contains students from the matching year level.');
        $xml = loadImportXml($templateEntries['xl/worksheets/sheet' . $year . '.xml'], 'year sheet');
        $validation = $xml->xpath('//*[local-name()="dataValidation"]')[0] ?? null;
        $formula = $validation?->xpath('./*[local-name()="formula1"]');
        completionCheck($validation !== null && (string) $validation['sqref'] === 'D4:D' . max(4, count($sheetRows)) && (string) $validation['showDropDown'] === '0' && (string) $validation['errorStyle'] === 'stop' && (string) ($formula[0] ?? '') === '"Completed,Incomplete"', 'Year ' . $year . ' has a visible Completed/Incomplete dropdown on all completion cells.');
    }
    $legacy = readCcsCompletionWorkbook(completionFile([completionRow($students[0], 'YES', $context)], true));
    completionCheck(count($legacy) === 1 && $legacy[0]['completed'] === 'YES', 'Previously downloaded four-column completion templates still import.');
    completionCheck(deanMinimumBulkCount($database, ['organization_code' => 'CCS', 'organization_id' => $organizations['CCS']]) === 1, 'CCS has no five-student minimum.');
    completionRejected(fn() => approveDeanClearance($database, $deanId, $students[0]['clearance_id']), 'Manual clearance waits for the official completion list.');
    $bad = completionRow($students[1], 'YES', $context); $bad['student_number'] = 'UNKNOWN';
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[0], 'YES', $context), $bad]), 'An unknown student rejects the entire import.');
    completionCheck((int) $database->query('SELECT COUNT(*) FROM ccs_evaluation_completions')->fetchColumn() === 0, 'A rejected file leaves no partially imported completion records.');
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[0], 'YES', $context), completionRow($students[0], 'NO', $context)]), 'Duplicate student numbers are rejected.');
    $duplicate = completionRow($students[0], 'NO', $context); $duplicate['year_level'] = 3;
    $duplicateFile = completionFile([completionRow($students[0], 'YES', $context), $duplicate]);
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, readCcsCompletionWorkbook($duplicateFile)), 'Duplicate student numbers across year-level sheets reject the entire import.');
    $oldPeriod = completionRow($students[0], 'YES', $context); $oldPeriod['school_year'] = '2097-2098';
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [$oldPeriod]), 'A workbook for the wrong school year is rejected.');
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId + 1, [completionRow($students[0], 'YES', $context)]), 'A stale active-semester ID is rejected.');
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[0], 'MAYBE', $context)]), 'Invalid completion values are rejected.');
    completionRejected(fn() => createCcsCompletionWorkbook($database, $wrongDean), 'A non-CCS Dean cannot download the completion roster.');
    completionRejected(fn() => importCcsCompletionWorkbook($database, $wrongDean, $periodId, [completionRow($students[0], 'YES', $context)]), 'A non-CCS Dean cannot import completion statuses.');
    $foreignStudent = completionStudent($database, (int) $organizations['CTE'], $periodId, 2, 'EXCEL-OTHER-COLLEGE');
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($foreignStudent, 'YES', $context)]), 'A CCS import cannot update a student from another college.');
    $database->prepare("UPDATE semester_enrollments SET status='REMOVED' WHERE student_id=? AND academic_period_id=?")->execute([$students[2]['student_id'], $periodId]);
    completionRejected(fn() => importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[2], 'YES', $context)]), 'A completion list cannot update a removed enrollment.');
    $database->prepare("UPDATE semester_enrollments SET status='ENROLLED' WHERE student_id=? AND academic_period_id=?")->execute([$students[2]['student_id'], $periodId]);
    $entries = readZipEntries($templateFile, ['xl/sharedStrings.xml'], 'xl/worksheets/'); $sheet = $entries['xl/worksheets/sheet4.xml'];
    $formulaFile = tempnam(sys_get_temp_dir(), 'completion-formula-'); $files[] = $formulaFile;
    $formulaEntries = $entries; $formulaEntries['xl/worksheets/sheet4.xml'] = str_replace('</sheetData>', '<row r="99"><c r="D99"><f>"Completed"</f><v>Completed</v></c></row></sheetData>', $sheet);
    file_put_contents($formulaFile, createStoredZipArchive($formulaEntries));
    completionRejected(fn() => readCcsCompletionWorkbook($formulaFile), 'A formula in the last year-level sheet rejects the workbook.');
    $extraColumn = tempnam(sys_get_temp_dir(), 'completion-extra-'); $files[] = $extraColumn;
    $extraEntries = $entries; $extraEntries['xl/worksheets/sheet4.xml'] = str_replace('</sheetData>', '<row r="99"><c r="G99" t="inlineStr"><is><t>Unexpected</t></is></c></row></sheetData>', $sheet);
    file_put_contents($extraColumn, createStoredZipArchive($extraEntries));
    completionRejected(fn() => readCcsCompletionWorkbook($extraColumn), 'Extra columns on a later year-level sheet are rejected.');
    $wrongHeaders = tempnam(sys_get_temp_dir(), 'completion-headers-'); $files[] = $wrongHeaders;
    $wrongEntries = $entries; $wrongEntries['xl/worksheets/sheet4.xml'] = str_replace('Requirements Completion', 'Evaluation Answers', $sheet);
    file_put_contents($wrongHeaders, createStoredZipArchive($wrongEntries));
    completionRejected(fn() => readCcsCompletionWorkbook($wrongHeaders), 'Every year-level sheet must have the completion-template headers.');
    $malformed = tempnam(sys_get_temp_dir(), 'completion-invalid-'); $files[] = $malformed; file_put_contents($malformed, 'not an xlsx');
    completionRejected(fn() => readCcsCompletionWorkbook($malformed), 'Invalid XLSX files are rejected safely.');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText); if ($socket === false) { throw new RuntimeException($errorText); }
    $address = stream_socket_get_name($socket, false); fclose($socket); $base = 'http://' . $address;
    $environment = getenv(); $environment['DB_DATABASE'] = $temporary; unset($environment['APP_BASE_URL']);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__)], [0 => ['pipe','r'], 1 => ['file',$log,'a'], 2 => ['file',$log,'a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start isolated server.'); } fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) { $probe = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1); if ($probe !== false) { fclose($probe); break; } usleep(100000); }
    $deanClient = completionLogin('dean-excel-test@thelewiscollege.edu.ph'); $studentClient = completionLogin($students[0]['email']); $wrongClient = completionLogin('wrong-dean-excel-test@thelewiscollege.edu.ph');
    $page = completionHttp($deanClient['handle'], '/dean/dashboard.php');
    completionCheck($page['status'] === 200 && str_contains($page['body'], 'Download Excel template') && !str_contains($page['body'], 'Submitted Requirements') && !str_contains($page['body'], 'evaluation_image.php'), 'CCS dashboard exposes Excel controls without screenshot-review links.');
    $profile = completionHttp($deanClient['handle'], '/auth/profile.php');
    completionCheck($profile['status'] === 200 && str_contains($profile['body'], 'CCS Dean Clearance') && !str_contains($profile['body'], 'Submitted Requirements') && !str_contains($profile['body'], '/dean/submitted_requirements.php'), 'The CCS Dean profile has no Submitted Requirements navigation link.');
    completionSnapshot('open', $page['body']); completionSnapshot('profile', $profile['body']);
    completionCheck(count(completionRosterNumbers($page['body'])) === 30, 'The first dashboard page contains 30 student rows.');
    $secondPage = completionHttp($deanClient['handle'], '/dean/dashboard.php?page=2');
    completionCheck(count(completionRosterNumbers($secondPage['body'])) === 7, 'Pagination shows the remaining seven students on page two.');
    $searchPage = completionHttp($deanClient['handle'], '/dean/dashboard.php?search=0000123');
    completionCheck(completionRosterNumbers($searchPage['body']) === ['0000123'], 'Student number search preserves and finds leading-zero IDs.');
    $yearPage = completionHttp($deanClient['handle'], '/dean/dashboard.php?year_level=4');
    completionCheck(completionRosterNumbers($yearPage['body']) === [$students[1]['number']], 'Year-level filtering shows only the matching fourth-year student.');
    $coursePage = completionHttp($deanClient['handle'], '/dean/dashboard.php?course=NO-SUCH-COURSE');
    completionCheck(completionRosterNumbers($coursePage['body']) === [] && str_contains($coursePage['body'], 'No students match this view.'), 'Course filters render a controlled empty state.');
    $report = completionHttp($deanClient['handle'], '/dean/dashboard.php?year_level=4&export=xlsx');
    $reportPath = tempnam(sys_get_temp_dir(), 'completion-report-'); $files[] = $reportPath; file_put_contents($reportPath, $report['body']);
    $reportEntries = readZipEntries($reportPath, [], 'xl/worksheets/'); $reportRows = parseStudentWorksheet($reportEntries['xl/worksheets/sheet1.xml'], []);
    completionCheck($report['status'] === 200 && count($reportRows) === 4 && $reportRows[3][0] === $students[1]['number'], 'Excel report export respects the selected year-level filter.');
    $database->prepare("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=?")->execute([$periodId]);
    completionCheck(completionHttp($studentClient['handle'],'/student/clearance_preview.php?period=' . $periodId)['status'] === 404 && completionHttp($studentClient['handle'],'/student/clearance_pdf.php?period=' . $periodId . '&view=inline')['status'] === 404, 'Preview and inline PDF are unavailable before clearance opens.');
    $preparing = completionHttp($deanClient['handle'], '/dean/dashboard.php');
    completionCheck($preparing['status'] === 200 && strpos($preparing['body'], '<h2>Semester preparation</h2>') < strpos($preparing['body'], 'id="completion-heading"'), 'The completion panel follows Semester Preparation during preparation.');
    completionSnapshot('preparing', $preparing['body']);
    $ready = completionHttp($deanClient['handle'], '/clearance/readiness_action.php', ['csrf_token'=>$deanClient['csrf'],'action'=>'ready']);
    completionCheck($ready['status'] === 303 && officeReadiness($database, 'DEAN')['readiness_status'] === 'READY', 'Mark Dean Office Ready records readiness during preparation.');
    $unready = completionHttp($deanClient['handle'], '/clearance/readiness_action.php', ['csrf_token'=>$deanClient['csrf'],'action'=>'unready']);
    completionCheck($unready['status'] === 303 && officeReadiness($database, 'DEAN')['readiness_status'] === 'NOT_READY', 'Undo Ready restores the preparation state.');
    $database->prepare("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=?")->execute([$periodId]);
    completionHttp($deanClient['handle'], '/clearance/readiness_action.php', ['csrf_token'=>$deanClient['csrf'],'action'=>'ready']);
    completionCheck(officeReadiness($database, 'DEAN')['readiness_status'] === 'NOT_READY', 'Ready cannot be changed after clearance opens.');
    $remark = 'Synthetic review <script>alert(1)</script>';
    $remarkPost = ['csrf_token'=>$deanClient['csrf'],'clearance_id'=>$students[0]['clearance_id'],'remarks'=>$remark];
    $remarkResponse = completionHttp($deanClient['handle'], '/clearance/remarks_action.php', $remarkPost);
    $remarkQuery = $database->prepare("SELECT record.office_remarks FROM clearance_stations record JOIN stations station ON station.id=record.station_id AND station.code='DEAN' WHERE record.clearance_id=?");
    $remarkQuery->execute([$students[0]['clearance_id']]);
    completionCheck($remarkResponse['status'] === 303 && $remarkQuery->fetchColumn() === $remark, 'The Dean can save remarks for an assigned pending student.');
    $remarkPage = completionHttp($deanClient['handle'], '/dean/dashboard.php?search=0000123');
    completionCheck(!str_contains($remarkPage['body'], '<script>alert(1)</script>') && str_contains($remarkPage['body'], '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Saved remarks are escaped when the dashboard renders them.');
    $remarkPost['csrf_token'] = $wrongClient['csrf']; $remarkPost['remarks'] = 'Unauthorized change';
    completionHttp($wrongClient['handle'], '/clearance/remarks_action.php', $remarkPost); $remarkQuery->execute([$students[0]['clearance_id']]);
    completionCheck($remarkQuery->fetchColumn() === $remark, 'Another college Dean cannot edit CCS student remarks.');
    $wrongApproval = completionHttp($wrongClient['handle'], '/dean/action.php', ['csrf_token'=>$wrongClient['csrf'],'clearance_id'=>$students[0]['clearance_id'],'action'=>'approve']);
    completionCheck($wrongApproval['status'] === 303 && officeClearanceContext($database, 'DEAN', $students[0]['clearance_id'])['station_status'] === 'PENDING', 'Another college Dean cannot clear a CCS student.');
    $studentApproval = completionHttp($studentClient['handle'], '/dean/action.php', ['csrf_token'=>$studentClient['csrf'],'clearance_id'=>$students[0]['clearance_id'],'action'=>'approve']);
    completionCheck($studentApproval['status'] === 403, 'Students cannot invoke Dean clearance actions.');
    $downloadResponse = completionHttp($deanClient['handle'], '/dean/completion_workbook_download.php');
    completionCheck($downloadResponse['status'] === 200 && str_starts_with($downloadResponse['body'], 'PK'), 'Authenticated template download returns a real XLSX.');
    $httpTemplate = tempnam(sys_get_temp_dir(), 'completion-http-'); $files[] = $httpTemplate; file_put_contents($httpTemplate, $downloadResponse['body']);
    completionCheck(count(readCcsCompletionWorkbook($httpTemplate)) === 37 && str_contains($downloadResponse['body'], 'Full Name') && str_contains($downloadResponse['body'], 'sheet name="Year 4"'), 'The HTTP download includes full names and all year-level sheets.');
    $portal = completionHttp($studentClient['handle'], '/student/dashboard.php');
    completionSnapshot('student-clearance',$portal['body']);
    completionCheck($portal['status'] === 200 && !str_contains($portal['body'], 'evaluation_proofs') && !str_contains($portal['body'], '/student/evaluation_action.php') && str_contains($portal['body'], 'No upload is needed.'), 'Student dashboard has no evaluation screenshot form.');
    completionCheck(str_contains($portal['body'], '/student/clearance_pdf.php?period=' . $periodId) && str_contains($portal['body'], 'Download PDF'), 'Students can download the digital clearance while the semester is open.');
    completionCheck(str_contains($portal['body'],'/student/clearance_preview.php?period=' . $periodId) && str_contains($portal['body'],'Preview clearance'), 'The student Clearance section has a separate paper preview button.');
    completionCheck(str_contains($portal['body'],'data-open-clearance-preview') && str_contains($portal['body'],'<dialog class="portal-clearance-preview"') && str_contains($portal['body'],'data-clearance-preview-src=') && !preg_match('/<iframe[^>]*\ssrc="[^"]*clearance_pdf\.php/', $portal['body']), 'The student dashboard includes a dialog preview that loads its private PDF only when opened.');
    $preview = completionHttp($studentClient['handle'],'/student/clearance_preview.php?period=' . $periodId);
    completionCheck($preview['status'] === 200 && str_contains($preview['body'],'title="Your clearance paper"') && str_contains($preview['body'],'&amp;view=inline') && str_contains($preview['body'],'Download PDF'), 'Opening the preview displays the paper with a separate PDF download action.');
    completionSnapshot('clearance-preview',$preview['body']);
    completionCheck(completionHttp($deanClient['handle'],'/student/clearance_preview.php?period=' . $periodId)['status'] === 403, 'Staff cannot open the student-only clearance preview.');
    completionCheck(completionHttp($studentClient['handle'],'/student/clearance_preview.php?period[]=1')['status'] === 404, 'Malformed preview requests are rejected safely.');
    $initialPdf = completionHttp($studentClient['handle'], '/student/clearance_pdf.php?period=' . $periodId);
    completionCheck($initialPdf['status'] === 200 && str_starts_with($initialPdf['body'], '%PDF-1.4') && !str_contains($initialPdf['body'], '(Pending clearance)') && !str_contains($initialPdf['body'], 'Synthetic review'), 'An unfinished clearance downloads as a valid PDF with pending remarks blank and pending offices unsigned.');
    $paperPurposes = ['Final Exam - Graduating', 'Final Exam - Non-Graduating', 'Withdrawal', 'Transfer Out', 'Readmission', 'Others:'];
    completionCheck(count(array_filter($paperPurposes, fn(string $label): bool => str_contains($initialPdf['body'], '(' . $label . ')'))) === count($paperPurposes), 'The paper retains all six distinct clearance purposes, including a writable Others field.');
    $paperFields = ['LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'DATE', 'STUDENT NUMBER', 'COURSE / PROGRAM', 'YEAR LEVEL', 'SY / SEMESTER', 'ADDRESS', 'PHONE NUMBER', 'NAME OF THE GUARDIAN', 'GUARDIAN PHONE NUMBER'];
    completionCheck(count(array_filter($paperFields, fn(string $label): bool => str_contains($initialPdf['body'], '(' . $label . ')'))) === count($paperFields), 'The paper includes the original student, contact, guardian, and semester fields.');
    completionCheck(str_contains($initialPdf['body'], '(Purpose for Transfer Out/Withdrawal)')
        && str_contains($initialPdf['body'], "(Student's Name and Signature)")
        && str_contains($initialPdf['body'], "(Representative's Name and Signature)")
        && str_contains($initialPdf['body'], 'parents/guardians must be informed')
        && str_contains($initialPdf['body'], 'authorization letter with a photocopy of a valid ID')
        && str_contains($initialPdf['body'], 'Data Privacy Notice:'), 'The paper restores the reason box, both personal signature lines, authorization notes, and privacy notice.');
    $inlinePdf = completionHttp($studentClient['handle'],'/student/clearance_pdf.php?period=' . $periodId . '&view=inline');
    completionCheck($inlinePdf['status'] === 200 && str_starts_with($inlinePdf['body'],'%PDF-1.4') && str_contains(strtolower($inlinePdf['headers']),'content-disposition: inline;') && str_contains(strtolower($initialPdf['headers']),'content-disposition: attachment;'), 'Preview PDFs display inline while the download action still downloads the paper.');
    completionCheck(str_contains(strtolower($preview['headers']),'no-store') && str_contains(strtolower($inlinePdf['headers']),'no-store'), 'Preview and PDF responses prevent caching private student documents.');
    foreach (['/student/evaluation_action.php','/dean/submitted_requirement_action.php','/dean/evaluation_image.php?id=1','/dean/submitted_requirements.php'] as $path) {
        foreach (['GET','POST'] as $method) { $response = completionHttp($deanClient['handle'], $path, $method === 'POST' ? ['action'=>'verify','csrf_token'=>$deanClient['csrf']] : null); completionCheck($response['status'] === 404, 'Removed screenshot page returns 404 for ' . $method . ': ' . $path); }
    }
    $oneFile = completionFile([completionRow($students[0], 'Completed', $context)]);
    completionCheck(count(readCcsCompletionWorkbook($oneFile)) === 1, 'Empty year-level tabs are accepted alongside a completed student tab.');
    $post = ['csrf_token'=>$deanClient['csrf'], 'period_id'=>$periodId, 'completion_workbook'=>new CURLFile($oneFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'completion.xlsx')];
    $badCsrf = $post; $badCsrf['csrf_token'] = 'invalid'; completionHttp($deanClient['handle'], '/dean/completion_workbook_import.php', $badCsrf);
    completionCheck((int) $database->query('SELECT COUNT(*) FROM ccs_evaluation_completions')->fetchColumn() === 0, 'Invalid CSRF cannot import completion data.');
    $wrongPost = $post; $wrongPost['csrf_token'] = $wrongClient['csrf']; completionHttp($wrongClient['handle'], '/dean/completion_workbook_import.php', $wrongPost);
    completionCheck((int) $database->query('SELECT COUNT(*) FROM ccs_evaluation_completions')->fetchColumn() === 0, 'Wrong-college HTTP import cannot alter CCS data.');
    $studentPost = $post; $studentPost['csrf_token'] = $studentClient['csrf']; $response = completionHttp($studentClient['handle'], '/dean/completion_workbook_import.php', $studentPost);
    completionCheck($response['status'] === 403 && (int) $database->query('SELECT COUNT(*) FROM ccs_evaluation_completions')->fetchColumn() === 0, 'Students cannot import their own completion status.');
    $response = completionHttp($deanClient['handle'], '/dean/completion_workbook_import.php', $post);
    completionCheck($response['status'] === 303 && bulkReadyClearanceIds($database, 'DEAN', (int) $organizations['CCS']) === [$students[0]['clearance_id']], 'Actual Excel upload marks only the listed completed student ready.');
    completionCheck(officeClearanceContext($database, 'DEAN', $students[0]['clearance_id'])['station_status'] === 'PENDING', 'Import does not automatically approve Dean clearance.');
    $completion = $database->query('SELECT * FROM ccs_evaluation_completions')->fetch();
    completionCheck((int) $completion['recorded_by'] === $deanId && $completion['recorded_at'] !== null, 'Completion import records the Dean and import time.');
    $blank = importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[0], '', $context)]);
    completionCheck($blank['not_completed'] === 1 && bulkReadyClearanceIds($database, 'DEAN', (int) $organizations['CCS']) === [] && $database->query('SELECT status FROM ccs_evaluation_completions')->fetchColumn() === 'NOT_COMPLETED', 'A blank cell records Incomplete and removes previous completion eligibility.');
    completionRejected(fn() => approveDeanClearance($database, $deanId, $students[0]['clearance_id']), 'A blank completion cell blocks approval when evaluation is required.');
    importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($students[0], 'Completed', $context)]);
    $response = completionHttp($deanClient['handle'], '/dean/action.php', ['csrf_token'=>$deanClient['csrf'], 'action'=>'approve_all_ready']);
    completionCheck($response['status'] === 303 && officeClearanceContext($database, 'DEAN', $students[0]['clearance_id'])['station_status'] === 'APPROVED', 'One ready student can be bulk cleared while 36 others remain uncleared.');
    $batchRows = []; foreach (array_slice($students, 1, 35) as $student) { $batchRows[] = completionRow($student, 'Completed', $context); }
    $batchFile = completionFile($batchRows);
    $response = completionHttp($deanClient['handle'], '/dean/completion_workbook_import.php', ['csrf_token'=>$deanClient['csrf'],'period_id'=>$periodId,'completion_workbook'=>new CURLFile($batchFile,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','completion.xlsx')]);
    completionCheck($response['status'] === 303 && count(bulkReadyClearanceIds($database, 'DEAN', (int) $organizations['CCS'])) === 35, 'Completion import supports an eligible roster beyond the 30-row page.');
    $fourthYearEvaluation = completionRequirement($database, $students[1]['clearance_id'], 'CCS_FOURTH_YEAR_FACULTY_EVALUATION');
    completionCheck(($fourthYearEvaluation['status'] ?? '') === 'VERIFIED' && (int) $fourthYearEvaluation['verified_by'] === $deanId && $fourthYearEvaluation['verified_at'] !== null, 'First-semester Year 4 Completed verifies Faculty Evaluation with the Dean and timestamp.');
    completionCheck(officeClearanceContext($database, 'DEAN', $students[1]['clearance_id'])['station_status'] === 'PENDING', 'First-semester Year 4 verification does not automatically clear the student.');
    completionCheck(completionRequirement($database, $students[1]['clearance_id'], 'CCS_GRADUATION_APPLICATION') === null, 'First-semester import does not create or verify the second-semester graduation requirement.');
    $filtered = completionHttp($deanClient['handle'], '/dean/dashboard.php?search=NO-MATCH');
    completionCheck(str_contains($filtered['body'], 'Clear all ready (35)'), 'Bulk count includes ready students outside the current search.');
    completionHttp($deanClient['handle'], '/dean/action.php', ['csrf_token'=>$deanClient['csrf'],'action'=>'approve_all_ready']);
    $count = (int) $database->query("SELECT COUNT(*) FROM clearance_stations record JOIN stations station ON station.id=record.station_id WHERE station.code='DEAN' AND record.status='APPROVED'")->fetchColumn();
    completionCheck($count === 36, 'Bulk approval clears all 35 ready students across pages without an upper student cap.');
    $last = $students[36]; importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($last,'Incomplete',$context)]);
    completionRejected(fn() => approveDeanClearance($database, $deanId, $last['clearance_id']), 'An Incomplete dropdown value blocks manual clearance.');
    $database->prepare("UPDATE clearance_stations record JOIN stations station ON station.id=record.station_id SET record.status='RETURNED',record.returned_reason='Old evaluation submission' WHERE record.clearance_id=? AND station.code='DEAN'")->execute([$last['clearance_id']]);
    importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($last,'Completed',$context)]);
    completionCheck(officeClearanceContext($database, 'DEAN', $last['clearance_id'])['station_status'] === 'PENDING', 'A completed list restores a returned Dean record to pending review.');
    $response = completionHttp($deanClient['handle'], '/dean/action.php', ['csrf_token'=>$deanClient['csrf'],'clearance_id'=>$last['clearance_id'],'action'=>'approve']);
    completionCheck($response['status'] === 303 && officeClearanceContext($database, 'DEAN', $last['clearance_id'])['station_status'] === 'APPROVED', 'Individual manual clearing succeeds after completion import.');
    $preserved = importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($last,'NO',$context)]);
    completionCheck($preserved['already_cleared'] === 1 && officeClearanceContext($database, 'DEAN', $last['clearance_id'])['station_status'] === 'APPROVED', 'Later imports preserve existing signed clearances.');
    $signedBlank = importCcsCompletionWorkbook($database, $deanId, $periodId, [completionRow($last,'',$context)]);
    completionCheck($signedBlank['already_cleared'] === 1 && officeClearanceContext($database, 'DEAN', $last['clearance_id'])['station_status'] === 'APPROVED', 'A blank cell preserves an already signed clearance.');
    foreach (['phase2_dean_clearance_test.php','phase3_office_workflow_test.php'] as $test) {
        $process = proc_open([PHP_BINARY,__DIR__ . '/' . $test], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $testPipes, dirname(__DIR__), $environment);
        if (!is_resource($process)) { throw new RuntimeException('Cannot run the regression.'); } fclose($testPipes[0]); $output = stream_get_contents($testPipes[1]); $error = stream_get_contents($testPipes[2]); fclose($testPipes[1]); fclose($testPipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException($test . ': ' . $output . $error); } completionCheck(true, 'Existing ' . $test . ' passes with completion lists in the isolated database.');
    }
    $database->exec("UPDATE academic_periods SET status='CLOSED',clearance_status='CLOSED' WHERE id=" . $periodId);
    completionCheck(completionHttp($studentClient['handle'],'/student/clearance_preview.php?period=' . $periodId)['status'] === 404, 'Closed semesters cannot open the clearance preview.');
    completionRejected(fn() => importCcsCompletionWorkbook($database,$deanId,$periodId,[completionRow($students[0],'YES',$context)]), 'Closed semesters reject completion imports.');
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2098-2099','2ND','ACTIVE','OPEN')"); $secondPeriod = (int) $database->lastInsertId();
    $graduate = completionStudent($database,(int)$organizations['CCS'],$secondPeriod,4,'EXCEL-GRADUATE');
    $graduateContext = ccsCompletionImportContext($database, $deanId);
    $graduateTemplate = tempnam(sys_get_temp_dir(), 'completion-graduate-'); $files[] = $graduateTemplate;
    file_put_contents($graduateTemplate, createCcsCompletionWorkbook($database, $deanId));
    $graduateRows = readCcsCompletionWorkbook($graduateTemplate);
    completionCheck(count($graduateRows) === 1 && $graduateRows[0]['student_number'] === $graduate['number'] && $graduateRows[0]['course'] === 'Test Program', 'Year 4 is populated in second semester even when evaluation is not an applicable requirement.');
    $graduateEntries = readZipEntries($graduateTemplate, [], 'xl/worksheets/');
    completionCheck(str_contains($graduateEntries['xl/worksheets/sheet4.xml'], $graduate['number']), 'Second-semester fourth-year students appear on the Year 4 tab.');
    completionRejected(fn() => approveDeanClearance($database,$deanId,$graduate['clearance_id']), 'Second-semester Year 4 cannot clear before graduation verification.');
    $graduateFile = completionFile([completionRow($graduate, 'Completed', $graduateContext)]);
    $graduatePost = ['csrf_token'=>$deanClient['csrf'], 'period_id'=>$secondPeriod, 'completion_workbook'=>new CURLFile($graduateFile,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','requirements.xlsx')];
    $response = completionHttp($deanClient['handle'], '/dean/completion_workbook_import.php', $graduatePost);
    $graduation = completionRequirement($database, $graduate['clearance_id'], 'CCS_GRADUATION_APPLICATION');
    completionCheck($response['status'] === 303 && ($graduation['status'] ?? '') === 'VERIFIED' && (int) $graduation['verified_by'] === $deanId && $graduation['verified_at'] !== null, 'Second-semester Year 4 Completed verifies Application for Graduation through the actual Excel upload.');
    completionCheck(officeClearanceContext($database, 'DEAN', $graduate['clearance_id'])['station_status'] === 'PENDING', 'Second-semester Excel verification leaves Dean clearance pending.');
    completionCheck(bulkReadyClearanceIds($database, 'DEAN', (int) $organizations['CCS']) === [$graduate['clearance_id']], 'Verified graduation becomes ready for a separate individual or bulk clearance action.');
    completionCheck(completionRequirement($database, $graduate['clearance_id'], 'CCS_FOURTH_YEAR_FACULTY_EVALUATION') === null, 'Second-semester import does not create or verify first-semester Faculty Evaluation.');
    $audit = $database->query("SELECT user_id,description FROM audit_logs WHERE student_id=" . $graduate['student_id'] . " AND action='CCS_REQUIREMENTS_COMPLETION_IMPORTED' ORDER BY id DESC LIMIT 1")->fetch();
    completionCheck((int) ($audit['user_id'] ?? 0) === $deanId && str_contains($audit['description'], 'clearance approval remains separate'), 'Graduation workbook verification records an audit event attributed to the Dean.');
    importCcsCompletionWorkbook($database, $deanId, $secondPeriod, [completionRow($graduate, '', $graduateContext)]);
    $incompleteGraduation = completionRequirement($database, $graduate['clearance_id'], 'CCS_GRADUATION_APPLICATION');
    completionCheck($incompleteGraduation['status'] === 'NOT_STARTED' && $incompleteGraduation['verified_by'] === null && $incompleteGraduation['verified_at'] === null && bulkReadyClearanceIds($database, 'DEAN', (int) $organizations['CCS']) === [], 'Blank graduation completion removes verification and bulk eligibility while still pending.');
    completionRejected(fn() => approveDeanClearance($database,$deanId,$graduate['clearance_id']), 'Blank graduation completion blocks individual clearance.');
    importCcsCompletionWorkbook($database, $deanId, $secondPeriod, [completionRow($graduate, 'Incomplete', $graduateContext)]);
    completionCheck(completionRequirement($database, $graduate['clearance_id'], 'CCS_GRADUATION_APPLICATION')['status'] === 'NOT_STARTED', 'Explicit Incomplete leaves the graduation requirement unverified.');
    $signatureDrawing = json_encode(['strokes'=>[[[0.1,0.5],[0.2,0.3],[0.3,0.6],[0.45,0.4],[0.6,0.5]]],'aspect_ratio'=>3.75]);
    $signature = renderStaffSignature($signatureDrawing)['data'];
    $signaturePost = ['csrf_token'=>$deanClient['csrf'],'printed_name'=>'Dr. Synthetic Dean','position_title'=>'College Dean','signature_drawing'=>$signatureDrawing];
    $saveSignature = completionHttp($deanClient['handle'], '/auth/signature_action.php', $signaturePost);
    completionCheck($saveSignature['status'] === 303 && staffSignature($database, $deanId)['printed_name'] === 'Dr. Synthetic Dean', 'The Dean can save a drawn signature through the actual profile action.');
    $signatureImage = completionHttp($deanClient['handle'], '/auth/signature_image.php');
    completionCheck($signatureImage['status'] === 200 && $signatureImage['body'] === $signature, 'The saved drawing is returned as a protected PNG for its owner.');
    $drawingProfile = completionHttp($deanClient['handle'], '/auth/profile.php');
    completionSnapshot('profile-feedback',$drawingProfile['body']);
    $signatureFeedback = 'Signature saved. Use the saved signature cards to choose which signature to use.';
    $deanInbox = completionHttp($deanClient['handle'],'/auth/notifications.php');
    $deanInboxPayload = json_decode($deanInbox['body'],true,512,JSON_THROW_ON_ERROR);
    $feedbackItems = array_values(array_filter($deanInboxPayload['notifications'],static fn(array $item): bool => $item['message'] === $signatureFeedback));
    completionCheck($deanInbox['status'] === 200 && count($feedbackItems) === 1 && !$feedbackItems[0]['is_read'], 'Saving a signature stores its status message once in the user notification bell as unread.');
    completionHttp($deanClient['handle'],'/auth/notifications.php');
    completionCheck((int)$database->query("SELECT COUNT(*) FROM notifications WHERE user_id=" . $deanId . " AND title='ClearGo' AND message=" . $database->quote($signatureFeedback))->fetchColumn() === 1, 'Refreshing the notification list does not duplicate action feedback.');
    $studentNotice = completionHttp($studentClient['handle'],'/student/password_action.php',['csrf_token'=>$studentClient['csrf'],'current_password'=>'Incorrect synthetic password','new_password'=>'Synthetic-New-Password!','confirm_password'=>'Synthetic-New-Password!']);
    $passwordFeedback = completionHttp($studentClient['handle'],'/student/password.php');
    completionCheck($studentNotice['status'] === 303 && str_contains($passwordFeedback['body'],'The current password is incorrect.'), 'Form feedback remains available on a page without a notification bell.');
    $studentInbox = completionHttp($studentClient['handle'],'/auth/notifications.php?period=' . $periodId);
    completionCheck($studentInbox['status'] === 200 && str_contains($studentInbox['body'],'The current password is incorrect.') && !str_contains($studentInbox['body'],$signatureFeedback), 'Student feedback survives inline consumption and stays private to that student.');
    $studentFeedbackPage = completionHttp($studentClient['handle'],'/student/dashboard.php?period=' . $periodId);
    completionSnapshot('student-feedback',$studentFeedbackPage['body']);
    $badRead = completionHttp($deanClient['handle'],'/auth/notifications.php',['action'=>'mark_read','csrf_token'=>'invalid'],true);
    completionCheck($badRead['status'] === 403 && (int)$database->query('SELECT is_read FROM notifications WHERE id=' . $feedbackItems[0]['id'])->fetchColumn() === 0, 'Invalid CSRF cannot mark action notifications read.');
    $readFeedback = completionHttp($deanClient['handle'],'/auth/notifications.php',['action'=>'mark_read','csrf_token'=>$deanClient['csrf']],true);
    completionCheck($readFeedback['status'] === 200 && (int)$database->query('SELECT is_read FROM notifications WHERE id=' . $feedbackItems[0]['id'])->fetchColumn() === 1, 'Opening the notification bell can mark saved action feedback read.');
    completionCheck((int)$database->query("SELECT is_read FROM notifications WHERE user_id=" . $students[0]['user_id'] . " AND message='The current password is incorrect.' ORDER BY id DESC LIMIT 1")->fetchColumn() === 0, 'Reading a staff notification leaves another user feedback unread.');
    completionCheck(str_contains($drawingProfile['body'], 'data-signature-form') && str_contains($drawingProfile['body'], '<canvas') && !str_contains($drawingProfile['body'], 'name="signature_image"'), 'The profile offers a drawing pad instead of a signature file upload.');
    $invalidSignature = $signaturePost; $invalidSignature['signature_drawing'] = '[]';
    completionHttp($deanClient['handle'], '/auth/signature_action.php', $invalidSignature);
    completionCheck(completionHttp($deanClient['handle'], '/auth/signature_image.php')['body'] === $signature, 'A blank drawing cannot replace the saved signature.');
    $invalidSignature['signature_drawing'] = ['invalid'];
    completionHttp($deanClient['handle'], '/auth/signature_action.php', $invalidSignature);
    completionCheck(completionHttp($deanClient['handle'], '/auth/signature_image.php')['body'] === $signature, 'Malformed array input is rejected without replacing the signature.');
    $invalidSignature = $signaturePost; $invalidSignature['csrf_token'] = 'invalid';
    completionHttp($deanClient['handle'], '/auth/signature_action.php', $invalidSignature);
    completionCheck((int)$database->query("SELECT COUNT(*) FROM audit_logs WHERE action='AUTHORIZED_SIGNATURE_SAVED'")->fetchColumn() === 1, 'Invalid CSRF and drawing requests create no signature save audit.');
    $firstSignatureId = (int) staffSignature($database,$deanId)['saved_signature_id'];
    completionCheck(count(staffSavedSignatures($database,$deanId)) === 1 && $firstSignatureId > 0, 'The first saved signature is selected automatically.');
    $studentSignature = $signaturePost; $studentSignature['csrf_token'] = $studentClient['csrf'];
    completionCheck(completionHttp($studentClient['handle'], '/auth/signature_action.php', $studentSignature)['status'] === 403 && staffSignature($database, (int)$students[0]['user_id']) === false, 'Students cannot register an office signature.');
    completionCheck(completionHttp($studentClient['handle'], '/auth/signature_image.php')['status'] === 403, 'Students cannot retrieve standalone staff signature images.');
    completionHttp($deanClient['handle'], '/dean/completion_workbook_import.php', $graduatePost);
    $response = completionHttp($deanClient['handle'], '/dean/action.php', ['csrf_token'=>$deanClient['csrf'],'action'=>'approve_all_ready']);
    completionCheck($response['status'] === 303 && officeClearanceContext($database, 'DEAN', $graduate['clearance_id'])['station_status'] === 'APPROVED', 'A separate Clear all ready action clears the Excel-verified graduate.');
    completionCheck((int)$database->query('SELECT COUNT(*) FROM clearance_station_signatures signature JOIN clearance_stations record ON record.id=signature.clearance_station_id WHERE record.clearance_id=' . $graduate['clearance_id'])->fetchColumn() === 1, 'Bulk approval saves the selected signature before any PDF download.');
    $graduateClient = completionLogin($graduate['email']);
    $database->prepare('UPDATE students SET first_name=?,middle_name=? WHERE id=?')->execute(['Synthetic Mary','Middle Example',$graduate['student_id']]);
    $signedPdf = completionHttp($graduateClient['handle'], '/student/clearance_pdf.php?period=' . $secondPeriod);
    completionCheck(str_contains($signedPdf['body'],'(Synthetic Mary)') && str_contains($signedPdf['body'],'(Middle Example)') && str_contains($signedPdf['body'],'(Excel Student, Synthetic Mary Middle Example)'), 'The paper places compound first and middle names in separate fields and retains the complete student signing name.');
    $graduateAccount = completionHttp($graduateClient['handle'],'/student/dashboard.php?period=' . $secondPeriod);
    completionCheck($graduateAccount['status'] === 200 && str_contains($graduateAccount['body'],'Synthetic Mary Middle Example Excel Student'), 'The student account still displays the full name after middle names are stored separately.');
    completionCheck($signedPdf['status'] === 200 && str_contains($signedPdf['body'], '(Dr. Synthetic Dean)') && str_contains($signedPdf['body'], '(College Dean)') && str_contains($signedPdf['body'], '(Cleared)') && !str_contains($signedPdf['body'], '(Pending clearance)'), 'A cleared office shows its signatory and cleared remark while pending office remarks stay blank.');
    $signedPreview = completionHttp($graduateClient['handle'],'/student/clearance_preview.php?period=' . $secondPeriod);
    completionCheck($signedPreview['status'] === 200 && str_contains($signedPreview['body'],'period=' . $secondPeriod), 'The preview opens for a partially signed clearance without requiring completion.');
    if (getenv('CCS_COMPLETION_PREVIEW') === '1') {
        completionSnapshot('clearance-preview',$signedPreview['body']);
        file_put_contents(__DIR__ . '/../.tmp/ccs-dean-check/clearance-preview.pdf',$signedPdf['body']);
    }
    if (getenv('CLEARANCE_PDF_PREVIEW') === '1') {
        $previewDirectory = __DIR__ . '/../.tmp/digital-clearance-preview';
        if (!is_dir($previewDirectory) && !mkdir($previewDirectory, 0700, true)) { throw new RuntimeException('Cannot create the PDF preview directory.'); }
        file_put_contents($previewDirectory . '/signed-clearance.pdf', $signedPdf['body']);
    }
    $snapshot = $database->prepare('SELECT printed_name,image_sha256 FROM clearance_station_signatures WHERE clearance_station_id=(SELECT id FROM clearance_stations WHERE clearance_id=? AND station_id=(SELECT id FROM stations WHERE code=\'DEAN\'))');
    $snapshot->execute([$graduate['clearance_id']]); $snapshot = $snapshot->fetch();
    completionCheck(($snapshot['printed_name'] ?? '') === 'Dr. Synthetic Dean' && ($snapshot['image_sha256'] ?? '') === hash('sha256',$signature), 'The signed office stores an immutable signature snapshot for the clearance record.');
    $replacementPost = $signaturePost;
    $replacementPost['printed_name'] = 'Dr. Updated Dean';
    $replacementPost['signature_drawing'] = json_encode([[[0.2,0.2],[0.3,0.7],[0.5,0.4]]]);
    completionHttp($deanClient['handle'], '/auth/signature_action.php', $replacementPost);
    $choices = staffSavedSignatures($database,$deanId);
    $secondSignatureId = (int)$choices[0]['id'];
    completionCheck(count($choices) === 2 && staffSignature($database,$deanId)['printed_name'] === 'Dr. Synthetic Dean', 'Saving another named signature preserves both choices and keeps the existing selection.');
    foreach (['printed_name','position_title'] as $field) {
        $invalidChoice = $replacementPost; $invalidChoice[$field] = '';
        completionHttp($deanClient['handle'], '/auth/signature_action.php', $invalidChoice);
        completionCheck(count(staffSavedSignatures($database,$deanId)) === 2, 'A saved signature requires ' . $field . '.');
    }
    $libraryProfile = completionHttp($deanClient['handle'], '/auth/profile.php');
    completionCheck(substr_count($libraryProfile['body'],'data-saved-signature=') === 2 && str_contains($libraryProfile['body'],'Use this signature') && str_contains($libraryProfile['body'],'Dr. Updated Dean'), 'The profile displays both signatures with their name, position and selection control.');
    completionSnapshot('profile-signatures',$libraryProfile['body']);
    if (getenv('CCS_COMPLETION_PREVIEW') === '1') {
        foreach ($choices as $choice) {
            file_put_contents(__DIR__ . '/../.tmp/ccs-dean-check/signature-' . $choice['id'] . '.png',completionHttp($deanClient['handle'],'/auth/signature_image.php?id=' . $choice['id'])['body']);
        }
    }
    $selection = ['csrf_token'=>$deanClient['csrf'],'action'=>'select','signature_id'=>$secondSignatureId];
    completionHttp($deanClient['handle'],'/auth/signature_action.php',$selection);
    $preservedPdf = completionHttp($graduateClient['handle'], '/student/clearance_pdf.php?period=' . $secondPeriod);
    completionCheck(staffSignature($database,$deanId)['printed_name'] === 'Dr. Updated Dean' && str_contains($preservedPdf['body'],'(Dr. Synthetic Dean)') && !str_contains($preservedPdf['body'],'(Dr. Updated Dean)'), 'Selecting a different saved signature preserves the identity and signature on earlier signed clearances.');
    completionCheck(completionHttp($wrongClient['handle'],'/auth/signature_image.php?id=' . $secondSignatureId)['status'] === 404, 'Another college staff account cannot view a saved signature by ID.');
    $foreignSelection = $selection; $foreignSelection['csrf_token'] = $wrongClient['csrf']; $foreignSelection['user_id'] = $deanId;
    completionHttp($wrongClient['handle'],'/auth/signature_action.php',$foreignSelection);
    completionCheck(staffSignature($database,$wrongDean) === false && (int)staffSignature($database,$deanId)['saved_signature_id'] === $secondSignatureId, 'An account cannot select another account’s signature or change its selection.');
    $badSelection = $selection; $badSelection['csrf_token'] = 'invalid'; $badSelection['signature_id'] = $firstSignatureId;
    completionHttp($deanClient['handle'],'/auth/signature_action.php',$badSelection);
    completionCheck((int)staffSignature($database,$deanId)['saved_signature_id'] === $secondSignatureId, 'Selecting a signature requires a valid CSRF token.');
    $badSelection = $selection; unset($badSelection['signature_id']); $badSelection['signature_id[]'] = (string)$firstSignatureId;
    completionHttp($deanClient['handle'],'/auth/signature_action.php',$badSelection);
    completionCheck((int)staffSignature($database,$deanId)['saved_signature_id'] === $secondSignatureId, 'Malformed signature selection input is rejected safely.');
    $manualGraduate = completionStudent($database,(int)$organizations['CCS'],$secondPeriod,4,'EXCEL-MANUAL-GRADUATE');
    $recordId = completionRequirement($database, $manualGraduate['clearance_id'], 'CCS_GRADUATION_APPLICATION')['id'];
    completionHttp($deanClient['handle'],'/dean/action.php',['csrf_token'=>$deanClient['csrf'],'clearance_id'=>$manualGraduate['clearance_id'],'requirement_record_id'=>$recordId,'action'=>'verify_requirement']);
    completionCheck(officeClearanceContext($database, 'DEAN', $manualGraduate['clearance_id'])['station_status'] === 'PENDING', 'Existing individual graduation verification also leaves clearance pending.');
    $response = completionHttp($deanClient['handle'], '/dean/action.php', ['csrf_token'=>$deanClient['csrf'],'clearance_id'=>$manualGraduate['clearance_id'],'action'=>'approve']);
    completionCheck($response['status'] === 303 && officeClearanceContext($database, 'DEAN', $manualGraduate['clearance_id'])['station_status'] === 'APPROVED', 'Existing manual graduation verification and individual Clear remain available.');
    $newSignature = $database->query('SELECT signature.printed_name FROM clearance_station_signatures signature JOIN clearance_stations record ON record.id=signature.clearance_station_id WHERE record.clearance_id=' . $manualGraduate['clearance_id'])->fetchColumn();
    completionCheck($newSignature === 'Dr. Updated Dean', 'Individual approval captures the newly selected signature.');
    $selection['signature_id'] = $firstSignatureId;
    completionHttp($deanClient['handle'],'/auth/signature_action.php',$selection);
    completionCheck((int)staffSignature($database,$deanId)['saved_signature_id'] === $firstSignatureId && count(staffSavedSignatures($database,$deanId)) === 2, 'The original saved signature can be selected again without redrawing.');
    $undownloadedSignature = $database->query('SELECT signature.printed_name FROM clearance_station_signatures signature JOIN clearance_stations record ON record.id=signature.clearance_station_id WHERE record.clearance_id=' . $manualGraduate['clearance_id'])->fetchColumn();
    completionCheck($undownloadedSignature === 'Dr. Updated Dean', 'Switching again preserves the prior approval even before its first PDF download.');
    $registrarClient = completionLogin('registrar-excel-test@thelewiscollege.edu.ph');
    $createName = ['csrf_token'=>$registrarClient['csrf'],'action'=>'create','student_number'=>'MIDDLE-NAME-TEST','first_name'=>'Mary Jane','middle_name'=>'De la Rosa','last_name'=>'Cruz','email'=>'middle-name-test@thelewiscollege.edu.ph','program'=>'BSCS','year_level'=>1,'organization_id'=>$organizations['CCS']];
    $createdName = completionHttp($registrarClient['handle'],'/registrar/student_action.php',$createName);
    $nameRecord = $database->query("SELECT id,first_name,middle_name,last_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetch();
    completionCheck($createdName['status'] === 303 && $nameRecord !== false && $nameRecord['first_name'] === 'Mary Jane' && $nameRecord['middle_name'] === 'De la Rosa' && $nameRecord['last_name'] === 'Cruz', 'Registrar account creation persists the three name fields without splitting compound names.');
    $nameDirectory = completionHttp($registrarClient['handle'],'/registrar/students.php?enrollment=ALL&search=De%20la%20Rosa');
    completionCheck($nameDirectory['status'] === 200 && str_contains($nameDirectory['body'],'MIDDLE-NAME-TEST') && str_contains($nameDirectory['body'],'name="middle_name"') && str_contains($nameDirectory['body'],'Cruz, Mary Jane De la Rosa'), 'Registrar search and edit fields include separately stored middle names.');
    completionSnapshot('registrar-middle-names',$nameDirectory['body']);
    $editName = $createName; $editName['action']='update'; $editName['student_id']=$nameRecord['id']; $editName['middle_name']='Santos';
    completionHttp($registrarClient['handle'],'/registrar/student_action.php',$editName);
    completionCheck($database->query("SELECT middle_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetchColumn() === 'Santos', 'The Registrar can correct an existing student middle name.');
    $invalidName = $editName; unset($invalidName['middle_name']); $invalidName['middle_name[]']='invalid';
    $invalidNameResponse = completionHttp($registrarClient['handle'],'/registrar/student_action.php',$invalidName);
    completionCheck($invalidNameResponse['status'] === 303 && $database->query("SELECT middle_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetchColumn() === 'Santos', 'Malformed middle-name input is rejected without changing the saved record or displaying a PHP error.');
    $invalidName = $editName; $invalidName['csrf_token']='invalid'; $invalidName['middle_name']='Unauthorized';
    completionHttp($registrarClient['handle'],'/registrar/student_action.php',$invalidName);
    completionCheck($database->query("SELECT middle_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetchColumn() === 'Santos', 'Invalid CSRF cannot alter a middle name.');
    $invalidName['csrf_token']=$graduateClient['csrf'];
    completionCheck(completionHttp($graduateClient['handle'],'/registrar/student_action.php',$invalidName)['status'] === 403 && $database->query("SELECT middle_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetchColumn() === 'Santos', 'Students cannot alter Registrar name records.');
    $editName['middle_name']='';
    completionHttp($registrarClient['handle'],'/registrar/student_action.php',$editName);
    completionCheck($database->query("SELECT middle_name FROM students WHERE student_number='MIDDLE-NAME-TEST'")->fetchColumn() === '', 'Students with no middle name are supported when updating an account.');
    $auditMarker = 'Synthetic pagination & filter checks';
    $auditDescriptions = [];
    $auditInsert = $database->prepare("INSERT INTO audit_logs(user_id,action,description,created_at) VALUES (?,'QA_AUDIT_PAGE',?,'2099-01-01 00:00:00')");
    $database->beginTransaction();
    for ($i=1; $i<=1005; $i++) {
        $description = $auditMarker . ' ' . str_pad((string)$i,4,'0',STR_PAD_LEFT);
        $auditInsert->execute([$staff['REGISTRAR'],$description]);
        $auditDescriptions[] = $description;
    }
    $database->prepare("INSERT INTO audit_logs(action,description) VALUES ('QA_AUDIT_OTHER',?),('QA_AUDIT_PAGE','Excluded from the pagination search')")->execute([$auditMarker]);
    $database->commit();
    $auditDescriptions = array_reverse($auditDescriptions);
    $auditRows = static function(array $response): array {
        completionCheck($response['status'] === 200,'The paginated audit request returns a normal page.');
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>' . $response['body']);
        return array_map(static fn(DOMNode $node): string => trim($node->textContent),iterator_to_array((new DOMXPath($document))->query('//table/tbody/tr/td[7]')));
    };
    $auditUrl = '/registrar/audit_logs.php?' . http_build_query(['search'=>$auditMarker,'action'=>'QA_AUDIT_PAGE']);
    $auditFirst = completionHttp($registrarClient['handle'],$auditUrl);
    completionCheck($auditRows($auditFirst) === array_slice($auditDescriptions,0,30) && str_contains($auditFirst['body'],'of 1,005 matching records. Page 1 of 34.'),'Audit pagination shows only the first 30 matching records in stable newest-first order.');
    completionSnapshot('audit-pagination',$auditFirst['body']);
    $auditDocument = new DOMDocument(); @$auditDocument->loadHTML('<?xml encoding="utf-8" ?>' . $auditFirst['body']);
    $auditXpath = new DOMXPath($auditDocument);
    $auditLinks = $auditXpath->query('//nav[@aria-label="Audit log pages"]/a');
    completionCheck($auditLinks->length > 0,'The audit table exposes labeled page navigation.');
    foreach ($auditLinks as $link) {
        parse_str((string)parse_url($link->getAttribute('href'),PHP_URL_QUERY),$linkQuery);
        completionCheck(($linkQuery['search']??'') === $auditMarker && ($linkQuery['action']??'') === 'QA_AUDIT_PAGE','Audit page links preserve the search text and action filter.');
    }
    $auditSecond = completionHttp($registrarClient['handle'],$auditUrl . '&page=2');
    completionCheck($auditRows($auditSecond) === array_slice($auditDescriptions,30,30),'Page two advances by 30 records without overlapping page one.');
    $auditLast = completionHttp($registrarClient['handle'],$auditUrl . '&page=34');
    completionCheck($auditRows($auditLast) === array_slice($auditDescriptions,990),'The last audit page includes older records beyond the previous 1,000-record cutoff.');
    $auditTooHigh = completionHttp($registrarClient['handle'],$auditUrl . '&page=999999');
    completionCheck($auditRows($auditTooHigh) === array_slice($auditDescriptions,990),'An out-of-range audit page safely displays the final matching page.');
    foreach (['0','invalid','1&page[]=1'] as $badPage) {
        completionCheck($auditRows(completionHttp($registrarClient['handle'],$auditUrl . '&page=' . $badPage)) === array_slice($auditDescriptions,0,30),'Invalid audit page values safely fall back to page one.');
    }
    $auditEmpty = completionHttp($registrarClient['handle'],'/registrar/audit_logs.php?action=QA_AUDIT_PAGE&search=NoMatchingAuditRecord&page=99');
    completionCheck($auditEmpty['status'] === 200 && str_contains($auditEmpty['body'],'No audit records match.') && !str_contains($auditEmpty['body'],'aria-label="Audit log pages"'),'Empty audit filters show a controlled empty state without unusable page links.');
    completionCheck(completionHttp($deanClient['handle'],$auditUrl)['status'] === 403,'Audit pagination remains restricted to the Registrar.');
    echo "All {$checks} CCS Excel completion and unrestricted clearing checks passed.\n";
} finally {
    if ($database->inTransaction()) { $database->rollBack(); }
    foreach ($clients as $client) { try { completionHttp($client['handle'],'/logout.php',['csrf_token'=>$client['csrf']]); } catch (Throwable $ignored) {} curl_close($client['handle']); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach (array_merge($files,[$log]) as $file) { if (is_string($file) && is_file($file)) { unlink($file); } }
    if (!preg_match('/\Acleargo_ccs_excel_test_[a-f0-9]{12}\z/', $temporary) || $temporary === $template) { throw new RuntimeException('Refusing unsafe test cleanup.'); }
    $database->exec('DROP DATABASE IF EXISTS `' . $temporary . '`');
}
