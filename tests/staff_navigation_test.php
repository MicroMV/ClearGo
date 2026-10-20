<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../auth/auth_check.php';

function navigationCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
$previousBase = getenv('APP_BASE_URL');
putenv('APP_BASE_URL=/ClearGo');
try {
    foreach (['dean/dashboard.php','organization/dashboard.php','library/dashboard.php','clinic/dashboard.php','bookstore/dashboard.php',
        'facilities/dashboard.php','comlab/dashboard.php','accounting/dashboard.php','saso/dashboard.php','student-council/dashboard.php','registrar/clearance.php'] as $page) {
        $view = json_encode(['path' => '/ClearGo/' . $page, 'query' => 'search=Test+Student&course=BSIT&year_level=4&filter=PENDING&page=3&export=xlsx&redirect=https://example.com']);
        $result = staffDashboardReturnPath('/' . $page . '?clearance=7', $view);
        parse_str((string) parse_url($result, PHP_URL_QUERY), $query);
        navigationCheck($query['search'] === 'Test Student' && $query['course'] === 'BSIT' && $query['year_level'] === '4'
            && $query['filter'] === 'PENDING' && $query['page'] === '3' && $query['clearance'] === '7', $page . ' lost filters or the action destination.');
        navigationCheck(!isset($query['export']) && !isset($query['redirect']), 'Unrelated query parameters were added.');
    }
    foreach ([null, [], 'broken JSON', 'null', json_encode(['path' => '/ClearGo/organization/dashboard.php', 'query' => 'page=3']),
        json_encode(['path' => 'https://example.com/ClearGo/dean/dashboard.php', 'query' => 'page=3']),
        json_encode(['path' => '/ClearGo/dean/dashboard.php', 'query' => []])] as $view) {
        navigationCheck(staffDashboardReturnPath('/dean/dashboard.php', $view) === '/dean/dashboard.php', 'Malformed or unrelated view changed a redirect.');
    }
    $view = json_encode(['path' => '/ClearGo/dean/dashboard.php', 'query' => 'filter[]=APPROVED&page=2']);
    navigationCheck(staffDashboardReturnPath('/dean/dashboard.php#records', $view) === '/dean/dashboard.php?page=2#records', 'Array filters or explicit anchors were handled incorrectly.');
    navigationCheck(staffDashboardReturnPath('https://example.com/dean/dashboard.php', $view) === 'https://example.com/dean/dashboard.php', 'An external destination was changed.');
    navigationCheck(staffDashboardReturnPath('/logout.php', $view) === '/logout.php', 'Office view changed a logout redirect.');
    $view = json_encode(['path' => '/ClearGo/registrar/students.php', 'query' => 'account_status=ACTIVE&enrollment=ENROLLED&organization=1&program=BSIT&page=4']);
    navigationCheck(staffDashboardReturnPath('/registrar/students.php', $view) === '/registrar/students.php?program=BSIT&account_status=ACTIVE&enrollment=ENROLLED&organization=1&page=4', 'Registrar roster filters were lost.');
    $view = json_encode(['path' => '/ClearGo/facilities/borrowed-items.php', 'query' => 'status=OUTSTANDING&page=3']);
    navigationCheck(staffDashboardReturnPath('/facilities/borrowed-items.php', $view) === '/facilities/borrowed-items.php?status=OUTSTANDING&page=3', 'Office history filters were lost.');
    $view = json_encode(['path' => '/ClearGo/dean/dashboard.php', 'query' => 'clearance=2&page=3']);
    navigationCheck(staffDashboardReturnPath('/dean/dashboard.php?clearance=7', $view) === '/dean/dashboard.php?clearance=7&page=3', 'Old view replaced the action-selected student.');
} finally { $previousBase === false ? putenv('APP_BASE_URL') : putenv('APP_BASE_URL=' . $previousBase); }
echo "All 11 office return paths preserve filters and pagination; unrelated paths and invalid context are ignored.\n";
