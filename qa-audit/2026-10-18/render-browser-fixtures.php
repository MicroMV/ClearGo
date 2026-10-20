<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ob_start();require dirname(__DIR__,2).'/tests/student_dashboard_history_test.php';ob_end_clean();
$dir=__DIR__.'/browser-fixtures';if(!is_dir($dir))mkdir($dir,0700,true);
foreach(['active'=>[$bothSemesters[0],$bothSemesters,'DEAN'],'history'=>[$firstSemesterOnly[0],$firstSemesterOnly,'DEAN'],'empty'=>[false,[],'DEAN'],'complete'=>[$bothSemesters[0],$bothSemesters,'NONE']]as $name=>[$p,$periods,$station]){
    $html=renderDashboardFixture($view,$p,$periods,$station);
    // Synthetic HTML only: disable external polling so the real frontend can be tested independently.
    $html=preg_replace('/<script\b[^>]*data-connection-status[^>]*><\/script>/','',$html);
    $html=str_replace('data-notification-endpoint="/auth/notifications.php"','data-notification-endpoint=""',$html);
    $html=str_replace('/logo.png','/assets/the-lewis-college-logo.png',$html);
    file_put_contents($dir.'/'.$name.'.html',$html);
}
echo "Rendered four synthetic student dashboards from the current PHP template.\n";
