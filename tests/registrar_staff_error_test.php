<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$template = getenv('DB_DATABASE') ?: 'lewis_clearance';
$temporary = 'cleargo_staff_error_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $template) || $temporary === $template) { throw new RuntimeException('Unsafe test database.'); }
$host = getenv('DB_HOST') ?: 'localhost'; $port = getenv('DB_PORT') ?: '3306';
$password = getenv('DB_PASSWORD');
$database = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", getenv('DB_USERNAME') ?: 'root', $password === false ? '' : $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$server = null; $clients = []; $checks = 0; $base = '';
$log = tempnam(sys_get_temp_dir(), 'staff-error-log-');

function staffErrorCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++; echo 'PASS: ' . $message . PHP_EOL;
}
function staffErrorHttp(CurlHandle $client, string $path, ?array $post = null): array {
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($post === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else { curl_setopt($client, CURLOPT_POST, true); curl_setopt($client, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($client);
    if ($body === false) { throw new RuntimeException(curl_error($client)); }
    return ['status'=>(int)curl_getinfo($client,CURLINFO_HTTP_CODE),'body'=>$body];
}
function staffErrorLogin(string $email): array {
    global $clients;
    $client = curl_init(); $clients[] = ['client'=>$client,'csrf'=>null]; $clientIndex = array_key_last($clients);
    curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>10]);
    $login = staffErrorHttp($client,'/login.php');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $login['body'], $match)) { throw new RuntimeException('Missing login token.'); }
    $token = html_entity_decode($match[1]);
    $clients[$clientIndex]['csrf'] = $token;
    $response = staffErrorHttp($client,'/auth/login_process.php',['email'=>$email,'password'=>'Synthetic-Only-2026!','csrf_token'=>$token]);
    if ($response['status'] !== 303) { throw new RuntimeException('Synthetic login failed.'); }
    require_once __DIR__ . '/support/http_fixture.php'; global $base;
    $token = signedInFixtureCsrf($client, $base); $clients[$clientIndex]['csrf'] = $token;
    return ['client'=>$client,'csrf'=>$token];
}
function staffErrorFeedback(array $registrar, array $post, string $expected): void {
    $response = staffErrorHttp($registrar['client'],'/registrar/staff_action.php',$post + ['csrf_token'=>$registrar['csrf']]);
    staffErrorCheck($response['status'] === 303, 'Staff action uses a controlled redirect.');
    $page = staffErrorHttp($registrar['client'],'/registrar/staff_accounts.php');
    staffErrorCheck($page['status'] === 200 && str_contains($page['body'],$expected), 'Staff page displays: ' . $expected);
    staffErrorCheck(preg_match('/SQLSTATE|Duplicate entry|Integrity constraint|PDOException|Fatal error|Stack trace|Uncaught /i',$response['body'].$page['body']) !== 1, 'The action and staff page expose no database or PHP exception details.');
}

