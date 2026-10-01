-- A5.1 schema migration registry foundation.
-- Idempotent DDL only: this migration creates no application or customer data.

CREATE TABLE IF NOT EXISTS `sys_schema_migration` (
  `migration_id` VARCHAR(128) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `checksum_sha256` CHAR(64) NOT NULL,
  `catalog_version` SMALLINT UNSIGNED NOT NULL,
  `tool_version` VARCHAR(32) NOT NULL,
  `classification` VARCHAR(32) NOT NULL,
  `policies` VARCHAR(255) NOT NULL,
  `batch_id` VARCHAR(64) DEFAULT NULL,
  `applied_by` VARCHAR(128) DEFAULT NULL,
  `execution_ms` INT UNSIGNED DEFAULT NULL,
  `metadata_json` LONGTEXT DEFAULT NULL,
  `applied_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`migration_id`),
  UNIQUE KEY `uq_sys_schema_migration_filename` (`filename`),
  KEY `idx_sys_schema_migration_applied_at` (`applied_at`),
  KEY `idx_sys_schema_migration_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
