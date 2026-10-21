<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404); exit('Not found');
}
require_once __DIR__ . '/../registrar/semester_functions.php';

// Child tables are deleted first. Foreign key enforcement stays enabled.
const DEBUG_SEMESTER_TABLES = [
    'sanction_submission_items','sanction_submissions','event_sanctions','event_sanction_policies',
    'bookstore_receipt_submissions','event_attendance',
    'clinic_medical_appointments','clearance_station_signatures','ccs_evaluation_completions',
    'requirement_records','clearance_stations','clearances',
    'student_bookstore_fees','accounting_receipts','bookstore_obligations',
    'accounting_obligations','facilities_obligations','medical_requirements','semester_enrollments',
    'staff_signature_recoveries','office_period_readiness','clinic_medical_schedules','events','bookstore_fees',
    'academic_periods',
];
const DEBUG_GLOBAL_ACTIVITY_TABLES = ['library_card_submissions','library_cards','borrowed_books','violations'];

function debugBackupDirectory(): string
{
    return __DIR__ . '/../storage/backups/debug-console';
}

function debugTableIdentifier(string $table): string
{
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $table)) { throw new RuntimeException('Invalid database table.'); }
    return '`' . $table . '`';
}

function debugTableCounts(PDO $database): array
{
    $counts = [];
    foreach (array_merge(DEBUG_SEMESTER_TABLES,DEBUG_GLOBAL_ACTIVITY_TABLES,['notifications','users','students','audit_logs','staff_saved_signatures']) as $table) {
        $counts[$table] = (int)$database->query('SELECT COUNT(*) FROM ' . debugTableIdentifier($table))->fetchColumn();
    }
    return $counts;
}

/** Write a complete database snapshot before reset, inside the caller's transaction. */
function debugCreateBackup(PDO $database): string
{
    if (!$database->inTransaction()) { throw new RuntimeException('Backups require a database transaction.'); }
    $unsupported = $database->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()")->fetchColumn();
    $unsupported += $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_TYPE<>'BASE TABLE' OR ENGINE<>'InnoDB')")->fetchColumn();
    if ((int)$unsupported !== 0) { throw new RuntimeException('Back up this database with the database administrator before resetting it.'); }
    $directory = debugBackupDirectory();
    if (!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory)) {
        throw new RuntimeException('Backup storage is unavailable. No data was reset.');
    }
    $name = 'debug-' . (new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.sql.gz';
    $path = $directory . '/' . $name;
    $stream = gzopen($path . '.partial','wb6');
    if ($stream === false) { throw new RuntimeException('Cannot create a backup. No data was reset.'); }
    $write = static function (string $data) use ($stream): void {
        if (gzwrite($stream,$data) !== strlen($data)) { throw new RuntimeException('Backup could not be completed. No data was reset.'); }
    };
    try {
        $write("-- ClearGo local debug backup. Restore into an empty database with the existing private upload files retained.\nSET NAMES utf8mb4;\nSET @DEBUG_OLD_FK=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($database->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $identifier = debugTableIdentifier($table);
            $schema = $database->query('SHOW CREATE TABLE ' . $identifier)->fetch(PDO::FETCH_NUM)[1];
            $write('DROP TABLE IF EXISTS ' . $identifier . ";\n" . $schema . ";\n");
            $query = $database->query('SELECT * FROM ' . $identifier);
            while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns = array_map('debugTableIdentifier',array_keys($row));
                // Hex literals safely preserve text, passwords, binary signatures and uploaded-image metadata.
                $values = array_map(static fn($value): string => $value === null ? 'NULL' : "X'" . bin2hex((string)$value) . "'",array_values($row));
                $write('INSERT INTO ' . $identifier . ' (' . implode(',',$columns) . ') VALUES (' . implode(',',$values) . ");\n");
            }
        }
        $write("SET FOREIGN_KEY_CHECKS=@DEBUG_OLD_FK;\n-- BACKUP COMPLETE\n");
        if (!gzclose($stream)) { throw new RuntimeException('Backup could not be completed. No data was reset.'); }
        $stream = null;
        if (!rename($path . '.partial',$path)) { throw new RuntimeException('Backup could not be finalized. No data was reset.'); }
    } catch (Throwable $exception) {
        if (is_resource($stream)) { gzclose($stream); }
        // Only this request's incomplete, randomly named file can be removed.
        if (is_file($path . '.partial')) { unlink($path . '.partial'); }
        throw $exception;
    }
    return $name;
}

function debugAudit(PDO $database, ?int $userId, string $action, string $description): void
{
    $query = $database->prepare('INSERT INTO audit_logs (user_id,action,description,new_status) VALUES (?,?,?,?)');
    $query->execute([$userId,'DEBUG_' . $action,$description,'DEBUG']);
}

