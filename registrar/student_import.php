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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Data Import | ClearGo</title>
    <style>
        :root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd;--red:#a33722;--green:#176b3a;--amber:#956200}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}header{background:#fff;border-bottom:1px solid var(--line);padding:18px 24px}.topbar,main{max-width:1200px;margin:auto}.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px}.logout-form{margin:0}button,.button{display:inline-block;border:0;border-radius:5px;background:var(--blue);color:#fff;cursor:pointer;font:inherit;padding:10px 16px;text-decoration:none}.secondary{background:#587086}h1{margin:0;color:var(--blue);font-size:24px}h2{margin-top:0;font-size:20px}main{padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}.message{padding:13px;background:#edf6ff;border-left:4px solid var(--blue)}.result{background:#eaf7ef;border-left-color:var(--green)}.summary{display:flex;gap:12px;flex-wrap:wrap}.summary span{background:#f1f6fa;border-radius:6px;padding:10px 14px}.upload{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.upload label{display:grid;gap:7px}.hint,.muted{color:#587086;font-size:13px}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:960px}th,td{border-top:1px solid var(--line);padding:10px;text-align:left;vertical-align:top}th{color:#587086;font-size:13px}.badge{display:inline-block;border-radius:99px;padding:5px 8px;font-size:11px;font-weight:bold}.add{background:#e4f5eb;color:var(--green)}.update{background:#fff7e8;color:var(--amber)}.invalid{background:#fff0ed;color:var(--red)}ul.issues{margin:5px 0 0;padding-left:18px;color:var(--red)}ul.warnings{margin:5px 0 0;padding-left:18px;color:var(--amber)}.actions{display:flex;gap:10px;align-items:center}.actions form{margin:0}@media(max-width:700px){header,main{padding:16px}.topbar{align-items:flex-start}}
    </style>
</head>
<body>
<header><div class="topbar"><div><h1>Semester Enrollment Import</h1><a href="<?= escapeHtml(applicationUrl('/registrar/dashboard.php')) ?>">Back to Registrar</a></div><?= $logoutForm ?></div></header>
<main>
    <?php if ($message !== null): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
    <?php if (is_array($result)): ?><div class="panel message result"><strong>Semester roster imported.</strong><p>Added: <?= (int) $result['added'] ?> &nbsp; Updated: <?= (int) $result['updated'] ?> &nbsp; Removed from this semester: <?= (int) ($result['removed_from_semester'] ?? 0) ?> &nbsp; Skipped: <?= (int) $result['skipped'] ?> &nbsp; Errors: <?= (int) $result['errors'] ?></p></div><?php endif; ?>
    <section class="panel">
        <h2>Upload student records</h2>
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
            <h2>Preview: <?= escapeHtml((string) ($_SESSION['student_import_name'] ?? 'uploaded file')) ?></h2>
            <div class="summary"><span>Period: <strong><?= escapeHtml((string) ($preview['academic_period'] ?? 'Active period')) ?></strong></span><span>Total: <strong><?= count($preview['rows']) ?></strong></span><span>Valid: <strong><?= (int) $preview['valid_count'] ?></strong></span><span>New: <strong><?= (int) $preview['added_count'] ?></strong></span><span>Updates: <strong><?= (int) $preview['updated_count'] ?></strong></span><span>Records with errors: <strong><?= (int) $preview['error_count'] ?></strong></span></div>
            <p class="muted">Rows with errors will be skipped. Existing Student Nos. retain their account and clearance history. When confirmed, students missing from this complete official file are removed only from the current semester roster.</p>
            <div class="table-wrap"><table><thead><tr><th>Row</th><th>Action</th><th>Student No.</th><th>Full Name</th><th>Course</th><th>Year Level</th><th>Gsuite Address</th><th>Assigned College</th><th>Validation</th></tr></thead><tbody>
            <?php foreach ($preview['rows'] as $row): ?><tr>
                <td><?= (int) $row['row_number'] ?></td><td><span class="badge <?= $row['errors'] !== [] ? 'invalid' : strtolower($row['action']) ?>"><?= $row['errors'] !== [] ? 'INVALID' : escapeHtml($row['action']) ?></span></td>
                <td><?= escapeHtml($row['student_id']) ?></td><td><?= escapeHtml($row['student_name']) ?></td><td><?= escapeHtml($row['course_program']) ?></td><td><?= (int) $row['year_level'] ?: '' ?></td><td><?= escapeHtml($row['school_email']) ?></td><td><?= escapeHtml($row['organization_label']) ?></td>
                <td><?php if ($row['errors'] === [] && $row['warnings'] === []): ?><span class="badge add">VALID</span><?php endif; ?><?php if ($row['errors'] !== []): ?><ul class="issues"><?php foreach ($row['errors'] as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ($row['warnings'] !== []): ?><ul class="warnings"><?php foreach ($row['warnings'] as $warning): ?><li><?= escapeHtml($warning) ?></li><?php endforeach; ?></ul><?php endif; ?></td>
            </tr><?php endforeach; ?></tbody></table></div>
            <div class="actions">
                <form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_import_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="import"><input type="hidden" name="import_token" value="<?= escapeHtml((string) ($_SESSION['student_import_token'] ?? '')) ?>"><button type="submit" <?= (int) $preview['valid_count'] < 1 ? 'disabled' : '' ?>>Import <?= (int) $preview['valid_count'] ?> Enrolled Students</button></form>
                <form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_import_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><button class="secondary" name="action" value="cancel" type="submit">Cancel Preview</button></form>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
