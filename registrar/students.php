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
if (!in_array($enrollmentFilter, ['ALL', 'ENROLLED', 'REMOVED'], true)) {
    $enrollmentFilter = 'ALL';
}
$accountFilter = strtoupper((string) ($_GET['account_status'] ?? 'ALL'));
if (!in_array($accountFilter, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
    $accountFilter = 'ALL';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 30;
try {
    $database = getDatabaseConnection();
    $period = registrarActivePeriod($database);
    $statement = $database->prepare(
        "SELECT student.id,student.student_number,student.first_name,student.middle_name,student.last_name,student.program,
                student.year_level,user.email,user.status AS account_status,
                organization.id AS organization_id,organization.code AS organization_code,
                COALESCE(enrollment.status,'REMOVED') AS enrollment_status
         FROM students student
         INNER JOIN users user ON user.id=student.user_id
         INNER JOIN organizations organization ON organization.id=student.organization_id
         LEFT JOIN semester_enrollments enrollment ON enrollment.student_id=student.id AND enrollment.academic_period_id=:period_id
         WHERE (student.student_number LIKE :number OR student.first_name LIKE :first_name
            OR student.middle_name LIKE :middle_name OR student.last_name LIKE :last_name OR user.email LIKE :email)
           AND (:organization_all=0 OR student.organization_id=:organization_id)
           AND (:program_all='' OR student.program=:program)
           AND (:year_all=0 OR student.year_level=:year_level)
           AND (:enrollment_all='ALL' OR COALESCE(enrollment.status,'REMOVED')=:enrollment_status)
           AND (:account_all='ALL' OR user.status=:account_status)
         ORDER BY student.last_name,student.first_name"
    );
    $term = '%' . $search . '%';
    $statement->execute([
        'period_id' => $period['id'],
        'number' => $term,
        'first_name' => $term,
        'middle_name' => $term,
        'last_name' => $term,
        'email' => $term,
        'organization_all' => $organizationFilter,
        'organization_id' => $organizationFilter,
        'program_all' => $programFilter,
        'program' => $programFilter,
        'year_all' => $yearFilter,
        'year_level' => $yearFilter,
        'enrollment_all' => $enrollmentFilter,
        'enrollment_status' => $enrollmentFilter,
        'account_all' => $accountFilter,
        'account_status' => $accountFilter,
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
    $notifications = dashboardNotificationPreview($database, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Registrar students failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load student accounts.');
}
$message = pullFlashMessage();
$csrfToken = createCsrfToken();
$logoutForm = dashboardLogoutForm();
$sidebar = registrarDashboardSidebar($user, 'students', $logoutForm);
$studentStylesVersion = (string) (filemtime(__DIR__ . '/../assets/css/registrar-students.css') ?: 1);
$studentScriptVersion = (string) (filemtime(__DIR__ . '/../assets/js/registrar-students.js') ?: 1);
$staffStylesVersion = (string) (filemtime(__DIR__ . '/../assets/css/staff-dashboard.css') ?: 1);
$editStudent = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId !== false) {
    foreach ($allStudents as $candidate) {
        if ((int) $candidate['id'] === (int) $editId) {
            $editStudent = $candidate;
            break;
        }
    }
}
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
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <?= connectionStatusAssets() ?>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Student Accounts | ClearGo</title>
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/registrar-students.css')) ?>?v=<?= escapeHtml($studentStylesVersion) ?>">
    <link rel="stylesheet" href="<?= escapeHtml(applicationUrl('/assets/css/staff-dashboard.css')) ?>?v=<?= escapeHtml($staffStylesVersion) ?>">
</head>

<body class="staff-dashboard">
    <div class="staff-shell"><?= $sidebar ?><div class="staff-workspace">
            <header class="staff-topbar"><button class="mobile-nav-toggle" type="button" data-sidebar-open aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation">&#9776;</button>
                <div class="topbar-title">
                    <h1>Student Accounts</h1>
                    <p>Manage student records and current-semester enrollment.</p>
                </div>
                <div class="topbar-actions">
                    <?= dashboardNotificationBell($notifications) ?>
                </div>
            </header>
            <main class="staff-main">
                <?php if ($message): ?><p class="message" data-flash-message><?= escapeHtml($message) ?></p><?php endif; ?>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Create one student account</h2>
                            <p class="muted">Add and enroll an individual student in the active semester.</p>
                        </div>
                    </div>
                    <p class="muted">Initial password: <strong>thelewiscollege</strong>. Students can change it after signing in.</p>
                    <form class="grid" method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input name="student_number" required placeholder="Student No."><input name="first_name" maxlength="100" required aria-label="First name" placeholder="First name"><input name="middle_name" maxlength="100" aria-label="Middle name (optional)" placeholder="Middle name (optional)"><input name="last_name" maxlength="100" required aria-label="Last name" placeholder="Last name"><input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" required placeholder="Gsuite Address"><input name="program" required placeholder="Course"><select name="year_level"><?php for ($year = 1; $year <= 6; $year++): ?><option value="<?= $year ?>">Year <?= $year ?></option><?php endfor; ?></select><select name="organization_id"><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>"><?= escapeHtml($org['code'] . ' - ' . $org['name']) ?></option><?php endforeach; ?></select><button name="action" value="create">Create and Enroll</button></form>
                </section>
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h2>Student directory</h2>
                            <p class="muted">Find accounts, review enrollment, and manage existing records.</p>
                        </div>
                    </div>
                    <form class="inline filters" method="get">
                        <input name="search" value="<?= escapeHtml($search) ?>" placeholder="Search student number, name, email">
                        <select name="organization">
                            <option value="0">All colleges</option><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>" <?= $organizationFilter === (int)$org['id'] ? 'selected' : '' ?>><?= escapeHtml($org['code']) ?></option><?php endforeach; ?>
                        </select>
                        <select name="program">
                            <option value="">All courses</option><?php foreach ($programs as $program): ?><option value="<?= escapeHtml($program) ?>" <?= $programFilter === $program ? 'selected' : '' ?>><?= escapeHtml($program) ?></option><?php endforeach; ?>
                        </select>
                        <select name="year_level">
                            <option value="0">All year levels</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endforeach; ?>
                        </select>
                        <select name="enrollment">
                            <option value="ENROLLED" <?= $enrollmentFilter === 'ENROLLED' ? 'selected' : '' ?>>Current roster</option>
                            <option value="REMOVED" <?= $enrollmentFilter === 'REMOVED' ? 'selected' : '' ?>>Not enrolled this semester</option>
                            <option value="ALL" <?= $enrollmentFilter === 'ALL' ? 'selected' : '' ?>>All student accounts</option>
                        </select>
                        <select name="account_status">
                            <option value="ALL" <?= $accountFilter === 'ALL' ? 'selected' : '' ?>>All accounts</option>
                            <option value="ACTIVE" <?= $accountFilter === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                            <option value="INACTIVE" <?= $accountFilter === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <button type="submit">Apply Filters</button>
                        <a class="clear-filter filter-clear" aria-label="Clear filters" href="<?= escapeHtml(applicationUrl('/registrar/students.php')) ?>">Clear</a>
                    </form>
                    <p class="muted"><?= escapeHtml(semesterLabel($period)) ?>; enrollment changes are <?= $period['clearance_status'] === 'PREPARING' ? 'open' : 'unavailable after clearance opening' ?>. Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> student(s).</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Account</th>
                                    <th>Course</th>
                                    <th>Year / College</th>
                                    <th>Semester</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students === []): ?><tr>
                                        <td colspan="7">No students match the selected filters.</td>
                                    </tr><?php endif; ?>
                                <?php foreach ($students as $student): ?><tr>
                                        <td><strong><?= escapeHtml($student['student_number']) ?></strong></td>
                                        <td><?= escapeHtml(trim($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name'])) ?></td>
                                        <td><?= escapeHtml($student['email']) ?><br><span class="badge <?= strtolower($student['account_status']) ?>"><?= escapeHtml($student['account_status']) ?></span></td>
                                        <td><?= escapeHtml($student['program']) ?></td>
                                        <td>Year <?= (int) $student['year_level'] ?> - <?= escapeHtml($student['organization_code']) ?></td>
                                        <td><span class="badge <?= strtolower($student['enrollment_status']) ?>"><?= escapeHtml($student['enrollment_status']) ?></span></td>
                                        <td>
                                            <div class="student-row-actions"><a class="student-edit-button" href="?<?= escapeHtml($filterQuery . '&edit=' . (int) $student['id']) ?>" data-student-edit data-student-record="<?= escapeHtml(json_encode([
                                                'student_id' => (int) $student['id'],
                                                'student_number' => $student['student_number'],
                                                'first_name' => $student['first_name'],
                                                'middle_name' => $student['middle_name'],
                                                'last_name' => $student['last_name'],
                                                'email' => $student['email'],
                                                'program' => $student['program'],
                                                'year_level' => (int) $student['year_level'],
                                                'organization_id' => (int) $student['organization_id'],
                                            ], JSON_THROW_ON_ERROR)) ?>" aria-haspopup="dialog" aria-controls="student-edit-modal">Edit</a>
                                                <form class="student-action-form" method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                                    <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                                                    <?php if ($period['clearance_status'] === 'PREPARING'): ?>
                                                        <button name="action" value="<?= $student['enrollment_status'] === 'ENROLLED' ? 'remove_enrollment' : 'enroll' ?>" title="<?= $student['enrollment_status'] === 'ENROLLED' ? 'Unenroll this semester' : 'Enroll this semester' ?>"<?php if ($student['enrollment_status'] === 'ENROLLED'): ?> data-confirm="<?= escapeHtml('Are you sure you want to unenroll ' . $student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_number'] . ') from this semester? They will no longer be able to process clearance for this semester.') ?>"<?php endif; ?>><?= $student['enrollment_status'] === 'ENROLLED' ? 'Unenroll' : 'Enroll' ?></button>
                                                    <?php endif; ?>
                                                    <button class="<?= $student['account_status'] === 'ACTIVE' ? 'danger' : '' ?>" name="action" value="toggle_account" title="<?= $student['account_status'] === 'ACTIVE' ? 'Disable account' : 'Enable account' ?>"<?php if ($student['account_status'] === 'ACTIVE'): ?> data-confirm="<?= escapeHtml('Are you sure you want to disable the account for ' . $student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_number'] . ')? They will no longer be able to sign in.') ?>"<?php endif; ?>><?= $student['account_status'] === 'ACTIVE' ? 'Disable' : 'Enable' ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?= renderPagination($page, $totalPages, $_GET) ?>
                </section>
            </main>
        </div>
    </div>
    <dialog id="student-edit-modal" class="student-edit-modal" aria-labelledby="student-edit-title" aria-describedby="student-edit-description" data-initial-edit="<?= $editStudent ? 'true' : 'false' ?>">
        <div class="student-edit-heading">
            <div>
                <h2 id="student-edit-title">Edit<?= $editStudent ? ' ' . escapeHtml($editStudent['student_number']) : ' student' ?></h2>
                <p class="muted" id="student-edit-description">Update this student's existing account details.</p>
            </div>
            <button type="button" class="student-edit-close" data-close-student-edit aria-label="Close student editor">&times;</button>
        </div>
        <form method="post" action="<?= escapeHtml(applicationUrl('/registrar/student_action.php')) ?>" data-student-edit-form>
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="student_id" value="<?= (int) ($editStudent['id'] ?? 0) ?>">
            <div class="student-edit-fields">
                <label>Student number<input name="student_number" required autofocus value="<?= escapeHtml($editStudent['student_number'] ?? '') ?>"></label>
                <label>First name<input name="first_name" maxlength="100" required value="<?= escapeHtml($editStudent['first_name'] ?? '') ?>"></label>
                <label>Middle name (optional)<input name="middle_name" maxlength="100" value="<?= escapeHtml($editStudent['middle_name'] ?? '') ?>"></label>
                <label>Last name<input name="last_name" maxlength="100" required value="<?= escapeHtml($editStudent['last_name'] ?? '') ?>"></label>
                <label>School email<input type="email" name="email" pattern=".+@thelewiscollege\.edu\.ph" required value="<?= escapeHtml($editStudent['email'] ?? '') ?>"></label>
                <label>Course<input name="program" required value="<?= escapeHtml($editStudent['program'] ?? '') ?>"></label>
                <label>Year level<input type="number" min="1" max="6" name="year_level" required value="<?= (int) ($editStudent['year_level'] ?? 1) ?>"></label>
                <label>College<select name="organization_id" required><?php foreach ($organizations as $org): ?><option value="<?= (int) $org['id'] ?>" <?= (int) $org['id'] === (int) ($editStudent['organization_id'] ?? 0) ? 'selected' : '' ?>><?= escapeHtml($org['code'] . ' - ' . $org['name']) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="student-edit-footer">
                <div class="student-edit-save-actions">
                    <button type="button" class="secondary" data-close-student-edit>Cancel</button>
                    <button name="action" value="update">Save Changes</button>
                </div>
                <button class="student-edit-reset" name="action" value="reset_password" formnovalidate>Reset Password to thelewiscollege</button>
            </div>
        </form>
    </dialog>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/staff-dashboard.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/staff-dashboard.js') ?>"></script>
    <script src="<?= escapeHtml(applicationUrl('/assets/js/registrar-students.js')) ?>?v=<?= escapeHtml($studentScriptVersion) ?>"></script>
</body>

</html>
