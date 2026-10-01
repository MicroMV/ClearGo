CREATE TABLE bookstore_receipt_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_fee_id BIGINT UNSIGNED NOT NULL,
    status ENUM('SUBMITTED','LOST_REPORTED','VERIFIED','RETURNED') NOT NULL,
    stored_name VARCHAR(255) NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    return_reason VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bookstore_receipt_student_fee (student_fee_id),
    KEY idx_bookstore_receipt_status (status, submitted_at),
    KEY fk_bookstore_receipt_reviewer (reviewed_by),
    CONSTRAINT fk_bookstore_receipt_student_fee FOREIGN KEY (student_fee_id)
        REFERENCES student_bookstore_fees (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookstore_receipt_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
