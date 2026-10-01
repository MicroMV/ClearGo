CREATE TABLE library_card_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    library_card_id BIGINT UNSIGNED NOT NULL,
    status ENUM('SUBMITTED','VERIFIED','RETURNED') NOT NULL DEFAULT 'SUBMITTED',
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    return_reason VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_card_submission_card (library_card_id),
    KEY idx_library_card_submission_status (status, submitted_at),
    KEY fk_library_card_submission_reviewer (reviewed_by),
    CONSTRAINT fk_library_card_submission_card FOREIGN KEY (library_card_id)
        REFERENCES library_cards (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_library_card_submission_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
