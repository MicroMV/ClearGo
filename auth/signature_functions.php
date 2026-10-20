<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

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
    // Freeze earlier approvals before changing the selected signatory, even if
    // the student has never downloaded a PDF for those approvals.
    preserveStaffSignatureApprovals($database, $userId);
    $activate = $database->prepare(
        'INSERT INTO staff_signatures (user_id,saved_signature_id,printed_name,position_title,image_data,mime_type,image_sha256)
         SELECT user_id,id,printed_name,position_title,image_data,mime_type,image_sha256
         FROM staff_saved_signatures WHERE id=:id AND user_id=:user_id
         ON DUPLICATE KEY UPDATE saved_signature_id=VALUES(saved_signature_id),printed_name=VALUES(printed_name),
             position_title=VALUES(position_title),image_data=VALUES(image_data),mime_type=VALUES(mime_type),
             image_sha256=VALUES(image_sha256),updated_at=NOW()'
    );
    $activate->execute(['id' => $signatureId, 'user_id' => $userId]);
    // Also supports approvals made before the signature feature was configured.
    preserveStaffSignatureApprovals($database, $userId);
}

function selectStaffSignature(PDO $database, array $user, int $signatureId): void
{
    $database->beginTransaction();
    try {
        $account = $database->prepare("SELECT id FROM users WHERE id=:id AND status='ACTIVE' AND role<>'STUDENT' FOR UPDATE");
        $account->execute(['id' => $user['id']]);
        if ($account->fetchColumn() === false) {
            throw new RuntimeException('Only active staff can select an office signature.');
        }
        activateStaffSignature($database, (int) $user['id'], $signatureId);
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

function preserveStaffSignatureApprovals(PDO $database, int $userId): void
{
    $save = $database->prepare(
        "INSERT IGNORE INTO clearance_station_signatures
            (clearance_station_id,approved_by_user_id,printed_name,position_title,image_data,mime_type,image_sha256,signed_at)
         SELECT record.id,record.approved_by,signature.printed_name,signature.position_title,
                signature.image_data,signature.mime_type,signature.image_sha256,record.approved_at
         FROM clearance_stations record
         INNER JOIN staff_signatures signature ON signature.user_id=record.approved_by
         WHERE record.status='APPROVED' AND record.approved_by=:user_id AND record.approved_at IS NOT NULL"
    );
    $save->execute(['user_id' => $userId]);
}

function syncClearanceSignatureSnapshots(PDO $database, int $clearanceId): void
{
    $sync = $database->prepare(
        "INSERT INTO clearance_station_signatures
            (clearance_station_id,approved_by_user_id,printed_name,position_title,image_data,mime_type,image_sha256,signed_at)
         SELECT record.id,record.approved_by,signature.printed_name,signature.position_title,
                signature.image_data,signature.mime_type,signature.image_sha256,record.approved_at
         FROM clearance_stations record
         INNER JOIN staff_signatures signature ON signature.user_id=record.approved_by
         LEFT JOIN clearance_station_signatures snapshot ON snapshot.clearance_station_id=record.id
         WHERE record.clearance_id=:clearance_id AND record.status='APPROVED' AND record.approved_at IS NOT NULL
           AND (snapshot.clearance_station_id IS NULL OR snapshot.approved_by_user_id<>record.approved_by OR snapshot.signed_at<>record.approved_at)
         ON DUPLICATE KEY UPDATE approved_by_user_id=VALUES(approved_by_user_id),printed_name=VALUES(printed_name),
             position_title=VALUES(position_title),image_data=VALUES(image_data),mime_type=VALUES(mime_type),
             image_sha256=VALUES(image_sha256),signed_at=VALUES(signed_at),created_at=NOW()"
    );
    $sync->execute(['clearance_id' => $clearanceId]);
}
