<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/profile_picture_functions.php';

// Run against the local Apache app. Only temporary test accounts and pictures are changed.
define('PROFILE_TEST_BASE_URL', getenv('CLEAR_GO_TEST_BASE_URL') ?: 'http://localhost/ClearGo');
$database = getDatabaseConnection();
$token = bin2hex(random_bytes(6));
$accounts = [];
$files = [];

function profileCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function testProfilePng(int $red, int $blue): string
{
    $chunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat(chr($red) . "\0" . chr($blue), 2), 2))) . $chunk('IEND', '');
}

function profileRequest(string $path, ?array $account = null, ?array $fields = null): array
{
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $curl = curl_init(PROFILE_TEST_BASE_URL . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10];
    if ($account !== null) { $options[CURLOPT_COOKIE] = 'clear_go_session=' . $account['session_id']; }
    if ($fields !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = $fields; }
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    if ($response === false) { throw new RuntimeException('Local HTTP request failed: ' . curl_error($curl)); }
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $result = ['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize), 'type' => curl_getinfo($curl, CURLINFO_CONTENT_TYPE), 'redirect' => curl_getinfo($curl, CURLINFO_REDIRECT_URL)];
    curl_close($curl);
    return $result;
}

function profileRecord(PDO $database, int $userId): array|false
{
    $query = $database->prepare('SELECT stored_name,mime_type FROM user_profile_pictures WHERE user_id=:id');
    $query->execute(['id' => $userId]);
    return $query->fetch();
}

try {
    $files['original'] = tempnam(sys_get_temp_dir(), 'cg-profile-');
    $files['replacement'] = tempnam(sys_get_temp_dir(), 'cg-profile-');
    $files['invalid'] = tempnam(sys_get_temp_dir(), 'cg-profile-');
    $files['large'] = tempnam(sys_get_temp_dir(), 'cg-profile-');
    file_put_contents($files['original'], testProfilePng(255, 0));
    file_put_contents($files['replacement'], testProfilePng(0, 255));
    file_put_contents($files['invalid'], '<?php echo "not a profile picture";');
    file_put_contents($files['large'], testProfilePng(255, 0) . str_repeat('x', PROFILE_PICTURE_MAX_BYTES));
    $organizationId = (int) $database->query("SELECT id FROM organizations WHERE code='CCS' AND status='ACTIVE' LIMIT 1")->fetchColumn();
    $deanStationId = (int) $database->query("SELECT id FROM stations WHERE code='DEAN' LIMIT 1")->fetchColumn();
    profileCheck($organizationId > 0 && $deanStationId > 0, 'Profile test requires the CCS organization and Dean station.');
    $insert = $database->prepare("INSERT INTO users (email,password_hash,role,status) VALUES (:email,:password,:role,'ACTIVE')");
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    foreach (APPLICATION_ROLES as $index => $role) {
        $email = 'profile-' . $token . '-' . $index . '@test.invalid';
        $insert->execute(['email' => $email, 'password' => $passwordHash, 'role' => $role]);
        $id = (int) $database->lastInsertId();
        $account = ['id' => $id, 'email' => $email, 'role' => $role, 'session_id' => 'profiletest' . $token . $index, 'csrf_token' => bin2hex(random_bytes(32))];
        $accounts[$role] = $account;
        if ($role === 'DEAN') {
            $database->prepare("INSERT INTO staff_assignments (user_id,station_id,organization_id,status) VALUES (:user,:station,:organization,'ACTIVE')")
                ->execute(['user' => $id, 'station' => $deanStationId, 'organization' => $organizationId]);
        }
        if ($role === 'STUDENT') {
            $database->prepare("INSERT INTO students (user_id,student_number,first_name,last_name,program,year_level,organization_id,status) VALUES (:user,:number,'Profile','Test','BSCS',1,:organization,'ACTIVE')")
                ->execute(['user' => $id, 'number' => 'PROFILE-STUDENT-' . strtoupper($token), 'organization' => $organizationId]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        ini_set('session.use_strict_mode', '0');
        session_name('clear_go_session');
        session_save_path(__DIR__ . '/../storage/sessions');
        session_id($account['session_id']);
        session_start();
        $_SESSION = ['authenticated_user' => ['id' => $id, 'email' => $email, 'role' => $role], 'csrf_token' => $account['csrf_token']];
        session_write_close();
    }

    $auditDetails = $database->prepare("SELECT audit.old_status,audit.new_status,station.code AS station_code,student.user_id AS student_user_id
        FROM audit_logs audit LEFT JOIN stations station ON station.id=audit.station_id
        LEFT JOIN students student ON student.id=audit.student_id
        WHERE audit.user_id=:id AND audit.action='PROFILE_PICTURE_UPDATED' ORDER BY audit.id DESC LIMIT 1");
    foreach ($accounts as $role => $account) {
        $page = $role === 'STUDENT' ? '/student/dashboard.php' : '/auth/profile.php';
        $response = profileRequest($page, $account);
        profileCheck($response['status'] === 200 && str_contains($response['body'], 'data-profile-picture-form'), $role . ' cannot open picture settings.');
        profileCheck(substr_count($response['body'], 'assets/js/profile-picture.js') === 1, $role . ' picture preview script is missing or duplicated.');
        if ($role !== 'STUDENT') {
            profileCheck(str_contains($response['body'], 'data-sidebar-open'), $role . ' profile page is missing mobile navigation.');
        }
        $response = profileRequest('/auth/profile_picture_action.php', $account, [
            'csrf_token' => $account['csrf_token'], 'user_id' => $accounts['REGISTRAR']['id'],
            'profile_picture' => new CURLFile($files['original'], 'application/octet-stream', '../../picture.php'),
        ]);
        $expectedReturn = $role === 'STUDENT' ? '/student/dashboard.php#account' : '/auth/profile.php';
        profileCheck($response['status'] === 303 && str_ends_with($response['redirect'], $expectedReturn), $role . ' upload did not return to its settings.');
        $record = profileRecord($database, $account['id']);
        profileCheck($record !== false && $record['mime_type'] === 'image/png' && str_ends_with($record['stored_name'], '.png'), $role . ' upload did not use the actual image type or account owner.');
        profileCheck(is_file(profilePicturePath($record['stored_name'])), $role . ' picture was not stored.');
        $auditDetails->execute(['id' => $account['id']]);
        $auditRow = $auditDetails->fetch();
        profileCheck($auditRow['old_status'] === 'NO_PICTURE' && $auditRow['new_status'] === 'UPLOADED', $role . ' first upload is missing its audit change.');
        if ($role === 'STUDENT') {
            profileCheck((int) $auditRow['student_user_id'] === $account['id'] && $auditRow['station_code'] === null, 'Student picture audit is linked to the wrong account or office.');
        } else {
            $expectedOffice = $role === 'ORG_STAFF' ? 'ORGANIZATION' : preg_replace('/_STAFF$/', '', $role);
            profileCheck($auditRow['station_code'] === $expectedOffice && $auditRow['student_user_id'] === null, $role . ' picture audit is missing its office or incorrectly targets a student.');
        }
        $image = profileRequest('/auth/profile_image.php', $account);
        profileCheck($image['status'] === 200 && $image['type'] === 'image/png' && $image['body'] === testProfilePng(255, 0), $role . ' cannot view its uploaded picture.');
        profileCheck(str_contains(strtolower($image['headers']), 'x-content-type-options: nosniff'), 'Profile image response is missing content-type protection.');
        $response = profileRequest($page, $account);
        profileCheck(str_contains($response['body'], 'Update picture') && str_contains($response['body'], $record['stored_name']), $role . ' avatar or update control did not refresh.');
        if ($role === 'STUDENT') {
            profileCheck(substr_count($response['body'], 'alt="Your profile picture"') === 2, 'Student header and account avatars did not both update.');
        } else {
            profileCheck(str_contains($response['body'], '<img class="sidebar-avatar"'), $role . ' sidebar avatar did not update.');
        }
    }

    $studentAccount = $accounts['STUDENT'];
    $previous = profileRecord($database, $studentAccount['id']);
    profileRequest('/auth/profile_picture_action.php', $studentAccount, ['csrf_token' => $studentAccount['csrf_token'], 'profile_picture' => new CURLFile($files['replacement'], 'image/png', 'new.png')]);
    $updated = profileRecord($database, $studentAccount['id']);
    profileCheck($updated['stored_name'] !== $previous['stored_name'] && !is_file(profilePicturePath($previous['stored_name'])), 'Replacement did not remove the old picture.');
    profileCheck(profileRequest('/auth/profile_image.php', $studentAccount)['body'] === testProfilePng(0, 255), 'Replacement image did not take effect.');
    $staffAccount = $accounts['LIBRARY_STAFF'];
    profileCheck(profileRequest('/auth/profile_image.php?user_id=' . $studentAccount['id'], $staffAccount)['body'] === testProfilePng(255, 0), 'An account can access another account picture through an ID parameter.');
    profileCheck(profileRequest('/auth/profile_image.php')['status'] === 303, 'Anonymous users can retrieve profile pictures.');
    profileCheck(profileRequest('/storage/private/profile-pictures/' . $updated['stored_name'])['status'] === 403, 'Profile files are publicly accessible.');

    foreach (['invalid', 'large'] as $fileKey) {
        profileRequest('/auth/profile_picture_action.php', $studentAccount, ['csrf_token' => $studentAccount['csrf_token'], 'profile_picture' => new CURLFile($files[$fileKey], 'image/jpeg', 'invalid.jpg')]);
        profileCheck(profileRecord($database, $studentAccount['id']) === $updated, 'An invalid or oversized upload changed the current picture.');
    }
    profileRequest('/auth/profile_picture_action.php', $studentAccount, ['csrf_token' => 'invalid', 'profile_picture' => new CURLFile($files['original'], 'image/png', 'valid.png')]);
    profileCheck(profileRecord($database, $studentAccount['id']) === $updated, 'An upload without a valid CSRF token changed the picture.');
    $auditCount = $database->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=:id AND action='PROFILE_PICTURE_UPDATED'");
    $auditCount->execute(['id' => $studentAccount['id']]);
    profileCheck((int) $auditCount->fetchColumn() === 2, 'Picture changes were not audited exactly once per successful upload.');
    $auditDetails->execute(['id' => $studentAccount['id']]);
    $replacementAudit = $auditDetails->fetch();
    profileCheck($replacementAudit['old_status'] === 'HAS_PICTURE' && $replacementAudit['new_status'] === 'UPDATED', 'Replacement audit does not distinguish an existing picture.');

    // Earlier picture entries omitted these fields; display context without altering their history.
    $legacyAudit = $database->prepare("INSERT INTO audit_logs (user_id,action,description,new_status) VALUES (:id,'PROFILE_PICTURE_UPDATED','Legacy profile update test','UPDATED')");
    foreach (['DEAN', 'STUDENT'] as $legacyRole) {
        $legacyAccount = $accounts[$legacyRole];
        $legacyAudit->execute(['id' => $legacyAccount['id']]);
        $searchTerm = $legacyRole === 'STUDENT' ? 'PROFILE-STUDENT-' . strtoupper($token) : $legacyAccount['email'];
        $auditPage = profileRequest('/registrar/audit_logs.php?action=PROFILE_PICTURE_UPDATED&search=' . rawurlencode($searchTerm), $accounts['REGISTRAR']);
        profileCheck($auditPage['status'] === 200, 'Audit page failed to render profile activity.');
        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $auditPage['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $rows = (new DOMXPath($document))->query('//tbody/tr[td[last()][contains(.,"Legacy profile update test")]]');
        profileCheck($rows->length === 1, $legacyRole . ' earlier profile activity is missing from account or student search (found ' . $rows->length . ' rows).');
        $cells = $rows->item(0)->getElementsByTagName('td');
        profileCheck(trim($cells->item(4)->textContent) === ($legacyRole === 'DEAN' ? 'Dean' : 'Not applicable'), 'Earlier profile activity shows a missing or incorrect office.');
        profileCheck(str_contains($cells->item(3)->textContent, $legacyRole === 'STUDENT' ? $searchTerm : 'Not applicable'), 'Earlier profile activity shows a missing or incorrect student.');
        profileCheck(trim($cells->item(5)->textContent) === 'Not recorded → UPDATED', 'Earlier profile history was guessed instead of identified as unrecorded.');
    }
    echo "Profile upload, replacement, ownership and audit-context checks passed for all 12 account roles.\n";
} finally {
    foreach ($accounts as $account) {
        $record = profileRecord($database, $account['id']);
        if ($record !== false) { @unlink(profilePicturePath($record['stored_name'])); }
        $database->prepare('DELETE FROM audit_logs WHERE user_id=:id')->execute(['id' => $account['id']]);
        $database->prepare('DELETE FROM staff_assignments WHERE user_id=:id')->execute(['id' => $account['id']]);
        $database->prepare('DELETE FROM students WHERE user_id=:id')->execute(['id' => $account['id']]);
        $database->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $account['id']]);
        @unlink(__DIR__ . '/../storage/sessions/sess_' . $account['session_id']);
    }
    foreach ($files as $file) { if (is_string($file) && is_file($file)) { @unlink($file); } }
}
