<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

$workbookOffice = match ($stationCode) {
    'LIBRARY' => 'Library',
    'BOOKSTORE' => 'Bookstore',
    'CLINIC' => 'Clinic',
    default => null,
};
if ($workbookOffice === null) { return; }
$workbookDirectory = strtolower($stationCode);
$workbookInputId = $workbookDirectory . '-workbook';
$workbookDescription = match ($stationCode) {
    'LIBRARY' => "Download the CCS, CBE and CTE sheets. Enter <strong>CLEARED</strong> only after the Librarian or Library Desk verifies the student's library card.",
    'BOOKSTORE' => 'Download the CCS, CBE and CTE sheets. Enter <strong>CLEARED</strong> only after all Bookstore obligations are settled.',
    'CLINIC' => 'Download the CCS, CBE and CTE sheets. Enter <strong>CLEARED</strong> in Clearance Status only after medical requirements are complete.',
};
?>
<section class="panel workbook-panel completion-panel" aria-labelledby="<?= $workbookInputId ?>-heading">
    <div class="section-heading">
        <div><h2 id="<?= $workbookInputId ?>-heading"><?= $workbookOffice ?> Excel Workbook</h2><p class="muted"><?= $workbookDescription ?></p></div>
    </div>
    <details class="completion-instructions">
        <summary>How to complete the workbook</summary>
        <p>To search in Excel, open the filter arrow on <strong>Full Name</strong> or <strong>Student Number</strong> and type the name or ID in the filter's Search box. You can also press <strong>Ctrl+F</strong> to find a student.</p>
        <?php if ($stationCode === 'LIBRARY'): ?>
            <p>Use <strong>Borrowed Book?</strong> (Yes/No) and <strong>Book Title(s)</strong> to record borrowing beside each student. Uploads save Library Status and Remarks; borrowing entries remain in your spreadsheet.</p>
        <?php elseif ($stationCode === 'BOOKSTORE'): ?>
            <p><strong>Pending Obligations</strong> includes active event names and amounts; <strong>Pending Balance</strong> includes unpaid event fees. Closed events are excluded. Event Receipt Status is PAID when all active event receipts are confirmed, otherwise UNPAID.</p>
            <p>Verify each student's ID and payment receipt or reference against Bookstore records. Uploads save Clearance Status and Remarks; confirm event receipts on Receipt Submissions. Download again after adding or closing an event. Use remarks to record missing documents or follow-up instructions.</p>
        <?php else: ?>
            <p>Use the official ClearGo workbook to process current student records. Verify medical requirements before marking a student CLEARED, and use Remarks for any follow-up instructions.</p>
        <?php endif; ?>
    </details>
    <form class="completion-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/' . $workbookDirectory . '/workbook_import.php')) ?>" onsubmit="return confirm('Import this workbook and clear students marked CLEARED?');">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <div class="completion-upload">
            <div class="completion-upload-heading">
                <span class="completion-upload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-7m-3 3 3-3 3 3"/></svg></span>
                <div><label for="<?= $workbookInputId ?>">Upload completed <?= $workbookOffice ?> workbook</label><p id="<?= $workbookInputId ?>-help">Choose your completed Excel template (.xlsx, up to 5 MB).</p></div>
            </div>
            <input id="<?= $workbookInputId ?>" type="file" name="<?= $workbookDirectory ?>_workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-describedby="<?= $workbookInputId ?>-help" required>
        </div>
        <div class="completion-footer">
            <p class="muted">Students are matched by Student Number.<br>Name spelling differences will not affect the import.</p>
            <div class="completion-actions">
                <a class="button-link completion-download" href="<?= escapeHtml(applicationUrl('/' . $workbookDirectory . '/workbook_template.php')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>Download 3-sheet template</a>
                <button type="submit"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m-4 4 4-4 4 4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>Upload and clear students</button>
            </div>
        </div>
    </form>
</section>
