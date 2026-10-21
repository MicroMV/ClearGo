-- Apply before deploying the matching authentication code. No existing account/password is changed.
SET @auth_version_present=(SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='auth_version');
SET @auth_version_ddl=IF(@auth_version_present=0,
    'ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1','DO 0');
PREPARE auth_version_statement FROM @auth_version_ddl;
EXECUTE auth_version_statement;
DEALLOCATE PREPARE auth_version_statement;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY idx_auth_limits_cleanup (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_password_resets (
    user_id BIGINT UNSIGNED NOT NULL,
    request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    auth_version INT UNSIGNED NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    requested_at INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    verified_until INT UNSIGNED NULL,
    reset_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_student_reset_request (request_id),
    KEY idx_student_reset_expiry (expires_at),
    CONSTRAINT fk_student_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_remember_tokens (
    selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    previous_validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    previous_valid_until INT UNSIGNED NULL,
    auth_version INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    last_used_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (selector),
    KEY idx_remember_user (user_id),
    KEY idx_remember_expiry (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The mail payload is encrypted using APP_KEY; OTPs are never kept as plaintext in the database.
CREATE TABLE IF NOT EXISTS auth_mail_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('STUDENT_RESET_CODE','PASSWORD_CHANGED') NOT NULL,
    request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recipient VARCHAR(254) NOT NULL,
    payload TEXT NOT NULL,
    status ENUM('PENDING','SENDING','SENT','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at INT UNSIGNED NOT NULL,
    claimed_until INT UNSIGNED NULL,
    expires_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    sent_at INT UNSIGNED NULL,
    last_error VARCHAR(160) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_mail_event (kind,request_id),
    KEY idx_auth_mail_delivery (status,available_at,expires_at),
    KEY idx_auth_mail_user (user_id),
    CONSTRAINT fk_auth_mail_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
