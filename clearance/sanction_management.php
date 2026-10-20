<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
// Included by the authenticated Organization / Student Council submissions screen.
$sanctionScope=sanctionStaffScope($database,$user);
$monitorOptions=sanctionMonitors($database,$sanctionScope);
$policyReadiness=officeReadiness($database,$sanctionScope['code']);
$policyEditable=$policyReadiness['readiness_status']==='NOT_READY';
$policy=false;
if($selectedEvent!==null){
    $policyQuery=$database->prepare('SELECT p.*,u.email AS monitor_name FROM event_sanction_policies p JOIN users u ON u.id=p.monitor_user_id WHERE p.event_id=?');
    $policyQuery->execute([$selectedEvent['id']]);$policy=$policyQuery->fetch();
}
$editingPolicy=$policy && $policyEditable && ($_GET['edit_policy']??null)==='1';
?>
<section class="panel sanction-panel">
    <div class="section-heading"><div><h2>Absence sanctions</h2><p class="muted">Set the work, hours per absence, and the staff member who will monitor it. Students submit one Before/After pair for all outstanding sanctions from this office.</p></div></div>
    <?php if(!$policyEditable): ?><p class="sanction-policy-locked">Office is Ready. Adding, editing and deleting sanction policies is locked.</p><?php endif; ?>
    <?php if($selectedEvent===null): ?><p class="muted">Create an event first to set its sanction.</p>
    <?php else: ?>
        <form class="controls" method="get"><label>Event<select name="event" onchange="this.form.submit()"><?php foreach($events as $policyEvent): ?><option value="<?= (int)$policyEvent['id'] ?>" <?= (int)$policyEvent['id']===(int)$selectedEvent['id']?'selected':'' ?>><?= escapeHtml($policyEvent['name'].' - '.$policyEvent['event_date']) ?></option><?php endforeach; ?></select></label><button type="submit">Choose event</button><a class="button-link secondary filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl($directory.'/sanction_submissions.php')) ?>">Clear</a></form>
        <p><strong><?= escapeHtml($selectedEvent['name']) ?></strong></p>
        <?php if(!$policyEditable && !$policy): ?><p class="muted">No sanction policy was configured for this event.</p>
        <?php elseif($policy && !$editingPolicy): ?><div class="sanction-policy-summary"><p><?= nl2br(escapeHtml($policy['task'])) ?></p><p><strong><?= escapeHtml($policy['hours']) ?> hours per absence</strong> &middot; Monitor: <?= escapeHtml($policy['monitor_name']) ?></p><small>Changes to this saved policy do not alter already assigned student sanctions or submitted proof.</small>
            <?php if($policyEditable): ?><div class="sanction-policy-actions"><a class="button-link" href="<?= escapeHtml('?'.http_build_query(['event'=>$selectedEvent['id'],'edit_policy'=>'1'])) ?>">Edit</a><form method="post" action="<?= escapeHtml(applicationUrl('/clearance/sanction_action.php')) ?>" onsubmit="return confirm('Delete this saved sanction policy? Existing student sanctions and submitted proof will remain.');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="policy_delete"><input type="hidden" name="event_id" value="<?= (int)$selectedEvent['id'] ?>"><button class="danger" type="submit">Delete</button></form></div><?php endif; ?>
        </div>
        <?php else: ?><form class="sanction-policy-form" method="post" action="<?= escapeHtml(applicationUrl('/clearance/sanction_action.php')) ?>">
            <?php if($editingPolicy): ?><h3>Edit sanction policy</h3><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="<?= $editingPolicy?'policy_update':'policy' ?>"><input type="hidden" name="event_id" value="<?= (int)$selectedEvent['id'] ?>">
            <label>Sanction / required work<textarea name="task" maxlength="2000" required placeholder="Example: Clean the assigned classroom and complete community service."><?= escapeHtml($policy['task']??'') ?></textarea></label>
            <div class="sanction-policy-fields"><label>Hours per absence<input type="number" name="hours" min="0.01" max="100" step="0.01" value="<?= escapeHtml($policy['hours']??'2') ?>" required></label><label>Assigned monitor<select name="monitor_user_id" required><?php $selectedMonitorId=(int)($policy['monitor_user_id']??$user['id']); ?><option value="" disabled <?= !in_array($selectedMonitorId,array_map('intval',array_column($monitorOptions,'id')),true)?'selected':'' ?>>Choose an active monitor</option><?php foreach($monitorOptions as $monitor): ?><option value="<?= (int)$monitor['id'] ?>" <?= (int)$monitor['id']===$selectedMonitorId?'selected':'' ?>><?= escapeHtml($monitor['email']) ?></option><?php endforeach; ?></select></label></div>
            <p class="muted">This policy assigns sanctions when QR check-in ends. Already assigned student sanctions keep their original requirements.</p><div class="sanction-policy-actions"><button type="submit"><?= $editingPolicy?'Save changes':'Save sanction for this event' ?></button><?php if($editingPolicy): ?><a class="button-link secondary" href="<?= escapeHtml('?'.http_build_query(['event'=>$selectedEvent['id']])) ?>">Cancel</a><?php endif; ?></div>
        </form><?php endif; ?>
    <?php endif; ?>
</section>
