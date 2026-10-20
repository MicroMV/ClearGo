<?php if(($sanctionGroups??[])!==[]): ?>
<section class="student-sanctions" aria-labelledby="sanctions-title"><h3 id="sanctions-title">Complete your sanctions</h3><p>One Before/After photo pair covers all outstanding sanctions from the same office. Organization and Student Council submissions are separate.</p>
<?php foreach($sanctionGroups as $ownerCode=>$group):$proof=$group['submission']; ?>
<article class="student-sanction-card"><div class="sanction-card-heading"><h3><?= escapeHtml($group['name']) ?></h3><strong><?= escapeHtml((string)$group['hours']) ?> hours total</strong></div>
<ul class="sanction-task-list"><?php foreach($group['items'] as $item): ?><li><strong><?= escapeHtml($item['event_name']) ?> &mdash; <?= escapeHtml($item['hours']) ?> hours</strong><span><?= nl2br(escapeHtml($item['task'])) ?></span><small>Monitor: <?= escapeHtml($item['monitor_name']) ?></small></li><?php endforeach; ?></ul>
<?php if($proof&&$proof['status']==='SUBMITTED'): ?><p class="sanction-submitted">Your two photos are awaiting office verification. New absences after submission will need verification in your next submission.</p>
<?php else: ?>
<?php if($proof&&$proof['status']==='RETURNED'): ?><p class="sanction-returned"><strong>Please correct your submission:</strong> <?= escapeHtml($proof['review_remarks']??'') ?></p><?php endif; ?>
<form class="sanction-upload-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/student/sanction_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="owner_code" value="<?= escapeHtml($ownerCode) ?>">
<div class="sanction-photo-pair"><label>Before photo<input type="file" name="before_photo" accept="image/jpeg,image/png,image/webp" required></label><label>After photo<input type="file" name="after_photo" accept="image/jpeg,image/png,image/webp" required></label></div>
<p>Submit exactly two different JPG, PNG or WebP photos, up to 2 MB each. Complete the assigned work and hours before submitting.</p><button class="portal-action-button" type="submit"><?= $proof?'Resubmit two photos':'Submit two photos' ?></button></form>
<?php endif; ?>
</article><?php endforeach; ?></section><?php endif; ?>
