<?php
ob_start();
require __DIR__ . '/../../tests/student_dashboard_history_test.php';
ob_end_clean();
foreach (['active' => [$bothSemesters[0],$bothSemesters,'DEAN'],'history' => [$firstSemesterOnly[0],$firstSemesterOnly,'DEAN'],'empty' => [false,[],'DEAN'],'complete' => [$bothSemesters[0],$bothSemesters,'NONE']] as $name => [$previewPeriod,$previewPeriods,$pendingOffice]) {
    $html = renderDashboardFixture($view,$previewPeriod,$previewPeriods,$pendingOffice);
    $html = str_replace('data-notification-endpoint="/auth/notifications.php"','data-notification-endpoint=""',$html);
    $html = str_replace('data-initial-view="clearance"','data-initial-view="overview"',$html);
    $html = str_replace('data-initial-modal="requirements-modal-1"','data-initial-modal=""',$html);
    $html = str_replace('/logo.png','/assets/the-lewis-college-logo.png',$html);
    $html = str_replace('<p>Finished for now?</p></div>','<p>Finished for now?</p><form class="logout-form" method="post" action="/logout.php"><button type="submit">Log out</button></form></div>',$html);
    file_put_contents(__DIR__ . '/' . $name . '.html',$html);
}
$redirect = renderDashboardFixture($view,$bothSemesters[0],$bothSemesters,'DEAN',true);
$redirect = str_replace('data-notification-endpoint="/auth/notifications.php"','data-notification-endpoint=""',$redirect);
file_put_contents(__DIR__ . '/redirect.html',$redirect);
echo "Preview fixtures rendered.\n";
