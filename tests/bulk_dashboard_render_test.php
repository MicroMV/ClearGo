<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/bulk_clearance.php';

// Render an existing staff dashboard without submitting any clearance decisions.
$role = $argv[1] ?? 'COMLAB_STAFF';
$database = getDatabaseConnection();
$statement = $database->prepare("SELECT user.id,user.email,user.role FROM users user
    WHERE user.role=:role AND user.status='ACTIVE'
      AND (user.role NOT IN ('DEAN','ORG_STAFF') OR EXISTS (
          SELECT 1 FROM staff_assignments assignment INNER JOIN stations station ON station.id=assignment.station_id
          WHERE assignment.user_id=user.id AND assignment.status='ACTIVE'
            AND station.code=CASE WHEN user.role='DEAN' THEN 'DEAN' ELSE 'ORGANIZATION' END
      )) ORDER BY user.id LIMIT 1");
$statement->execute(['role' => $role]);
$user = $statement->fetch();
if (!$user) {
    throw new RuntimeException('No active test account for ' . $role);
}
$user['id'] = (int) $user['id'];
startSecureSession();
$_SESSION['authenticated_user'] = $user;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SCRIPT_NAME'] = '/ClearGo/test-dashboard.php';
ob_start();
register_shutdown_function(static function () use ($role): void {
    $html = ob_get_clean();
    $_SESSION = [];
    session_destroy();
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html ?: '<html></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $forms = $xpath->query('//div[@class="clearance-list-footer"]/form[@class="bulk-clearance-form"]');
    if ($forms->length !== 1 || $xpath->query('.//input[@name="action" and @value="approve_all_ready"]', $forms->item(0))->length !== 1) {
        fwrite(STDERR, $role . " dashboard did not render its bulk approval form.\n");
        exit(1);
    }
    if ($xpath->query('//form//form')->length !== 0 || http_response_code() >= 400) {
        fwrite(STDERR, $role . " dashboard failed HTML or response checks.\n");
        exit(1);
    }
    echo $role . " dashboard bulk button rendered successfully.\n";
});
if ($role === 'DEAN') {
    require __DIR__ . '/../dean/dashboard.php';
} elseif ($role === 'ORG_STAFF') {
    require __DIR__ . '/../organization/dashboard.php';
} else {
    require_once __DIR__ . '/../clearance/office_dashboard.php';
    $config = officeConfigForRole($role);
    renderOfficeDashboard($role, $config['code'], $config['title']);
}
