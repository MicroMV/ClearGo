-- Office workflows derived from the 2026 stakeholder interviews.
-- Apply once after 001_phase1_clearance_workflow.sql and 002_phase2_dean_evaluation.sql.

ALTER TABLE requirements
    ADD COLUMN requirement_code VARCHAR(80) NULL AFTER station_id,
    ADD COLUMN applicable_organization_id BIGINT UNSIGNED NULL AFTER description,
    ADD COLUMN minimum_year_level TINYINT UNSIGNED NULL AFTER applicable_organization_id,
    ADD COLUMN maximum_year_level TINYINT UNSIGNED NULL AFTER minimum_year_level,
    ADD UNIQUE KEY uq_requirement_code (requirement_code),
    ADD KEY idx_requirements_applicability
        (station_id, applicable_organization_id, minimum_year_level, maximum_year_level, status),
    ADD CONSTRAINT fk_requirements_organization
        FOREIGN KEY (applicable_organization_id) REFERENCES organizations (id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE requirements
SET requirement_code = CONCAT('LEGACY_', id)
WHERE requirement_code IS NULL;

UPDATE requirements r
INNER JOIN stations s ON s.id = r.station_id AND s.code = 'DEAN'
INNER JOIN organizations o ON o.code = 'CCS'
SET r.requirement_code = 'CCS_FACULTY_EVALUATION',
    r.name = 'Faculty Evaluation Completion',
    r.description = 'Provide proof that the required faculty evaluation was completed.',
    r.applicable_organization_id = o.id,
    r.maximum_year_level = 3
WHERE r.name = 'Instructor Evaluation Completion';

INSERT INTO requirements
    (station_id, requirement_code, name, type, description,
     applicable_organization_id, minimum_year_level, maximum_year_level, is_required, status)
SELECT s.id, 'CBE_YBC_APPROVAL', 'CBE Organization (YBC) Sign-off', 'APPROVAL',
       'Secure the required sign-off from YBC, the CBE department organization.',
       o.id, 1, 3, 1, 'ACTIVE'
FROM stations s
INNER JOIN organizations o ON o.code = 'CBE'
WHERE s.code = 'DEAN';

INSERT INTO requirements
    (station_id, requirement_code, name, type, description,
     applicable_organization_id, minimum_year_level, maximum_year_level, is_required, status)
SELECT s.id, 'CBE_OJT_REQUIREMENTS', 'OJT Requirements', 'DOCUMENT',
       'Complete and verify all required OJT documents and obligations.',
       o.id, 4, 4, 1, 'ACTIVE'
FROM stations s
INNER JOIN organizations o ON o.code = 'CBE'
WHERE s.code = 'DEAN';

INSERT INTO requirements
    (station_id, requirement_code, name, type, description,
     applicable_organization_id, minimum_year_level, maximum_year_level, is_required, status)
SELECT s.id, 'CCS_GRADUATION_APPLICATION', 'Application for Graduation', 'DOCUMENT',
       'Submit and verify the application for graduation.',
       o.id, 4, 4, 1, 'ACTIVE'
FROM stations s
INNER JOIN organizations o ON o.code = 'CCS'
WHERE s.code = 'DEAN';

CREATE TABLE clearance_exceptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clearance_station_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    proof_reference VARCHAR(255) NULL,
    status ENUM('REQUESTED','APPROVED','REJECTED','RESOLVED','EXPIRED') NOT NULL DEFAULT 'REQUESTED',
    requested_by BIGINT UNSIGNED NULL,
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clearance_exceptions_station_status (clearance_station_id, status),
    KEY fk_clearance_exceptions_requested_by (requested_by),
    KEY fk_clearance_exceptions_decided_by (decided_by),
    CONSTRAINT fk_clearance_exceptions_station
        FOREIGN KEY (clearance_station_id) REFERENCES clearance_stations (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_clearance_exceptions_requested_by
        FOREIGN KEY (requested_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_clearance_exceptions_decided_by
        FOREIGN KEY (decided_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE student_bookstore_fees
    ADD COLUMN payment_reference VARCHAR(100) NULL AFTER paid_at,
    ADD COLUMN remarks TEXT NULL AFTER payment_reference;

ALTER TABLE accounting_obligations
    ADD COLUMN payment_reference VARCHAR(100) NULL AFTER status,
    ADD COLUMN remarks TEXT NULL AFTER payment_reference;

-- Add applicable requirement records to existing applications.
INSERT INTO requirement_records (clearance_station_id, requirement_id, status)
SELECT cs.id, r.id, 'NOT_STARTED'
FROM clearance_stations cs
INNER JOIN clearances c ON c.id = cs.clearance_id
INNER JOIN students student ON student.id = c.student_id
INNER JOIN requirements r ON r.station_id = cs.station_id
LEFT JOIN requirement_records rr
    ON rr.clearance_station_id = cs.id AND rr.requirement_id = r.id
WHERE r.status = 'ACTIVE'
  AND r.is_required = 1
  AND (r.applicable_organization_id IS NULL OR r.applicable_organization_id = student.organization_id)
  AND (r.minimum_year_level IS NULL OR student.year_level >= r.minimum_year_level)
  AND (r.maximum_year_level IS NULL OR student.year_level <= r.maximum_year_level)
  AND rr.id IS NULL;

-- Remove scoped records that do not apply to the student.
DELETE rr
FROM requirement_records rr
INNER JOIN clearance_stations cs ON cs.id = rr.clearance_station_id
INNER JOIN clearances c ON c.id = cs.clearance_id
INNER JOIN students student ON student.id = c.student_id
INNER JOIN requirements r ON r.id = rr.requirement_id
WHERE (r.applicable_organization_id IS NOT NULL AND r.applicable_organization_id <> student.organization_id)
   OR (r.minimum_year_level IS NOT NULL AND student.year_level < r.minimum_year_level)
   OR (r.maximum_year_level IS NOT NULL AND student.year_level > r.maximum_year_level);

INSERT IGNORE INTO medical_requirements (student_id, academic_period_id, status)
SELECT student.id, period.id, 'PENDING'
FROM students student
CROSS JOIN academic_periods period
WHERE student.status = 'ACTIVE' AND period.status = 'ACTIVE';

INSERT IGNORE INTO library_cards (student_id, status)
SELECT id, 'PENDING' FROM students WHERE status = 'ACTIVE';

INSERT IGNORE INTO student_bookstore_fees (student_id, fee_id, status)
SELECT student.id, fee.id, 'UNPAID'
FROM students student
INNER JOIN bookstore_fees fee ON fee.status = 'ACTIVE' AND fee.is_required = 1
INNER JOIN academic_periods period ON period.id = fee.academic_period_id AND period.status = 'ACTIVE'
WHERE student.status = 'ACTIVE';

INSERT IGNORE INTO student_documents (student_id, document_requirement_id, status)
SELECT student.id, requirement.id, 'MISSING'
FROM students student
INNER JOIN document_requirements requirement ON requirement.status = 'ACTIVE' AND requirement.is_required = 1
INNER JOIN academic_periods period ON period.id = requirement.academic_period_id AND period.status = 'ACTIVE'
WHERE student.status = 'ACTIVE';
