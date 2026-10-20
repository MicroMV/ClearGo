ALTER TABLE events ADD COLUMN attendance_finalized_at DATETIME NULL;
ALTER TABLE event_attendance ADD COLUMN sanction_verified_at DATETIME NULL,
    ADD COLUMN sanction_verified_by BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_attendance_sanction_verifier FOREIGN KEY (sanction_verified_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE event_sanction_policies (
    event_id BIGINT UNSIGNED PRIMARY KEY,
    task TEXT NOT NULL,
    hours DECIMAL(6,2) NOT NULL,
    monitor_user_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (monitor_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_sanctions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    task TEXT NOT NULL,
    hours DECIMAL(6,2) NOT NULL,
    monitor_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('PENDING','RESOLVED') NOT NULL DEFAULT 'PENDING',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    UNIQUE KEY uq_event_sanction_student (event_id,student_id),
    KEY idx_student_sanction_status (student_id,status),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (monitor_user_id) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sanction_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    status ENUM('SUBMITTED','RETURNED','APPROVED') NOT NULL DEFAULT 'SUBMITTED',
    before_stored_name VARCHAR(45) NOT NULL,
    before_mime VARCHAR(30) NOT NULL,
    after_stored_name VARCHAR(45) NOT NULL,
    after_mime VARCHAR(30) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_remarks TEXT NULL,
    KEY idx_sanction_submission_owner (academic_period_id,station_id,organization_id,status),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sanction_submission_items (
    submission_id BIGINT UNSIGNED NOT NULL,
    sanction_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (submission_id,sanction_id),
    FOREIGN KEY (submission_id) REFERENCES sanction_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (sanction_id) REFERENCES event_sanctions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
