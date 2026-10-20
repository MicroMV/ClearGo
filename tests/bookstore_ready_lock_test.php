<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../bookstore/obligation_functions.php';
require_once __DIR__ . '/../bookstore/workbook_functions.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

// Copy table definitions and reference configuration, never school/student records.
$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_bookstore_ready_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $template) || $temporary === $template) {
    throw new RuntimeException('Unsafe test database configuration.');
}
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password === false ? '' : $password, $options);
$checks = 0;
$server = null;
$cookie = '';
$base = '';
$log = tempnam(sys_get_temp_dir(), 'bookstore-ready-');

function readyLockCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
    echo "PASS: {$message}\n";
}

function readyLockDenied(callable $action, string $message): void
{
    $denied = false;
    try { $action(); }
    catch (RuntimeException $exception) { $denied = true; }
    readyLockCheck($denied, $message);
}

function readyLockHttp(string $path, ?array $post = null): array
{
    global $base, $cookie;
    $headers = 'Cookie: ' . $cookie . "\r\n";
    if ($post !== null) { $headers .= "Content-Type: application/x-www-form-urlencoded\r\n"; }
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers,
        'content' => $post === null ? '' : http_build_query($post), 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 5]]);
    $body = file_get_contents($base . $path, false, $context);
    if ($body === false) { throw new RuntimeException('The isolated HTTP request failed.'); }
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: (clear_go_session=[^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    if (preg_match('/Fatal error|Uncaught |Warning:/', $body)) { throw new RuntimeException('The isolated HTTP response exposed a PHP error: ' . strip_tags($body)); }
    return ['status' => (int) $status[1], 'body' => $body];
}

function readyLockCsrf(string $body): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match)) { throw new RuntimeException('No CSRF token in the test response.'); }
    return html_entity_decode($match[1]);
}

