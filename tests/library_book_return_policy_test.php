<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';
require_once __DIR__ . '/../library/workbook_functions.php';

$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_library_books_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $template) || $temporary === $template) { throw new RuntimeException('Unsafe test database.'); }
$host = getenv('DB_HOST') ?: 'localhost'; $port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'root'; $password = getenv('DB_PASSWORD');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password === false ? '' : $password, $options);
$server = null; $base = ''; $clients = []; $checks = 0;
$log = tempnam(sys_get_temp_dir(), 'library-book-test-');

function libraryBookCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++; echo "PASS: {$message}\n";
}

function libraryBookBlocked(callable $action, string $message): void
{
    $blocked = false;
    try { $action(); }
    catch (RuntimeException $exception) { $blocked = str_contains($exception->getMessage(), 'Return all borrowed books'); }
    libraryBookCheck($blocked, $message);
}

function libraryBookStudent(PDO $database, int $organizationId, int $periodId): array
{
    $token = bin2hex(random_bytes(5)); $email = 'books-' . $token . '@thelewiscollege.edu.ph';
    $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,'STUDENT','ACTIVE')")->execute([$email, password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT)]);
    $userId = (int) $database->lastInsertId();
    $number = 'BOOKS-' . $token;
    $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES (?,?,'Synthetic','Library Test','Test Program',2,?,'ACTIVE')")->execute([$userId, $number, $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$periodId, $studentId]);
    $started = startStudentClearance($database, $userId); $clearanceId = (int) $started['clearance']['id'];
    $database->prepare("UPDATE clearance_stations record JOIN stations station ON station.id=record.station_id SET record.status=CASE WHEN station.code='LIBRARY' THEN 'PENDING' WHEN station.code IN ('DEAN','ORGANIZATION','STUDENT_COUNCIL','BOOKSTORE','CLINIC') THEN 'APPROVED' ELSE 'LOCKED' END WHERE record.clearance_id=?")->execute([$clearanceId]);
    return ['user_id' => $userId, 'student_id' => $studentId, 'clearance_id' => $clearanceId, 'email' => $email, 'number' => $number];
}

function libraryBookHttp(CurlHandle $client, string $path, ?array $post = null): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($post === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($client);
    if ($body === false) { throw new RuntimeException(curl_error($client)); }
    if (preg_match('/Fatal error|Uncaught |Warning:/', $body)) { throw new RuntimeException('HTTP PHP error: ' . strip_tags($body)); }
    return ['status' => (int) curl_getinfo($client, CURLINFO_HTTP_CODE), 'body' => $body];
}

