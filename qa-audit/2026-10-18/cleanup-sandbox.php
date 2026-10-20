<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$fixture=json_decode(file_get_contents(__DIR__.'/sandbox-fixture.json'),true,512,JSON_THROW_ON_ERROR);$name=$fixture['database'];
if(!preg_match('/\Acleargo_qa_20261018_[a-f0-9]{8}\z/',$name)||$name===($fixture['source_database']??null))throw new RuntimeException('Refusing unsafe database cleanup.');
$host=getenv('DB_HOST')?:'localhost';$port=getenv('DB_PORT')?:'3306';$user=getenv('DB_USERNAME')?:'root';$password=getenv('DB_PASSWORD');
$db=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$password===false?'':$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query('SELECT DATABASE()')->fetchColumn()!==$name)throw new RuntimeException('Connected database does not match isolated fixture.');
$db->exec('DROP DATABASE `'.$name.'`');
$remaining=$db->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$remaining->execute([$name]);
$status=['temporary_database'=>$name,'temporary_database_removed'=>(int)$remaining->fetchColumn()===0,'original_database_modified_by_cleanup'=>false,'retained'=>'Audit report, synthetic evidence, scripts and schema snapshot. Application source and original records were not reset.'];
file_put_contents(__DIR__.'/cleanup.json',json_encode($status,JSON_PRETTY_PRINT));echo json_encode($status,JSON_PRETTY_PRINT)."\n";
