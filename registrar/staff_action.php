<?php

declare(strict_types=1);
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/student_import_functions.php';

$registrar = requireRole('REGISTRAR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('Unable to process that request.');
    redirectTo('/registrar/staff_accounts.php');
}

try {
    $database = getDatabaseConnection();
    $action = (string) ($_POST['action'] ?? '');
    $stationId = (int) $database->query("SELECT id FROM stations WHERE code='REGISTRAR' LIMIT 1")->fetchColumn();

    if ($action === 'create') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = strtoupper((string) ($_POST['role'] ?? ''));
        $allowedRoles = ['ORG_STAFF', 'DEAN', 'SASO_STAFF', 'STUDENT_COUNCIL_STAFF', 'LIBRARY_STAFF', 'BOOKSTORE_STAFF', 'CLINIC_STAFF', 'FACILITIES_STAFF', 'COMLAB_STAFF', 'ACCOUNTING_STAFF', 'REGISTRAR'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@thelewiscollege.edu.ph') || !in_array($role, $allowedRoles, true)) {
            throw new RuntimeException('Enter a valid staff role and Lewis College GSuite Address.');
        }
        $organizationId = filter_var($_POST['organization_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (in_array($role, ['ORG_STAFF', 'DEAN'], true) && $organizationId === false) {
            throw new RuntimeException('Select the college for Organization Staff or Dean.');
        }
        if ($role === 'DEAN') {
            $existingDean = $database->prepare(
                "SELECT COUNT(*) FROM staff_assignments assignment
                 INNER JOIN users dean ON dean.id=assignment.user_id AND dean.role='DEAN' AND dean.status='ACTIVE'
                 INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN'
                 WHERE assignment.organization_id=:organization_id AND assignment.status='ACTIVE'"
            );
            $existingDean->execute(['organization_id' => $organizationId]);
            if ((int) $existingDean->fetchColumn() > 0) {
                throw new RuntimeException('That college already has an active Dean account.');
            }
        }
        $database->beginTransaction();
        $insert = $database->prepare("INSERT INTO users(email,password_hash,role,status) VALUES(:email,:hash,:role,'ACTIVE')");
        $insert->execute(['email' => $email, 'hash' => password_hash(CLEAR_GO_INITIAL_PASSWORD, PASSWORD_DEFAULT), 'role' => $role]);
        $newUserId = (int) $database->lastInsertId();
        if (in_array($role, ['ORG_STAFF', 'DEAN'], true)) {
            $stationCode = $role === 'DEAN' ? 'DEAN' : 'ORGANIZATION';
            $organizationStation = (int) $database->query("SELECT id FROM stations WHERE code='" . $stationCode . "' LIMIT 1")->fetchColumn();
            $assignment = $database->prepare("INSERT INTO staff_assignments(user_id,station_id,organization_id,status) VALUES(:user,:station,:organization,'ACTIVE')");
            $assignment->execute(['user' => $newUserId, 'station' => $organizationStation, 'organization' => $organizationId]);
        }
        $description = 'Registrar created ' . $role . ' account ' . $email . '.';
        $auditAction = 'STAFF_ACCOUNT_CREATED';
        $database->commit();
        setFlashMessage('Staff account created with initial password thelewiscollege.');
    } else {
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            throw new RuntimeException('Select a valid staff account.');
        }
        $accountQuery = $database->prepare("SELECT id,email,role FROM users WHERE id=:id AND role<>'STUDENT' LIMIT 1");
        $accountQuery->execute(['id' => $userId]);
        $account = $accountQuery->fetch();
        if ($account === false) {
            throw new RuntimeException('Staff account not found.');
        }

        if ($action === 'update_email') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@thelewiscollege.edu.ph')) {
                throw new RuntimeException('Enter a valid Lewis College GSuite Address.');
            }
            $database->prepare('UPDATE users SET email=:email WHERE id=:id')->execute(['email' => $email, 'id' => $userId]);
            $description = 'Registrar changed the GSuite address for ' . $account['role'] . ' from ' . $account['email'] . ' to ' . $email . '.';
            $auditAction = 'STAFF_EMAIL_CHANGED';
            setFlashMessage('Staff GSuite Address updated.');
        } elseif ($action === 'change_password') {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('The new staff password must contain at least 8 characters.');
            }
            $database->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
            $description = 'Registrar changed the password for ' . $account['role'] . ' account ' . $account['email'] . '.';
            $auditAction = 'STAFF_PASSWORD_CHANGED';
            setFlashMessage('Staff password changed.');
        } elseif ($action === 'reset_default') {
            $database->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash' => password_hash(CLEAR_GO_INITIAL_PASSWORD, PASSWORD_DEFAULT), 'id' => $userId]);
            $description = 'Registrar reset the password for ' . $account['role'] . ' account ' . $account['email'] . ' to the institutional initial password.';
            $auditAction = 'STAFF_PASSWORD_RESET';
            setFlashMessage('Staff password reset to thelewiscollege.');
        } else {
            throw new RuntimeException('Invalid staff account action.');
        }
    }
    $audit = $database->prepare("INSERT INTO audit_logs(user_id,action,station_id,description,new_status) VALUES(:user,:action,:station,:description,'UPDATED')");
    $audit->execute(['user' => $registrar['id'], 'action' => $auditAction, 'station' => $stationId, 'description' => $description]);
} catch (Throwable $exception) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Staff account action failed: ' . $exception->getMessage());
    if ($exception instanceof PDOException) {
        $duplicateEmail = (int) ($exception->errorInfo[1] ?? 0) === 1062
            && in_array($action ?? '', ['create', 'update_email'], true);
        setFlashMessage($duplicateEmail
            ? 'That school email already has an account.'
            : 'Unable to update the staff account. Please try again.');
    } else {
        setFlashMessage($exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to update the staff account.');
    }
}
redirectTo('/registrar/staff_accounts.php');
