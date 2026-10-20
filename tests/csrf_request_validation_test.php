<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../auth/auth_check.php';

$checks = 0; $server = null; $client = null;
$log = tempnam(sys_get_temp_dir(), 'csrf-request-log-');
$router = tempnam(sys_get_temp_dir(), 'csrf-request-router-');
function csrfRequestCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++; echo 'PASS: ' . $message . PHP_EOL;
}
try {
    $token = createCsrfToken();
    set_error_handler(static function(int $severity, string $message, string $file, int $line): never { throw new ErrorException($message,0,$severity,$file,$line); });
    try {
        csrfRequestCheck(verifyCsrfToken($token), 'The valid string token is accepted.');
        foreach ([null,[],['invalid'],[['invalid']],['token'=>$token],0,123,1.5,true,false,new stdClass(),'','invalid'] as $index=>$value) {
            csrfRequestCheck(!verifyCsrfToken($value), 'Malformed or mismatched token ' . ($index+1) . ' is rejected without PHP warnings.');
        }
        csrfRequestCheck(verifyCsrfToken($token), 'Malformed values do not invalidate the existing valid token.');
        $_SESSION['csrf_token'] = ['bad-session-value'];
        csrfRequestCheck(!verifyCsrfToken($token), 'A malformed session token is rejected safely.');
        $_SESSION['csrf_token'] = $token;
    } finally { restore_error_handler(); $_SESSION=[]; session_destroy(); }

    $root = dirname(__DIR__);
    $routerCode = '<?php' . PHP_EOL
        . 'if (in_array(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), ["/__csrf_error_settings_check", "/__csrf_error_failure_check"], true)) {' . PHP_EOL
        . 'require ' . var_export($root . '/auth/auth_check.php',true) . ';' . PHP_EOL
        . 'if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) === "/__csrf_error_failure_check") { throw new RuntimeException("CSRF-TEST-INTERNAL-EXCEPTION"); }' . PHP_EOL
        . 'trigger_error("CSRF-TEST-INTERNAL-WARNING", E_USER_WARNING);' . PHP_EOL
        . 'header("Content-Type: application/json"); echo json_encode([ini_get("display_errors"),ini_get("display_startup_errors"),ini_get("log_errors")]); return true;' . PHP_EOL
        . '} return false;' . PHP_EOL;
    file_put_contents($router,$routerCode);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errorNumber,$errorText);
    if($socket===false){throw new RuntimeException($errorText);}
    $address=stream_socket_get_name($socket,false);fclose($socket);$base='http://'.$address;
    $environment=getenv();$environment['DB_HOST']='127.0.0.1';$environment['DB_PORT']='1';
    $environment['DB_DATABASE']='csrf_validation_must_not_connect';$environment['DB_USERNAME']='csrf_invalid_user';$environment['DB_PASSWORD']='';unset($environment['APP_BASE_URL']);
    $server=proc_open([PHP_BINARY,'-d','display_errors=1','-d','display_startup_errors=1','-d','log_errors=0','-S',$address,'-t',$root,$router],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,$environment);
    if(!is_resource($server)){throw new RuntimeException('Cannot start isolated CSRF test server.');}fclose($pipes[0]);
    for($attempt=0;$attempt<50;$attempt++){$probe=@stream_socket_client('tcp://'.$address,$errorNumber,$errorText,0.1);if($probe!==false){fclose($probe);break;}usleep(100000);}
    $client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>5]);
    curl_setopt($client,CURLOPT_URL,$base.'/login.php');$page=curl_exec($client);
    if(!is_string($page)||!preg_match('/name="csrf_token" value="([^"]+)"/',$page,$match)){throw new RuntimeException('Missing login form token.');}
    $webToken=html_entity_decode($match[1]);
    $cases=[
        ['','Unable to process the sign-in request.'],
        ['csrf_token=','Unable to process the sign-in request.'],
        ['csrf_token=invalid','Unable to process the sign-in request.'],
        ['csrf_token%5B%5D=invalid','Unable to process the sign-in request.'],
        ['csrf_token%5Btoken%5D=invalid','Unable to process the sign-in request.'],
        ['csrf_token%5B%5D%5B%5D=invalid','Unable to process the sign-in request.'],
        [http_build_query(['csrf_token'=>$webToken]),'Invalid email or password.'],
        [http_build_query(['csrf_token'=>$webToken,'email'=>['bad'],'password'=>'fixture']),'Invalid email or password.'],
        [http_build_query(['csrf_token'=>$webToken,'email'=>'fixture@thelewiscollege.edu.ph','password'=>['bad']]),'Invalid email or password.'],
    ];
    foreach($cases as $index=>[$post,$expected]){
        curl_setopt($client,CURLOPT_URL,$base.'/auth/login_process.php');curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,$post);
        $body=curl_exec($client);$status=(int)curl_getinfo($client,CURLINFO_HTTP_CODE);$redirect=(string)curl_getinfo($client,CURLINFO_REDIRECT_URL);
        csrfRequestCheck($status===303&&$redirect===$base.'/login.php','Invalid request '.($index+1).' redirects safely to login.');
        csrfRequestCheck($body==='','Invalid request '.($index+1).' returns no PHP errors, paths or stack traces.');
        curl_setopt($client,CURLOPT_URL,$base.'/login.php');curl_setopt($client,CURLOPT_HTTPGET,true);$page=curl_exec($client);
        csrfRequestCheck(is_string($page)&&str_contains($page,$expected)&&preg_match('/TypeError|Fatal error|Warning:|Stack trace|SQLSTATE|auth_check\.php:/i',$page)!==1,'Invalid request '.($index+1).' shows the expected controlled feedback.');
    }
    curl_setopt($client,CURLOPT_URL,$base.'/__csrf_error_settings_check');$settings=curl_exec($client);
    csrfRequestCheck(is_string($settings)&&json_decode($settings,true)===['0','0','1'],'Web bootstrap disables error display and enables logging even when server defaults are unsafe.');
    curl_setopt($client,CURLOPT_URL,$base.'/__csrf_error_failure_check');$failure=curl_exec($client);
    csrfRequestCheck((int)curl_getinfo($client,CURLINFO_HTTP_CODE)===500&&$failure==='','An internal exception uses HTTP 500 without exposing technical details.');
    $serverLog=file_get_contents($log);
    csrfRequestCheck(str_contains($serverLog,'CSRF-TEST-INTERNAL-WARNING')&&str_contains($serverLog,'CSRF-TEST-INTERNAL-EXCEPTION'),'Internal warnings and exception details are written to the server log.');
    csrfRequestCheck(!str_contains($serverLog,'Database connection failed')&&!str_contains($serverLog,'Array to string conversion'),'Invalid login requests never reach the database or cast arrays to strings.');
    curl_setopt($client,CURLOPT_URL,$base.'/logout.php');curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,['csrf_token'=>$webToken]);curl_exec($client);
    echo 'All '.$checks.' CSRF and malformed-request checks passed.'.PHP_EOL;
} finally {
    if($client instanceof CurlHandle){curl_close($client);}
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    foreach([$log,$router] as $file){if(is_string($file)&&is_file($file)){unlink($file);}}
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
}
