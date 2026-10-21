<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';
require_once __DIR__ . '/../clearance/office_workflow.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';
require_once __DIR__ . '/../auth/signature_functions.php';
require_once __DIR__ . '/support/http_fixture.php';

$database = getDatabaseConnection();
$source = (string) $database->query('SELECT DATABASE()')->fetchColumn();
$temporary = 'cleargo_accounting_final_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $source)) { throw new RuntimeException('Unsafe template database.'); }
$server = null; $clients = []; $base = ''; $checks = 0;
$log = tempnam(sys_get_temp_dir(), 'accounting-final-');
function finalCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++; echo "PASS: $message\n";
}
function finalHttp(CurlHandle $client, string $path, ?array $post = null): array
{
    global $base;
    $headers = '';
    curl_setopt_array($client, [CURLOPT_URL => $base . $path, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    curl_setopt($client, CURLOPT_HEADERFUNCTION, static function ($handle, string $line) use (&$headers): int { $headers .= $line; return strlen($line); });
    if ($post === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($client);
    if (!is_string($body)) { throw new RuntimeException(curl_error($client)); }
    if (preg_match('/Fatal error|Uncaught |Warning:/', $body)) { throw new RuntimeException('Unexpected PHP error in response.'); }
    return ['status' => (int) curl_getinfo($client, CURLINFO_HTTP_CODE), 'body' => $body, 'headers' => $headers];
}
function finalLogin(string $email): array
{
    global $clients, $base;
    $client = curl_init(); curl_setopt($client, CURLOPT_COOKIEFILE, '');
    $page = finalHttp($client, '/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $match)) { throw new RuntimeException('Missing login CSRF.'); }
    $login = finalHttp($client, '/auth/login_process.php', ['csrf_token' => html_entity_decode($match[1]), 'email' => $email, 'password' => 'Synthetic-Only-2026!']);
    if ($login['status'] !== 303) { throw new RuntimeException('Synthetic login failed.'); }
    $result = ['handle' => $client, 'csrf' => signedInFixtureCsrf($client, $base)];
    $clients[] = $result; return $result;
}
function finalRow(string $html, string $number): DOMElement
{
    $document = new DOMDocument(); libxml_use_internal_errors(true); $document->loadHTML($html); libxml_clear_errors();
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//table[@id="clearance-records"]/tbody/tr') as $row) {
        if (str_contains($row->textContent, $number)) { return $row; }
    }
    throw new RuntimeException('Synthetic student row missing.');
}
try {
    $database->exec("CREATE DATABASE `$temporary` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $database->exec("USE `$temporary`"); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Unsafe template table.'); }
        $database->exec($database->query("SHOW CREATE TABLE `$source`.`$table`")->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations','stations','requirements'] as $table) { $database->exec("INSERT INTO `$table` SELECT * FROM `$source`.`$table`"); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $account = static function (string $role, string $email) use ($database): int {
        $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,?,'ACTIVE')")->execute([$email, password_hash('Synthetic-Only-2026!', PASSWORD_DEFAULT), $role]);
        return (int) $database->lastInsertId();
    };
    $staffId = $account('ACCOUNTING_STAFF', 'final-accounting@thelewiscollege.edu.ph');
    $wrongId = $account('LIBRARY_STAFF', 'final-library@thelewiscollege.edu.ph');
    saveStaffSignature($database, ['id' => $staffId, 'role' => 'ACCOUNTING_STAFF'], 'Synthetic Accountant', 'Accounting', '[[[0.1,0.4],[0.4,0.7],[0.8,0.2]]]');
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2099-2100','2ND','ACTIVE','OPEN')");
    $periodId = (int) $database->lastInsertId();
    $database->prepare("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT ?,id,'READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CTE'")->fetchColumn();
    $students = [];
    foreach (['unpaid','paid','missing-proof','locked','bulk-ready'] as $index => $key) {
        $email = 'final-' . $key . '@thelewiscollege.edu.ph'; $userId = $account('STUDENT', $email);
        $number = 'FINAL-' . ($index + 1);
        $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES (?,?,'Synthetic','Accounting Final','BEED',1,?,'ACTIVE')")->execute([$userId, $number, $organizationId]);
        $studentId = (int) $database->lastInsertId();
        $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$periodId, $studentId]);
        $started = startStudentClearance($database, $userId); $clearanceId = (int) $started['clearance']['id'];
        $database->prepare("UPDATE clearance_stations record JOIN stations station ON station.id=record.station_id SET record.status=CASE WHEN station.code='ACCOUNTING' THEN ? WHEN station.code='REGISTRAR' THEN 'LOCKED' ELSE 'APPROVED' END WHERE record.clearance_id=?")->execute([$key === 'locked' ? 'LOCKED' : 'PENDING', $clearanceId]);
        $receiptId = null;
        if ($key !== 'missing-proof') {
            $database->prepare("INSERT INTO accounting_receipts(student_id,academic_period_id,receipt_code,status,issued_by,scanned_at) VALUES (?,?,?,'SCANNED',?,NOW())")->execute([$studentId, $periodId, 'PORTAL-FINAL-' . $index, $staffId]);
            $receiptId = (int) $database->lastInsertId();
        }
        $students[$key] = ['id' => $studentId, 'clearance' => $clearanceId, 'number' => $number, 'receipt' => $receiptId, 'email' => $email];
    }
    $unpaid = $students['unpaid'];
    $obligation = $database->prepare("INSERT INTO accounting_obligations(student_id,academic_period_id,description,amount,status) VALUES (?,?,'Synthetic balance',?,'UNPAID')");
    $debts = [];
    foreach (['100.00','200.00'] as $amount) { $obligation->execute([$unpaid['id'], $periodId, $amount]); $debts[] = (int) $database->lastInsertId(); }
    $context = officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance']);
    // Retained historical rows must have no effect even if an old approval remains.
    $hasLegacyTable = (int) $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clearance_exceptions'")->fetchColumn() === 1;
    if ($hasLegacyTable) {
        $database->prepare("INSERT INTO clearance_exceptions(clearance_station_id,reason,proof_reference,status,requested_by,decided_by) VALUES (?,'Legacy promissory note','SYNTHETIC','APPROVED',?,?)")->execute([$context['clearance_station_id'], $staffId, $staffId]);
    }
    finalCheck(!officeEligibility($database, 'ACCOUNTING', $context)['eligible'], 'An unpaid balance stays ineligible even with a legacy exception.');
    finalCheck(!in_array($unpaid['clearance'], bulkReadyClearanceIds($database, 'ACCOUNTING'), true), 'Bulk-ready lists exclude unpaid balances.');
    $blocked = false;
    try { approveOfficeClearance($database, 'ACCOUNTING', $unpaid['clearance'], $staffId); } catch (RuntimeException $error) { $blocked = str_contains($error->getMessage(), 'fully paid'); }
    finalCheck($blocked, 'Direct approval rejects unpaid obligations with a full-payment message.');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText); if ($socket === false) { throw new RuntimeException($errorText); }
    $address = stream_socket_get_name($socket, false); fclose($socket); $base = 'http://' . $address;
    $environment = getenv(); $environment['DB_DATABASE'] = $temporary; unset($environment['APP_BASE_URL']);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__)], [0 => ['pipe','r'], 1 => ['file',$log,'a'], 2 => ['file',$log,'a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start local test server.'); } fclose($pipes[0]);
    for ($attempt = 0; $attempt < 50; $attempt++) { $probe = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1); if ($probe !== false) { fclose($probe); break; } usleep(100000); }
    $staff = finalLogin('final-accounting@thelewiscollege.edu.ph');
    $studentClient = finalLogin($unpaid['email']); $wrong = finalLogin('final-library@thelewiscollege.edu.ph');
    $dashboard = finalHttp($staff['handle'], '/accounting/dashboard.php');
    finalCheck($dashboard['status'] === 200 && !str_contains($dashboard['body'], 'Manual Accounting clearance') && !str_contains($dashboard['body'], 'Temporary exception'), 'Accounting dashboard has no manual bypass or exception controls.');
    foreach (['paid' => false, 'unpaid' => true, 'missing-proof' => true] as $key => $disabled) {
        $row = finalRow($dashboard['body'], $students[$key]['number']);
        $xpath = new DOMXPath($row->ownerDocument);
        $buttons = $xpath->query('.//button[@name="action" and @value="approve"]', $row);
        finalCheck($buttons->length === 1 && trim($buttons->item(0)->textContent) === 'Clear' && $buttons->item(0)->hasAttribute('disabled') === $disabled && $xpath->query('.//a[contains(text(),"Review")]', $row)->length === 0, $key . ' Accounting row has only the correctly enabled Clear action.');
    }
    $lockedRow = finalRow($dashboard['body'], $students['locked']['number']);
    $unpaidRow = finalRow($dashboard['body'], $unpaid['number']);
    $unpaidXpath = new DOMXPath($unpaidRow->ownerDocument);
    $paymentLinks = $unpaidXpath->query('./td[7]//a[normalize-space()="Payment records"]', $unpaidRow);
    finalCheck($paymentLinks->length === 1 && str_contains($unpaidRow->textContent, 'Outstanding balance: PHP 300.00') && $unpaidXpath->query('./td[9]//a', $unpaidRow)->length === 0, 'Requirements show the unpaid balance and payment records while the Action column stays Clear only.');
    $paymentPage = finalHttp($staff['handle'], '/accounting/dashboard.php' . $paymentLinks->item(0)->getAttribute('href'));
    finalCheck($paymentPage['status'] === 200 && str_contains($paymentPage['body'], 'value="clear_obligation"'), 'Payment records remain reachable for recording actual settled payments.');
    finalCheck((new DOMXPath($lockedRow->ownerDocument))->query('.//button[@name="action"]', $lockedRow)->length === 0, 'Clear stays hidden until the student reaches Accounting.');
    $detail = finalHttp($staff['handle'], '/accounting/dashboard.php?clearance=' . $unpaid['clearance']);
    finalCheck(!str_contains($detail['body'], 'approve_exception') && !str_contains($detail['body'], 'Promissory note'), 'Old detail URLs no longer expose exception approvals.');
    $countLegacy = static fn(): int => $hasLegacyTable ? (int) $database->query('SELECT COUNT(*) FROM clearance_exceptions')->fetchColumn() : 0;
    $legacyBefore = $countLegacy();
    foreach (['approve_exception','manual_accounting_clear','approve'] as $action) {
        $response = finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => $action, 'reason' => 'Not allowed', 'proof_reference' => 'SYNTHETIC']);
        finalCheck($response['status'] === 303 && officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance'])['station_status'] === 'PENDING' && $countLegacy() === $legacyBefore, $action . ' cannot bypass payment through a forged request.');
    }
    finalHttp($staff['handle'], '/accounting/submission_action.php', ['csrf_token' => $staff['csrf'], 'submission_id' => $unpaid['receipt'], 'action' => 'approve']);
    finalCheck(officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance'])['station_status'] === 'PENDING' && $database->query('SELECT status FROM accounting_receipts WHERE id=' . $unpaid['receipt'])->fetchColumn() === 'SCANNED', 'Screenshot approval also rejects unpaid balances without consuming the submission.');
    finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => 'clear_obligation', 'obligation_id' => $debts[0], 'payment_reference' => '']);
    finalCheck((int) $database->query("SELECT COUNT(*) FROM accounting_obligations WHERE status='UNPAID'")->fetchColumn() === 2, 'Payment settlement requires a real reference to be entered.');
    foreach ($debts as $index => $debtId) {
        finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => 'clear_obligation', 'obligation_id' => $debtId, 'payment_reference' => 'SYNTHETIC-PAYMENT-' . $index]);
        finalCheck((int) $database->query("SELECT COUNT(*) FROM accounting_obligations WHERE status='UNPAID'")->fetchColumn() === 1 - $index, 'Recording payment settles only the selected obligation.');
        finalCheck(officeEligibility($database, 'ACCOUNTING', $context)['eligible'] === ($index === 1), 'Eligibility waits until every recorded balance is settled.');
    }
    foreach (['student' => $studentClient, 'other-office' => $wrong] as $role => $unauthorized) {
        $response = finalHttp($unauthorized['handle'], '/clearance/office_action.php', ['csrf_token' => $unauthorized['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => 'approve']);
        finalCheck($response['status'] === ($role === 'student' ? 403 : 303) && officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance'])['station_status'] === 'PENDING', $role . ' requests cannot approve Accounting.');
    }
    finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => 'invalid', 'clearance_id' => $unpaid['clearance'], 'action' => 'approve']);
    finalCheck(officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance'])['station_status'] === 'PENDING', 'Invalid CSRF cannot clear a paid student.');
    finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => 'approve']);
    finalCheck(officeClearanceContext($database, 'ACCOUNTING', $unpaid['clearance'])['station_status'] === 'APPROVED', 'Clear approves a fully settled student with a submitted portal assessment.');
    finalCheck((int) $database->query('SELECT COUNT(*) FROM clearance_station_signatures WHERE clearance_station_id=' . $context['clearance_station_id'])->fetchColumn() === 1 && $database->query('SELECT status FROM accounting_receipts WHERE id=' . $unpaid['receipt'])->fetchColumn() === 'APPROVED', 'Clear captures the signature and approves the assessment screenshot.');
    finalCheck(officeClearanceContext($database, 'REGISTRAR', $unpaid['clearance'])['station_status'] === 'PENDING', 'Valid Accounting clearance unlocks the Registrar.');
    $before = (int) $database->query('SELECT COUNT(*) FROM accounting_obligations')->fetchColumn();
    finalHttp($staff['handle'], '/clearance/office_action.php', ['csrf_token' => $staff['csrf'], 'clearance_id' => $unpaid['clearance'], 'action' => 'add_obligation', 'description' => 'Too late', 'amount' => '1.00']);
    finalCheck((int) $database->query('SELECT COUNT(*) FROM accounting_obligations')->fetchColumn() === $before, 'A stale request cannot add a new unpaid balance after approval.');
    $bulk = approveAllReadyClearances($database, ['id' => $staffId, 'role' => 'ACCOUNTING_STAFF']);
    finalCheck($bulk['approved'] === 2 && officeClearanceContext($database, 'ACCOUNTING', $students['missing-proof']['clearance'])['station_status'] === 'PENDING' && officeClearanceContext($database, 'ACCOUNTING', $students['locked']['clearance'])['station_status'] === 'LOCKED', 'Bulk clearance approves only fully paid eligible students with submitted assessments.');
    echo "All $checks final Accounting payment checks passed.\n";
} finally {
    if ($database->inTransaction()) { $database->rollBack(); }
    foreach ($clients as $client) { try { finalHttp($client['handle'], '/logout.php', ['csrf_token' => $client['csrf']]); } catch (Throwable $ignored) {} curl_close($client['handle']); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (is_string($log) && is_file($log)) { unlink($log); }
    if (!preg_match('/\Acleargo_accounting_final_test_[a-f0-9]{12}\z/', $temporary) || $temporary === $source) { throw new RuntimeException('Unsafe test cleanup.'); }
    $database->exec("DROP DATABASE IF EXISTS `$temporary`");
}
