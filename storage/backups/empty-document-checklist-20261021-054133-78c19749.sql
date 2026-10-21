-- Retired document checklist structures; both tables confirmed empty.
CREATE TABLE `document_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_document_requirements_period` (`academic_period_id`),
  CONSTRAINT `fk_document_requirements_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `document_requirement_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `original_file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `status` enum('MISSING','SUBMITTED','UNDER_REVIEW','VERIFIED','RETURNED') NOT NULL DEFAULT 'MISSING',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `return_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_document_requirement` (`student_id`,`document_requirement_id`),
  KEY `fk_student_documents_requirement` (`document_requirement_id`),
  KEY `fk_student_documents_reviewed_by` (`reviewed_by`),
  KEY `idx_student_documents_status` (`student_id`,`status`),
  CONSTRAINT `fk_student_documents_requirement` FOREIGN KEY (`document_requirement_id`) REFERENCES `document_requirements` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_documents_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_student_documents_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=338 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
