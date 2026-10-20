-- Authorized staff signatures and immutable snapshots used by downloadable clearance forms.

CREATE TABLE IF NOT EXISTS staff_signatures (
    user_id BIGINT UNSIGNED NOT NULL,
    printed_name VARCHAR(150) NOT NULL,
    position_title VARCHAR(150) NOT NULL,
    image_data MEDIUMBLOB NOT NULL,
    mime_type ENUM('image/jpeg','image/png') NOT NULL,
    image_sha256 CHAR(64) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_staff_signature_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clearance_station_signatures (
    clearance_station_id BIGINT UNSIGNED NOT NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    printed_name VARCHAR(150) NOT NULL,
    position_title VARCHAR(150) NOT NULL,
    image_data MEDIUMBLOB NOT NULL,
    mime_type ENUM('image/jpeg','image/png') NOT NULL,
    image_sha256 CHAR(64) NOT NULL,
    signed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clearance_station_id),
    KEY idx_clearance_signature_user (approved_by_user_id),
    CONSTRAINT fk_clearance_signature_station FOREIGN KEY (clearance_station_id)
        REFERENCES clearance_stations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_clearance_signature_user FOREIGN KEY (approved_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
