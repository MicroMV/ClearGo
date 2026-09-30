<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../clearance/clearance_functions.php';

$user = requireRole('DEAN');
$database = getDatabaseConnection();
$dean = deanAssignment($database, (int) $user['id']);
if ($dean['organization_code'] !== 'CCS') { http_response_code(403); exit('Submitted requirements are only available to the CCS Dean.'); }
$filter = strtoupper((string) ($_GET['filter'] ?? 'SUBMITTED'));
if (!in_array($filter, ['SUBMITTED', 'VERIFIED', 'RETURNED', 'ALL'], true)) { $filter = 'SUBMITTED'; }
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
$countQuery = $database->prepare("SELECT COUNT(*) FROM evaluation_submissions submission INNER JOIN clearances clearance ON clearance.id=submission.clearance_id INNER JOIN students student ON student.id=clearance.student_id AND student.organization_id=:organization_id INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE' WHERE (:all_status=1 OR submission.status=:status)");
$countQuery->execute(['organization_id' => $dean['organization_id'], 'all_status' => $filter === 'ALL' ? 1 : 0, 'status' => $filter]);
$totalSubmissions = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalSubmissions / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$query = $database->prepare(
    "SELECT submission.id, submission.anonymous_code, submission.status, submission.submitted_at, submission.reviewed_at, submission.return_reason,
            COUNT(image.id) AS image_count
     FROM evaluation_submissions submission
     INNER JOIN clearances clearance ON clearance.id=submission.clearance_id
     INNER JOIN students student ON student.id=clearance.student_id AND student.organization_id=:organization_id
     INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
     LEFT JOIN evaluation_submission_images image ON image.submission_id=submission.id
     WHERE (:all_status=1 OR submission.status=:status)
     GROUP BY submission.id
     ORDER BY FIELD(submission.status, 'SUBMITTED','RETURNED','VERIFIED'), submission.submitted_at ASC
     LIMIT $pageSize OFFSET $offset"
);
$query->execute(['organization_id' => $dean['organization_id'], 'all_status' => $filter === 'ALL' ? 1 : 0, 'status' => $filter]);
$submissions = $query->fetchAll();
$images = $database->prepare('SELECT id, position FROM evaluation_submission_images WHERE submission_id=:submission_id ORDER BY position');
foreach ($submissions as &$submission) { $images->execute(['submission_id' => $submission['id']]); $submission['images'] = $images->fetchAll(); }
unset($submission);
$csrfToken = createCsrfToken();
$message = pullFlashMessage();
$sidebar = staffDashboardSidebar('CCS Dean Clearance', $user, [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => '/dean/dashboard.php', 'icon' => 'dashboard'],
    ['key' => 'submitted_requirements', 'label' => 'Submitted Requirements', 'url' => '/dean/submitted_requirements.php', 'icon' => 'document'],
], 'submitted_requirements', dashboardLogoutForm());
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Submitted Requirements | CCS Dean</title><link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>"><style>.page{max-width:1240px;margin:auto;padding:24px}.filters,.actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.filters a{padding:8px 11px;border:1px solid #b7c8d8;border-radius:7px;text-decoration:none}.filters a.active{background:#0d5ea6;color:#fff}.submission{background:#fff;border:1px solid #d8e3ed;border-radius:10px;padding:18px;margin:16px 0}.submission-head{display:flex;justify-content:space-between;gap:16px;align-items:start}.proofs{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}.proofs img{width:min(100%,300px);max-height:360px;object-fit:contain;border:1px solid #d8e3ed;border-radius:8px;background:#f7f9fc}.badge{padding:5px 9px;border-radius:99px;background:#fff7e8;color:#956200;font-size:12px;font-weight:bold}.verified{background:#e4f5eb;color:#176b3a}.returned{background:#fff0ed;color:#a33722}textarea{width:min(100%,500px);min-height:74px}button{background:#0d5ea6;color:#fff;border:0;border-radius:6px;padding:9px 12px;font:inherit;font-weight:bold}.return{background:#a33722}.message{padding:12px;background:#edf6ff;border-left:4px solid #0d5ea6}</style></head><body class="staff-dashboard"><div class="staff-shell"><?= $sidebar ?><main class="page"><h1>Submitted Requirements</h1><p>Review Faculty Evaluation screenshots by anonymous submission code. Student names and IDs are intentionally hidden.</p><?php if ($message): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?><nav class="filters"><?php foreach (['SUBMITTED'=>'Awaiting review','RETURNED'=>'Returned','VERIFIED'=>'Verified','ALL'=>'All'] as $value=>$label): ?><a class="<?= $filter===$value?'active':'' ?>" href="?filter=<?= $value ?>"><?= $label ?></a><?php endforeach; ?></nav><?php if ($submissions===[]): ?><p>No submissions in this view.</p><?php endif; ?><?php foreach ($submissions as $submission): ?><article class="submission"><div class="submission-head"><div><strong>Submission <?= escapeHtml($submission['anonymous_code']) ?></strong><br><small>Submitted <?= escapeHtml($submission['submitted_at']) ?> · <?= (int) $submission['image_count'] ?> image(s)</small></div><span class="badge <?= strtolower($submission['status']) ?>"><?= escapeHtml($submission['status']) ?></span></div><div class="proofs"><?php foreach ($submission['images'] as $image): ?><a href="<?= escapeHtml(applicationUrl('/dean/evaluation_image.php?id=' . (int) $image['id'])) ?>" target="_blank" rel="noopener"><img src="<?= escapeHtml(applicationUrl('/dean/evaluation_image.php?id=' . (int) $image['id'])) ?>" alt="Submitted evaluation screenshot <?= (int) $image['position'] ?>"></a><?php endforeach; ?></div><?php if ($submission['status']==='SUBMITTED'): ?><form class="actions" method="post" action="<?= escapeHtml(applicationUrl('/dean/submitted_requirement_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><button name="action" value="verify">Verify screenshots</button><textarea name="reason" maxlength="1000" placeholder="Reason if returning this submission"></textarea><button class="return" name="action" value="return">Return for resubmission</button></form><?php elseif ($submission['status']==='RETURNED'): ?><p>Return reason: <?= escapeHtml($submission['return_reason']) ?></p><?php endif; ?></article><?php endforeach; ?></main></div></body></html>
