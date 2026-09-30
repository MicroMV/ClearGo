<?php

declare(strict_types=1);

require_once __DIR__ . '/../verification/evaluation_verifier.php';

function studentEvaluationContext(PDO $database, int $userId): array
{
    $statement = $database->prepare(
        "SELECT students.id AS student_id, clearances.id AS clearance_id,
                clearance_stations.id AS clearance_station_id,
                clearance_stations.status AS dean_status
         FROM students
         INNER JOIN clearances ON clearances.student_id = students.id
         INNER JOIN academic_periods ON academic_periods.id = clearances.academic_period_id
            AND academic_periods.status = 'ACTIVE'
         INNER JOIN stations ON stations.code = 'DEAN'
         INNER JOIN clearance_stations ON clearance_stations.clearance_id = clearances.id
            AND clearance_stations.station_id = stations.id
         INNER JOIN requirement_records evaluation_record
            ON evaluation_record.clearance_station_id = clearance_stations.id
         INNER JOIN requirements evaluation_requirement
            ON evaluation_requirement.id = evaluation_record.requirement_id
            AND evaluation_requirement.requirement_code IN
                ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')
         WHERE students.user_id = :user_id AND students.status = 'ACTIVE'
         LIMIT 1"
    );
    $statement->execute(['user_id' => $userId]);
    $context = $statement->fetch();

    if ($context === false) {
        throw new RuntimeException('Start a clearance application before uploading evaluation proof.');
    }
    if ($context['dean_status'] === 'LOCKED') {
        throw new RuntimeException('Dean clearance is not currently available.');
    }
    if ($context['dean_status'] === 'APPROVED') {
        throw new RuntimeException('Dean clearance is already approved.');
    }

    return $context;
}

function validateEvaluationUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Select a valid evaluation completion screenshot.');
    }

    $size = filter_var($file['size'] ?? null, FILTER_VALIDATE_INT);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('The screenshot must be no larger than 8 MB.');
    }

    $temporaryName = (string) ($file['tmp_name'] ?? '');
    if ($temporaryName === '' || !is_uploaded_file($temporaryName)) {
        throw new RuntimeException('The screenshot upload could not be validated.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryName);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        throw new RuntimeException('Upload a JPG, PNG, or WebP screenshot.');
    }

    $dimensions = @getimagesize($temporaryName);
    if ($dimensions === false || $dimensions[0] < 300 || $dimensions[1] < 200) {
        throw new RuntimeException('The screenshot is invalid or too small to verify.');
    }

    return ['temporary_name' => $temporaryName, 'mime_type' => $mimeType, 'extension' => $extensions[$mimeType]];
}

