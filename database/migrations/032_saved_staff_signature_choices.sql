-- Keep each saved signature and its signatory identity; staff_signatures remains
-- the selected signature used by approvals and existing PDF readers.
CREATE TABLE IF NOT EXISTS staff_saved_signatures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    printed_name VARCHAR(150) NOT NULL,
    position_title VARCHAR(150) NOT NULL,
    image_data MEDIUMBLOB NOT NULL,
    mime_type ENUM('image/jpeg','image/png') NOT NULL,
    image_sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved_signature_owner (id,user_id),
    KEY idx_saved_signature_user (user_id,id),
    CONSTRAINT fk_saved_signature_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO staff_saved_signatures
    (user_id,printed_name,position_title,image_data,mime_type,image_sha256,created_at)
SELECT user_id,printed_name,position_title,image_data,mime_type,image_sha256,updated_at
FROM staff_signatures;

ALTER TABLE staff_signatures
    ADD COLUMN saved_signature_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_selected_signature_owner (saved_signature_id,user_id),
    ADD CONSTRAINT fk_selected_signature_owner FOREIGN KEY (saved_signature_id,user_id)
        REFERENCES staff_saved_signatures(id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE staff_signatures active_signature
INNER JOIN staff_saved_signatures saved ON saved.user_id=active_signature.user_id
SET active_signature.saved_signature_id=saved.id;
