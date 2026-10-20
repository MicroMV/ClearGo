<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$f=json_decode(file_get_contents(__DIR__.'/sandbox-fixture.json'),true,512,JSON_THROW_ON_ERROR);if(getenv('DB_DATABASE')!==$f['database'])throw new RuntimeException('Isolated DB required');
require_once __DIR__.'/sandbox-app/config/database.php';require_once __DIR__.'/sandbox-app/clearance/office_workflow.php';$db=getDatabaseConnection();$q=$db->prepare('SELECT id FROM clearances WHERE student_id=? AND academic_period_id=?');$q->execute([$f['student_id'],$f['period_id']]);$cid=(int)$q->fetchColumn();
$task=$argv[1]??'setup';
if($task==='setup'){
 $db->prepare("UPDATE academic_periods SET clearance_status='OPEN' WHERE id=?")->execute([$f['period_id']]);$db->prepare("UPDATE clearances SET status='IN_PROGRESS',completed_at=NULL WHERE id=?")->execute([$cid]);
 $db->prepare("UPDATE clearance_stations cs JOIN stations s ON s.id=cs.station_id SET cs.status=CASE WHEN s.code IN ('LIBRARY','CLINIC') THEN 'PENDING' WHEN s.code='SASO' THEN 'LOCKED' ELSE 'APPROVED' END WHERE cs.clearance_id=?")->execute([$cid]);
 $db->prepare("INSERT INTO library_cards(student_id,status)VALUES(?,'VERIFIED')ON DUPLICATE KEY UPDATE status='VERIFIED'")->execute([$f['student_id']]);$db->prepare("UPDATE medical_requirements SET status='VERIFIED' WHERE student_id=? AND academic_period_id=?")->execute([$f['student_id'],$f['period_id']]);echo "ready\n";
}elseif($task==='worker'){
 $station=$argv[2];$start=(float)$argv[3];while(microtime(true)<$start)usleep(500);
 $role=$station==='LIBRARY'?'LIBRARY_STAFF':'CLINIC_STAFF';try{approveOfficeClearance($db,$station,$cid,(int)$f['accounts'][$role]['id']);echo json_encode(['station'=>$station,'approved'=>true]);}catch(Throwable$e){echo json_encode(['station'=>$station,'approved'=>false,'error'=>$e->getMessage()]);}
}elseif($task==='read'){$q=$db->prepare("SELECT s.code,cs.status FROM clearance_stations cs JOIN stations s ON s.id=cs.station_id WHERE cs.clearance_id=? AND s.code IN ('LIBRARY','CLINIC','SASO')");$q->execute([$cid]);echo json_encode($q->fetchAll(PDO::FETCH_KEY_PAIR));}
