<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/student_import_functions.php';
require_once __DIR__ . '/semester_functions.php';

$user = requireRole('REGISTRAR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo('/registrar/students.php');
}

function registrarStudentId(): int
{
    $id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new RuntimeException('Select a valid student.');
    }
    return (int) $id;
}

function validSchoolEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && str_ends_with(strtolower($email), '@thelewiscollege.edu.ph');
}

function auditStudentAccount(PDO $database, int $registrarId, int $stationId, int $studentId, string $action, string $description, ?string $old = null, ?string $new = null): void
{
    $statement = $database->prepare('INSERT INTO audit_logs(user_id,action,station_id,student_id,description,old_status,new_status) VALUES(:user,:action,:station,:student,:description,:old,:new)');
    $statement->execute(['user' => $registrarId, 'action' => $action, 'station' => $stationId, 'student' => $studentId, 'description' => $description, 'old' => $old, 'new' => $new]);
}

try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    $period = registrarActivePeriod($database);
    $stationId = (int) $database->query("SELECT id FROM stations WHERE code='REGISTRAR' LIMIT 1")->fetchColumn();

    if ($action === 'create') {
        $number = normalizeImportText((string) ($_POST['student_number'] ?? ''));
        $name = normalizeImportText((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $program = normalizeImportText((string) ($_POST['program'] ?? ''));
        $year = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
        $organizationId = filter_var($_POST['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $section = normalizeImportText((string) ($_POST['section'] ?? 'N/A'));
        [$firstName, $lastName] = splitStudentName($name);
        if ($number === '' || $firstName === '' || $lastName === '' || !validSchoolEmail($email) || $program === '' || $year === false || $organizationId === false) {
            throw new RuntimeException('Complete all fields with a valid Lewis College GSuite Address.');
        }
        $database->beginTransaction();
        $insertUser = $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(:email,:hash,'STUDENT','ACTIVE')");
        $insertUser->execute(['email' => $email, 'hash' => password_hash(CLEAR_GO_INITIAL_PASSWORD, PASSWORD_DEFAULT)]);
        $insertStudent = $database->prepare("INSERT INTO students(user_id,student_number,first_name,last_name,program,year_level,section,organization_id,status) VALUES(:user_id,:number,:first,:last,:program,:year,:section,:organization,'ACTIVE')");
        $insertStudent->execute(['user_id' => (int) $database->lastInsertId(), 'number' => $number, 'first' => $firstName, 'last' => $lastName, 'program' => $program, 'year' => $year, 'section' => $section ?: 'N/A', 'organization' => $organizationId]);
        $studentId = (int) $database->lastInsertId();
        if ($period['clearance_status'] === 'PREPARING') {
            $enroll = $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status,enrolled_by) VALUES(:period,:student,'ENROLLED',:user)");
            $enroll->execute(['period' => $period['id'], 'student' => $studentId, 'user' => $user['id']]);
            prepareSemesterClearanceRecords($database, (int) $period['id']);
            $database->prepare("UPDATE office_period_readiness SET status='NOT_READY',ready_by=NULL,ready_at=NULL,notes=NULL WHERE academic_period_id=:period")->execute(['period' => $period['id']]);
        }
        auditStudentAccount($database, $user['id'], $stationId, $studentId, 'STUDENT_ACCOUNT_CREATED', 'Registrar created student account ' . $number . '.', null, 'ACTIVE');
        $database->commit();
        setFlashMessage('Student account created with initial password thelewiscollege.');
    } else {
        $studentId = registrarStudentId();
        $query = $database->prepare('SELECT student.*,user.email,user.status AS account_status FROM students student INNER JOIN users user ON user.id=student.user_id WHERE student.id=:id LIMIT 1');
        $query->execute(['id' => $studentId]);
        $student = $query->fetch();
        if ($student === false) {
            throw new RuntimeException('Student account not found.');
        }

        if ($action === 'update') {
            $number = normalizeImportText((string) ($_POST['student_number'] ?? ''));
            $name = normalizeImportText((string) ($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $program = normalizeImportText((string) ($_POST['program'] ?? ''));
            $year = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]);
            $organizationId = filter_var($_POST['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $section = normalizeImportText((string) ($_POST['section'] ?? ''));
            [$firstName, $lastName] = splitStudentName($name);
            if ($number === '' || $firstName === '' || $lastName === '' || !validSchoolEmail($email) || $program === '' || $year === false || $organizationId === false) {
                throw new RuntimeException('Complete all fields with a valid Lewis College GSuite Address.');
            }
            $database->beginTransaction();
            $database->prepare('UPDATE users SET email=:email WHERE id=:id')->execute(['email' => $email, 'id' => $student['user_id']]);
            $database->prepare('UPDATE students SET student_number=:number,first_name=:first,last_name=:last,program=:program,year_level=:year,section=:section,organization_id=:organization WHERE id=:id')->execute(['number' => $number, 'first' => $firstName, 'last' => $lastName, 'program' => $program, 'year' => $year, 'section' => $section ?: 'N/A', 'organization' => $organizationId, 'id' => $studentId]);
            auditStudentAccount($database, $user['id'], $stationId, $studentId, 'STUDENT_ACCOUNT_UPDATED', 'Registrar modified the student account.', null, 'UPDATED');
            $database->commit();
            setFlashMessage('Student account updated.');
        } elseif ($action === 'toggle_account') {
            $newStatus = $student['account_status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
            $database->beginTransaction();
            $database->prepare('UPDATE users SET status=:status WHERE id=:id')->execute(['status' => $newStatus, 'id' => $student['user_id']]);
            $database->prepare('UPDATE students SET status=:status WHERE id=:id')->execute(['status' => $newStatus, 'id' => $studentId]);
            auditStudentAccount($database, $user['id'], $stationId, $studentId, 'STUDENT_ACCOUNT_STATUS_CHANGED', 'Registrar changed the student account status.', $student['account_status'], $newStatus);
            $database->commit();
            setFlashMessage('Account status changed to ' . $newStatus . '.');
        } elseif ($action === 'enroll' || $action === 'remove_enrollment') {
            if ($period['clearance_status'] !== 'PREPARING') {
                throw new RuntimeException('Enrollment changes are unavailable after clearance opens.');
            }
            $newStatus = $action === 'enroll' ? 'ENROLLED' : 'REMOVED';
            $statement = $database->prepare("INSERT INTO semester_enrollments(academic_period_id,student_id,status,enrolled_by,enrolled_at,removed_by,removed_at) VALUES(:period,:student,:status,:enrolled_by,NOW(),:removed_by,CASE WHEN :removed_status='REMOVED' THEN NOW() ELSE NULL END) ON DUPLICATE KEY UPDATE status=VALUES(status),enrolled_by=VALUES(enrolled_by),enrolled_at=CASE WHEN VALUES(status)='ENROLLED' THEN NOW() ELSE enrolled_at END,removed_by=VALUES(removed_by),removed_at=VALUES(removed_at)");
            $statement->execute(['period' => $period['id'], 'student' => $studentId, 'status' => $newStatus, 'enrolled_by' => $newStatus === 'ENROLLED' ? $user['id'] : null, 'removed_by' => $newStatus === 'REMOVED' ? $user['id'] : null, 'removed_status' => $newStatus]);
            if ($newStatus === 'ENROLLED') {
                prepareSemesterClearanceRecords($database, (int) $period['id']);
            }
            $database->prepare("UPDATE office_period_readiness SET status='NOT_READY',ready_by=NULL,ready_at=NULL,notes=NULL WHERE academic_period_id=:period")->execute(['period' => $period['id']]);
            auditStudentAccount($database, $user['id'], $stationId, $studentId, 'SEMESTER_ENROLLMENT_CHANGED', 'Registrar changed current-semester enrollment.', null, $newStatus);
            setFlashMessage('Semester enrollment changed to ' . $newStatus . '.');
        } elseif ($action === 'reset_password') {
            $database->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash' => password_hash(CLEAR_GO_INITIAL_PASSWORD, PASSWORD_DEFAULT), 'id' => $student['user_id']]);
            auditStudentAccount($database, $user['id'], $stationId, $studentId, 'STUDENT_PASSWORD_RESET', 'Registrar reset the student password to the institutional initial password.', null, 'RESET');
            setFlashMessage('Student password reset to thelewiscollege.');
        } else {
            throw new RuntimeException('Invalid account action.');
        }
    }
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Registrar student action failed: ' . $exception->getMessage());
    setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to update the student account.');
}
redirectTo('/registrar/students.php');
