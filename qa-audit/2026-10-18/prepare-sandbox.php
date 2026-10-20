<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root=dirname(__DIR__,2);
require_once $root.'/config/database.php';
ini_set('mysqlnd.net_read_timeout','5');
$source=getDatabaseConnection();
$databaseName='cleargo_qa_20261018_'.bin2hex(random_bytes(4));
if (!preg_match('/\Acleargo_qa_20261018_[a-f0-9]{8}\z/',$databaseName)) { throw new RuntimeException('Invalid sandbox database name.'); }
$source->exec('CREATE DATABASE `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$host=getenv('DB_HOST')?:'localhost'; $port=getenv('DB_PORT')?:'3306'; $username=getenv('DB_USERNAME')?:'root'; $password=getenv('DB_PASSWORD');
$sandbox=new PDO("mysql:host=$host;port=$port;dbname=$databaseName;charset=utf8mb4",$username,$password===false?'':$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$sandbox->exec('SET FOREIGN_KEY_CHECKS=0');
$tables=$source->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$schema=[];
foreach($tables as $table) {
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/',$table)) { throw new RuntimeException('Invalid table name.'); }
    $ddl=$source->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    // Preserve definitions and constraints, but do not copy any real account or student data.
    $sandbox->exec($ddl); $schema[$table]=$ddl;
}
$referenceTables=['organizations','stations','requirements','document_requirements','bookstore_fees'];
$counts=[];
foreach($referenceTables as $table) {
    if (!in_array($table,$tables,true)) continue;
    $rows=$source->query('SELECT * FROM `'.$table.'`')->fetchAll();
    foreach($rows as $row) {
        // Any author references are replaced with the synthetic Registrar below.
        foreach($row as $key=>&$value) if(preg_match('/(?:created|updated|recorded|verified|approved|opened|closed|enrolled|paid|cleared)_by$/',$key)&&$value!==null) $value=900000;
        unset($value);
        $cols=array_keys($row); $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
        $sandbox->prepare($sql)->execute(array_values($row));
    }
    $counts[$table]=count($rows);
}
$roles=['REGISTRAR','DEAN','ORG_STAFF','SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','COMLAB_STAFF','ACCOUNTING_STAFF','STUDENT'];
$accounts=[]; $hash=password_hash('QA-only-2026!Test',PASSWORD_DEFAULT);
$insert=$sandbox->prepare('INSERT INTO users (id,email,password_hash,role,status) VALUES (?,?,?,?,?)');
foreach($roles as $index=>$role) {
    $id=900000+$index; $email='qa.'.strtolower($role).'@thelewiscollege.edu.ph';
    $insert->execute([$id,$email,$hash,$role,'ACTIVE']); $accounts[$role]=['id'=>$id,'email'=>$email,'password'=>'QA-only-2026!Test'];
}
$orgs=$sandbox->query("SELECT id,code FROM organizations WHERE status='ACTIVE' ORDER BY id")->fetchAll();
$org=$orgs[0]; foreach($orgs as $candidate) if($candidate['code']==='CCS') $org=$candidate;
foreach(['DEAN'=>'DEAN','ORG_STAFF'=>'ORGANIZATION'] as $role=>$code) {
    $st=$sandbox->prepare('SELECT id FROM stations WHERE code=?'); $st->execute([$code]);
    $sandbox->prepare("INSERT INTO staff_assignments (user_id,station_id,organization_id,status) VALUES (?,?,?,'ACTIVE')")->execute([$accounts[$role]['id'],$st->fetchColumn(),$org['id']]);
}
$sandbox->prepare("INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES (?,?,'QA','Synthetic Student','BSCS',2,'QA-ONLY',?,'ACTIVE')")->execute([$accounts['STUDENT']['id'],'QA-AUDIT-001',$org['id']]);
$studentId=(int)$sandbox->lastInsertId();
$sandbox->exec("INSERT INTO academic_periods (school_year,semester,status,clearance_status) VALUES ('2098-2099','1ST','ACTIVE','PREPARING')");
$periodId=(int)$sandbox->lastInsertId();
$sandbox->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status,enrolled_by) VALUES (?,?,'ENROLLED',?)")->execute([$periodId,$studentId,$accounts['REGISTRAR']['id']]);
$sandbox->prepare("INSERT INTO office_period_readiness (academic_period_id,station_id,status) SELECT ?,id,'NOT_READY' FROM stations WHERE status='ACTIVE' AND code<>'REGISTRAR'")->execute([$periodId]);
$sandbox->exec('SET FOREIGN_KEY_CHECKS=1');
$fixture=['database'=>$databaseName,'source_database'=>getenv('DB_DATABASE')?:'lewis_clearance','tables'=>count($tables),'reference_rows'=>$counts,'accounts'=>$accounts,'student_id'=>$studentId,'period_id'=>$periodId,'organization'=>$org];
file_put_contents(__DIR__.'/sandbox-fixture.json',json_encode($fixture,JSON_PRETTY_PRINT));
file_put_contents(__DIR__.'/sandbox-schema.json',json_encode($schema,JSON_PRETTY_PRINT));
$sandboxRoot=__DIR__.'/sandbox-app';
if(!is_dir($sandboxRoot)) mkdir($sandboxRoot,0700,true);
$skip=['.git','.tmp','qa-audit','storage','tests','database','node_modules'];
function copyAuditApp(string $from,string $to,array $skip): void {
    foreach(new DirectoryIterator($from) as $item) {
        if($item->isDot()||in_array($item->getFilename(),$skip,true)) continue;
        $target=$to.'/'.$item->getFilename();
        if($item->isDir()) { if(!is_dir($target))mkdir($target,0700,true);copyAuditApp($item->getPathname(),$target,$skip); }
        elseif(preg_match('/\.(?:php|js|css|html|png|jpg|webp)$/i',$item->getFilename()))copy($item->getPathname(),$target);
    }
}
copyAuditApp($root,$sandboxRoot,$skip);
mkdir($sandboxRoot.'/storage/sessions',0700,true);
mkdir($sandboxRoot.'/storage/private',0700,true);
echo json_encode(['database'=>$databaseName,'tables'=>count($tables),'reference_rows'=>$counts,'synthetic_roles'=>count($roles),'sandbox_root'=>$sandboxRoot],JSON_PRETTY_PRINT)."\n";
