<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/semester_functions.php';

$user = requireRole('REGISTRAR');
$search = trim((string) ($_GET['search'] ?? ''));
$organizationFilter = filter_var($_GET['organization'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$organizationFilter = $organizationFilter === false ? 0 : (int) $organizationFilter;
$programFilter = trim((string) ($_GET['program'] ?? ''));
$yearFilter = filter_var($_GET['year_level'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
$yearFilter = $yearFilter === false ? 0 : (int) $yearFilter;
$enrollmentFilter = strtoupper((string) ($_GET['enrollment'] ?? 'ENROLLED'));
if (!in_array($enrollmentFilter, ['ALL','ENROLLED','REMOVED'], true)) { $enrollmentFilter = 'ALL'; }
$accountFilter = strtoupper((string) ($_GET['account_status'] ?? 'ALL'));
if (!in_array($accountFilter, ['ALL','ACTIVE','INACTIVE'], true)) { $accountFilter = 'ALL'; }
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;
try {
    $database = getDatabaseConnection();
    $period = registrarActivePeriod($database);
    $statement = $database->prepare(
        "SELECT student.id,student.student_number,student.first_name,student.last_name,student.program,
                student.year_level,student.section,user.email,user.status AS account_status,
                organization.id AS organization_id,organization.code AS organization_code,
                COALESCE(enrollment.status,'REMOVED') AS enrollment_status
         FROM students student
         INNER JOIN users user ON user.id=student.user_id
         INNER JOIN organizations organization ON organization.id=student.organization_id
         LEFT JOIN semester_enrollments enrollment ON enrollment.student_id=student.id AND enrollment.academic_period_id=:period_id
         WHERE (student.student_number LIKE :number OR student.first_name LIKE :first_name
            OR student.last_name LIKE :last_name OR user.email LIKE :email)
           AND (:organization_all=0 OR student.organization_id=:organization_id)
           AND (:program_all='' OR student.program=:program)
           AND (:year_all=0 OR student.year_level=:year_level)
           AND (:enrollment_all='ALL' OR COALESCE(enrollment.status,'REMOVED')=:enrollment_status)
           AND (:account_all='ALL' OR user.status=:account_status)
         ORDER BY student.last_name,student.first_name"
    );
    $term = '%' . $search . '%';
    $statement->execute([
        'period_id' => $period['id'], 'number' => $term, 'first_name' => $term,
        'last_name' => $term, 'email' => $term,
        'organization_all' => $organizationFilter, 'organization_id' => $organizationFilter,
        'program_all' => $programFilter, 'program' => $programFilter,
        'year_all' => $yearFilter, 'year_level' => $yearFilter,
        'enrollment_all' => $enrollmentFilter, 'enrollment_status' => $enrollmentFilter,
        'account_all' => $accountFilter, 'account_status' => $accountFilter,
    ]);
    $allStudents = $statement->fetchAll();
    $totalRows = count($allStudents);
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $students = array_slice($allStudents, ($page - 1) * $pageSize, $pageSize);
    $organizations = $database->query("SELECT id,code,name FROM organizations WHERE status='ACTIVE' ORDER BY code")->fetchAll();
    $programQuery = $database->prepare("SELECT DISTINCT program FROM students WHERE program<>'' AND (:organization_all=0 OR organization_id=:organization_id) ORDER BY program");
    $programQuery->execute(['organization_all' => $organizationFilter, 'organization_id' => $organizationFilter]);
    $programs = $programQuery->fetchAll(PDO::FETCH_COLUMN);
    $yearQuery = $database->prepare("SELECT DISTINCT year_level FROM students WHERE (:organization_all=0 OR organization_id=:organization_id) AND (:program_all='' OR program=:program) ORDER BY year_level");
    $yearQuery->execute(['organization_all' => $organizationFilter, 'organization_id' => $organizationFilter, 'program_all' => $programFilter, 'program' => $programFilter]);
    $yearLevels = array_map('intval', $yearQuery->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $exception) {
    error_log('Registrar students failed: ' . $exception->getMessage());
    http_response_code(500); exit('Unable to load student accounts.');
}
$message = pullFlashMessage(); $csrfToken = createCsrfToken(); $logoutForm = dashboardLogoutForm();
$editStudent = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId !== false) { foreach ($allStudents as $candidate) { if ((int) $candidate['id'] === (int) $editId) { $editStudent = $candidate; break; } } }
$filterQuery = http_build_query([
    'search' => $search,
    'organization' => $organizationFilter,
    'program' => $programFilter,
    'year_level' => $yearFilter,
    'enrollment' => $enrollmentFilter,
    'account_status' => $accountFilter,
    'page' => $page,
]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Accounts | ClearGo</title><style>
:root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd;--red:#a33722}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}header{padding:18px 24px;background:#fff;border-bottom:1px solid var(--line)}.topbar,main{max-width:1250px;margin:auto}.topbar{display:flex;justify-content:space-between;align-items:center}h1{margin:0;color:var(--blue);font-size:24px}main{padding:24px}.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:20px}.message{padding:12px;background:#edf6ff;border-left:4px solid var(--blue)}form.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}input,select,button{padding:9px;border:1px solid #b7c8d8;border-radius:5px;font:inherit}button{border:0;background:var(--blue);color:#fff;cursor:pointer}.danger{background:var(--red)}table{border-collapse:collapse;width:100%;min-width:1100px}th,td{border-top:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{font-size:13px;color:#587086}.table-wrap{overflow:auto}.inline{display:flex;gap:6px;flex-wrap:wrap}.filters{align-items:center}.filters input{min-width:250px}.clear-filter{padding:8px}.badge{border-radius:99px;padding:5px 8px;font-size:11px;font-weight:bold;background:#eef1f4}.enrolled,.active{background:#e4f5eb;color:#176b3a}.removed,.inactive{background:#fff0ed;color:#a33722}.muted{font-size:13px;color:#587086}a{color:var(--blue)}@media(max-width:800px){form.grid{grid-template-columns:1fr 1fr}header,main{padding:16px}.filters>*{width:100%}.filters input{min-width:0}}
.pagination{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:14px}.pagination-link,.pagination-ellipsis{display:grid;place-items:center;min-width:42px;height:42px;padding:0 12px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--dark);font-weight:600;text-decoration:none}.pagination-link.active{border-color:var(--blue);background:var(--blue);color:#fff}.pagination-ellipsis{border:0;background:transparent}
</style></head><body><header><div class="topbar"><div><h1>Student Accounts</h1><a href="<?= escapeHtml(applicationUrl('/registrar/dashboard.php')) ?>">Back to Registrar</a></div><?= $logoutForm ?></div></header><main>
<?php if ($message): ?><p class="message"><?= escapeHtml($message) ?></p><?php endif; ?>
<section class="panel"><h2>Create one student account</h2><p class="muted">Initial password: <strong>thelewiscollege</strong>. Students can change it after signing in.</p><form class="grid" method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input name="student_number" required placeholder="Student No."><input name="full_name" required placeholder="Last, First, Middle"><input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" required placeholder="Gsuite Address"><input name="program" required placeholder="Course"><select name="year_level"><?php for ($year=1;$year<=6;$year++): ?><option value="<?= $year ?>">Year <?= $year ?></option><?php endfor; ?></select><select name="organization_id"><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>"><?= escapeHtml($org['code'].' - '.$org['name']) ?></option><?php endforeach; ?></select><input name="section" value="N/A" placeholder="Section"><button name="action" value="create">Create and Enroll</button></form></section>
<section class="panel">
    <form class="inline filters" method="get">
        <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, email">
        <select name="organization"><option value="0">All colleges</option><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>" <?= $organizationFilter===(int)$org['id']?'selected':'' ?>><?= escapeHtml($org['code']) ?></option><?php endforeach; ?></select>
        <select name="program"><option value="">All courses</option><?php foreach ($programs as $program): ?><option value="<?= escapeHtml($program) ?>" <?= $programFilter===$program?'selected':'' ?>><?= escapeHtml($program) ?></option><?php endforeach; ?></select>
        <select name="year_level"><option value="0">All year levels</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $yearFilter===$year?'selected':'' ?>>Year <?= $year ?></option><?php endforeach; ?></select>
        <select name="enrollment"><option value="ENROLLED" <?= $enrollmentFilter==='ENROLLED'?'selected':'' ?>>Current roster</option><option value="REMOVED" <?= $enrollmentFilter==='REMOVED'?'selected':'' ?>>Not enrolled this semester</option><option value="ALL" <?= $enrollmentFilter==='ALL'?'selected':'' ?>>All student accounts</option></select>
        <select name="account_status"><option value="ALL" <?= $accountFilter==='ALL'?'selected':'' ?>>All accounts</option><option value="ACTIVE" <?= $accountFilter==='ACTIVE'?'selected':'' ?>>Active</option><option value="INACTIVE" <?= $accountFilter==='INACTIVE'?'selected':'' ?>>Inactive</option></select>
        <button type="submit">Apply Filters</button>
        <a class="clear-filter" href="<?= escapeHtml(applicationUrl('/registrar/students.php')) ?>">Clear</a>
    </form>
    <p class="muted"><?= escapeHtml(semesterLabel($period)) ?>; enrollment changes are <?= $period['clearance_status']==='PREPARING'?'open':'locked after clearance opening' ?>. Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s).</p>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Account</th><th>Course</th><th>Year / College</th><th>Semester</th><th>Actions</th></tr></thead><tbody>
    <?php if ($students === []): ?><tr><td colspan="6">No students match the selected filters.</td></tr><?php endif; ?>
    <?php foreach ($students as $student): ?><tr><td><strong><?= escapeHtml($student['student_number']) ?></strong><br><?= escapeHtml($student['last_name'].', '.$student['first_name']) ?></td><td><?= escapeHtml($student['email']) ?><br><span class="badge <?= strtolower($student['account_status']) ?>"><?= escapeHtml($student['account_status']) ?></span></td><td><?= escapeHtml($student['program']) ?></td><td>Year <?= (int) $student['year_level'] ?> - <?= escapeHtml($student['organization_code']) ?></td><td><span class="badge <?= strtolower($student['enrollment_status']) ?>"><?= escapeHtml($student['enrollment_status']) ?></span></td><td><div class="inline"><a href="?<?= escapeHtml($filterQuery . '&edit=' . (int) $student['id']) ?>">Edit</a><form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>"><?php if ($period['clearance_status']==='PREPARING'): ?><button name="action" value="<?= $student['enrollment_status']==='ENROLLED'?'remove_enrollment':'enroll' ?>"><?= $student['enrollment_status']==='ENROLLED'?'Remove from semester':'Enroll' ?></button><?php endif; ?><button class="<?= $student['account_status']==='ACTIVE'?'danger':'' ?>" name="action" value="toggle_account"><?= $student['account_status']==='ACTIVE'?'Deactivate':'Activate' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?= renderPagination($page, $totalPages, $_GET) ?>
</section>
<?php if ($editStudent): ?><section class="panel"><h2>Edit <?= escapeHtml($editStudent['student_number']) ?></h2><form class="grid" method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="student_id" value="<?= (int) $editStudent['id'] ?>"><input name="student_number" required value="<?= escapeHtml($editStudent['student_number']) ?>"><input name="full_name" required value="<?= escapeHtml($editStudent['last_name'].', '.$editStudent['first_name']) ?>"><input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" required value="<?= escapeHtml($editStudent['email']) ?>"><input name="program" required value="<?= escapeHtml($editStudent['program']) ?>"><input type="number" min="1" max="6" name="year_level" required value="<?= (int) $editStudent['year_level'] ?>"><select name="organization_id"><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>" <?= (int)$org['id']===(int)$editStudent['organization_id']?'selected':'' ?>><?= escapeHtml($org['code'].' - '.$org['name']) ?></option><?php endforeach; ?></select><input name="section" value="<?= escapeHtml($editStudent['section']) ?>"><button name="action" value="update">Save Changes</button><button name="action" value="reset_password">Reset Password to thelewiscollege</button></form></section><?php endif; ?>
</main></body></html>
