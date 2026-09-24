-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: lewis_clearance
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_periods`
--

DROP TABLE IF EXISTS `academic_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_year` varchar(20) NOT NULL,
  `semester` enum('1ST','2ND','SUMMER') NOT NULL,
  `status` enum('ACTIVE','INACTIVE','CLOSED') NOT NULL DEFAULT 'INACTIVE',
  `clearance_status` enum('DRAFT','PREPARING','OPEN','CLOSED') NOT NULL DEFAULT 'DRAFT',
  `opened_by` bigint(20) unsigned DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_period` (`school_year`,`semester`),
  KEY `fk_academic_periods_opened_by` (`opened_by`),
  CONSTRAINT `fk_academic_periods_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_periods`
--

LOCK TABLES `academic_periods` WRITE;
/*!40000 ALTER TABLE `academic_periods` DISABLE KEYS */;
INSERT INTO `academic_periods` VALUES (1,'2026-2027','1ST','ACTIVE','OPEN',NULL,'2026-09-11 14:16:17',NULL,'2026-08-28 14:37:22','2026-09-21 12:27:06');
/*!40000 ALTER TABLE `academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounting_obligations`
--

DROP TABLE IF EXISTS `accounting_obligations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_obligations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('UNPAID','CLEARED') NOT NULL DEFAULT 'UNPAID',
  `payment_reference` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `cleared_by` bigint(20) unsigned DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_accounting_period` (`academic_period_id`),
  KEY `fk_accounting_cleared_by` (`cleared_by`),
  KEY `idx_accounting_student_status` (`student_id`,`status`),
  CONSTRAINT `fk_accounting_cleared_by` FOREIGN KEY (`cleared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_accounting_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_accounting_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_obligations`
--

LOCK TABLES `accounting_obligations` WRITE;
/*!40000 ALTER TABLE `accounting_obligations` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounting_obligations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `station_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_student_date` (`student_id`,`created_at`),
  KEY `idx_audit_user_date` (`user_id`,`created_at`),
  KEY `idx_audit_station_date` (`station_id`,`created_at`),
  CONSTRAINT `fk_audit_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (2,22,'CLEARANCE_STARTED',2,22,'Student started a clearance application.','NOT_STARTED','IN_PROGRESS','2026-09-11 14:16:17'),(10,22,'EVALUATION_PROOF_CHECKED',2,22,'Instructor Evaluation proof was checked without retaining the uploaded image.',NULL,'ERROR','2026-09-11 14:31:04');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookstore_fees`
--

DROP TABLE IF EXISTS `bookstore_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookstore_fees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bookstore_fees_period` (`academic_period_id`),
  CONSTRAINT `fk_bookstore_fees_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookstore_fees`
--

LOCK TABLES `bookstore_fees` WRITE;
/*!40000 ALTER TABLE `bookstore_fees` DISABLE KEYS */;
INSERT INTO `bookstore_fees` VALUES (1,1,'Required Event Fee A',200.00,1,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(2,1,'Required Event Fee B',150.00,1,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22');
/*!40000 ALTER TABLE `bookstore_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `borrowed_books`
--

DROP TABLE IF EXISTS `borrowed_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `borrowed_books` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `borrowed_at` date NOT NULL,
  `due_at` date DEFAULT NULL,
  `returned_at` date DEFAULT NULL,
  `status` enum('BORROWED','RETURNED') NOT NULL DEFAULT 'BORROWED',
  `recorded_by` bigint(20) unsigned NOT NULL,
  `returned_verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_borrowed_books_recorded_by` (`recorded_by`),
  KEY `fk_borrowed_books_returned_verified_by` (`returned_verified_by`),
  KEY `idx_borrowed_books_student_status` (`student_id`,`status`),
  CONSTRAINT `fk_borrowed_books_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_borrowed_books_returned_verified_by` FOREIGN KEY (`returned_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_borrowed_books_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `borrowed_books`
--

LOCK TABLES `borrowed_books` WRITE;
/*!40000 ALTER TABLE `borrowed_books` DISABLE KEYS */;
/*!40000 ALTER TABLE `borrowed_books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearance_exceptions`
--

DROP TABLE IF EXISTS `clearance_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearance_exceptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clearance_station_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `proof_reference` varchar(255) DEFAULT NULL,
  `status` enum('REQUESTED','APPROVED','REJECTED','RESOLVED','EXPIRED') NOT NULL DEFAULT 'REQUESTED',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `decided_by` bigint(20) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clearance_exceptions_station_status` (`clearance_station_id`,`status`),
  KEY `fk_clearance_exceptions_requested_by` (`requested_by`),
  KEY `fk_clearance_exceptions_decided_by` (`decided_by`),
  CONSTRAINT `fk_clearance_exceptions_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_exceptions_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_exceptions_station` FOREIGN KEY (`clearance_station_id`) REFERENCES `clearance_stations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearance_exceptions`
--

LOCK TABLES `clearance_exceptions` WRITE;
/*!40000 ALTER TABLE `clearance_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `clearance_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearance_stations`
--

DROP TABLE IF EXISTS `clearance_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearance_stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clearance_id` bigint(20) unsigned NOT NULL,
  `station_id` bigint(20) unsigned NOT NULL,
  `status` enum('LOCKED','PENDING','UNDER_REVIEW','ELIGIBLE','APPROVED','RETURNED') NOT NULL DEFAULT 'LOCKED',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `returned_by` bigint(20) unsigned DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `returned_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clearance_station` (`clearance_id`,`station_id`),
  KEY `fk_clearance_stations_approved_by` (`approved_by`),
  KEY `fk_clearance_stations_returned_by` (`returned_by`),
  KEY `idx_clearance_station_status` (`station_id`,`status`),
  CONSTRAINT `fk_clearance_stations_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_stations_clearance` FOREIGN KEY (`clearance_id`) REFERENCES `clearances` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_stations_returned_by` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_stations_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=551 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearance_stations`
--

LOCK TABLES `clearance_stations` WRITE;
/*!40000 ALTER TABLE `clearance_stations` DISABLE KEYS */;
INSERT INTO `clearance_stations` VALUES (16,2,1,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(17,2,2,'PENDING',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(18,2,3,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(19,2,4,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(20,2,5,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(21,2,6,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(22,2,7,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(23,2,8,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(24,2,9,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(25,2,10,'LOCKED',NULL,NULL,NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17');
/*!40000 ALTER TABLE `clearance_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clearances`
--

DROP TABLE IF EXISTS `clearances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clearances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `status` enum('NOT_STARTED','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'NOT_STARTED',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  UNIQUE KEY `uq_student_period` (`student_id`,`academic_period_id`),
  KEY `fk_clearances_period` (`academic_period_id`),
  KEY `idx_clearances_status` (`status`),
  CONSTRAINT `fk_clearances_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_clearances_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clearances`
--

LOCK TABLES `clearances` WRITE;
/*!40000 ALTER TABLE `clearances` DISABLE KEYS */;
INSERT INTO `clearances` VALUES (2,22,1,'CLR-1-DEV2026022-BF7877','IN_PROGRESS','2026-09-11 14:16:17',NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17');
/*!40000 ALTER TABLE `clearances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_requirements`
--

DROP TABLE IF EXISTS `document_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requirements`
--

LOCK TABLES `document_requirements` WRITE;
/*!40000 ALTER TABLE `document_requirements` DISABLE KEYS */;
INSERT INTO `document_requirements` VALUES (1,1,'Required Document A','Development document requirement.',1,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(2,1,'Required Document B','Development document requirement.',1,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(3,1,'Required Document C','Development document requirement.',1,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22');
/*!40000 ALTER TABLE `document_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluation_verifications`
--

DROP TABLE IF EXISTS `evaluation_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clearance_id` bigint(20) unsigned NOT NULL,
  `status` enum('NOT_SUBMITTED','VERIFIED','REJECTED','ERROR') NOT NULL DEFAULT 'NOT_SUBMITTED',
  `verification_method` varchar(50) NOT NULL DEFAULT 'OPENAI_VISION',
  `model_name` varchar(100) DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_submitted_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evaluation_clearance` (`clearance_id`),
  KEY `idx_evaluation_status` (`status`),
  CONSTRAINT `fk_evaluation_clearance` FOREIGN KEY (`clearance_id`) REFERENCES `clearances` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluation_verifications`
--

LOCK TABLES `evaluation_verifications` WRITE;
/*!40000 ALTER TABLE `evaluation_verifications` DISABLE KEYS */;
INSERT INTO `evaluation_verifications` VALUES (4,2,'ERROR','OPENAI_VISION','gpt-4o-mini',NULL,1,'2026-09-11 14:31:04',NULL,'2026-09-11 14:31:04','2026-09-11 14:31:04');
/*!40000 ALTER TABLE `evaluation_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_attendance`
--

DROP TABLE IF EXISTS `event_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `status` enum('PRESENT','ABSENT','EXCUSED') NOT NULL,
  `recorded_by` bigint(20) unsigned NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_student` (`event_id`,`student_id`),
  KEY `fk_event_attendance_recorded_by` (`recorded_by`),
  KEY `idx_event_attendance_student` (`student_id`),
  CONSTRAINT `fk_event_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_event_attendance_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_event_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attendance`
--

LOCK TABLES `event_attendance` WRITE;
/*!40000 ALTER TABLE `event_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `station_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_events_created_by` (`created_by`),
  KEY `idx_events_station_date` (`station_id`,`event_date`),
  KEY `idx_events_org_date` (`organization_id`,`event_date`),
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_events_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_events_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `library_cards`
--

DROP TABLE IF EXISTS `library_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `library_cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `status` enum('PENDING','VERIFIED','INVALID') NOT NULL DEFAULT 'PENDING',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_library_card_student` (`student_id`),
  KEY `fk_library_cards_verified_by` (`verified_by`),
  CONSTRAINT `fk_library_cards_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_library_cards_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `library_cards`
--

LOCK TABLES `library_cards` WRITE;
/*!40000 ALTER TABLE `library_cards` DISABLE KEYS */;
INSERT INTO `library_cards` VALUES (1,1,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(2,2,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(3,3,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(4,4,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(5,5,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(6,6,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(7,7,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(8,8,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(9,9,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(10,10,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(11,11,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(12,12,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(13,13,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(14,14,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(15,15,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(16,16,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(17,17,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(18,18,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(19,19,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(20,20,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(21,21,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(22,22,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(23,23,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(24,24,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(25,25,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(26,26,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(27,27,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(28,28,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(29,29,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(30,30,'PENDING',NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52');
/*!40000 ALTER TABLE `library_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_requirements`
--

DROP TABLE IF EXISTS `medical_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medical_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `status` enum('PENDING','UNDER_REVIEW','VERIFIED','RETURNED') NOT NULL DEFAULT 'PENDING',
  `remarks` text DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_student_period` (`student_id`,`academic_period_id`),
  KEY `fk_medical_period` (`academic_period_id`),
  KEY `fk_medical_verified_by` (`verified_by`),
  CONSTRAINT `fk_medical_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_medical_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_medical_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_requirements`
--

LOCK TABLES `medical_requirements` WRITE;
/*!40000 ALTER TABLE `medical_requirements` DISABLE KEYS */;
INSERT INTO `medical_requirements` VALUES (1,1,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(2,2,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(3,3,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(4,4,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(5,5,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(6,6,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(7,7,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(8,8,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(9,9,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(10,10,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(11,11,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(12,12,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(13,13,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(14,14,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(15,15,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(16,16,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(17,17,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(18,18,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(19,19,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(20,20,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(21,21,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(22,22,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(23,23,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(24,24,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(25,25,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(26,26,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(27,27,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(28,28,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(29,29,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(30,30,1,'PENDING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52');
/*!40000 ALTER TABLE `medical_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=338 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (2,22,'Clearance application started','Your clearance application has started. Instructor Evaluation verification is your first requirement.',0,'2026-09-11 14:16:17'),(10,22,'Evaluation verification unavailable','Your screenshot was not retained. Please try again when the verification service is available.',0,'2026-09-11 14:31:04');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `office_period_readiness`
--

DROP TABLE IF EXISTS `office_period_readiness`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `office_period_readiness` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `station_id` bigint(20) unsigned NOT NULL,
  `status` enum('NOT_READY','READY') NOT NULL DEFAULT 'NOT_READY',
  `ready_by` bigint(20) unsigned DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_office_period_readiness` (`academic_period_id`,`station_id`),
  KEY `idx_office_period_status` (`academic_period_id`,`status`),
  KEY `fk_office_period_station` (`station_id`),
  KEY `fk_office_period_ready_by` (`ready_by`),
  CONSTRAINT `fk_office_period_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_office_period_ready_by` FOREIGN KEY (`ready_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_office_period_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `office_period_readiness`
--

LOCK TABLES `office_period_readiness` WRITE;
/*!40000 ALTER TABLE `office_period_readiness` DISABLE KEYS */;
INSERT INTO `office_period_readiness` VALUES (1,1,1,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(2,1,2,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(3,1,3,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(4,1,4,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(5,1,5,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(6,1,6,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(7,1,7,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(8,1,8,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(9,1,9,'READY',NULL,'2026-09-11 14:16:17',NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06');
/*!40000 ALTER TABLE `office_period_readiness` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (1,'College of Computer Studies','CCS','ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(2,'College of Business Education','CBE','ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(3,'College of Teacher Education','CTE','ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22');
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requirement_records`
--

DROP TABLE IF EXISTS `requirement_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requirement_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clearance_station_id` bigint(20) unsigned NOT NULL,
  `requirement_id` bigint(20) unsigned NOT NULL,
  `status` enum('NOT_STARTED','SUBMITTED','UNDER_REVIEW','VERIFIED','RETURNED','RESOLVED','NOT_CLEAR') NOT NULL DEFAULT 'NOT_STARTED',
  `remarks` text DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clearance_requirement` (`clearance_station_id`,`requirement_id`),
  KEY `fk_requirement_records_requirement` (`requirement_id`),
  KEY `fk_requirement_records_verified_by` (`verified_by`),
  KEY `idx_requirement_records_status` (`status`),
  CONSTRAINT `fk_requirement_records_clearance_station` FOREIGN KEY (`clearance_station_id`) REFERENCES `clearance_stations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_requirement_records_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `requirements` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_requirement_records_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=554 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requirement_records`
--

LOCK TABLES `requirement_records` WRITE;
/*!40000 ALTER TABLE `requirement_records` DISABLE KEYS */;
INSERT INTO `requirement_records` VALUES (16,16,1,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(17,17,2,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(18,18,3,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(19,19,4,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(20,20,5,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(21,20,6,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(22,21,7,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(23,22,8,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(24,23,9,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(25,24,10,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17'),(26,25,11,'NOT_STARTED',NULL,NULL,NULL,'2026-09-11 14:16:17','2026-09-11 14:16:17');
/*!40000 ALTER TABLE `requirement_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requirements`
--

DROP TABLE IF EXISTS `requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `station_id` bigint(20) unsigned NOT NULL,
  `requirement_code` varchar(80) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('ATTENDANCE','DOCUMENT','PAYMENT','VIOLATION','VERIFICATION','APPROVAL','LIBRARY_BOOK','FINANCIAL') NOT NULL,
  `description` text DEFAULT NULL,
  `applicable_organization_id` bigint(20) unsigned DEFAULT NULL,
  `minimum_year_level` tinyint(3) unsigned DEFAULT NULL,
  `maximum_year_level` tinyint(3) unsigned DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_requirement_code` (`requirement_code`),
  KEY `idx_requirements_station` (`station_id`,`status`),
  KEY `idx_requirements_applicability` (`station_id`,`applicable_organization_id`,`minimum_year_level`,`maximum_year_level`,`status`),
  KEY `fk_requirements_organization` (`applicable_organization_id`),
  CONSTRAINT `fk_requirements_organization` FOREIGN KEY (`applicable_organization_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_requirements_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requirements`
--

LOCK TABLES `requirements` WRITE;
/*!40000 ALTER TABLE `requirements` DISABLE KEYS */;
INSERT INTO `requirements` VALUES (1,1,'LEGACY_1','Required Organization Events and Meetings','ATTENDANCE','Student must meet the required attendance for organization events and meetings.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(2,2,'LEGACY_2','Dean Approval','APPROVAL','Dean must approve the student clearance.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(3,3,'LEGACY_3','School Rules Violations','VIOLATION','Student must have no unresolved school rules violations.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(4,4,'LEGACY_4','Required Student Council Events','ATTENDANCE','Student must meet the required attendance for Student Council events.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(5,5,'LEGACY_5','Library Card','VERIFICATION','Student must have a verified library card.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(6,5,'LEGACY_6','Borrowed Books','LIBRARY_BOOK','All borrowed books must be returned.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(7,6,'LEGACY_7','Required Event Fees','PAYMENT','All required event fees must be paid and verified.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(8,7,'LEGACY_8','School Medical Requirement','VERIFICATION','School medical requirement must be verified.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(9,8,'LEGACY_9','Facilities Violations','VIOLATION','Student must have no unresolved facilities violations.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(10,9,'LEGACY_10','Financial Obligations','FINANCIAL','Financial obligations must be cleared.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(11,10,'LEGACY_11','Required Documents','DOCUMENT','All required registrar documents must be verified.',NULL,NULL,NULL,1,'ACTIVE','2026-08-28 14:37:22','2026-09-21 12:06:52'),(12,2,'CCS_FACULTY_EVALUATION','Faculty Evaluation Completion','VERIFICATION','Provide proof that the required faculty evaluation was completed.',1,NULL,3,1,'ACTIVE','2026-09-11 14:22:11','2026-09-21 12:06:52'),(13,2,'CBE_YBC_APPROVAL','YBC Department Organization Approval','APPROVAL','Obtain the required approval from the YBC department organization.',2,1,3,1,'ACTIVE','2026-09-21 12:06:52','2026-09-21 12:06:52'),(14,2,'CBE_OJT_REQUIREMENTS','OJT Requirements','DOCUMENT','Complete the required OJT documents and obligations.',2,4,4,1,'ACTIVE','2026-09-21 12:06:52','2026-09-21 12:06:52'),(15,2,'CCS_GRADUATION_APPLICATION','Application for Graduation','DOCUMENT','Submit and verify the application for graduation.',1,4,4,1,'ACTIVE','2026-09-21 12:06:52','2026-09-21 12:06:52');
/*!40000 ALTER TABLE `requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `semester_enrollments`
--

DROP TABLE IF EXISTS `semester_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `semester_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `status` enum('ENROLLED','REMOVED') NOT NULL DEFAULT 'ENROLLED',
  `enrolled_by` bigint(20) unsigned DEFAULT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) unsigned DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_semester_enrollment` (`academic_period_id`,`student_id`),
  KEY `idx_semester_enrollment_status` (`academic_period_id`,`status`),
  KEY `fk_semester_enrollment_student` (`student_id`),
  KEY `fk_semester_enrollment_enrolled_by` (`enrolled_by`),
  KEY `fk_semester_enrollment_removed_by` (`removed_by`),
  CONSTRAINT `fk_semester_enrollment_enrolled_by` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_semester_enrollment_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_semester_enrollment_removed_by` FOREIGN KEY (`removed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_semester_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `semester_enrollments`
--

LOCK TABLES `semester_enrollments` WRITE;
/*!40000 ALTER TABLE `semester_enrollments` DISABLE KEYS */;
INSERT INTO `semester_enrollments` VALUES (1,1,1,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(2,1,2,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(3,1,3,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(4,1,4,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(5,1,5,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(6,1,6,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(7,1,7,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(8,1,8,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(9,1,9,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(10,1,10,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(11,1,11,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(12,1,12,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(13,1,13,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(14,1,14,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(15,1,15,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(16,1,16,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(17,1,17,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(18,1,18,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(19,1,19,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(20,1,20,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(21,1,21,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(22,1,22,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(23,1,23,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(24,1,24,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(25,1,25,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(26,1,26,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(27,1,27,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(28,1,28,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(29,1,29,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06'),(30,1,30,'ENROLLED',NULL,'2026-09-21 12:27:06',NULL,NULL,'2026-09-21 12:27:06','2026-09-21 12:27:06');
/*!40000 ALTER TABLE `semester_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_assignments`
--

DROP TABLE IF EXISTS `staff_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `station_id` bigint(20) unsigned NOT NULL,
  `organization_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_station_org` (`user_id`,`station_id`,`organization_id`),
  KEY `idx_staff_assignment_station` (`station_id`,`status`),
  KEY `idx_staff_assignment_org` (`organization_id`,`status`),
  CONSTRAINT `fk_staff_assignment_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_assignment_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_assignment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_assignments`
--

LOCK TABLES `staff_assignments` WRITE;
/*!40000 ALTER TABLE `staff_assignments` DISABLE KEYS */;
INSERT INTO `staff_assignments` VALUES (1,31,1,1,'ACTIVE','2026-08-28 14:57:40','2026-08-28 14:57:40'),(2,32,1,2,'ACTIVE','2026-08-28 14:57:40','2026-08-28 14:57:40'),(3,43,1,3,'ACTIVE','2026-08-28 14:57:40','2026-08-28 14:57:40');
/*!40000 ALTER TABLE `staff_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stations`
--

DROP TABLE IF EXISTS `stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_station_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_stations_parent_status_order` (`parent_station_id`,`status`,`sort_order`),
  CONSTRAINT `fk_stations_parent` FOREIGN KEY (`parent_station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stations`
--

LOCK TABLES `stations` WRITE;
/*!40000 ALTER TABLE `stations` DISABLE KEYS */;
INSERT INTO `stations` VALUES (1,NULL,'Organization','ORGANIZATION','Organization and department clearance',2,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(2,NULL,'Dean','DEAN','Dean approval',1,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(3,NULL,'SASO','SASO','School rules and student affairs clearance',3,'ACTIVE','2026-08-28 14:37:22','2026-08-28 14:37:22'),(4,3,'Student Council','STUDENT_COUNCIL','Student Council event attendance',1,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(5,3,'Library','LIBRARY','Library card and borrowed books',2,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(6,3,'Bookstore','BOOKSTORE','Required event fees',3,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(7,3,'Clinic','CLINIC','School medical requirement',4,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(8,NULL,'Facilities','FACILITIES','Facilities violations',4,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(9,NULL,'Accounting','ACCOUNTING','Financial obligations',5,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39'),(10,NULL,'Registrar','REGISTRAR','Required documents',6,'ACTIVE','2026-08-28 14:37:22','2026-09-11 14:13:39');
/*!40000 ALTER TABLE `stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_bookstore_fees`
--

DROP TABLE IF EXISTS `student_bookstore_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_bookstore_fees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `fee_id` bigint(20) unsigned NOT NULL,
  `status` enum('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
  `paid_at` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_fee` (`student_id`,`fee_id`),
  KEY `fk_student_bookstore_fees_fee` (`fee_id`),
  KEY `fk_student_bookstore_fees_verified_by` (`verified_by`),
  KEY `idx_student_bookstore_status` (`student_id`,`status`),
  CONSTRAINT `fk_student_bookstore_fees_fee` FOREIGN KEY (`fee_id`) REFERENCES `bookstore_fees` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_bookstore_fees_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_bookstore_fees_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_bookstore_fees`
--

LOCK TABLES `student_bookstore_fees` WRITE;
/*!40000 ALTER TABLE `student_bookstore_fees` DISABLE KEYS */;
INSERT INTO `student_bookstore_fees` VALUES (1,1,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(2,1,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(3,2,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(4,2,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(5,3,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(6,3,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(7,4,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(8,4,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(9,5,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(10,5,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(11,6,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(12,6,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(13,7,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(14,7,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(15,8,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(16,8,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(17,9,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(18,9,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(19,10,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(20,10,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(21,11,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(22,11,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(23,12,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(24,12,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(25,13,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(26,13,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(27,14,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(28,14,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(29,15,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(30,15,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(31,16,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(32,16,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(33,17,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(34,17,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(35,18,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(36,18,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(37,19,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(38,19,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(39,20,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(40,20,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(41,21,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(42,21,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(43,22,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(44,22,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(45,23,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(46,23,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(47,24,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(48,24,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(49,25,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(50,25,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(51,26,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(52,26,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(53,27,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(54,27,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(55,28,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(56,28,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(57,29,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(58,29,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(59,30,1,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(60,30,2,'UNPAID',NULL,NULL,NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52');
/*!40000 ALTER TABLE `student_bookstore_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_documents`
--

DROP TABLE IF EXISTS `student_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_documents`
--

LOCK TABLES `student_documents` WRITE;
/*!40000 ALTER TABLE `student_documents` DISABLE KEYS */;
INSERT INTO `student_documents` VALUES (1,1,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(2,1,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(3,1,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(4,2,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(5,2,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(6,2,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(7,3,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(8,3,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(9,3,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(10,4,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(11,4,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(12,4,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(13,5,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(14,5,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(15,5,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(16,6,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(17,6,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(18,6,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(19,7,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(20,7,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(21,7,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(22,8,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(23,8,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(24,8,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(25,9,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(26,9,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(27,9,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(28,10,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(29,10,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(30,10,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(31,11,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(32,11,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(33,11,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(34,12,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(35,12,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(36,12,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(37,13,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(38,13,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(39,13,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(40,14,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(41,14,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(42,14,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(43,15,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(44,15,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(45,15,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(46,16,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(47,16,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(48,16,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(49,17,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(50,17,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(51,17,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(52,18,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(53,18,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(54,18,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(55,19,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(56,19,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(57,19,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(58,20,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(59,20,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(60,20,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(61,21,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(62,21,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(63,21,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(64,22,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(65,22,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(66,22,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(67,23,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(68,23,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(69,23,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(70,24,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(71,24,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(72,24,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(73,25,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(74,25,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(75,25,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(76,26,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(77,26,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(78,26,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(79,27,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(80,27,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(81,27,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(82,28,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(83,28,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(84,28,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(85,29,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(86,29,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(87,29,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(88,30,1,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(89,30,2,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52'),(90,30,3,NULL,NULL,NULL,NULL,'MISSING',NULL,NULL,NULL,'2026-09-21 12:06:52','2026-09-21 12:06:52');
/*!40000 ALTER TABLE `student_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `program` varchar(150) NOT NULL,
  `year_level` tinyint(3) unsigned NOT NULL,
  `section` varchar(50) NOT NULL,
  `organization_id` bigint(20) unsigned NOT NULL,
  `status` enum('ACTIVE','INACTIVE','GRADUATED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `idx_students_organization` (`organization_id`),
  KEY `idx_students_name` (`last_name`,`first_name`),
  KEY `idx_students_program` (`program`),
  CONSTRAINT `fk_students_organization` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,1,'DEV-2026-001','Development','Student 01','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(2,2,'DEV-2026-002','Development','Student 02','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(3,3,'DEV-2026-003','Development','Student 03','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(4,4,'DEV-2026-004','Development','Student 04','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(5,5,'DEV-2026-005','Development','Student 05','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(6,6,'DEV-2026-006','Development','Student 06','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(7,7,'DEV-2026-007','Development','Student 07','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(8,8,'DEV-2026-008','Development','Student 08','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(9,9,'DEV-2026-009','Development','Student 09','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(10,10,'DEV-2026-010','Development','Student 10','BS Computer Science',1,'A',1,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(11,11,'DEV-2026-011','Development','Student 11','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(12,12,'DEV-2026-012','Development','Student 12','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(13,13,'DEV-2026-013','Development','Student 13','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(14,14,'DEV-2026-014','Development','Student 14','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(15,15,'DEV-2026-015','Development','Student 15','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(16,16,'DEV-2026-016','Development','Student 16','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(17,17,'DEV-2026-017','Development','Student 17','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(18,18,'DEV-2026-018','Development','Student 18','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(19,19,'DEV-2026-019','Development','Student 19','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(20,20,'DEV-2026-020','Development','Student 20','BS Business Administration',1,'A',2,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(21,21,'DEV-2026-021','Development','Student 21','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(22,22,'DEV-2026-022','Development','Student 22','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(23,23,'DEV-2026-023','Development','Student 23','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(24,24,'DEV-2026-024','Development','Student 24','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(25,25,'DEV-2026-025','Development','Student 25','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(26,26,'DEV-2026-026','Development','Student 26','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(27,27,'DEV-2026-027','Development','Student 27','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(28,28,'DEV-2026-028','Development','Student 28','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(29,29,'DEV-2026-029','Development','Student 29','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49'),(30,30,'DEV-2026-030','Development','Student 30','Bachelor of Secondary Education',1,'A',3,'ACTIVE','2026-08-28 14:40:49','2026-08-28 14:40:49');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('STUDENT','ORG_STAFF','DEAN','SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','ACCOUNTING_STAFF','REGISTRAR') NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `external_auth_id` varchar(255) DEFAULT NULL,
  `auth_provider` varchar(50) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `external_auth_id` (`external_auth_id`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'student01@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,'2026-09-21 13:41:09','2026-08-28 14:40:49','2026-09-21 13:41:09'),(2,'student02@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(3,'student03@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(4,'student04@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(5,'student05@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(6,'student06@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(7,'student07@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(8,'student08@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(9,'student09@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(10,'student10@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(11,'student11@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(12,'student12@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(13,'student13@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(14,'student14@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(15,'student15@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(16,'student16@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(17,'student17@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(18,'student18@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(19,'student19@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(20,'student20@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(21,'student21@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(22,'student22@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(23,'student23@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(24,'student24@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(25,'student25@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(26,'student26@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(27,'student27@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(28,'student28@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(29,'student29@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(30,'student30@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(31,'orgstaff01@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','ORG_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(32,'orgstaff02@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','ORG_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(33,'dean@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','DEAN','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(34,'saso@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','SASO_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(35,'studentcouncil@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','STUDENT_COUNCIL_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(36,'library@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','LIBRARY_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(37,'bookstore@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','BOOKSTORE_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(38,'clinic@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','CLINIC_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(39,'facilities@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','FACILITIES_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(40,'accounting@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','ACCOUNTING_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:40:49','2026-09-21 13:34:14'),(41,'registrar@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','REGISTRAR','ACTIVE',NULL,NULL,'2026-09-21 13:41:10','2026-08-28 14:40:49','2026-09-21 13:41:10'),(43,'orgstaff03@thelewiscollege.edu.ph','$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq','ORG_STAFF','ACTIVE',NULL,NULL,NULL,'2026-08-28 14:57:40','2026-09-21 13:34:14');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `violations`
--

DROP TABLE IF EXISTS `violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `station_id` bigint(20) unsigned NOT NULL,
  `description` text NOT NULL,
  `status` enum('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN',
  `recorded_by` bigint(20) unsigned NOT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_violations_recorded_by` (`recorded_by`),
  KEY `fk_violations_resolved_by` (`resolved_by`),
  KEY `idx_violations_student_status` (`student_id`,`status`),
  KEY `idx_violations_station_status` (`station_id`,`status`),
  CONSTRAINT `fk_violations_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_violations_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_violations_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_violations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `violations`
--

LOCK TABLES `violations` WRITE;
/*!40000 ALTER TABLE `violations` DISABLE KEYS */;
/*!40000 ALTER TABLE `violations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'lewis_clearance'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-21 13:57:11
