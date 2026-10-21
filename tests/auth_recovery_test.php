<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../auth/password_recovery_functions.php';
if(($argv[1]??'')==='--rate-worker'){
    echo consumeAuthLimits(getDatabaseConnection(),[['parallel-test','one',5,900]]);exit;
}
if(($argv[1]??'')==='--reset-worker'){
    echo completeStudentPasswordReset(getDatabaseConnection(),$argv[2],$argv[3],$argv[4],'Parallel-Synthetic-Password!')?'1':'0';exit;
}
ob_start();
$root=dirname(__DIR__);$db=getDatabaseConnection();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$source))throw new RuntimeException('Unsafe source schema.');
$temporary='cleargo_auth_test_'.bin2hex(random_bytes(6));$out=$root.'/.tmp/auth-recovery-check-'.bin2hex(random_bytes(4));mkdir($out,0700,true);
$clients=[];$checks=[];$server=null;$sink=null;$base='';$accounts=[];
function authTestCheck(bool $ok,string $message):void{global $checks;$checks[]=['name'=>$message,'passed'=>$ok];if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
function authTestClient():CurlHandle{global $clients;$c=curl_init();curl_setopt_array($c,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15]);$clients[]=$c;return$c;}
function authTestHttp(CurlHandle $client,string $path,?array $post=null):array{
    global $base;$headers='';curl_setopt($client,CURLOPT_URL,$base.$path);curl_setopt($client,CURLOPT_HEADERFUNCTION,static function($h,$line)use(&$headers){$headers.=$line;return strlen($line);});
    if($post===null)curl_setopt($client,CURLOPT_HTTPGET,true);else{curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($client);if($body===false)throw new RuntimeException(curl_error($client));
    if(preg_match('/Fatal error|Warning:|SQLSTATE|Stack trace/',$body))throw new RuntimeException('HTTP internal error at '.$path);
    return['status'=>(int)curl_getinfo($client,CURLINFO_HTTP_CODE),'body'=>$body,'headers'=>$headers,'redirect'=>(string)curl_getinfo($client,CURLINFO_REDIRECT_URL)];
}
function authTestCsrf(CurlHandle $client,string $path='/login.php?recovery=1'):string{$page=authTestHttp($client,$path);if(!preg_match('/name="csrf_token" value="([^"]+)"/',$page['body'],$m))throw new RuntimeException('Missing CSRF at '.$path);return html_entity_decode($m[1]);}
function authTestCookies(CurlHandle $client):array{$all=[];foreach(curl_getinfo($client,CURLINFO_COOKIELIST)as$line){$p=explode("\t",$line);if(count($p)===7)$all[$p[5]]=$p[6];}return$all;}
function authTestLogin(CurlHandle $client,string $email,string $password='Auth-Test-Only-2026!',bool $remember=false):array{
    $csrf=authTestCsrf($client,'/login.php');return authTestHttp($client,'/auth/login_process.php',['csrf_token'=>$csrf,'email'=>$email,'password'=>$password]+($remember?['remember_me'=>'1']:[]));
}
function authTestRequest(CurlHandle $client,string $email):array{
    $csrf=authTestCsrf($client);$r=authTestHttp($client,'/auth/password_recovery_action.php',['csrf_token'=>$csrf,'action'=>'request','email'=>$email]);
    if($r['status']===303)$r['page']=authTestHttp($client,'/login.php?recovery=1')['body'];return$r;
}
function authTestCode(PDO $db,int $userId):array{
    $q=$db->prepare("SELECT * FROM auth_mail_queue WHERE user_id=? AND kind='STUDENT_RESET_CODE' ORDER BY id DESC LIMIT 1");$q->execute([$userId]);$job=$q->fetch();
    $payload=decryptAuthMail($job['payload'],$job['kind'].':'.$userId.':'.$job['request_id']);return[$payload['code'],$job];
}
function authTestVerify(CurlHandle $client,string $code):array{return authTestHttp($client,'/auth/password_recovery_action.php',['csrf_token'=>authTestCsrf($client),'action'=>'verify','code'=>$code]);}
function authTestReset(CurlHandle $client,string $password,string $confirm):array{return authTestHttp($client,'/auth/password_recovery_action.php',['csrf_token'=>authTestCsrf($client),'action'=>'reset','new_password'=>$password,'confirm_password'=>$confirm]);}
try{
    $db->exec("CREATE DATABASE `$temporary` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE `$temporary`");$db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($db->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN)as$table){if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$table))throw new RuntimeException('Unsafe table.');$db->exec($db->query("SHOW CREATE TABLE `$source`.`$table`")->fetch(PDO::FETCH_NUM)[1]);}
    foreach(['organizations','stations','requirements']as$table)$db->exec("INSERT INTO `$table` SELECT * FROM `$source`.`$table`");$db->exec('SET FOREIGN_KEY_CHECKS=1');
    $org=(int)$db->query("SELECT id FROM organizations WHERE code='CCS'")->fetchColumn();
    foreach(array_merge(APPLICATION_ROLES,['STUDENT_OTHER','STUDENT_INACTIVE'])as$key){$role=str_starts_with($key,'STUDENT_')?'STUDENT':$key;$email=strtolower($key).'-auth-test@thelewiscollege.edu.ph';
        $db->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(?,?,?,'ACTIVE')")->execute([$email,password_hash('Auth-Test-Only-2026!',PASSWORD_DEFAULT),$role]);$id=(int)$db->lastInsertId();$accounts[$key]=['id'=>$id,'email'=>$email,'role'=>$role,'auth_version'=>1];
        if($role==='STUDENT')$db->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES(?,?,'Synthetic','Auth Test','BSCS',1,?,'ACTIVE')")->execute([$id,'AUTH-'.$key,$org]);
    }
    $db->prepare("UPDATE users SET status='INACTIVE' WHERE id=?")->execute([$accounts['STUDENT_INACTIVE']['id']]);
    // All messages stay on loopback; use a random test-only encryption key.
    putenv('APP_ENV=development');putenv('APP_KEY=base64:'.base64_encode(random_bytes(32)));putenv('MAIL_HOST=127.0.0.1');putenv('MAIL_ENCRYPTION=none');putenv('MAIL_USERNAME=synthetic-smtp');putenv('MAIL_PASSWORD=synthetic-smtp-password');putenv('MAIL_FROM_ADDRESS=synthetic-sender@thelewiscollege.edu.ph');
    $sink=proc_open(['C:/Program Files/nodejs/node.exe',__DIR__.'/support/smtp_sink.mjs',$out.'/smtp-port',$out.'/messages.json'],[0=>['pipe','r'],1=>['file',$out.'/smtp.log','a'],2=>['file',$out.'/smtp.log','a']],$pipes,$root);fclose($pipes[0]);
    for($i=0;$i<100&&!is_file($out.'/smtp-port');$i++)usleep(50000);if(!is_file($out.'/smtp-port'))throw new RuntimeException('SMTP sink did not start');putenv('MAIL_PORT='.trim(file_get_contents($out.'/smtp-port')));
    $socket=stream_socket_server('tcp://127.0.0.1:0',$err,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$base='http://'.$address;
    $env=getenv();$env['DB_DATABASE']=$temporary;$env['APP_BASE_URL']='/';
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$out.'/http.log','a'],2=>['file',$out.'/http.log','a']],$pipes,$root,$env);fclose($pipes[0]);
    for($i=0;$i<100;$i++){if($s=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1))){fclose($s);break;}usleep(50000);}
    $student=$accounts['STUDENT'];$saved=authTestClient();$signIn=authTestLogin($saved,$student['email'],'Auth-Test-Only-2026!',true);
    authTestCheck($signIn['status']===303&&isset(authTestCookies($saved)[REMEMBER_COOKIE]),'Checking Keep me signed in saves a persistent cookie.');
    authTestCheck(str_contains(strtolower($signIn['headers']),'httponly')&&str_contains(strtolower($signIn['headers']),'samesite=lax'),'Saved sign-in cookie is HttpOnly and SameSite=Lax.');
    $savedValue=authTestCookies($saved)[REMEMBER_COOKIE];$parts=rememberCookieParts($savedValue);$q=$db->prepare('SELECT * FROM auth_remember_tokens WHERE selector=?');$q->execute([$parts[0]]);$row=$q->fetch();
    authTestCheck($row['validator_hash']===hash('sha256',$parts[1])&&!str_contains(serialize($row),$parts[1])&&(int)$row['expires_at']<=time()+REMEMBER_LIFETIME,'The database stores a verifier hash and limits saved sign-ins to 30 days.');
    $restored=authTestClient();curl_setopt($restored,CURLOPT_COOKIE,REMEMBER_COOKIE.'='.$savedValue);$page=authTestHttp($restored,'/student/password.php');
    authTestCheck($page['status']===200&&str_contains($page['body'],$student['email']),'A new browser session can sign in using the saved cookie.');
    $rotated=authTestCookies($restored)[REMEMBER_COOKIE]??'';curl_setopt($restored,CURLOPT_COOKIE,'');
    authTestCheck($rotated!==''&&$rotated!==$savedValue,'Restoring a saved sign-in rotates its secret.');
    $plain=authTestClient();authTestLogin($plain,$student['email']);
    authTestCheck(!isset(authTestCookies($plain)[REMEMBER_COOKIE]),'Leaving Keep me signed in unchecked creates no saved sign-in.');
    $anonymous=authTestClient();
    $legacy=authTestHttp($anonymous,'/forgot_password.php');
    authTestCheck($legacy['status']===303&&str_ends_with($legacy['redirect'],'/login.php?recovery=1'),'Old recovery links open the login modal instead of a separate page.');
    file_put_contents($out.'/login.html',authTestHttp($anonymous,'/login.php')['body']);
    file_put_contents($out.'/forgot.html',authTestHttp($anonymous,'/login.php?recovery=1')['body']);
    $invalid=authTestHttp($anonymous,'/auth/password_recovery_action.php',['action'=>'request','email'=>$student['email'],'csrf_token'=>'bad']);
    authTestCheck($invalid['status']===303&&(int)$db->query('SELECT COUNT(*) FROM student_password_resets')->fetchColumn()===0,'Invalid CSRF cannot request a reset email.');
    $r=authTestRequest($anonymous,'unknown-auth-test@thelewiscollege.edu.ph');
    authTestCheck($r['status']===303&&str_contains($r['page'],RESET_REQUEST_MESSAGE)&&(int)$db->query('SELECT COUNT(*) FROM auth_mail_queue')->fetchColumn()===0,'Unknown accounts receive generic feedback without email.');
    foreach($accounts as$key=>$account){if($account['role']==='STUDENT'&&$key!=='STUDENT_INACTIVE')continue;$c=authTestClient();$r=authTestRequest($c,$account['email']);
        authTestCheck($r['status']===303&&str_contains($r['page'],RESET_REQUEST_MESSAGE),'Recovery does not disclose or email '.$key.' accounts.');}
    authTestCheck((int)$db->query('SELECT COUNT(*) FROM student_password_resets')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM auth_mail_queue')->fetchColumn()===0,'Office and inactive accounts have no reset records or queued emails.');
    $a=authTestClient();$r=authTestRequest($a,$student['email']);file_put_contents($out.'/verify.html',$r['page']);[$code,$job]=authTestCode($db,$student['id']);
    $q=$db->prepare('SELECT * FROM student_password_resets WHERE user_id=?');$q->execute([$student['id']]);$reset=$q->fetch();
    authTestCheck($r['status']===303&&password_verify($code,$reset['code_hash'])&&!str_contains($job['payload'],$code),'Student code is hashed and queued email is encrypted.');
    $worker=proc_open([PHP_BINARY,$root.'/tools/auth_mail_worker.php','--limit=20'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$out.'/worker.log','a']],$pipes,$root,$env);fclose($pipes[0]);$workerOutput=stream_get_contents($pipes[1]);fclose($pipes[1]);$workerStatus=proc_close($worker);$sent=json_decode($workerOutput,true);$messages=json_decode(file_get_contents($out.'/messages.json'),true);
    authTestCheck($workerStatus===0&&is_file(authMailHeartbeatPath($db)),'The scheduled mail worker delivers jobs and records health for its own database.');
    authTestCheck($sent['sent']===1&&str_contains($messages[0]['body'],$code)&&str_contains($messages[0]['recipient'],$student['email']),'PHPMailer delivers the code over SMTP to the local test mailbox.');
    $b=authTestClient();authTestVerify($b,$code);
    authTestCheck(!str_contains(authTestHttp($b,'/login.php?recovery=1')['body'],'id="new-password"'),'A code cannot be used without its original browser reset context.');
    for($i=0;$i<5;$i++)authTestVerify($a,$code==='000000'?'111111':'000000');authTestVerify($a,$code);
    $q->execute([$student['id']]);$locked=$q->fetch();
    authTestCheck((int)$locked['attempts']===5&&$locked['verified_until']===null,'Five wrong codes exhaust the request, including a later correct code.');
    $db->exec('DELETE FROM auth_rate_limits');$a=authTestClient();authTestRequest($a,$student['email']);[$expired]=authTestCode($db,$student['id']);
    $db->prepare('UPDATE student_password_resets SET expires_at=? WHERE user_id=?')->execute([time()-1,$student['id']]);authTestVerify($a,$expired);
    authTestCheck(!str_contains(authTestHttp($a,'/login.php?recovery=1')['body'],'id="new-password"'),'Expired email codes cannot create a reset grant.');
    $db->exec('DELETE FROM auth_rate_limits');$a=authTestClient();authTestRequest($a,$student['email']);[$oldCode,$oldJob]=authTestCode($db,$student['id']);
    $throttled=authTestRequest(authTestClient(),$student['email']);
    authTestCheck($throttled['status']===429&&str_contains($throttled['headers'],'Retry-After:'),'Immediate reset resends are throttled with Retry-After.');
    authTestCheck(str_contains($throttled['body'],'data-auto-open="true"')&&str_contains($throttled['body'],'Too many attempts'),'Rate limit feedback stays in the recovery modal.');
    $db->exec('DELETE FROM auth_rate_limits');$a=authTestClient();authTestRequest($a,$student['email']);[$code,$job]=authTestCode($db,$student['id']);
    authTestCheck($job['request_id']!==$oldJob['request_id']&&verifyStudentResetCode($db,$oldJob['request_id'],'wrong-binding',$oldCode)===null,'A replacement request invalidates the old code and browser context.');
    $early=authTestClient();authTestRequest($early,$accounts['STUDENT_OTHER']['email']);authTestReset($early,'New-Synthetic-Password!','New-Synthetic-Password!');
    authTestCheck((int)$db->query('SELECT MAX(auth_version) FROM users')->fetchColumn()===1,'Password reset is blocked until code verification is complete.');
    authTestVerify($a,$code);$resetPage=authTestHttp($a,'/login.php?recovery=1');file_put_contents($out.'/reset.html',$resetPage['body']);
    authTestCheck(str_contains($resetPage['body'],'id="new-password"'),'The correct code opens the new-password form.');
    authTestVerify($a,$code);$q->execute([$student['id']]);$verified=$q->fetch();
    authTestCheck($verified['code_hash']===''&&is_string($verified['reset_token_hash']),'Successful verification consumes the code and stores only a reset-grant hash.');
    authTestReset($a,'New-Synthetic-Password!','Mismatch!');
    authTestCheck(str_contains(authTestHttp($a,'/login.php?recovery=1')['body'],'confirmation does not match'),'Password confirmation must match before the grant is used.');
    $newPassword='New-Synthetic-Password!';$done=authTestReset($a,$newPassword,$newPassword);
    authTestCheck($done['status']===303&&str_ends_with($done['redirect'],'/login.php'),'Reset completes by returning to sign in, without automatic login.');
    $account=$db->query('SELECT * FROM users WHERE id='.$student['id'])->fetch();
    authTestCheck(password_verify($newPassword,$account['password_hash'])&&(int)$account['auth_version']===2,'Reset changes the hash and increments the account session version.');
    authTestCheck((int)$db->query('SELECT COUNT(*) FROM auth_remember_tokens WHERE user_id='.$student['id'])->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM student_password_resets WHERE user_id='.$student['id'])->fetchColumn()===0,'Reset revokes every remembered device and consumes the reset record.');
    authTestCheck(authTestHttp($plain,'/student/password.php')['status']===303,'A password reset revokes a previously signed-in session.');
    authTestCheck(authTestHttp($restored,'/auth/notifications.php')['status']===401,'A revoked session cannot poll private notifications.');
    $stolen=authTestClient();curl_setopt($stolen,CURLOPT_COOKIE,REMEMBER_COOKIE.'='.$rotated);
    authTestCheck(authTestHttp($stolen,'/student/password.php')['status']===303,'A saved cookie from before the password reset no longer works.');
    $login=authTestClient();authTestLogin($login,$student['email'],'Auth-Test-Only-2026!');
    authTestCheck(authTestHttp($login,'/student/password.php')['status']===303,'The old password cannot sign in after reset.');
    authTestLogin($login,$student['email'],$newPassword,true);
    authTestCheck(authTestHttp($login,'/student/password.php')['status']===200,'The new password signs in successfully.');
    $csrf=authTestCsrf($login,'/student/password.php');authTestHttp($login,'/logout.php',['csrf_token'=>$csrf]);
    authTestCheck((int)$db->query('SELECT COUNT(*) FROM auth_remember_tokens WHERE user_id='.$student['id'])->fetchColumn()===0&&!isset(authTestCookies($login)[REMEMBER_COOKIE]),'Logout deletes the saved token and clears its cookie.');
    $db->exec('DELETE FROM auth_rate_limits');$attacker=authTestClient();
    for($i=0;$i<5;$i++)authTestLogin($attacker,$student['email'],'wrong-password');$r=authTestLogin($attacker,$student['email'],'wrong-password');
    authTestCheck($r['status']===429&&str_contains($r['headers'],'Retry-After:'),'Repeated failed logins are throttled.');
    $workers=[];for($i=0;$i<10;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--rate-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$out.'/workers.log','a']],$pipes,$root,$env);fclose($pipes[0]);$workers[]=[$p,$pipes[1]];}
    $allowed=0;$denied=0;$workerResults=[];foreach($workers as[$p,$pipe]){$value=trim(stream_get_contents($pipe));fclose($pipe);$status=proc_close($p);$workerResults[]=['status'=>$status,'value'=>$value];if($value==='0')$allowed++;elseif(ctype_digit($value)&&(int)$value>0)$denied++;}
    file_put_contents($out.'/rate-worker-results.json',json_encode($workerResults,JSON_PRETTY_PRINT));
    authTestCheck($allowed===5&&$denied===5,'Concurrent requests cannot exceed the atomic rate-limit allowance.');
    dispatchAuthMail($db);$messages=json_decode(file_get_contents($out.'/messages.json'),true);
    authTestCheck(count(array_filter($messages,static fn($m)=>str_contains($m['body'],'password was changed')))===1,'The student receives a password-change notice without the new password.');
    $originalKey=appSetting('APP_KEY');$cipher=encryptAuthMail(['code'=>'123456'],'one');$rejected=false;try{decryptAuthMail($cipher,'another');}catch(RuntimeException){$rejected=true;}
    authTestCheck($rejected,'Encrypted email cannot be swapped into a different request context.');
    putenv('APP_ENV=production');$rejected=!authMailConfigured();putenv('APP_ENV=development');
    authTestCheck($rejected,'Production configuration rejects plaintext unauthenticated SMTP.');
    $current=$db->query('SELECT id,email,role,auth_version FROM users WHERE id='.$student['id'])->fetch();
    $token=issueRememberToken($db,$current);$when=time();$first=consumeRememberToken($db,$token,$when);$latest=$_COOKIE[REMEMBER_COOKIE];
    $parallel=consumeRememberToken($db,$token,$when+1);
    authTestCheck($first!==null&&$parallel!==null&&$_COOKIE[REMEMBER_COOKIE]===$latest,'Concurrent restore requests accept a brief previous-token grace without reverting the cookie.');
    authTestCheck(consumeRememberToken($db,$token,$when+31)===null,'A replay of the previous saved-sign-in secret is rejected after the grace period.');
    $token=issueRememberToken($db,$current);$p=rememberCookieParts($token);$db->prepare('UPDATE auth_remember_tokens SET expires_at=? WHERE selector=?')->execute([time()-1,$p[0]]);
    authTestCheck(consumeRememberToken($db,$token)===null,'Expired saved sign-ins are rejected.');
    $token=issueRememberToken($db,$current);$p=rememberCookieParts($token);
    authTestCheck(consumeRememberToken($db,$p[0].'.'.str_repeat('0',64))===null,'A tampered saved-sign-in secret is rejected.');
    $office=authTestClient();authTestLogin($office,$accounts['LIBRARY_STAFF']['email'],'Auth-Test-Only-2026!',true);$officeCookie=authTestCookies($office)[REMEMBER_COOKIE];
    $officeRestore=authTestClient();curl_setopt($officeRestore,CURLOPT_COOKIE,REMEMBER_COOKIE.'='.$officeCookie);
    authTestCheck(authTestHttp($officeRestore,'/auth/notifications.php')['status']===200,'Office accounts can use the optional saved sign-in.');
    replaceAccountPassword($db,$accounts['LIBRARY_STAFF']['id'],password_hash('Registrar-Changed-Office-Password!',PASSWORD_DEFAULT));
    authTestCheck(authTestHttp($office,'/auth/notifications.php')['status']===401,'A Registrar-style office password change revokes the old office session.');
    $db->exec('DELETE FROM auth_rate_limits');
    $self=authTestClient();authTestLogin($self,$student['email'],$newPassword,true);$otherSession=authTestClient();authTestLogin($otherSession,$student['email'],$newPassword);
    $r=authTestHttp($self,'/student/password_action.php',['csrf_token'=>authTestCsrf($self,'/student/password.php'),'current_password'=>$newPassword,'new_password'=>'Self-Changed-Synthetic-Password!','confirm_password'=>'Self-Changed-Synthetic-Password!']);
    authTestCheck($r['status']===303&&authTestHttp($self,'/student/password.php')['status']===200,'Normal password change keeps the current student session active.');
    authTestCheck(authTestHttp($otherSession,'/student/password.php')['status']===303&&(int)$db->query('SELECT COUNT(*) FROM auth_remember_tokens WHERE user_id='.$student['id'])->fetchColumn()===0,'Normal password change revokes other sessions and remembered devices.');
    $other=$accounts['STUDENT_OTHER'];$binding=bin2hex(random_bytes(32));$request=requestStudentPasswordReset($db,$other['email'],$binding);[$code]=authTestCode($db,$other['id']);$grant=verifyStudentResetCode($db,$request,$binding,$code);
    $workers=[];for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--reset-worker',$request,$binding,$grant],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$out.'/workers.log','a']],$pipes,$root,$env);fclose($pipes[0]);$workers[]=[$p,$pipes[1]];}
    $values=[];foreach($workers as[$p,$pipe]){$values[]=trim(stream_get_contents($pipe));fclose($pipe);proc_close($p);}sort($values);
    authTestCheck($values===['0','1']&&(int)$db->query('SELECT auth_version FROM users WHERE id='.$other['id'])->fetchColumn()===2,'A reset grant can change the password only once under concurrent requests.');
    cleanupAuthRecords($db);
    echo 'All '.count($checks)." authentication recovery, SMTP and saved sign-in checks passed.\n";
}finally{
    if($db->inTransaction())$db->rollBack();foreach($clients as$c)curl_close($c);
    foreach([$server,$sink]as$p)if(is_resource($p)){proc_terminate($p);proc_close($p);}
    if(!preg_match('/\Acleargo_auth_test_[a-f0-9]{12}\z/',$temporary))throw new RuntimeException('Unsafe cleanup');
    $db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$temporary`");
    $heartbeat=$root.'/storage/private/auth-mail-worker-'.substr(hash('sha256',$temporary),0,24).'.heartbeat';if(is_file($heartbeat))unlink($heartbeat);
    file_put_contents($out.'/results.json',json_encode(['checks'=>$checks,'synthetic_only'=>true,'temporary_database_removed'=>true],JSON_PRETTY_PRINT));
    file_put_contents($out.'/checks.log',(string)ob_get_contents());ob_end_flush();
}
