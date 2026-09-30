-- CCS Faculty Evaluation: retain up to three private screenshots for anonymous Dean review.

CREATE TABLE evaluation_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clearance_id BIGINT UNSIGNED NOT NULL,
    anonymous_code CHAR(12) NOT NULL,
    status ENUM('SUBMITTED','VERIFIED','RETURNED') NOT NULL DEFAULT 'SUBMITTED',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    return_reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evaluation_submission_clearance (clearance_id),
    UNIQUE KEY uq_evaluation_submission_code (anonymous_code),
    KEY idx_evaluation_submission_status (status),
    CONSTRAINT fk_evaluation_submission_clearance FOREIGN KEY (clearance_id)
        REFERENCES clearances (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_evaluation_submission_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_submission_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    stored_name VARCHAR(128) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_evaluation_submission_image_position (submission_id, position),
    CONSTRAINT fk_evaluation_submission_image_submission FOREIGN KEY (submission_id)
        REFERENCES evaluation_submissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
