CREATE TABLE accounting_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    receipt_code VARCHAR(255) NOT NULL,
    status ENUM('ISSUED','SCANNED','APPROVED') NOT NULL DEFAULT 'ISSUED',
    issued_by BIGINT UNSIGNED NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanned_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_receipt_code (receipt_code),
    KEY idx_accounting_receipt_student_period (student_id,academic_period_id,status),
    CONSTRAINT fk_accounting_receipt_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_accounting_receipt_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_accounting_receipt_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_accounting_receipt_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
