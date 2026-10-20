<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__,3).'/config/database.php';
$database=getDatabaseConnection();
$sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/028_cbe_registrar_initial_station_order.sql');
$sql=trim(preg_replace('/^--.*$/m','',$sql));
$set=strpos($sql,"\nSET ");$where=strpos($sql,"\nWHERE ");
if(!str_starts_with($sql,'UPDATE clearance_stations organization_record')||$set===false||$where===false){throw new RuntimeException('Unexpected repair SQL.');}
$select='SELECT clearance.id AS clearance_id,student.id AS student_id,organization_station.id AS station_id,
 organization_record.id AS organization_record_id,organization_record.status AS organization_status,
 organization_record.updated_at AS organization_updated_at,
 dean_record.id AS dean_record_id,dean_record.status AS dean_status,dean_record.updated_at AS dean_updated_at
 FROM '.substr($sql,strlen('UPDATE '),$set-strlen('UPDATE ')).rtrim(substr($sql,$where),';');
$apply=($argv[1]??'check')==='apply';
try{
 if($apply)$database->beginTransaction();
 $rows=$database->query($select.($apply?' FOR UPDATE':''))->fetchAll();
 $changed=0;
 if($apply&&$rows!==[]){
  if(file_put_contents(__DIR__.'/before-repair.json',json_encode($rows,JSON_PRETTY_PRINT))===false)throw new RuntimeException('Cannot save repair rollback evidence.');
  $changed=$database->exec($sql);
  if($changed!==count($rows)*2)throw new RuntimeException('Repair changed an unexpected number of station rows; rolling back.');
  $audit=$database->prepare("INSERT INTO audit_logs(user_id,action,station_id,student_id,description,old_status,new_status)VALUES(NULL,'CBE_INITIAL_ORDER_REPAIRED',?,?,'Migration 028 restored untouched CBE clearance to Organization/YBC first, then Dean.','LOCKED','PENDING')");
  foreach($rows as$row)$audit->execute([$row['station_id'],$row['student_id']]);
 }
 if($apply)$database->commit();
 $result=['mode'=>$apply?'apply':'check','eligible_untouched_cbe_clearances'=>count($rows),'station_rows_changed'=>$changed,'completed_and_archived_records_excluded'=>true,'existing_office_decisions_excluded'=>true,'remaining_eligible_clearances'=>$apply?(int)$database->query('SELECT COUNT(*) FROM ('.$select.') eligible')->fetchColumn():count($rows)];
 file_put_contents(__DIR__.($apply?'/repair-result.json':'/repair-preview.json'),json_encode($result,JSON_PRETTY_PRINT));
 echo json_encode($result,JSON_PRETTY_PRINT)."\n";
}catch(Throwable$exception){if($database->inTransaction())$database->rollBack();throw$exception;}
