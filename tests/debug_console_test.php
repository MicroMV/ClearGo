<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../debug/access.php';
require_once __DIR__ . '/../debug/functions.php';
require_once __DIR__ . '/../auth/signature_functions.php';

$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_debug_test_' . bin2hex(random_bytes(6));
$restored = $temporary . '_restore';
if (!preg_match('/\A[a-zA-Z0-9_]+\z/',$template)) { throw new RuntimeException('Unsafe template database.'); }
$host=getenv('DB_HOST') ?: 'localhost'; $port=getenv('DB_PORT') ?: '3306'; $password=getenv('DB_PASSWORD');
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4",getenv('DB_USERNAME') ?: 'root',$password === false ? '' : $password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$server=null; $disabledServer=null; $localClient=null; $base=''; $clients=[]; $backups=[]; $checks=0; $log=tempnam(sys_get_temp_dir(),'debug-console-test-');

function debugCheck(bool $passed,string $message): void {
    global $checks;
    if (!$passed) { throw new RuntimeException($message); }
    $checks++; echo 'PASS: ' . $message . PHP_EOL;
}
function debugHttp(CurlHandle $client,string $path,?array $post=null): array {
    global $base;
    curl_setopt($client,CURLOPT_URL,$base . $path);
    if ($post === null) { curl_setopt($client,CURLOPT_HTTPGET,true); }
    else { curl_setopt($client,CURLOPT_POST,true); curl_setopt($client,CURLOPT_POSTFIELDS,$post); }
    $body=curl_exec($client);
    if ($body === false) { throw new RuntimeException(curl_error($client)); }
    return ['status'=>(int)curl_getinfo($client,CURLINFO_HTTP_CODE),'body'=>$body];
}
function debugLogin(string $email): array {
    global $clients;
    $client=curl_init();
    curl_setopt_array($client,[CURLOPT_COOKIEFILE=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>40]);
    $page=debugHttp($client,'/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/',$page['body'],$match)) { throw new RuntimeException('Missing login token.'); }
    $csrf=html_entity_decode($match[1]);
    $clients[]=['handle'=>$client,'csrf'=>$csrf];
    if (debugHttp($client,'/auth/login_process.php',['email'=>$email,'password'=>'Synthetic-Only-2026!','csrf_token'=>$csrf])['status']!==303) { throw new RuntimeException('Fixture login failed.'); }
    return ['handle'=>$client,'csrf'=>$csrf];
}
function debugFingerprint(PDO $database,string $table): string {
    $rows=$database->query('SELECT * FROM ' . debugTableIdentifier($table))->fetchAll();
    $rows=array_map('serialize',$rows); sort($rows);
    return hash('sha256',serialize($rows));
}
function debugFixtureBackup(PDO $database): string {
    global $backups;
    $description=$database->query("SELECT description FROM audit_logs WHERE action IN ('DEBUG_BACKUP','DEBUG_SEMESTERS','DEBUG_ACTIVITY') ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!is_string($description)||!preg_match('/debug-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}\.sql\.gz/',$description,$match)) { throw new RuntimeException('No completed backup recorded.'); }
    $backups[]=$match[0];return $match[0];
}
try {
    foreach ([['127.0.0.1','localhost',true],['::1','[::1]:8080',true],['192.168.1.9','localhost',false],['127.0.0.1','attacker.example',false],['127.0.0.1','localhost.attacker.example',false]] as [$remote,$httpHost,$expected]) {
        debugCheck(debugRequestIsLocal(['REMOTE_ADDR'=>$remote,'HTTP_HOST'=>$httpHost])===$expected,'Local access boundary for ' . $remote . ' / ' . $httpHost);
    }
    debugCheck(!debugRequestIsLocal(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'localhost','HTTP_X_FORWARDED_FOR'=>'1.2.3.4']),'Forwarded requests cannot reach the debug console.');
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`'); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $identifier=debugTableIdentifier($table);
        $database->exec($database->query('SHOW CREATE TABLE `' . $template . '`.' . $identifier)->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations','stations','requirements'] as $table) { $database->exec('INSERT INTO `' . $temporary . '`.' . debugTableIdentifier($table) . ' SELECT * FROM `' . $template . '`.' . debugTableIdentifier($table)); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $users=[];
    foreach (['registrar'=>'REGISTRAR','library'=>'LIBRARY_STAFF','student'=>'STUDENT'] as $name=>$role) {
        $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,?,'ACTIVE')")->execute([$name . '-debug-test@thelewiscollege.edu.ph',password_hash('Synthetic-Only-2026!',PASSWORD_DEFAULT),$role]);
        $users[$name]=(int)$database->lastInsertId();
    }
    $organization=(int)$database->query("SELECT id FROM organizations WHERE code='CCS'")->fetchColumn();
    $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,'DEBUG-SYNTHETIC','Synthetic','Debug Student','Test Program',1,'TEST',?,'ACTIVE')")->execute([$users['student'],$organization]);
    $student=(int)$database->lastInsertId();
    $period=createRegistrarSemester($database,$users['registrar'],'2098-2099','1ST');
    $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES (?,?,'ENROLLED')")->execute([$period,$student]);
    prepareSemesterClearanceRecords($database,$period);
    $database->prepare("INSERT INTO borrowed_books(student_id,book_title,borrowed_at,recorded_by) VALUES (?,'Synthetic borrowed book',CURRENT_DATE,?)")->execute([$student,$users['library']]);
    $database->prepare("INSERT INTO library_cards(student_id,status) VALUES (?,'VERIFIED')")->execute([$student]);
    $station=(int)$database->query("SELECT id FROM stations WHERE code='COMLAB'")->fetchColumn();
    $database->prepare("INSERT INTO violations(student_id,station_id,description,recorded_by) VALUES (?,?,'Synthetic incident',?)")->execute([$student,$station,$users['library']]);
    $signature=renderStaffSignature('[[[0.1,0.5],[0.3,0.3],[0.6,0.5]]]');
    $database->prepare("INSERT INTO staff_saved_signatures(user_id,printed_name,position_title,image_data,mime_type,image_sha256) VALUES (?,'Synthetic Registrar','Registrar',?,'image/png',?)")->execute([$users['registrar'],$signature['data'],$signature['sha256']]);
    $signatureId=(int)$database->lastInsertId();
    selectStaffSignature($database,['id'=>$users['registrar']],$signatureId);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errorNumber,$errorText);
    $address=stream_socket_get_name($socket,false);fclose($socket);$base='http://' . $address;
    $environment=getenv();$environment['DB_DATABASE']=$temporary;unset($environment['APP_BASE_URL'],$environment['CLEAR_GO_DEBUG']);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start test server.'); } fclose($pipes[0]);
    for ($i=0;$i<50;$i++) { $probe=@stream_socket_client('tcp://' . $address,$errorNumber,$errorText,.1);if ($probe!==false) { fclose($probe);break; }usleep(100000); }
    $registrarApp=debugLogin('registrar-debug-test@thelewiscollege.edu.ph');$library=debugLogin('library-debug-test@thelewiscollege.edu.ph');$studentClient=debugLogin('student-debug-test@thelewiscollege.edu.ph');
    $localClient=curl_init();curl_setopt_array($localClient,[CURLOPT_COOKIEFILE=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>40]);
    $registrar=['handle'=>$localClient,'csrf'=>null];
    $protected=['users','students','organizations','stations','requirements','staff_assignments','staff_saved_signatures','staff_signatures'];
    $fingerprints=[];foreach($protected as $table){$fingerprints[$table]=debugFingerprint($database,$table);}
    $initialCounts=debugTableCounts($database);
    $page=debugHttp($registrar['handle'],'/debug/index.php');
    debugCheck($page['status']===200&&str_contains($page['body'],'Ready all offices')&&str_contains($page['body'],'No school account login required.'),'The standalone debug screen opens without any school login or redirect.');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/',$page['body'],$debugToken)) { throw new RuntimeException('Missing independent debug token.'); }
    $registrar['csrf']=html_entity_decode($debugToken[1]);
    debugCheck(debugTableCounts($database)===$initialCounts,'Opening the debug screen does not change data.');
    foreach ([$library,$studentClient] as $client) { debugCheck(debugHttp($client['handle'],'/debug/index.php')['status']===200,'Local debug access does not depend on the current school account role.'); }
    debugCheck(debugHttp($studentClient['handle'],'/student/dashboard.php')['status']===200,'Opening the debug screen does not change the student application session.');
    $schoolToken=debugHttp($localClient,'/debug/action.php',['action'=>'ready_all','period_id'=>$period,'csrf_token'=>$registrarApp['csrf']]);
    debugCheck($schoolToken['status']===403,'School login tokens cannot authorize standalone debug actions.');
    $hostProbe=curl_init();curl_setopt_array($hostProbe,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Host: remote.example']]);
    debugCheck(debugHttp($hostProbe,'/debug/index.php')['status']===404,'An external Host is rejected even from a loopback request.');
    curl_close($hostProbe);
    $getAction=debugHttp($registrar['handle'],'/debug/action.php');
    debugCheck($getAction['status']===405,'Debug actions never run from GET requests (HTTP ' . $getAction['status'] . ': ' . substr($getAction['body'],0,100) . ').');
    debugCheck(debugHttp($registrar['handle'],'/debug/action.php',['action'=>'activity','confirmation'=>'RESET ACTIVITY','csrf_token'=>'invalid'])['status']===403,'Reset actions require a valid CSRF token.');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'activity','confirmation'=>'wrong','csrf_token'=>$registrar['csrf']]);
    debugCheck(debugTableCounts($database)===$initialCounts,'A reset with the wrong confirmation changes nothing.');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'ready_all','period_id'=>$period+100,'csrf_token'=>$registrar['csrf']]);
    debugCheck((int)$database->query("SELECT COUNT(*) FROM office_period_readiness WHERE status='READY'")->fetchColumn()===0,'Stale semester requests cannot mark another semester ready.');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'ready_all','period_id'=>$period,'csrf_token'=>$registrar['csrf']]);
    debugCheck((int)$database->query("SELECT COUNT(*) FROM office_period_readiness WHERE status<>'READY'")->fetchColumn()===0,'Ready all offices marks every required office ready.');
    debugCheck((int)$database->query("SELECT COUNT(*) FROM clearance_stations WHERE status='APPROVED'")->fetchColumn()===0,'Ready all offices does not approve students or forge signatures.');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'open','period_id'=>$period,'csrf_token'=>$registrar['csrf']]);
    debugCheck($database->query('SELECT clearance_status FROM academic_periods WHERE id=' . $period)->fetchColumn()==='OPEN','Open clearance follows the actual Registrar workflow.');
    debugCheck(debugHttp($studentClient['handle'],'/student/clearance_preview.php?period=' . $period)['status']===200,'Opening through debug makes the student paper preview available.');
    if (getenv('DEBUG_CONSOLE_PREVIEW')==='1') {
        $directory=__DIR__ . '/../.tmp/debug-console-check';if(!is_dir($directory)){mkdir($directory,0700,true);}
        file_put_contents($directory . '/screen.html',debugHttp($registrar['handle'],'/debug/index.php')['body']);
    }
    $beforeReset=[];foreach($database->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table){$beforeReset[$table]=debugFingerprint($database,$table);}
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'semesters','confirmation'=>'CLEAR SEMESTERS','csrf_token'=>$registrar['csrf']]);
    $backup=debugFixtureBackup($database);
    debugCheck((int)$database->query('SELECT COUNT(*) FROM academic_periods')->fetchColumn()===0&&(int)$database->query('SELECT COUNT(*) FROM clearances')->fetchColumn()===0,'Clear all semesters removes semester definitions and clearance records.');
    debugCheck((int)$database->query('SELECT COUNT(*) FROM borrowed_books')->fetchColumn()===1&&(int)$database->query('SELECT COUNT(*) FROM violations')->fetchColumn()===1,'Semester reset preserves global library records and violations.');
    foreach($protected as $table){debugCheck(debugFingerprint($database,$table)===$fingerprints[$table],'Reset preserves ' . $table . ' exactly.');}
    $download=debugHttp($registrar['handle'],'/debug/backup.php?file=' . $backup);
    $sql=gzdecode($download['body']);
    debugCheck($download['status']===200&&is_string($sql)&&str_ends_with($sql,"-- BACKUP COMPLETE\n"),'Every reset produces a complete downloadable compressed SQL backup first.');
    $backupProbe=curl_init();curl_setopt_array($backupProbe,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Host: remote.example']]);
    debugCheck(debugHttp($backupProbe,'/debug/backup.php?file=' . $backup)['status']===404,'Backups are unavailable outside the local debug access boundary.');curl_close($backupProbe);
    debugCheck(debugHttp($registrar['handle'],'/debug/backup.php?file=../../config/database.php')['status']===404,'Backup downloads reject path traversal.');
    $database->exec('CREATE DATABASE `' . $restored . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $restored . '`');$database->exec($sql);
    foreach($beforeReset as $table=>$fingerprint){debugCheck(debugFingerprint($database,$table)===$fingerprint,'Backup restores original ' . $table . ' contents including binary values.');}
    $database->exec('USE `' . $temporary . '`');
    $newPage=debugHttp($registrar['handle'],'/debug/index.php');
    debugCheck($newPage['status']===200&&str_contains($newPage['body'],'Start a new semester'),'The screen still works after all semesters are deleted.');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'create_semester','school_year'=>'2099-2100','semester'=>'2ND','csrf_token'=>$registrar['csrf']]);
    debugCheck((int)$database->query('SELECT COUNT(*) FROM academic_periods')->fetchColumn()===1,'A new semester can be created from the debug screen after a reset.');
    $beforeFailure=debugTableCounts($database);
    $database->exec('CREATE VIEW debug_test_backup_view AS SELECT id FROM users');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'activity','confirmation'=>'RESET ACTIVITY','csrf_token'=>$registrar['csrf']]);
    debugCheck(debugTableCounts($database)===$beforeFailure,'A database that cannot be backed up safely is not reset.');
    $database->exec('DROP VIEW debug_test_backup_view');
    $database->exec('CREATE TABLE debug_test_blocker (id BIGINT UNSIGNED PRIMARY KEY,period_id BIGINT UNSIGNED NOT NULL, FOREIGN KEY(period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT) ENGINE=InnoDB');
    $database->exec('INSERT INTO debug_test_blocker SELECT 1,id FROM academic_periods LIMIT 1');
    $beforeBackups=array_column(debugBackups(),'name');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'activity','confirmation'=>'RESET ACTIVITY','csrf_token'=>$registrar['csrf']]);
    foreach(array_diff(array_column(debugBackups(),'name'),$beforeBackups) as $failedBackup){$backups[]=$failedBackup;}
    debugCheck(debugTableCounts($database)===$beforeFailure,'A foreign key failure rolls back all reset deletions and retains the prior records.');
    $database->exec('DROP TABLE debug_test_blocker');
    debugHttp($registrar['handle'],'/debug/action.php',['action'=>'activity','confirmation'=>'RESET ACTIVITY','csrf_token'=>$registrar['csrf']]);
    debugFixtureBackup($database);
    foreach(array_merge(DEBUG_SEMESTER_TABLES,DEBUG_GLOBAL_ACTIVITY_TABLES,['notifications']) as $table){debugCheck((int)$database->query('SELECT COUNT(*) FROM ' . debugTableIdentifier($table))->fetchColumn()===0,'Fresh activity reset clears ' . $table . '.');}
    foreach($protected as $table){debugCheck(debugFingerprint($database,$table)===$fingerprints[$table],'Full activity reset retains ' . $table . '.');}
    debugCheck((int)$database->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()===1,'Foreign key enforcement stays enabled during normal reset operations.');
    debugCheck((int)$database->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'DEBUG_%' AND user_id IS NOT NULL")->fetchColumn()===0,'Standalone debug actions do not impersonate a school account in audit logs.');
    $disabledSocket=stream_socket_server('tcp://127.0.0.1:0',$errorNumber,$errorText);$disabledAddress=stream_socket_get_name($disabledSocket,false);fclose($disabledSocket);
    $disabledEnvironment=$environment;$disabledEnvironment['CLEAR_GO_DEBUG']='0';$disabledEnvironment['DB_DATABASE']='debug_gate_must_not_access_database';
    $disabledServer=proc_open([PHP_BINARY,'-S',$disabledAddress,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$disabledPipes,dirname(__DIR__),$disabledEnvironment);
    if(!is_resource($disabledServer)){throw new RuntimeException('Cannot start disabled-gate test server.');}fclose($disabledPipes[0]);
    for($i=0;$i<50;$i++){$probe=@stream_socket_client('tcp://' . $disabledAddress,$errorNumber,$errorText,.1);if($probe!==false){fclose($probe);break;}usleep(100000);}
    $disabledProbe=curl_init('http://' . $disabledAddress . '/debug/index.php');curl_setopt($disabledProbe,CURLOPT_RETURNTRANSFER,true);curl_exec($disabledProbe);
    debugCheck((int)curl_getinfo($disabledProbe,CURLINFO_HTTP_CODE)===404,'Disabling debug access blocks the page before login or database access.');curl_close($disabledProbe);
    echo "All {$checks} local debug console checks passed.\n";
} finally {
    if($database->inTransaction()){$database->rollBack();}
    foreach($clients as $client){try{debugHttp($client['handle'],'/logout.php',['csrf_token'=>$client['csrf']]);}catch(Throwable $ignored){}curl_close($client['handle']);}
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($localClient instanceof CurlHandle){curl_close($localClient);}
    if(is_resource($disabledServer)){proc_terminate($disabledServer);proc_close($disabledServer);}
    foreach(array_unique($backups) as $backup){if(preg_match('/\Adebug-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}\.sql\.gz\z/',$backup)){unlink(debugBackupDirectory() . '/' . $backup);}}
    if(is_file($log)){unlink($log);}
    if(!preg_match('/\Acleargo_debug_test_[a-f0-9]{12}\z/',$temporary)||$temporary===$template){throw new RuntimeException('Unsafe test cleanup.');}
    $database->exec('DROP DATABASE IF EXISTS `' . $restored . '`');$database->exec('DROP DATABASE IF EXISTS `' . $temporary . '`');
}
