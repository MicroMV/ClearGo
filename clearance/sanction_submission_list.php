<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
// Included by the authenticated Organization / Student Council submissions page.
$sanctionScope=sanctionStaffScope($database,$user);
$proofStatus=is_string($_GET['proof_status']??null)?$_GET['proof_status']:'SUBMITTED';
if(!in_array($proofStatus,['SUBMITTED','RETURNED','APPROVED','ALL'],true))$proofStatus='SUBMITTED';
$proofPage=filter_var($_GET['proof_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$proofPage=$proofPage===false?1:$proofPage;
$proofWhere=' FROM sanction_submissions sub JOIN students s ON s.id=sub.student_id WHERE sub.academic_period_id=? AND sub.station_id=? AND sub.organization_id <=> ?';
$proofParams=[$period['id'],$sanctionScope['station_id'],$sanctionScope['organization_id']];
if($proofStatus!=='ALL'){$proofWhere.=' AND sub.status=?';$proofParams[]=$proofStatus;}
$proofCount=$database->prepare('SELECT COUNT(*)'.$proofWhere);$proofCount->execute($proofParams);$proofTotal=(int)$proofCount->fetchColumn();
$proofPages=max(1,(int)ceil($proofTotal/20));$proofPage=min($proofPage,$proofPages);$proofOffset=($proofPage-1)*20;
$proofQuery=$database->prepare('SELECT sub.id,sub.status,sub.submitted_at,sub.review_remarks,s.student_number,s.first_name,s.middle_name,s.last_name'.$proofWhere.' ORDER BY sub.submitted_at DESC,sub.id DESC LIMIT 20 OFFSET '.$proofOffset);
$proofQuery->execute($proofParams);$proofs=$proofQuery->fetchAll();
?>
<section class="panel sanction-panel">
    <div class="section-heading"><div><h2>Sanction submissions</h2><p class="muted">Photos support the review. Confirm the actual work and required hours with the assigned monitor before verifying.</p></div></div>
    <form class="controls" method="get"><label>Submission status<select name="proof_status"><?php foreach(['SUBMITTED'=>'Awaiting review','RETURNED'=>'Returned','APPROVED'=>'Verified','ALL'=>'All submissions'] as $value=>$label): ?><option value="<?= $value ?>" <?= $proofStatus===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><button type="submit">Show</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl($directory.'/sanction_submissions.php')) ?>">Clear</a></form>
    <?php if($proofs===[]): ?><p class="muted">No submissions in this view.</p><?php endif; ?>
    <?php foreach($proofs as $proof):
        $itemsQuery=$database->prepare('SELECT x.task,x.hours,e.name AS event_name,u.email AS monitor_name FROM sanction_submission_items item JOIN event_sanctions x ON x.id=item.sanction_id JOIN events e ON e.id=x.event_id JOIN users u ON u.id=x.monitor_user_id WHERE item.submission_id=? ORDER BY e.event_date,e.id');$itemsQuery->execute([$proof['id']]);$proofItems=$itemsQuery->fetchAll();$proofHours=array_sum(array_column($proofItems,'hours'));
    ?>
        <article class="sanction-review-card">
            <div class="sanction-card-heading"><div><h3><?= escapeHtml(trim($proof['last_name'].', '.$proof['first_name'].' '.$proof['middle_name'])) ?></h3><p><?= escapeHtml($proof['student_number']) ?> &middot; <?= escapeHtml($proof['submitted_at']) ?></p></div><strong><?= escapeHtml($proof['status']==='APPROVED'?'Verified':ucfirst(strtolower($proof['status']))) ?></strong></div>
            <ul class="sanction-task-list"><?php foreach($proofItems as $item): ?><li><strong><?= escapeHtml($item['event_name']) ?> &mdash; <?= escapeHtml($item['hours']) ?> hours</strong><span><?= nl2br(escapeHtml($item['task'])) ?></span><small>Monitor: <?= escapeHtml($item['monitor_name']) ?></small></li><?php endforeach; ?></ul>
            <p><strong>Total required: <?= escapeHtml((string)$proofHours) ?> hours</strong></p>
            <div class="sanction-photo-pair"><?php foreach(['before'=>'Before','after'=>'After'] as $key=>$label): ?><figure><figcaption><?= $label ?></figcaption><a href="<?= escapeHtml(applicationUrl('/clearance/sanction_image.php?id='.(int)$proof['id'].'&photo='.$key)) ?>" target="_blank" rel="noopener"><img src="<?= escapeHtml(applicationUrl('/clearance/sanction_image.php?id='.(int)$proof['id'].'&photo='.$key)) ?>" alt="<?= $label ?> sanction work" loading="lazy"></a></figure><?php endforeach; ?></div>
            <?php if($proof['review_remarks']): ?><p><?= nl2br(escapeHtml($proof['review_remarks'])) ?></p><?php endif; ?>
            <?php if($proof['status']==='SUBMITTED'): ?><form class="sanction-review-form" method="post" action="<?= escapeHtml(applicationUrl('/clearance/sanction_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= (int)$proof['id'] ?>">
                <label class="sanction-monitor-confirm"><input type="checkbox" name="work_verified" value="1">I confirmed the completed work and <?= escapeHtml((string)$proofHours) ?> required hours with the assigned monitor(s).</label>
                <label>Feedback<textarea name="remarks" maxlength="2000" placeholder="Required when returning the submission."></textarea></label>
                <div class="sanction-review-actions"><button name="action" value="approve">Verify sanctions</button><button class="secondary" name="action" value="return">Return for correction</button></div>
            </form><?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if($proofPages>1): ?><nav class="pagination" aria-label="Sanction submission pages"><?php foreach(paginationPages($proofPage,$proofPages) as $n): ?><?php if($n==='ellipsis'): ?><span class="pagination-ellipsis">&hellip;</span><?php else: ?><a class="pagination-link <?= $n===$proofPage?'active':'' ?>" <?= $n===$proofPage?'aria-current="page"':'' ?> href="<?= escapeHtml('?'.http_build_query(['proof_status'=>$proofStatus,'proof_page'=>$n])) ?>"><?= $n ?></a><?php endif; ?><?php endforeach; ?></nav><?php endif; ?>
</section>
