CREATE TABLE facilities_obligations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('BORROWED_ITEM','TOGA') NOT NULL,
    description VARCHAR(255) NOT NULL,
    status ENUM('OUTSTANDING','RETURNED') NOT NULL DEFAULT 'OUTSTANDING',
    remarks VARCHAR(1000) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    returned_by BIGINT UNSIGNED NULL,
    returned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_facilities_item (student_id,academic_period_id,item_type,description),
    KEY idx_facilities_period_status (academic_period_id,status),
    KEY fk_facilities_recorded_by (recorded_by),
    KEY fk_facilities_returned_by (returned_by),
    CONSTRAINT fk_facilities_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_facilities_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_facilities_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_facilities_returned_by FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO facilities_obligations
    (student_id,academic_period_id,item_type,description,status)
SELECT enrollment.student_id,enrollment.academic_period_id,'TOGA','Graduation toga','OUTSTANDING'
FROM semester_enrollments enrollment
INNER JOIN students student ON student.id=enrollment.student_id
INNER JOIN academic_periods period ON period.id=enrollment.academic_period_id AND period.status='ACTIVE'
WHERE enrollment.status='ENROLLED' AND student.status='ACTIVE' AND student.year_level>=4
  AND period.semester='2ND';
