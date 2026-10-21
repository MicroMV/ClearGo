-- Empty retired table structure; confirmed zero rows before removal.
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