try {
    $database->exec('CREATE DATABASE `' . $temporary . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database->exec('USE `' . $temporary . '`'); $database->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($database->query('SHOW FULL TABLES FROM `' . $template . "` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Unsafe template table.'); }
        $database->exec($database->query('SHOW CREATE TABLE `' . $template . '`.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1]);
    }
    foreach (['organizations','stations'] as $table) { $database->exec('INSERT INTO `' . $temporary . '`.`' . $table . '` SELECT * FROM `' . $template . '`.`' . $table . '`'); }
    $database->exec('SET FOREIGN_KEY_CHECKS=1');
    $database->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES ('2099-2100','1ST','ACTIVE','PREPARING')");
    $insert = $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES (?,?,?,'ACTIVE')");
    $users = [];
    foreach (['registrar'=>'REGISTRAR','library'=>'LIBRARY_STAFF','clinic'=>'CLINIC_STAFF'] as $name=>$role) {
        $insert->execute([$name.'-error-test@thelewiscollege.edu.ph',password_hash('Synthetic-Only-2026!',PASSWORD_DEFAULT),$role]);
        $users[$name] = (int)$database->lastInsertId();
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0',$errorNumber,$errorText);
    if ($socket === false) { throw new RuntimeException($errorText); }
    $address = stream_socket_get_name($socket,false); fclose($socket); $base = 'http://'.$address;
    $environment = getenv(); $environment['DB_DATABASE']=$temporary; unset($environment['APP_BASE_URL']);
    $server = proc_open([PHP_BINARY,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start the isolated server.'); } fclose($pipes[0]);
    for ($attempt=0;$attempt<50;$attempt++) { $probe=@stream_socket_client('tcp://'.$address,$errorNumber,$errorText,0.1);if($probe!==false){fclose($probe);break;}usleep(100000); }
    $registrar=staffErrorLogin('registrar-error-test@thelewiscollege.edu.ph');
    $library=staffErrorLogin('library-error-test@thelewiscollege.edu.ph');
    $before=(int)$database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $hashBefore=$database->query('SELECT password_hash FROM users WHERE id='.$users['library'])->fetchColumn();
    staffErrorFeedback($registrar,['action'=>'create','email'=>'library-error-test@thelewiscollege.edu.ph','role'=>'LIBRARY_STAFF'],'That school email already has an account.');
    staffErrorCheck((int)$database->query('SELECT COUNT(*) FROM users')->fetchColumn()===$before,'Duplicate creation does not add an account.');
    staffErrorCheck($database->query('SELECT password_hash FROM users WHERE id='.$users['library'])->fetchColumn()===$hashBefore,'Duplicate creation preserves the existing password.');
    staffErrorCheck((int)$database->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===0,'Rejected creation does not create a success audit event.');
    staffErrorFeedback($registrar,['action'=>'create','email'=>' LIBRARY-ERROR-TEST@THELEWISCOLLEGE.EDU.PH ','role'=>'LIBRARY_STAFF'],'That school email already has an account.');
    staffErrorFeedback($registrar,['action'=>'update_email','user_id'=>$users['clinic'],'email'=>'library-error-test@thelewiscollege.edu.ph'],'That school email already has an account.');
    staffErrorCheck($database->query('SELECT email FROM users WHERE id='.$users['clinic'])->fetchColumn()==='clinic-error-test@thelewiscollege.edu.ph','A conflicting email change preserves the original address.');
    staffErrorFeedback($registrar,['action'=>'create','email'=>'not-a-school-email@example.com','role'=>'LIBRARY_STAFF'],'Enter a valid staff role and Lewis College GSuite Address.');
    $invalidCollege=(int)$database->query('SELECT MAX(id)+1000 FROM organizations')->fetchColumn();
    staffErrorFeedback($registrar,['action'=>'create','email'=>'invalid-college-error-test@thelewiscollege.edu.ph','role'=>'ORG_STAFF','organization_id'=>$invalidCollege],'Unable to update the staff account. Please try again.');
    staffErrorCheck((int)$database->query("SELECT COUNT(*) FROM users WHERE email='invalid-college-error-test@thelewiscollege.edu.ph'")->fetchColumn()===0,'An unrelated database constraint failure rolls back the new account.');
    staffErrorFeedback($registrar,['action'=>'create','email'=>'created-error-test@thelewiscollege.edu.ph','role'=>'LIBRARY_STAFF'],'Staff account created');
    $created=(int)$database->query("SELECT id FROM users WHERE email='created-error-test@thelewiscollege.edu.ph'")->fetchColumn();
    staffErrorCheck($created>0 && (int)$database->query("SELECT COUNT(*) FROM audit_logs WHERE action='STAFF_ACCOUNT_CREATED'")->fetchColumn()===1,'Normal staff creation still succeeds and is audited.');
    staffErrorFeedback($registrar,['action'=>'update_email','user_id'=>$created,'email'=>'updated-error-test@thelewiscollege.edu.ph'],'Staff GSuite Address updated.');
    staffErrorCheck($database->query('SELECT email FROM users WHERE id='.$created)->fetchColumn()==='updated-error-test@thelewiscollege.edu.ph','A unique email change still succeeds.');
    staffErrorFeedback($registrar,['action'=>'create','email'=>'csrf-error-test@thelewiscollege.edu.ph','role'=>'LIBRARY_STAFF','csrf_token'=>'invalid'],'Unable to process that request.');
    staffErrorCheck((int)$database->query("SELECT COUNT(*) FROM users WHERE email='csrf-error-test@thelewiscollege.edu.ph'")->fetchColumn()===0,'An invalid CSRF token cannot create an account.');
    $denied=staffErrorHttp($library['client'],'/registrar/staff_action.php',['action'=>'create','email'=>'forbidden-error-test@thelewiscollege.edu.ph','role'=>'LIBRARY_STAFF','csrf_token'=>$library['csrf']]);
    staffErrorCheck($denied['status']===403,'Non-Registrar staff cannot create accounts.');
    staffErrorCheck((int)$database->query("SELECT COUNT(*) FROM users WHERE email='forbidden-error-test@thelewiscollege.edu.ph'")->fetchColumn()===0,'Rejected role access leaves accounts unchanged.');
    $serverLog=file_get_contents($log);
    staffErrorCheck(str_contains($serverLog,'Staff account action failed: SQLSTATE') && str_contains($serverLog,'1062') && str_contains($serverLog,'1452'),'Technical duplicate and foreign-key errors remain in the private server log.');
    echo 'All '.$checks.' Registrar staff error checks passed.'.PHP_EOL;
} finally {
    if ($database->inTransaction()) { $database->rollBack(); }
    foreach ($clients as $session) {
        try { staffErrorHttp($session['client'],'/logout.php',['csrf_token'=>$session['csrf']]); } catch (Throwable $ignored) {}
        curl_close($session['client']);
    }
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(is_file($log)){unlink($log);}
    if(!preg_match('/\Acleargo_staff_error_test_[a-f0-9]{12}\z/',$temporary)||$temporary===$template){throw new RuntimeException('Refusing unsafe cleanup.');}
    $database->exec('DROP DATABASE IF EXISTS `'.$temporary.'`');
}
