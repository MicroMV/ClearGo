<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
require_once __DIR__.'/../config/database.php';
$db=getDatabaseConnection();$root=realpath(dirname(__DIR__));$options=getopt('',['archive-unreferenced-profiles','min-age:']);
$minimum=max(3600,(int)($options['min-age']??86400));$archive=isset($options['archive-unreferenced-profiles']);
$report=['unsigned_active_approvals'=>0,'unsigned_archived_approvals'=>0,'unreferenced_profiles'=>0,'archived_profiles'=>0,'recent_profiles_skipped'=>0];
$legacy=$db->query("SELECT cs.id AS clearance_station_id,c.id AS clearance_id,c.academic_period_id,p.status AS period_status,s.code AS station_code
 FROM clearance_stations cs JOIN clearances c ON c.id=cs.clearance_id JOIN academic_periods p ON p.id=c.academic_period_id
 JOIN stations s ON s.id=cs.station_id LEFT JOIN clearance_station_signatures signature ON signature.clearance_station_id=cs.id
 WHERE cs.status='APPROVED' AND signature.clearance_station_id IS NULL ORDER BY c.academic_period_id,cs.id")->fetchAll();
foreach($legacy as$row){$report[$row['period_status']==='CLOSED'?'unsigned_archived_approvals':'unsigned_active_approvals']++;}
$source=realpath($root.'/storage/private/profile-pictures');$saved=[];
if($archive){
 $lock=fopen($root.'/storage/private/profile-maintenance.lock','c');if($lock===false||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another profile maintenance task is running.');
 $base=$root.'/storage/backups/profile-retention';if(!is_dir($base)&&!mkdir($base,0700,true))throw new RuntimeException('Archive storage unavailable.');
 $base=realpath($base);if($base===false||!str_starts_with(str_replace('\\','/',$base),str_replace('\\','/',$root).'/storage/backups/'))throw new RuntimeException('Unsafe archive root.');
 $destination=$base.'/'.(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Ymd-His').'-'.bin2hex(random_bytes(4));mkdir($destination,0700);$destination=realpath($destination);
 if($destination===false||!str_starts_with(str_replace('\\','/',$destination),str_replace('\\','/',$base).'/'))throw new RuntimeException('Unsafe archive destination.');
 file_put_contents($destination.'/legacy-approval-review.json',json_encode($legacy,JSON_PRETTY_PRINT),LOCK_EX);
}
if($source!==false){
 if(!str_starts_with(str_replace('\\','/',$source),str_replace('\\','/',$root).'/storage/private/'))throw new RuntimeException('Unsafe profile root.');
 $referenced=array_fill_keys($db->query('SELECT stored_name FROM user_profile_pictures')->fetchAll(PDO::FETCH_COLUMN),true);
 foreach(new DirectoryIterator($source)as$file){
  $name=$file->getFilename();if(!$file->isFile()||!preg_match('/\A[a-f0-9]{40}\.(jpg|png|webp)\z/',$name)||isset($referenced[$name]))continue;
  $report['unreferenced_profiles']++;
  if(time()-$file->getMTime()<$minimum){$report['recent_profiles_skipped']++;continue;}
  if(!$archive)continue;
  $original=realpath($file->getPathname());
  if($original===false||dirname($original)!==$source)throw new RuntimeException('Unsafe profile target.');
  $target=$destination.DIRECTORY_SEPARATOR.$name;
  if(dirname($target)!==$destination||is_file($target))throw new RuntimeException('Unsafe profile archive target.');
  $db->beginTransaction();
  try{
   $check=$db->prepare('SELECT user_id FROM user_profile_pictures WHERE stored_name=? FOR UPDATE');$check->execute([$name]);
   if($check->fetchColumn()!==false){$db->commit();continue;}
   $entry=['stored_name'=>$name,'sha256'=>hash_file('sha256',$original),'status'=>'planned'];$saved[]=$entry;
   file_put_contents($destination.'/manifest.json',json_encode($saved,JSON_PRETTY_PRINT),LOCK_EX);
   if(!rename($original,$target))throw new RuntimeException('Unable to archive profile.');
   if(!hash_equals($entry['sha256'],hash_file('sha256',$target)))throw new RuntimeException('Profile archive verification failed.');
   $saved[array_key_last($saved)]['status']='archived';
   file_put_contents($destination.'/manifest.json',json_encode($saved,JSON_PRETTY_PRINT),LOCK_EX);
   $db->commit();$report['archived_profiles']++;
  }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw$error;}
 }
}
if($archive){$report['private_archive']=str_replace('\\','/',substr($destination,strlen($root)+1));flock($lock,LOCK_UN);fclose($lock);}
echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
