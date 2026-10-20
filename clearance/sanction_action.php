<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/auth_check.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/sanction_functions.php';
$user=requireLogin();
if(!in_array($user['role'],['ORG_STAFF','STUDENT_COUNCIL_STAFF'],true)){http_response_code(403);exit('Forbidden');}
$directory=$user['role']==='ORG_STAFF'?'/organization':'/student-council';
$back=$directory.'/sanction_submissions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!verifyCsrfToken($_POST['csrf_token']??null)){setFlashMessage('Unable to process that sanction request.');redirectTo($back);}
try{
    $db=getDatabaseConnection();$action=$_POST['action']??'';
    if(in_array($action,['policy','policy_update','policy_delete'],true)){
        $id=filter_var($_POST['event_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false)throw new RuntimeException('Choose a valid event.');
        $back.='?event='.$id;
        if($action==='policy_delete'){
            deleteEventSanctionPolicy($db,$user,$id);
            setFlashMessage('Saved sanction policy deleted. Existing student sanctions and proof were preserved.');
        }else{
            $monitor=filter_var($_POST['monitor_user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($monitor===false)throw new RuntimeException('Choose a valid monitor.');
            saveEventSanctionPolicy($db,$user,$id,is_string($_POST['task']??null)?$_POST['task']:'',is_string($_POST['hours']??null)?$_POST['hours']:'',$monitor,$action==='policy_update');
            setFlashMessage($action==='policy_update'?'Sanction policy updated. Existing student sanctions retain their assigned requirements.':'Sanction policy saved. Absences are assigned when this event check-in ends.');
        }
    }elseif($action==='approve'||$action==='return'){
        $id=filter_var($_POST['submission_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false)throw new RuntimeException('Choose a valid submission.');
        reviewSanctionProof($db,$user,$id,$action==='approve',is_string($_POST['remarks']??null)?$_POST['remarks']:'',($_POST['work_verified']??null)==='1');
        setFlashMessage($action==='approve'?'Sanctions verified. The student can be cleared when all office requirements are complete.':'Proof returned to the student with your feedback.');
    }else throw new RuntimeException('Choose a valid sanction action.');
}catch(Throwable $e){error_log('Sanction action failed: '.$e->getMessage());setFlashMessage($e instanceof RuntimeException&&!$e instanceof PDOException?$e->getMessage():'Unable to update sanctions. Please try again.');}
redirectTo($back);
