CREATE TABLE IF NOT EXISTS clinic_medical_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    schedule_date DATE NOT NULL,
    schedule_time TIME NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    status ENUM('ACTIVE','CLOSED') NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clinic_schedule (academic_period_id, schedule_date, schedule_time),
    CONSTRAINT fk_clinic_schedule_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_clinic_schedule_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinic_medical_appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('SCHEDULED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'SCHEDULED',
    booked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clinic_student_period (student_id, academic_period_id),
    KEY idx_clinic_appointment_schedule (schedule_id, status),
    CONSTRAINT fk_clinic_appointment_schedule FOREIGN KEY (schedule_id) REFERENCES clinic_medical_schedules(id) ON DELETE CASCADE,
    CONSTRAINT fk_clinic_appointment_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_clinic_appointment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
