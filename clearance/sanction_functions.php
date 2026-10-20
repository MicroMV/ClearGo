<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../auth/profile_picture_functions.php';
require_once __DIR__ . '/../organization/event_functions.php';
require_once __DIR__ . '/../organization/clearance_functions.php';
require_once __DIR__ . '/readiness_functions.php';

function sanctionPhotoPath(string $name): string
{
    if (!preg_match('/\A[a-f0-9]{40}\.(jpg|png|webp)\z/',$name)) { throw new RuntimeException('Invalid sanction photo reference.'); }
    return __DIR__.'/../storage/private/sanction-proofs/'.$name;
}

function removeSanctionPhotos(array $names): void
{
    foreach ($names as $name) {
        $path=sanctionPhotoPath($name);
        if (is_file($path) && !@unlink($path)) { error_log('Unable to remove superseded sanction photo: '.$name); }
    }
}

function sanctionStaffScope(PDO $db, array $user): array
{
    if (!in_array($user['role'] ?? '', ['ORG_STAFF','STUDENT_COUNCIL_STAFF'], true)) { throw new RuntimeException('Only the event organizer can manage sanctions.'); }
    $account = $db->prepare("SELECT id FROM users WHERE id=? AND role=? AND status='ACTIVE'");
    $account->execute([$user['id'],$user['role']]);
    if (!$account->fetchColumn()) { throw new RuntimeException('The staff account is unavailable.'); }
    $code = $user['role'] === 'ORG_STAFF' ? 'ORGANIZATION' : 'STUDENT_COUNCIL';
    $organization = $code === 'ORGANIZATION' ? assignedOrganization($db,(int)$user['id']) : null;
    $station = $db->prepare("SELECT id FROM stations WHERE code=? AND status='ACTIVE'"); $station->execute([$code]);
    $stationId = $station->fetchColumn();
    if (!$stationId) { throw new RuntimeException('The event office is unavailable.'); }
    return ['station_id'=>(int)$stationId,'code'=>$code,'organization_id'=>$organization === null ? null : (int)$organization['id']];
}

function sanctionMonitors(PDO $db, array $scope): array
{
    if ($scope['code'] === 'ORGANIZATION') {
        $q=$db->prepare("SELECT DISTINCT u.id,u.email FROM users u JOIN staff_assignments a ON a.user_id=u.id AND a.status='ACTIVE' WHERE u.role='ORG_STAFF' AND u.status='ACTIVE' AND a.station_id=? AND a.organization_id=? ORDER BY u.email");
        $q->execute([$scope['station_id'],$scope['organization_id']]);
    } else {
        $q=$db->query("SELECT id,email FROM users WHERE role='STUDENT_COUNCIL_STAFF' AND status='ACTIVE' ORDER BY email");
    }
    return $q->fetchAll();
}

/** Caller holds a transaction so policy changes and attendance finalization share the event lock. */
function lockSanctionPolicyEvent(PDO $db, array $scope, int $eventId): array
{
    // Use the same readiness/period locks as Mark Ready so a stale form cannot bypass it.
    $readiness=officeReadiness($db,$scope['code'],true);
    if($readiness['readiness_status']!=='NOT_READY') {
        throw new RuntimeException('Sanction policies are locked because this office is Ready.');
    }
    $query=$db->prepare("SELECT e.* FROM events e JOIN academic_periods p ON p.id=e.academic_period_id AND p.status='ACTIVE' AND p.clearance_status IN ('PREPARING','OPEN') WHERE e.id=? AND e.station_id=? AND e.organization_id <=> ? AND e.academic_period_id=? AND e.status='ACTIVE' AND e.is_required=1 FOR UPDATE");
    $query->execute([$eventId,$scope['station_id'],$scope['organization_id'],$readiness['academic_period_id']]);
    $event=$query->fetch();
    if (!$event) { throw new RuntimeException('The selected event is unavailable to this office.'); }
    return $event;
}

