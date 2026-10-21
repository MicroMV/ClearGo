<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../clearance/sanction_functions.php';
require_once __DIR__.'/../student-council/event_functions.php';
require_once __DIR__.'/../clearance/office_workflow.php';
require_once __DIR__.'/../auth/signature_functions.php';
require_once __DIR__.'/../clearance/bulk_clearance.php';
require_once __DIR__.'/../registrar/semester_functions.php';
$source=getenv('DB_DATABASE')?:'lewis_clearance';$temporary='cleargo_sanction_test_'.bin2hex(random_bytes(6));
if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$source))throw new RuntimeException('Unsafe database name.');
$db=getDatabaseConnection();$server=null;$clients=[];$files=[];$checks=0;$base='';$log=tempnam(sys_get_temp_dir(),'sanction-log-');
function sanctionCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS: $message\n";}
function sanctionReject(callable $fn,string $message):void{$rejected=false;try{$fn();}catch(RuntimeException){$rejected=true;}sanctionCheck($rejected,$message);}
function sanctionHttp(array $client,string $path,?array $post=null):array{
 global $base;$h=$client['handle'];curl_setopt($h,CURLOPT_URL,$base.$path);
 if($post===null)curl_setopt($h,CURLOPT_HTTPGET,true);else{curl_setopt($h,CURLOPT_POST,true);curl_setopt($h,CURLOPT_POSTFIELDS,$post);}
 $body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));
 if(preg_match('/Fatal error|Uncaught |Warning:/',$body))throw new RuntimeException(strip_tags($body));
 return ['status'=>(int)curl_getinfo($h,CURLINFO_HTTP_CODE),'body'=>$body,'redirect'=>(string)curl_getinfo($h,CURLINFO_REDIRECT_URL)];
}
function sanctionLogin(array $user):array{
 global $clients;$h=curl_init();curl_setopt_array($h,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);$client=['handle'=>$h];$clients[]=$client;
 $page=sanctionHttp($client,'/login.php');if(!preg_match('/name="csrf_token" value="([^"]+)"/',$page['body'],$m))throw new RuntimeException('Missing login token.');
 $client['csrf']=html_entity_decode($m[1]);$result=sanctionHttp($client,'/auth/login_process.php',['csrf_token'=>$client['csrf'],'email'=>$user['email'],'password'=>'Sanction-Test-2026!']);
 if($result['status']!==303)throw new RuntimeException('Synthetic login failed.');
 require_once __DIR__.'/support/http_fixture.php';global $base;$client['csrf']=signedInFixtureCsrf($h,$base);$clients[array_key_last($clients)]=$client;return $client;
}
function sanctionUser(PDO $db,string $role,string $name):array{
 $email=$name.'@thelewiscollege.edu.ph';$db->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(?,?,?,'ACTIVE')")->execute([$email,password_hash('Sanction-Test-2026!',PASSWORD_DEFAULT),$role]);return ['id'=>(int)$db->lastInsertId(),'role'=>$role,'email'=>$email];
}
function sanctionSnapshot(string $name,string $body):void{$dir=__DIR__.'/../.tmp/attendance-sanctions-check';if(!is_dir($dir))mkdir($dir,0700,true);file_put_contents($dir.'/'.$name,$body);}
try{
 $db->exec("CREATE DATABASE `$temporary` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE `$temporary`");$db->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach($db->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$table))throw new RuntimeException('Unsafe table.');$db->exec($db->query("SHOW CREATE TABLE `$source`.`$table`")->fetch(PDO::FETCH_NUM)[1]);}
 foreach(['organizations','stations','requirements'] as $table)$db->exec("INSERT INTO `$table` SELECT * FROM `$source`.`$table`");$db->exec('SET FOREIGN_KEY_CHECKS=1');
 $orgs=$db->query('SELECT code,id FROM organizations')->fetchAll(PDO::FETCH_KEY_PAIR);$stations=$db->query('SELECT code,id FROM stations')->fetchAll(PDO::FETCH_KEY_PAIR);
 $db->exec("INSERT INTO academic_periods(school_year,semester,status,clearance_status) VALUES('2098-2099','1ST','ACTIVE','PREPARING')");$period=(int)$db->lastInsertId();
 $db->exec("INSERT INTO office_period_readiness(academic_period_id,station_id,status) SELECT $period,id,'NOT_READY' FROM stations WHERE status='ACTIVE'");
 $org=sanctionUser($db,'ORG_STAFF','sanction-org');$otherOrg=sanctionUser($db,'ORG_STAFF','sanction-other-org');$council=sanctionUser($db,'STUDENT_COUNCIL_STAFF','sanction-council');$dean=sanctionUser($db,'DEAN','sanction-dean');
 foreach ([$org,$otherOrg,$council,$dean] as $signer) saveStaffSignature($db,$signer,'Synthetic Signer','Office Signatory',json_encode([[[0.1,0.4],[0.3,0.6],[0.5,0.3]]]));
 foreach([[$org,$orgs['CCS']],[$otherOrg,$orgs['CBE']]] as [$staff,$organization])$db->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) VALUES(?,?,?,'ACTIVE')")->execute([$staff['id'],$stations['ORGANIZATION'],$organization]);
 $db->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) VALUES(?,?,?,'ACTIVE')")->execute([$dean['id'],$stations['DEAN'],$orgs['CCS']]);
 $students=[];
 foreach(['present','absent','other'] as $name){$user=sanctionUser($db,'STUDENT','sanction-'.$name);$organization=$name==='other'?$orgs['CBE']:$orgs['CCS'];$db->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES(?,?,?,'Student','BSCS',1,?,'ACTIVE')")->execute([$user['id'],'SANCTION-'.$name,ucfirst($name),$organization]);$user['student_id']=(int)$db->lastInsertId();$db->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status) VALUES(?,?,'ENROLLED')")->execute([$period,$user['student_id']]);$students[$name]=$user;}
 $absent=$students['absent'];
 $event1=createOrganizationEvent($db,$org['id'],(int)$orgs['CCS'],'Classroom activity one','',date('Y-m-d'),null,null);
 $event2=createOrganizationEvent($db,$org['id'],(int)$orgs['CCS'],'Classroom activity two','',date('Y-m-d'),null,null);
 createStudentCouncilEvent($db,$council['id'],'Institutional activity',date('Y-m-d'));$councilEvent=(int)$db->query("SELECT id FROM events WHERE station_id=".$stations['STUDENT_COUNCIL'])->fetchColumn();
 sanctionReject(fn()=>saveEventSanctionPolicy($db,$otherOrg,$event1,'Clean a classroom','2',$otherOrg['id']),'Another organization cannot configure this event sanction.');
 sanctionReject(fn()=>saveEventSanctionPolicy($db,$org,$event1,'Clean a classroom','2',$council['id']),'An organization cannot assign a Student Council account as its monitor.');
 sanctionReject(fn()=>saveEventSanctionPolicy($db,$org,$event1,'Clean a classroom','0',$org['id']),'Required service hours must be positive.');
 saveEventSanctionPolicy($db,$org,$event1,'Clean assigned classroom','2',$org['id']);saveEventSanctionPolicy($db,$org,$event2,'Assist with campus cleanup','2',$org['id']);saveEventSanctionPolicy($db,$council,$councilEvent,'Council community service','2',$council['id']);
 $qr1=openEventQr($db,$org['id'],(int)$orgs['CCS'],$event1,10);$qr2=openEventQr($db,$org['id'],(int)$orgs['CCS'],$event2,10);$qrCouncil=openStudentCouncilEventQr($db,$council['id'],$councilEvent,10);
 $listener=stream_socket_server('tcp://127.0.0.1:0',$error,$errorText);$address=stream_socket_get_name($listener,false);fclose($listener);$base='http://'.$address;
 $environment=getenv();$environment['DB_DATABASE']=$temporary;unset($environment['APP_BASE_URL']);
 $server=proc_open([PHP_BINARY,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);if(!is_resource($server))throw new RuntimeException('Cannot start test server.');fclose($pipes[0]);
 for($i=0;$i<50;$i++){ $ready=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1));if($ready){fclose($ready);break;}usleep(100000); }
 $presentClient=sanctionLogin($students['present']);$absentClient=sanctionLogin($absent);$otherStudentClient=sanctionLogin($students['other']);$orgClient=sanctionLogin($org);$otherOrgClient=sanctionLogin($otherOrg);$councilClient=sanctionLogin($council);$deanClient=sanctionLogin($dean);
 $policyEvent=createOrganizationEvent($db,$org['id'],(int)$orgs['CCS'],'Policy form setup','',date('Y-m-d'),null,null);
 sanctionSnapshot('policy.html',sanctionHttp($orgClient,'/organization/sanction_submissions.php?event='.$policyEvent)['body']);
 $policyPost=['csrf_token'=>'invalid','action'=>'policy','event_id'=>$policyEvent,'task'=>'Monitor assigned classroom cleanup','hours'=>'2','monitor_user_id'=>$org['id']];
 sanctionHttp($orgClient,'/clearance/sanction_action.php',$policyPost);
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===0,'Invalid CSRF cannot configure an absence sanction.');
 $policyPost['csrf_token']=$orgClient['csrf'];$policyResponse=sanctionHttp($orgClient,'/clearance/sanction_action.php',$policyPost);
 sanctionCheck(str_contains($policyResponse['redirect'],'/organization/sanction_submissions.php?event='.$policyEvent),'Saving a sanction returns to its selected event on the sanction submissions page.');
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===1,'The staff form saves the required work, hours and assigned monitor.');
 sanctionSnapshot('policy-saved.html',sanctionHttp($orgClient,'/organization/sanction_submissions.php?event='.$policyEvent)['body']);
 $editPage=sanctionHttp($orgClient,'/organization/sanction_submissions.php?event='.$policyEvent.'&edit_policy=1');
 sanctionCheck($editPage['status']===200&&str_contains($editPage['body'],'value="policy_update"')&&str_contains($editPage['body'],'Monitor assigned classroom cleanup'),'Edit opens a prefilled sanction policy form.');
 sanctionSnapshot('policy-edit.html',$editPage['body']);
 $editPost=$policyPost;$editPost['action']='policy_update';$editPost['task']='Updated classroom task';$editPost['hours']='3.5';
 $invalidEdit=$editPost;$invalidEdit['csrf_token']='invalid';sanctionHttp($orgClient,'/clearance/sanction_action.php',$invalidEdit);
 sanctionCheck($db->query('SELECT task FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===$policyPost['task'],'Invalid CSRF cannot edit a sanction policy.');
 sanctionHttp($otherOrgClient,'/clearance/sanction_action.php',array_replace($editPost,['csrf_token'=>$otherOrgClient['csrf']]));
 sanctionCheck($db->query('SELECT task FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===$policyPost['task'],'Another organization cannot edit this policy.');
 sanctionHttp($orgClient,'/clearance/sanction_action.php',$editPost);
 sanctionCheck($db->query('SELECT task FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()==='Updated classroom task'&&(float)$db->query('SELECT hours FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===3.5,'An office can edit its saved sanction before marking Ready.');
 sanctionReject(fn()=>saveEventSanctionPolicy($db,$org,$policyEvent,'Invalid monitor','2',$council['id'],true),'Policy edits still reject monitors from another office.');
 $deletePost=['csrf_token'=>$orgClient['csrf'],'action'=>'policy_delete','event_id'=>$policyEvent];
 sanctionHttp($orgClient,'/clearance/sanction_action.php',array_replace($deletePost,['csrf_token'=>'invalid']));
 sanctionReject(fn()=>deleteEventSanctionPolicy($db,$otherOrg,$policyEvent),'Another organization cannot delete the saved policy.');
 sanctionReject(fn()=>deleteEventSanctionPolicy($db,$council,$policyEvent),'Student Council cannot delete an organization policy.');
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===1,'Rejected deletion attempts leave the saved policy intact.');
 sanctionHttp($orgClient,'/clearance/sanction_action.php',$deletePost);
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===0,'The owner can delete the saved policy before marking Ready.');
 sanctionHttp($orgClient,'/clearance/sanction_action.php',$policyPost);
 // Council has the same edit/delete behavior and retains its required two-hour policy afterward.
 saveEventSanctionPolicy($db,$council,$councilEvent,'Updated Council task','3',$council['id'],true);
 deleteEventSanctionPolicy($db,$council,$councilEvent);
 saveEventSanctionPolicy($db,$council,$councilEvent,'Council community service','2',$council['id']);
 $newEvent=createOrganizationEvent($db,$org['id'],(int)$orgs['CCS'],'Later absence','',date('Y-m-d'),null,null);
 saveEventSanctionPolicy($db,$org,$newEvent,'Additional community service','2',$org['id']);
 markOfficeReady($db,$org['id'],'ORGANIZATION','');markOfficeReady($db,$council['id'],'STUDENT_COUNCIL','');
 foreach([[$org,$policyEvent],[$council,$councilEvent]] as [$staff,$eventId]){
     sanctionReject(fn()=>saveEventSanctionPolicy($db,$staff,$eventId,'Blocked edit','4',$staff['id'],true),'Ready offices cannot edit existing sanction policies.');
     sanctionReject(fn()=>deleteEventSanctionPolicy($db,$staff,$eventId),'Ready offices cannot delete existing sanction policies.');
 }
 $lockedPage=sanctionHttp($orgClient,'/organization/sanction_submissions.php?event='.$policyEvent.'&edit_policy=1');
 sanctionCheck(str_contains($lockedPage['body'],'Office is Ready')&&!str_contains($lockedPage['body'],'value="policy_update"')&&!str_contains($lockedPage['body'],'value="policy_delete"'),'Ready hides editing and deletion, even with an old Edit link.');
 sanctionSnapshot('policy-locked.html',$lockedPage['body']);
 sanctionHttp($orgClient,'/clearance/sanction_action.php',$editPost);sanctionHttp($orgClient,'/clearance/sanction_action.php',$deletePost);
 sanctionCheck($db->query('SELECT task FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()===$policyPost['task'],'Stale Edit and Delete POST requests cannot bypass Ready.');
 markOfficeNotReady($db,$org['id'],'ORGANIZATION');
 saveEventSanctionPolicy($db,$org,$policyEvent,'Editable after Undo Ready','2',$org['id'],true);
 sanctionCheck($db->query('SELECT task FROM event_sanction_policies WHERE event_id='.$policyEvent)->fetchColumn()==='Editable after Undo Ready','Undo Ready restores policy editing during preparation.');
 deleteEventSanctionPolicy($db,$org,$policyEvent);markOfficeReady($db,$org['id'],'ORGANIZATION','');
 sanctionReject(fn()=>saveEventSanctionPolicy($db,$org,$policyEvent,'Blocked new policy','2',$org['id']),'Ready offices cannot add a new sanction policy either.');
 sanctionCheck((int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('EVENT_SANCTION_UPDATED','EVENT_SANCTION_DELETED')")->fetchColumn()>=4,'Policy edits and deletions are recorded in audit history.');
 $db->exec("UPDATE office_period_readiness SET status='READY' WHERE academic_period_id=$period");
 $registrar=sanctionUser($db,'REGISTRAR','sanction-registrar');
 sanctionCheck(openRegistrarClearance($db,$registrar['id'])===count($students),'The Registrar opens clearance after every office is Ready.');
 foreach([[$orgClient,'ORGANIZATION','/organization/dashboard.php'],[$councilClient,'STUDENT_COUNCIL','/student-council/dashboard.php'],[$deanClient,'DEAN','/dean/dashboard.php']] as [$client,$stationCode,$dashboard]) {
     $response=sanctionHttp($client,'/clearance/readiness_action.php',['csrf_token'=>$client['csrf'],'action'=>'unready']);
     sanctionCheck($response['status']===303&&officeReadiness($db,$stationCode)['readiness_status']==='READY','An old Undo Ready request cannot change '.$stationCode.' after the Registrar opens clearance.');
     $dashboardPage=sanctionHttp($client,$dashboard);
     sanctionCheck($dashboardPage['status']===200&&!str_contains($dashboardPage['body'],'value="unready"'),'Undo Ready is hidden on '.$stationCode.' after clearance opens.');
 }
 // A scheduled end limits QR duration even when a longer duration is requested.
 $db->exec("UPDATE events SET event_date=DATE(DATE_ADD(NOW(),INTERVAL 1 MINUTE)),end_time=TIME(DATE_ADD(NOW(),INTERVAL 1 MINUTE)) WHERE id=$policyEvent");
 openEventQr($db,$org['id'],(int)$orgs['CCS'],$policyEvent,10);
 sanctionCheck((int)$db->query("SELECT TIMESTAMPDIFF(SECOND,check_in_opens_at,check_in_closes_at) FROM events WHERE id=$policyEvent")->fetchColumn()<=60,'A QR window cannot extend beyond the scheduled event end.');
 $db->exec("UPDATE events SET status='INACTIVE' WHERE id=$policyEvent");
 $scan=sanctionHttp($presentClient,'/student/attendance_scanner_action.php',['csrf_token'=>$presentClient['csrf'],'token'=>$qr1['token']]);
 sanctionCheck($scan['status']===303 && (int)$db->query("SELECT COUNT(*) FROM event_attendance WHERE event_id=$event1 AND status='PRESENT'")->fetchColumn()===1,'A valid QR scan records Present immediately without another confirmation tap.');
 $confirmation=sanctionHttp($presentClient,'/student/attendance_checkin.php');sanctionCheck(str_contains($confirmation['body'],'Attendance Confirmed')&&!str_contains($confirmation['body'],'Confirm My Attendance'),'The scan result displays attendance confirmation.');
 sanctionHttp($presentClient,'/student/attendance_scanner_action.php',['csrf_token'=>$presentClient['csrf'],'token'=>$qr1['token']]);
 sanctionCheck((int)$db->query("SELECT COUNT(*) FROM event_attendance WHERE event_id=$event1 AND status='PRESENT'")->fetchColumn()===1,'Duplicate scans do not duplicate attendance.');
 sanctionHttp($otherStudentClient,'/student/attendance_scanner_action.php',['csrf_token'=>$otherStudentClient['csrf'],'token'=>$qr1['token']]);sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_attendance WHERE student_id='.$students['other']['student_id'])->fetchColumn()===0,'Students outside the organization cannot scan its event.');
 sanctionHttp($absentClient,'/student/attendance_scanner_action.php',['csrf_token'=>'invalid','token'=>$qr1['token']]);sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_attendance WHERE student_id='.$absent['student_id'])->fetchColumn()===0,'Invalid CSRF cannot record QR attendance.');
 recordQrAttendance($db,$qr2['token'],$students['present']['id']);recordQrAttendance($db,$qrCouncil['token'],$students['present']['id']);
 $db->exec('UPDATE events SET check_in_closes_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
 sanctionReject(fn()=>recordQrAttendance($db,$qr1['token'],$absent['id']),'Late QR scans cannot mark an absent student present.');
 $poll=sanctionHttp($absentClient,'/auth/notifications.php');
 sanctionCheck($poll['status']===200&&str_contains($poll['body'],'Attendance sanction assigned'),'The existing notification poll assigns expired-event sanctions without waiting for a page reload.');
 syncExpiredEventSanctions($db,$period);syncExpiredEventSanctions($db,$period);
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanctions WHERE student_id='.$absent['student_id'])->fetchColumn()===3,'Ended events assign exactly one sanction per absence, even when synchronization repeats.');
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanctions WHERE student_id='.$students['present']['student_id'])->fetchColumn()===0,'Students who attended receive no sanctions.');
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM event_sanctions x JOIN events e ON e.id=x.event_id WHERE e.organization_id IS NOT NULL AND x.student_id='.$students['other']['student_id'])->fetchColumn()===0,'Organization sanctions do not affect another organization students.');
 sanctionReject(fn()=>openEventQr($db,$org['id'],(int)$orgs['CCS'],$event1,10),'Finalized attendance cannot be reopened after sanctions are assigned.');
 sanctionReject(fn()=>deleteOrganizationEvent($db,$org['id'],(int)$orgs['CCS'],$event1),'Finalized events cannot be deleted to bypass sanctions.');
 $groups=studentSanctionGroups($db,$absent['student_id'],$period);
 sanctionCheck(count($groups['ORGANIZATION']['items'])===2 && $groups['ORGANIZATION']['hours']===4.0 && count($groups['STUDENT_COUNCIL']['items'])===1,'Outstanding hours are combined by organization, with a separate Student Council group.');
 sanctionCheck(!organizationEligibility($db,(int)$orgs['CCS'],$absent['student_id'])['eligible'],'Unverified sanctions block organization clearance.');
 $application=startStudentClearance($db,$absent['id']);$clearanceId=(int)$application['clearance']['id'];
 // Synthetic prerequisite approval isolates the attendance checks from Dean requirements.
 $db->exec("UPDATE clearance_stations cs JOIN stations st ON st.id=cs.station_id SET cs.status=CASE WHEN st.code='DEAN' THEN 'APPROVED' ELSE 'PENDING' END WHERE cs.clearance_id=$clearanceId AND st.code IN ('DEAN','ORGANIZATION')");
 sanctionReject(fn()=>approveOrganizationStudent($db,$org['id'],(int)$orgs['CCS'],$absent['student_id']),'Manual organization clearance rejects unresolved sanctions.');
 sanctionCheck(!in_array($clearanceId,bulkReadyClearanceIds($db,'ORGANIZATION',(int)$orgs['CCS']),true),'Bulk organization clearance excludes unresolved sanctions.');
 sanctionSnapshot('upload.html',sanctionHttp($absentClient,'/student/dashboard.php')['body']);
 $first=renderStaffSignature(json_encode(['aspect_ratio'=>3,'strokes'=>[[[.1,.2],[.3,.7],[.8,.2]]]]))['data'];$second=renderStaffSignature(json_encode(['aspect_ratio'=>3,'strokes'=>[[[.1,.7],[.5,.2],[.8,.6]]]]))['data'];$replacement=renderStaffSignature(json_encode(['aspect_ratio'=>3,'strokes'=>[[[.2,.2],[.6,.8],[.9,.1]]]]))['data'];
 // A valid PNG ancillary chunk makes a realistic-size upload larger than MySQL's 1 MB packet limit.
 $pngMetadata="Comment\0".str_repeat('Synthetic attendance test. ',50000);
 $first=substr($first,0,-12).pack('N',strlen($pngMetadata)).'tEXt'.$pngMetadata.pack('N',crc32('tEXt'.$pngMetadata)).substr($first,-12);
 foreach([$first,$second,$replacement] as $data){$file=tempnam(sys_get_temp_dir(),'sanction-photo-');file_put_contents($file,$data);$files[]=$file;}
 $post=['csrf_token'=>$absentClient['csrf'],'owner_code'=>'ORGANIZATION','before_photo'=>new CURLFile($files[0],'image/png','before.png'),'after_photo'=>new CURLFile($files[1],'image/png','after.png')];
 $one=$post;unset($one['after_photo']);sanctionHttp($absentClient,'/student/sanction_action.php',$one);
 $extra=$post;$extra['extra_photo']=new CURLFile($files[2],'image/png','extra.png');sanctionHttp($absentClient,'/student/sanction_action.php',$extra);
 $same=$post;$same['after_photo']=$same['before_photo'];sanctionHttp($absentClient,'/student/sanction_action.php',$same);
 $invalid=$post;$invalid['csrf_token']='invalid';sanctionHttp($absentClient,'/student/sanction_action.php',$invalid);
 $badFile=tempnam(sys_get_temp_dir(),'sanction-invalid-');file_put_contents($badFile,'not an image');$files[]=$badFile;
 $invalidImage=$post;$invalidImage['before_photo']=new CURLFile($badFile,'image/png','pretend.png');sanctionHttp($absentClient,'/student/sanction_action.php',$invalidImage);
 $largeFile=tempnam(sys_get_temp_dir(),'sanction-large-');file_put_contents($largeFile,str_repeat('x',2*1024*1024+1));$files[]=$largeFile;
 $largePost=$post;$largePost['before_photo']=new CURLFile($largeFile,'image/png','large.png');sanctionHttp($absentClient,'/student/sanction_action.php',$largePost);
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM sanction_submissions')->fetchColumn()===0,'Missing, extra, identical, fake-image, oversized and invalid-CSRF photos are rejected without creating submissions.');
 $upload=sanctionHttp($absentClient,'/student/sanction_action.php',$post);$submission=(int)$db->query('SELECT MAX(id) FROM sanction_submissions')->fetchColumn();
 sanctionCheck($upload['status']===303&&$submission>0&&(int)$db->query('SELECT COUNT(*) FROM sanction_submission_items WHERE submission_id='.$submission)->fetchColumn()===2,'Exactly two photos cover both outstanding organization sanctions in one submission.');
 $originalNames=$db->query('SELECT before_stored_name,after_stored_name FROM sanction_submissions WHERE id='.$submission)->fetch();
 sanctionCheck(filesize(sanctionPhotoPath($originalNames['before_stored_name']))>1024*1024,'A valid photo over 1 MB saves without depending on the database packet limit.');
 $savedCount=count(glob(dirname(sanctionPhotoPath($originalNames['before_stored_name'])).'/*.{png,jpg,webp}',GLOB_BRACE));
 sanctionHttp($absentClient,'/student/sanction_action.php',$post);sanctionCheck((int)$db->query('SELECT COUNT(*) FROM sanction_submissions')->fetchColumn()===1,'A pending submission cannot be duplicated or silently replaced.');
 sanctionCheck(count(glob(dirname(sanctionPhotoPath($originalNames['before_stored_name'])).'/*.{png,jpg,webp}',GLOB_BRACE))===$savedCount,'A rejected duplicate upload cleans up its newly saved photos.');
 sanctionCheck(!organizationEligibility($db,(int)$orgs['CCS'],$absent['student_id'])['eligible'],'Uploading photos alone does not clear a sanction.');
 foreach([[$absentClient,200],[$orgClient,200],[$otherOrgClient,404],[$otherStudentClient,404],[$councilClient,404],[$deanClient,403]] as [$client,$status])sanctionCheck(sanctionHttp($client,'/clearance/sanction_image.php?id='.$submission.'&photo=before')['status']===$status,'Private sanction photos enforce student and event-office ownership (HTTP '.$status.').');
 sanctionCheck(sanctionHttp($absentClient,'/clearance/sanction_image.php?id='.$submission.'&photo=before')['body']===$first,'The protected handler returns the submitted Before image.');
 openEventQr($db,$org['id'],(int)$orgs['CCS'],$newEvent,10);$db->exec("UPDATE events SET check_in_closes_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=$newEvent");syncExpiredEventSanctions($db,$period);
 sanctionReject(fn()=>reviewSanctionProof($db,$council,$submission,true,'',true),'Student Council cannot verify organization sanctions.');sanctionReject(fn()=>reviewSanctionProof($db,$otherOrg,$submission,true,'',true),'Another organization cannot review these photos.');sanctionReject(fn()=>reviewSanctionProof($db,$org,$submission,true,'',false),'Approval requires confirmation of the work and required hours.');
 reviewSanctionProof($db,$org,$submission,true,'Work and hours confirmed with the monitor.',true);
 sanctionCheck((int)$db->query("SELECT COUNT(*) FROM event_sanctions WHERE student_id=".$absent['student_id']." AND status='RESOLVED'")->fetchColumn()===2,'Verification resolves only the sanctions captured in that submitted photo pair.');
 sanctionCheck((int)$db->query("SELECT COUNT(*) FROM event_attendance WHERE student_id=".$absent['student_id']." AND status='ABSENT' AND sanction_verified_at IS NOT NULL")->fetchColumn()===2,'The attendance record remains Absent while completed sanctions are recorded separately.');
 sanctionCheck(!organizationEligibility($db,(int)$orgs['CCS'],$absent['student_id'])['eligible'],'A new absence after submission still blocks organization clearance.');
 $councilPost=$post;$councilPost['owner_code']='STUDENT_COUNCIL';sanctionHttp($absentClient,'/student/sanction_action.php',$councilPost);$councilSubmission=(int)$db->query('SELECT MAX(id) FROM sanction_submissions')->fetchColumn();
 sanctionCheck((int)$db->query('SELECT COUNT(*) FROM sanction_submission_items WHERE submission_id='.$councilSubmission)->fetchColumn()===1,'Student Council uses a separate photo pair covering only its own sanctions.');
 reviewSanctionProof($db,$council,$councilSubmission,true,'Confirmed.',true);
 $councilSql=studentCouncilAttendanceEligibilitySql('s','p');$q=$db->prepare("SELECT ($councilSql) FROM students s JOIN academic_periods p ON p.id=? WHERE s.id=?");$q->execute([$period,$absent['student_id']]);sanctionCheck((int)$q->fetchColumn()===1,'Verified Council sanctions satisfy Council attendance eligibility.');
 sanctionHttp($absentClient,'/student/sanction_action.php',$post);$lastSubmission=(int)$db->query('SELECT MAX(id) FROM sanction_submissions')->fetchColumn();
 sanctionReject(fn()=>reviewSanctionProof($db,$org,$lastSubmission,false,'',false),'Returning a submission requires actionable feedback.');
 reviewSanctionProof($db,$org,$lastSubmission,false,'Replace the Before photo with a clearer image.',false);
 $returnedNames=$db->query('SELECT before_stored_name,after_stored_name FROM sanction_submissions WHERE id='.$lastSubmission)->fetch();
 sanctionSnapshot('returned.html',sanctionHttp($absentClient,'/student/dashboard.php')['body']);
 $post['before_photo']=new CURLFile($files[2],'image/png','replacement.png');sanctionHttp($absentClient,'/student/sanction_action.php',$post);
 $replacementName=$db->query('SELECT before_stored_name FROM sanction_submissions WHERE id='.$lastSubmission)->fetchColumn();
 sanctionCheck((int)$db->query('SELECT MAX(id) FROM sanction_submissions')->fetchColumn()===$lastSubmission&&file_get_contents(sanctionPhotoPath($replacementName))===$replacement,'Resubmission replaces the returned photo pair without orphaned storage or duplicate batches.');
 sanctionCheck(!is_file(sanctionPhotoPath($returnedNames['before_stored_name']))&&!is_file(sanctionPhotoPath($returnedNames['after_stored_name']))&&is_file(sanctionPhotoPath($originalNames['before_stored_name'])),'Successful replacement removes only the returned pair and retains verified submission photos.');
 $staffPage=sanctionHttp($orgClient,'/organization/sanction_submissions.php');$studentPage=sanctionHttp($absentClient,'/student/dashboard.php');
 sanctionCheck($staffPage['status']===200&&str_contains($staffPage['body'],'Verify sanctions')&&str_contains($staffPage['body'],'work_verified'),'The dedicated sanction submissions screen displays photo review and monitor verification.');
 $attendancePage=sanctionHttp($orgClient,'/organization/attendance.php?event='.$newEvent.'&attendance_status=ABSENT');
 sanctionCheck($attendancePage['status']===200&&!str_contains($attendancePage['body'],'Absence sanctions')&&str_contains($attendancePage['body'],'Attendance records')&&!str_contains($attendancePage['body'],'sanction-review-card'),'Event attendance contains records without sanction setup or photo review.');
 sanctionSnapshot('attendance.html',$attendancePage['body']);
 sanctionCheck(str_contains($staffPage['body'],'Absence sanctions')&&str_contains($councilSetup=sanctionHttp($councilClient,'/student-council/sanction_submissions.php')['body'],'Absence sanctions'),'Both office submissions pages include absence sanction setup.');
 $councilPage=sanctionHttp($councilClient,'/student-council/sanction_submissions.php?proof_status=APPROVED');
 sanctionCheck($councilPage['status']===200&&str_contains($councilPage['body'],'Institutional activity')&&!str_contains($councilPage['body'],'Later absence'),'Student Council submissions show only Council-owned sanctions.');
 $otherOrgPage=sanctionHttp($otherOrgClient,'/organization/sanction_submissions.php');
 sanctionCheck($otherOrgPage['status']===200&&!str_contains($otherOrgPage['body'],'sanction-review-card'),'Another organization cannot view this organization submission list.');
 foreach([[$absentClient,'/organization/sanction_submissions.php'],[$deanClient,'/student-council/sanction_submissions.php'],[$orgClient,'/student-council/sanction_submissions.php'],[$councilClient,'/organization/sanction_submissions.php']] as [$client,$path])sanctionCheck(sanctionHttp($client,$path)['status']===403,'Dedicated submission pages enforce the owning staff role.');
 foreach([[$orgClient,'/organization'],[$councilClient,'/student-council']] as [$client,$directory])foreach([$directory.'/dashboard.php',$directory.'/attendance.php','/auth/profile.php'] as $path){$page=sanctionHttp($client,$path);sanctionCheck($page['status']===200&&str_contains($page['body'],$directory.'/sanction_submissions.php'),'Submission sidebar link on '.$path.' (HTTP '.$page['status'].', '.$page['redirect'].').');}
 sanctionCheck($studentPage['status']===200&&str_contains($studentPage['body'],'awaiting office verification'),'The student sees outstanding hours and pending photo verification.');
 sanctionSnapshot('staff.html',$staffPage['body']);sanctionSnapshot('student.html',$studentPage['body']);sanctionSnapshot('before.png',$replacement);sanctionSnapshot('after.png',$second);
 $reviewPost=['csrf_token'=>$orgClient['csrf'],'submission_id'=>$lastSubmission,'action'=>'approve','work_verified'=>'1','remarks'=>'The monitor confirmed the completed service.'];
 $reviewResponse=sanctionHttp($orgClient,'/clearance/sanction_action.php',$reviewPost);
 sanctionCheck($reviewResponse['status']===303&&str_contains($reviewResponse['redirect'],'/organization/sanction_submissions.php'),'Review actions return to the dedicated sanction submissions page.');
 sanctionCheck(organizationEligibility($db,(int)$orgs['CCS'],$absent['student_id'])['eligible'],'Organization clearance becomes eligible only after every required event or sanction is satisfied.');
 sanctionCheck((int)$db->query("SELECT COUNT(*) FROM sanction_submissions WHERE status='APPROVED'")->fetchColumn()===3,'Organization and Council reviews keep distinct verified submission histories.');
 sanctionCheck(organizationStudent($db,(int)$orgs['CCS'],$absent['student_id'])['clearance_status']==='PENDING','Verifying sanctions does not automatically approve office clearance.');
 sanctionCheck(in_array($clearanceId,bulkReadyClearanceIds($db,'ORGANIZATION',(int)$orgs['CCS']),true),'Verified sanctions make the student available for bulk organization clearance.');
 approveOrganizationStudent($db,$org['id'],(int)$orgs['CCS'],$absent['student_id']);
 sanctionCheck(organizationStudent($db,(int)$orgs['CCS'],$absent['student_id'])['clearance_status']==='APPROVED','The organization can manually clear the student after sanction verification.');
 $bulkCouncil=approveAllReadyClearances($db,$council);
 sanctionCheck($bulkCouncil['approved']===1 && officeClearanceContext($db,'STUDENT_COUNCIL',$clearanceId)['station_status']==='APPROVED','Student Council can bulk clear a ready student with verified Council sanctions.');
 sanctionCheck(sanctionHttp($absentClient,'/clearance/sanction_action.php',$reviewPost)['status']===403,'Students cannot invoke staff sanction verification.');
 $db->exec("UPDATE academic_periods SET status='CLOSED',clearance_status='CLOSED' WHERE id=$period");
 sanctionReject(fn()=>reviewSanctionProof($db,$org,$lastSubmission,true,'',true),'Closed semesters reject sanction review.');
 echo "All $checks attendance and sanction checks passed.\n";
}finally{
 if($db->inTransaction())$db->rollBack();foreach($clients as $client)curl_close($client['handle']);if(is_resource($server)){proc_terminate($server);proc_close($server);}foreach(array_merge($files,[$log]) as $file)if(is_file($file))unlink($file);
 if(!preg_match('/\Acleargo_sanction_test_[a-f0-9]{12}\z/',$temporary)||$temporary===$source)throw new RuntimeException('Unsafe cleanup.');
 try {foreach($db->query("SELECT before_stored_name,after_stored_name FROM `$temporary`.sanction_submissions")->fetchAll(PDO::FETCH_NUM) as $names)removeSanctionPhotos($names);}catch(PDOException){}
 $db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$temporary`");
}