function debugReadyAllOffices(PDO $database, ?int $userId, int $expectedPeriodId): void
{
    $database->beginTransaction();
    try {
        $period = registrarActivePeriod($database,true);
        if ((int)$period['id'] !== $expectedPeriodId || $period['clearance_status'] !== 'PREPARING') {
            throw new RuntimeException('Refresh this screen. Ready all offices requires the current semester to be in PREPARING.');
        }
        require_once __DIR__ . '/../auth/signature_functions.php';
        $signers = [];
        foreach (READINESS_ROLE_STATIONS as $role => $stationCode) {
            $selected = $database->prepare("SELECT user.id FROM users user JOIN staff_signatures signature ON signature.user_id=user.id
                WHERE user.role=? AND user.status='ACTIVE' AND LENGTH(signature.image_data)>0 ORDER BY user.id LIMIT 1 FOR UPDATE");
            $selected->execute([$role]);
            $signer = $selected->fetchColumn();
            if ($signer === false) { throw new RuntimeException('Save and select an authorized signature for ' . str_replace('_',' ', $stationCode) . ' before using Ready all offices.'); }
            requireStaffSignature($database, (int) $signer);
            $signers[$stationCode] = (int) $signer;
        }
        $query = $database->prepare("INSERT INTO office_period_readiness (academic_period_id,station_id,status,ready_by,ready_at,notes)
            SELECT ?,id,'READY',?,NOW(),'Temporary standalone local debug: all offices marked ready.'
            FROM stations WHERE status='ACTIVE' AND code=?
            ON DUPLICATE KEY UPDATE status='READY',ready_by=VALUES(ready_by),ready_at=NOW(),notes=VALUES(notes)");
        foreach ($signers as $code => $signer) { $query->execute([$expectedPeriodId,$signer,$code]); }
        debugAudit($database,$userId,'READY_ALL','Standalone local debug screen marked all offices ready for semester #' . $expectedPeriodId . '. No students were cleared.');
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function debugResetData(PDO $database, ?int $userId, string $scope, string $confirmation): string
{
    $phrase = match ($scope) { 'semesters'=>'CLEAR SEMESTERS','activity'=>'RESET ACTIVITY','backup'=>'',default=>throw new RuntimeException('Invalid reset action.') };
    if ($confirmation !== $phrase) { throw new RuntimeException('Type ' . $phrase . ' to confirm this reset.'); }
    $databaseName = (string)$database->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'cleargo_debug_' . substr(hash('sha256',$databaseName),0,32);
    $lock = $database->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) { throw new RuntimeException('Another debug operation is running. Try again shortly.'); }
    try {
        // The dump's reads hold shared locks through deletion/commit, keeping the backup
        // consistent with the rows being removed. Do not use TRUNCATE or disable FK checks.
        $database->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $database->beginTransaction();
        $backup = debugCreateBackup($database);
        if ($scope !== 'backup') {
            $database->exec($scope === 'activity' ? 'DELETE FROM notifications' : 'DELETE FROM notifications WHERE academic_period_id IS NOT NULL');
            foreach (DEBUG_SEMESTER_TABLES as $table) {
                if ($scope === 'semesters' && $table === 'event_attendance') {
                    $database->exec('DELETE attendance FROM event_attendance attendance INNER JOIN events event ON event.id=attendance.event_id WHERE event.academic_period_id IS NOT NULL');
                } elseif ($scope === 'semesters' && $table === 'events') {
                    $database->exec('DELETE FROM events WHERE academic_period_id IS NOT NULL');
                } else {
                    $database->exec('DELETE FROM ' . debugTableIdentifier($table));
                }
            }
            if ($scope === 'activity') {
                foreach (DEBUG_GLOBAL_ACTIVITY_TABLES as $table) { $database->exec('DELETE FROM ' . debugTableIdentifier($table)); }
            }
        }
        debugAudit($database,$userId,strtoupper($scope),$scope . ' action completed using local debug screen. Backup: ' . $backup . '. Accounts, students, signatures, configuration, private files and existing audit logs were retained.');
        $database->commit();
        return $backup;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    } finally {
        $release = $database->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
    }
}

function debugBackups(): array
{
    $backups = [];
    $directory = debugBackupDirectory();
    if (!is_dir($directory)) { return []; }
    foreach (new DirectoryIterator($directory) as $file) {
        if (!$file->isFile() || !preg_match('/\Adebug-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}\.sql\.gz\z/',$file->getFilename())) { continue; }
        $backups[] = ['name'=>$file->getFilename(),'size'=>$file->getSize()];
    }
    usort($backups,static fn(array $a,array $b): int => strcmp($b['name'],$a['name']));
    return array_slice($backups,0,10);
}
