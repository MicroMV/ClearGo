<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../auth/mail_functions.php';
$checks=[];
function deploymentResult(string $name,string $status,string $detail):void{global$checks;$checks[]=['check'=>$name,'status'=>$status,'detail'=>$detail];}
deploymentResult('Environment',productionEnvironment()?'PASS':'FAIL','Set APP_ENV=production on the target server.');
deploymentResult('PHP',version_compare(PHP_VERSION,'8.3.0','>=')?'PASS':'FAIL','Detected '.PHP_VERSION.'. Use a supported, fully patched PHP version.');
$public=appSetting('APP_PUBLIC_URL');
deploymentResult('Public HTTPS URL',parse_url($public,PHP_URL_SCHEME)==='https'?'PASS':'FAIL','APP_PUBLIC_URL must identify the real HTTPS application URL; verify its certificate and proxy configuration.');
foreach(['pdo_mysql','openssl','mbstring','zlib','dom']as$extension)deploymentResult('Extension '.$extension,extension_loaded($extension)?'PASS':'FAIL','Required by authentication, encrypted mail or current imports.');
deploymentResult('Database credentials',appSetting('DB_USERNAME','root')!==''&&strtolower(appSetting('DB_USERNAME','root'))!=='root'&&appSetting('DB_PASSWORD')!==''?'PASS':'FAIL','Use a private, dedicated runtime database account; migrations use separate administrator credentials.');
deploymentResult('Student recovery SMTP',authMailConfigured()?'PASS':'FAIL','Configure APP_KEY and authenticated TLS SMTP. Credentials and codes are never printed by this check.');
foreach(['sessions','private','backups']as$folder){$path=__DIR__.'/../storage/'.$folder;deploymentResult('Storage '.$folder,is_dir($path)&&is_writable($path)?'PASS':'FAIL','A writable private runtime directory is required; deny direct HTTP access.');}
try{
 $db=getDatabaseConnection();$version=(string)$db->query('SELECT VERSION()')->fetchColumn();
 $heartbeat=authMailHeartbeatPath($db);$health=is_file($heartbeat)?json_decode((string)file_get_contents($heartbeat),true):null;
 deploymentResult('Mail worker',is_array($health)&&(int)($health['time']??0)>time()-180&&($health['delivery_errors']??1)===0?'PASS':'FAIL','Schedule tools/auth_mail_worker.php every minute and monitor failures for this database.');
 $supported=str_contains(strtolower($version),'mariadb')?version_compare($version,'10.11.0','>='):version_compare($version,'8.4.0','>=');
 deploymentResult('Database version',$supported?'PASS':'FAIL','Detected '.$version.'. Use a maintained, patched database and rerun compatibility tests.');
 $present=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('auth_rate_limits','auth_remember_tokens','student_password_resets','auth_mail_queue')")->fetchColumn();
 $column=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='auth_version'")->fetchColumn();
 deploymentResult('Migration 037',$present===4&&$column===1?'PASS':'FAIL','Authentication requires the four current security tables and users.auth_version.');
 $legacyExceptions=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clearance_exceptions'")->fetchColumn();
 deploymentResult('Migration 038',$legacyExceptions===0?'PASS':'FAIL','Remove the retired clearance_exceptions table after confirming it is empty and saving its structure.');
 $documentChecklist=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('document_requirements','student_documents')")->fetchColumn();
 deploymentResult('Migration 039',$documentChecklist===0?'PASS':'FAIL','Registrar uses manual document checks and remarks; remove the two retired empty checklist tables after saving their structures.');
 $unsigned=(int)$db->query("SELECT COUNT(*) FROM clearance_stations cs JOIN clearances c ON c.id=cs.clearance_id JOIN academic_periods p ON p.id=c.academic_period_id LEFT JOIN clearance_station_signatures s ON s.clearance_station_id=cs.id WHERE cs.status='APPROVED' AND p.status<>'CLOSED' AND s.clearance_station_id IS NULL")->fetchColumn();
 deploymentResult('Active approval signatures',$unsigned===0?'PASS':'FAIL',$unsigned.' active approvals have no signature snapshot. Archived exceptions require a separate Registrar review.');
}catch(Throwable$error){deploymentResult('Database access','FAIL','Unable to complete database checks. Check private configuration and migrations.');}
deploymentResult('Web server and headers','MANUAL','Use supported patched Apache/Nginx, verify deny rules and SAMEORIGIN/frame-ancestors headers over HTTPS.');
deploymentResult('Capacity and phone checks','MANUAL','A 5,000-session host load test and Android/iPhone acceptance tests remain required.');
deploymentResult('Disaster recovery','MANUAL','Restore a private database backup together with uploaded files on the target platform.');
echo json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))>0?1:0);
