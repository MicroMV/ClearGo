<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/auth_check.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../clearance/sanction_functions.php';
$user=requireRole('STUDENT');
if($_SERVER['REQUEST_METHOD']!=='POST'||!verifyCsrfToken($_POST['csrf_token']??null)){setFlashMessage('Unable to submit sanction proof. Please try again.');redirectTo('/student/dashboard.php#attendance');}
try{
    if(array_diff(array_keys($_FILES),['before_photo','after_photo'])!==[] || !is_array($_FILES['before_photo']??null) || !is_array($_FILES['after_photo']??null)) throw new RuntimeException('Submit exactly one Before photo and one After photo.');
    submitSanctionProof(getDatabaseConnection(),$user,is_string($_POST['owner_code']??null)?$_POST['owner_code']:'',$_FILES['before_photo'],$_FILES['after_photo']);
    setFlashMessage('Your two photos were submitted. The event office will verify your work and required hours.');
}catch(Throwable $e){error_log('Sanction proof submission failed: '.$e->getMessage());setFlashMessage($e instanceof RuntimeException && !$e instanceof PDOException?$e->getMessage():'Unable to submit the photos. Please try again.');}
redirectTo('/student/dashboard.php#attendance');
