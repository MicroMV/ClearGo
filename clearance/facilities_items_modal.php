<?php
$formatFacilitiesDate = static function (string $value): string {
    try { return (new DateTimeImmutable($value, new DateTimeZone('Asia/Manila')))->format('M j, Y \a\t g:i A'); }
    catch (Exception $exception) { return $value; }
};
foreach ($rows as $facilitiesRow): ?>
<template id="facilities-items-<?= (int) $facilitiesRow['clearance_id'] ?>">
    <?php
    $canManageItems = $facilitiesRow['station_status'] !== 'APPROVED' && in_array($facilitiesRow['clearance_status'], ['PREPARING', 'OPEN'], true);
    $facilitiesItems = officeDetailRecords($database, 'FACILITIES', $facilitiesRow);
    $pendingItems = array_values(array_filter($facilitiesItems, static fn(array $item): bool => $item['status'] === 'OUTSTANDING'));
    $returnedItems = array_values(array_filter($facilitiesItems, static fn(array $item): bool => $item['status'] === 'RETURNED'));
    $borrowingNow = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d\TH:i');
    $studentName = trim(($facilitiesRow['first_name'] ?? '') . ' ' . ($facilitiesRow['last_name'] ?? ''));
    $stateTone = 'waiting';
    if ($facilitiesRow['station_status'] === 'APPROVED') {
        $stateTone = 'complete'; $stateTitle = 'Facilities clearance complete'; $stateCopy = 'This record is available for viewing.';
    } elseif (!$canManageItems) {
        $stateTitle = 'Semester is closed'; $stateCopy = 'You can view the saved items and returns for this student.';
    } elseif ($facilitiesRow['station_status'] === 'LOCKED') {
        $stateTitle = 'Waiting for preceding offices'; $stateCopy = 'You can record borrowing and returns now. Clear this student after the preceding offices are complete.';
    } elseif ($facilitiesRow['eligibility']['eligible']) {
        $stateTone = 'complete'; $stateTitle = 'Ready for Facilities clearance'; $stateCopy = 'All item return requirements are complete. You can clear this student below.';
    } elseif ($pendingItems !== []) {
        $stateTitle = 'Items still need to be returned'; $stateCopy = 'After receiving an item, use Mark as returned below.';
    } else {
        $stateTitle = 'Check Facilities requirements'; $stateCopy = 'Confirm all borrowing and graduation requirements before clearing this student.';
    }
    ?>
    <div class="facilities-student"><div><strong><?= escapeHtml($studentName ?: 'Student record') ?></strong><span><?= escapeHtml(implode(" \u{00B7} ", array_filter([$facilitiesRow['student_number'] ?? '', $facilitiesRow['program'] ?? '', isset($facilitiesRow['year_level']) ? 'Year ' . (int) $facilitiesRow['year_level'] : '']))) ?></span></div><span class="facilities-count"><?= count($pendingItems) ?> to return</span></div>
    <div class="facilities-state is-<?= $stateTone ?>"><span class="facilities-state-icon" aria-hidden="true"><?= $stateTone === 'complete' ? '&#10003;' : '&#8505;' ?></span><div><strong><?= escapeHtml($stateTitle) ?></strong><p><?= escapeHtml($stateCopy) ?></p></div></div>

    <section class="facilities-items-section" aria-label="Items awaiting return">
        <div class="facilities-section-heading"><h3>Items awaiting return</h3><span><?= count($pendingItems) ?> <?= count($pendingItems) === 1 ? 'item' : 'items' ?></span></div>
        <?php if ($pendingItems === []): ?><div class="facilities-empty"><strong>No items awaiting return</strong><p>New borrowed items will appear here when recorded.</p></div><?php endif; ?>
        <?php foreach ($pendingItems as $item): ?>
        <article class="facilities-item-card">
            <div class="facilities-item-heading"><div><span class="facilities-item-type"><?= $item['item_type'] === 'BORROWED_ITEM' ? 'Borrowed item' : 'Graduation item' ?></span><h4><?= escapeHtml($item['description']) ?></h4></div><span class="facilities-item-status">Not yet returned</span></div>
            <p class="facilities-item-date"><?= $item['item_type'] === 'BORROWED_ITEM' ? 'Borrowed' : 'Recorded' ?> <?= escapeHtml($formatFacilitiesDate((string) $item['created_at'])) ?></p>
            <?php if ($item['remarks']): ?><p class="facilities-item-note"><?= nl2br(escapeHtml($item['remarks'])) ?></p><?php endif; ?>
            <?php if ($canManageItems): ?>
            <form class="facilities-return-form" method="post" action="<?= escapeHtml($actionUrl) ?>">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <label>Return note <span>(optional)</span><input type="text" name="remarks" maxlength="1000" placeholder="e.g. Received in good condition"></label>
                <button name="action" value="return_facilities_item">Mark as returned</button>
            </form>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>

    <?php if ($returnedItems !== []): ?>
    <details class="facilities-returned"><summary>Returned items <span><?= count($returnedItems) ?></span></summary><div class="facilities-returned-list">
        <?php foreach ($returnedItems as $item): ?><article><div><strong><?= escapeHtml($item['description']) ?></strong><span>Returned<?= $item['returned_at'] ? ' ' . escapeHtml($formatFacilitiesDate((string) $item['returned_at'])) : '' ?></span><?php if ($item['remarks']): ?><p><?= nl2br(escapeHtml($item['remarks'])) ?></p><?php endif; ?></div><span class="facilities-returned-check" aria-label="Returned">&#10003;</span></article><?php endforeach; ?>
    </div></details>
    <?php endif; ?>

    <?php if ($canManageItems): ?>
    <section class="facilities-borrow-section" aria-labelledby="facilities-add-title">
        <h3 id="facilities-add-title">Record a new borrowed item</h3><p>Use this when issuing an item to the student.</p>
        <form id="facilities-add-item" data-facilities-add-item method="post" action="<?= escapeHtml($actionUrl) ?>">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
            <input type="hidden" name="action" value="add_facilities_item">
            <label>Item name<input type="text" name="description" maxlength="255" required placeholder="e.g. Toga, chairs, tela"></label>
            <label>Date and time borrowed<input type="datetime-local" name="borrowed_at" required value="<?= escapeHtml($borrowingNow) ?>" max="<?= escapeHtml($borrowingNow) ?>"></label>
            <label>Item details <span>(optional)</span><textarea name="remarks" maxlength="1000" rows="2" placeholder="e.g. Asset number, quantity, or condition"></textarea></label>
            <div class="facilities-borrow-submit"><button type="submit">Save borrowed item</button></div>
        </form>
    </section>
    <?php if ($facilitiesRow['station_status'] !== 'LOCKED' && $facilitiesRow['eligibility']['eligible']): ?>
    <section class="facilities-clearance"><div><h3>Facilities clearance</h3><p>All return requirements are complete.</p><p data-facilities-draft-warning hidden>Save the new borrowed item before clearing this student.</p></div>
        <form method="post" action="<?= escapeHtml($actionUrl) ?>" onsubmit="return confirm('Clear this student for Facilities?');">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="clearance_id" value="<?= (int) $facilitiesRow['clearance_id'] ?>">
            <button name="action" value="approve" data-facilities-clear data-clear-eligible="1">Clear Facilities</button>
        </form>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</template>
<?php endforeach; ?>
<div id="facilities-item-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="facilities-item-title" aria-describedby="facilities-item-description">
    <div class="modal facilities-dialog" tabindex="-1">
        <header class="facilities-modal-header"><div><h2 id="facilities-item-title">Borrowed items &amp; returns</h2><p id="facilities-item-description">Manage this student's Facilities items.</p></div><button class="facilities-modal-close" type="button" onclick="closeFacilitiesItemModal()" aria-label="Close borrowed items">&times;</button></header>
        <div id="facilities-item-content"></div>
        <footer class="facilities-modal-footer"><button type="button" class="secondary" onclick="closeFacilitiesItemModal()">Close</button></footer>
    </div>
</div>
<?php if ($selected !== null): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        openFacilitiesItemModal(<?= (int) $selected['clearance_id'] ?>);
    });
</script>
<?php endif; ?>
