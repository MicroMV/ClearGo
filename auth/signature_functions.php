<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../clearance/readiness_functions.php';

const STAFF_SIGNATURE_MAX_POINTS = 12000;
const STAFF_SIGNATURE_WIDTH = 900;
const STAFF_SIGNATURE_HEIGHT = 240;

function staffSignature(PDO $database, int $userId): array|false
{
    $query = $database->prepare(
        'SELECT saved_signature_id,printed_name,position_title,mime_type,updated_at
         FROM staff_signatures WHERE user_id=:user_id LIMIT 1'
    );
    $query->execute(['user_id' => $userId]);
    return $query->fetch();
}

function staffSavedSignatures(PDO $database, int $userId): array
{
    $query = $database->prepare(
        'SELECT id,printed_name,position_title,created_at FROM staff_saved_signatures
         WHERE user_id=:user_id ORDER BY id DESC'
    );
    $query->execute(['user_id' => $userId]);
    return $query->fetchAll();
}

/** Lock the same semester/readiness rows as Ready to serialize concurrent changes. */
function staffSignatureManagementState(PDO $database, array $user, bool $lock = false): array
{
    $account = $database->prepare("SELECT role FROM users WHERE id=? AND status='ACTIVE' AND role<>'STUDENT'");
    $account->execute([$user['id']]);
    $role = $account->fetchColumn();
    if ($role === false) { throw new RuntimeException('Only active staff can manage office signatures.'); }
    $period = $database->query("SELECT id,clearance_status FROM academic_periods WHERE status='ACTIVE' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''))->fetch();
    if ($period === false) { return ['locked' => false, 'reason' => '']; }
    $stationCode = READINESS_ROLE_STATIONS[$role] ?? null;
    if ($stationCode !== null) {
        $query = $database->prepare('SELECT readiness.status FROM office_period_readiness readiness JOIN stations station ON station.id=readiness.station_id WHERE readiness.academic_period_id=? AND station.code=?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$period['id'], $stationCode]);
        $locked = $query->fetchColumn() === 'READY';
    } else {
        // The Registrar has no office Ready state; its approvals still capture immutable signatures.
        $locked = false;
    }
    return ['locked' => $locked, 'period_id' => (int)$period['id'], 'reason' => $locked ? 'Your office is Ready, so signatures are locked. Undo Ready during semester preparation to manage signatures.' : ''];
}

function staffSignatureRecovery(PDO $database, int $userId, bool $lock = false): array|false
{
    $query = $database->prepare("SELECT recovery.id,recovery.expires_at FROM staff_signature_recoveries recovery
        JOIN academic_periods period ON period.id=recovery.academic_period_id AND period.status='ACTIVE' AND period.clearance_status IN ('PREPARING','OPEN')
        WHERE recovery.user_id=? AND recovery.consumed_at IS NULL AND recovery.expires_at>NOW()" . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$userId]);
    return $query->fetch();
}

function authorizeStaffSignatureRecovery(PDO $database, int $registrarId, int $staffId, string $reason): void
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) { throw new RuntimeException('Enter a recovery reason between 5 and 300 characters.'); }
    $database->beginTransaction();
    try {
        $registrar = $database->prepare("SELECT id FROM users WHERE id=? AND role='REGISTRAR' AND status='ACTIVE'");
        $registrar->execute([$registrarId]);
        if ($registrar->fetchColumn() === false) { throw new RuntimeException('Only the Registrar can authorize signature recovery.'); }
        $state = staffSignatureManagementState($database, ['id'=>$staffId], true);
        if (!$state['locked'] || staffSignature($database,$staffId) !== false) {
            throw new RuntimeException('Recovery is available only for a Ready office account with no selected signature.');
        }
        $period = $database->prepare("SELECT id FROM academic_periods WHERE id=? AND status='ACTIVE' AND clearance_status IN ('PREPARING','OPEN')");
        $period->execute([$state['period_id']]);
        if ($period->fetchColumn() === false) { throw new RuntimeException('Signature recovery is unavailable for this semester.'); }
        $database->prepare("INSERT INTO staff_signature_recoveries(academic_period_id,user_id,authorized_by,reason,expires_at)
            VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))
            ON DUPLICATE KEY UPDATE authorized_by=VALUES(authorized_by),reason=VALUES(reason),authorized_at=NOW(),expires_at=VALUES(expires_at),consumed_at=NULL")
            ->execute([$state['period_id'],$staffId,$registrarId,$reason]);
        $database->prepare("INSERT INTO audit_logs(user_id,action,description) VALUES (?,'SIGNATURE_RECOVERY_AUTHORIZED',?)")
            ->execute([$registrarId,'Authorized one-time signature setup for staff #' . $staffId . ', semester #' . $state['period_id'] . '. Reason: ' . $reason]);
        createNotification($database,$staffId,'Signature setup authorized','The Registrar authorized one-time signature setup in My Profile for 24 hours. Previous approvals and office readiness remain unchanged.',(int)$state['period_id']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

/** Recovery permits only establishing a missing selection; editing/deletion keep the normal Ready lock. */
function assertStaffSignatureSetupAllowed(PDO $database, array $user): void
{
    $state = staffSignatureManagementState($database,$user,true);
    if (!$state['locked']) { return; }
    if (staffSignature($database,(int)$user['id']) === false && staffSignatureRecovery($database,(int)$user['id'],true) !== false) { return; }
    throw new RuntimeException('Your office is Ready. If your selected signature is missing, ask the Registrar to authorize one-time signature setup in Staff Accounts.');
}

function consumeStaffSignatureRecovery(PDO $database, int $userId): void
{
    $update = $database->prepare("UPDATE staff_signature_recoveries recovery JOIN academic_periods period ON period.id=recovery.academic_period_id AND period.status='ACTIVE'
        SET recovery.consumed_at=NOW() WHERE recovery.user_id=? AND recovery.consumed_at IS NULL AND recovery.expires_at>NOW()");
    $update->execute([$userId]);
    if ($update->rowCount() > 0) {
        $database->prepare("INSERT INTO audit_logs(user_id,action,description) VALUES (?,'SIGNATURE_RECOVERY_COMPLETED','Established the missing signature selection. Office readiness and previous approvals were unchanged.')")->execute([$userId]);
    }
}

function assertStaffSignatureEditable(PDO $database, array $user): void
{
    $state = staffSignatureManagementState($database, $user, true);
    if ($state['locked']) { throw new RuntimeException($state['reason']); }
}

function requireStaffSignature(PDO $database, int $userId): void
{
    $query = $database->prepare("SELECT signature.user_id FROM staff_signatures signature JOIN users user ON user.id=signature.user_id AND user.status='ACTIVE' AND user.role<>'STUDENT' WHERE signature.user_id=? AND LENGTH(signature.image_data)>0 AND signature.printed_name<>'' AND signature.position_title<>'' FOR UPDATE");
    $query->execute([$userId]);
    if ($query->fetchColumn() === false) {
        throw new RuntimeException('Save and select an authorized signature with a name and position in My Profile before marking Ready or approving clearance.');
    }
}

/** Render validated drawing strokes as a standard PNG, without trusting client image data. */
function renderStaffSignature(mixed $drawing): array
{
    $invalid = 'Please draw your signature in the signing box before saving.';
    if (!is_string($drawing) || strlen($drawing) > 400000) {
        throw new RuntimeException($invalid);
    }
    try {
        $decoded = json_decode($drawing, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException($invalid);
    }
    $strokes = $decoded;
    $height = STAFF_SIGNATURE_HEIGHT;
    if (is_array($decoded) && !array_is_list($decoded)) {
        $ratio = $decoded['aspect_ratio'] ?? null;
        if ((!is_int($ratio) && !is_float($ratio)) || !is_finite((float)$ratio) || $ratio < 1 || $ratio > 8) {
            throw new RuntimeException($invalid);
        }
        $height = (int) round(STAFF_SIGNATURE_WIDTH / $ratio);
        $strokes = $decoded['strokes'] ?? null;
    }
    if (!is_array($strokes) || !array_is_list($strokes) || $strokes === [] || count($strokes) > 200) {
        throw new RuntimeException($invalid);
    }
    $count = 0;
    $distance = 0.0;
    foreach ($strokes as $stroke) {
        if (!is_array($stroke) || !array_is_list($stroke) || $stroke === []) {
            throw new RuntimeException($invalid);
        }
        $previous = null;
        foreach ($stroke as $point) {
            if (!is_array($point) || !array_is_list($point) || count($point) !== 2) {
                throw new RuntimeException($invalid);
            }
            foreach ($point as $coordinate) {
                if ((!is_int($coordinate) && !is_float($coordinate)) || !is_finite((float) $coordinate)
                    || $coordinate < 0 || $coordinate > 1) {
                    throw new RuntimeException($invalid);
                }
            }
            if (++$count > STAFF_SIGNATURE_MAX_POINTS) {
                throw new RuntimeException('The drawing is too long. Clear the box and sign again.');
            }
            if ($previous !== null) {
                $distance += hypot(($point[0] - $previous[0]) * STAFF_SIGNATURE_WIDTH, ($point[1] - $previous[1]) * $height);
            }
            $previous = $point;
        }
    }
    if ($distance < 12 || $distance > 100000) {
        throw new RuntimeException($invalid);
    }

    $width = STAFF_SIGNATURE_WIDTH;
    $rows = array_fill(0, $height, str_repeat("\0", $width * 4));
    $ink = "\x12\x35\x68\xff";
    $dot = static function (float $x, float $y) use (&$rows, $width, $height, $ink): void {
        for ($py = max(0, (int) floor($y - 1.5)); $py <= min($height - 1, (int) ceil($y + 1.5)); $py++) {
            for ($px = max(0, (int) floor($x - 1.5)); $px <= min($width - 1, (int) ceil($x + 1.5)); $px++) {
                if (($px - $x) ** 2 + ($py - $y) ** 2 <= 2.25) {
                    for ($channel = 0; $channel < 4; $channel++) {
                        $rows[$py][$px * 4 + $channel] = $ink[$channel];
                    }
                }
            }
        }
    };
    foreach ($strokes as $stroke) {
        $previous = null;
        foreach ($stroke as $point) {
            $x = $point[0] * ($width - 1);
            $y = $point[1] * ($height - 1);
            if ($previous === null) {
                $dot($x, $y);
            } else {
                $steps = max(1, (int) ceil(hypot($x - $previous[0], $y - $previous[1])));
                for ($step = 1; $step <= $steps; $step++) {
                    $dot($previous[0] + ($x - $previous[0]) * $step / $steps, $previous[1] + ($y - $previous[1]) * $step / $steps);
                }
            }
            $previous = [$x, $y];
        }
    }
    $chunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $pixels = '';
    foreach ($rows as $row) {
        $pixels .= "\0" . $row;
    }
    $imageData = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($pixels, 6)) . $chunk('IEND', '');
    return ['data' => $imageData, 'mime_type' => 'image/png', 'sha256' => hash('sha256', $imageData)];
}

function saveStaffSignature(PDO $database, array $user, string $printedName, string $positionTitle, mixed $drawing): void
{
    if (($user['role'] ?? 'STUDENT') === 'STUDENT') {
        throw new RuntimeException('Only authorized staff can register an office signature.');
    }
    $printedName = trim(preg_replace('/\s+/u', ' ', $printedName) ?? '');
    $positionTitle = trim(preg_replace('/\s+/u', ' ', $positionTitle) ?? '');
    if (mb_strlen($printedName) < 2 || mb_strlen($printedName) > 150) {
        throw new RuntimeException('Enter the authorized signatory’s full name.');
    }
    if (mb_strlen($positionTitle) < 2 || mb_strlen($positionTitle) > 150) {
        throw new RuntimeException('Enter the authorized signatory’s position.');
    }
    $image = renderStaffSignature($drawing);

    $database->beginTransaction();
    try {
        assertStaffSignatureSetupAllowed($database, $user);
        $account = $database->prepare("SELECT id,role FROM users WHERE id=:id AND status='ACTIVE' AND role<>'STUDENT' FOR UPDATE");
        $account->execute(['id' => $user['id']]);
        if ($account->fetch() === false) {
            throw new RuntimeException('Your staff account is no longer active.');
        }
        $save = $database->prepare(
            'INSERT INTO staff_saved_signatures (user_id,printed_name,position_title,image_data,mime_type,image_sha256)
             VALUES (:user_id,:printed_name,:position_title,:image_data,:mime_type,:image_sha256)'
        );
        $save->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
        $save->bindValue(':printed_name', $printedName);
        $save->bindValue(':position_title', $positionTitle);
        $save->bindValue(':image_data', $image['data'], PDO::PARAM_LOB);
        $save->bindValue(':mime_type', $image['mime_type']);
        $save->bindValue(':image_sha256', $image['sha256']);
        $save->execute();
        $savedId = (int) $database->lastInsertId();
        // The first signature is selected automatically. Additional signatures
        // remain in the library until the owner explicitly chooses one.
        if (staffSignature($database, (int) $user['id']) === false) {
            activateStaffSignature($database, (int) $user['id'], $savedId);
        }
        consumeStaffSignatureRecovery($database, (int)$user['id']);

        $audit = $database->prepare(
            "INSERT INTO audit_logs (user_id,action,description,new_status)
             VALUES (:user_id,'AUTHORIZED_SIGNATURE_SAVED',:description,'SAVED')"
        );
        $audit->execute(['user_id' => $user['id'], 'description' => 'Saved signature #' . $savedId . ' for ' . $printedName . ' (' . $positionTitle . ').']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

/** Called inside a transaction with the owning user locked. */
function activateStaffSignature(PDO $database, int $userId, int $signatureId): void
{
    $query = $database->prepare('SELECT id FROM staff_saved_signatures WHERE id=:id AND user_id=:user_id');
    $query->execute(['id' => $signatureId, 'user_id' => $userId]);
    if ($query->fetchColumn() === false) {
        throw new RuntimeException('Choose one of your saved signatures.');
    }
    $activate = $database->prepare(
        'INSERT INTO staff_signatures (user_id,saved_signature_id,printed_name,position_title,image_data,mime_type,image_sha256)
         SELECT user_id,id,printed_name,position_title,image_data,mime_type,image_sha256
         FROM staff_saved_signatures WHERE id=:id AND user_id=:user_id
         ON DUPLICATE KEY UPDATE saved_signature_id=VALUES(saved_signature_id),printed_name=VALUES(printed_name),
             position_title=VALUES(position_title),image_data=VALUES(image_data),mime_type=VALUES(mime_type),
             image_sha256=VALUES(image_sha256),updated_at=NOW()'
    );
    $activate->execute(['id' => $signatureId, 'user_id' => $userId]);
}

function selectStaffSignature(PDO $database, array $user, int $signatureId): void
{
    $database->beginTransaction();
    try {
        assertStaffSignatureSetupAllowed($database, $user);
        $account = $database->prepare("SELECT id FROM users WHERE id=:id AND status='ACTIVE' AND role<>'STUDENT' FOR UPDATE");
        $account->execute(['id' => $user['id']]);
        if ($account->fetchColumn() === false) {
            throw new RuntimeException('Only active staff can select an office signature.');
        }
        activateStaffSignature($database, (int) $user['id'], $signatureId);
        consumeStaffSignatureRecovery($database, (int)$user['id']);
        $audit = $database->prepare(
            "INSERT INTO audit_logs (user_id,action,description,new_status)
             VALUES (:user_id,'AUTHORIZED_SIGNATURE_SELECTED',:description,'SELECTED')"
        );
        $audit->execute(['user_id' => $user['id'], 'description' => 'Selected saved signature #' . $signatureId . ' for future clearance approvals.']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function updateStaffSignature(PDO $database, array $user, int $signatureId, string $printedName, string $positionTitle, mixed $drawing): void
{
    $printedName = trim(preg_replace('/\s+/u', ' ', $printedName) ?? '');
    $positionTitle = trim(preg_replace('/\s+/u', ' ', $positionTitle) ?? '');
    if (mb_strlen($printedName) < 2 || mb_strlen($printedName) > 150 || mb_strlen($positionTitle) < 2 || mb_strlen($positionTitle) > 150) {
        throw new RuntimeException('Enter a name and position between 2 and 150 characters.');
    }
    $image = $drawing === '' ? null : renderStaffSignature($drawing);
    $database->beginTransaction();
    try {
        assertStaffSignatureEditable($database, $user);
        $owned = $database->prepare('SELECT id FROM staff_saved_signatures WHERE id=? AND user_id=? FOR UPDATE');
        $owned->execute([$signatureId, $user['id']]);
        if ($owned->fetchColumn() === false) { throw new RuntimeException('Choose one of your saved signatures.'); }
        $sql = 'UPDATE staff_saved_signatures SET printed_name=?,position_title=?';
        $values = [$printedName, $positionTitle];
        if ($image !== null) {
            $sql .= ',image_data=?,mime_type=?,image_sha256=?';
            array_push($values, $image['data'], $image['mime_type'], $image['sha256']);
        }
        array_push($values, $signatureId, $user['id']);
        $database->prepare($sql . ' WHERE id=? AND user_id=?')->execute($values);
        $selected = staffSignature($database, (int) $user['id']);
        if ($selected !== false && (int) $selected['saved_signature_id'] === $signatureId) { activateStaffSignature($database, (int) $user['id'], $signatureId); }
        $database->prepare("INSERT INTO audit_logs(user_id,action,description) VALUES (?,'AUTHORIZED_SIGNATURE_UPDATED',?)")->execute([$user['id'], 'Updated saved signature #' . $signatureId . '. Earlier approvals keep their original signatures.']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

function deleteStaffSignature(PDO $database, array $user, int $signatureId): void
{
    $database->beginTransaction();
    try {
        assertStaffSignatureEditable($database, $user);
        $owned = $database->prepare('SELECT id FROM staff_saved_signatures WHERE id=? AND user_id=? FOR UPDATE');
        $owned->execute([$signatureId, $user['id']]);
        if ($owned->fetchColumn() === false) { throw new RuntimeException('Choose one of your saved signatures.'); }
        $database->prepare('DELETE FROM staff_signatures WHERE saved_signature_id=? AND user_id=?')->execute([$signatureId, $user['id']]);
        $database->prepare('DELETE FROM staff_saved_signatures WHERE id=? AND user_id=?')->execute([$signatureId, $user['id']]);
        $database->prepare("INSERT INTO audit_logs(user_id,action,description) VALUES (?,'AUTHORIZED_SIGNATURE_DELETED',?)")->execute([$user['id'], 'Deleted saved signature #' . $signatureId . '. Earlier approvals keep their original signatures.']);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) { $database->rollBack(); }
        throw $exception;
    }
}

/** Called only by an approval transaction, for the office being approved now. */
function captureClearanceStationSignature(PDO $database, int $clearanceStationId, int $staffUserId): void
{
    if (!$database->inTransaction()) { throw new LogicException('Capture a signature inside the approval transaction.'); }
    requireStaffSignature($database, $staffUserId);
    $sync = $database->prepare(
        "INSERT INTO clearance_station_signatures
            (clearance_station_id,approved_by_user_id,printed_name,position_title,image_data,mime_type,image_sha256,signed_at)
         SELECT record.id,record.approved_by,signature.printed_name,signature.position_title,
                signature.image_data,signature.mime_type,signature.image_sha256,record.approved_at
         FROM clearance_stations record
         INNER JOIN staff_signatures signature ON signature.user_id=record.approved_by
         WHERE record.id=:station_id AND record.approved_by=:user_id AND record.status='APPROVED' AND record.approved_at IS NOT NULL
         ON DUPLICATE KEY UPDATE approved_by_user_id=VALUES(approved_by_user_id),printed_name=VALUES(printed_name),
             position_title=VALUES(position_title),image_data=VALUES(image_data),mime_type=VALUES(mime_type),
             image_sha256=VALUES(image_sha256),signed_at=VALUES(signed_at),created_at=NOW()"
    );
    $sync->execute(['station_id' => $clearanceStationId, 'user_id' => $staffUserId]);
    if ($sync->rowCount() < 1) {
        // Reapproval within the same second can leave an identical snapshot; MySQL reports zero changed rows.
        $record = $database->prepare("SELECT id FROM clearance_stations WHERE id=? AND approved_by=? AND status='APPROVED' AND approved_at IS NOT NULL");
        $record->execute([$clearanceStationId, $staffUserId]);
        if ($record->fetchColumn() === false) { throw new RuntimeException('The office signature could not be attached to this approval.'); }
    }
}
