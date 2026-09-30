<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$user = requireRole('REGISTRAR');
$message = pullFlashMessage();
$preview = $_SESSION['student_import_preview'] ?? null;
$result = $_SESSION['student_import_result'] ?? null;
unset($_SESSION['student_import_result']);
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$notifications = [];
try {
    $notifications = dashboardNotificationPreview(getDatabaseConnection(), (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Registrar import notifications failed: ' . $exception->getMessage());
}
$sidebar = registrarDashboardSidebar($user, 'import', $logoutForm);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Data Import | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/registrar-student-import.css')) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell">
        <?= $sidebar ?>
        <div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Import Enrollment</h1>
                    <p>Upload, validate, and confirm the official semester roster.</p>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-account"><strong><?= escapeHtml($user['email']) ?></strong><span>Registrar account</span></div><?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
                <?php if (is_array($result)): ?><div class="panel message result"><strong>Semester roster imported.</strong>
                        <p>Added: <?= (int) $result['added'] ?> &nbsp; Updated: <?= (int) $result['updated'] ?> &nbsp; Removed from this semester: <?= (int) ($result['removed_from_semester'] ?? 0) ?> &nbsp; Skipped: <?= (int) $result['skipped'] ?> &nbsp; Errors: <?= (int) $result['errors'] ?></p>
                    </div><?php endif; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Upload student records</h2>
                            <p class="muted">Prepare the official roster for validation before importing it.</p>
                        </div>
                    </div>
                    <p>Upload the official student master list using the same layout as your existing Excel record. Format names as <strong>Last Name, First Name, Middle Name</strong>. The college is assigned automatically from the Course.</p>
                    <form class="upload" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/registrar/student_import_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="preview">
                        <label>Excel or CSV file<input name="student_file" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
                        <button type="submit">Upload and Preview</button><a class="button secondary" href="<?= escapeHtml(applicationUrl('/registrar/student_import_template.php')) ?>">Excel Template</a><a class="button secondary" href="<?= escapeHtml(applicationUrl('/registrar/student_import_template.php?format=csv')) ?>">CSV Template</a>
                    </form>
                    <p class="hint">Accepted: .xlsx or .csv, up to 5 MB and 5,000 student rows. Columns: #, Student No., Full Name, Course, Year Level, Gsuite Address. The importer recognizes a title such as “ACADEMIC YEAR 2026-2027 1ST SEMESTER” above the headers.</p>
                </section>
                <?php if (is_array($preview)): ?>
                    <section class="panel">
                        <div class="section-heading">
                            <div>
                                <h2>Import preview</h2>
                                <p class="muted"><?= escapeHtml((string) ($_SESSION['student_import_name'] ?? 'Uploaded file')) ?></p>
                            </div>
                        </div>
                        <div class="summary"><span>Period: <strong><?= escapeHtml((string) ($preview['academic_period'] ?? 'Active period')) ?></strong></span><span>Total: <strong><?= count($preview['rows']) ?></strong></span><span>Valid: <strong><?= (int) $preview['valid_count'] ?></strong></span><span>New: <strong><?= (int) $preview['added_count'] ?></strong></span><span>Updates: <strong><?= (int) $preview['updated_count'] ?></strong></span><span>Records with errors: <strong><?= (int) $preview['error_count'] ?></strong></span></div>
                        <p class="muted">Rows with errors will be skipped. Existing Student Nos. retain their account and clearance history. When confirmed, students missing from this complete official file are removed only from the current semester roster.</p>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Action</th>
                                        <th>Student No.</th>
                                        <th>Full Name</th>
                                        <th>Course</th>
                                        <th>Year Level</th>
                                        <th>Gsuite Address</th>
                                        <th>Assigned College</th>
                                        <th>Validation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['rows'] as $row): ?><tr>
                                            <td><?= (int) $row['row_number'] ?></td>
                                            <td><span class="badge <?= $row['errors'] !== [] ? 'invalid' : strtolower($row['action']) ?>"><?= $row['errors'] !== [] ? 'INVALID' : escapeHtml($row['action']) ?></span></td>
                                            <td><?= escapeHtml($row['student_id']) ?></td>
                                            <td><?= escapeHtml($row['student_name']) ?></td>
                                            <td><?= escapeHtml($row['course_program']) ?></td>
                                            <td><?= (int) $row['year_level'] ?: '' ?></td>
                                            <td><?= escapeHtml($row['school_email']) ?></td>
                                            <td><?= escapeHtml($row['organization_label']) ?></td>
                                            <td><?php if ($row['errors'] === [] && $row['warnings'] === []): ?><span class="badge add">VALID</span><?php endif; ?><?php if ($row['errors'] !== []): ?><ul class="issues"><?php foreach ($row['errors'] as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ($row['warnings'] !== []): ?><ul class="warnings"><?php foreach ($row['warnings'] as $warning): ?><li><?= escapeHtml($warning) ?></li><?php endforeach; ?></ul><?php endif; ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div>
                        <div class="actions">
                            <form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_import_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="import"><input type="hidden" name="import_token" value="<?= escapeHtml((string) ($_SESSION['student_import_token'] ?? '')) ?>"><button type="submit" <?= (int) $preview['valid_count'] < 1 ? 'disabled' : '' ?>>Import <?= (int) $preview['valid_count'] ?> Enrolled Students</button></form>
                            <form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_import_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button class="secondary" name="action" value="cancel" type="submit">Cancel Preview</button></form>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>"></script>
</body>

</html>