function saveEvaluationResult(PDO $database, array $context, int $userId, array $result): void
{
    $database->beginTransaction();
    try {
        $lock = $database->prepare('SELECT status FROM clearance_stations WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $context['clearance_station_id']]);
        $deanStatus = $lock->fetchColumn();
        if ($deanStatus === false || $deanStatus === 'LOCKED' || $deanStatus === 'APPROVED') {
            throw new RuntimeException('Dean clearance is no longer available for proof submission.');
        }

        $verification = $database->prepare(
            "INSERT INTO evaluation_verifications
                (clearance_id, status, verification_method, model_name, confidence,
                 attempt_count, last_submitted_at, verified_at)
             VALUES
                (:clearance_id, :status, 'OPENAI_VISION', :model_name, :confidence,
                 1, NOW(), CASE WHEN :verified_status = 'VERIFIED' THEN NOW() ELSE NULL END)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), model_name = VALUES(model_name), confidence = VALUES(confidence),
                attempt_count = attempt_count + 1, last_submitted_at = NOW(),
                verified_at = CASE WHEN VALUES(status) = 'VERIFIED' THEN NOW() ELSE NULL END"
        );
        $verification->execute([
            'clearance_id' => $context['clearance_id'],
            'status' => $result['status'],
            'verified_status' => $result['status'],
            'model_name' => $result['model_name'],
            'confidence' => $result['confidence'],
        ]);

        if ($result['status'] === 'VERIFIED') {
            $requirement = $database->prepare(
                "UPDATE requirement_records record
                 INNER JOIN requirements requirement ON requirement.id = record.requirement_id
                 SET record.status = 'VERIFIED', record.remarks = NULL,
                     record.verified_by = NULL, record.verified_at = NOW()
                 WHERE record.clearance_station_id = :clearance_station_id
                   AND requirement.requirement_code IN
                       ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')"
            );
            $requirement->execute(['clearance_station_id' => $context['clearance_station_id']]);
        } elseif ($result['status'] === 'REJECTED') {
            $requirement = $database->prepare(
                "UPDATE requirement_records record
                 INNER JOIN requirements requirement ON requirement.id = record.requirement_id
                 SET record.status = 'RETURNED',
                     record.remarks = 'Your proof could not be verified. Please upload a valid screenshot and try again.',
                     record.verified_by = NULL, record.verified_at = NULL
                 WHERE record.clearance_station_id = :clearance_station_id
                   AND requirement.requirement_code IN
                       ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')"
            );
            $requirement->execute(['clearance_station_id' => $context['clearance_station_id']]);
        }

        $messages = [
            'VERIFIED' => ['Evaluation proof verified', 'Your Faculty Evaluation completion proof was verified and is ready for Dean review.'],
            'REJECTED' => ['Evaluation proof requires action', 'Your proof could not be verified. Please upload a valid screenshot and try again.'],
            'ERROR' => ['Evaluation verification unavailable', 'Your screenshot was not retained. Please try again when the verification service is available.'],
        ];
        [$title, $message] = $messages[$result['status']];
        $notification = $database->prepare(
            'INSERT INTO notifications (user_id, title, message) VALUES (:user_id, :title, :message)'
        );
        $notification->execute(['user_id' => $userId, 'title' => $title, 'message' => $message]);

        $stationId = $database->query("SELECT id FROM stations WHERE code = 'DEAN' LIMIT 1")->fetchColumn();
        $audit = $database->prepare(
            "INSERT INTO audit_logs
                (user_id, action, station_id, student_id, description, old_status, new_status)
             VALUES
                (:user_id, 'EVALUATION_PROOF_CHECKED', :station_id, :student_id,
                 'Faculty Evaluation proof was checked without retaining the uploaded image.', NULL, :new_status)"
        );
        $audit->execute([
            'user_id' => $userId,
            'station_id' => $stationId,
            'student_id' => $context['student_id'],
            'new_status' => $result['status'],
        ]);

        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}

function processEvaluationUpload(PDO $database, int $userId, array $file): array
{
    $context = studentEvaluationContext($database, $userId);
    $validated = validateEvaluationUpload($file);
    $storageDirectory = __DIR__ . '/../storage/private/evaluation-proofs';
    if (!is_dir($storageDirectory) || !is_writable($storageDirectory)) {
        throw new RuntimeException('Secure temporary upload storage is unavailable.');
    }

    $temporaryPath = $storageDirectory . '/' . bin2hex(random_bytes(24)) . '.' . $validated['extension'];
    if (!move_uploaded_file($validated['temporary_name'], $temporaryPath)) {
        throw new RuntimeException('The screenshot could not be stored securely for verification.');
    }

    try {
        try {
            $result = verifyEvaluationScreenshot($temporaryPath, $validated['mime_type'], $userId);
        } catch (Throwable $exception) {
            error_log('Evaluation proof verification failed: ' . $exception->getMessage());
            $result = [
                'status' => 'ERROR',
                'confidence' => null,
                'model_name' => evaluationVisionModel(),
            ];
        }
        saveEvaluationResult($database, $context, $userId, $result);
        return $result;
    } finally {
        if (is_file($temporaryPath) && !unlink($temporaryPath)) {
            error_log('Unable to delete a temporary evaluation screenshot.');
        }
    }
}

function processEvaluationSubmission(PDO $database, int $userId, array $files): array
{
    $context = studentEvaluationContext($database, $userId);
    $uploads = [];
    foreach (($files['name'] ?? []) as $index => $name) {
        $uploads[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    if (count($uploads) < 1 || count($uploads) > 3) {
        throw new RuntimeException('Upload from one to three screenshots only.');
    }
    $validated = array_map('validateEvaluationUpload', $uploads);
    $directory = __DIR__ . '/../storage/private/evaluation-submissions';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('Secure screenshot storage is unavailable.');
    }
    $stored = [];
    try {
        foreach ($validated as $index => $image) {
            $name = bin2hex(random_bytes(24)) . '.' . $image['extension'];
            if (!move_uploaded_file($image['temporary_name'], $directory . '/' . $name)) {
                throw new RuntimeException('A screenshot could not be stored. Please try again.');
            }
            $stored[] = ['stored_name' => $name, 'original_name' => basename((string) $uploads[$index]['name']), 'mime_type' => $image['mime_type'], 'position' => $index + 1];
        }
        $database->beginTransaction();
        $lock = $database->prepare('SELECT status FROM clearance_stations WHERE id=:id FOR UPDATE');
        $lock->execute(['id' => $context['clearance_station_id']]);
        if (in_array($lock->fetchColumn(), ['LOCKED', 'APPROVED'], true)) {
            throw new RuntimeException('Dean clearance is no longer available for submission.');
        }
        $existingSubmission = $database->prepare('SELECT id FROM evaluation_submissions WHERE clearance_id=:clearance_id FOR UPDATE');
        $existingSubmission->execute(['clearance_id' => $context['clearance_id']]);
        $isFirstSubmission = $existingSubmission->fetchColumn() === false;
        $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $submission = $database->prepare("INSERT INTO evaluation_submissions (clearance_id,anonymous_code,status,submitted_at,reviewed_at,reviewed_by,return_reason) VALUES (:clearance_id,:code,'SUBMITTED',NOW(),NULL,NULL,NULL) ON DUPLICATE KEY UPDATE anonymous_code=VALUES(anonymous_code),status='SUBMITTED',submitted_at=NOW(),reviewed_at=NULL,reviewed_by=NULL,return_reason=NULL");
        $submission->execute(['clearance_id' => $context['clearance_id'], 'code' => $code]);
        $idQuery = $database->prepare('SELECT id FROM evaluation_submissions WHERE clearance_id=:clearance_id');
        $idQuery->execute(['clearance_id' => $context['clearance_id']]);
        $submissionId = (int) $idQuery->fetchColumn();
        $database->prepare('DELETE FROM evaluation_submission_images WHERE submission_id=:id')->execute(['id' => $submissionId]);
        $imageInsert = $database->prepare('INSERT INTO evaluation_submission_images (submission_id,stored_name,original_name,mime_type,position) VALUES (:submission_id,:stored_name,:original_name,:mime_type,:position)');
        foreach ($stored as $image) {
            $imageInsert->execute(['submission_id' => $submissionId] + $image);
        }
        if ($isFirstSubmission) {
            $notifyDean = $database->prepare("INSERT INTO notifications (user_id,title,message) SELECT assignment.user_id,'Anonymous evaluation submitted','A Faculty Evaluation proof was submitted for anonymous CCS Dean review.' FROM staff_assignments assignment INNER JOIN stations station ON station.id=assignment.station_id AND station.code='DEAN' INNER JOIN students student ON student.organization_id=assignment.organization_id WHERE student.id=:student_id AND assignment.status='ACTIVE'");
            $notifyDean->execute(['student_id' => $context['student_id']]);
        }
        $database->prepare("UPDATE requirement_records record INNER JOIN requirements requirement ON requirement.id=record.requirement_id SET record.status='PENDING',record.remarks='Evaluation screenshots submitted for anonymous Dean review.',record.verified_by=NULL,record.verified_at=NULL WHERE record.clearance_station_id=:station_id AND requirement.requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION')")->execute(['station_id' => $context['clearance_station_id']]);
        $database->commit();
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        foreach ($stored as $image) {
            @unlink($directory . '/' . $image['stored_name']);
        }
        throw $exception;
    }
    return ['status' => 'SUBMITTED'];
}
