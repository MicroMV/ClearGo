<?php
declare(strict_types=1);

require_once __DIR__.'/../auth/auth_check.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/sanction_functions.php';

function renderSanctionSubmissionsPage(string $role): void
{
    $user=requireRole($role);
    $database=getDatabaseConnection();
    $scope=sanctionStaffScope($database,$user);
    $isOrganization=$scope['code']==='ORGANIZATION';
    $directory=$isOrganization?'/organization':'/student-council';
    $organization=$isOrganization?assignedOrganization($database,(int)$user['id']):null;
    $officeName=$isOrganization?($organization['code']==='CBE'?'YBC Organization Clearance':'Organization Clearance'):'Student Council Clearance';
    $period=$database->query("SELECT id,school_year,semester FROM academic_periods WHERE status='ACTIVE' LIMIT 1")->fetch();
    $events=[];
    $selectedEvent=null;
    if($period!==false) {
        syncExpiredEventSanctions($database,(int)$period['id']);
        $eventQuery=$database->prepare("SELECT id,name,event_date FROM events WHERE academic_period_id=? AND station_id=? AND organization_id <=> ? AND status='ACTIVE' AND is_required=1 ORDER BY event_date DESC,id DESC");
        $eventQuery->execute([$period['id'],$scope['station_id'],$scope['organization_id']]);
        $events=$eventQuery->fetchAll();
        $selectedEvent=$events[0]??null;
        $requestedEventId=filter_var($_GET['event']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        foreach($events as $event) {
            if((int)$event['id']===$requestedEventId) { $selectedEvent=$event; break; }
        }
    }
    $csrfToken=createCsrfToken();
    $message=pullFlashMessage();
    $notifications=dashboardNotifications($database,(int)$user['id']);
    $sidebar=staffDashboardSidebar($officeName,$user,[
        ['key'=>'dashboard','label'=>'Dashboard','url'=>$directory.'/dashboard.php','icon'=>'dashboard'],
        ['key'=>'attendance','label'=>'Event Attendance','url'=>$directory.'/attendance.php','icon'=>'document'],
        ['key'=>'sanctions','label'=>'Sanction Submissions','url'=>$directory.'/sanction_submissions.php','icon'=>'document'],
    ],'sanctions',dashboardLogoutForm());
    ?>
<!doctype html>
<html lang="en">
<head><?= connectionStatusAssets() ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sanction Submissions | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/sanctions.css')) ?>?v=<?= (int)filemtime(__DIR__.'/../assets/css/sanctions.css') ?>">
</head>
<body class="staff-dashboard"><div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
<header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button><div class="topbar-title"><h1>Sanction Submissions</h1><p>Set absence sanctions and review students' Before and After photos.</p></div><div class="topbar-actions"><?= dashboardNotificationBell($notifications) ?></div></header>
<main class="staff-main">
<?php if($message!==null): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
<?php if($period===false): ?><section class="panel"><h2>No active semester</h2><p class="muted">Sanction submissions will appear when a semester is active.</p></section>
<?php else: ?><p class="muted"><?= escapeHtml($period['semester'].' Semester A.Y. '.$period['school_year']) ?></p><?php require __DIR__.'/sanction_management.php'; ?><?php require __DIR__.'/sanction_submission_list.php'; ?><?php endif; ?>
</main></div></div><script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int)filemtime(__DIR__.'/../assets/js/staff-dashboard.js') ?>"></script></body>
</html>
<?php
}
