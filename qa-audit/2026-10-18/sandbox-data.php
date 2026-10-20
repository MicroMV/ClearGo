<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$fixture=json_decode(file_get_contents(__DIR__.'/sandbox-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$name=$fixture['database'];
if(!preg_match('/\Acleargo_qa_20261018_[a-f0-9]{8}\z/',$name)){throw new RuntimeException('Unsafe fixture database');}
$host=getenv('DB_HOST')?:'localhost';$port=getenv('DB_PORT')?:'3306';$username=getenv('DB_USERNAME')?:'root';$password=getenv('DB_PASSWORD');
$db=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$username,$password===false?'':$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$task=$argv[1]??'snapshot';
if($task==='snapshot'){
    require_once __DIR__.'/sandbox-app/registrar/semester_functions.php';
    $db->prepare('UPDATE bookstore_fees SET academic_period_id=?')->execute([$fixture['period_id']]);
    $number=0;
    foreach($db->query("SELECT id,code FROM organizations WHERE status='ACTIVE'")->fetchAll() as $org){
        if($org['code']!==$fixture['organization']['code'])foreach(['DEAN'=>'DEAN','ORG_STAFF'=>'ORGANIZATION']as $role=>$station){
            $id=900100+(++$number);$email='qa.'.strtolower($role).'.'.strtolower($org['code']).'@thelewiscollege.edu.ph';
            $db->prepare("INSERT IGNORE INTO users(id,email,password_hash,role,status) VALUES (?,?,?,?,'ACTIVE')")->execute([$id,$email,password_hash('QA-only-2026!Test',PASSWORD_DEFAULT),$role]);
            $db->prepare("INSERT IGNORE INTO staff_assignments (user_id,station_id,organization_id,status) SELECT ?,id,?,'ACTIVE' FROM stations WHERE code=?")->execute([$id,$org['id'],$station]);
        }
        for($year=1;$year<=4;$year++){
            $id=901000+(int)$org['id']*10+$year;$email='qa.student.'.strtolower($org['code']).'.'.$year.'@thelewiscollege.edu.ph';
            $db->prepare("INSERT IGNORE INTO users(id,email,password_hash,role,status) VALUES (?,?,?,'STUDENT','ACTIVE')")->execute([$id,$email,password_hash('QA-only-2026!Test',PASSWORD_DEFAULT)]);
            $db->prepare("INSERT IGNORE INTO students (user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,?,'QA',?,'BSCS',?,'QA-ONLY',?,'ACTIVE')")->execute([$id,'QA-'.$org['code'].'-'.$year,'Synthetic '.$org['code'].' Year '.$year,$year,$org['id']]);
            $student=$db->prepare('SELECT id FROM students WHERE user_id=?');$student->execute([$id]);
            $db->prepare("INSERT IGNORE INTO semester_enrollments (academic_period_id,student_id,status,enrolled_by) VALUES (?,?,'ENROLLED',?)")->execute([$fixture['period_id'],$student->fetchColumn(),$fixture['accounts']['REGISTRAR']['id']]);
        }
    }
    $db->exec("INSERT IGNORE INTO academic_periods (school_year,semester,status,clearance_status) VALUES ('2097-2098','2ND','CLOSED','CLOSED')");
    prepareSemesterClearanceRecords($db,(int)$fixture['period_id']);
    $rows=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$rows[$table]=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();
    file_put_contents(__DIR__.'/sandbox-seed.json',json_encode($rows,JSON_PRETTY_PRINT));
    echo 'Synthetic baseline saved; '.count($rows)." tables, no real student records.\n";
}elseif($task==='reset'){
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach(json_decode(file_get_contents(__DIR__.'/sandbox-seed.json'),true,512,JSON_THROW_ON_ERROR) as $table=>$rows){
        if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$table))throw new RuntimeException('Unsafe table');
        $db->exec('TRUNCATE TABLE `'.$table.'`');
        foreach($rows as $row){$cols=array_keys($row);$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')')->execute(array_values($row));}
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');echo "Synthetic baseline restored.\n";
}elseif($task==='open'){
    $db->exec("UPDATE academic_periods SET clearance_status='OPEN',opened_at=NOW() WHERE status='ACTIVE';UPDATE office_period_readiness SET status='READY'");
    echo "Synthetic semester opened.\n";
}elseif($task==='import-year'){
    $db->exec("UPDATE academic_periods SET school_year='2026-2027',semester='1ST' WHERE status='ACTIVE'");echo "Synthetic year aligned with the existing import test fixture.\n";
}elseif($task==='summary'){
    $r=['database'=>$name,'period'=>$db->query('SELECT id,status,clearance_status FROM academic_periods')->fetchAll(),'workflow'=>$db->query('SELECT s.code,cs.status FROM clearance_stations cs JOIN stations s ON s.id=cs.station_id ORDER BY s.sort_order')->fetchAll(),'tables'=>array_keys(json_decode(file_get_contents(__DIR__.'/sandbox-schema.json'),true))];echo json_encode($r,JSON_PRETTY_PRINT)."\n";
}