function libraryBookLogin(string $email): array
{
    global $clients, $base;
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    $page = libraryBookHttp($handle, '/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $match)) { throw new RuntimeException('Missing CSRF token.'); }
    $client = ['handle' => $handle, 'csrf' => html_entity_decode($match[1])]; $clients[] = $client;
    $response = libraryBookHttp($handle, '/auth/login_process.php', ['csrf_token' => $client['csrf'], 'email' => $email, 'password' => 'Synthetic-Only-2026!']);
    if ($response['status'] !== 303) { throw new RuntimeException('Synthetic login failed.'); }
    require_once __DIR__ . '/support/http_fixture.php';
    $client['csrf'] = signedInFixtureCsrf($handle, $base); $clients[array_key_last($clients)] = $client;
    return $client;
}

try {
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`'); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Unsafe template table.'); }
        $database->exec($database->query('SHOW CREATE TABLE `' . $template . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations', 'stations', 'requirements'] as $table) { $database->exec('INSERT INTO `' . $temporary . '`.`' . $table . '` SELECT * FROM `' . $template . '`.`' . $table . '`'); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES ('library-books-test@thelewiscollege.edu.ph',?,'LIBRARY_STAFF','ACTIVE')")->execute([password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT)]);
    $staffId = (int) $database->lastInsertId();
    require_once __DIR__ . '/../auth/signature_functions.php';
    saveStaffSignature($database, ['id' => $staffId, 'role' => 'LIBRARY_STAFF'], 'Synthetic Library', 'Office signatory', '[[[0.1,0.4],[0.5,0.7]]]');
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CCS'")->fetchColumn();
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2098-2099','1ST','ACTIVE','OPEN')");
    $periodId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
    $student = libraryBookStudent($database, $organizationId, $periodId);
    $ready = libraryBookStudent($database, $organizationId, $periodId);
    $physical = libraryBookStudent($database, $organizationId, $periodId);
    $reviewReady = libraryBookStudent($database, $organizationId, $periodId);
    foreach ([$student, $ready] as $candidate) { $database->prepare("UPDATE library_cards SET status='VERIFIED' WHERE student_id=?")->execute([$candidate['student_id']]); }
    $bookInsert = $database->prepare("INSERT INTO borrowed_books(student_id,book_title,borrowed_at,due_at,status,recorded_by) VALUES (?,?,'2000-01-01',NULL,'BORROWED',?)");
    $bookInsert->execute([$student['student_id'], 'Synthetic Library <Book> & QA', $staffId]); $firstBook = (int) $database->lastInsertId();
    $bookInsert->execute([$student['student_id'], 'Second synthetic book', $staffId]); $secondBook = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO borrowed_books(student_id,book_title,borrowed_at,returned_at,status,recorded_by) VALUES (?,'Already returned','2000-01-01','2000-01-02','RETURNED',?)")->execute([$ready['student_id'], $staffId]);
    $context = officeClearanceContext($database, 'LIBRARY', $student['clearance_id']);
    $eligibility = officeEligibility($database, 'LIBRARY', $context);
    libraryBookCheck(!$eligibility['eligible'] && count($eligibility['outstanding_books']) === 2, 'A verified card cannot clear two outstanding books from an earlier date.');
    libraryBookCheck(str_contains(implode(' ', $eligibility['details']), 'Synthetic Library <Book> & QA'), 'Eligibility explains which books remain outstanding.');
    libraryBookCheck(officeEligibility($database, 'LIBRARY', officeClearanceContext($database, 'LIBRARY', $ready['clearance_id']))['eligible'], 'Returned books and another student borrowing do not block eligibility.');
    libraryBookBlocked(fn() => approveOfficeClearance($database, 'LIBRARY', $student['clearance_id'], $staffId), 'Generic individual approval rejects outstanding books.');
    libraryBookBlocked(fn() => approveOfficeClearance($database, 'LIBRARY', $student['clearance_id'], $staffId, null, true), 'Ready-only approval rechecks book returns.');
    libraryBookBlocked(fn() => clearLibraryClearance($database, $student['clearance_id'], $staffId), 'Physical-card direct clearance cannot bypass outstanding books.');
    $result = importLibraryWorkbook($database, $staffId, [['student_number' => $student['number'], 'status' => 'CLEARED', 'remarks' => '']]);
    libraryBookCheck($result['cleared'] === 0 && count($result['errors']) === 1, 'Workbook clearance rejects unreturned books.');
    libraryBookCheck(bulkReadyClearanceIds($database, 'LIBRARY') === [$ready['clearance_id']], 'Bulk readiness excludes both outstanding books and missing cards.');
    libraryBookCheck(officeClearanceContext($database, 'LIBRARY', $student['clearance_id'])['station_status'] === 'PENDING' && officeClearanceContext($database, 'SASO', $student['clearance_id'])['station_status'] === 'LOCKED', 'Blocked attempts preserve Library status and do not unlock SASO.');
    libraryBookCheck(clearLibraryClearance($database, $physical['clearance_id'], $staffId), 'Manual physical-card verification still clears a student with no books.');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText);
    if ($socket === false) { throw new RuntimeException($errorText); }
    $address = stream_socket_get_name($socket, false); fclose($socket); $base = 'http://' . $address;
    $environment = getenv(); $environment['DB_DATABASE'] = $temporary; unset($environment['APP_BASE_URL']);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__)], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start the isolated server.'); }
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) { $probe = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1); if ($probe !== false) { fclose($probe); break; } usleep(100000); }
    $staff = libraryBookLogin('library-books-test@thelewiscollege.edu.ph'); $studentClient = libraryBookLogin($student['email']);
    $dashboard = libraryBookHttp($staff['handle'], '/library/dashboard.php?clearance=' . $student['clearance_id']);
    libraryBookCheck($dashboard['status'] === 200 && str_contains($dashboard['body'], 'Unreturned books: 2') && str_contains($dashboard['body'], 'Synthetic Library &lt;Book&gt; &amp; QA') && str_contains($dashboard['body'], 'Review books'), 'Library dashboard identifies unreturned books and escapes titles.');
    $document = new DOMDocument(); $previous = libxml_use_internal_errors(true); $document->loadHTML($dashboard['body']); libxml_clear_errors(); libxml_use_internal_errors($previous); $xpath = new DOMXPath($document);
    libraryBookCheck($xpath->query('//form[input[@name="clearance_id" and @value="' . $student['clearance_id'] . '"]]//button[@value="library_clear" and @disabled]')->length >= 1, 'Library dashboard disables clearance for the affected student.');
    libraryBookCheck($xpath->query('//form//button[@value="return_library_book"]')->length === 2, 'Staff can mark each outstanding book returned from the review panel.');
    $portal = libraryBookHttp($studentClient['handle'], '/student/dashboard.php?station=LIBRARY');
    libraryBookCheck($portal['status'] === 200 && str_contains($portal['body'], 'Return these books before Library clearance:') && str_contains($portal['body'], 'Synthetic Library &lt;Book&gt; &amp; QA'), 'Student dashboard shows the book-return blocker with escaped titles.');
    foreach (['library_clear', 'approve'] as $action) {
        $response = libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $student['clearance_id'], 'action' => $action]);
        libraryBookCheck($response['status'] === 303 && officeClearanceContext($database, 'LIBRARY', $student['clearance_id'])['station_status'] === 'PENDING', 'Direct HTTP ' . $action . ' cannot bypass book returns.');
    }
    $response = libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'action' => 'approve_all_ready']);
    libraryBookCheck($response['status'] === 303 && officeClearanceContext($database, 'LIBRARY', $ready['clearance_id'])['station_status'] === 'APPROVED' && officeClearanceContext($database, 'LIBRARY', $student['clearance_id'])['station_status'] === 'PENDING', 'HTTP bulk approval clears only students with no unreturned books.');
    $database->prepare("UPDATE library_cards SET status='PENDING' WHERE student_id=?")->execute([$student['student_id']]);
    foreach ([$student, $reviewReady] as $candidate) {
        $cardId = (int) $database->query('SELECT id FROM library_cards WHERE student_id=' . $candidate['student_id'])->fetchColumn();
        $database->prepare("INSERT INTO library_card_submissions(library_card_id,status,stored_name,mime_type) VALUES (?,'SUBMITTED','synthetic-test-reference.png','image/png')")->execute([$cardId]);
        $submissionId = (int) $database->lastInsertId();
        $response = libraryBookHttp($staff['handle'], '/library/submission_action.php', ['csrf_token' => $staff['csrf'], 'submission_id' => $submissionId, 'action' => 'verify']);
        if ($candidate['student_id'] === $student['student_id']) {
            libraryBookCheck($response['status'] === 303 && $database->query('SELECT status FROM library_cards WHERE id=' . $cardId)->fetchColumn() === 'VERIFIED' && officeClearanceContext($database, 'LIBRARY', $student['clearance_id'])['station_status'] === 'PENDING', 'Card review confirms the card but leaves clearance blocked by books.');
        } else {
            libraryBookCheck($response['status'] === 303 && officeClearanceContext($database, 'LIBRARY', $candidate['clearance_id'])['station_status'] === 'APPROVED', 'Card review still clears a student whose books are returned.');
        }
    }
    libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $ready['clearance_id'], 'book_id' => $firstBook, 'action' => 'return_library_book']);
    libraryBookCheck(count(libraryOutstandingBooks($database, $student['student_id'])) === 2, 'A return request cannot change another student book.');
    libraryBookHttp($studentClient['handle'], '/clearance/office_action.php', ['csrf_token' => $studentClient['csrf'], 'clearance_id' => $student['clearance_id'], 'book_id' => $firstBook, 'action' => 'return_library_book']);
    libraryBookCheck(count(libraryOutstandingBooks($database, $student['student_id'])) === 2, 'Students cannot mark their own books returned.');
    $response = libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $student['clearance_id'], 'book_id' => $firstBook, 'action' => 'return_library_book']);
    $returned = $database->query('SELECT status,returned_at,returned_verified_by FROM borrowed_books WHERE id=' . $firstBook)->fetch();
    libraryBookCheck($response['status'] === 303 && $returned['status'] === 'RETURNED' && $returned['returned_at'] !== null && (int) $returned['returned_verified_by'] === $staffId, 'Mark Returned records the return date and Library staff verifier.');
    libraryBookBlocked(fn() => clearLibraryClearance($database, $student['clearance_id'], $staffId), 'Returning only one of two books does not permit clearance.');
    libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $student['clearance_id'], 'book_id' => $secondBook, 'action' => 'return_library_book']);
    libraryBookCheck(officeEligibility($database, 'LIBRARY', $context)['eligible'] && bulkReadyClearanceIds($database, 'LIBRARY') === [$student['clearance_id']], 'Returning every book restores individual and bulk eligibility.');
    $response = libraryBookHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $student['clearance_id'], 'action' => 'library_clear']);
    libraryBookCheck($response['status'] === 303 && officeClearanceContext($database, 'LIBRARY', $student['clearance_id'])['station_status'] === 'APPROVED', 'Individual HTTP clearance succeeds after all books return.');
    libraryBookCheck(officeClearanceContext($database, 'SASO', $student['clearance_id'])['station_status'] === 'PENDING', 'SASO unlocks only after the Library signature is saved.');
    libraryBookCheck(!clearLibraryClearance($database, $student['clearance_id'], $staffId), 'Repeated Library approval remains idempotent.');
    $result = importLibraryWorkbook($database, $staffId, [['student_number' => $student['number'], 'status' => 'CLEARED', 'remarks' => '']]);
    libraryBookCheck($result['already_cleared'] === 1 && $result['errors'] === [], 'Workbook import accepts the returned and already cleared student.');
    $portal = libraryBookHttp($studentClient['handle'], '/student/dashboard.php?station=LIBRARY');
    libraryBookCheck(!str_contains($portal['body'], 'Return these books before Library clearance:'), 'Student book warning disappears after all returns.');

    foreach (['library_workbook_test.php', 'dashboard_summary_test.php'] as $test) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/' . $test], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $testPipes, dirname(__DIR__), $environment);
        if (!is_resource($process)) { throw new RuntimeException('Cannot run existing regression.'); }
        fclose($testPipes[0]); $output = stream_get_contents($testPipes[1]); $error = stream_get_contents($testPipes[2]); fclose($testPipes[1]); fclose($testPipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException($test . ': ' . $output . $error); }
        libraryBookCheck(true, 'Existing ' . $test . ' passes in the disposable database.');
    }
    echo "All {$checks} Library book-return checks passed.\n";
} finally {
    if ($database->inTransaction()) { $database->rollBack(); }
    foreach ($clients as $client) { try { libraryBookHttp($client['handle'], '/logout.php', ['csrf_token' => $client['csrf']]); } catch (Throwable $ignored) {} curl_close($client['handle']); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (is_string($log) && is_file($log)) { unlink($log); }
    if (!preg_match('/\Acleargo_library_books_test_[a-f0-9]{12}\z/', $temporary) || $temporary === $template) { throw new RuntimeException('Refusing unsafe test cleanup.'); }
    $database->exec('DROP DATABASE IF EXISTS `' . $temporary . '`');
}
