<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function semesterNotificationCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function expectNotificationSemesterDenied(callable $operation): void
{
    try { $operation(); } catch (InvalidArgumentException $exception) { return; }
    throw new RuntimeException('An inaccessible notification semester was accepted.');
}

// Exercise rollover without changing the real active semester or its students.
$memory = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$memory->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
$memory->exec("CREATE TABLE academic_periods (id INTEGER PRIMARY KEY,school_year TEXT,semester TEXT,status TEXT,clearance_status TEXT);
    CREATE TABLE users (id INTEGER PRIMARY KEY,role TEXT,status TEXT);
    CREATE TABLE students (id INTEGER PRIMARY KEY,user_id INTEGER,status TEXT);
    CREATE TABLE semester_enrollments (academic_period_id INTEGER,student_id INTEGER,status TEXT);
    CREATE TABLE clearances (id INTEGER PRIMARY KEY,academic_period_id INTEGER,student_id INTEGER);
    CREATE TABLE notifications (id INTEGER PRIMARY KEY,user_id INTEGER,academic_period_id INTEGER,title TEXT,message TEXT,is_read INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE events (id INTEGER PRIMARY KEY,name TEXT,academic_period_id INTEGER,check_in_closes_at TEXT,created_by INTEGER,status TEXT,check_in_token_hash TEXT);
    CREATE TABLE event_attendance (event_id INTEGER,status TEXT);
    INSERT INTO academic_periods VALUES (1,'2026-2027','1ST','CLOSED','CLOSED'),(2,'2026-2027','2ND','ACTIVE','PREPARING'),(3,'2027-2028','1ST','DRAFT','DRAFT');
    INSERT INTO users VALUES (1,'STUDENT','ACTIVE'),(2,'STUDENT','ACTIVE'),(3,'LIBRARY_STAFF','ACTIVE'),(4,'STUDENT','ACTIVE');
    INSERT INTO students VALUES (10,1,'ACTIVE'),(20,2,'ACTIVE'),(40,4,'ACTIVE');
    INSERT INTO semester_enrollments VALUES (1,10,'ENROLLED'),(2,10,'ENROLLED'),(1,20,'ENROLLED'),(2,20,'REMOVED');
    INSERT INTO clearances VALUES (1,2,20);");
$student = ['id' => 1, 'role' => 'STUDENT'];
semesterNotificationCheck(notificationPeriodForUser($memory, $student) === 2, 'Default student semester is incorrect.');
semesterNotificationCheck(notificationPeriodForUser($memory, $student, '1') === 1, 'An enrolled student cannot select their archive.');
expectNotificationSemesterDenied(fn() => notificationPeriodForUser($memory, $student, 3));
expectNotificationSemesterDenied(fn() => notificationPeriodForUser($memory, $student, 'invalid'));
expectNotificationSemesterDenied(fn() => notificationPeriodForUser($memory, ['id' => 2, 'role' => 'STUDENT'], 2));
semesterNotificationCheck(notificationPeriodForUser($memory, ['id' => 2, 'role' => 'STUDENT']) === 1, 'Removed enrollment does not default to the archive.');
semesterNotificationCheck(notificationPeriodForUser($memory, ['id' => 4, 'role' => 'STUDENT'], 0) === 0, 'An empty student dashboard cannot poll notifications.');
expectNotificationSemesterDenied(fn() => notificationPeriodForUser($memory, ['id' => 3, 'role' => 'LIBRARY_STAFF'], 1));
createNotification($memory, 1, 'Clearance update', 'Same event', 1);
semesterNotificationCheck(dashboardNotifications($memory, 1) === [] && dashboardUnreadNotificationCount($memory, 1) === 0, 'Archive alerts leak into the current semester.');
semesterNotificationCheck(createNotification($memory, 1, 'Clearance update', 'Same event'), 'An identical event in a new semester was suppressed.');
semesterNotificationCheck(!createNotification($memory, 1, 'Clearance update', 'Same event'), 'An unread event was duplicated within its semester.');
createNotification($memory, 2, 'Other student', 'Private', 2);
semesterNotificationCheck(count(dashboardNotificationPreview($memory, 1, 1)) === 1 && count(dashboardNotificationPreview($memory, 1, 2)) === 1, 'Per-semester deduplication or ownership is incorrect.');
markSemesterNotificationsRead($memory, 1, 2);
semesterNotificationCheck(dashboardUnreadNotificationCount($memory, 1, 2) === 0 && dashboardUnreadNotificationCount($memory, 1, 1) === 1 && dashboardUnreadNotificationCount($memory, 2, 2) === 1, 'Mark read changed another semester or account.');
semesterNotificationCheck(createNotification($memory, 1, 'Clearance update', 'Same event', 2) && dashboardUnreadNotificationCount($memory, 1, 2) === 1, 'A later event after reading was suppressed.');
$memory->exec("UPDATE academic_periods SET status='CLOSED',clearance_status='CLOSED' WHERE id=2;
    UPDATE academic_periods SET status='ACTIVE',clearance_status='PREPARING' WHERE id=3;
    INSERT INTO semester_enrollments VALUES (3,10,'ENROLLED');");
semesterNotificationCheck(notificationPeriodForUser($memory, $student) === 3 && dashboardNotifications($memory, 1) === [], 'New semester did not start with an empty notification list.');
semesterNotificationCheck(count(dashboardNotifications($memory, 1, 2)) === 1 && dashboardUnreadNotificationCount($memory, 1, 1) === 1, 'Rollover removed historical notifications or changed their read state.');
createNotification($memory, 1, 'New semester', 'Current notice');
semesterNotificationCheck(dashboardNotifications($memory, 1)[0]['title'] === 'New semester', 'A new notification did not attach to the new active semester.');
$memory->exec("INSERT INTO events VALUES (1,'Archived activity',1,'2000-01-01 00:00:00',3,'ACTIVE','expired-token');");
notifyExpiredAttendanceWindows($memory, 3);
semesterNotificationCheck(dashboardNotifications($memory, 3) === [] && count(dashboardNotifications($memory, 3, 1)) === 1, 'An archived QR expiry alert leaked into the new semester.');
semesterNotificationCheck(dashboardNotificationPreview($memory, 1, 0) === [] && dashboardUnreadNotificationCount($memory, 1, 0) === 0, 'An empty semester shows notifications.');

// Verify the actual HTTP endpoint, session ownership, and dashboard wiring with disposable accounts.
function semesterNotificationRequest(string $path, ?array $account = null, ?array $payload = null): array
{
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $curl = curl_init((getenv('CLEAR_GO_TEST_BASE_URL') ?: 'http://localhost/ClearGo') . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false];
    if ($account !== null) { $options[CURLOPT_COOKIE] = 'clear_go_session=' . $account['session_id']; }
    if ($payload !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    $result = ['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'body' => $body, 'json' => json_decode($body, true)];
    curl_close($curl);
    return $result;
}

$database = getDatabaseConnection();
$activeId = activeNotificationPeriodId($database);
$archiveId = (int) $database->query("SELECT id FROM academic_periods WHERE status='CLOSED' ORDER BY id DESC LIMIT 1")->fetchColumn();
semesterNotificationCheck($activeId > 0 && $archiveId > 0, 'HTTP checks require current and archived semesters.');
$accounts = [];
$token = bin2hex(random_bytes(6));
try {
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CCS' LIMIT 1")->fetchColumn();
    foreach (['STUDENT', 'LIBRARY_STAFF'] as $index => $role) {
        $email = 'semester-notifications-' . $token . '-' . $index . '@test.invalid';
        $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,:role,'ACTIVE')")
            ->execute(['email' => $email, 'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'role' => $role]);
        $id = (int) $database->lastInsertId();
        $accounts[$role] = ['id' => $id, 'email' => $email, 'role' => $role, 'session_id' => 'semesternotif' . $token . $index, 'csrf_token' => bin2hex(random_bytes(32))];
        $account = $accounts[$role];
        if ($role === 'STUDENT') {
            $database->prepare("INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES (:user,:number,'Semester','Notification','BSCS',1,:organization,'ACTIVE')")
                ->execute(['user' => $id, 'number' => 'NOTIF-' . strtoupper($token), 'organization' => $organizationId]);
            $studentId = (int) $database->lastInsertId();
            $enrollment = $database->prepare("INSERT INTO semester_enrollments (academic_period_id,student_id,status) VALUES (:period,:student,'ENROLLED')");
            foreach ([$archiveId, $activeId] as $periodId) { $enrollment->execute(['period' => $periodId, 'student' => $studentId]); }
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        // The CLI fixture creates a new session file; HTTP strict mode remains enabled.
        ini_set('session.use_strict_mode', '0');
        session_name('clear_go_session');
        session_save_path(__DIR__ . '/../storage/sessions');
        session_id($account['session_id']);
        session_start();
        $_SESSION = ['authenticated_user' => ['id' => $id, 'email' => $email, 'role' => $role], 'csrf_token' => $account['csrf_token']];
        session_write_close();
        createNotification($database, $id, 'Archive-only test notice', 'Saved semester notification', $archiveId);
        $current = semesterNotificationRequest('/auth/notifications.php', $account);
        semesterNotificationCheck($current['status'] === 200 && $current['json']['academic_period_id'] === $activeId && $current['json']['notifications'] === [] && $current['json']['unread_count'] === 0, $role . ' sees archive notifications in the current semester.');
        createNotification($database, $id, 'Current-only test notice', 'Current semester notification', $activeId);
        $current = semesterNotificationRequest('/auth/notifications.php', $account);
        semesterNotificationCheck($current['status'] === 200 && count($current['json']['notifications']) === 1 && $current['json']['unread_count'] === 1 && $current['json']['notifications'][0]['title'] === 'Current-only test notice', $role . ' current polling is incorrect.');
    }
    $account = $accounts['STUDENT'];
    $archive = semesterNotificationRequest('/auth/notifications.php?period=' . $archiveId, $account);
    semesterNotificationCheck($archive['status'] === 200 && $archive['json']['academic_period_id'] === $archiveId && count($archive['json']['notifications']) === 1 && $archive['json']['notifications'][0]['title'] === 'Archive-only test notice', 'Archive polling shows incorrect notices.');
    foreach ([$archiveId => 'Archive-only test notice', $activeId => 'Current-only test notice'] as $periodId => $title) {
        $page = semesterNotificationRequest('/student/dashboard.php?period=' . $periodId, $account);
        $otherTitle = $periodId === $archiveId ? 'Current-only test notice' : 'Archive-only test notice';
        semesterNotificationCheck($page['status'] === 200 && str_contains($page['body'], '/auth/notifications.php?period=' . $periodId) && str_contains($page['body'], $title) && !str_contains($page['body'], $otherTitle), 'Student dashboard does not match its selected notification semester.');
    }
    $payload = ['action' => 'mark_read', 'csrf_token' => $account['csrf_token']];
    $read = semesterNotificationRequest('/auth/notifications.php?period=' . $archiveId, $account, $payload);
    semesterNotificationCheck($read['status'] === 200 && $read['json']['unread_count'] === 0 && dashboardUnreadNotificationCount($database, $account['id'], $activeId) === 1, 'Reading archived notices changed current alerts.');
    $invalidCsrf = semesterNotificationRequest('/auth/notifications.php?period=' . $activeId, $account, ['action' => 'mark_read', 'csrf_token' => 'invalid']);
    semesterNotificationCheck($invalidCsrf['status'] === 403 && dashboardUnreadNotificationCount($database, $account['id'], $activeId) === 1, 'Invalid CSRF marked notifications read.');
    $invalidPeriod = semesterNotificationRequest('/auth/notifications.php?period=999999999', $account, $payload);
    semesterNotificationCheck($invalidPeriod['status'] === 403 && dashboardUnreadNotificationCount($database, $account['id'], $activeId) === 1, 'A tampered semester request changed notifications.');
    $office = semesterNotificationRequest('/auth/notifications.php?period=' . $archiveId, $accounts['LIBRARY_STAFF']);
    semesterNotificationCheck($office['status'] === 403, 'Office polling accepted a non-active semester.');
    semesterNotificationCheck(semesterNotificationRequest('/auth/notifications.php')['status'] === 401, 'Unauthenticated notification requests are accepted.');
    $database->prepare("UPDATE users SET status='INACTIVE' WHERE id=:id")->execute(['id' => $account['id']]);
    semesterNotificationCheck(semesterNotificationRequest('/auth/notifications.php', $account)['status'] === 401, 'An inactive account can read notifications.');
} finally {
    foreach ($accounts as $account) {
        $database->prepare('DELETE enrollment FROM semester_enrollments enrollment INNER JOIN students student ON student.id=enrollment.student_id WHERE student.user_id=:id')->execute(['id' => $account['id']]);
        $database->prepare('DELETE FROM students WHERE user_id=:id')->execute(['id' => $account['id']]);
        $database->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $account['id']]);
        @unlink(__DIR__ . '/../storage/sessions/sess_' . $account['session_id']);
    }
}
echo "Semester notification rollover, archive selection, unread isolation, ownership and HTTP checks passed.\n";