function saveEventSanctionPolicy(PDO $db, array $user, int $eventId, string $task, string $hours, int $monitorId, bool $update = false): void
{
    $scope=sanctionStaffScope($db,$user); $task=trim($task);
    if ($task === '' || mb_strlen($task)>2000 || !preg_match('/\A\d{1,3}(?:\.\d{1,2})?\z/',$hours) || (float)$hours<=0 || (float)$hours>100) {
        throw new RuntimeException('Describe the sanction and enter required hours between 0.01 and 100.');
    }
    if (!in_array($monitorId,array_map('intval',array_column(sanctionMonitors($db,$scope),'id')),true)) { throw new RuntimeException('Choose an active monitor from this event office.'); }
    $db->beginTransaction();
    try {
        $event=lockSanctionPolicyEvent($db,$scope,$eventId);
        $existing=$db->prepare('SELECT task,hours,monitor_user_id FROM event_sanction_policies WHERE event_id=? FOR UPDATE'); $existing->execute([$eventId]);
        $previous=$existing->fetch();
        if ($update && !$previous) { throw new RuntimeException('This sanction policy was deleted. Refresh the page before saving.'); }
        if (!$update && $previous) { throw new RuntimeException('This event already has an assigned sanction policy. Use Edit to change it.'); }
        if ($update) {
            $db->prepare('UPDATE event_sanction_policies SET task=?,hours=?,monitor_user_id=? WHERE event_id=?')->execute([$task,$hours,$monitorId,$eventId]);
        } else {
            $db->prepare('INSERT INTO event_sanction_policies(event_id,task,hours,monitor_user_id,created_by) VALUES(?,?,?,?,?)')->execute([$eventId,$task,$hours,$monitorId,$user['id']]);
        }
        $description=($update?'Updated':'Configured').' sanction policy for '.$event['name'].'. '.json_encode(['previous'=>$previous?:null,'policy'=>['task'=>$task,'hours'=>$hours,'monitor_user_id'=>$monitorId]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $db->prepare('INSERT INTO audit_logs(user_id,action,station_id,description,new_status) VALUES(?,?,?,?,?)')->execute([$user['id'],$update?'EVENT_SANCTION_UPDATED':'EVENT_SANCTION_CONFIGURED',$scope['station_id'],$description,$update?'UPDATED':'CONFIGURED']);
        $db->commit();
    } catch (Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
    syncExpiredEventSanctions($db,(int)$event['academic_period_id']);
}

function deleteEventSanctionPolicy(PDO $db, array $user, int $eventId): void
{
    $scope=sanctionStaffScope($db,$user);
    $db->beginTransaction();
    try {
        $event=lockSanctionPolicyEvent($db,$scope,$eventId);
        $query=$db->prepare('SELECT task,hours,monitor_user_id FROM event_sanction_policies WHERE event_id=? FOR UPDATE');
        $query->execute([$eventId]);$policy=$query->fetch();
        if (!$policy) { throw new RuntimeException('This sanction policy was already deleted.'); }
        // Assigned student sanctions are independent snapshots and keep their proof history.
        $db->prepare('DELETE FROM event_sanction_policies WHERE event_id=?')->execute([$eventId]);
        $description='Deleted saved sanction policy for '.$event['name'].'. Assigned student sanctions were retained. '.json_encode($policy,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $db->prepare("INSERT INTO audit_logs(user_id,action,station_id,description,new_status) VALUES(?,'EVENT_SANCTION_DELETED',?,?,'DELETED')")->execute([$user['id'],$scope['station_id'],$description]);
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) { $db->rollBack(); } throw $error; }
}

/** Finalization is serialized with QR check-in. Only configured events create sanctions. */
function syncExpiredEventSanctions(PDO $db, int $periodId): void
{
    $q=$db->prepare("SELECT e.id FROM events e JOIN event_sanction_policies policy ON policy.event_id=e.id JOIN academic_periods p ON p.id=e.academic_period_id AND p.status='ACTIVE' AND p.clearance_status IN ('PREPARING','OPEN') WHERE e.academic_period_id=? AND e.status='ACTIVE' AND e.is_required=1 AND e.attendance_finalized_at IS NULL AND e.check_in_opens_at IS NOT NULL AND e.check_in_closes_at<=NOW() ORDER BY e.id");
    $q->execute([$periodId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $eventId) {
        $db->beginTransaction();
        try {
            $eventQuery=$db->prepare("SELECT e.*,policy.task,policy.hours,policy.monitor_user_id FROM events e JOIN event_sanction_policies policy ON policy.event_id=e.id JOIN academic_periods p ON p.id=e.academic_period_id AND p.status='ACTIVE' AND p.clearance_status IN ('PREPARING','OPEN') WHERE e.id=? AND e.status='ACTIVE' AND e.attendance_finalized_at IS NULL AND e.check_in_closes_at<=NOW() FOR UPDATE");
            $eventQuery->execute([$eventId]); $event=$eventQuery->fetch();
            if (!$event) { $db->commit(); continue; }
            $db->prepare("UPDATE events SET attendance_finalized_at=NOW(),check_in_token_hash=NULL WHERE id=?")->execute([$eventId]);
            $db->prepare("INSERT INTO event_attendance(event_id,student_id,status,check_in_method,recorded_by) SELECT ?,s.id,'ABSENT','MANUAL',? FROM students s JOIN semester_enrollments en ON en.student_id=s.id AND en.academic_period_id=? AND en.status='ENROLLED' WHERE s.status='ACTIVE' AND (? IS NULL OR s.organization_id=?) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id)")
                ->execute([$eventId,$event['created_by'],$periodId,$event['organization_id'],$event['organization_id']]);
            $absent=$db->prepare("SELECT a.student_id,s.user_id FROM event_attendance a JOIN students s ON s.id=a.student_id WHERE a.event_id=? AND a.status='ABSENT' AND a.sanction_verified_at IS NULL");
            $absent->execute([$eventId]);
            $insert=$db->prepare('INSERT INTO event_sanctions(event_id,student_id,task,hours,monitor_user_id) VALUES(?,?,?,?,?)');
            foreach ($absent->fetchAll() as $student) {
                $insert->execute([$eventId,$student['student_id'],$event['task'],$event['hours'],$event['monitor_user_id']]);
                createNotification($db,(int)$student['user_id'],'Attendance sanction assigned','Absent from "'.$event['name'].'": '.$event['task'].' ('.$event['hours'].' hour(s)). Complete the work and submit one Before/After photo pair for this office.', $periodId);
            }
            $db->prepare("INSERT INTO audit_logs(user_id,action,station_id,description,new_status) VALUES(NULL,'EVENT_ATTENDANCE_FINALIZED',?,?,'FINALIZED')")->execute([$event['station_id'],'Finalized attendance and assigned sanctions for ' . $event['name'] . '.']);
            $db->commit();
        } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
    }
}

function studentSanctionGroups(PDO $db, int $studentId, int $periodId): array
{
    $q=$db->prepare("SELECT x.*,e.name AS event_name,e.organization_id,e.station_id,st.code AS owner_code,u.email AS monitor_name FROM event_sanctions x JOIN events e ON e.id=x.event_id JOIN stations st ON st.id=e.station_id JOIN users u ON u.id=x.monitor_user_id JOIN students student ON student.id=x.student_id AND (e.organization_id IS NULL OR e.organization_id=student.organization_id) WHERE x.student_id=? AND e.academic_period_id=? AND e.status='ACTIVE' AND x.status='PENDING' ORDER BY e.event_date,e.id");
    $q->execute([$studentId,$periodId]); $groups=[];
    foreach($q->fetchAll() as $row) {
        $code=$row['owner_code'];
        if(!isset($groups[$code])) $groups[$code]=['name'=>$code==='ORGANIZATION'?'Organization':'Student Council','items'=>[],'hours'=>0,'submission'=>false];
        $groups[$code]['items'][]=$row; $groups[$code]['hours']+=(float)$row['hours'];
    }
    foreach($groups as $code=>&$group) {
        $q=$db->prepare("SELECT sub.id,sub.status,sub.review_remarks,sub.submitted_at FROM sanction_submissions sub JOIN stations st ON st.id=sub.station_id WHERE sub.student_id=? AND sub.academic_period_id=? AND st.code=? AND sub.organization_id <=> ? AND sub.status IN ('SUBMITTED','RETURNED') ORDER BY sub.id DESC LIMIT 1");
        $q->execute([$studentId,$periodId,$code,$group['items'][0]['organization_id']]); $group['submission']=$q->fetch();
    }
    unset($group); return $groups;
}

function submitSanctionProof(PDO $db, array $user, string $ownerCode, array $before, array $after): int
{
    if(($user['role']??'')!=='STUDENT' || !in_array($ownerCode,['ORGANIZATION','STUDENT_COUNCIL'],true)) throw new RuntimeException('Choose a valid sanction office.');
    $period=activeEventPeriod($db); syncExpiredEventSanctions($db,(int)$period['id']);
    $pictures=[];
    foreach([$before,$after] as $file) {
        try { $picture=validateProfilePicture($file); }
        catch (RuntimeException $error) { throw new RuntimeException(str_replace('profile picture','sanction photo',$error->getMessage()),0,$error); }
        if(!is_uploaded_file($picture['temporary_name'])) throw new RuntimeException('Choose a valid Before and After photo.');
        $pictures[]=$picture;
    }
    if(hash_file('sha256',$pictures[0]['temporary_name'])===hash_file('sha256',$pictures[1]['temporary_name'])) throw new RuntimeException('Use different photos for Before and After.');
    $saved=[];$old=[];
    try {
        $directory=dirname(sanctionPhotoPath(str_repeat('0',40).'.png'));
        if(!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory)) throw new RuntimeException('Sanction photo storage is unavailable.');
        foreach($pictures as $picture) {
            $name=bin2hex(random_bytes(20)).'.'.$picture['extension'];
            if(!move_uploaded_file($picture['temporary_name'],sanctionPhotoPath($name))) throw new RuntimeException('The photo could not be saved. Please try again.');
            $saved[]=$name;
        }
        $db->beginTransaction();
        $q=$db->prepare("SELECT s.* FROM students s JOIN semester_enrollments en ON en.student_id=s.id AND en.academic_period_id=? AND en.status='ENROLLED' WHERE s.user_id=? AND s.status='ACTIVE' FOR UPDATE");
        $q->execute([$period['id'],$user['id']]); $student=$q->fetch();
        if(!$student) throw new RuntimeException('Active semester enrollment is required.');
        $groups=studentSanctionGroups($db,(int)$student['id'],(int)$period['id']); $group=$groups[$ownerCode]??null;
        if(!$group || $group['items']===[]) throw new RuntimeException('There are no outstanding sanctions for this office.');
        $previous=$group['submission'];
        if($previous && $previous['status']==='SUBMITTED') throw new RuntimeException('Your photo pair is already awaiting office verification.');
        $owner=$group['items'][0];
        if($previous && $previous['status']==='RETURNED') {
            $id=(int)$previous['id'];
            $prior=$db->prepare('SELECT before_stored_name,after_stored_name FROM sanction_submissions WHERE id=? FOR UPDATE');$prior->execute([$id]);$old=array_values($prior->fetch());
            $q=$db->prepare("UPDATE sanction_submissions SET before_stored_name=?,before_mime=?,after_stored_name=?,after_mime=?,status='SUBMITTED',submitted_at=NOW(),reviewed_by=NULL,reviewed_at=NULL,review_remarks=NULL WHERE id=?");
            $q->execute([$saved[0],$pictures[0]['mime_type'],$saved[1],$pictures[1]['mime_type'],$id]);
            $db->prepare('DELETE FROM sanction_submission_items WHERE submission_id=?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO sanction_submissions(student_id,academic_period_id,station_id,organization_id,before_stored_name,before_mime,after_stored_name,after_mime) VALUES(?,?,?,?,?,?,?,?)')->execute([$student['id'],$period['id'],$owner['station_id'],$owner['organization_id'],$saved[0],$pictures[0]['mime_type'],$saved[1],$pictures[1]['mime_type']]);
            $id=(int)$db->lastInsertId();
        }
        $item=$db->prepare('INSERT INTO sanction_submission_items(submission_id,sanction_id) VALUES(?,?)');
        $monitors=[];
        foreach($group['items'] as $sanction) { $item->execute([$id,$sanction['id']]); $monitors[(int)$sanction['monitor_user_id']]=true; }
        foreach(array_keys($monitors) as $monitor) createNotification($db,$monitor,'Sanction proof submitted',$student['student_number'].' submitted Before/After photos for '.$group['hours'].' hour(s). Review the work and required hours before approving.',(int)$period['id']);
        $db->prepare("INSERT INTO audit_logs(user_id,action,station_id,student_id,description,new_status) VALUES(?,'SANCTION_PROOF_SUBMITTED',?,?,?,'SUBMITTED')")->execute([$user['id'],$owner['station_id'],$student['id'],'Submitted exactly two photos covering '.count($group['items']).' outstanding sanction(s).']);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();removeSanctionPhotos($saved);throw $e;}
    // Only remove a returned submission's old pair after its replacement is committed.
    removeSanctionPhotos($old); return $id;
}

function reviewSanctionProof(PDO $db, array $user, int $submissionId, bool $approve, string $remarks, bool $workVerified): void
{
    $scope=sanctionStaffScope($db,$user); $period=activeEventPeriod($db); $remarks=trim($remarks);
    if(mb_strlen($remarks)>2000 || (!$approve && $remarks==='')) throw new RuntimeException('Explain what the student needs to correct (up to 2,000 characters).');
    if($approve && !$workVerified) throw new RuntimeException('Confirm the work and required hours with the assigned monitor first.');
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT sub.id,sub.status,sub.student_id,s.user_id FROM sanction_submissions sub JOIN students s ON s.id=sub.student_id WHERE sub.id=? AND sub.academic_period_id=? AND sub.station_id=? AND sub.organization_id <=> ? FOR UPDATE');
        $q->execute([$submissionId,$period['id'],$scope['station_id'],$scope['organization_id']]);$submission=$q->fetch();
        if(!$submission || $submission['status']!=='SUBMITTED') throw new RuntimeException('This submission is unavailable or has already been reviewed.');
        $q=$db->prepare("SELECT x.id,x.event_id,x.student_id FROM sanction_submission_items item JOIN event_sanctions x ON x.id=item.sanction_id JOIN events e ON e.id=x.event_id WHERE item.submission_id=? AND x.student_id=? AND e.academic_period_id=? AND e.station_id=? AND e.organization_id <=> ? AND e.status='ACTIVE' AND x.status='PENDING' FOR UPDATE");
        $q->execute([$submissionId,$submission['student_id'],$period['id'],$scope['station_id'],$scope['organization_id']]);$items=$q->fetchAll();
        if($items===[]) throw new RuntimeException('This submission no longer has outstanding sanctions.');
        if($approve) {
            $resolve=$db->prepare("UPDATE event_sanctions SET status='RESOLVED',resolved_by=?,resolved_at=NOW() WHERE id=? AND status='PENDING'");
            $attendance=$db->prepare("UPDATE event_attendance SET sanction_verified_at=NOW(),sanction_verified_by=? WHERE event_id=? AND student_id=? AND status='ABSENT'");
            foreach($items as $item){$resolve->execute([$user['id'],$item['id']]);$attendance->execute([$user['id'],$item['event_id'],$item['student_id']]);}
        }
        $status=$approve?'APPROVED':'RETURNED';
        $db->prepare('UPDATE sanction_submissions SET status=?,reviewed_by=?,reviewed_at=NOW(),review_remarks=? WHERE id=?')->execute([$status,$user['id'],$remarks===''?null:$remarks,$submissionId]);
        $db->prepare('INSERT INTO audit_logs(user_id,action,station_id,student_id,description,new_status) VALUES(?,?,?,?,?,?)')->execute([$user['id'],'SANCTION_PROOF_'.$status,$scope['station_id'],$submission['student_id'],($approve?'Verified completed work and hours for ':'Returned proof for ').count($items).' sanction(s). '.$remarks,$status]);
        createNotification($db,(int)$submission['user_id'],$approve?'Sanctions completed':'Sanction proof returned',$approve?'The event office verified your submitted sanctions. Any new sanctions still need separate verification.':'Please correct and resubmit your Before/After photos: '.$remarks,(int)$period['id']);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
