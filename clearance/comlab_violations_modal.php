<?php foreach ($rows as $comlabRow): ?>
<template id="comlab-violations-<?= (int) $comlabRow['clearance_id'] ?>">
    <h3><?= escapeHtml($comlabRow['first_name'] . ' ' . $comlabRow['last_name']) ?></h3>
    <?php $canManage = in_array($comlabRow['station_status'], ['LOCKED', 'PENDING', 'RETURNED'], true) && in_array($comlabRow['clearance_status'], ['PREPARING', 'OPEN'], true);
    $canRecord = in_array($comlabRow['station_status'], ['LOCKED', 'PENDING', 'RETURNED'], true) && in_array($comlabRow['clearance_status'], ['PREPARING', 'OPEN'], true);
    $incidents = officeDetailRecords($database, 'COMLAB', $comlabRow);
    $violationNow = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d\TH:i'); ?>
    <?php if (array_filter($incidents, static fn($incident): bool => $incident['status'] === 'OPEN') !== []): ?>
    <p>The student must visit the Comlab room to resolve any violation before clearance.</p>
    <?php endif; ?>
    <?php if ($incidents === []): ?><p class="muted">No violations recorded.</p><?php endif; ?>
    <?php foreach ($incidents as $incident): ?>
    <div class="record">
        <span class="badge <?= $incident['status'] === 'OPEN' ? 'open' : 'resolved' ?>"><?= $incident['status'] === 'OPEN' ? 'VIOLATED' : 'RESOLVED' ?></span>
        <p><?= nl2br(escapeHtml($incident['description'])) ?></p>
        <p class="muted">Reported <?= escapeHtml($incident['created_at']) ?></p>
        <?php if ($incident['resolved_at']): ?><p class="muted">Resolved <?= escapeHtml($incident['resolved_at']) ?></p><?php endif; ?>
        <?php if ($canManage && $incident['status'] === 'OPEN'): ?>
        <form method="post" action="<?= escapeHtml($actionUrl) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="clearance_id" value="<?= (int) $comlabRow['clearance_id'] ?>">
            <input type="hidden" name="incident_id" value="<?= (int) $incident['id'] ?>">
            <button name="action" value="resolve_comlab_incident">Mark resolved after Comlab visit</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($canRecord): ?>
    <form method="post" action="<?= escapeHtml($actionUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <input type="hidden" name="clearance_id" value="<?= (int) $comlabRow['clearance_id'] ?>">
        <label>Violation details<textarea name="description" required maxlength="2000" placeholder="Describe the violation"></textarea></label>
        <p><label>Violation date and time<input type="datetime-local" name="violation_at" required value="<?= escapeHtml($violationNow) ?>" max="<?= escapeHtml($violationNow) ?>"></label></p>
        <button name="action" value="add_comlab_incident">Save violation</button>
    </form>
    <?php endif; ?>
</template>
<?php endforeach; ?>
<div id="comlab-violation-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="comlab-violation-title">
    <div class="modal" style="max-height:85vh;overflow-y:auto" tabindex="-1">
        <h2 id="comlab-violation-title">Comlab violations</h2>
        <div id="comlab-violation-content"></div>
        <div class="actions"><button type="button" class="secondary" onclick="closeComlabViolationModal()">Close</button></div>
    </div>
</div>
<script src="<?= escapeHtml(applicationUrl('/assets/js/comlab-dashboard.js')) ?>"></script>
<?php if ($selected !== null): ?>
<script>openComlabViolationModal(<?= (int) $selected['clearance_id'] ?>);</script>
<?php endif; ?>
