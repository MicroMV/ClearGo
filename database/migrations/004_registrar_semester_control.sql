-- Registrar-controlled semester enrollment and clearance opening.

ALTER TABLE academic_periods
    ADD COLUMN clearance_status ENUM('DRAFT','PREPARING','OPEN','CLOSED') NOT NULL DEFAULT 'DRAFT' AFTER status,
    ADD COLUMN opened_by BIGINT UNSIGNED NULL AFTER clearance_status,
    ADD COLUMN opened_at DATETIME NULL AFTER opened_by,
    ADD COLUMN closed_at DATETIME NULL AFTER opened_at,
    ADD KEY fk_academic_periods_opened_by (opened_by),
    ADD CONSTRAINT fk_academic_periods_opened_by
        FOREIGN KEY (opened_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE academic_periods
SET clearance_status = CASE
    WHEN status = 'CLOSED' THEN 'CLOSED'
    WHEN status = 'ACTIVE' AND EXISTS (
        SELECT 1 FROM clearances WHERE clearances.academic_period_id = academic_periods.id
    ) THEN 'OPEN'
    WHEN status = 'ACTIVE' THEN 'PREPARING'
    ELSE 'DRAFT'
END,
opened_at = CASE
    WHEN status = 'ACTIVE' AND EXISTS (
        SELECT 1 FROM clearances WHERE clearances.academic_period_id = academic_periods.id
    ) THEN COALESCE((SELECT MIN(created_at) FROM clearances WHERE clearances.academic_period_id = academic_periods.id), NOW())
    ELSE NULL
END;

CREATE TABLE semester_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('ENROLLED','REMOVED') NOT NULL DEFAULT 'ENROLLED',
    enrolled_by BIGINT UNSIGNED NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_by BIGINT UNSIGNED NULL,
    removed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_semester_enrollment (academic_period_id, student_id),
    KEY idx_semester_enrollment_status (academic_period_id, status),
    KEY fk_semester_enrollment_student (student_id),
    KEY fk_semester_enrollment_enrolled_by (enrolled_by),
    KEY fk_semester_enrollment_removed_by (removed_by),
    CONSTRAINT fk_semester_enrollment_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id) ON UPDATE CASCADE,
    CONSTRAINT fk_semester_enrollment_student FOREIGN KEY (student_id) REFERENCES students (id) ON UPDATE CASCADE,
    CONSTRAINT fk_semester_enrollment_enrolled_by FOREIGN KEY (enrolled_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_semester_enrollment_removed_by FOREIGN KEY (removed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE office_period_readiness (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    status ENUM('NOT_READY','READY') NOT NULL DEFAULT 'NOT_READY',
    ready_by BIGINT UNSIGNED NULL,
    ready_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_office_period_readiness (academic_period_id, station_id),
    KEY idx_office_period_status (academic_period_id, status),
    KEY fk_office_period_station (station_id),
    KEY fk_office_period_ready_by (ready_by),
    CONSTRAINT fk_office_period_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_office_period_station FOREIGN KEY (station_id) REFERENCES stations (id) ON UPDATE CASCADE,
    CONSTRAINT fk_office_period_ready_by FOREIGN KEY (ready_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO semester_enrollments (academic_period_id, student_id, status)
SELECT period.id, student.id, 'ENROLLED'
FROM academic_periods period
INNER JOIN students student ON student.status = 'ACTIVE'
WHERE period.status = 'ACTIVE'
ON DUPLICATE KEY UPDATE status = VALUES(status), removed_by = NULL, removed_at = NULL;

INSERT INTO office_period_readiness (academic_period_id, station_id, status, ready_at)
SELECT period.id, station.id,
       CASE WHEN period.clearance_status = 'OPEN' THEN 'READY' ELSE 'NOT_READY' END,
       CASE WHEN period.clearance_status = 'OPEN' THEN COALESCE(period.opened_at, NOW()) ELSE NULL END
FROM academic_periods period
INNER JOIN stations station ON station.status = 'ACTIVE' AND station.code <> 'REGISTRAR'
ON DUPLICATE KEY UPDATE status = VALUES(status), ready_at = VALUES(ready_at);

