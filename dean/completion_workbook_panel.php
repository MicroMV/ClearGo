<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
?>
                <?php if ($dean['organization_code'] === 'CCS'): ?><section class="panel completion-panel" aria-labelledby="completion-heading">
                    <div class="section-heading">
                        <div><h2 id="completion-heading">Requirements completion list</h2><p class="muted">Choose Completed to verify applicable Dean requirements, then import. Year 4: Faculty Evaluation in first semester; Application for Graduation in second semester. Blank cells count as Incomplete.</p></div>
                    </div>
                    <?php if (in_array($readiness['clearance_status'], ['PREPARING', 'OPEN'], true)): ?>
                    <form class="completion-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/dean/completion_workbook_import.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                        <input type="hidden" name="period_id" value="<?= (int) $readiness['academic_period_id'] ?>">
                        <div class="completion-upload">
                            <div class="completion-upload-heading">
                                <span class="completion-upload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-7m-3 3 3-3 3 3"/></svg></span>
                                <div><label for="completion-workbook">Upload completion workbook</label><p id="completion-upload-help">Choose your completed Excel template (.xlsx, up to 5 MB).</p></div>
                            </div>
                            <input id="completion-workbook" type="file" name="completion_workbook" accept=".xlsx" aria-describedby="completion-upload-help" required>
                        </div>
                        <div class="completion-footer">
                            <p class="muted">Keep School Year and Semester unchanged.<br>After import, clear students individually or in bulk.</p>
                            <div class="completion-actions">
                                <a class="button-link completion-download" href="<?= escapeHtml(applicationUrl('/dean/completion_workbook_download.php')) ?>"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>Download Excel template</a>
                                <button type="submit"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m-4 4 4-4 4 4M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>Import completion list</button>
                            </div>
                        </div>
                    </form><?php else: ?><p class="muted">Completion imports are closed for this semester.</p><?php endif; ?>
                </section><?php endif; ?>
