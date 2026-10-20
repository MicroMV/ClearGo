<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$f=json_decode(file_get_contents(__DIR__.'/sandbox-fixture.json'),true,512,JSON_THROW_ON_ERROR);putenv('DB_DATABASE='.$f['database']);
require_once __DIR__.'/sandbox-app/config/database.php';$db=getDatabaseConnection();
$data=['organizations'=>$db->query('SELECT id,code,name,status FROM organizations')->fetchAll(),'stations'=>$db->query('SELECT id,code,name,parent_station_id,sort_order,status FROM stations ORDER BY COALESCE(parent_station_id,id),sort_order')->fetchAll(),'requirements'=>$db->query('SELECT r.name,r.requirement_code,s.code AS station,r.applicable_organization_id,r.minimum_year_level,r.maximum_year_level,r.applicable_semester,r.is_required,r.status FROM requirements r JOIN stations s ON s.id=r.station_id ORDER BY s.sort_order,r.id')->fetchAll()];file_put_contents(__DIR__.'/system-config.json',json_encode($data,JSON_PRETTY_PRINT));echo 'Exported synthetic reference configuration only.';
