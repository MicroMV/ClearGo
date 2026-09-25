-- Stores only the outcome of AI receipt-photo checks. Receipt images are deleted after scanning.
CREATE TABLE accounting_receipt_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    accounting_receipt_id BIGINT UNSIGNED NOT NULL,
    status ENUM('NOT_SUBMITTED','VERIFIED','REJECTED','ERROR') NOT NULL DEFAULT 'NOT_SUBMITTED',
    verification_method VARCHAR(50) NOT NULL DEFAULT 'OPENAI_VISION',
    model_name VARCHAR(100) NULL,
    confidence DECIMAL(5,4) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_submitted_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_receipt_verification (accounting_receipt_id),
    KEY idx_accounting_receipt_verification_status (status),
    CONSTRAINT fk_accounting_receipt_verification_receipt
        FOREIGN KEY (accounting_receipt_id) REFERENCES accounting_receipts (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
