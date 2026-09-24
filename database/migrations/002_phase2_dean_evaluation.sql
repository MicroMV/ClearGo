-- Phase 2: retain only the Instructor Evaluation verification result.

CREATE TABLE evaluation_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clearance_id BIGINT UNSIGNED NOT NULL,
    status ENUM('NOT_SUBMITTED', 'VERIFIED', 'REJECTED', 'ERROR') NOT NULL DEFAULT 'NOT_SUBMITTED',
    verification_method VARCHAR(50) NOT NULL DEFAULT 'OPENAI_VISION',
    model_name VARCHAR(100) NULL,
    confidence DECIMAL(5,4) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_submitted_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evaluation_clearance (clearance_id),
    KEY idx_evaluation_status (status),
    CONSTRAINT fk_evaluation_clearance
        FOREIGN KEY (clearance_id) REFERENCES clearances (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO requirements (station_id, name, type, description, is_required, status)
SELECT id,
       'Instructor Evaluation Completion',
       'VERIFICATION',
       'Upload a screenshot showing that the Instructor Evaluation was completed.',
       1,
       'ACTIVE'
FROM stations
WHERE code = 'DEAN'
  AND NOT EXISTS (
      SELECT 1
      FROM requirements existing
      WHERE existing.station_id = stations.id
        AND existing.name = 'Instructor Evaluation Completion'
  );

INSERT INTO requirement_records (clearance_station_id, requirement_id, status)
SELECT clearance_stations.id, requirements.id, 'NOT_STARTED'
FROM clearance_stations
INNER JOIN stations ON stations.id = clearance_stations.station_id AND stations.code = 'DEAN'
INNER JOIN requirements ON requirements.station_id = stations.id
    AND requirements.name = 'Instructor Evaluation Completion'
LEFT JOIN requirement_records ON requirement_records.clearance_station_id = clearance_stations.id
    AND requirement_records.requirement_id = requirements.id
WHERE requirement_records.id IS NULL;