try {
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`');
    $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Invalid template table.'); }
        $database->exec($database->query('SHOW CREATE TABLE `' . $template . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations', 'stations', 'requirements'] as $table) {
        $database->exec('INSERT INTO `' . $temporary . '`.`' . $table . '` SELECT * FROM `' . $template . '`.`' . $table . '`');
    }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $users = [];
    foreach (['BOOKSTORE_STAFF', 'REGISTRAR', 'STUDENT'] as $role) {
        $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,?,'ACTIVE')")
            ->execute([strtolower($role) . '-ready-test@thelewiscollege.edu.ph', password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT), $role]);
        $users[$role] = (int) $database->lastInsertId();
    }
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CCS'")->fetchColumn();
    $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,'READY-TEST-1','Synthetic','Ready Test','BSCS',2,'QA',?,'ACTIVE')")
        ->execute([$users['STUDENT'], $organizationId]);
    $studentId = (int) $database->lastInsertId();
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2098-2099','1ST','ACTIVE','PREPARING')");
    $periodId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$periodId, $studentId]);
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'NOT_READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
    $database->prepare("INSERT INTO clearances(student_id,academic_period_id,reference_number,status) VALUES (?,?,'READY-TEST-1','IN_PROGRESS')")->execute([$studentId, $periodId]);
    $clearanceId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO clearance_stations(clearance_id,station_id,status) SELECT ?,id,CASE WHEN code='BOOKSTORE' THEN 'PENDING' ELSE 'LOCKED' END FROM stations WHERE status='ACTIVE'")->execute([$clearanceId]);

    readyLockCheck(bookstoreObligationLockReason(bookstoreObligationPreparation($database, $periodId)) === null, 'Bookstore can edit while preparing and Not Ready.');
    readyLockCheck(createBookstoreEventFee($database, $periodId, 'Synthetic event', 100) === 1, 'Event creation assigns the fee before Ready.');
    $feeId = (int) $database->query('SELECT id FROM bookstore_fees')->fetchColumn();
    readyLockCheck(deactivateBookstoreEventFee($database, $periodId, $feeId), 'Event closing is available before Ready.');
    createBookstoreEventFee($database, $periodId, 'Receipt event', 50);
    $receiptFeeId = (int) $database->query("SELECT id FROM student_bookstore_fees WHERE fee_id=(SELECT id FROM bookstore_fees WHERE name='Receipt event')")->fetchColumn();
    $database->prepare("INSERT INTO bookstore_receipt_submissions(student_fee_id,status) VALUES (?,'LOST_REPORTED')")->execute([$receiptFeeId]);
    $submissionId = (int) $database->lastInsertId();
    addBookstoreObligation($database, $clearanceId, $users['BOOKSTORE_STAFF'], 'Synthetic balance', 100);
    addBookstoreObligation($database, $clearanceId, $users['BOOKSTORE_STAFF'], 'Second synthetic balance', 200);
    readyLockCheck((int) $database->query('SELECT COUNT(*) FROM bookstore_obligations')->fetchColumn() === 2, 'Individual obligations can be prepared before Ready.');

    $second = new PDO("mysql:host={$host};port={$port};dbname={$temporary};charset=utf8mb4", $username, $password === false ? '' : $password, $options);
    $second->exec('SET SESSION innodb_lock_wait_timeout=1');
    $database->beginTransaction();
    requireBookstoreObligationPreparation($database, $periodId);
    $timedOut = false;
    try { markOfficeReady($second, $users['BOOKSTORE_STAFF'], 'BOOKSTORE', ''); }
    catch (PDOException $exception) { $timedOut = (int) ($exception->errorInfo[1] ?? 0) === 1205; }
    $database->rollBack();
    readyLockCheck($timedOut, 'Mark Ready waits for an obligation preparation transaction.');
    markOfficeReady($database, $users['BOOKSTORE_STAFF'], 'BOOKSTORE', 'Synthetic test only');
    readyLockDenied(fn() => addBookstoreObligation($database, $clearanceId, $users['BOOKSTORE_STAFF'], 'Forbidden balance', 100), 'Ready blocks direct obligation creation.');
    readyLockDenied(fn() => createBookstoreEventFee($database, $periodId, 'Forbidden event', 100), 'Ready blocks direct event creation.');
    readyLockDenied(fn() => deactivateBookstoreEventFee($database, $periodId, $feeId), 'Ready blocks direct event changes.');

    // Test the actual authenticated routes against this disposable database.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText);
    if ($socket === false) { throw new RuntimeException($errorText); }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['DB_DATABASE'] = $temporary;
    unset($environment['APP_BASE_URL']);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__)], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start the isolated PHP server.'); }
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1);
        if ($probe !== false) { fclose($probe); break; }
        usleep(100000);
    }
    $login = readyLockHttp('/login.php');
    $csrf = readyLockCsrf($login['body']);
    $loginResult = readyLockHttp('/auth/login_process.php', ['csrf_token' => $csrf, 'email' => 'bookstore_staff-ready-test@thelewiscollege.edu.ph', 'password' => 'Synthetic-Only-2026!']);
    readyLockCheck($loginResult['status'] === 303, 'Synthetic Bookstore account signs in through the real handler.');
    $dashboard = readyLockHttp('/bookstore/dashboard.php?clearance=' . $clearanceId);
    readyLockCheck($dashboard['status'] === 200 && preg_match('/value="add_bookstore_obligation"\s+disabled/', $dashboard['body']) === 1, 'Ready dashboard disables adding obligations.');
    $csrf = readyLockCsrf($dashboard['body']);
    $response = readyLockHttp('/clearance/office_action.php', ['csrf_token' => $csrf, 'clearance_id' => $clearanceId, 'action' => 'add_bookstore_obligation', 'description' => 'Bypass attempt', 'amount' => 100]);
    readyLockCheck($response['status'] === 303 && (int) $database->query('SELECT COUNT(*) FROM bookstore_obligations')->fetchColumn() === 2, 'A direct POST cannot bypass the Ready obligation lock.');
    $fees = readyLockHttp('/bookstore/fees.php');
    readyLockCheck($fees['status'] === 200 && preg_match('/type="submit"\s+disabled>Add Event Fee/', $fees['body']) === 1 && preg_match('/value="deactivate"\s+disabled/', $fees['body']) === 1, 'Ready event-fee page disables creating and closing fees.');
    $response = readyLockHttp('/bookstore/fee_action.php', ['csrf_token' => $csrf, 'action' => 'create', 'name' => 'POST bypass', 'amount' => 100]);
    readyLockCheck($response['status'] === 303 && (int) $database->query('SELECT COUNT(*) FROM bookstore_fees')->fetchColumn() === 2, 'A direct POST cannot create an event after Ready.');
    $activeFeeId = (int) $database->query("SELECT id FROM bookstore_fees WHERE status='ACTIVE'")->fetchColumn();
    $response = readyLockHttp('/bookstore/fee_action.php', ['csrf_token' => $csrf, 'action' => 'deactivate', 'fee_id' => $activeFeeId]);
    readyLockCheck($response['status'] === 303 && $database->query('SELECT status FROM bookstore_fees WHERE id=' . $activeFeeId)->fetchColumn() === 'ACTIVE', 'A direct POST cannot close an event after Ready.');
    $response = readyLockHttp('/bookstore/submission_action.php', ['csrf_token' => $csrf, 'action' => 'verify', 'submission_id' => $submissionId]);
    readyLockCheck($response['status'] === 303 && $database->query('SELECT status FROM student_bookstore_fees WHERE id=' . $receiptFeeId)->fetchColumn() === 'PAID', 'Receipt confirmation remains available after Ready.');
    $obligationId = (int) $database->query('SELECT MIN(id) FROM bookstore_obligations')->fetchColumn();
    $response = readyLockHttp('/clearance/office_action.php', ['csrf_token' => $csrf, 'action' => 'clear_bookstore_obligation', 'clearance_id' => $clearanceId, 'obligation_id' => $obligationId, 'payment_reference' => 'SYNTHETIC-RECEIPT']);
    readyLockCheck($response['status'] === 303 && $database->query('SELECT status FROM bookstore_obligations WHERE id=' . $obligationId)->fetchColumn() === 'CLEARED', 'Individual payment recording remains available after Ready.');
    $result = importBookstoreWorkbook($database, $users['BOOKSTORE_STAFF'], [['student_number' => 'READY-TEST-1', 'status' => 'CLEARED', 'reference' => 'SYNTHETIC-WORKBOOK', 'remarks' => '']]);
    if ($result['errors'] !== []) { throw new RuntimeException('Workbook payment failure: ' . json_encode($result)); }
    readyLockCheck($result['cleared'] === 1 && $result['errors'] === [] && (int) $database->query("SELECT COUNT(*) FROM bookstore_obligations WHERE status='UNPAID'")->fetchColumn() === 0, 'Workbook payment settlement and student approval remain available after Ready.');

    markOfficeNotReady($database, $users['BOOKSTORE_STAFF'], 'BOOKSTORE');
    readyLockCheck(bookstoreObligationLockReason(bookstoreObligationPreparation($database, $periodId)) === null, 'Undo Ready restores preparation access before opening.');
    readyLockDenied(fn() => addBookstoreObligation($database, $clearanceId, $users['BOOKSTORE_STAFF'], 'Late balance', 100), 'An already cleared student cannot receive a new obligation even before Ready.');
    readyLockDenied(fn() => createBookstoreEventFee($database, $periodId, 'Late event', 100), 'New semester fees cannot invalidate existing Bookstore signatures.');
    readyLockDenied(fn() => unlockAfterOfficeApproval($database, 'REGISTRAR', $clearanceId), 'Completion cannot be recorded while other offices remain unresolved.');
    $database->prepare("UPDATE clearance_stations SET status='APPROVED' WHERE clearance_id=?")->execute([$clearanceId]);
    unlockAfterOfficeApproval($database, 'REGISTRAR', $clearanceId);
    readyLockCheck($database->query('SELECT status FROM clearances WHERE id=' . $clearanceId)->fetchColumn() === 'COMPLETED', 'A fully signed clearance can still complete.');
    readyLockDenied(fn() => addBookstoreObligation($database, $clearanceId, $users['BOOKSTORE_STAFF'], 'Completed student balance', 100), 'The original completed-clearance bug is rejected without changing approvals.');
    readyLockCheck($database->query('SELECT status FROM clearances WHERE id=' . $clearanceId)->fetchColumn() === 'COMPLETED' && officeClearanceContext($database, 'BOOKSTORE', $clearanceId)['station_status'] === 'APPROVED', 'Rejected additions preserve a completed clearance and its Bookstore approval.');

    $database->exec("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=" . $periodId);
    readyLockDenied(fn() => createBookstoreEventFee($database, $periodId, 'Open event', 100), 'Opening freezes fees even if readiness data is Not Ready.');
    readyLockDenied(fn() => markOfficeNotReady($database, $users['BOOKSTORE_STAFF'], 'BOOKSTORE'), 'Undo Ready cannot reopen preparation after clearance opens.');
    $database->exec("UPDATE academic_periods SET clearance_status='PREPARING' WHERE id=" . $periodId);
    $database->exec("DELETE readiness FROM office_period_readiness readiness JOIN stations station ON station.id=readiness.station_id WHERE station.code='BOOKSTORE'");
    readyLockDenied(fn() => createBookstoreEventFee($database, $periodId, 'Missing readiness', 100), 'Missing readiness configuration fails safely without adding fees.');

    foreach (['bookstore_workflow_test.php', 'bookstore_workbook_test.php', 'bookstore_event_workbook_test.php'] as $test) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/' . $test], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $testPipes, dirname(__DIR__), $environment);
        if (!is_resource($process)) { throw new RuntimeException('Cannot run existing regression.'); }
        fclose($testPipes[0]);
        $output = stream_get_contents($testPipes[1]);
        $error = stream_get_contents($testPipes[2]);
        fclose($testPipes[1]); fclose($testPipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) { throw new RuntimeException($test . ': ' . $output . $error); }
        readyLockCheck(true, 'Existing ' . $test . ' passes in the disposable database.');
    }
    echo "All {$checks} Bookstore readiness, payment and completion checks passed.\n";
} finally {
    if ($database->inTransaction()) { $database->rollBack(); }
    if (is_resource($server)) {
        if ($cookie !== '') { try { readyLockHttp('/logout.php', ['csrf_token' => $csrf ?? '']); } catch (Throwable $ignored) {} }
        proc_terminate($server); proc_close($server);
    }
    if (is_string($log) && is_file($log)) { unlink($log); }
    if (!preg_match('/\Acleargo_bookstore_ready_test_[a-f0-9]{12}\z/', $temporary) || $temporary === $template) {
        throw new RuntimeException('Refusing unsafe test cleanup.');
    }
    $database->exec('DROP DATABASE IF EXISTS `' . $temporary . '`');
}
