-- A Registrar can grant one-time setup to a Ready office account missing its selected signature.
-- Office readiness and historical approvals are never changed by recovery.
CREATE TABLE IF NOT EXISTS staff_signature_recoveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    authorized_by BIGINT UNSIGNED NULL,
    reason VARCHAR(300) NOT NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_signature_recovery_period_user (academic_period_id,user_id),
    CONSTRAINT fk_signature_recovery_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_signature_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_signature_recovery_registrar FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
