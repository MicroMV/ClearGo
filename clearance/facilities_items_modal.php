<?php foreach ($rows as $facilitiesRow): ?>
<template id="facilities-items-<?= (int) $facilitiesRow['clearance_id'] ?>">
    <?php $canManageItems = $facilitiesRow['station_status'] !== 'APPROVED' && in_array($facilitiesRow['clearance_status'], ['PREPARING', 'OPEN'], true);
    $facilitiesItems = officeDetailRecords($database, 'FACILITIES', $facilitiesRow);
    $borrowingNow = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d\TH:i'); ?>
    <?php if ($facilitiesRow['station_status'] === 'APPROVED'): ?><p class="message">This student is already cleared by Facilities.</p><?php elseif ($facilitiesRow['station_status'] === 'LOCKED'): ?><p class="message">Borrowed items can be recorded now. The preceding offices must clear this student before Facilities clearance.</p><?php elseif ($facilitiesRow['eligibility']['eligible']): ?><p class="message">No items are pending return. This student is ready to clear.</p><?php endif; ?>
    <?php if ($facilitiesItems === []): ?><p class="muted">No borrowed items recorded.</p><?php endif; ?>
    <?php foreach ($facilitiesItems as $item): ?>
    <div class="record">
        <strong><?= escapeHtml($item['description']) ?></strong>
        <span class="badge <?= $item['status'] === 'RETURNED' ? 'resolved' : 'pending' ?>"><?= escapeHtml($item['status']) ?></span>
        <p class="muted"><?= $item['item_type'] === 'BORROWED_ITEM' ? 'Borrowed' : 'Recorded' ?> <?= escapeHtml($item['created_at']) ?></p>
        <?php if ($item['remarks']): ?><p class="muted"><?= escapeHtml($item['remarks']) ?></p><?php endif; ?>
        <?php if ($item['returned_at']): ?><p class="muted">Returned <?= escapeHtml($item['returned_at']) ?></p><?php endif; ?>
        <?php if ($canManageItems && $item['status'] === 'OUTSTANDING'): ?>
        <form method="post" action="<?= escapeHtml($actionUrl) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
            <input type="text" name="remarks" maxlength="1000" placeholder="Optional return note" aria-label="Return note">
            <button name="action" value="return_facilities_item">Mark Returned</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($canManageItems): ?>
    <h3>Record borrowed item</h3>
    <form id="facilities-add-item" data-facilities-add-item method="post" action="<?= escapeHtml($actionUrl) ?>">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
        <input type="text" name="description" maxlength="255" required placeholder="Borrowed item" aria-label="Borrowed item">
        <p><label>Borrowed on<input type="datetime-local" name="borrowed_at" required value="<?= escapeHtml($borrowingNow) ?>" max="<?= escapeHtml($borrowingNow) ?>"></label></p>
        <p><input type="text" name="remarks" maxlength="1000" placeholder="Optional item details" aria-label="Item details"></p>
        <input type="hidden" name="action" value="add_facilities_item">
    </form>
    <div class="facilities-modal-actions">
    <button type="submit" form="facilities-add-item">Save borrowed item</button>
    <?php if ($facilitiesRow['station_status'] !== 'LOCKED' && $facilitiesRow['eligibility']['eligible']): ?>
    <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Facilities?');">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
        <button name="action" value="approve" data-facilities-clear data-clear-eligible="<?= $facilitiesRow['eligibility']['eligible'] ? '1' : '0' ?>" <?= !$facilitiesRow['eligibility']['eligible'] ? 'disabled' : '' ?>>Clear Facilities</button>
    </form>
    <?php endif; ?>
    </div>
    <?php endif; ?>
</template>
<?php endforeach; ?>
<div id="facilities-item-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="facilities-item-title">
    <div class="modal" style="max-height: 85vh; overflow-y: auto;" tabindex="-1">
        <h2 id="facilities-item-title">Facilities borrowed items</h2>
        <div id="facilities-item-content"></div>
        <div class="actions"><button type="button" class="secondary" onclick="closeFacilitiesItemModal()">Close</button></div>
    </div>
</div>
<?php if ($selected !== null): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        openFacilitiesItemModal(<?= (int) $selected['clearance_id'] ?>);
    });
</script>
<?php endif; ?>
