<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/auth_check.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/sanction_functions.php';
$user=requireLogin();$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$which=$_GET['photo']??'';
if($id===false||!in_array($which,['before','after'],true)){http_response_code(404);exit;}
$db=getDatabaseConnection();
if($user['role']==='STUDENT'){
    $q=$db->prepare('SELECT sub.id FROM sanction_submissions sub JOIN students s ON s.id=sub.student_id WHERE sub.id=? AND s.user_id=?');$q->execute([$id,$user['id']]);
}elseif(in_array($user['role'],['ORG_STAFF','STUDENT_COUNCIL_STAFF'],true)){
    $scope=sanctionStaffScope($db,$user);$q=$db->prepare('SELECT id FROM sanction_submissions WHERE id=? AND station_id=? AND organization_id <=> ?');$q->execute([$id,$scope['station_id'],$scope['organization_id']]);
}else{http_response_code(403);exit;}
if(!$q->fetchColumn()){http_response_code(404);exit;}
$q=$db->prepare('SELECT '.$which.'_stored_name AS stored_name,'.$which.'_mime AS mime FROM sanction_submissions WHERE id=?');$q->execute([$id]);$photo=$q->fetch();
if(!$photo || !is_file($path=sanctionPhotoPath($photo['stored_name']))){http_response_code(404);exit;}
header('Content-Type: '.$photo['mime']);header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Security-Policy: default-src \'none\'');readfile($path);
