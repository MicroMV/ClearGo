-- CCS evaluation completion metadata only. No evaluation answers or images are stored here.
CREATE TABLE IF NOT EXISTS ccs_evaluation_completions (
    clearance_id BIGINT UNSIGNED NOT NULL,
    status ENUM('COMPLETED','NOT_COMPLETED') NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clearance_id),
    KEY idx_ccs_evaluation_completion_status (status),
    CONSTRAINT fk_ccs_evaluation_completion_clearance FOREIGN KEY (clearance_id)
        REFERENCES clearances(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ccs_evaluation_completion_recorder FOREIGN KEY (recorded_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
