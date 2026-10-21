<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../registrar/semester_functions.php';
$source=getenv('DB_DATABASE')?:'lewis_clearance';$temporary='cleargo_section_test_'.bin2hex(random_bytes(6));
if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$source))throw new RuntimeException('Unsafe schema.');
$db=getDatabaseConnection();$server=null;$clients=[];$checks=[];$log=tempnam(sys_get_temp_dir(),'section-check-');
function sectionCheck(bool $passed,string $message):void{global $checks;$checks[]=['name'=>$message,'passed'=>$passed];if(!$passed)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
function sectionHttp(CurlHandle $client,string $route,?array $post=null):array{global $base;curl_setopt($client,CURLOPT_URL,$base.$route);if($post===null)curl_setopt($client,CURLOPT_HTTPGET,true);else{curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,$post);} $body=curl_exec($client);if($body===false)throw new RuntimeException(curl_error($client));if(preg_match('/Fatal error|Uncaught |Warning:/',$body))throw new RuntimeException('PHP error in '.$route);return ['status'=>(int)curl_getinfo($client,CURLINFO_HTTP_CODE),'body'=>$body];}
function sectionLogin(string $email,string $password):array{global $clients,$base;$client=curl_init();$clients[]=$client;curl_setopt_array($client,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);$login=sectionHttp($client,'/login.php');if(!preg_match('/name="csrf_token" value="([^"]+)"/',$login['body'],$match))throw new RuntimeException('Missing login token.');$csrf=html_entity_decode($match[1]);sectionCheck(sectionHttp($client,'/auth/login_process.php',['csrf_token'=>$csrf,'email'=>$email,'password'=>$password])['status']===303,'Synthetic '.$email.' signs in.');require_once __DIR__.'/support/http_fixture.php';$csrf=signedInFixtureCsrf($client,$base);return [$client,$csrf];}
try{
 $db->exec("CREATE DATABASE `$temporary` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE `$temporary`");$db->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach($db->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$table))throw new RuntimeException('Unsafe table.');$db->exec($db->query("SHOW CREATE TABLE `$source`.`$table`")->fetch(PDO::FETCH_NUM)[1]);}
 foreach(['organizations','stations','requirements'] as $table)$db->exec("INSERT INTO `$table` SELECT * FROM `$source`.`$table`");$db->exec('SET FOREIGN_KEY_CHECKS=1');
 // Reconstruct the pre-migration shape only inside this disposable schema on repeat runs.
 if(!$db->query("SHOW COLUMNS FROM students LIKE 'section'")->fetch())$db->exec("ALTER TABLE students ADD COLUMN section VARCHAR(50) NOT NULL DEFAULT 'N/A' AFTER year_level");
 if(!$db->query("SHOW TABLES LIKE 'accounting_receipt_verifications'")->fetch())$db->exec(file_get_contents(__DIR__.'/../database/migrations/018_accounting_receipt_photo_verification.sql'));
 $db->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(?,?,'REGISTRAR','ACTIVE')")->execute(['section-registrar@thelewiscollege.edu.ph',password_hash('Section-Test-2026!',PASSWORD_DEFAULT)]);$registrar=(int)$db->lastInsertId();
 $db->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(?,?,'STUDENT','ACTIVE')")->execute(['section-student@thelewiscollege.edu.ph',password_hash('Section-Test-2026!',PASSWORD_DEFAULT)]);$studentUser=(int)$db->lastInsertId();
 $organization=(int)$db->query("SELECT id FROM organizations WHERE code='CCS'")->fetchColumn();
 $db->prepare("INSERT INTO students(user_id,student_number,first_name,middle_name,last_name,program,year_level,section,organization_id,status) VALUES(?,'SECTION-KEEP','Synthetic','Middle','Student','BSCS',2,'N/A',?,'ACTIVE')")->execute([$studentUser,$organization]);$student=(int)$db->lastInsertId();
 $before=$db->query('SELECT id,user_id,student_number,first_name,middle_name,last_name,program,year_level,organization_id,status,created_at,updated_at FROM students')->fetchAll();
 $db->exec(file_get_contents(__DIR__.'/../database/migrations/036_remove_student_section_and_retired_receipt_ai.sql'));
 sectionCheck(!$db->query("SHOW COLUMNS FROM students LIKE 'section'")->fetch(),'Migration removes the section column.');
 sectionCheck(!$db->query("SHOW TABLES LIKE 'accounting_receipt_verifications'")->fetch(),'Migration removes the retired receipt AI table.');
 sectionCheck($before===$db->query('SELECT * FROM students')->fetchAll(),'All remaining student fields and timestamps are preserved.');
 sectionCheck((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===2,'Existing accounts survive migration.');
 $period=createRegistrarSemester($db,$registrar,'2098-2099','1ST');
 $db->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES(?,?,'ENROLLED')")->execute([$period,$student]);prepareSemesterClearanceRecords($db,$period);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$number,$message);$address=stream_socket_get_name($socket,false);fclose($socket);$base='http://'.$address;
 $environment=getenv();$environment['DB_DATABASE']=$temporary;unset($environment['APP_BASE_URL']);
 $server=proc_open([PHP_BINARY,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);if(!is_resource($server))throw new RuntimeException('Cannot start disposable server.');fclose($pipes[0]);
 for($i=0;$i<50;$i++){if($ready=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1))){fclose($ready);break;}usleep(100000);}
 [$staff,$csrf]=sectionLogin('section-registrar@thelewiscollege.edu.ph','Section-Test-2026!');
 sectionCheck(sectionHttp($staff,'/registrar/students.php')['status']===200,'Registrar roster loads without the section column.');
 $post=['csrf_token'=>$csrf,'action'=>'create','student_number'=>'SECTION-CREATE','first_name'=>'Added','middle_name'=>'Middle','last_name'=>'Student','email'=>'section-added@thelewiscollege.edu.ph','program'=>'BSCS','year_level'=>'1','organization_id'=>$organization];
 sectionCheck(sectionHttp($staff,'/registrar/student_action.php',$post)['status']===303,'Registrar student creation redirects normally.');
 $created=$db->query("SELECT * FROM students WHERE student_number='SECTION-CREATE'")->fetch();sectionCheck($created!==false&&$created['middle_name']==='Middle','Registrar creates a profile without a section placeholder.');
 $post['action']='update';$post['student_id']=$created['id'];$post['first_name']='Updated';$post['year_level']='3';$post['section']='legacy input ignored';sectionHttp($staff,'/registrar/student_action.php',$post);
 $updated=$db->query('SELECT * FROM students WHERE id='.(int)$created['id'])->fetch();sectionCheck($updated['first_name']==='Updated'&&(int)$updated['year_level']===3&&!array_key_exists('section',$updated),'Registrar updates a profile while ignoring legacy section input.');
 [$client]=sectionLogin('section-student@thelewiscollege.edu.ph','Section-Test-2026!');$dashboard=sectionHttp($client,'/student/dashboard.php');
 sectionCheck($dashboard['status']===200&&str_contains($dashboard['body'],'<dt>Year level</dt>')&&!str_contains($dashboard['body'],'Year &amp; section'),'Student account displays year level without a section field.');
 $db->prepare("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=?")->execute([$period]);
 sectionCheck(sectionHttp($client,'/student/clearance_preview.php?period='.$period)['status']===200,'Clearance preview loads without a section column.');
 $pdf=sectionHttp($client,'/student/clearance_pdf.php?period='.$period);sectionCheck($pdf['status']===200&&str_starts_with($pdf['body'],'%PDF-'),'Clearance PDF generates without a section column.');
 $import=proc_open([PHP_BINARY,__DIR__.'/student_import_mysql_test.php'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$importPipes,dirname(__DIR__),$environment);if(!is_resource($import))throw new RuntimeException('Cannot run isolated import check.');fclose($importPipes[0]);sectionCheck(proc_close($import)===0,'MySQL student import adds and updates profiles without section.');
 echo 'All '.count($checks).' student section removal checks passed.'.PHP_EOL;
}finally{
 if($db->inTransaction())$db->rollBack();foreach($clients as $client)curl_close($client);if(is_resource($server)){proc_terminate($server);proc_close($server);}if(is_file($log))unlink($log);
 if(!preg_match('/\Acleargo_section_test_[a-f0-9]{12}\z/',$temporary)||$source===$temporary)throw new RuntimeException('Unsafe cleanup.');$db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$temporary`");
 $directory=__DIR__.'/../.tmp/database-cleanup-2026-10-20';if(!is_dir($directory))mkdir($directory,0700,true);file_put_contents($directory.'/test-results.json',json_encode($checks,JSON_PRETTY_PRINT));
}
